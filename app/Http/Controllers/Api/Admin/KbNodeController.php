<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateKbNodeRequest;
use App\Http\Requests\Admin\ListKbNodesRequest;
use App\Http\Requests\Admin\ReindexKbNodeRequest;
use App\Http\Requests\Admin\UpdateKbNodeRequest;
use App\Http\Requests\Admin\UpsertKbNodeFieldsRequest;
use App\Models\Chatbot\KbNode;
use App\Models\Chatbot\KbNodeField;
use App\Services\Chatbot\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class KbNodeController extends Controller
{
    public function index(ListKbNodesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = KbNode::query()
            ->with(['document:id,title,type,status'])
            ->orderBy('updated_at', 'desc');

        if (! empty($filters['document_id'])) {
            $query->where('document_id', $filters['document_id']);
        }

        if (! empty($filters['node_type'])) {
            $query->where('node_type', $filters['node_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->paginate($perPage));
    }

    public function store(CreateKbNodeRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $node = KbNode::query()->create([
            'id' => (string) Str::uuid(),
            'document_id' => $payload['document_id'],
            'parent_id' => $payload['parent_id'] ?? null,
            'node_type' => $payload['node_type'],
            'title' => $payload['title'],
            'code' => $payload['code'] ?? null,
            'status' => $payload['status'] ?? KbNode::STATUS_DRAFT,
            'valid_from' => $payload['valid_from'] ?? null,
            'valid_to' => $payload['valid_to'] ?? null,
            'change_note' => $payload['change_note'] ?? null,
        ])->fresh(['document:id,title,type,status', 'parent:id,title,node_type']);

        return response()->json($node, 201);
    }

    public function update(UpdateKbNodeRequest $request, string $id): JsonResponse
    {
        $node = KbNode::query()->findOrFail($id);
        $payload = $request->validated();

        if (array_key_exists('parent_id', $payload) && $payload['parent_id'] === $node->id) {
            return response()->json([
                'message' => 'parent_id cannot be the same node id.',
            ], 422);
        }

        $validFrom = array_key_exists('valid_from', $payload)
            ? $payload['valid_from']
            : $node->valid_from?->toDateString();
        $validTo = array_key_exists('valid_to', $payload)
            ? $payload['valid_to']
            : $node->valid_to?->toDateString();

        if ($validFrom !== null && $validTo !== null && $validTo < $validFrom) {
            return response()->json([
                'message' => 'valid_to must be after or equal to valid_from.',
            ], 422);
        }

        $node->fill([
            'parent_id' => array_key_exists('parent_id', $payload)
                ? $payload['parent_id']
                : $node->parent_id,
            'title' => $payload['title'] ?? $node->title,
            'code' => array_key_exists('code', $payload) ? $payload['code'] : $node->code,
            'status' => $payload['status'] ?? $node->status,
            'valid_from' => array_key_exists('valid_from', $payload) ? $payload['valid_from'] : $node->valid_from,
            'valid_to' => array_key_exists('valid_to', $payload) ? $payload['valid_to'] : $node->valid_to,
            'change_note' => array_key_exists('change_note', $payload) ? $payload['change_note'] : $node->change_note,
        ]);
        $node->save();

        return response()->json($node->fresh(['document:id,title,type,status', 'parent:id,title,node_type']));
    }

    public function upsertFields(UpsertKbNodeFieldsRequest $request, string $id): JsonResponse
    {
        $node = KbNode::query()->findOrFail($id);
        $items = $request->validated('fields');

        foreach ($items as $item) {
            KbNodeField::query()->updateOrCreate(
                [
                    'node_id' => $node->id,
                    'key' => $item['key'],
                ],
                [
                    'value' => $item['value'] ?? null,
                    'source_page_start' => $item['source_page_start'] ?? null,
                    'source_page_end' => $item['source_page_end'] ?? null,
                    'change_note' => $item['change_note'] ?? null,
                ],
            );
        }

        $fields = $node->fields()->orderBy('key')->get();

        return response()->json([
            'node_id' => $node->id,
            'fields' => $fields,
        ]);
    }

    public function fields(string $id): JsonResponse
    {
        $node = KbNode::query()->findOrFail($id);

        return response()->json([
            'node_id' => $node->id,
            'fields' => $node->fields()->orderBy('key')->get(),
        ]);
    }

    public function reindex(
        ReindexKbNodeRequest $request,
        string $id,
        AiClient $aiClient,
    ): JsonResponse {
        $node = KbNode::query()->findOrFail($id);
        $result = $aiClient->reindexNode(
            nodeId: $node->id,
            force: (bool) $request->boolean('force', true),
        );

        return response()->json([
            'node_id' => $node->id,
            'result' => $result,
        ]);
    }
}
