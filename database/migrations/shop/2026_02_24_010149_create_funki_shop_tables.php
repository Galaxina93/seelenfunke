<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Die kaufbaren Items im Shop
        Schema::create('funki_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['background', 'frame', 'skin', 'companion']); // Art des Cosmetics
            $table->enum('rarity', ['common', 'rare', 'epic', 'legendary'])->default('common');
            $table->integer('price_funken')->nullable(); // Preis in Funken
            $table->integer('price_money')->nullable(); // Optional: Preis in Cent (Echtgeld)
            $table->string('preview_image_path'); // Vorschaubild im Shop
            $table->string('asset_path'); // Pfad zur 3D-Datei (.glb) ODER CSS-Klasse für Rahmen/BG
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Das Inventar der Kunden (Welches Item gehört wem?)
        Schema::create('customer_funki_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('funki_item_id')->constrained('funki_items')->cascadeOnDelete();
            $table->string('purchased_via')->default('funken'); // 'funken' oder 'stripe'
            $table->timestamps();
        });

        // 3. Erweiterung des Gamification-Profils um die "ausgerüsteten" Items
        Schema::table('customer_gamifications', function (Blueprint $table) {
            $table->foreignId('active_background_id')->nullable()->constrained('funki_items')->nullOnDelete();
            $table->foreignId('active_frame_id')->nullable()->constrained('funki_items')->nullOnDelete();
            $table->foreignId('active_skin_id')->nullable()->constrained('funki_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_gamifications', function (Blueprint $table) {
            $table->dropForeign(['active_background_id']);
            $table->dropForeign(['active_frame_id']);
            $table->dropForeign(['active_skin_id']);
            $table->dropColumn(['active_background_id', 'active_frame_id', 'active_skin_id']);
        });
        Schema::dropIfExists('customer_funki_items');
        Schema::dropIfExists('funki_items');
    }
};
