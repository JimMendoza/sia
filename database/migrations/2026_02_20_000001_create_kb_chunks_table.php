<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('page')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('document_id')
                ->references('id')
                ->on('kb_documents')
                ->cascadeOnDelete();

            $table->unique(['document_id', 'chunk_index']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_chunks');
    }
};

