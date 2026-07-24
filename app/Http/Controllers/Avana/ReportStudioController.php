<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveBalance;
use App\Models\PayrollComponent;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Drag-and-drop pivot report builder for the AvanaHR tenant ("Report Studio").
 *
 * Rows/Columns are chosen from an allowlist of employee dimensions and Values
 * from an allowlist of numeric measures; a tampered request can only ever pick
 * from these keys — no user string reaches a column or table name. Every query
 * is tenant-scoped, and the pivot is computed in PHP over an aggregate dataset
 * so individual salaries are never sent to the client, only aggregated cells.
 */
class ReportStudioController extends Controller
{
    /** Permission module (shared with the existing report builders). */
    private const MODULE = 'dynamic_report';

    /** Hard cap on distinct row groups returned for one pivot. */
    private const MAX_ROWS = 300;

    /**
     * Render the builder shell: field palette, measures, and starter templates.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        return Inertia::render('avana/report-studio/index', [
            'dimensions' => $this->dimensionOptions(),
            'measures' => $this->measureOptions(),
            'templates' => $this->templates(),
        ]);
    }

    /**
     * Compute a pivot from the posted config and return it as JSON (live preview).
     */
    public function run(Request $request): JsonResponse
    {
        $this->ensureCan($request, 'view');

        $config = $this->extractConfig($request);

        if ($config['rows'] === [] || $config['values'] === []) {
            return response()->json([
                'columns' => [],
                'rows' => [],
                'chart' => [],
                'meta' => ['empty' => true],
            ]);
        }

        return response()->json(
            $this->pivot($this->buildDataset((int) $request->user()->tenant_id), $config),
        );
    }

    /**
     * Stream the current pivot as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->ensureCan($request, 'export');

        $config = $this->extractConfig($request);
        $result = ($config['rows'] === [] || $config['values'] === [])
            ? ['columns' => [], 'rows' => []]
            : $this->pivot($this->buildDataset((int) $request->user()->tenant_id), $config);

        $rowFieldLabels = array_map(
            fn (string $key): string => $this->dimensions()[$key]['label'],
            $config['rows'],
        );

        $filename = 'report-studio-'.Carbon::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($result, $rowFieldLabels): void {
            $out = fopen('php://output', 'w');

            $header = array_merge(
                $rowFieldLabels !== [] ? $rowFieldLabels : ['Baris'],
                array_map(fn (array $c): string => $c['label'], $result['columns']),
            );
            fputcsv($out, $header);

            foreach ($result['rows'] as $row) {
                fputcsv($out, array_merge([$row['label']], array_map(
                    static fn ($cell) => $cell ?? '',
                    $row['cells'],
                )));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Reduce raw request input to an allowlisted, de-duplicated pivot config.
     *
     * @return array{rows: array<int, string>, columns: array<int, string>, values: array<int, array{field: string, agg: string}>}
     */
    private function extractConfig(Request $request): array
    {
        $payload = $request->input('payload');
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $data = is_array($decoded) ? $decoded : [];
        } else {
            $data = $request->only(['rows', 'columns', 'values']);
        }

        $dimensions = $this->dimensions();
        $measures = $this->measures();

        $rows = array_values(array_intersect(
            array_keys($dimensions),
            array_map('strval', (array) ($data['rows'] ?? [])),
        ));

        $columns = array_values(array_diff(
            array_intersect(
                array_keys($dimensions),
                array_map('strval', (array) ($data['columns'] ?? [])),
            ),
            $rows,
        ));

        $values = [];
        $seen = [];
        foreach ((array) ($data['values'] ?? []) as $value) {
            $field = is_array($value) ? ($value['field'] ?? null) : null;
            if (! is_string($field) || ! isset($measures[$field]) || isset($seen[$field])) {
                continue;
            }

            $agg = is_array($value) ? ($value['agg'] ?? null) : null;
            if (! in_array($agg, $measures[$field]['aggs'], true)) {
                $agg = $measures[$field]['default_agg'];
            }

            $values[] = ['field' => $field, 'agg' => $agg];
            $seen[$field] = true;
        }

