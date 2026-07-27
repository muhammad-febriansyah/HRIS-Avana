<?php

namespace App\Http\Requests\Avana;

use App\Concerns\ResolvesTopApprover;
use App\Models\AttendancePolicy;
use App\Models\CustomField;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use ResolvesTopApprover;

    /**
     * Fold the Atasan Langsung sentinel into manager_id + is_top_approver.
     */
    protected function prepareForValidation(): void
    {
        $this->resolveTopApprover();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'nik' => ['nullable', 'digits:16'],
            'gender' => ['nullable', 'in:male,female,unspecified'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'employment_status' => ['required', 'in:probation,contract,permanent,resigned'],
            'join_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'employee_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('employees', 'employee_number')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'work_location_id' => ['nullable', Rule::exists('work_locations', 'id')->where('tenant_id', $tenantId)],
            'attendance_scope' => ['nullable', Rule::in(AttendancePolicy::SCOPES)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'job_level_id' => ['nullable', Rule::exists('job_levels', 'id')->where('tenant_id', $tenantId)],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_top_approver' => ['nullable', 'boolean'],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
            'password' => ['nullable', 'string', 'min:8'],
            'custom_data' => ['nullable', 'array'],
            'custom_data.*' => ['nullable'],
        ];
    }

    /**
     * Enforce the tenant's required custom employee fields.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenantId = $this->user()->tenant_id;

            $required = CustomField::forTenant($tenantId)
                ->where('entity', 'employee')
                ->where('status', 'active')
                ->where('is_required', true)
                ->get(['key', 'label']);

            $data = (array) $this->input('custom_data', []);

            foreach ($required as $field) {
                $value = $data[$field->key] ?? null;

                if ($value === null || $value === '') {
                    $validator->errors()->add('custom_data.'.$field->key, $field->label.' wajib diisi.');
                }
            }

            // A login account is created only when a password is provided, and it
            // needs a unique email to sign in with.
            if (filled($this->input('password'))) {
                $email = $this->input('email');

                if (blank($email)) {
                    $validator->errors()->add('email', 'Email wajib diisi untuk membuat akun login.');
                } elseif (User::where('email', $email)->exists()) {
                    $validator->errors()->add('email', 'Email sudah digunakan akun lain.');
                }
            }
        });
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'employment_status.required' => 'Status kepegawaian wajib dipilih.',
            'employment_status.in' => 'Status kepegawaian tidak valid.',
            'status.required' => 'Status karyawan wajib dipilih.',
            'status.in' => 'Status karyawan tidak valid.',
            'employee_number.unique' => 'Nomor karyawan sudah digunakan.',
            'branch_id.exists' => 'Cabang yang dipilih tidak valid.',
            'work_location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
            'department_id.exists' => 'Departemen yang dipilih tidak valid.',
            'position_id.exists' => 'Posisi yang dipilih tidak valid.',
            'job_level_id.exists' => 'Jenjang jabatan yang dipilih tidak valid.',
            'manager_id.exists' => 'Atasan yang dipilih tidak valid.',
        ];
    }
}
