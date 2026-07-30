<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('habit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->string('type', 20);
            $table->string('status', 20)->default('active');
            $table->string('priority', 10)->default('medium');

            $table->string('icon', 40)->default('target');
            $table->string('color', 7)->default('#5b5a8b');

            $table->date('start_date');
            $table->date('due_date')->nullable();

            $table->decimal('initial_value', 12, 2)->nullable();
            $table->decimal('target_value', 12, 2)->nullable();
            $table->string('unit', 40)->nullable();

            // Configuración específica por tipo (ej. periodo de medición de un
            // objetivo porcentual). Ver App\Enums\GoalType / GoalProgressCalculator.
            $table->json('settings')->nullable();

            $table->boolean('is_private')->default(false);
            $table->boolean('reminder_enabled')->default(false);
            $table->date('reminder_date')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
