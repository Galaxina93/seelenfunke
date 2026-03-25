<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // z.B. "Material"
            $table->string('slug')->nullable();
            $table->string('type')->default('physical'); // physical, digital, service (zur Filterung)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_attributes');
    }
};
