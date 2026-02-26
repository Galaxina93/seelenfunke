<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_gamifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->integer('funken_balance')->default(0); // Aktueller Kontostand
            $table->integer('funken_total_earned')->default(0); // Lifetime gesammelt
            $table->integer('level')->default(1);
            $table->integer('sparks_collected_today')->default(0);
            $table->date('last_spark_collection_date')->nullable();
            $table->json('titles_progress')->nullable(); // Speichert den Fortschritt der Titel
            $table->boolean('show_seelengott_badge')->default(false);

            // NEU: Energie-System für Spiele
            $table->integer('energy_balance')->default(5); // Aktuelle Leben/Energie
            $table->timestamp('last_energy_refill_at')->nullable(); // Wann wurde zuletzt aufgefüllt?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gamifications');
    }
};
