<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Custom report builder for the AvanaHR tenant. Reports are defined against a
 * HARD allowlist of entities, columns and filters — user input is never
 * interpolated into raw SQL or column/table names. Allowlisted keys map only
 * to query-builder calls, and every query is scoped by tenant_id. The stored
 * report definition is re-validated against the allowlist at run/export time,
 * so a tampered row can never widen the column set or inject a filter column.
 */
class DynamicReportController extends Controller
{
    /**
     * Roles that may always build reports within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Hard cap on rows materialised for a single report run/export.
     */
    private const MAX_ROWS = 2000;

    /**
     * Render the report builder: saved reports plus allowlist metadata.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;
        $schema = $this->entitySchema();

        $reports = SavedReport::forTenant($tenantId)
            ->latest('id')
            ->get()
            ->map(fn (SavedReport $report): array => $this->transformReport($report, $schema))
            ->all();

        return Inertia::render('avana/dynamic-report/index', [
            'reports' => $reports,
            'entities' => $this->entityOptions($schema),
        ]);
    }

    /**
     * Persist a new report definition after sanitising it against the allowlist.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $schema = $this->entitySchema();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity' => ['required', 'string', Rule::in(array_keys($schema))],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'filters' => ['nullable', 'array'],
        ]);

        $definition = $schema[$validated['entity']];

        // Keep only allowlisted columns, preserving the canonical column order.
        $columns = array_values(array_intersect(
            array_keys($definition['columns']),
            $validated['columns'],
        ));

        if ($columns === []) {
            throw ValidationException::withMessages([
                'columns' => 'Pilih minimal satu kolom yang valid.',
            ]);
        }

        $filters = $this->sanitizeFilters($definition, $validated['filters'] ?? []);

        SavedReport::create([
            'tenant_id' => (int) $request->user()->tenant_id,
            'name' => $validated['name'],
            'entity' => $validated['entity'],
            'columns' => $columns,
            'filters' => $filters !== [] ? $filters : null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('avana.dynamic-report')
            ->with('success', 'Laporan berhasil disimpan');
    }

    /**
     * Delete a saved report.
     */
    public function destroy(Request $request, SavedReport $report): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $report);

        $report->delete();

        return back()->with('success', 'Laporan dihapus');
    }

    /**
     * Build and preview a saved report's dataset.
     */
    public function run(Request $request, SavedReport $report): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $report);

        $schema = $this->entitySchema();
        [$headers, $rows] = $this->buildDataset($report, $schema);

        return Inertia::render('avana/dynamic-report/show', [
            'report' => $this->transformReport($report, $schema),
            'headers' => $headers,
            'rows' => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * Stream a saved report's dataset as a CSV download.
     */
    public function export(Request $request, SavedReport $report): StreamedResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $report);

        $schema = $this->entitySchema();
        [$headers, $rows] = $this->buildDataset($report, $schema);

        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($report->name)) ?: 'laporan';
        $filename = 'laporan-'.trim($slug, '-').'-'.Carbon::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Materialise a saved report into [headers, rows], re-validating its stored
     * columns and filters against the allowlist as a defence-in-depth measure.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|null>>}
     */
    private function buildDataset(SavedReport $report, array $schema): array
    {
        $entity = (string) $report->entity;
        abort_unless(isset($schema[$entity]), 404);

        $definition = $schema[$entity];
        $allowedColumns = $definition['columns'];

        $selected = array_values(array_intersect(
            array_keys($allowedColumns),
            (array) $report->columns,
        ));

        if ($selected === []) {
            $selected = [array_key_first($allowedColumns)];
        }

        $query = $this->baseQuery($entity, (int) $report->tenant_id);

        if (! empty($definition['with'])) {
            $query->with($definition['with']);
        }

        foreach ($this->sanitizeFilters($definition, (array) $report->filters) as $key => $value) {
            $filter = $definition['filters'][$key];

            if ($filter['type'] === 'like') {
                $query->where($filter['column'], 'like', '%'.$value.'%');
            } else {
                $query->where($filter['column'], '=', $value);
            }
        }

        $records = $query->orderByDesc('id')->limit(self::MAX_ROWS)->get();

        $headers = array_map(
            fn (string $key): string => (string) $allowedColumns[$key]['label'],
            $selected,
        );

        $rows = $records
            ->map(fn (Model $record): array => array_map(
                fn (string $key) => ($allowedColumns[$key]['get'])($record),
                $selected,
            ))
            ->all();

        return [$headers, $rows];
    }

    /**
     * Resolve the tenant-scoped base query for an allowlisted entity. Each arm
     * is a fixed model — the entity key never reaches a table name directly.
     */
    private function baseQuery(string $entity, int $tenantId): Builder
    {
        return match ($entity) {
            'employees' => Employee::query()->forTenant($tenantId),
            'leave' => LeaveRequest::query()->forTenant($tenantId),
            'claims' => Claim::query()->forTenant($tenantId),
            'attendance' => Attendance::query()->forTenant($tenantId),
        };
    }

    /**
     * Reduce raw filter input to allowlisted, scalar, non-empty values keyed by
     * the entity's allowlisted filter keys.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function sanitizeFilters(array $definition, array $raw): array
    {
        $allowed = $definition['filters'] ?? [];
        $clean = [];

        foreach ($raw as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * Build the `{ id, name, entity, columns, filters }` row consumed by the UI.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<string, mixed>
     */
    private function transformReport(SavedReport $report, array $schema): array
    {
        $definition = $schema[$report->entity] ?? null;
        $columns = (array) $report->columns;

        $columnLabels = $definition !== null
            ? array_values(array_map(
                fn (string $key): string => (string) ($definition['columns'][$key]['label'] ?? $key),
                array_values(array_intersect(array_keys($definition['columns']), $columns)),
            ))
            : $columns;

        return [
            'id' => $report->id,
            'name' => $report->name,
            'entity' => $report->entity,
            'entity_label' => $definition['label'] ?? $report->entity,
            'columns' => $columns,
            'column_labels' => $columnLabels,
            'filters' => (array) ($report->filters ?? []),
            'created_at' => $report->created_at?->toDateString(),
        ];
    }

    /**
     * Build the builder option metadata (entities, columns, filters) for the UI.
     *
     * @param  array<string, array<string, mixed>>  $schema
     * @return array<int, array<string, mixed>>
     */
    private function entityOptions(array $schema): array
    {
        $options = [];

        foreach ($schema as $key => $definition) {
            $columns = [];
            foreach ($definition['columns'] as $columnKey => $column) {
                $columns[] = ['key' => $columnKey, 'label' => $column['label']];
            }

            $filters = [];
            foreach ($definition['filters'] ?? [] as $filterKey => $filter) {
                $filters[] = [
                    'key' => $filterKey,
                    'label' => $filter['label'],
                    'type' => $filter['type'],
                    'options' => $filter['options'] ?? [],
                ];
            }

            $options[] = [
                'key' => $key,
                'label' => $definition['label'],
                'columns' => $columns,
                'filters' => $filters,
            ];
        }

        return $options;
    }

    /**
     * The hard allowlist: every selectable entity, its columns (key => label +
     * value resolver) and its filters (key => label + DB column + match type).
     * Column resolvers read already-loaded model attributes/relations only —
     * no user value ever names a column.
     *
     * @return array<string, array{
     *     label: string,
     *     with: array<int, string>,
     *     columns: array<string, array{label: string, get: callable}>,
     *     filters: array<string, array{label: string, column: string, type: string, options?: array<int, array{value: string, label: string}>}>
     * }>
     */
    private function entitySchema(): array
    {
        return [
            'employees' => [
                'label' => 'Karyawan',
                'with' => ['department:id,name'],
                'columns' => [
                    'employee_number' => ['label' => 'Nomor', 'get' => fn (Employee $e) => $e->employee_number],
                    'full_name' => ['label' => 'Nama', 'get' => fn (Employee $e) => $e->full_name],
                    'email' => ['label' => 'Email', 'get' => fn (Employee $e) => $e->email],
                    'department' => ['label' => 'Departemen', 'get' => fn (Employee $e) => $e->department?->name],
                    'employment_status' => ['label' => 'Status Kerja', 'get' => fn (Employee $e) => $e->employment_status],
                    'status' => ['label' => 'Status', 'get' => fn (Employee $e) => $e->status],
                    'join_date' => ['label' => 'Tgl Masuk', 'get' => fn (Employee $e) => $e->join_date?->format('Y-m-d')],
                ],
                'filters' => [
                    'full_name' => ['label' => 'Nama', 'column' => 'full_name', 'type' => 'like'],
                    'status' => [
                        'label' => 'Status',
                        'column' => 'status',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'active', 'label' => 'Aktif'],
                            ['value' => 'inactive', 'label' => 'Nonaktif'],
                        ],
                    ],
                    'employment_status' => [
                        'label' => 'Status Kerja',
                        'column' => 'employment_status',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'permanent', 'label' => 'Tetap'],
                            ['value' => 'contract', 'label' => 'Kontrak'],
                            ['value' => 'probation', 'label' => 'Probation'],
                            ['value' => 'resigned', 'label' => 'Resign'],
                        ],
                    ],
                ],
            ],
            'leave' => [
                'label' => 'Cuti',
                'with' => ['employee:id,full_name', 'leaveType:id,name'],
                'columns' => [
                    'employee' => ['label' => 'Nama', 'get' => fn (LeaveRequest $l) => $l->employee?->full_name],
                    'leave_type' => ['label' => 'Jenis', 'get' => fn (LeaveRequest $l) => $l->leaveType?->name],
                    'start_date' => ['label' => 'Mulai', 'get' => fn (LeaveRequest $l) => $l->start_date?->format('Y-m-d')],
                    'end_date' => ['label' => 'Selesai', 'get' => fn (LeaveRequest $l) => $l->end_date?->format('Y-m-d')],
                    'total_days' => ['label' => 'Total Hari', 'get' => fn (LeaveRequest $l) => (int) $l->total_days],
                    'status' => ['label' => 'Status', 'get' => fn (LeaveRequest $l) => $l->status],
                ],
                'filters' => [
                    'status' => [
                        'label' => 'Status',
                        'column' => 'status',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'pending', 'label' => 'Menunggu'],
                            ['value' => 'approved', 'label' => 'Disetujui'],
                            ['value' => 'rejected', 'label' => 'Ditolak'],
                        ],
                    ],
                ],
            ],
            'claims' => [
                'label' => 'Klaim',
                'with' => ['employee:id,full_name'],
                'columns' => [
                    'employee' => ['label' => 'Nama', 'get' => fn (Claim $c) => $c->employee?->full_name],
                    'claim_type' => ['label' => 'Jenis', 'get' => fn (Claim $c) => $c->claim_type],
                    'title' => ['label' => 'Judul', 'get' => fn (Claim $c) => $c->title],
                    'amount' => ['label' => 'Jumlah', 'get' => fn (Claim $c) => (int) $c->amount],
                    'claim_date' => ['label' => 'Tanggal', 'get' => fn (Claim $c) => $c->claim_date?->format('Y-m-d')],
                    'status' => ['label' => 'Status', 'get' => fn (Claim $c) => $c->status],
                ],
                'filters' => [
                    'status' => [
                        'label' => 'Status',
                        'column' => 'status',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'pending', 'label' => 'Menunggu'],
                            ['value' => 'approved', 'label' => 'Disetujui'],
                            ['value' => 'rejected', 'label' => 'Ditolak'],
                            ['value' => 'paid', 'label' => 'Dibayar'],
                        ],
                    ],
                    'claim_type' => [
                        'label' => 'Jenis',
                        'column' => 'claim_type',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'medical', 'label' => 'Medis'],
                            ['value' => 'transport', 'label' => 'Transport'],
                            ['value' => 'meal', 'label' => 'Makan'],
                            ['value' => 'glasses', 'label' => 'Kacamata'],
                            ['value' => 'other', 'label' => 'Lainnya'],
                        ],
                    ],
                ],
            ],
            'attendance' => [
                'label' => 'Absensi',
                'with' => ['employee:id,full_name'],
                'columns' => [
                    'employee' => ['label' => 'Nama', 'get' => fn (Attendance $a) => $a->employee?->full_name],
                    'date' => ['label' => 'Tanggal', 'get' => fn (Attendance $a) => $a->date?->format('Y-m-d')],
                    'clock_in_at' => ['label' => 'Masuk', 'get' => fn (Attendance $a) => $a->clock_in_at?->format('H:i')],
                    'clock_out_at' => ['label' => 'Keluar', 'get' => fn (Attendance $a) => $a->clock_out_at?->format('H:i')],
                    'late_minutes' => ['label' => 'Telat (menit)', 'get' => fn (Attendance $a) => (int) $a->late_minutes],
                    'status' => ['label' => 'Status', 'get' => fn (Attendance $a) => $a->status],
                ],
                'filters' => [
                    'status' => [
                        'label' => 'Status',
                        'column' => 'status',
                        'type' => 'equals',
                        'options' => [
                            ['value' => 'present', 'label' => 'Hadir'],
                            ['value' => 'late', 'label' => 'Terlambat'],
                            ['value' => 'absent', 'label' => 'Alpa'],
                            ['value' => 'leave', 'label' => 'Cuti'],
                            ['value' => 'incomplete', 'label' => 'Belum Lengkap'],
                            ['value' => 'need_correction', 'label' => 'Perlu Koreksi'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Abort with 404 when the saved report does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, SavedReport $report): void
    {
        abort_if((int) $report->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
