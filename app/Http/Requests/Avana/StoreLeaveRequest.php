<?php

namespace App\Http\Requests\Avana;

use App\Models\LeaveType;
use App\Services\LeaveQuota;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is enforced in the controller via the LeaveRequestPolicy.
     */
    public function authorize(): bool
    {
        return true;
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
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'leave_type_id' => [
                'required',
                Rule::exists('leave_types', 'id')->where('tenant_id', $tenantId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Reject the request when it would break either the parent quota or the
     * sub-type's own yearly cap. {@see LeaveQuota} holds the rules so the admin
     * form, the ESS page, and the mobile API stay in step.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $leaveType = LeaveType::forTenant($this->user()->tenant_id)
                ->with('parent')
                ->find($this->input('leave_type_id'));

            if ($leaveType === null) {
                return;
            }

            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            $totalDays = (float) ((int) $start->diffInDays($end) + 1);

            $message = LeaveQuota::check(
                (int) $this->input('employee_id'),
                $leaveType,
                $totalDays,
                $start->year,
            );

            if ($message !== null) {
                $validator->errors()->add('leave_type_id', $message);
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
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'employee_id.exists' => 'Karyawan yang dipilih tidak valid.',
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'leave_type_id.exists' => 'Jenis cuti yang dipilih tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Tanggal mulai tidak valid.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date' => 'Tanggal selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ];
    }
}
