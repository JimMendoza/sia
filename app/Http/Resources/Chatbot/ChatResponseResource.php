<?php

namespace App\Http\Resources\Chatbot;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array $resource
 */
class ChatResponseResource extends JsonResource
{
    /**
     * Keep the API contract flat (no top-level "data" wrapper).
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'conversation_id' => $this->resource['conversation_id'],
            'message_id' => $this->resource['message_id'],
            'answer' => $this->resource['answer'],
            'sources' => $this->resource['sources'],
            'policy' => $this->resource['policy'],
        ];
    }
}
