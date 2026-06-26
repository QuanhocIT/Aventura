<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('restaurant_branches')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->nullableMorphs('attachable');

            $table->string('collection', 100)->default('default');
            $table->enum('media_type', ['image', 'document', 'video', 'other'])->default('image');
            $table->string('disk', 50)->default('public');
            $table->string('file_name', 255);
            $table->string('file_path', 2048);
            $table->string('mime_type', 150)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->boolean('is_private')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'collection'], 'media_assets_restaurant_collection_index');
            $table->index(['attachable_type', 'attachable_id'], 'media_assets_attachable_index');
            $table->index(['media_type', 'created_at'], 'media_assets_type_created_index');
            $table->index('uploaded_by', 'media_assets_uploaded_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
