<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\BpjsProgram;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentValue;
use App\Models\PayrollFormula;
use App\Models\PayrollFormulaItem;
use App\Models\Position;
use App\Models\User;
use App\Support\BasicWageComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Komponen" submodule of the BPR-manual payroll: Master Komponen (earnings /
 * deductions with the tax + slip + calc settings) and Master Formula (named
 * component combinations used as calculation bases).
 */
class PayrollKomponenController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    /**
     * @var array<int, string>
     */
    private const GROUPS = ['penerimaan', 'potongan'];

    /**
     * @var array<int, string>
     */
    private const CALC_BASES = ['fixed', 'percentage', 'per_present_day', 'per_overtime_hour'];

    /**
     * @var array<int, string>
     */
    private const FORMULA_TIPES = ['penerimaan', 'potongan', 'umr'];

    /**
     * Dasar perhitungan (BPR manual 1.2.2). Null = legacy attendance/attachment.
     *
     * @var array<int, string>
     */
    private const BASIS_TYPES = ['fixed', 'tabel', 'formula'];

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $values = PayrollComponentValue::forTenant($tenantId)
            ->with(['position:id,name', 'jobLevel:id,name', 'branch:id,name'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('payroll_component_id');

        $components = PayrollComponent::forTenant($tenantId)
            ->with(['formula:id,name', 'percentageOf:id,name,code'])
            ->orderBy('component_group')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'group' => $c->component_group ?? ($c->type === 'deduction' ? 'potongan' : 'penerimaan'),
                'category' => ($c->component_group ?? ($c->type === 'deduction' ? 'potongan' : 'penerimaan')) === 'potongan' ? 'potongan' : 'pendapatan',
                'calc_type' => $this->calcType($c),
                'is_taxable' => (bool) $c->is_taxable,
                'is_bpjs_base' => (bool) $c->is_bpjs_base,
                'show_on_slip' => $c->show_on_slip ?? true,
                'calc_basis' => $c->calc_basis ?? 'fixed',
                'period_basis' => $c->period_basis,
                'formula' => $c->formula?->name,
                'basis_type' => $c->basis_type,
                'basis_value' => $c->basis_value !== null ? (float) $c->basis_value : null,
                'basis_min' => $c->basis_min !== null ? (float) $c->basis_min : null,
                'basis_max' => $c->basis_max !== null ? (float) $c->basis_max : null,
                'basis_cut_off_day' => $c->basis_cut_off_day,
                'payroll_formula_id' => $c->payroll_formula_id,
                'percentage_of_component_id' => $c->percentage_of_component_id,
                // Null reference reads as Gaji Pokok, which is what the engine
                // falls back to.
                'percentage_of' => $c->calc_basis === 'percentage'
                    ? ($c->percentageOf?->name ?? 'Gaji Pokok')
                    : null,
                'status' => $c->status,
                'values' => ($values->get($c->id) ?? collect())->map(fn (PayrollComponentValue $v): array => [
                    'id' => $v->id,
                    'kategori' => $v->kategori,
                    'employment_status' => $v->employment_status,
                    'position' => $v->position?->name,
                    'job_level' => $v->jobLevel?->name,
                    'branch' => $v->branch?->name,
                    'value' => (float) $v->value,
                    'note' => $v->note,
                ])->values()->all(),
            ]);

        $formulas = PayrollFormula::forTenant($tenantId)
            ->with(['items.component:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollFormula $f): array => [
                'id' => $f->id,
                'name' => $f->name,
                'note' => $f->note,
                'is_active' => (bool) $f->is_active,
                'items' => $f->items->map(fn (PayrollFormulaItem $i): array => [
                    'id' => $i->id,
                    'tipe' => $i->tipe,
                    'component' => $i->component?->name,
                    'component_id' => $i->payroll_component_id,
                    'operator' => $i->operator,
                    'nilai' => (float) $i->nilai,
                    'prorate' => (bool) $i->prorate,
                ])->all(),
            ]);

        $taxBpjs = BpjsProgram::orderBy('name')->get(['id', 'code', 'name', 'type', 'is_active'])
            ->map(fn (BpjsProgram $p): array => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'category' => 'bpjs',
                'is_active' => (bool) $p->is_active,
            ])
            ->prepend([
                'id' => 0,
                'code' => 'PPH21',
                'name' => 'PPh 21 (TER / Pasal 17)',
                'category' => 'pajak',
                'is_active' => true,
            ])
            ->values();

        return Inertia::render('avana/payroll-komponen/index', [
            'components' => $components,
            'formulas' => $formulas,
            'taxBpjs' => $taxBpjs,
            'kpis' => [
                'pendapatan' => $components->where('category', 'pendapatan')->count(),
                'pendapatan_active' => $components->where('category', 'pendapatan')->where('status', 'active')->count(),
                'potongan' => $components->where('category', 'potongan')->count(),
                'potongan_active' => $components->where('category', 'potongan')->where('status', 'active')->count(),
                'pajak_bpjs' => $taxBpjs->count(),
            ],
            'componentOptions' => PayrollComponent::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PayrollComponent $c): array => ['id' => $c->id, 'name' => $c->name])
                ->all(),
            'formulaOptions' => $formulas->map(fn (array $f): array => ['id' => $f['id'], 'name' => $f['name']])->all(),
            // What a Persentase component may be a percentage of: any earning
            // that carries a rupiah figure of its own.
            'percentageBaseOptions' => PayrollComponent::forTenant($tenantId)
                ->where(fn ($query) => $query->whereNull('component_group')->orWhere('component_group', '!=', 'potongan'))
                ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', 'deduction'))
                ->where(fn ($query) => $query->whereNull('calc_basis')->orWhere('calc_basis', '!=', 'percentage'))
                ->orderByRaw(BasicWageComponent::orderFirstSql())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (PayrollComponent $c): array => ['id' => $c->id, 'name' => $c->name])
                ->all(),
            'mappingOptions' => [
                'positions' => Position::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Position $p): array => ['id' => $p->id, 'name' => $p->name])->all(),
                'jobLevels' => JobLevel::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (JobLevel $j): array => ['id' => $j->id, 'name' => $j->name])->all(),
                'branches' => Branch::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Branch $b): array => ['id' => $b->id, 'name' => $b->name])->all(),
                'kategori' => Employee::forTenant($tenantId)->whereNotNull('kategori')->distinct()->orderBy('kategori')->pluck('kategori')->all(),
                'statuses' => Employee::forTenant($tenantId)->whereNotNull('employment_status')->distinct()->orderBy('employment_status')->pluck('employment_status')->all(),
            ],
        ]);
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $tenantId = (int) $request->user()->tenant_id;

        $data = $this->validateComponent($request);

        PayrollComponent::create([
            'tenant_id' => $tenantId,
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'type' => $data['group'] === 'potongan' ? 'deduction' : 'earning',
            'component_group' => $data['group'],
            'is_taxable' => $request->boolean('is_taxable'),
            'is_bpjs_base' => $request->boolean('is_bpjs_base'),
            'show_on_slip' => $request->boolean('show_on_slip'),
            'calc_basis' => $data['calc_basis'],
            'period_basis' => $data['period_basis'] ?? null,
            'status' => 'active',
            'is_fixed' => $this->canBeFixedAllowance($data),
            ...$this->basisAttributes($data),
        ]);

        return back()->with('success', 'Komponen disimpan');
    }

    public function updateComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $component->tenant_id);

        $data = $this->validateComponent($request);

        $component->update([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'type' => $data['group'] === 'potongan' ? 'deduction' : 'earning',
            'component_group' => $data['group'],
            'is_taxable' => $request->boolean('is_taxable'),
            'is_bpjs_base' => $request->boolean('is_bpjs_base'),
            'show_on_slip' => $request->boolean('show_on_slip'),
            'calc_basis' => $data['calc_basis'],
            'period_basis' => $data['period_basis'] ?? null,
            // Setup Lembur owns the "Tetap" flag, so editing a component here
            // leaves the tenant's choice alone — unless the edit makes the
            // component one that cannot be a fixed allowance at all.
            'is_fixed' => $component->is_fixed && $this->canBeFixedAllowance($data),
            ...$this->basisAttributes($data),
        ]);

        return back()->with('success', 'Komponen diperbarui');
    }

    /**
     * Whether a component could be part of the overtime basis at all.
     *
     * `is_fixed` is the "Tetap" mark Setup Lembur puts on the allowances that
     * join Gaji Pokok in the overtime basis (PP 35/2021 Pasal 30). The column
     * defaulted to true and this screen never wrote it, so every component
     * created here — a per-day meal allowance, a co-op deduction, the overtime
     * line itself — was born inside the basis it had no business being in, and
     * inflated the hourly wage every overtime hour was paid at.
     *
     * A deduction is never a wage. An allowance paid per present day or per
     * overtime hour is variable, which is the opposite of fixed. What is left
     * is a fixed monthly allowance, which is exactly what the article means.
     *
     * @param  array<string, mixed>  $data
     */
    private function canBeFixedAllowance(array $data): bool
    {
        if (($data['group'] ?? null) === 'potongan') {
            return false;
        }

        return ! in_array($data['calc_basis'] ?? null, ['per_present_day', 'per_overtime_hour'], true);
    }

    public function destroyComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureOwnership($request, $component->tenant_id);

        $component->delete();

        return back()->with('success', 'Komponen dihapus');
    }

    /**
     * Toggle a component active/inactive. Inactive components are skipped by the
     * payroll engine (which only loads status = active).
     */
    public function toggleComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $component->tenant_id);

        $active = $component->status === 'active';
        $component->update(['status' => $active ? 'inactive' : 'active']);

        return back()->with('success', $active ? 'Komponen dinonaktifkan' : 'Komponen diaktifkan');
    }

    /**
     * Derive the "Tipe Perhitungan" shown on the component list from the stored
     * basis: Rumus (formula) > Persentase > Per Hari > Jumlah Tetap.
     */
    private function calcType(PayrollComponent $c): string
    {
        if ($c->payroll_formula_id !== null || $c->basis_type === 'formula') {
            return 'rumus';
        }

        return match ($c->calc_basis) {
            'percentage' => 'persentase',
            'per_present_day' => 'per_hari',
            'per_overtime_hour' => 'per_jam',
            default => 'jumlah_tetap',
        };
    }

    /**
     * Retired: "Nilai Komponen" used to hold a rupiah figure scoped by
     * kategori/status/posisi/pangkat/cabang, which made Master Komponen a second
     * place a nominal could come from. Payroll no longer reads it, so writing
     * new rows would only create figures nobody is paid — the endpoint says so
     * instead of accepting them. Existing rows stay readable for audit.
     */
    public function storeComponentValue(Request $request, PayrollComponent $component): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $component->tenant_id);

        throw ValidationException::withMessages([
            'value' => 'Nominal komponen sekarang hanya ditetapkan lewat Master Gaji, bukan di Master Komponen.',
        ]);
    }

    public function destroyComponentValue(Request $request, PayrollComponentValue $value): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $value->tenant_id);

        $value->delete();

        return back()->with('success', 'Nilai komponen dihapus');
    }

    public function storeFormula(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('payroll_formulas', 'name')->where('tenant_id', $tenantId)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        PayrollFormula::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'note' => $data['note'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Master Formula dibuat');
    }

    public function destroyFormula(Request $request, PayrollFormula $formula): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureOwnership($request, $formula->tenant_id);

        $formula->delete();

        return back()->with('success', 'Master Formula dihapus');
    }

    public function storeFormulaItem(Request $request, PayrollFormula $formula): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $formula->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'tipe' => ['required', Rule::in(self::FORMULA_TIPES)],
            'payroll_component_id' => ['nullable', 'integer', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'operator' => ['required', Rule::in(['*', '+'])],
            'nilai' => ['required', 'numeric'],
            'prorate' => ['boolean'],
        ]);

        $formula->items()->create([
            'tipe' => $data['tipe'],
            'payroll_component_id' => $data['payroll_component_id'] ?? null,
            'operator' => $data['operator'],
            'nilai' => $data['nilai'],
            'prorate' => $request->boolean('prorate'),
            'sort_order' => (int) $formula->items()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Item formula ditambahkan');
    }

    public function destroyFormulaItem(Request $request, PayrollFormulaItem $item): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $item->loadMissing('formula');
        $this->ensureOwnership($request, $item->formula?->tenant_id);

        $item->delete();

        return back()->with('success', 'Item formula dihapus');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateComponent(Request $request): array
    {
        $tenantId = (int) $request->user()->tenant_id;

        return $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'group' => ['required', Rule::in(self::GROUPS)],
            'calc_basis' => ['required', Rule::in(self::CALC_BASES)],
            'period_basis' => ['nullable', Rule::in(['berjalan', 'bulan_lalu'])],
            'is_taxable' => ['boolean'],
            'is_bpjs_base' => ['boolean'],
            'show_on_slip' => ['boolean'],
            'basis_type' => ['nullable', Rule::in(self::BASIS_TYPES)],
            'basis_value' => ['nullable', 'numeric', 'min:0'],
            'basis_min' => ['nullable', 'numeric', 'min:0'],
            'basis_max' => ['nullable', 'numeric', 'min:0'],
            'basis_cut_off_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'payroll_formula_id' => ['nullable', 'integer', Rule::exists('payroll_formulas', 'id')->where('tenant_id', $tenantId)],
            // A percentage of a percentage has no rupiah figure to start from,
            // and a percentage of itself has none at all.
            'percentage_of_component_id' => [
                'nullable',
                'integer',
                Rule::notIn(array_filter([$request->route('component')?->id])),
                Rule::exists('payroll_components', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where(fn ($inner) => $inner->whereNull('calc_basis')->orWhere('calc_basis', '!=', 'percentage'))),
            ],
        ]);
    }

    /**
     * Map validated component input onto the model's basis (dasar perhitungan)
     * attributes, keeping only the fields relevant to the chosen basis type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function basisAttributes(array $data): array
    {
        $type = $data['basis_type'] ?? null;

        // A rupiah nominal belongs to Master Gaji (and the employee's salary
        // copied from it), never here: a component carrying its own figure gave
        // payroll a second source to pay from, and it won — an employee could be
        // paid a number nobody had assigned them. The one number that stays is
        // a Persentase component's percent, which is not rupiah and has no
        // meaning on a salary template.
        $isPercentage = ($data['calc_basis'] ?? null) === 'percentage';

        return [
            'basis_type' => $type,
            'basis_value' => $type === 'fixed' && $isPercentage ? ($data['basis_value'] ?? null) : null,
            'basis_min' => $type === 'formula' ? ($data['basis_min'] ?? null) : null,
            'basis_max' => $type === 'formula' ? ($data['basis_max'] ?? null) : null,
            'basis_cut_off_day' => $data['basis_cut_off_day'] ?? null,
            'payroll_formula_id' => $type === 'formula' ? ($data['payroll_formula_id'] ?? null) : null,
            // Only a Persentase component has something to be a percentage of.
            'percentage_of_component_id' => ($data['calc_basis'] ?? null) === 'percentage'
                ? ($data['percentage_of_component_id'] ?? null)
                : null,
        ];
    }

    private function ensureOwnership(Request $request, int|string|null $tenantId): void
    {
        abort_if((int) $tenantId !== (int) $request->user()->tenant_id, 404);
    }

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
