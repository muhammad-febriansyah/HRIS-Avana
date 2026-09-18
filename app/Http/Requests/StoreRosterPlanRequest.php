<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRosterPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'name' => ['nullable', 'string', 'max:120'],
            'shift_label' => ['nullable', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'days' => ['required', 'array', 'min:1', 'max:62'],
            'days.*.date' => ['required', 'date'],
            'days.*.type' => ['required', Rule::in(['jadwal', 'libur'])],
            'days.*.category' => ['nullable', Rule::in(['wfo', 'wfh', 'wfa'])],
            'days.*.boundary_start' => ['nullable', 'date_format:H:i'],
            'days.*.boundary_end' => ['nullable', 'date_format:H:i'],
            'days.*.schedule_start' => ['nullable', 'date_format:H:i'],
            'days.*.schedule_end' => ['nullable', 'date_format:H:i'],
            'days.*.break_start' => ['nullable', 'date_format:H:i'],
            'days.*.break_end' => ['nullable', 'date_format:H:i'],
            'days.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $periodStart = Carbon::parse($this->input('period_start'))->startOfDay();
            $periodEnd = Carbon::parse($this->input('period_end'))->endOfDay();
            $dates = [];

            foreach ($this->input('days', []) as $index => $day) {
                $date = Carbon::parse($day['date'])->startOfDay();
                $dateKey = $date->format('Y-m-d');

                if ($date->lt($periodStart) || $date->gt($periodEnd)) {
                    $validator->errors()->add(
                        "days.{$index}.date",
                        'Tanggal harus berada di dalam periode plan.',
                    );
                }

                if (in_array($dateKey, $dates, true)) {
                    $validator->errors()->add(
                        "days.{$index}.date",
                        'Tanggal tidak boleh duplikat dalam satu plan.',
                    );
                }

                $dates[] = $dateKey;

                if ($day['type'] !== 'jadwal') {
                    continue;
                }

                if (blank($day['category'] ?? null)) {
                    $validator->errors()->add(
                        "days.{$index}.category",
                        'Kategori wajib diisi untuk tipe Jadwal.',
                    );
                }

                $this->validateTimePair($validator, $index, $day, 'boundary', 'Batas');
                $this->validateTimePair($validator, $index, $day, 'schedule', 'Jadwal');
                $this->validateTimePair($validator, $index, $day, 'break', 'Istirahat');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function validateTimePair(
        Validator $validator,
        int|string $index,
        array $day,
        string $prefix,
        string $label,
    ): void {
        $start = $day["{$prefix}_start"] ?? null;
        $end = $day["{$prefix}_end"] ?? null;

        if (blank($start) xor blank($end)) {
            $validator->errors()->add(
                "days.{$index}.{$prefix}_end",
                "Jam mulai dan selesai {$label} harus diisi berpasangan.",
            );
        }
    }
}
