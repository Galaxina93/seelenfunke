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
        Schema::create('shipping_zone_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // FIX: Explizite UUID-Spalte statt foreignId
            $table->uuid('shipping_zone_id');

            // Constraint explizit setzen
            $table->foreign('shipping_zone_id')
                ->references('id')
                ->on('shipping_zones')
                ->onDelete('cascade');

            $table->string('country_code', 2); // ISO Code (DE, AT, CH, US...)

            // Ein Land kann nur in einer Zone sein (Unique Index)
            $table->unique('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_countries');
    }
};
