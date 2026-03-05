<?php

namespace App\Console\Commands;

use App\Services\Chatbot\AiClient;
use Illuminate\Console\Command;

class KbImportExcelCommand extends Command
{
    protected $signature = 'kb:import-excel
        {path : Absolute or relative path to .xlsx file}
        {--mode=upsert : Import mode}
        {--no-embed : Skip embeddings regeneration}';

    protected $description = 'Import KB data from Excel via AI Service';

    public function __construct(private readonly AiClient $aiClient)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pathInput = (string) $this->argument('path');
        $absolutePath = realpath($pathInput) ?: $pathInput;
        $mode = (string) $this->option('mode');
        $embed = ! (bool) $this->option('no-embed');

        $result = $this->aiClient->ingestExcel(
            path: $absolutePath,
            mode: $mode,
            embed: $embed,
        );

        $this->line(json_encode([
            'documents_upserted' => (int) ($result['documents_upserted'] ?? 0),
            'nodes_upserted' => (int) ($result['nodes_upserted'] ?? 0),
            'chunks_upserted' => (int) ($result['chunks_upserted'] ?? 0),
            'embedded' => (int) ($result['embedded'] ?? 0),
            'errors' => array_values((array) ($result['errors'] ?? [])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

