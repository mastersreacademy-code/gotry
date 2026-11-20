<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->restrictOnDelete();
            $table->string('first_name', 80);
            $table->string('surname', 40)->nullable();
            $table->unsignedTinyInteger('age');
            $table->char('registration_key', 64);
            $table->timestamps();
            $table->unique(['parent_id', 'registration_key']);
        });
        Schema::table('interests', function (Blueprint $table): void {
            $table->foreignId('child_id')->nullable()->constrained('children')->restrictOnDelete();
        });

        DB::table('interests')->orderBy('id')->chunkById(200, function ($interests): void {
            foreach ($interests as $interest) {
                DB::table('children')->insertOrIgnore([
                    'parent_id' => $interest->parent_id,
                    'first_name' => $interest->child_name,
                    'surname' => null,
                    'age' => $interest->child_age,
                    'registration_key' => $interest->child_key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $childId = DB::table('children')->where('parent_id', $interest->parent_id)
                    ->where('registration_key', $interest->child_key)->value('id');
                DB::table('interests')->where('id', $interest->id)->update(['child_id' => $childId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('interests', fn (Blueprint $table) => $table->dropConstrainedForeignId('child_id'));
        Schema::dropIfExists('children');
    }
};
