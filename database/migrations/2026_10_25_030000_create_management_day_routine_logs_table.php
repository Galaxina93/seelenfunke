<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('management_day_routine_logs')) {
            Schema::create('management_day_routine_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('day_routine_id')
                    ->constrained('management_day_routines')
                    ->onDelete('cascade');
                $table->date('date')->index();
                $table->boolean('is_completed')->default(true);
                $table->json('completed_steps')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->unique(['day_routine_id', 'date'], 'routine_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_day_routine_logs');
    }
};
