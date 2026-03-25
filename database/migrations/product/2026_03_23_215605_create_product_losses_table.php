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
        Schema::create('product_losses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('quantity'); // Anzahl an kaputten Einheiten
            $table->integer('cost_value'); // Theoretischer Wertverlust in Cents (Einkaufspreis * Menge)
            $table->text('reason')->nullable(); // Grund für Schwund (z.B. Laser kaputt, Splitter, Falschlieferung)
            $table->string('recorded_by')->nullable(); // UUID des Admins
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_losses');
    }
};
