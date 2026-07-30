<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Cantidad añadida en este registro (no un valor absoluto): el
            // progreso actual de un objetivo numérico es
            // initial_value + SUM(value) de sus entradas.
            $table->decimal('value', 12, 2);
            $table->date('recorded_at');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['goal_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_progress_entries');
    }
};
