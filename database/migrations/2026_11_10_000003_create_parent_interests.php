<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->constrained('users')->restrictOnDelete();
            $table->string('child_name', 80);
            $table->char('child_key', 64);
            $table->unsignedTinyInteger('child_age');
            $table->string('status', 20)->default('interested');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('activity_version');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'parent_id', 'child_key'], 'interest_child_unique');
            $table->index(['activity_id', 'status'], 'interest_capacity_index');
            $table->index(['parent_id', 'created_at']);
        });
        Schema::create('interest_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interest_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedInteger('version');
            $table->timestamps();
            $table->unique(['interest_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_events');
        Schema::dropIfExists('interests');
    }
};
