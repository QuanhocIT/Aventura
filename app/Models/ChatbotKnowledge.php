<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotKnowledge extends Model
{
    protected $table = 'chatbot_knowledge';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'alt_questions' => 'array',
            'keywords' => 'array',
            'suggested_questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function incrementViews(): void
    {
        $this->increment('view_count');
    }

    public function incrementHelpful(): void
    {
        $this->increment('helpful_count');
    }

    public function incrementUnhelpful(): void
    {
        $this->increment('unhelpful_count');
    }
}
