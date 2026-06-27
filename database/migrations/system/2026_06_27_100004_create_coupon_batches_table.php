<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('template_id')->nullable()->constrained('campaign_templates')->nullOnDelete();
            $table->string('code_prefix', 20);
            $table->unsignedInteger('code_count');
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 12, 2);
            $table->unsignedInteger('max_uses_per_code')->default(1);
            $table->datetime('starts_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->enum('status', ['pending', 'generating', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('qr_sheet_path', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained('coupon_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('coupon_batches');
    }
};
