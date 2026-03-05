<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertKbNodeFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:80'],
            'fields.*.value' => ['nullable', 'string'],
            'fields.*.source_page_start' => ['nullable', 'integer', 'min:1'],
            'fields.*.source_page_end' => ['nullable', 'integer', 'min:1'],
            'fields.*.change_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

