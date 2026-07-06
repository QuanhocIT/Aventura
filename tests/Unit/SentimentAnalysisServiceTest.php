<?php

namespace Tests\Unit;

use App\Services\SentimentAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentimentAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SentimentAnalysisService
    {
        return app(SentimentAnalysisService::class);
    }

    public function test_detects_positive_content(): void
    {
        $result = $this->service()->analyze('Món ăn rất ngon, nhân viên phục vụ nhiệt tình và thân thiện, chắc chắn sẽ quay lại!');

        $this->assertSame('positive', $result['sentiment']);
        $this->assertGreaterThan(0, $result['score']);
    }

    public function test_detects_negative_content(): void
    {
        $result = $this->service()->analyze('Đồ ăn nguội, phục vụ chậm, thái độ nhân viên rất khó chịu.');

        $this->assertSame('negative', $result['sentiment']);
        $this->assertLessThan(0, $result['score']);
    }

    public function test_negation_flips_positive_word_to_negative(): void
    {
        $result = $this->service()->analyze('Món này không ngon chút nào.');

        $this->assertSame('negative', $result['sentiment']);
    }

    public function test_neutral_when_no_keywords_matched(): void
    {
        $result = $this->service()->analyze('Tôi đã ăn ở đây vào thứ ba tuần trước.');

        $this->assertSame('neutral', $result['sentiment']);
        $this->assertSame(0.0, $result['score']);
    }

    public function test_null_for_empty_content(): void
    {
        $result = $this->service()->analyze(null);

        $this->assertNull($result['sentiment']);
        $this->assertNull($result['score']);

        $result = $this->service()->analyze('   ');
        $this->assertNull($result['sentiment']);
    }
}
