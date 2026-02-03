<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task_id');
            $table->string('task_name');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status'); // 'success', 'error', 'running'
            $table->text('output')->nullable(); // Konsolen-Ausgabe oder Fehlermeldung
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
