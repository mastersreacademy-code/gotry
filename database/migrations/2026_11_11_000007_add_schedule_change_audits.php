<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interest_events', fn (Blueprint $table) => $table->json('schedule_change')->nullable());
        Schema::table('activity_events', fn (Blueprint $table) => $table->json('schedule_change')->nullable());
    }

    public function down(): void
    {
        Schema::table('interest_events', fn (Blueprint $table) => $table->dropColumn('schedule_change'));
        Schema::table('activity_events', fn (Blueprint $table) => $table->dropColumn('schedule_change'));
    }
};
