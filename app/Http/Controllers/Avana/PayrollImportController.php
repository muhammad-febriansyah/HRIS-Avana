<?php

namespace App\Http\Controllers\Avana;

use App\Exports\PayrollImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PayrollImportRowsImport;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryChangeSet;
use App\Models\SalaryMasterComponent;
use App\Models\User;
use App\Support\BasicWageComponent;
use App\Support\PayrollImportLayout;
use App\Support\SalaryCompliance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Upload a finished payroll instead of computing one.
 *
 * A tenant that runs its payroll in another system (or in a spreadsheet) still
 * needs the payslips, the bank transfer file, the tax form and the reports that
 * hang off a payroll run. This takes the numbers as given: one row per employee,
 * written straight into the period's run without touching the salary engine.
 *
 * The import REPLACES the period's run items — a payroll uploaded twice must
 * not stack up, and a row removed from the file must disappear from the run.
 */
final class PayrollImportController extends Controller
{
    /**
     * The permission module gating this screen — the same one that guards
     * running payroll, because uploading one has the same effect on pay.
     */
    private const MODULE = 'payroll';

    /**
     * Download the fill-in template for a period: the employees that period
     * would pay, one column per component in the tenant's Master Komponen, and
     * the fixed contract amounts already filled in as a starting point.
     */
    public function template(Request $request): BinaryFileResponse
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;
        $period = $this->resolvePeriod($request, $tenantId);

        $employees = $this->payableEmployees($tenantId, $period);
        $amounts = $this->contractAmounts($tenantId, $employees);

        $rows = $employees
            ->map(fn (Employee $employee): array => [
                'number' => (string) ($employee->employee_number ?? ''),
                'name' => (string) $employee->full_name,
                'amounts' => $amounts[$employee->id] ?? [],
            ])
            ->values()
            ->all();

        $slug = str($period->code ?? $period->name ?? 'periode')->slug()->value();

