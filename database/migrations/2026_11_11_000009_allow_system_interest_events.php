<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interest_events', fn (Blueprint $table) => $table->unsignedBigInteger('actor_id')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('interest_events')->whereNull('actor_id')->exists()) {
            throw new RuntimeException('Cannot restore the actor requirement while automated audit events exist.');
        }
        Schema::table('interest_events', fn (Blueprint $table) => $table->unsignedBigInteger('actor_id')->nullable(false)->change());
    }
};
