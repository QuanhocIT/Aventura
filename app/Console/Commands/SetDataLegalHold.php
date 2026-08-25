<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Illuminate\Console\Command;

class SetDataLegalHold extends Command
{
    protected $signature = 'data:legal-hold
        {restaurant : Restaurant ID}
        {--release : Release the legal hold}
        {--reason= : Reason for applying the hold}';

    protected $description = 'Apply or release a tenant data legal hold';

    public function handle(): int
    {
        $restaurant = Restaurant::query()->find((int) $this->argument('restaurant'));
        if (! $restaurant) {
            $this->error('Restaurant not found.');

            return self::FAILURE;
        }

        if ($this->option('release')) {
            $restaurant->forceFill([
                'data_legal_hold' => false,
                'data_legal_hold_reason' => null,
                'data_legal_hold_at' => null,
                'data_legal_hold_by' => null,
            ])->save();
            $this->info("Legal hold released for restaurant #{$restaurant->id}.");

            return self::SUCCESS;
        }

        $reason = trim((string) ($this->option('reason') ?: 'Operational/legal review'));
        $restaurant->forceFill([
            'data_legal_hold' => true,
            'data_legal_hold_reason' => $reason,
            'data_legal_hold_at' => now(),
        ])->save();
        $this->info("Legal hold applied for restaurant #{$restaurant->id}.");

        return self::SUCCESS;
    }
}
