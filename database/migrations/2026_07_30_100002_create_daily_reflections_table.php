<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reflections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('reflection_date');
            $table->string('status', 20)->default('draft');

            $table->unsignedTinyInteger('mood')->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable();
            $table->unsignedTinyInteger('productivity_level')->nullable();

            $table->text('went_well')->nullable();
            $table->text('challenges')->nullable();
            $table->text('learned')->nullable();
            $table->text('gratitude')->nullable();
            $table->text('improve_tomorrow')->nullable();
            $table->text('tomorrow_priority')->nullable();
            $table->text('free_notes')->nullable();

            $table->json('tags')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reflection_date']);
            $table->index(['user_id', 'reflection_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reflections');
    }
};
