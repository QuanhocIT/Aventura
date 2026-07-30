<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\Onboarding\DemoDataSeederService;
use Illuminate\Console\Command;

class SeedRestaurantDemoData extends Command
{
    protected $signature = 'seed:restaurant-demo
        {--email=tamh77573@gmail.com : The email of the restaurant owner}
        {--template=bbq : The demo template (bbq, cafe, bubble_tea)}';

    protected $description = 'Seed rich sample data for a restaurant using the DemoDataSeederService. Delegates to the same logic used by the web onboarding wizard.';

    public function __construct(
        private readonly DemoDataSeederService $seeder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $email    = $this->option('email');
        $template = $this->option('template');

        $this->info("Starting demo seeding for: {$email} (template: {$template})");

        $owner = User::where('email', $email)->first();
        if (! $owner) {
            $this->error("User with email {$email} not found!");
            return self::FAILURE;
        }

        $restaurant = $owner->restaurant;
        if (! $restaurant) {
            $this->error("No restaurant found for user {$owner->name}!");
            return self::FAILURE;
        }

        $this->info("Restaurant: {$restaurant->name} (ID: {$restaurant->id})");

        // Reset existing demo data first (if any)
        if ($restaurant->sandbox_mode) {
            $this->warn('Resetting existing sandbox data...');
            $this->seeder->reset($restaurant);
        }

        // Seed via the shared service
        $this->seeder->seed($restaurant, $template);

        $this->info('✅ Demo data seeded successfully!');
        $this->table(
            ['Key', 'Value'],
            [
                ['Restaurant', $restaurant->name],
                ['Template', $template],
                ['Sandbox Mode', 'ON'],
            ]
        );

        return self::SUCCESS;
    }
}
