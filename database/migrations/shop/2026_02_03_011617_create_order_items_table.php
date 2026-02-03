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
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Löscht Items, wenn Bestellung gelöscht wird
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();

            // Falls das Produkt gelöscht wird, bleibt die Position erhalten (product_id wird null)
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('product_name'); // Snapshot des Namens zum Kaufzeitpunkt
            $table->integer('quantity');
            $table->integer('unit_price'); // Preis zum Kaufzeitpunkt
            $table->integer('total_price');

            // Konfiguration (Gravur etc.) als JSON
            $table->json('configuration')->nullable();

            $table->string('config_fingerprint')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
