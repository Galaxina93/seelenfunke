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
        Schema::create('niche_products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('platform'); // Etsy, Amazon, AliExpress
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('sales_volume')->default(0)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('review_count')->default(0)->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->text('url')->nullable();
            $table->integer('niche_score')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('niche_products');
    }
};
