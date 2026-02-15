<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_check_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable(); // Optional: Pro User speichern
            $table->string('filter_type')->default('all'); // all, business, private
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->string('range_mode')->default('current_month'); // current_month, year, custom
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_check_configs');
    }
};
