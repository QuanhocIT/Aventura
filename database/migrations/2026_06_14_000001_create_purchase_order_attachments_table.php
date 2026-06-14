<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->index();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('uploader_id');

            $table->string('disk', 20)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->enum('document_type', ['invoice', 'delivery_note', 'other'])->default('invoice');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('uploader_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['purchase_order_id', 'created_at'], 'poa_po_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_attachments');
    }
};
