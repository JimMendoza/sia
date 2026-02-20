<?php

namespace App\Services\Chatbot;

use App\Models\Chatbot\ChatConversation;
use App\Models\Chatbot\ChatMessage;
use App\Models\Chatbot\KbChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class SendMessageService
{
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

        ChatMessage::query()->create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'role' => ChatMessage::ROLE_USER,
            'content' => $message,
            'created_at' => now(),
        ]);

        try {
            $retrievedChunks = $this->aiClient->retrieve(
                query: $message,
                topK: (int) config('ai.retrieve_top_k', 5),
            );

            $resolvedChunks = $this->resolveChunksFromKb($retrievedChunks);
            $chunksForAnswer = $resolvedChunks->take(2)->values();

            $answer = $this->buildAnswer($chunksForAnswer);
            $sources = $this->buildSources($chunksForAnswer);
        } catch (Throwable $exception) {
            report($exception);

            $answer = 'El servicio de informacion no esta disponible. Intente nuevamente mas tarde.';
            $sources = [];
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
     * @param  array<int, array{document_id: string, chunk_index: ?int, content: string, reference: ?string, page: ?int}>  $retrievedChunks
     * @return Collection<int, KbChunk>
     */
    private function resolveChunksFromKb(array $retrievedChunks): Collection
    {
        $chunks = collect();

        foreach ($retrievedChunks as $retrievedChunk) {
            $chunkIndex = $retrievedChunk['chunk_index'];

            if (! is_int($chunkIndex)) {
                continue;
            }

            $chunk = KbChunk::query()
                ->with('document')
                ->where('document_id', $retrievedChunk['document_id'])
                ->where('chunk_index', $chunkIndex)
                ->first();

            if ($chunk !== null) {
                $chunks->push($chunk);
            }
        }

        return $chunks
            ->unique(fn (KbChunk $chunk): string => $chunk->document_id.':'.$chunk->chunk_index)
            ->values();
    }

    /**
     * @param  Collection<int, KbChunk>  $chunks
     */
    private function buildAnswer(Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return 'Segun la documentacion cargada, no se encontraron coincidencias para esta consulta.';
        }

        $extracts = $chunks
            ->map(function (KbChunk $chunk, int $index): string {
                $excerpt = (string) Str::of($chunk->content)->squish()->limit(180, '...');

                return ($index + 1).') '.$excerpt;
            })
            ->implode(' ');

        return 'Segun la documentacion cargada: '.$extracts;
    }

    /**
     * @param  Collection<int, KbChunk>  $chunks
     * @return array<int, array{title: string, type: string, reference: string, url: ?string}>
     */
    private function buildSources(Collection $chunks): array
    {
        return $chunks
            ->map(function (KbChunk $chunk): array {
                $document = $chunk->document;
                $reference = $chunk->page !== null
                    ? 'page:'.$chunk->page
                    : 'chunk:'.$chunk->chunk_index;

                return [
                    'title' => $document?->title ?? 'Documento sin titulo',
                    'type' => $document?->type ?? 'txt',
                    'reference' => $reference,
                    'url' => $document?->source_url,
                ];
            })
            ->values()
            ->all();
    }
}
