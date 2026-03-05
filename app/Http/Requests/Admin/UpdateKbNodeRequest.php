<?php

namespace App\Http\Requests\Admin;

use App\Models\Chatbot\KbNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKbNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['sometimes', 'nullable', 'uuid', 'exists:kb_nodes,id'],
            'title' => ['sometimes', 'string', 'max:5000'],
            'code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in([
                KbNode::STATUS_DRAFT,
                KbNode::STATUS_PUBLISHED,
                KbNode::STATUS_ARCHIVED,
            ])],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date'],
            'change_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}

