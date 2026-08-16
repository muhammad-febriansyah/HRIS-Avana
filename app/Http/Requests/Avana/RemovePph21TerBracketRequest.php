<?php

namespace App\Http\Requests\Avana;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dropping a bracket is a republish like any other: it needs an effective date
 * and a reason, and it never touches the version already in force.
 */
class RemovePph21TerBracketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'effective_start_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.min' => 'Alasan perubahan minimal 10 karakter.',
            'effective_start_date.date_format' => 'Tanggal berlaku harus dalam format YYYY-MM-DD.',
        ];
    }
}
