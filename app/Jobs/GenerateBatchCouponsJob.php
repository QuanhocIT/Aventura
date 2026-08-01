<?php

namespace App\Jobs;

use App\Models\Coupon;
use App\Models\CouponBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateBatchCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private CouponBatch $batch
    ) {}

    public function handle(): void
    {
        $this->batch->update(['status' => 'generating']);

        $prefix = strtoupper($this->batch->code_prefix);
        $count = $this->batch->code_count;
        $generated = 0;

        for ($i = 0; $i < $count; $i++) {
            $code = $this->generateUniqueCode($prefix);

            Coupon::create([
                'batch_id' => $this->batch->id,
                'code' => $code,
                'discount_type' => $this->batch->discount_type,
                'discount_value' => $this->batch->discount_value,
                'max_uses' => $this->batch->max_uses_per_code,
                'uses_count' => 0,
                'starts_at' => $this->batch->starts_at,
                'expires_at' => $this->batch->expires_at,
                'status' => 'active',
            ]);

            $generated++;

            if ($generated % 50 === 0) {
                $this->batch->update(['generated_count' => $generated]);
            }
        }

        $this->batch->update([
            'generated_count' => $generated,
            'status' => 'completed',
        ]);
    }

    private function generateUniqueCode(string $prefix): string
    {
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = $prefix.'-'.strtoupper(Str::random(8));

            if (! Coupon::where('code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix.'-'.strtoupper(Str::random(12));
    }

    public function failed(\Throwable $e): void
    {
        $this->batch->update(['status' => 'failed']);
    }
}
