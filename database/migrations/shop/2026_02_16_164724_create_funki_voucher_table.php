<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funki_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title'); // Z.B. "Februar Aktion"
            $table->string('code')->nullable();  // Pattern für Auto
            $table->enum('type', ['fixed', 'percent']);
            $table->integer('value');
            $table->integer('used_count')->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('min_order_value')->nullable();

            // Für Automatisierung
            $table->string('trigger_event')->nullable();
            $table->integer('days_offset')->default(0);
            $table->integer('validity_days')->nullable(); // Wie lange gültig nach Erstellung

            // Unterscheidung: Auto (Funki) vs Manual (Admin)
            $table->enum('mode', ['auto', 'manual'])->default('auto');

            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funki_vouchers');
    }
};
