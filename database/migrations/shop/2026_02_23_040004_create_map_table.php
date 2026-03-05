<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');

            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('type')->default('default'); // core, sales, finance, api
            $table->string('status')->default('active'); // active, inactive, planned
            $table->string('link')->nullable(); // NEU: Verlinkung
            $table->string('component_key')->nullable();
            $table->float('pos_x')->default(50);
            $table->float('pos_y')->default(50);
            $table->timestamps();
        });

        Schema::create('map_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_id');
            $table->uuid('target_id');
            $table->string('label')->nullable();
            $table->string('description')->nullable(); // NEU: Beschreibung für Tooltip
            $table->string('status')->default('active'); // active, inactive (rot)
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('map_nodes')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('map_nodes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_edges');
        Schema::dropIfExists('map_nodes');
    }
};
