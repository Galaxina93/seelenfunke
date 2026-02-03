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

            // Spalten explizit als UUID definieren
            $table->uuid('order_id');
            $table->uuid('product_id');

            // Constraints separat definieren
            $table->foreign('order_id')
                ->references('id')
                ->on('orders') // Sicherstellen, dass die Tabelle 'orders' existiert!
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->string('product_name');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('total_price');
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
