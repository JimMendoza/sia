<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot\ChatConversation;
use App\Models\Chatbot\ChatMessage;
use Illuminate\Support\Str;
use Throwable;

class SendMessageService
{
    private const FALLBACK_NO_SUPPORT = 'No encuentro sustento en la base cargada para responder eso.';

    private const FALLBACK_SERVICE_UNAVAILABLE = 'El servicio de informacion no esta disponible. Intente nuevamente mas tarde.';
    private const MAX_DISAMBIGUATION_ROUNDS = 2;

    public function __construct(private readonly AiClient $aiClient)
    {
    }

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

        $conversationMetadata = (array) ($conversation->metadata ?? []);
        $updatedMetadata = $conversationMetadata;

        ChatMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $message,
            'created_at' => now(),
        ]);

        try {
            $state = (array) ($conversationMetadata['disambiguation'] ?? []);
            $pendingChoices = array_values(
                array_filter((array) ($state['choices'] ?? []), fn ($choice): bool => is_array($choice))
            );
            $round = (int) ($state['round'] ?? 0);
            $topK = (int) config('ai.retrieve_top_k', 5);

            if ($pendingChoices !== []) {
                [$answer, $sources, $updatedMetadata] = $this->handlePendingDisambiguation(
                    message: $message,
                    conversationId: $conversation->id,
                    pendingChoices: $pendingChoices,
                    state: $state,
                    metadata: $conversationMetadata,
                    topK: $topK,
                    round: $round,
                );
            } else {
                [$answer, $sources, $updatedMetadata] = $this->handleFreshQuestion(
                    message: $message,
                    conversationId: $conversation->id,
                    metadata: $conversationMetadata,
                    topK: $topK,
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $answer = self::FALLBACK_SERVICE_UNAVAILABLE;
            $sources = [];
            $updatedMetadata = $conversationMetadata;
        }

        if ($updatedMetadata !== $conversationMetadata) {
            $conversation->metadata = $updatedMetadata;
            $conversation->save();
        }

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

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $metadata
     * @param array<int, array<string, mixed>> $pendingChoices
     * @return array{0: string, 1: array<int, array{title: string, type: string, reference: string, url: ?string}>, 2: array<string, mixed>}
     */
    private function handlePendingDisambiguation(
        string $message,
        string $conversationId,
        array $pendingChoices,
        array $state,
        array $metadata,
        int $topK,
        int $round,
    ): array {
        $selection = $this->extractNumericSelection($message);

        if ($selection !== null && isset($pendingChoices[$selection - 1])) {
            $choice = $pendingChoices[$selection - 1];
            $queryText = (string) ($state['query'] ?? $message);
            $selectedNodeId = (string) ($choice['node_id'] ?? '');
            $documentId = isset($choice['document_id']) ? (string) $choice['document_id'] : null;

            if ($selectedNodeId === '') {
                return [self::FALLBACK_NO_SUPPORT, [], $this->clearDisambiguation($metadata)];
            }

            $chatResult = $this->aiClient->chat(
                message: $queryText,
                topK: $topK,
                selectedNodeId: $selectedNodeId,
                documentId: $documentId,
                conversationId: $conversationId,
            );

            $answer = (string) ($chatResult['answer'] ?? '');
            $sources = array_values((array) ($chatResult['sources'] ?? []));

            if ($answer === '') {
                $answer = self::FALLBACK_NO_SUPPORT;
            }

            if ($answer === self::FALLBACK_NO_SUPPORT) {
                $sources = [];
            }

            return [$answer, $sources, $this->clearDisambiguation($metadata)];
        }

        if ($round >= self::MAX_DISAMBIGUATION_ROUNDS) {
            return [self::FALLBACK_NO_SUPPORT, [], $this->clearDisambiguation($metadata)];
        }

        $metadata['disambiguation'] = [
            'round' => $round + 1,
            'query' => (string) ($state['query'] ?? ''),
            'choices' => $pendingChoices,
        ];

        return [
            $this->buildDisambiguationAnswer($pendingChoices),
            [],
            $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{0: string, 1: array<int, array{title: string, type: string, reference: string, url: ?string}>, 2: array<string, mixed>}
     */
    private function handleFreshQuestion(
        string $message,
        string $conversationId,
        array $metadata,
        int $topK,
    ): array {
        $resolved = $this->aiClient->resolveNodes(query: $message, limit: 5);
        $candidates = array_values((array) ($resolved['candidates'] ?? []));
        $strongMatch = (bool) ($resolved['strong_match'] ?? false);
        $selectedNodeId = null;

        if (isset($resolved['selected_node_id']) && $resolved['selected_node_id'] !== null) {
            $selectedNodeId = (string) $resolved['selected_node_id'];
        }

        if ($candidates === []) {
            return [self::FALLBACK_NO_SUPPORT, [], $this->clearDisambiguation($metadata)];
        }

        if ((! $strongMatch || $selectedNodeId === null || $selectedNodeId === '') && count($candidates) === 1) {
            $selectedNodeId = isset($candidates[0]['node_id']) ? (string) $candidates[0]['node_id'] : null;
        }

        if ($selectedNodeId === null || $selectedNodeId === '') {
            $metadata['disambiguation'] = [
                'round' => 1,
                'query' => $message,
                'choices' => array_slice($candidates, 0, 5),
            ];

            return [
                $this->buildDisambiguationAnswer((array) $metadata['disambiguation']['choices']),
                [],
                $metadata,
            ];
        }

        $selected = collect($candidates)
            ->first(fn (array $candidate): bool => ($candidate['node_id'] ?? null) === $selectedNodeId);
        $documentId = is_array($selected) && isset($selected['document_id'])
            ? (string) $selected['document_id']
            : null;

        $chatResult = $this->aiClient->chat(
            message: $message,
            topK: $topK,
            selectedNodeId: $selectedNodeId,
            documentId: $documentId,
            conversationId: $conversationId,
        );

        $answer = (string) ($chatResult['answer'] ?? '');
        $sources = array_values((array) ($chatResult['sources'] ?? []));

        if ($answer === '') {
            $answer = self::FALLBACK_NO_SUPPORT;
        }

        if ($answer === self::FALLBACK_NO_SUPPORT) {
            $sources = [];
        }

        return [$answer, $sources, $this->clearDisambiguation($metadata)];
    }

    /**
     * @param array<int, array<string, mixed>> $choices
     */
    private function buildDisambiguationAnswer(array $choices): string
    {
        $lines = ['Necesito que elijas el tramite o tema exacto. Responde solo con el numero:'];

        foreach (array_values($choices) as $index => $choice) {
            $title = trim((string) ($choice['title'] ?? 'Sin titulo'));
            $code = isset($choice['code']) && $choice['code'] !== null
                ? trim((string) $choice['code'])
                : '';
            $suffix = $code !== '' ? " ({$code})" : '';
            $lines[] = ($index + 1).". {$title}{$suffix}";
        }

        return implode("\n", $lines);
    }

    private function extractNumericSelection(string $message): ?int
    {
        $trimmed = trim($message);
        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function clearDisambiguation(array $metadata): array
    {
        unset($metadata['disambiguation']);

        return $metadata;
    }
}
