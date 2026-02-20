<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;

class AiClient
{
    /**
     * @return array<int, array{
     *     document_id: string,
     *     chunk_index: ?int,
     *     content: string,
     *     reference: ?string,
     *     page: ?int
     * }>
     */
    public function retrieve(string $query, int $topK = 5): array
    {
        $endpoint = rtrim((string) config('ai.service_url'), '/').'/retrieve';
        $timeout = (int) config('ai.timeout', 8);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'query' => $query,
                'top_k' => max(1, $topK),
            ]);

        $response->throw();

        $payload = $response->json();
        $chunks = is_array($payload) && isset($payload['chunks']) && is_array($payload['chunks'])
            ? $payload['chunks']
            : [];

        return collect($chunks)
            ->filter(fn ($chunk): bool => is_array($chunk))
            ->map(function (array $chunk): array {
                $chunkIndex = isset($chunk['chunk_index']) && is_numeric($chunk['chunk_index'])
                    ? (int) $chunk['chunk_index']
                    : null;

                $page = isset($chunk['page']) && is_numeric($chunk['page'])
                    ? (int) $chunk['page']
                    : null;

                return [
                    'document_id' => (string) ($chunk['document_id'] ?? ''),
                    'chunk_index' => $chunkIndex,
                    'content' => (string) ($chunk['content'] ?? ''),
                    'reference' => isset($chunk['reference']) ? (string) $chunk['reference'] : null,
                    'page' => $page,
                ];
            })
            ->filter(fn (array $chunk): bool => $chunk['document_id'] !== '')
            ->values()
            ->all();
    }
}
