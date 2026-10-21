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
        Schema::create('product_analytics_events', function (Blueprint $table) {
            $table->id();
            
            // Anonymer Session-Hash (keine IP, keine PII)
            $table->string('session_hash', 64)->index();
            
            // Herkunft: 'configurator' oder 'calculator'
            $table->string('source', 30)->index();
            
            // Event Name (z.B. configurator_view, mode_chosen, template_selected, symbol_added, etc.)
            $table->string('event_name', 60)->index();
            
            // Trichter-Schritt (0, 1, 2, 3, 4 für Rechner)
            $table->unsignedTinyInteger('step')->nullable()->index();
            
            // UUID Verknüpfungen zu Produkten und Vorlagen
            $table->uuid('product_id')->nullable()->index();
            $table->uuid('template_id')->nullable()->index();
            
            // Identifikator für Aktionsdaten (z.B. Dateiname des Symbols, Modus-Typ, Upload-Format)
            $table->string('item_identifier', 191)->nullable();
            
            // Geldwert / Cent-Betrag (z.B. Express-Gebühr, Artikelpreis, Rechner-Warenwert)
            $table->unsignedInteger('value_cents')->nullable();
            
            // Gerätetyp für Usability-Analyse
            $table->enum('device_type', ['desktop', 'tablet', 'mobile'])->default('desktop')->index();
            
            // Flexible Metadaten (Dauer in Sekunden, Kategorie, Fehlertext etc.)
            $table->json('meta_data')->nullable();
            
            // Erstellungszeitpunkt
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_analytics_events');
    }
};
