<?php

namespace App\Http\Requests\Avana;

use App\Models\EmployeeSalaryComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ApplySalaryAssignmentRequest extends FormRequest
{
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
            'salary_master_id' => [
                'required',
                'integer',
                Rule::exists('salary_masters', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->where('status', 'active'),
            ],
            'preview_employee_ids' => ['required', 'array', 'min:1'],
            'preview_employee_ids.*' => [
                'required',
                'integer',
                'distinct:strict',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->where('status', 'active'),
            ],
            'preview_token' => ['required', 'string', 'size:64'],
            'effective_start_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
            'existing' => ['required', Rule::in(['skip', 'overwrite'])],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || filled($this->input('reason'))) {
                return;
            }

            // Assigning a template to somebody who has no salary yet is setup;
            // changing a salary that is already being paid is a decision, and
            // the history has to record why it was taken.
            $changesLiveSalary = EmployeeSalaryComponent::forTenant((int) $this->user()->tenant_id)
                ->whereIn('employee_id', (array) $this->input('employee_ids', []))
                ->inForce()
                ->effectiveOn($this->string('effective_start_date')->toString())
                ->exists();

            if ($changesLiveSalary) {
                $validator->errors()->add(
                    'reason',
                    'Sebagian karyawan sudah punya gaji berjalan — tulis alasan penetapannya.',
                );
            }
        }];
    }
}
