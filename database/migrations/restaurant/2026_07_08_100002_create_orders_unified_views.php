<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tạo VIEW orders_unified và order_items_unified — union hot + cold table.
 *
 * Mục đích: Các service analytics (BI, GoalTracking, MenuInsight...) query
 * bình thường mà không cần biết dữ liệu đang ở hot table hay archive.
 *
 * Cách dùng:
 *   DB::table('orders_unified')  → thay DB::table('orders') trong analytics
 *   DB::table('order_items_unified') → thay DB::table('order_items')
 *
 * Lưu ý hiệu năng:
 *   - MySQL optimizer có thể partition-prune trên orders_archive nếu WHERE
 *     có điều kiện trên created_at/completed_at (do partition RANGE).
 *   - Query trong 30 ngày gần nhất: orders (hot) thường đủ, archive ít rows.
 *   - Query lịch sử dài (1-3 năm): optimizer prune đúng partition → OK.
 *   - Không dùng VIEW này cho POS real-time (dùng Model Order trực tiếp).
 */
return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::statement('DROP VIEW IF EXISTS orders_unified');
        }

        // ── orders_unified ───────────────────────────────────────────────────
        DB::statement('
            CREATE '.($isSqlite ? '' : 'OR REPLACE ').'VIEW orders_unified AS
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

            UNION ALL

            SELECT
                id, restaurant_id, branch_id, table_id, customer_id,
                created_by, cashier_user_id, order_number, channel, status,
                payment_status, subtotal, discount_amount, service_charge,
                tax_amount, total_amount, refund_amount, refund_reason,
                note, online_notes, is_split, split_from_order_id,
                is_red_flagged, is_override_split_penalty,
                confirmed_at, completed_at, scheduled_at, cancelled_at,
                cancelled_by, refunded_at, refunded_by,
                created_at, updated_at, NULL as deleted_at
            FROM orders_archive
        ');

        if ($isSqlite) {
            DB::statement('DROP VIEW IF EXISTS order_items_unified');
        }

        // ── order_items_unified ──────────────────────────────────────────────
        DB::statement('
            CREATE '.($isSqlite ? '' : 'OR REPLACE ').'VIEW order_items_unified AS
            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items

            UNION ALL

            SELECT
                id, restaurant_id, order_id, product_id, quantity,
                unit_price, discount_amount, line_total, status,
                notes, sent_to_kitchen_at, prepared_at, served_at,
                created_at, updated_at
            FROM order_items_archive
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS orders_unified');
        DB::statement('DROP VIEW IF EXISTS order_items_unified');
    }
};
