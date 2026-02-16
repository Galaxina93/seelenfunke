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
        Schema::create('system_check_configs', function (Blueprint $table) {
            $table->id();

            // WICHTIG: foreignUuid verwenden, da deine User-IDs Strings (UUIDs) sind.
            // constrained() sucht automatisch nach der Tabelle 'users' oder 'admins'.
            // Da du mehrere Guards hast, lassen wir constrained() hier ggf. generisch
            // oder definieren es explizit, falls du eine Haupt-User-Tabelle hast.
            $table->foreignUuid('user_id')->index()->onDelete('cascade');

            $table->string('filter_type')->default('all'); // business, private, all
            $table->date('date_start');
            $table->date('date_end');
            $table->string('range_mode')->default('year'); // year, current_month, custom

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_check_configs');
    }
};
