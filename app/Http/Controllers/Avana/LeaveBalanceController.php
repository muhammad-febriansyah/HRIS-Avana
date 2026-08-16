<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Exports\LeaveBalanceTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\LeaveBalanceRowsImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveBalanceProvisioner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Saldo Cuti": the yearly leave quota each employee holds.
 *
 * The rows themselves are opened in bulk by {@see LeaveBalanceProvisioner} —
 * this screen is where HR sees them and overrides the ones that differ from the
 * leave type's default (longer service, a negotiated package, a mid-year join).
 *
 * `used` is never edited by hand: it is the sum of approved leave and is
 * recomputed from the requests on record, so the number always agrees with the
 * history behind it.
 */
class LeaveBalanceController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * One page of employees with their balances for the chosen year.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;
        $year = $this->resolveYear($request);

        $query = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->with(['department:id,name'])
            ->when($request->query('search'), function ($builder, string $search): void {
                $builder->where(function ($scoped) use ($search): void {
                    $scoped->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('department_id'), fn ($builder, $id) => $builder->where('department_id', $id))
            ->orderBy('full_name');

        $this->applyBranchScope($query, $request->user());

        $employees = $query->paginate($request->integer('per_page', 15))->withQueryString();

        $balances = LeaveBalance::forTenant($tenantId)
            ->forLiveTypes()
            ->where('year', $year)
            ->whereIn('employee_id', $employees->getCollection()->modelKeys())
            ->get()
            ->groupBy('employee_id');

        $types = $this->quotaOwners($tenantId);

        return Inertia::render('avana/saldo-cuti/index', [
            'rows' => [
                'data' => $employees->getCollection()->map(
                    fn (Employee $employee): array => $this->shapeEmployee($employee, $balances->get($employee->getKey()), $types)
                )->values(),
                'meta' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total(),
                ],
            ],
            'leaveTypes' => $types->map(fn (LeaveType $type): array => [
                'id' => $type->getKey(),
                'name' => $type->name,
                'default_quota' => (float) $type->default_quota,
            ])->values(),
            'departments' => Department::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                ]),
            'filters' => [
                'search' => $request->query('search'),
                'department_id' => $request->query('department_id'),
                'year' => $year,
                'per_page' => $request->integer('per_page', 15),
            ],
            'years' => $this->selectableYears($tenantId, $year),
            'kpis' => $this->kpis($tenantId, $year),
        ]);
    }

    /**
     * Open the year's balances for everyone who has none yet.
     */
    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;
        $year = $this->resolveYear($request);

        $created = LeaveBalanceProvisioner::forTenant($tenantId, $year);
        $corrected = LeaveBalanceProvisioner::syncUsed($tenantId, $year);

        return back()->with('success', $created === 0 && $corrected === 0
            ? "Saldo {$year} sudah lengkap, tidak ada yang perlu dibuat."
            : "Saldo {$year}: {$created} baris dibuat, {$corrected} baris disesuaikan.");
    }

    /**
     * Roll the previous year's leftovers into the chosen year's quota.
     */
    public function carryOver(Request $request): RedirectResponse
    {
        $this->authorize('update', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;
        $year = $this->resolveYear($request);

        $data = $request->validate([
            'max_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);

        $adjusted = LeaveBalanceProvisioner::carryOver(
            $tenantId,
            $year - 1,
            $year,
            isset($data['max_days']) ? (float) $data['max_days'] : null,
        );

        return back()->with('success', $adjusted === 0
            ? 'Tidak ada sisa cuti '.($year - 1).' yang bisa dibawa ke '.$year.'.'
            : "{$adjusted} saldo diperbarui dengan sisa ".($year - 1).'.');
    }

    /**
     * Override one employee's quota for one leave type.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'quota' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);

        $this->writeQuota($tenantId, (int) $data['employee_id'], (int) $data['leave_type_id'], (int) $data['year'], (float) $data['quota']);

        return back()->with('success', 'Saldo cuti diperbarui');
    }

    /**
     * The fill-in workbook: every active employee against every quota-owning
     * leave type, pre-filled with the quota already on file.
     */
    public function template(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;
        $year = $this->resolveYear($request);
        $types = $this->quotaOwners($tenantId);

        $balances = LeaveBalance::forTenant($tenantId)
            ->where('year', $year)
            ->get()
            ->keyBy(fn (LeaveBalance $balance): string => $balance->employee_id.':'.$balance->leave_type_id);

        $rows = [];

        foreach (Employee::forTenant($tenantId)->where('status', 'active')->orderBy('full_name')->get() as $employee) {
            foreach ($types as $type) {
                $rows[] = [
                    (string) ($employee->employee_number ?? ''),
                    (string) $employee->full_name,
                    (string) $type->name,
                    (string) (float) ($balances->get($employee->getKey().':'.$type->getKey())?->quota ?? $type->default_quota),
                ];
            }
        }

        return Excel::download(new LeaveBalanceTemplateExport($rows, $year), "template-saldo-cuti-{$year}.xlsx");
    }

    /**
     * Read a filled-in workbook back in.
     *
     * All or nothing: one unusable row rejects the file rather than leaving
     * half the tenant on new quotas and half on the old ones.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('update', LeaveType::class);

        $tenantId = (int) $request->user()->tenant_id;

        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:8192'],
        ], [
            'file.mimes' => 'Unggah berkas .xlsx, .xls atau .csv.',
        ]);

        $year = $request->integer('year');
        $sheet = Excel::toArray(new LeaveBalanceRowsImport, $request->file('file'))[0] ?? [];

        if ($sheet !== [] && $this->looksLikeHeader($sheet[0])) {
            array_shift($sheet);
        }

        $employees = Employee::forTenant($tenantId)
            ->whereNotNull('employee_number')
            ->get(['id', 'employee_number'])
            ->keyBy(fn (Employee $employee): string => $this->normalise((string) $employee->employee_number));

        $types = $this->quotaOwners($tenantId)
            ->keyBy(fn (LeaveType $type): string => $this->normalise((string) $type->name));

        $parsed = [];
        $errors = [];

        foreach ($sheet as $index => $raw) {
            $line = $index + 2; // +1 for the stripped header, +1 for 1-based rows
            $cells = array_map(static fn ($value): string => trim((string) ($value ?? '')), array_values((array) $raw));

            if (implode('', $cells) === '') {
                continue;
            }

            [$number, , $typeName, $quota] = array_pad($cells, 4, '');

            $employee = $employees->get($this->normalise($number));
            $type = $types->get($this->normalise($typeName));

            if ($employee === null) {
                $errors[] = "Baris {$line}: nomor karyawan \"{$number}\" tidak ditemukan.";

                continue;
            }

            if ($type === null) {
                $errors[] = "Baris {$line}: jenis cuti \"{$typeName}\" tidak dikenal.";

                continue;
            }

            $days = $this->parseDays($quota);

            if ($days === null) {
                $errors[] = "Baris {$line}: kuota \"{$quota}\" bukan angka hari yang sah.";

                continue;
            }

            $parsed[] = [$employee->getKey(), $type->getKey(), $days];
        }

        if ($errors !== []) {
            return back()->withErrors(['file' => implode(' ', array_slice($errors, 0, 5))]);
        }

        if ($parsed === []) {
            return back()->withErrors(['file' => 'Berkas tidak berisi baris saldo yang bisa dibaca.']);
        }

        DB::transaction(function () use ($tenantId, $year, $parsed): void {
            foreach ($parsed as [$employeeId, $typeId, $days]) {
                $this->writeQuota($tenantId, (int) $employeeId, (int) $typeId, $year, (float) $days);
            }
        });

        return back()->with('success', count($parsed).' saldo cuti diperbarui dari berkas.');
    }

    /**
     * Write a quota, keeping `used` as the approved history says and deriving
     * `remaining` from the two. Creates the row when the year was never opened.
     */
    private function writeQuota(int $tenantId, int $employeeId, int $leaveTypeId, int $year, float $quota): void
    {
        $balance = LeaveBalance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        $used = (float) ($balance?->used ?? 0);

        if ($balance === null) {
            LeaveBalance::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
                'quota' => $quota,
                'used' => $used,
                'remaining' => max(0, $quota - $used),
            ]);

            return;
        }

        $balance->update([
            'quota' => $quota,
            'remaining' => max(0, $quota - $used),
        ]);
    }

    /**
     * One table row: the employee plus a cell per quota-owning leave type.
     *
     * @param  Collection<int, LeaveBalance>|null  $balances
     * @param  Collection<int, LeaveType>  $types
     * @return array<string, mixed>
     */
    private function shapeEmployee(Employee $employee, ?Collection $balances, Collection $types): array
    {
        $byType = ($balances ?? collect())->keyBy('leave_type_id');

        return [
            'employee_id' => $employee->getKey(),
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'department' => $employee->department?->name,
            'balances' => $types->map(function (LeaveType $type) use ($byType): array {
                $balance = $byType->get($type->getKey());

                return [
                    'leave_type_id' => $type->getKey(),
                    'leave_type' => $type->name,
                    // No row yet: the year has not been opened for this person,
                    // which the screen shows as "—" rather than a hopeful zero.
                    'has_balance' => $balance !== null,
                    'quota' => $balance !== null ? (float) $balance->quota : null,
                    'used' => $balance !== null ? (float) $balance->used : null,
                    'remaining' => $balance !== null ? (float) $balance->remaining : null,
                ];
            })->values(),
        ];
    }

    /**
     * Headline numbers for the year: how many employees still have no balance,
     * and the totals across the tenant.
     *
     * @return array<string, float|int>
     */
    private function kpis(int $tenantId, int $year): array
    {
        $active = Employee::forTenant($tenantId)->where('status', 'active')->count();

        $covered = LeaveBalance::forTenant($tenantId)
            ->where('year', $year)
            ->distinct()
            ->count('employee_id');

        $totals = LeaveBalance::forTenant($tenantId)
            ->forLiveTypes()
            ->where('year', $year)
            ->selectRaw('COALESCE(SUM(quota), 0) as quota, COALESCE(SUM(used), 0) as used, COALESCE(SUM(remaining), 0) as remaining')
            ->first();

        return [
            'employees' => $active,
            'covered' => $covered,
            'uncovered' => max(0, $active - $covered),
            'quota' => (float) ($totals->quota ?? 0),
            'used' => (float) ($totals->used ?? 0),
            'remaining' => (float) ($totals->remaining ?? 0),
        ];
    }

    /**
     * The tenant's quota-owning leave types (roots that carry days).
     *
     * @return Collection<int, LeaveType>
     */
    private function quotaOwners(int $tenantId): Collection
    {
        return LeaveType::forTenant($tenantId)
            ->roots()
            ->where('status', 'active')
            ->where('default_quota', '>', 0)
            ->orderBy('name')
            ->get();
    }

    /**
     * Years worth offering in the picker: whatever has balances, plus the
     * current and next year so a new one can always be opened.
     *
     * @return array<int, int>
     */
    private function selectableYears(int $tenantId, int $current): array
    {
        $years = LeaveBalance::forTenant($tenantId)
            ->select('year')
            ->distinct()
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->all();

        $years[] = (int) now()->year;
        $years[] = (int) now()->year + 1;
        $years[] = $current;

        $years = array_values(array_unique($years));
        rsort($years);

        return $years;
    }

    private function resolveYear(Request $request): int
    {
        $year = $request->integer('year', (int) now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }

    /**
     * Whether a sheet's first row is the header rather than data.
     *
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $first = strtolower(trim((string) ($row[0] ?? '')));

        return in_array($first, ['nomor_karyawan', 'nomor karyawan', 'nomor', 'nik', 'employee_number'], true);
    }

    /**
     * Compare employee numbers and type names without tripping over case or
     * stray spaces typed into the spreadsheet.
     */
    private function normalise(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }

    /**
     * Read a day count that may have been typed the Indonesian way ("12,5").
     */
    private function parseDays(string $raw): ?float
    {
        $value = str_replace(',', '.', trim($raw));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $days = (float) $value;

        return $days >= 0 && $days <= 365 ? $days : null;
    }
}
