<?php

namespace App\Http\Controllers\Api\Chatbot;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbot\ChatRequest;
use App\Http\Resources\Chatbot\ChatResponseResource;
use App\Services\Chatbot\SendMessageService;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

class PublicChatbotController extends Controller
{
    #[OA\Post(
        path: '/api/public/chatbot/chat',
        tags: ['Chatbot'],
        summary: 'Public chatbot chat (mock)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message'],
                properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Hola, necesito ayuda'),
                    new OA\Property(property: 'conversation_id', type: 'string', format: 'uuid', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    required: ['conversation_id', 'message_id', 'answer', 'sources', 'policy'],
                    properties: [
                        new OA\Property(property: 'conversation_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'message_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'answer', type: 'string'),
                        new OA\Property(
                            property: 'sources',
                            type: 'array',
                            items: new OA\Items(
                                required: ['title', 'type', 'reference', 'url'],
                                properties: [
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'type', type: 'string', enum: ['pdf', 'api']),
                                    new OA\Property(property: 'reference', type: 'string'),
                                    new OA\Property(property: 'url', type: 'string', nullable: true),
                                ],
                            ),
                        ),
                        new OA\Property(
                            property: 'policy',
                            type: 'object',
                            required: ['mode', 'restricted'],
                            properties: [
                                new OA\Property(property: 'mode', type: 'string', example: 'public'),
                                new OA\Property(property: 'restricted', type: 'boolean', example: false),
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function chat(ChatRequest $request, SendMessageService $service): JsonResource
    {
        $result = $service->send(
            message: $request->validated('message'),
            conversationId: $request->validated('conversation_id'),
        );

        return new ChatResponseResource($result);
    }
}
