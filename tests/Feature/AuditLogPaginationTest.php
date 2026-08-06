<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_are_paginated_at_ten_rows_per_page(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $restaurant = Restaurant::factory()->create(['owner_user_id' => $owner->id]);

        $owner->update(['restaurant_id' => $restaurant->id]);
        $owner->assignRole('owner');

        foreach (range(1, 11) as $index) {
            AuditLog::create([
                'restaurant_id' => $restaurant->id,
                'user_id' => $owner->id,
                'user_role' => 'owner',
                'event' => 'updated',
                'action' => 'test_audit_log',
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $firstPage = $this->actingAs($owner)->get('/audit-logs');

        $firstPage->assertOk();
        $firstPage->assertInertia(fn ($page) => $page
            ->component('audit-logs/Index')
            ->where('logs.total', 11)
            ->where('logs.per_page', 10)
            ->where('logs.current_page', 1)
            ->where('logs.last_page', 2)
            ->where('logs.data', fn ($data) => count($data) === 10)
        );

        $secondPage = $this->actingAs($owner)->get('/audit-logs?page=2');

        $secondPage->assertOk();
        $secondPage->assertInertia(fn ($page) => $page
            ->where('logs.current_page', 2)
            ->where('logs.data', fn ($data) => count($data) === 1)
        );
    }
}
