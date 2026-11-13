<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_payments', function (Blueprint $table): void {
            $table->foreignId('interest_id')->primary()->constrained()->cascadeOnDelete();
            $table->uuid('attempt')->unique();
            $table->unsignedInteger('interest_version');
            $table->unsignedInteger('activity_version');
            $table->string('order_id', 64)->nullable()->unique();
            $table->string('capture_id', 64)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_payments');
    }
};
