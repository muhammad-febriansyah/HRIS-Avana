<?php

namespace App\Http\Requests\Avana;

use App\Support\Pph21Ter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePph21TerCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'ptkp_status' => ['required', Rule::in(Pph21Ter::PTKP_STATUSES)],
            'category' => ['required', Rule::in(['A', 'B', 'C'])],
            'effective_start_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
