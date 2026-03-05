<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportKbExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:51200'],
            'mode' => ['sometimes', 'string', 'in:upsert'],
            'embed' => ['sometimes', 'boolean'],
        ];
    }
}

