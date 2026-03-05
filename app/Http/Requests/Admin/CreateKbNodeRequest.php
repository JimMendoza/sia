<?php

namespace App\Http\Requests\Admin;

use App\Models\Chatbot\KbNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKbNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['required', 'uuid', 'exists:kb_documents,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:kb_nodes,id'],
            'node_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:5000'],
            'code' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in([
                KbNode::STATUS_DRAFT,
                KbNode::STATUS_PUBLISHED,
                KbNode::STATUS_ARCHIVED,
            ])],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'change_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

