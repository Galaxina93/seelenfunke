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
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // z.B. "Standard", "Express"

            // Bedingungen (z.B. "ab 0kg bis 5kg" oder "ab 0€ bis 50€")
            // Wir machen hier eine gewichtsbasierte Berechnung + Preisgrenze
            $table->decimal('min_weight', 8, 2)->default(0); // in Gramm oder KG
            $table->decimal('max_weight', 8, 2)->nullable(); // Null = Unendlich

            $table->integer('min_price')->default(0); // in Cent (für "Versandkostenfrei ab X")

            $table->integer('price'); // Kosten in Cent

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
