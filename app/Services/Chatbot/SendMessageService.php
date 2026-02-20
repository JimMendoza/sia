<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot\ChatConversation;
use App\Models\Chatbot\ChatMessage;
use Illuminate\Support\Str;

class SendMessageService
{
    /**
     * @return array{
     *     conversation_id: string,
     *     message_id: string,
     *     answer: string,
     *     sources: array<int, array{title: string, type: string, reference: string, url: ?string}>,
     *     policy: array{mode: string, restricted: bool}
     * }
     */
    public function send(string $message, ?string $conversationId = null): array
    {
        $conversationId = $conversationId ?: (string) Str::uuid();

        $conversation = ChatConversation::query()->firstOrCreate(
            ['id' => $conversationId],
            ['channel' => ChatConversation::CHANNEL_WEB],
        );

        ChatMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $message,
            'created_at' => now(),
        ]);

        $answer = 'Respuesta mock: este endpoint aun no integra IA, pero ya respeta el contrato de respuesta.';

        $sources = [
            [
                'title' => 'Documento SIA (Ejemplo)',
                'type' => 'pdf',
                'reference' => 'SIA-PDF-001',
                'url' => null,
            ],
            [
                'title' => 'Catálogo interno (Ejemplo)',
                'type' => 'api',
                'reference' => 'CAT-API-STATUS',
                'url' => null,
            ],
        ];

        $assistantMessageId = (string) Str::uuid();

        ChatMessage::query()->create([
            'id' => $assistantMessageId,
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $answer,
            'created_at' => now(),
        ]);

        return [
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessageId,
            'answer' => $answer,
            'sources' => $sources,
            'policy' => [
                'mode' => 'public',
                'restricted' => false,
            ],
        ];
    }
}