        return Excel::download(
            new PayrollImportTemplateExport($rows, PayrollImportLayout::components($tenantId)),
            "template-payroll-{$slug}.xlsx",
        );
    }

    /**
     * The fixed component amounts each employee's pay already states, to
     * pre-fill the template with.
     *
     * Two sources, in the order payroll itself reads them: the figures set on
     * the employee, then their Master Gaji for the components the employee has
     * no figure of their own for. Reading only the first would hand HR a
     * template short of every allowance that lives on the master — a file that
     * looks complete and underpays.
     *
     * Only amounts that mean rupiah-per-month are filled: a percentage
     * component holds a percentage and an attendance-based one holds a daily or
     * hourly rate, so writing either into a monthly column would state a figure
     * nobody is owed. Those columns are left blank for HR to enter.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<int, float>>
     */
    private function contractAmounts(int $tenantId, Collection $employees): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $amounts = [];

        $rows = EmployeeSalaryComponent::forTenant($tenantId)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->inForce()
            ->effectiveOn()
            ->with('component')
            ->get();

        foreach ($rows as $row) {
            $component = $row->component;

            if ($component === null
                || $component->status !== 'active'
                || in_array($component->calc_basis, PayrollImportLayout::VARIABLE_BASES, true)) {
                continue;
            }

            $employeeId = (int) $row->employee_id;
            $componentId = (int) $component->id;

            $amounts[$employeeId][$componentId] = ($amounts[$employeeId][$componentId] ?? 0.0) + (float) $row->amount;
        }

        return $this->fillFromSalaryMaster($tenantId, $employees, $amounts);
    }

    /**
     * Add the Master Gaji amounts for components the employee states no figure
     * of their own for.
     *
     * Deductions and variable pay are left out on purpose: the master's figure
     * for those is a standing default the month may or may not charge, and a
     * pre-filled number is the kind nobody re-reads before uploading. The
     * employee's own row is different — it states what that person is actually
     * on, so it pre-fills whatever it can mean as a month's money.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  array<int, array<int, float>>  $amounts
     * @return array<int, array<int, float>>
     */
    private function fillFromSalaryMaster(int $tenantId, Collection $employees, array $amounts): array
    {
        $masterIds = $this->effectiveMasterIds($tenantId, $employees);

        if ($masterIds === []) {
            return $amounts;
        }

        $componentsByMaster = SalaryMasterComponent::query()
            ->whereIn('salary_master_id', array_values(array_unique($masterIds)))
            ->where('included', true)
            ->with('component')
            ->get()
            ->groupBy('salary_master_id');

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $masterId = $masterIds[$employeeId] ?? null;

            if ($masterId === null) {
                continue;
            }

            foreach ($componentsByMaster->get($masterId, collect()) as $masterComponent) {
                $component = $masterComponent->component;
                $amount = (float) $masterComponent->amount;

                if ($component === null
                    || $component->status !== 'active'
                    || $component->type === 'deduction'
                    || $amount <= 0.0
                    || PayrollImportLayout::isVariable($component)) {
                    continue;
                }

                // The employee's own figure wins — the master only fills what
                // the person does not already state.
                $amounts[$employeeId][(int) $component->id] ??= $amount;
            }
        }

        return $amounts;
    }

    /**
     * The Master Gaji in force for each employee, in one query instead of one
     * per row. Mirrors SalaryMasterAssignment::effectiveMasterId(): the latest
     * active change set that has started, else the employee's own master.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, int>
     */
    private function effectiveMasterIds(int $tenantId, Collection $employees): array
    {
        $masters = [];

        foreach ($employees as $employee) {
            if ($employee->salary_master_id !== null) {
                $masters[(int) $employee->id] = (int) $employee->salary_master_id;
            }
        }

        $changeSets = SalaryChangeSet::forTenant($tenantId)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<=', now()->toDateString()))
            ->orderBy('effective_start_date')
            ->orderBy('id')
            ->get(['employee_id', 'salary_master_id']);

        // Ascending, so the last row seen for an employee is the change set in
        // force — including one that moves them off a master entirely.
        foreach ($changeSets as $changeSet) {
            $employeeId = (int) $changeSet->employee_id;

            if ($changeSet->salary_master_id === null) {
                unset($masters[$employeeId]);

                continue;
            }

            $masters[$employeeId] = (int) $changeSet->salary_master_id;
        }

        return $masters;
    }

    /**
     * Parse the uploaded workbook and write it into the period's payroll run.
     *
     * All or nothing: a single unusable row rejects the whole file rather than
     * leaving half a payroll behind for someone to reconcile by hand.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $request->validate([
            'payroll_period_id' => [
                'required', 'integer',
                Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:8192'],
        ], [
            'file.mimes' => 'Unggah berkas .xlsx, .xls atau .csv.',
        ]);

        $period = PayrollPeriod::forTenant($tenantId)->findOrFail($request->integer('payroll_period_id'));

        if ($period->status === 'locked') {
            return back()->withErrors(['file' => 'Periode terkunci, payroll tidak bisa diunggah.']);
        }

        $sheet = Excel::toArray(new PayrollImportRowsImport, $request->file('file'))[0] ?? [];

        // Where each column is. A file with a header row says so itself, which
        // is what lets the component columns vary per tenant; a headerless file
        // is read in the fixed order the pre-component template used.
        $fields = array_flip(PayrollImportLayout::LEGACY_ORDER);
        $componentColumns = [];

        if ($sheet !== [] && $this->looksLikeHeader($sheet[0])) {
            $header = array_map(
                static fn ($value): string => trim((string) ($value ?? '')),
                array_values((array) array_shift($sheet)),
            );

            $layout = PayrollImportLayout::resolveHeader($header, PayrollImportLayout::components($tenantId));

            $fields = $layout['fields'];
            $componentColumns = $layout['components'];
        }

        // Employees keyed by their number, case- and space-insensitive, so a
        // stray space in the spreadsheet still finds the right person.
        $employees = Employee::forTenant($tenantId)
            ->whereNotNull('employee_number')
            ->get(['id', 'employee_number', 'full_name'])
            ->keyBy(fn (Employee $employee): string => $this->normaliseNumber((string) $employee->employee_number));

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($sheet as $index => $raw) {
            $line = $index + 2; // +1 for the stripped header, +1 for 1-based rows
            $cells = array_map(static fn ($value): string => trim((string) ($value ?? '')), array_values((array) $raw));

            if (implode('', $cells) === '') {
                continue;
            }

            $at = static fn (?int $column): string => $column === null ? '' : (string) ($cells[$column] ?? '');
            $field = static fn (string $name): string => $at($fields[$name] ?? null);

            $number = $field('nomor_karyawan');
            $key = $this->normaliseNumber($number);

            if ($key === '') {
                $errors[] = "Baris {$line}: nomor karyawan kosong.";

                continue;
            }

            if (! $employees->has($key)) {
                $errors[] = "Baris {$line}: nomor karyawan {$number} tidak ditemukan.";

                continue;
            }

            if (isset($seen[$key])) {
                $errors[] = "Baris {$line}: nomor karyawan {$number} muncul dua kali.";

                continue;
            }

            $grossCell = $field('gaji_bruto');
            $allowanceCell = $field('tunjangan');
            $deductionCell = $field('potongan');
            $bpjsEmployeeCell = $field('bpjs_karyawan');
            $bpjsCompanyCell = $field('bpjs_perusahaan');
            $pph21Cell = $field('pph21');
            $netCell = $field('take_home_pay');

            $invalid = null;

            foreach ([
                'gaji bruto' => $grossCell,
                'tunjangan' => $allowanceCell,
                'potongan' => $deductionCell,
                'bpjs karyawan' => $bpjsEmployeeCell,
                'bpjs perusahaan' => $bpjsCompanyCell,
                'pph21' => $pph21Cell,
                'take home pay' => $netCell,
            ] as $label => $value) {
                if ($value !== '' && ! $this->isNumeric($value)) {
                    $invalid = $label;

                    break;
                }
            }

            $componentAmounts = [];

            if ($invalid === null) {
                foreach ($componentColumns as $column => $component) {
                    $value = $at($column);

                    if ($value !== '' && ! $this->isNumeric($value)) {
                        $invalid = mb_strtolower((string) $component->name);

                        break;
                    }

                    $componentAmounts[(int) $component->id] = $this->money($value);
                }
            }

            if ($invalid !== null) {
                $errors[] = "Baris {$line}: kolom {$invalid} bukan angka.";

                continue;
            }

            // A row states either a rolled-up bruto or the components it is
            // made of, never both: one of the two would have to be ignored, and
            // ignoring money quietly is how a payroll goes wrong unnoticed.
            if ($grossCell !== '' && array_sum($componentAmounts) > 0.0) {
                $errors[] = "Baris {$line}: isi kolom komponen atau kolom gaji_bruto, jangan keduanya.";

                continue;
            }

            // A file from the pre-component template states one rolled-up
            // bruto; the component template states the parts, and the bruto is
            // their sum. Whichever the row uses, the payslip gets the lines the
            // file actually named.
            $gross = 0.0;
            $allowance = 0.0;
            $componentDeduction = 0.0;
            $earnings = [];
            $deductionLines = [];

            if ($grossCell !== '') {
                $gross = $this->money($grossCell);
                $allowance = $this->money($allowanceCell);
                $earnings = $this->slipLines([
                    'Gaji Bruto' => $gross - $allowance,
                    'Tunjangan' => $allowance,
                ]);
            } else {
                foreach ($componentColumns as $component) {
                    $amount = $componentAmounts[(int) $component->id] ?? 0.0;

                    if ($amount <= 0.0) {
                        continue;
                    }

                    if (PayrollImportLayout::isDeduction($component)) {
                        $componentDeduction += $amount;
                        $deductionLines[] = ['name' => (string) $component->name, 'amount' => $amount];

                        continue;
                    }

                    $gross += $amount;
                    $earnings[] = ['name' => (string) $component->name, 'amount' => $amount];

                    if (! BasicWageComponent::matches($component->code)) {
                        $allowance += $amount;
                    }
                }
            }

            if ($gross <= 0.0) {
                $errors[] = isset($fields['gaji_bruto']) && $componentColumns === []
                    ? "Baris {$line}: gaji bruto wajib diisi angka."
                    : "Baris {$line}: tidak ada komponen penerimaan yang diisi.";

                continue;
            }

            $seen[$key] = true;

            $otherDeduction = $this->money($deductionCell);
            $bpjsEmployeeValue = $this->money($bpjsEmployeeCell);
            $pph21Value = $this->money($pph21Cell);

            // Everything taken off the pay, which is what total_deduction means
            // on a computed run: BPJS and PPh 21 included, so `bruto − potongan
            // = netto` holds for an uploaded payslip exactly as it does for a
            // calculated one. They keep their own columns too, for the reports
            // that need the tax and the contribution on their own.
            $deductionValue = $otherDeduction + $componentDeduction + $bpjsEmployeeValue + $pph21Value;

            $rows[] = [
                'employee' => $employees->get($key),
                'gross' => $gross,
                'allowance' => $allowance,
                'deduction' => $deductionValue,
                'bpjs_employee' => $bpjsEmployeeValue,
                'bpjs_company' => $this->money($bpjsCompanyCell),
                'pph21' => $pph21Value,
                // A blank take-home is the common case: derive it rather than
                // making HR restate arithmetic the file already implies.
                'net' => $netCell !== ''
                    ? $this->money($netCell)
                    : max(0.0, $gross - $deductionValue),
                'earnings' => $earnings,
                'deductions' => [
                    ...$deductionLines,
                    ...$this->slipLines([
                        'Potongan Lain' => $otherDeduction,
                        'BPJS Karyawan' => $bpjsEmployeeValue,
                        'PPh 21' => $pph21Value,
                    ]),
                ],
                'line' => $line,
            ];
        }

        if ($errors !== []) {
            return back()->withErrors([
                'file' => count($errors).' baris bermasalah, tidak ada data yang disimpan: '
                    .implode(' ', array_slice($errors, 0, 5))
                    .(count($errors) > 5 ? ' …' : ''),
            ]);
        }

        if ($rows === []) {
            return back()->withErrors(['file' => 'Berkas tidak berisi baris payroll.']);
        }

        $this->writeRun($request->user(), $period, $tenantId, $rows, (string) $request->file('file')->getClientOriginalName());

        return back()->with(
            'success',
            count($rows).' baris payroll diunggah untuk '.($period->name ?? $period->code),
        );
    }

    /**
     * Replace the period's run with the uploaded rows, in one transaction.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeRun(User $user, PayrollPeriod $period, int $tenantId, array $rows, string $fileName): void
    {
        DB::transaction(function () use ($user, $period, $tenantId, $rows, $fileName): void {
            // The revision still in play; a superseded (already paid) run is
            // history and never written into again.
            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->whereNull('branch_id')
                ->current()
                ->orderByDesc('id')
                ->first() ?? new PayrollRun([
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'branch_id' => null,
                    'revision' => 1 + (int) PayrollRun::forTenant($tenantId)
                        ->where('payroll_period_id', $period->id)
                        ->max('revision'),
                ]);

            // Uploading over a computed run would replace figures the engine
            // derived from salaries, attendance and approvals with numbers from
            // a file, under the same run id. The two flows do not mix silently:
            // recompute or discard the engine run first.
            if ($run->exists && $run->source !== PayrollRun::SOURCE_IMPORT && $run->items()->exists()) {
                throw ValidationException::withMessages([
                    'file' => 'Periode ini sudah punya hasil perhitungan sistem. Impor payroll tidak boleh menimpanya — jalankan periode baru atau kosongkan hasil perhitungan lebih dulu.',
                ]);
            }

            // An upload is a fresh statement of the period's pay, so any earlier
            // approval on this run no longer applies to what it now holds.
            $run->status = 'calculated';
            $run->source = PayrollRun::SOURCE_IMPORT;
            $run->run_by = $user->id;
            $run->approved_by = null;
            $run->approved_at = null;
            $run->approval_note = null;
            $run->rejected_by = null;
            $run->rejected_at = null;
            $run->rejection_note = null;
            $run->save();

            PayrollRunItem::where('payroll_run_id', $run->id)->delete();

            $totals = ['gross' => 0.0, 'deduction' => 0.0, 'tax' => 0.0, 'net' => 0.0];
            $stamp = now()->toDateTimeString();

            foreach ($rows as $row) {
                /** @var Employee $employee */
                $employee = $row['employee'];

                PayrollRunItem::create([
                    'tenant_id' => $tenantId,
                    'payroll_run_id' => $run->id,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'gross_salary' => $row['gross'],
                    // Taxed elsewhere, so nothing here feeds the tax engine —
                    // the uploaded PPh 21 is the final word.
                    'taxable_gross' => $row['gross'],
                    'tax_deductible_premium' => 0,
                    'total_allowance' => $row['allowance'],
                    'total_deduction' => $row['deduction'],
                    'bpjs_employee_total' => $row['bpjs_employee'],
                    'bpjs_company_total' => $row['bpjs_company'],
                    'pph21_total' => $row['pph21'],
                    'net_salary' => $row['net'],
                    'calculation_snapshot' => [
                        'source' => 'import',
                        'file' => $fileName,
                        'row' => $row['line'],
                        'imported_at' => $stamp,
                        'imported_by' => $user->id,
                        // Named per component when the file used the component
                        // columns, so the payslip reads like the tenant's own
                        // salary setup rather than a single "Tunjangan" line.
                        'earnings' => $row['earnings'],
                        'deductions' => $row['deductions'],
                        'gross' => $row['gross'],
                        'deduction' => $row['deduction'],
                        'net' => $row['net'],
                    ],
                    'status' => 'calculated',
                ]);

                $totals['gross'] += $row['gross'];
                $totals['deduction'] += $row['deduction'];
                $totals['tax'] += $row['pph21'];
                $totals['net'] += $row['net'];
            }

            $run->update([
                'total_gross' => $totals['gross'],
                'total_deduction' => $totals['deduction'],
                'total_tax' => $totals['tax'],
                'total_net' => $totals['net'],
                'employee_count' => count($rows),
                'status' => 'calculated',
                'source' => PayrollRun::SOURCE_IMPORT,
                // What the approver has to answer for: whose pay the file
                // states, who it left out, and whose figure disagrees with the
                // salary the system holds.
                'reconciliation' => $this->reconcile($tenantId, $period, $rows, $fileName, $user),
            ]);
        });
    }

    /**
     * Compare an uploaded payroll against the salaries the system holds.
     *
     * An import bypasses the engine, so nothing else would ever notice that the
     * file pays somebody twice their contract, or quietly leaves three people
     * out. This does not block the upload — the tenant's own system may be
     * right — it states the differences so approving the run is a decision
     * somebody takes with the facts in front of them.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function reconcile(int $tenantId, PayrollPeriod $period, array $rows, string $fileName, User $user): array
    {
        $payable = $this->payableEmployees($tenantId, $period)->keyBy('id');
        $imported = collect($rows)->keyBy(fn (array $row): int => (int) $row['employee']->id);

        $variances = [];

        foreach ($rows as $row) {
            /** @var Employee $employee */
            $employee = $row['employee'];
            $expected = SalaryCompliance::monthlyWage($employee, $tenantId)['total'];
            $gross = (float) $row['gross'];
            $delta = $gross - $expected;

            // A tolerance, not a rule: variable pay (attendance allowances,
            // overtime, incentives) legitimately moves gross around, so only a
            // difference worth asking about is listed.
            if ($expected > 0.0 && abs($delta) / $expected < 0.1) {
                continue;
            }

            $variances[] = [
                'employee_id' => $employee->id,
                'employee' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'expected_fixed_wage' => round($expected),
                'imported_gross' => round($gross),
                'delta' => round($delta),
            ];
        }

        $missing = $payable
            ->reject(fn (Employee $employee): bool => $imported->has($employee->id))
            ->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'employee' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();

        $unexpected = $imported
            ->reject(fn (array $row): bool => $payable->has((int) $row['employee']->id))
            ->map(fn (array $row): array => [
                'employee_id' => $row['employee']->id,
                'employee' => $row['employee']->full_name,
                'employee_number' => $row['employee']->employee_number,
            ])
            ->values()
            ->all();

        return [
            'file' => $fileName,
            'imported_at' => now()->toDateTimeString(),
            'imported_by' => $user->id,
            'row_count' => count($rows),
            'payable_count' => $payable->count(),
            // Employees this period would pay that the file says nothing about.
            'missing' => $missing,
            // Rows for people this period would not have paid at all.
            'unexpected' => $unexpected,
            'variances' => $variances,
        ];
    }

    /**
     * Payslip lines for the amounts that are actually present — a zero row on a
     * slip reads as a mistake rather than as nothing.
     *
     * @param  array<string, float>  $amounts
     * @return array<int, array{name: string, amount: float}>
     */
    private function slipLines(array $amounts): array
    {
        $lines = [];

        foreach ($amounts as $name => $amount) {
            if ($amount > 0) {
                $lines[] = ['name' => $name, 'amount' => $amount];
            }
        }

        return $lines;
    }

    /**
     * The period the template is built for: an explicit id, else the one the
     * payroll screen would target.
     */
    private function resolvePeriod(Request $request, int $tenantId): PayrollPeriod
    {
        $explicit = $request->integer('payroll_period_id');

        if ($explicit > 0) {
            return PayrollPeriod::forTenant($tenantId)->findOrFail($explicit);
        }

        $period = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->orderByDesc('start_date')
            ->first();

        abort_if($period === null, 404);

        return $period;
    }

    /**
     * The employees a period pays — joined by its end, not gone before its
     * start. Mirrors PayrollController::payableEmployees().
     *
     * @return Collection<int, Employee>
     */
    private function payableEmployees(int $tenantId, PayrollPeriod $period): Collection
    {
        $start = $period->start_date?->toDateString();
        $end = $period->end_date?->toDateString();

        if ($start === null || $end === null) {
            return Employee::forTenant($tenantId)->where('status', 'active')->orderBy('full_name')->get();
        }

        return Employee::forTenant($tenantId)
            ->where(fn ($query) => $query->whereNull('join_date')->orWhere('join_date', '<=', $end))
            ->where(fn ($query) => $query->whereNull('resign_date')->orWhere('resign_date', '>=', $start))
            ->where(fn ($query) => $query->where('status', 'active')->orWhereNotNull('resign_date'))
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Whether the first row is the template's own header rather than data.
     *
     * @param  mixed  $row
     */
    private function looksLikeHeader($row): bool
    {
        return PayrollImportLayout::isEmployeeNumberHeader((string) (((array) $row)[0] ?? ''));
    }

    /** Compare employee numbers without case or spacing getting in the way. */
    private function normaliseNumber(string $value): string
    {
        return strtolower(preg_replace('/\s+/', '', $value) ?? '');
    }

    /** Whether a cell holds a number once thousand separators are stripped. */
    private function isNumeric(string $value): bool
    {
        return is_numeric($this->clean($value));
    }

    /** A cell's amount, accepting "10.000.000", "10,000,000" and "Rp 10000000". */
    private function money(string $value): float
    {
        return $value === '' ? 0.0 : round((float) $this->clean($value), 2);
    }

    /** Strip currency prefix and thousand separators, keep the decimal point. */
    private function clean(string $value): string
    {
        $value = trim(str_ireplace(['rp', ' '], '', $value));

        // "1.234.567,89" (id) and "1,234,567.89" (en) both mean the same amount:
        // whichever separator comes last is the decimal one.
        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');

        if ($lastDot !== false && $lastComma !== false) {
            return $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        if ($lastComma !== false) {
            // A lone comma is a decimal comma only when it is not grouping.
            return substr_count($value, ',') === 1 && strlen($value) - $lastComma <= 3
                ? str_replace(',', '.', $value)
                : str_replace(',', '', $value);
        }

        if ($lastDot !== false && substr_count($value, '.') > 1) {
            return str_replace('.', '', $value);
        }

        // A single dot with exactly three digits after it is a thousand
        // separator in Indonesian sheets ("10.000"), not a decimal point.
        if ($lastDot !== false && strlen($value) - $lastDot === 4) {
            return str_replace('.', '', $value);
        }

        return $value;
    }

    /**
     * Abort with 403 unless the user may act on payroll for their tenant.
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
