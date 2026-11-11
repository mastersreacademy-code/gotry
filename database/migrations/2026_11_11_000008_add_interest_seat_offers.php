<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interests', fn (Blueprint $table) => $table->timestamp('offer_expires_at')->nullable());
        Schema::table('interest_events', fn (Blueprint $table) => $table->timestamp('offer_expires_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('interest_events', fn (Blueprint $table) => $table->dropColumn('offer_expires_at'));
        Schema::table('interests', fn (Blueprint $table) => $table->dropColumn('offer_expires_at'));
    }
};
