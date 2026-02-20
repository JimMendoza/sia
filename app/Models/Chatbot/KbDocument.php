<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbDocument extends Model
{
    use HasFactory, HasUuids;

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
        'title',
        'type',
        'source_url',
        'status',
        'version',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(KbChunk::class, 'document_id');
    }
}

