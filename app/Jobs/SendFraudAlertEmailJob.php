<?php

namespace App\Jobs;

use App\Models\Restaurant;
use App\Mail\FraudAlertMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendFraudAlertEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $restaurantId,
        public array $alertData
    ) {}

    public function handle(): void
    {
        $restaurant = Restaurant::with('owner')->find($this->restaurantId);

        if (!$restaurant || !$restaurant->owner) {
            Log::warning('SendFraudAlertEmailJob: Không tìm thấy nhà hàng hoặc chủ nhà hàng', [
                'restaurant_id' => $this->restaurantId,
            ]);
            return;
        }

        $ownerEmail = $restaurant->owner->email;
        $restaurantName = $restaurant->name;

        Mail::to($ownerEmail)->send(new FraudAlertMail($restaurantName, $this->alertData));

        Log::info('SendFraudAlertEmailJob: Đã gửi email cảnh báo gian lận thành công', [
            'restaurant_id' => $this->restaurantId,
            'owner_email' => $ownerEmail,
        ]);
    }
}
