<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function appendMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toISOString(),
        ];
        $this->update(['messages' => $messages]);
    }
}
