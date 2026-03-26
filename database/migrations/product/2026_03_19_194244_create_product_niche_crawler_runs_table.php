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
        Schema::create('product_niche_crawler_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('admin_id')->nullable();
            $table->string('name');
            $table->string('keyword')->nullable();
            $table->string('platform')->nullable();
            $table->json('products_data')->nullable(); // Alle 40 gescannten Produkte als Array.
            $table->text('ai_recommendation')->nullable(); // Speichert das finale Text-Urteil der KI.
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_niche_crawler_runs');
    }
};
