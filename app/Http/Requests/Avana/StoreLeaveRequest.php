<?php

namespace App\Http\Requests\Avana;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
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
     * Reject the request when the leave type forbids negative balances and the
     * requested duration exceeds the employee's remaining balance for the year.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $leaveType = LeaveType::forTenant($this->user()->tenant_id)
                ->find($this->input('leave_type_id'));

            if ($leaveType === null || $leaveType->allow_negative) {
                return;
            }

            $start = Carbon::parse($this->input('start_date'));
            $end = Carbon::parse($this->input('end_date'));
            $totalDays = (int) $start->diffInDays($end) + 1;

            $balance = LeaveBalance::query()
                ->where('employee_id', $this->input('employee_id'))
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $start->year)
                ->first();

            $remaining = $balance !== null
                ? (float) $balance->remaining
                : (float) $leaveType->default_quota;

            if ($totalDays > $remaining) {
                $validator->errors()->add(
                    'leave_type_id',
                    sprintf('Saldo cuti tidak mencukupi (sisa %s hari).', $this->formatDays($remaining)),
                );
            }
        });
    }

    /**
     * Format a remaining-day count without trailing decimal zeros.
     */
    private function formatDays(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
