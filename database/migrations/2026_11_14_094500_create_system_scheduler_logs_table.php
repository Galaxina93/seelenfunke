<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_scheduler_logs')) {
            Schema::create('system_scheduler_logs', function (Blueprint $table) {
                $table->id();
                $table->string('task_id', 64)->nullable()->index();
                $table->string('task_name', 191);
                $table->timestamp('started_at')->nullable()->index();
                $table->timestamp('finished_at')->nullable();
                $table->string('status', 32)->default('success')->index();
                $table->mediumText('output')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_scheduler_logs');
    }
};
