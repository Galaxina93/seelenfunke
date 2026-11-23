<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laser_studio_projects')) {
            return;
        }

        Schema::create('laser_studio_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 180);
            $table->string('status', 24)->default('active')->index(); // active | archived
            $table->boolean('is_favorite')->default(false)->index();
            $table->string('mode', 32)->default('custom_card'); // custom_card | free_bed | product_blank
            $table->uuid('product_id')->nullable()->index();
            $table->decimal('width_mm', 8, 2)->default(86.00);
            $table->decimal('height_mm', 8, 2)->default(54.00);
            $table->string('contour_shape', 32)->default('rounded_card'); // rounded_card | rect | circle
            $table->boolean('has_back_side')->default(false);
            $table->string('surface_tone', 24)->default('dark_card'); // dark_card | gold_card | light_surface
            $table->json('configuration')->nullable();
            $table->timestamp('last_lasered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laser_studio_projects');
    }
};
