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
        Schema::create('quote_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('unit_price'); // Einzelpreis Netto/Brutto je nach Logik (hier Kalkulator-Preis)
            $table->integer('total_price');

            $table->json('configuration')->nullable(); // Hier liegen die Dateipfade drin!

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_request_items');
    }
};
