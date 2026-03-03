<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Führt die Migration aus (Tabelle erstellen).
     */
    public function up(): void
    {
        Schema::create('product_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Verknüpfung zum Hauptprodukt. Wenn das Produkt gelöscht wird,
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();

            // Name der Vorlage (z.B. "Premium Hochzeit Layout")
            $table->string('name');

            // Die Konfigurationsdaten (Texte, Logos, Positionen etc.) im JSON-Format
            $table->json('configuration')->nullable();

            // Vorschaubild, das im Shop oder in der Auswahl angezeigt wird
            $table->string('preview_image')->nullable();

            // Status, ob die Vorlage für Kunden sichtbar ist
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Macht die Migration rückgängig (Tabelle löschen).
     */
    public function down(): void
    {
        Schema::dropIfExists('product_templates');
    }
};
