<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->unsignedTinyInteger('trial_session')->nullable();
            $table->index(['activity_id', 'trial_session', 'status'], 'interest_session_capacity');
        });
        Schema::table('activity_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('trial_session')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_events', fn (Blueprint $table) => $table->dropColumn('trial_session'));
        Schema::table('interests', function (Blueprint $table) {
            $table->dropIndex('interest_session_capacity');
            $table->dropColumn('trial_session');
        });
    }
};
