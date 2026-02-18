<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('day_routine_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('day_routine_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->integer('position')->default(0);
            $table->integer('duration_minutes')->default(5); // Wie lange dauert dieser EINZELNE Schritt?
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('day_routine_steps');
    }
};
