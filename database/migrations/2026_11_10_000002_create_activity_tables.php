<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_token')->unique();
            $table->string('title', 160);
            $table->string('category', 60);
            $table->string('status', 40)->default('pending_approval')->index();
            $table->unsignedInteger('version')->default(1);
            $table->json('details');
            $table->text('review_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'created_at']);
        });
        Schema::create('activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('activities');
    }
};
