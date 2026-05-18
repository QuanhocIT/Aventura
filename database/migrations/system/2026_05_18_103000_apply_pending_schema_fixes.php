<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureOrderForeignKeys();
        $this->optimizeAuditLogIndex();
        $this->enhanceUnitsForConversion();
        $this->addPaymentGatewayTransactionCode();
        $this->createInventoryReservationsTable();
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_reservations')) {
            Schema::drop('inventory_reservations');
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'gateway_transaction_code')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('gateway_transaction_code');
            });
        }

        if (Schema::hasTable('units')) {
            if ($this->hasForeignKey('units', 'units_base_unit_id_foreign')) {
                DB::statement('ALTER TABLE units DROP FOREIGN KEY units_base_unit_id_foreign');
            }

            if (Schema::hasColumn('units', 'conversion_factor')) {
                Schema::table('units', function (Blueprint $table) {
                    $table->dropColumn('conversion_factor');
                });
            }

            if (Schema::hasColumn('units', 'base_unit_id')) {
                Schema::table('units', function (Blueprint $table) {
                    $table->dropColumn('base_unit_id');
                });
            }
        }

        if (Schema::hasTable('audit_logs')) {
            if ($this->hasIndex('audit_logs', 'audit_logs_restaurant_created_at_action_index')) {
                DB::statement('ALTER TABLE audit_logs DROP INDEX audit_logs_restaurant_created_at_action_index');
            }

            if (! $this->hasIndex('audit_logs', 'audit_logs_restaurant_action_created_index')) {
                DB::statement('ALTER TABLE audit_logs ADD INDEX audit_logs_restaurant_action_created_index (restaurant_id, action, created_at)');
            }
        }
    }

    private function ensureOrderForeignKeys(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('customers') || ! Schema::hasTable('users')) {
            return;
        }

        if (! $this->hasForeignKey('orders', 'orders_customer_id_foreign')) {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL');
        }

        if (! $this->hasForeignKey('orders', 'orders_cashier_user_id_foreign')) {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_cashier_user_id_foreign FOREIGN KEY (cashier_user_id) REFERENCES users (id) ON DELETE SET NULL');
        }

        if (Schema::hasTable('order_items') && Schema::hasTable('restaurants') && ! $this->hasForeignKey('order_items', 'order_items_restaurant_id_foreign')) {
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_restaurant_id_foreign FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE');
        }
    }

    private function optimizeAuditLogIndex(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! $this->hasIndex('audit_logs', 'audit_logs_restaurant_created_at_action_index')) {
            DB::statement('ALTER TABLE audit_logs ADD INDEX audit_logs_restaurant_created_at_action_index (restaurant_id, created_at, action)');
        }

        if ($this->hasIndex('audit_logs', 'audit_logs_restaurant_action_created_index')) {
            DB::statement('ALTER TABLE audit_logs DROP INDEX audit_logs_restaurant_action_created_index');
        }
    }

    private function enhanceUnitsForConversion(): void
    {
        if (! Schema::hasTable('units')) {
            return;
        }

        if (! Schema::hasColumn('units', 'base_unit_id')) {
            DB::statement('ALTER TABLE units ADD COLUMN base_unit_id BIGINT UNSIGNED NULL AFTER type');
        }

        if (! Schema::hasColumn('units', 'conversion_factor')) {
            DB::statement("ALTER TABLE units ADD COLUMN conversion_factor DECIMAL(12,4) NOT NULL DEFAULT '1.0000' AFTER base_unit_id");
        }

        if (! $this->hasForeignKey('units', 'units_base_unit_id_foreign')) {
            DB::statement('ALTER TABLE units ADD CONSTRAINT units_base_unit_id_foreign FOREIGN KEY (base_unit_id) REFERENCES units (id) ON DELETE SET NULL');
        }
    }

    private function addPaymentGatewayTransactionCode(): void
    {
        if (! Schema::hasTable('payments') || Schema::hasColumn('payments', 'gateway_transaction_code')) {
            return;
        }

        DB::statement('ALTER TABLE payments ADD COLUMN gateway_transaction_code VARCHAR(150) COLLATE utf8mb4_unicode_ci NULL AFTER transaction_code');
    }

    private function createInventoryReservationsTable(): void
    {
        if (Schema::hasTable('inventory_reservations')) {
            return;
        }

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->enum('status', ['holding', 'committed', 'released'])->default('holding');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'expires_at', 'status'], 'inv_reservations_restaurant_expires_status_index');
        });
    }

    private function hasForeignKey(string $tableName, string $constraintName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?'
            ,
            [$tableName, $constraintName, 'FOREIGN KEY']
        );

        return ((int) ($result->aggregate ?? 0)) > 0;
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?'
            ,
            [$tableName, $indexName]
        );

        return ((int) ($result->aggregate ?? 0)) > 0;
    }
};