        return ['rows' => $rows, 'columns' => $columns, 'values' => $values];
    }

    /**
     * Build the per-employee dataset: resolved dimension labels + raw measures.
     *
     * @return array<int, array{dims: array<string, string>, measures: array<string, float|int|null>}>
     */
    private function buildDataset(int $tenantId): array
    {
        $today = Carbon::today();
        $todayStr = $today->toDateString();

        // Sum of remaining leave across all leave types for the current year.
        $sisaCuti = LeaveBalance::forTenant($tenantId)
            ->where('year', (int) $today->year)
            ->selectRaw('employee_id, SUM(remaining) as total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        // Effective basic salary (the BASIC payroll component) per employee.
        $basicId = PayrollComponent::where('tenant_id', $tenantId)->where('code', 'BASIC')->value('id');
        $gajiPokok = $basicId === null
            ? collect()
            : EmployeeSalaryComponent::forTenant($tenantId)
                ->where('payroll_component_id', $basicId)
                ->where('effective_start_date', '<=', $todayStr)
                ->where(function ($query) use ($todayStr): void {
                    $query->whereNull('effective_end_date')->orWhere('effective_end_date', '>=', $todayStr);
                })
                ->selectRaw('employee_id, SUM(amount) as total')
                ->groupBy('employee_id')
                ->pluck('total', 'employee_id');

        // Latest scored performance review per employee.
        $skor = PerformanceReview::where('tenant_id', $tenantId)
            ->whereNotNull('final_score')
            ->orderByDesc('review_date')
            ->orderByDesc('id')
            ->get(['employee_id', 'final_score'])
            ->groupBy('employee_id')
            ->map(fn ($group): float => (float) $group->first()->final_score);

        $employees = Employee::forTenant($tenantId)
            ->with(['department:id,name', 'branch:id,name', 'position:id,name'])
            ->get(['id', 'department_id', 'branch_id', 'position_id', 'employment_status', 'status', 'join_date', 'resign_date']);

        $dataset = [];
        foreach ($employees as $employee) {
            $isActive = $employee->status === 'active';
            $isResign = $employee->resign_date !== null
                || $employee->status === 'inactive'
                || $employee->employment_status === 'resigned';

            $dataset[] = [
                'dims' => [
                    'department' => $employee->department?->name ?? '—',
                    'branch' => $employee->branch?->name ?? '—',
                    'employment_status' => $this->employmentLabel($employee->employment_status),
                    'position' => $employee->position?->name ?? '—',
                ],
                'measures' => [
                    'count' => $isActive ? 1 : 0,
                    'resign' => $isResign ? 1 : 0,
                    'sisa_cuti' => isset($sisaCuti[$employee->id]) ? (float) $sisaCuti[$employee->id] : null,
                    'masa_kerja' => $employee->join_date !== null ? $employee->join_date->diffInYears($today) : null,
                    'gaji_pokok' => isset($gajiPokok[$employee->id]) ? (float) $gajiPokok[$employee->id] : null,
                    'skor_kinerja' => $skor[$employee->id] ?? null,
                ],
            ];
        }

        return $dataset;
    }

    /**
     * Aggregate the dataset into a Rows × Columns × Values pivot.
     *
     * @param  array<int, array{dims: array<string, string>, measures: array<string, float|int|null>}>  $dataset
     * @param  array{rows: array<int, string>, columns: array<int, string>, values: array<int, array{field: string, agg: string}>}  $config
     * @return array<string, mixed>
     */
    private function pivot(array $dataset, array $config): array
    {
        $measures = $this->measures();
        $hasColumns = $config['columns'] !== [];
        $multiValue = count($config['values']) > 1;

        $rowLabels = [];
        $colLabels = [];
        // acc[rowKey][colKey][valueIndex] = ['sum' => float, 'n' => int]
        $acc = [];
        // chart[rowKey] = ['sum' => float, 'n' => int] for the first value only
        $chart = [];

        foreach ($dataset as $record) {
            $rowKey = $this->groupKey($record['dims'], $config['rows']);
            $colKey = $hasColumns ? $this->groupKey($record['dims'], $config['columns']) : '';
            $rowLabels[$rowKey] = $this->groupLabel($record['dims'], $config['rows']);
            if ($hasColumns) {
                $colLabels[$colKey] = $this->groupLabel($record['dims'], $config['columns']);
            }

            foreach ($config['values'] as $index => $value) {
                $raw = $record['measures'][$value['field']];
                if ($raw === null) {
                    continue;
                }
                $acc[$rowKey][$colKey][$index]['sum'] = ($acc[$rowKey][$colKey][$index]['sum'] ?? 0) + $raw;
                $acc[$rowKey][$colKey][$index]['n'] = ($acc[$rowKey][$colKey][$index]['n'] ?? 0) + 1;
            }

            $first = $config['values'][0];
            $firstRaw = $record['measures'][$first['field']];
            if ($firstRaw !== null) {
                $chart[$rowKey]['sum'] = ($chart[$rowKey]['sum'] ?? 0) + $firstRaw;
                $chart[$rowKey]['n'] = ($chart[$rowKey]['n'] ?? 0) + 1;
            }
        }

        ksort($rowLabels);
        ksort($colLabels);
        $rowKeys = array_slice(array_keys($rowLabels), 0, self::MAX_ROWS);
        $colKeys = $hasColumns ? array_keys($colLabels) : [''];

        // Build the flat data-column list (column group × value).
        $columns = [];
        foreach ($colKeys as $colKey) {
            foreach ($config['values'] as $index => $value) {
                $measure = $measures[$value['field']];
                $valueLabel = $this->valueLabel($value);
                $label = ! $hasColumns
                    ? $valueLabel
                    : ($multiValue ? $colLabels[$colKey].' · '.$valueLabel : $colLabels[$colKey]);

                $columns[] = [
                    'label' => $label,
                    'format' => $measure['format'],
                    'col_key' => $colKey,
                    'value_index' => $index,
                ];
            }
        }

        $rows = [];
        foreach ($rowKeys as $rowKey) {
            $cells = [];
            foreach ($columns as $column) {
                $bucket = $acc[$rowKey][$column['col_key']][$column['value_index']] ?? null;
                $agg = $config['values'][$column['value_index']]['agg'];
                $cells[] = $this->roundValue($this->reduce($bucket, $agg), $column['format']);
            }
            $rows[] = ['label' => $rowLabels[$rowKey], 'cells' => $cells];
        }

        $firstFormat = $measures[$config['values'][0]['field']]['format'];
        $firstAgg = $config['values'][0]['agg'];
        $chartData = [];
        foreach ($rowKeys as $rowKey) {
            $chartData[] = [
                'label' => $rowLabels[$rowKey],
                'value' => $this->roundValue($this->reduce($chart[$rowKey] ?? null, $firstAgg), $firstFormat) ?? 0,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'chart' => $chartData,
            'meta' => [
                'empty' => $rows === [],
                'truncated' => count($rowLabels) > self::MAX_ROWS,
                'row_fields' => array_map(fn (string $key): string => $this->dimensions()[$key]['label'], $config['rows']),
            ],
        ];
    }

    /**
     * Reduce an accumulator bucket to a single number for the given aggregation.
     *
     * @param  array{sum?: float, n?: int}|null  $bucket
     */
    private function reduce(?array $bucket, string $agg): ?float
    {
        if ($bucket === null || ($bucket['n'] ?? 0) === 0) {
            return $agg === 'avg' ? null : 0.0;
        }

        return $agg === 'avg'
            ? $bucket['sum'] / $bucket['n']
            : $bucket['sum'];
    }

    /**
     * Round a value according to its measure format.
     */
    private function roundValue(?float $value, string $format): int|float|null
    {
        if ($value === null) {
            return null;
        }

        return match ($format) {
            'integer', 'currency' => (int) round($value),
            default => round($value, 1),
        };
    }

    /**
     * A stable grouping key for a set of dimension values.
     *
     * @param  array<string, string>  $dims
     * @param  array<int, string>  $fields
     */
    private function groupKey(array $dims, array $fields): string
    {
        return implode("\x1f", array_map(fn (string $field): string => $dims[$field] ?? '—', $fields));
    }

    /**
     * A human label for a grouping ("Engineering / Bandung").
     *
     * @param  array<string, string>  $dims
     * @param  array<int, string>  $fields
     */
    private function groupLabel(array $dims, array $fields): string
    {
        return implode(' / ', array_map(fn (string $field): string => $dims[$field] ?? '—', $fields));
    }

    /**
     * The header label for one Value chip (aggregation + measure).
     *
     * @param  array{field: string, agg: string}  $value
     */
    private function valueLabel(array $value): string
    {
        $measure = $this->measures()[$value['field']];

        if (in_array($value['field'], ['count', 'resign'], true)) {
            return $measure['label'];
        }

        return ($value['agg'] === 'avg' ? 'Rata-rata ' : 'Total ').$measure['label'];
    }

    /**
     * Indonesian label for an employment-status code.
     */
    private function employmentLabel(?string $status): string
    {
        return match ($status) {
            'permanent' => 'Tetap',
            'contract' => 'Kontrak',
            'probation' => 'Probation',
            'resigned' => 'Resign',
            default => $status ?? '—',
        };
    }

    /**
     * Allowlisted Row/Column dimensions.
     *
     * @return array<string, array{label: string, group: string}>
     */
    private function dimensions(): array
    {
        return [
            'department' => ['label' => 'Departemen', 'group' => 'Data Karyawan'],
            'branch' => ['label' => 'Cabang', 'group' => 'Data Karyawan'],
            'employment_status' => ['label' => 'Status Kerja', 'group' => 'Data Karyawan'],
            'position' => ['label' => 'Jabatan', 'group' => 'Data Karyawan'],
        ];
    }

    /**
     * Allowlisted Value measures.
     *
     * @return array<string, array{label: string, group: string, format: string, aggs: array<int, string>, default_agg: string}>
     */
    private function measures(): array
    {
        return [
            'count' => ['label' => 'Jumlah Karyawan', 'group' => 'Ringkasan', 'format' => 'integer', 'aggs' => ['sum'], 'default_agg' => 'sum'],
            'resign' => ['label' => 'Jumlah Resign', 'group' => 'Absensi & Cuti', 'format' => 'integer', 'aggs' => ['sum'], 'default_agg' => 'sum'],
            'sisa_cuti' => ['label' => 'Sisa Cuti', 'group' => 'Absensi & Cuti', 'format' => 'decimal', 'aggs' => ['avg', 'sum'], 'default_agg' => 'avg'],
            'masa_kerja' => ['label' => 'Masa Kerja', 'group' => 'Absensi & Cuti', 'format' => 'decimal', 'aggs' => ['avg', 'sum'], 'default_agg' => 'avg'],
            'gaji_pokok' => ['label' => 'Gaji Pokok', 'group' => 'Payroll', 'format' => 'currency', 'aggs' => ['avg', 'sum'], 'default_agg' => 'avg'],
            'skor_kinerja' => ['label' => 'Skor Kinerja', 'group' => 'Payroll', 'format' => 'decimal', 'aggs' => ['avg'], 'default_agg' => 'avg'],
        ];
    }

    /**
     * Palette metadata for the dimension fields.
     *
     * @return array<int, array{key: string, label: string, group: string}>
     */
    private function dimensionOptions(): array
    {
        $options = [];
        foreach ($this->dimensions() as $key => $dimension) {
            $options[] = ['key' => $key, 'label' => $dimension['label'], 'group' => $dimension['group']];
        }

        return $options;
    }

    /**
     * Palette metadata for the measure fields.
     *
     * @return array<int, array{key: string, label: string, group: string, format: string, aggs: array<int, string>, default_agg: string}>
     */
    private function measureOptions(): array
    {
        $options = [];
        foreach ($this->measures() as $key => $measure) {
            $options[] = [
                'key' => $key,
                'label' => $measure['label'],
                'group' => $measure['group'],
                'format' => $measure['format'],
                'aggs' => $measure['aggs'],
                'default_agg' => $measure['default_agg'],
            ];
        }

        return $options;
    }

    /**
     * Starter templates matching the prototype.
     *
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'key' => 'turnover_cabang',
                'category' => 'Absensi',
                'title' => 'Turnover per cabang',
                'subtitle' => 'Cabang x Status, hitung resign',
                'config' => ['rows' => ['branch'], 'columns' => ['employment_status'], 'values' => [['field' => 'resign', 'agg' => 'sum']]],
            ],
            [
                'key' => 'headcount_dept',
                'category' => 'Karyawan',
                'title' => 'Headcount per departemen',
                'subtitle' => 'Departemen, jumlah karyawan',
                'config' => ['rows' => ['department'], 'columns' => [], 'values' => [['field' => 'count', 'agg' => 'sum']]],
            ],
            [
                'key' => 'avg_salary_dept',
                'category' => 'Payroll',
                'title' => 'Rata-rata gaji per departemen',
                'subtitle' => 'Departemen, rata-rata gaji pokok',
                'config' => ['rows' => ['department'], 'columns' => [], 'values' => [['field' => 'gaji_pokok', 'agg' => 'avg']]],
            ],
            [
                'key' => 'avg_tenure_position',
                'category' => 'Karyawan',
                'title' => 'Rata-rata masa kerja per jabatan',
                'subtitle' => 'Jabatan, rata-rata masa kerja',
                'config' => ['rows' => ['position'], 'columns' => [], 'values' => [['field' => 'masa_kerja', 'agg' => 'avg']]],
            ],
        ];
    }

    /**
     * Abort with 403 unless the user is privileged or holds the report permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}
