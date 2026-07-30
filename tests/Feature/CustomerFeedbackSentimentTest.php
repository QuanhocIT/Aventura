<?php

namespace Tests\Feature;

use App\Models\CustomerFeedback;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFeedbackSentimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sentiment_is_computed_automatically_on_create(): void
    {
        $restaurant = Restaurant::factory()->create();

        $feedback = CustomerFeedback::create([
            'restaurant_id' => $restaurant->id,
            'rating' => 5,
            'content' => 'Nhân viên rất nhiệt tình, món ăn ngon tuyệt vời!',
            'is_anonymous' => false,
            'status' => 'new',
        ]);

        $this->assertSame('positive', $feedback->sentiment);
        $this->assertGreaterThan(0, $feedback->sentiment_score);
    }

    public function test_sentiment_recomputed_when_content_updated(): void
    {
        $restaurant = Restaurant::factory()->create();

        $feedback = CustomerFeedback::create([
            'restaurant_id' => $restaurant->id,
            'rating' => 5,
            'content' => 'Rất ngon!',
            'is_anonymous' => false,
            'status' => 'new',
        ]);

        $this->assertSame('positive', $feedback->sentiment);

        $feedback->update(['content' => 'Thái độ nhân viên rất khó chịu, sẽ không quay lại.']);

        $this->assertSame('negative', $feedback->fresh()->sentiment);
    }
}
