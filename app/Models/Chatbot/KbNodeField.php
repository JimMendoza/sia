<?php

namespace App\Models\Chatbot;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbNodeField extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'kb_node_fields';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'node_id',
        'key',
        'value',
        'source_page_start',
        'source_page_end',
        'created_by',
        'updated_by',
        'change_note',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'node_id');
    }
}

