<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'tax_rate')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('tax_rate', 5, 2)->default(8)->after('tax_amount');
            });
        }

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'tax_code')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('tax_code', 30)->nullable()->after('email');
                $table->string('company_name', 255)->nullable()->after('tax_code');
            });
        }

        if (! Schema::hasTable('e_invoices')) {
            Schema::create('e_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('status', 30)->default('draft');
                $table->string('provider', 80)->nullable();
                $table->string('invoice_series', 80)->nullable();
                $table->string('invoice_number', 80)->nullable();
                $table->date('issue_date')->nullable();
                $table->string('customer_tax_code', 30)->nullable();
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->longText('xml_payload')->nullable();
                $table->json('provider_response')->nullable();
                $table->text('failure_reason')->nullable();
                $table->dateTime('issued_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['restaurant_id', 'order_id']);
                $table->index(['restaurant_id', 'status', 'issue_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoices');
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'tax_code')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropColumn(['tax_code', 'company_name']);
            });
        }
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'tax_rate')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('tax_rate');
            });
        }
    }
};
