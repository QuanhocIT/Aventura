<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\NewsPost;
use Illuminate\Console\Command;

class PublishScheduledNewsPosts extends Command
{
    protected $signature = 'news:publish-scheduled';

    protected $description = 'Tự động xuất bản các bài viết đã đặt lịch khi đến thời điểm published_at';

    public function handle(): int
    {
        $posts = NewsPost::query()
            ->where('is_published', false)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('Không có bài viết nào cần xuất bản.');

            return Command::SUCCESS;
        }

        foreach ($posts as $post) {
            $post->update(['is_published' => true]);

            AuditLog::create([
                'restaurant_id' => null,
                'branch_id' => null,
                'user_id' => null,
                'user_role' => 'system',
                'event' => 'updated',
                'action' => 'news_post_auto_publish',
                'subject_type' => NewsPost::class,
                'subject_id' => $post->id,
                'old_values' => ['is_published' => false],
                'new_values' => ['is_published' => true],
                'ip_address' => null,
                'user_agent' => null,
            ]);

            $this->line("→ Đã xuất bản: #{$post->id} \"{$post->title}\" (lên lịch: {$post->published_at})");
        }

        $this->info("Đã xuất bản {$posts->count()} bài viết.");

        return Command::SUCCESS;
    }
}
