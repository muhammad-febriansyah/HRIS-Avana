<?php

namespace App\Http\Requests\Avana;

use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\SalaryMasterComponent;
use App\Support\SalaryPeriodLock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreEmployeeSalaryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('effective_start_date') && $this->user() !== null) {
            $this->merge([
                'effective_start_date' => SalaryPeriodLock::suggestedDate((int) $this->user()->tenant_id)->toDateString(),
            ]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isSuperAdmin() || $user->hasPermissionTo('payroll.update'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = (int) $this->user()->tenant_id;

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->where('status', 'active'),
            ],
            'salary_master_id' => [
                'nullable',
                'integer',
                Rule::exists('salary_masters', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'effective_start_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
            'components' => ['present', 'array'],
            'components.*.payroll_component_id' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId),
            ],
            'components.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tenantId = (int) $this->user()->tenant_id;
            $employeeId = (int) $this->integer('employee_id');
            $masterId = $this->filled('salary_master_id') ? (int) $this->integer('salary_master_id') : null;
            $submittedIds = collect($this->input('components', []))
                ->pluck('payroll_component_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values();

            $components = PayrollComponent::forTenant($tenantId)
                ->whereIn('id', $submittedIds)
                ->get(['id', 'status', 'calc_basis']);

            if ($components->contains(fn (PayrollComponent $component): bool => ! in_array($component->status, [null, 'active'], true))) {
                $validator->errors()->add('components', 'Komponen nonaktif tidak boleh disimpan ke gaji karyawan.');

                return;
            }

            if ($components->contains(fn (PayrollComponent $component): bool => ! in_array($component->calc_basis, [null, 'fixed'], true))) {
                $validator->errors()->add('components', 'Tarif variable mengikuti snapshot Master Gaji dan tidak boleh dikirim sebagai nominal flat.');

                return;
            }

            $masterIds = $masterId === null
                ? collect()
                : SalaryMasterComponent::query()
                    ->where('salary_master_id', $masterId)
                    ->where('included', true)
                    ->whereHas('component', fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where(fn ($status) => $status->whereNull('status')->orWhere('status', 'active'))
                        ->where(fn ($basis) => $basis->whereNull('calc_basis')->orWhere('calc_basis', 'fixed')))
                    ->pluck('payroll_component_id');

            $overrideIds = EmployeeSalaryComponent::forTenant($tenantId)
                ->where('employee_id', $employeeId)
                ->where('source_type', 'employee_override')
                ->inForce()
                ->effectiveOn($this->string('effective_start_date')->toString())
                ->whereHas('component', fn ($query) => $query
                    ->where(fn ($status) => $status->whereNull('status')->orWhere('status', 'active'))
                    ->where(fn ($basis) => $basis->whereNull('calc_basis')->orWhere('calc_basis', 'fixed')))
                ->pluck('payroll_component_id');

            $allowedIds = $masterIds->merge($overrideIds)->unique()->sort()->values();

            if ($submittedIds->diff($allowedIds)->isNotEmpty()) {
                $validator->errors()->add('components', 'Daftar komponen sudah berubah. Muat ulang halaman Gaji Karyawan sebelum menyimpan.');
            }

            if ($masterId === null && $allowedIds->isEmpty()) {
                $validator->errors()->add('components', 'Pilih Master Gaji atau isi minimal satu komponen tetap.');
            }

            $this->requireReasonWhenItIsAChange($validator, $tenantId, $employeeId, $masterId);
        }];
    }

    /**
     * A reason is mandatory whenever the figure being saved is somebody's
     * decision rather than a copy of the template: a nominal that differs from
     * Master Gaji, or any change to a salary already in force.
     *
     * Without it the salary history answers "what changed" but never "why",
     * which is the question asked months later when the payslip is disputed.
     */
    private function requireReasonWhenItIsAChange(Validator $validator, int $tenantId, int $employeeId, ?int $masterId): void
    {
        if (filled($this->input('reason'))) {
            return;
        }

        $masterAmounts = $masterId === null
            ? collect()
            : SalaryMasterComponent::query()
                ->where('salary_master_id', $masterId)
                ->where('included', true)
                ->pluck('amount', 'payroll_component_id');

        $differsFromMaster = collect($this->input('components', []))
            ->contains(function (array $row) use ($masterAmounts): bool {
                $componentId = (int) ($row['payroll_component_id'] ?? 0);
                $amount = round((float) ($row['amount'] ?? 0), 2);

                return ! $masterAmounts->has($componentId)
                    || round((float) $masterAmounts[$componentId], 2) !== $amount;
            });

        if ($differsFromMaster) {
            $validator->errors()->add(
                'reason',
                'Nominal berbeda dari Master Gaji — tulis alasan perubahannya.',
            );

            return;
        }

        $hasLiveSalary = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->inForce()
            ->effectiveOn($this->string('effective_start_date')->toString())
            ->exists();

        if ($hasLiveSalary) {
            $validator->errors()->add(
                'reason',
                'Karyawan ini sudah punya gaji berjalan — tulis alasan perubahannya.',
            );
        }
    }
}
