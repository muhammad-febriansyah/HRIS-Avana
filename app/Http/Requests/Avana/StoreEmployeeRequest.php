<?php

namespace App\Http\Requests\Avana;

use App\Concerns\DescribesEmailConflict;
use App\Concerns\NormalisesContractType;
use App\Concerns\ResolvesTopApprover;
use App\Models\AttendancePolicy;
use App\Models\CustomField;
use App\Models\Employee;
use App\Support\EmployeeIdentity;
use App\Support\MaritalStatus;
use App\Support\Pph21Ter;
use App\Support\WorkingAge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use DescribesEmailConflict, NormalisesContractType, ResolvesTopApprover;

    /**
     * Fold the Atasan Langsung sentinel into manager_id + is_top_approver.
     */
    protected function prepareForValidation(): void
    {
        $this->resolveTopApprover();
        $this->normaliseContractType();
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'nik' => EmployeeIdentity::nikRules($tenantId),
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date', ...WorkingAge::birthDateRules()],
            'birth_place' => ['required', 'string', 'max:255'],
            'religion' => ['required', 'string', 'max:255'],
            'marital_status' => ['required', 'string', Rule::in(MaritalStatus::OPTIONS)],
            // Alamat and the two BPJS numbers are the only fields an admin may
            // leave for later: they arrive after the hire, not with it.
            'address' => ['nullable', 'string'],
            'employment_status' => ['required', 'in:probation,contract,permanent,resigned'],
            'join_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'employee_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('employees', 'employee_number')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            // Left empty ("Otomatis") means the employee follows the branch's
            // own location; the column is nullable to match.
            'work_location_id' => ['nullable', Rule::exists('work_locations', 'id')->where('tenant_id', $tenantId)],
            'attendance_scope' => ['nullable', Rule::in(AttendancePolicy::SCOPES)],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'position_id' => ['required', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'job_level_id' => ['required', Rule::exists('job_levels', 'id')->where('tenant_id', $tenantId)],
            'salary_master_id' => ['required', Rule::exists('salary_masters', 'id')->where('tenant_id', $tenantId)],
            // Contract details typed here create the row that the Kontrak
            // screen lists, so the same contract is not entered twice.
            'contract_number' => ['required', 'string', 'max:255'],
            'contract_type' => ['required', 'string', 'max:255'],
            'contract_start_date' => ['required', 'date'],
            // A PKWTT runs until the employee leaves, so it is the one contract
            // kind with no end date to type.
            'contract_end_date' => ['nullable', 'date', 'after:contract_start_date', 'required_unless:contract_type,pkwtt'],
            // Kept on the employee's BPJS profile, not the employee row.
            'bpjs_kesehatan_number' => ['nullable', 'string', 'max:32'],
            'ptkp_status' => ['required', 'string', Rule::in(array_keys(Pph21Ter::statutoryCategoryMap()))],
            'bpjs_ketenagakerjaan_number' => ['nullable', 'string', 'max:32'],
            // The payroll bank account, kept on its own row. Optional: a new
            // hire is often on the books before their rekening arrives, and the
            // transfer file simply leaves them out until it does.
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_top_approver' => ['nullable', 'boolean'],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
            // Either a password creates the login, or an existing account is
            // linked — never both, so neither can be required on its own.
            'password' => ['required_without:link_user_id', 'nullable', 'string', 'min:8'],
            // Attach an account that already exists instead of creating one.
            'link_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
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

            if (blank($this->managerChoice)) {
                $validator->errors()->add('manager_id', 'Atasan langsung wajib dipilih.');
            }

            $data = (array) $this->input('custom_data', []);

            foreach ($required as $field) {
                $value = $data[$field->key] ?? null;

                if ($value === null || $value === '') {
                    $validator->errors()->add('custom_data.'.$field->key, $field->label.' wajib diisi.');
                }
            }

            // An account is either made here or borrowed from one that already
            // exists — doing both would create a login the employee never uses.
            if (filled($this->input('link_user_id'))) {
                if (filled($this->input('password'))) {
                    $validator->errors()->add('password', 'Pilih salah satu: tautkan akun yang sudah ada, atau isi password untuk membuat akun baru.');
                }

                if (Employee::where('user_id', $this->input('link_user_id'))->exists()) {
                    $validator->errors()->add('link_user_id', 'Akun ini sudah tertaut ke karyawan lain.');
                }
            }

            // A login account is created only when a password is provided, and it
            // needs a unique email to sign in with.
            if (filled($this->input('password'))) {
                $email = $this->input('email');

                if (blank($email)) {
                    $validator->errors()->add('email', 'Email wajib diisi untuk membuat akun login.');
                } elseif (($conflict = $this->emailConflictMessage($email, (int) $tenantId)) !== null) {
                    $validator->errors()->add('email', $conflict);
                }

                // A new login must be told which role it holds. There is no
                // "default employee role" to fall back on any more — a tenant
                // names its own roles, so an unpicked role would leave the
                // account with an empty sidebar and a 403 on every page.
                if (blank($this->input('role_id'))) {
                    $validator->errors()->add('role_id', 'Pilih peran untuk akun ini — peran menentukan menu yang dilihat karyawan.');
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
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'nik.unique' => 'NIK ini sudah dipakai karyawan lain.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'birth_place.required' => 'Tempat lahir wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.before_or_equal' => WorkingAge::message(),
            'religion.required' => 'Agama wajib dipilih.',
            'marital_status.required' => 'Status pernikahan wajib dipilih.',
            'marital_status.in' => 'Status pernikahan tidak valid.',
            'join_date.required' => 'Tanggal masuk wajib diisi.',
            'branch_id.required' => 'Cabang wajib dipilih.',
            'department_id.required' => 'Departemen wajib dipilih.',
            'position_id.required' => 'Jabatan wajib dipilih.',
            'job_level_id.required' => 'Jenjang jabatan wajib dipilih.',
            'salary_master_id.required' => 'Master gaji wajib dipilih.',
            'contract_number.required' => 'Nomor kontrak wajib diisi.',
            'contract_type.required' => 'Jenis kontrak wajib dipilih.',
            'contract_start_date.required' => 'Tanggal mulai kontrak wajib diisi.',
            'contract_end_date.required_unless' => 'Tanggal berakhir kontrak wajib diisi kecuali untuk PKWTT.',
            'ptkp_status.required' => 'Status PTKP wajib dipilih.',
            'password.required_without' => 'Password wajib diisi, kecuali karyawan ini ditautkan ke akun yang sudah ada.',
            'employment_status.required' => 'Status kepegawaian wajib dipilih.',
            'employment_status.in' => 'Status kepegawaian tidak valid.',
            'status.required' => 'Status karyawan wajib dipilih.',
            'status.in' => 'Status karyawan tidak valid.',
            'employee_number.unique' => 'Nomor karyawan sudah digunakan.',
            'bank_account_number.max' => 'Nomor rekening terlalu panjang.',
            'branch_id.exists' => 'Cabang yang dipilih tidak valid.',
            'work_location_id.exists' => 'Lokasi kerja yang dipilih tidak valid.',
            'department_id.exists' => 'Departemen yang dipilih tidak valid.',
            'position_id.exists' => 'Posisi yang dipilih tidak valid.',
            'job_level_id.exists' => 'Jenjang jabatan yang dipilih tidak valid.',
            'salary_master_id.exists' => 'Master Gaji yang dipilih tidak valid.',
            'contract_end_date.after' => 'Tanggal berakhir kontrak harus setelah tanggal mulai.',
            'manager_id.exists' => 'Atasan yang dipilih tidak valid.',
        ];
    }
}
