<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('interest_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('activity_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_key', 80);
            $table->string('kind', 60);
            $table->string('title', 160);
            $table->text('message');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['source_key', 'recipient_id'], 'outbox_source_recipient_unique');
            $table->unique(['id', 'recipient_id'], 'outbox_identity_recipient_unique');
            $table->index(['recipient_id', 'delivered_at', 'id'], 'outbox_pending_recipient_index');
        });
        Schema::create('inbox_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('outbox_id')->unique();
            $table->unsignedBigInteger('recipient_id');
            // The outbox owns the activity FK; delivery must not acquire an activity lock.
            $table->unsignedBigInteger('activity_id');
            $table->string('kind', 60);
            $table->string('title', 160);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->foreign(['outbox_id', 'recipient_id'], 'inbox_outbox_recipient_fk')
                ->references(['id', 'recipient_id'])->on('notification_outbox')->restrictOnDelete();
            $table->index(['recipient_id', 'created_at'], 'inbox_recipient_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_notifications');
        Schema::dropIfExists('notification_outbox');
    }
};
