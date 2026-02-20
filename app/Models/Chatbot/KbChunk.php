<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbChunk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kb_chunks';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'document_id',
        'chunk_index',
        'content',
        'page',
        'metadata',
        'embedding',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'embedding' => 'array',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KbDocument::class, 'document_id');
    }
}
