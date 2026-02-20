<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class KbReindexCommand extends Command
{
    protected $signature = 'kb:reindex';

    protected $description = 'Placeholder for KB reindex pipeline (future embeddings)';

    public function handle(): int
    {
        $this->info('kb:reindex placeholder executed. Embeddings pipeline is not enabled yet.');

        return self::SUCCESS;
    }
}

