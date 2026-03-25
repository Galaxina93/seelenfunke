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
        Schema::create('product_packagings', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('material_type'); // Enum/String key: 'paper', 'plastic', 'glass', etc.
            $table->integer('weight_grams'); // Gewicht in Gramm
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_packagings');
    }
};
