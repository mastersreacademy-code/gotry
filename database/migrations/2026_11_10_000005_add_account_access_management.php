<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('access_version')->default(1);
            $table->index('role','users_role_index');
        });
        Schema::create('account_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->boolean('from_active'); $table->boolean('to_active');
            $table->unsignedInteger('version'); $table->text('reason'); $table->timestamps();
            $table->unique(['user_id','version']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('account_events');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_index'); $table->dropColumn(['is_active','access_version']);
        });
    }
};
