<?php

namespace App\Http\Requests\Avana;

/**
 * Correcting a bracket publishes a new dated version of its whole category, so
 * it needs the new numbers, the date they start on, and the reason.
 */
class RevisePph21TerBracketRequest extends RemovePph21TerBracketRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'income_min' => ['required', 'numeric', 'min:0'],
            'income_max' => ['nullable', 'numeric', 'gt:income_min'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'income_max.gt' => 'Batas atas harus lebih besar dari batas bawah.',
            'rate.max' => 'Tarif ditulis sebagai desimal, mis. 0,0225 untuk 2,25%.',
        ];
    }
}
