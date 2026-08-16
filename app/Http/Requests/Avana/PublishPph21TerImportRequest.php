<?php

namespace App\Http\Requests\Avana;

class PublishPph21TerImportRequest extends PreviewPph21TerImportRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'preview_token' => ['required', 'string'],
        ];
    }
}
