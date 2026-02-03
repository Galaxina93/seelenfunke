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
            $table->uuid('id')->primary();

            // WICHTIG: Hier muss uuid stehen, nicht foreignId!
            $table->uuid('shipping_zone_id');

            $table->foreign('shipping_zone_id')
                ->references('id')
                ->on('shipping_zones')
                ->onDelete('cascade');

            $table->string('name');
            $table->integer('price');
            $table->integer('min_price')->default(0);
            $table->integer('min_weight')->default(0);
            $table->integer('max_weight')->default(31500);
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
