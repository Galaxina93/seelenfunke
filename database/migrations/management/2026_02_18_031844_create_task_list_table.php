<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('icon')->default('list-bullet');
            $table->string('color')->default('#C5A059');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_list_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('parent_id')->nullable()->constrained('tasks')->onDelete('cascade');
            $table->text('title');
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->string('priority')->default('low');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_lists');
    }
};
