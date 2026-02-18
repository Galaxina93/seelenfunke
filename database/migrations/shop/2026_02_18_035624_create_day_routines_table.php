<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('day_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->time('start_time'); // z.B. 13:00:00
            $table->string('title');
            $table->string('message')->nullable(); // Der Spruch dazu
            $table->string('icon')->default('clock');
            $table->string('type')->default('general'); // food, hygiene, sport, work, sleep
            $table->integer('duration_minutes')->default(30); // Wie lange dauert das?
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('day_routines');
    }
};
