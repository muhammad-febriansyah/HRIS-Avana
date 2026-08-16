<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\IncentiveAssignment;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveRule;
use App\Models\IncentiveScheme;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Services\IncentiveCalculator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Insentif — schemes, who they apply to, what each period computes, and the
 * review that turns a computed figure into money.
 *
 * The screens are ordered the way the work is done: define the scheme and its
 * rules, assign employees, calculate a period, review and approve. Payroll only
 * ever reads approved rows, and locking the period locks them.
 */
class IncentiveController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly IncentiveCalculator $calculator) {}

    /**
     * Everything the Insentif screen needs: schemes with their rules, the
     * assignment roster, and the selected period's calculations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = (int) $request->user()->tenant_id;

        $schemes = IncentiveScheme::forTenant($tenantId)
            ->with(['rules', 'component:id,name,code'])
            ->withCount(['assignments as active_assignments_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('code')
            ->get()
            ->map(fn (IncentiveScheme $scheme): array => $this->schemeRow($scheme))
            ->values();

        $period = $request->filled('period')
            ? PayrollPeriod::forTenant($tenantId)->find($request->integer('period'))
            : PayrollPeriod::forTenant($tenantId)->orderByDesc('start_date')->first();

        $calculations = $period === null ? collect() : IncentiveCalculation::forTenant($tenantId)
            ->where('payroll_period_id', $period->id)
            ->with(['employee:id,full_name,employee_number', 'scheme:id,code,name', 'approver:id,name'])
            ->orderBy('id')
            ->get()
            ->map(fn (IncentiveCalculation $row): array => $this->calculationRow($row))
            ->values();

        return Inertia::render('avana/payroll-insentif/index', [
            'schemes' => $schemes,
            'periods' => PayrollPeriod::forTenant($tenantId)
                ->orderByDesc('start_date')
                ->get(['id', 'name', 'code', 'status'])
                ->map(fn (PayrollPeriod $row): array => [
                    'id' => $row->id,
                    'name' => $row->name ?? $row->code,
                    'status' => $row->status,
                ])
                ->values(),
            'selected_period_id' => $period?->id,
            'calculations' => $calculations,
            'employees' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $row): array => [
                    'id' => $row->id,
                    'name' => $row->full_name,
                    'employee_number' => $row->employee_number,
                ])
                ->values(),
            'components' => PayrollComponent::forTenant($tenantId)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
                ->where('type', 'earning')
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->values(),
            'assignments' => IncentiveAssignment::forTenant($tenantId)
                ->with(['employee:id,full_name,employee_number', 'scheme:id,code'])
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(fn (IncentiveAssignment $row): array => [
                    'id' => $row->id,
                    'route_key' => $row->public_id,
                    'scheme' => $row->scheme?->code,
                    'employee' => $row->employee?->full_name,
                    'employee_number' => $row->employee?->employee_number,
                    'effective_start_date' => $row->effective_start_date?->toDateString(),
                    'effective_end_date' => $row->effective_end_date?->toDateString(),
                    'status' => $row->status,
                ])
                ->values(),
            'bases' => IncentiveScheme::BASES,
        ]);
    }

    /** Create a scheme. */
    public function storeScheme(Request $request): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validateScheme($request, $tenantId);

        IncentiveScheme::create([...$data, 'tenant_id' => $tenantId]);

        return back()->with('success', 'Skema insentif dibuat');
    }

    /** Update a scheme. */
    public function updateScheme(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $scheme->tenant_id);

        $scheme->update($this->validateScheme($request, (int) $scheme->tenant_id, $scheme));

        return back()->with('success', 'Skema insentif diperbarui');
    }

    /** Delete a scheme, unless a period has already paid from it. */
    public function destroyScheme(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $scheme->tenant_id);

        $paid = IncentiveCalculation::query()
            ->where('incentive_scheme_id', $scheme->id)
            ->where('status', IncentiveCalculation::STATUS_LOCKED)
            ->exists();

        if ($paid) {
            throw ValidationException::withMessages([
                'scheme' => 'Skema ini sudah pernah dibayarkan pada periode terkunci — nonaktifkan saja, jangan dihapus.',
            ]);
        }

        $scheme->delete();

        return back()->with('success', 'Skema insentif dihapus');
    }

    /** Add a band to a scheme. */
    public function storeRule(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $scheme->tenant_id);

        $data = $request->validate([
            'sequence' => ['nullable', 'integer', 'min:1'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric', 'gte:min_value'],
            'amount_type' => ['required', Rule::in(IncentiveRule::AMOUNT_TYPES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        // Overlapping bands make the payout depend on which rule is read first,
        // which is not a rule at all — the first match wins, so two rules
        // covering 10–20 and 15–30 pay different amounts for 18 depending on
        // their order.
        $clash = $scheme->rules()->get()->first(function (IncentiveRule $rule) use ($data): bool {
            $newMin = $data['min_value'] ?? null;
            $newMax = $data['max_value'] ?? null;

            $startsAfter = $newMin !== null && $rule->max_value !== null && (float) $newMin > (float) $rule->max_value;
            $endsBefore = $newMax !== null && $rule->min_value !== null && (float) $newMax < (float) $rule->min_value;

            return ! $startsAfter && ! $endsBefore;
        });

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'min_value' => 'Rentang ini beririsan dengan aturan '
                    .($clash->min_value === null ? '−∞' : (float) $clash->min_value).' s.d. '
                    .($clash->max_value === null ? '∞' : (float) $clash->max_value)
                    .'. Satu nilai terukur hanya boleh masuk satu aturan.',
            ]);
        }

        $scheme->rules()->create([
            ...$data,
            'tenant_id' => $scheme->tenant_id,
            'sequence' => $data['sequence'] ?? ((int) $scheme->rules()->max('sequence') + 1),
        ]);

        return back()->with('success', 'Aturan insentif ditambahkan');
    }

    /** Remove a band. */
    public function destroyRule(Request $request, IncentiveRule $rule): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $rule->tenant_id);

        $rule->delete();

        return back()->with('success', 'Aturan insentif dihapus');
    }

    /**
     * Assign a scheme to one or many employees. Re-assigning the same employee
     * from the same date is a no-op rather than a second, double-paying row.
     */
    public function assign(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $scheme->tenant_id);

        $tenantId = (int) $scheme->tenant_id;

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
        ]);

        // Two live assignments of the same scheme to the same employee whose
        // dates overlap would each produce a calculation for a period inside
        // both — the same incentive twice.
        $overlapping = IncentiveAssignment::query()
            ->where('incentive_scheme_id', $scheme->id)
            ->whereIn('employee_id', $data['employee_ids'])
            ->where('status', 'active')
            ->whereDate('effective_start_date', '!=', $data['effective_start_date'])
            ->where(fn ($query) => $query
                ->whereNull('effective_end_date')
                ->orWhereDate('effective_end_date', '>=', $data['effective_start_date']))
            ->when(
                $data['effective_end_date'] ?? null,
                fn ($query, $end) => $query->whereDate('effective_start_date', '<=', $end),
            )
            ->with('employee:id,full_name')
            ->first();

        if ($overlapping !== null) {
            throw ValidationException::withMessages([
                'effective_start_date' => 'Penetapan skema ini untuk '
                    .($overlapping->employee?->full_name ?? 'karyawan tersebut')
                    .' sudah berlaku sejak '.$overlapping->effective_start_date?->toDateString()
                    .'. Hentikan penetapan lama dulu agar tidak tumpang tindih.',
            ]);
        }

        foreach ($data['employee_ids'] as $employeeId) {
            IncentiveAssignment::updateOrCreate(
                [
                    'incentive_scheme_id' => $scheme->id,
                    'employee_id' => $employeeId,
                    'effective_start_date' => $data['effective_start_date'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'effective_end_date' => $data['effective_end_date'] ?? null,
                    'status' => 'active',
                    'created_by' => $request->user()->id,
                ],
            );
        }

        return back()->with('success', count($data['employee_ids']).' karyawan ditetapkan ke skema');
    }

    /** End an assignment. */
    public function unassign(Request $request, IncentiveAssignment $assignment): RedirectResponse
    {
        $this->authorize('manage', PayrollPeriod::class);
        $this->ensureOwnership($request, $assignment->tenant_id);

        $assignment->update(['status' => 'inactive']);

        return back()->with('success', 'Penetapan insentif dihentikan');
    }

    /** Compute a scheme's incentives for a period. */
    public function calculate(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);
        $this->ensureOwnership($request, $scheme->tenant_id);

        $data = $request->validate([
            'payroll_period_id' => ['required', Rule::exists('payroll_periods', 'id')->where('tenant_id', $scheme->tenant_id)],
        ]);

        $period = PayrollPeriod::forTenant($scheme->tenant_id)->findOrFail($data['payroll_period_id']);

        $this->refuseLockedPeriod($period);

        $scheme->loadMissing('rules');
        $result = $this->calculator->calculate($scheme, $period, (int) $request->user()->id);

        return back()->with('success', sprintf(
            'Insentif dihitung: %d baru, %d diperbarui, %d dilewati (sudah disetujui/terkunci).',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        ));
    }

    /** Record a target figure or a manual amount on a draft row. */
    public function updateCalculation(Request $request, IncentiveCalculation $calculation): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);
        $this->ensureOwnership($request, $calculation->tenant_id);

        $calculation->loadMissing('period');

        if (! $calculation->isEditable() || $this->periodIsLocked($calculation)) {
            throw ValidationException::withMessages(['amount' => 'Insentif pada periode terkunci tidak bisa diubah.']);
        }

        $data = $request->validate([
            'measured_value' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            // An override is a decision somebody has to own.
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $overridden = (float) $data['amount'] !== (float) ($calculation->computed_amount ?? $calculation->amount);

        if ($overridden && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => 'Nominal berbeda dari hasil hitung — tulis alasannya.',
            ]);
        }

        $calculation->update([
            'measured_value' => $data['measured_value'] ?? $calculation->measured_value,
            'amount' => $data['amount'],
            'reason' => $data['reason'] ?? $calculation->reason,
            // Any edit sends the row back through review.
            'status' => IncentiveCalculation::STATUS_DRAFT,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return back()->with('success', 'Insentif diperbarui');
    }

    /** Send draft rows for approval. */
    public function submit(Request $request): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);

        $rows = $this->selectedCalculations($request);
        $this->refuseLockedRows($rows);

        foreach ($rows as $row) {
            if ($row->status !== IncentiveCalculation::STATUS_DRAFT) {
                continue;
            }

            $row->update(['status' => IncentiveCalculation::STATUS_PENDING]);
        }

        return back()->with('success', $rows->count().' insentif diajukan untuk persetujuan');
    }

    /**
     * Approve pending rows. The approver may not be the person who prepared
     * them: money that one person can both compute and sign off is money nobody
     * checked.
     */
    public function approve(Request $request): RedirectResponse
    {
        $this->authorize('approve', PayrollPeriod::class);

        $rows = $this->selectedCalculations($request);
        $this->refuseLockedRows($rows);
        $userId = (int) $request->user()->id;

        $ownWork = $rows->first(fn (IncentiveCalculation $row): bool => (int) $row->created_by === $userId);

        if ($ownWork !== null) {
            throw ValidationException::withMessages([
                'calculation_ids' => 'Insentif yang Anda hitung sendiri harus disetujui orang lain.',
            ]);
        }

        $approved = 0;

        foreach ($rows as $row) {
            if ($row->status !== IncentiveCalculation::STATUS_PENDING) {
                continue;
            }

            $row->update([
                'status' => IncentiveCalculation::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
            ]);
            $approved++;
        }

        return back()->with('success', $approved.' insentif disetujui');
    }

    /** Reject pending rows with a reason. */
    public function reject(Request $request): RedirectResponse
    {
        $this->authorize('approve', PayrollPeriod::class);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $rows = $this->selectedCalculations($request);
        $this->refuseLockedRows($rows);
        $rejected = 0;

        foreach ($rows as $row) {
            if (! in_array($row->status, [IncentiveCalculation::STATUS_PENDING, IncentiveCalculation::STATUS_APPROVED], true)) {
                continue;
            }

            $row->update([
                'status' => IncentiveCalculation::STATUS_REJECTED,
                'reason' => $data['reason'],
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
            ]);
            $rejected++;
        }

        return back()->with('success', $rejected.' insentif ditolak');
    }

    /**
     * Incentive history for one employee or one scheme — what was paid, when,
     * and on whose approval.
     */
    public function history(Request $request): Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = (int) $request->user()->tenant_id;

        $rows = IncentiveCalculation::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'scheme:id,code,name', 'period:id,name,code', 'approver:id,name'])
            ->when($request->integer('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
            ->when($request->integer('scheme_id'), fn ($query, $id) => $query->where('incentive_scheme_id', $id))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('avana/payroll-insentif/history', [
            'rows' => [
                'data' => collect($rows->items())
                    ->map(fn (IncentiveCalculation $row): array => [
                        ...$this->calculationRow($row),
                        'period' => $row->period?->name ?? $row->period?->code,
                    ])
                    ->all(),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ],
            'schemes' => IncentiveScheme::forTenant($tenantId)->orderBy('code')->get(['id', 'code', 'name']),
            'filters' => $request->only(['employee_id', 'scheme_id', 'status']),
        ]);
    }

    /**
     * The calculations named in the request, scoped to the acting tenant.
     *
     * @return Collection<int, IncentiveCalculation>
     */
    private function selectedCalculations(Request $request): Collection
    {
        $data = $request->validate([
            'calculation_ids' => ['required', 'array', 'min:1'],
            'calculation_ids.*' => ['integer'],
        ]);

        return IncentiveCalculation::forTenant($request->user()->tenant_id)
            ->whereIn('id', $data['calculation_ids'])
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateScheme(Request $request, int $tenantId, ?IncentiveScheme $scheme = null): array
    {
        $code = Rule::unique('incentive_schemes', 'code')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($scheme !== null) {
            $code->ignore($scheme->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', $code],
            'name' => ['required', 'string', 'max:255'],
            'basis' => ['required', Rule::in(IncentiveScheme::BASES)],
            'payroll_component_id' => ['nullable', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
            'rounding' => ['nullable', Rule::in(IncentiveScheme::ROUNDINGS)],
            'rounding_unit' => ['nullable', 'integer', 'min:1'],
            'prorate_partial_period' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemeRow(IncentiveScheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'route_key' => $scheme->public_id,
            'code' => $scheme->code,
            'name' => $scheme->name,
            'basis' => $scheme->basis,
            'component' => $scheme->component?->name,
            'payroll_component_id' => $scheme->payroll_component_id,
            'effective_start_date' => $scheme->effective_start_date?->toDateString(),
            'effective_end_date' => $scheme->effective_end_date?->toDateString(),
            'rounding' => $scheme->rounding,
            'rounding_unit' => (int) $scheme->rounding_unit,
            'prorate_partial_period' => (bool) $scheme->prorate_partial_period,
            'status' => $scheme->status,
            'notes' => $scheme->notes,
            'assignments_count' => (int) ($scheme->active_assignments_count ?? 0),
            'rules' => $scheme->rules->map(fn (IncentiveRule $rule): array => [
                'id' => $rule->id,
                'sequence' => (int) $rule->sequence,
                'min_value' => $rule->min_value === null ? null : (float) $rule->min_value,
                'max_value' => $rule->max_value === null ? null : (float) $rule->max_value,
                'amount_type' => $rule->amount_type,
                'amount' => (float) $rule->amount,
                'notes' => $rule->notes,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function calculationRow(IncentiveCalculation $row): array
    {
        return [
            'id' => $row->id,
            'route_key' => $row->public_id,
            'scheme' => $row->scheme?->code,
            'scheme_name' => $row->scheme?->name,
            'employee' => $row->employee?->full_name,
            'employee_number' => $row->employee?->employee_number,
            'measured_value' => (float) $row->measured_value,
            'amount' => (float) $row->amount,
            'computed_amount' => $row->computed_amount === null ? null : (float) $row->computed_amount,
            'overridden' => $row->computed_amount !== null && (float) $row->computed_amount !== (float) $row->amount,
            'status' => $row->status,
            'reason' => $row->reason,
            'approver' => $row->approver?->name,
            'approved_at' => $row->approved_at?->toDateTimeString(),
            'source_snapshot' => $row->source_snapshot,
        ];
    }

    /** Whether this calculation's payroll period has already been finalised. */
    private function periodIsLocked(IncentiveCalculation $calculation): bool
    {
        return $calculation->period?->status === 'locked';
    }

    /**
     * Refuse the whole action when any selected row belongs to a locked period.
     *
     * Locking a period pays it; changing an incentive afterwards would move a
     * figure the payslip has already stated. Checked for every mutation, not
     * only for recalculation — submit, approve and reject move money too.
     *
     * @param  Collection<int, IncentiveCalculation>  $rows
     */
    private function refuseLockedRows(Collection $rows): void
    {
        $rows->loadMissing('period:id,status,name,code');

        $locked = $rows->first(fn (IncentiveCalculation $row): bool => $this->periodIsLocked($row));

        if ($locked !== null) {
            throw ValidationException::withMessages([
                'calculation_ids' => 'Periode '.($locked->period?->name ?? $locked->period?->code ?? '').' sudah terkunci — insentifnya tidak bisa diubah lagi.',
            ]);
        }
    }

    private function ensureOwnership(Request $request, int|string|null $tenantId): void
    {
        abort_if((int) $tenantId !== (int) $request->user()->tenant_id, 404);
    }

    /** A locked period has been paid; its incentives are history. */
    private function refuseLockedPeriod(PayrollPeriod $period): void
    {
        if ($period->status === 'locked') {
            throw ValidationException::withMessages([
                'payroll_period_id' => 'Periode sudah terkunci — insentifnya tidak bisa dihitung ulang.',
            ]);
        }
    }
}
