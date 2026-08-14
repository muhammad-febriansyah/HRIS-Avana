<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

final class TrackingLocationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tracking_session_id' => ['required', 'integer', 'min:1'],
            'locations' => ['required', 'array', 'min:1', 'max:10'],
            'locations.*.client_uuid' => ['required', 'uuid', 'distinct:strict'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['required', 'numeric', 'min:0', 'max:10000'],
            'locations.*.altitude' => ['nullable', 'numeric', 'between:-1000,100000'],
            'locations.*.speed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'locations.*.heading' => ['nullable', 'numeric', 'between:0,360'],
            'locations.*.is_mocked' => ['nullable', 'boolean'],
            'locations.*.battery_level' => ['nullable', 'integer', 'between:0,100'],
            'locations.*.recorded_at' => [
                'required',
                'date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Carbon::parse((string) $value)->isAfter(now()->addMinutes(5))) {
                        $fail('Waktu lokasi tidak boleh berada jauh di masa depan.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locations.max' => 'Maksimal 10 titik lokasi per pengiriman.',
            'locations.*.client_uuid.distinct' => 'Setiap titik lokasi harus memiliki ID unik.',
            'locations.*.latitude.between' => 'Latitude lokasi tidak valid.',
            'locations.*.longitude.between' => 'Longitude lokasi tidak valid.',
        ];
    }
}
