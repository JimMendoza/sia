<?php

namespace App\Console\Commands;

use App\Models\Chatbot\KbChunk;
use App\Models\Chatbot\KbDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KbSeedDemoCommand extends Command
{
    protected $signature = 'kb:seed-demo';

    protected $description = 'Seed demo knowledge base documents and chunks';

    /**
     * @var array<int, array{
     *     id: string,
     *     title: string,
     *     type: string,
     *     source_url: ?string,
     *     status: string,
     *     version: ?string,
     *     chunks: array<int, array{
     *         chunk_index: int,
     *         content: string,
     *         page: ?int,
     *         metadata: array<string, mixed>
     *     }>
     * }>
     */
    private const DEMO_DOCUMENTS = [
        [
            'id' => '11111111-1111-1111-1111-111111111111',
            'title' => 'Manual de Tramites Regionales',
            'type' => KbDocument::TYPE_PDF,
            'source_url' => 'https://example.org/docs/manual-tramites.pdf',
            'status' => KbDocument::STATUS_ACTIVE,
            'version' => '1.0',
            'chunks' => [
                [
                    'chunk_index' => 0,
                    'content' => 'Para registrar una solicitud en mesa de partes virtual, el ciudadano debe completar el formulario unico y adjuntar un documento de identidad vigente.',
                    'page' => 3,
                    'metadata' => ['topic' => 'mesa_partes'],
                ],
                [
                    'chunk_index' => 1,
                    'content' => 'El tiempo de atencion para solicitudes administrativas simples es de hasta cinco dias habiles, salvo evaluaciones tecnicas adicionales.',
                    'page' => 5,
                    'metadata' => ['topic' => 'plazos'],
                ],
            ],
        ],
        [
            'id' => '22222222-2222-2222-2222-222222222222',
            'title' => 'Preguntas Frecuentes del Portal',
            'type' => KbDocument::TYPE_HTML,
            'source_url' => 'https://example.org/faq',
            'status' => KbDocument::STATUS_ACTIVE,
            'version' => '2026.02',
            'chunks' => [
                [
                    'chunk_index' => 0,
                    'content' => 'Si el sistema muestra error de acceso, se recomienda validar credenciales, limpiar cache del navegador y volver a intentar en cinco minutos.',
                    'page' => null,
                    'metadata' => ['topic' => 'acceso'],
                ],
            ],
        ],
    ];

    public function handle(): int
    {
        $documentsCount = 0;
        $chunksCount = 0;

        DB::transaction(function () use (&$documentsCount, &$chunksCount) {
            foreach (self::DEMO_DOCUMENTS as $documentData) {
                $document = KbDocument::query()->updateOrCreate(
                    ['id' => $documentData['id']],
                    [
                        'title' => $documentData['title'],
                        'type' => $documentData['type'],
                        'source_url' => $documentData['source_url'],
                        'status' => $documentData['status'],
                        'version' => $documentData['version'],
                    ],
                );

                KbChunk::query()->where('document_id', $document->id)->delete();

                foreach ($documentData['chunks'] as $chunkData) {
                    KbChunk::query()->create([
                        'id' => (string) Str::uuid(),
                        'document_id' => $document->id,
                        'chunk_index' => $chunkData['chunk_index'],
                        'content' => $chunkData['content'],
                        'page' => $chunkData['page'],
                        'metadata' => $chunkData['metadata'],
                        'created_at' => now(),
                    ]);

                    $chunksCount++;
                }

                $documentsCount++;
            }
        });

        $this->info("KB demo ready. Documents: {$documentsCount}. Chunks: {$chunksCount}.");

        return self::SUCCESS;
    }
}

