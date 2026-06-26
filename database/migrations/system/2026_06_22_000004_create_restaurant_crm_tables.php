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
        Schema::create('restaurant_internal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Admin who wrote the note
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('restaurant_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 30)->default('slate'); // e.g., red, blue, green, amber, slate
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('restaurant_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete(); // Admin assigned
            $table->string('note');
            $table->dateTime('remind_at');
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_followups');
        Schema::dropIfExists('restaurant_tags');
        Schema::dropIfExists('restaurant_internal_notes');
    }
};
