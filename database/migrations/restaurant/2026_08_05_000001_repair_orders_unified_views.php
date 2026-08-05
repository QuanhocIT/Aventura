<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recreate the analytics compatibility views when an environment reports
     * the original view migration as complete but the views are missing.
     */
    public function up(): void
    {
        $this->recreateOrdersView();
        $this->recreateOrderItemsView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS orders_unified');
        DB::statement('DROP VIEW IF EXISTS order_items_unified');
    }

    private function recreateOrdersView(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $selects = [
            <<<'SQL'
                SELECT
                    id, restaurant_id, branch_id, table_id, customer_id,
                    created_by, cashier_user_id, order_number, channel, status,
                    payment_status, subtotal, discount_amount, service_charge,
                    tax_amount, total_amount, refund_amount, refund_reason,
                    note, online_notes, is_split, split_from_order_id,
                    is_red_flagged, is_override_split_penalty,
                    confirmed_at, completed_at, scheduled_at, cancelled_at,
                    cancelled_by, refunded_at, refunded_by,
                    created_at, updated_at, deleted_at
                FROM orders
                WHERE deleted_at IS NULL
            SQL,
        ];

        if (Schema::hasTable('orders_archive')) {
            $selects[] = <<<'SQL'
                SELECT
                    id, restaurant_id, branch_id, table_id, customer_id,
                    created_by, cashier_user_id, order_number, channel, status,
                    payment_status, subtotal, discount_amount, service_charge,
                    tax_amount, total_amount, refund_amount, refund_reason,
                    note, online_notes, is_split, split_from_order_id,
                    is_red_flagged, is_override_split_penalty,
                    confirmed_at, completed_at, scheduled_at, cancelled_at,
                    cancelled_by, refunded_at, refunded_by,
                    created_at, updated_at, NULL AS deleted_at
                FROM orders_archive
            SQL;
        }

        DB::statement('DROP VIEW IF EXISTS orders_unified');
        DB::statement('CREATE VIEW orders_unified AS '.implode(' UNION ALL ', $selects));
    }

    private function recreateOrderItemsView(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        $selects = [
            <<<'SQL'
                SELECT
                    id, restaurant_id, order_id, product_id, quantity,
                    unit_price, discount_amount, line_total, status,
                    notes, sent_to_kitchen_at, prepared_at, served_at,
                    created_at, updated_at
                FROM order_items
            SQL,
        ];

        if (Schema::hasTable('order_items_archive')) {
            $selects[] = <<<'SQL'
                SELECT
                    id, restaurant_id, order_id, product_id, quantity,
                    unit_price, discount_amount, line_total, status,
                    notes, sent_to_kitchen_at, prepared_at, served_at,
                    created_at, updated_at
                FROM order_items_archive
            SQL;
        }

        DB::statement('DROP VIEW IF EXISTS order_items_unified');
        DB::statement('CREATE VIEW order_items_unified AS '.implode(' UNION ALL ', $selects));
    }
};
