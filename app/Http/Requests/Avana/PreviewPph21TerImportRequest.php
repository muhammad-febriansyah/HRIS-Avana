<?php

namespace App\Http\Requests\Avana;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PreviewPph21TerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', File::types(['xlsx'])->max(5 * 1024)],
            'effective_start_date' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih workbook TER yang akan divalidasi.',
            'file.mimes' => 'Unggah berkas .xlsx yang valid.',
            'reason.min' => 'Alasan perubahan minimal 10 karakter.',
        ];
    }
}
