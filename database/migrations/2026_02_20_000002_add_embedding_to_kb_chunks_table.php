<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kb_chunks', 'embedding')) {
            Schema::table('kb_chunks', function (Blueprint $table) {
                $table->vector('embedding', 768)->nullable()->after('metadata');
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS kb_chunks_document_id_idx ON kb_chunks (document_id)');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_indexes
        WHERE schemaname = current_schema()
          AND tablename = 'kb_chunks'
          AND indexname IN ('kb_chunks_embedding_hnsw_idx', 'kb_chunks_embedding_ivfflat_idx')
    ) THEN
        RETURN;
    END IF;

    BEGIN
        EXECUTE 'CREATE INDEX kb_chunks_embedding_hnsw_idx ON kb_chunks USING hnsw (embedding vector_cosine_ops)';
    EXCEPTION
        WHEN undefined_object OR feature_not_supported THEN
            EXECUTE 'CREATE INDEX kb_chunks_embedding_ivfflat_idx ON kb_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)';
    END;
END
$$;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS kb_chunks_embedding_hnsw_idx');
        DB::statement('DROP INDEX IF EXISTS kb_chunks_embedding_ivfflat_idx');
        DB::statement('DROP INDEX IF EXISTS kb_chunks_document_id_idx');

        if (Schema::hasColumn('kb_chunks', 'embedding')) {
            Schema::table('kb_chunks', function (Blueprint $table) {
                $table->dropColumn('embedding');
            });
        }
    }
};

