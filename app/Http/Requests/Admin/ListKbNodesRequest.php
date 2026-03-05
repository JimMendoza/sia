<?php

namespace App\Http\Requests\Admin;

use App\Models\Chatbot\KbNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListKbNodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_id' => ['nullable', 'uuid', 'exists:kb_documents,id'],
            'node_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in([
                KbNode::STATUS_DRAFT,
                KbNode::STATUS_PUBLISHED,
                KbNode::STATUS_ARCHIVED,
            ])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

