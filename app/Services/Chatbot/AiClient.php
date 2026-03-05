<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;

class AiClient
{
    private const FALLBACK_NO_SUPPORT = 'No encuentro sustento en la base cargada para responder eso.';

    /**
     * @return array{
     *     answer: string,
     *     sources: array<int, array{title: string, type: string, reference: string, url: ?string}>
     * }
     */
    public function chat(
        string $message,
        int $topK = 5,
        ?string $selectedNodeId = null,
        ?string $documentId = null,
        ?string $conversationId = null,
    ): array {
        $endpoint = rtrim((string) config('ai.service_url'), '/').'/chat';
        $timeout = (int) config('ai.timeout', 8);
        $payload = [
            'message' => $message,
            'top_k' => max(1, $topK),
        ];

        if ($selectedNodeId !== null) {
            $payload['selected_node_id'] = $selectedNodeId;
        }

        if ($documentId !== null) {
            $payload['document_id'] = $documentId;
        }

        if ($conversationId !== null) {
            $payload['conversation_id'] = $conversationId;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);

        $response->throw();

        $json = $response->json();
        $answer = is_array($json) && isset($json['answer'])
            ? (string) $json['answer']
            : self::FALLBACK_NO_SUPPORT;

        return [
            'answer' => $answer,
            'sources' => $this->normalizeSources($json),
        ];
    }

    /**
     * @return array{
     *     strong_match: bool,
     *     selected_node_id: ?string,
     *     candidates: array<int, array{
     *         node_id: string,
     *         title: string,
     *         code: ?string,
     *         node_type: ?string,
     *         document_id: ?string,
     *         score: float
     *     }>
     * }
     */
    public function resolveNodes(string $query, ?string $documentId = null, int $limit = 5): array
    {
        $endpoint = rtrim((string) config('ai.service_url'), '/').'/resolve/nodes';
        $timeout = (int) config('ai.timeout', 8);
        $payload = [
            'query' => $query,
            'limit' => max(1, min(10, $limit)),
        ];

        if ($documentId !== null) {
            $payload['document_id'] = $documentId;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);

        $response->throw();

        $json = $response->json();
        $rawCandidates = is_array($json) && isset($json['candidates']) && is_array($json['candidates'])
            ? $json['candidates']
            : [];

        $candidates = collect($rawCandidates)
            ->filter(fn ($candidate): bool => is_array($candidate))
            ->map(function (array $candidate): array {
                return [
                    'node_id' => (string) ($candidate['node_id'] ?? ''),
                    'title' => (string) ($candidate['title'] ?? ''),
                    'code' => isset($candidate['code']) && $candidate['code'] !== null
                        ? (string) $candidate['code']
                        : null,
                    'node_type' => isset($candidate['node_type']) && $candidate['node_type'] !== null
                        ? (string) $candidate['node_type']
                        : null,
                    'document_id' => isset($candidate['document_id']) && $candidate['document_id'] !== null
                        ? (string) $candidate['document_id']
                        : null,
                    'score' => (float) ($candidate['score'] ?? 0.0),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['node_id'] !== '' && $candidate['title'] !== '')
            ->values()
            ->all();

        $selectedNodeId = is_array($json) && isset($json['selected_node_id']) && $json['selected_node_id'] !== null
            ? (string) $json['selected_node_id']
            : null;

        $strongMatch = (bool) (is_array($json) && ($json['strong_match'] ?? false));

        return [
            'strong_match' => $strongMatch,
            'selected_node_id' => $selectedNodeId,
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reindexNode(string $nodeId, bool $force = true): array
    {
        $endpoint = rtrim((string) config('ai.service_url'), '/').'/reindex/node';
        $timeout = (int) config('ai.timeout', 8);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'node_id' => $nodeId,
                'force' => $force,
            ]);

        $response->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function ingestExcel(string $path, string $mode = 'upsert', bool $embed = true): array
    {
        $sizeBytes = is_file($path) ? (int) filesize($path) : 0;
        logger()->info('AiClient ingestExcel path check', [
            'path' => $path,
            'exists' => is_file($path),
            'size_bytes' => $sizeBytes,
        ]);

        if (! is_file($path)) {
            throw new \RuntimeException("Excel file not found: {$path}");
        }

        if ($sizeBytes <= 0) {
            throw new \RuntimeException("Excel file is empty or unreadable: {$path}");
        }

        $endpoint = rtrim((string) config('ai.service_url'), '/').'/ingest/excel';
        $timeout = (int) config('ai.timeout', 8);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, [
                'path' => $path,
                'mode' => $mode,
                'embed' => $embed,
            ]);

        if ($response->failed()) {
            logger()->error('AiClient ingestExcel upstream error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'path' => $path,
                'response_body' => $response->body(),
            ]);
        }

        $response->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed>|mixed $payload
     * @return array<int, array{title: string, type: string, reference: string, url: ?string}>
     */
    private function normalizeSources(mixed $payload): array
    {
        $rawSources = is_array($payload) && isset($payload['sources']) && is_array($payload['sources'])
            ? $payload['sources']
            : [];

        return collect($rawSources)
            ->filter(fn ($source): bool => is_array($source))
            ->map(function (array $source): array {
                return [
                    'title' => (string) ($source['title'] ?? 'Documento sin titulo'),
                    'type' => (string) ($source['type'] ?? 'txt'),
                    'reference' => (string) ($source['reference'] ?? ''),
                    'url' => isset($source['url']) && $source['url'] !== null
                        ? (string) $source['url']
                        : null,
                ];
            })
            ->filter(fn (array $source): bool => $source['reference'] !== '')
            ->values()
            ->all();
    }
}
