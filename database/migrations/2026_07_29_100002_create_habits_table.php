<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 40);
            $table->string('color', 7);

            $table->string('type', 20);
            $table->string('frequency_type', 20);
            $table->json('frequency_config')->nullable();

            $table->decimal('target_quantity', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('unit_custom_label', 40)->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();

            $table->boolean('never_fail_twice')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('remind_at_enabled')->default(false);
            $table->time('remind_at')->nullable();

            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
