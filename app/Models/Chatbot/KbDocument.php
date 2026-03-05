<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbDocument extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_PDF = 'pdf';
    public const TYPE_HTML = 'html';
    public const TYPE_TXT = 'txt';

    protected $table = 'kb_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'document_key',
        'title',
        'type',
        'source_url',
        'status',
        'version',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
        'metadata' => 'array',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(KbChunk::class, 'document_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(KbNode::class, 'document_id');
    }
}
