<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Referrer who earns the commission
            $table->unsignedBigInteger('buyer_id'); // Owner user who registered and paid
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->unsignedBigInteger('billing_invoice_id')->nullable();
            $table->decimal('amount', 15, 2); // Base transaction price
            $table->decimal('commission_percentage', 5, 2); // percentage used e.g. 10.00
            $table->decimal('commission_amount', 15, 2); // final commission amount earned
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->nullOnDelete();
            $table->foreign('billing_invoice_id')->references('id')->on('billing_invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_logs');
    }
};
