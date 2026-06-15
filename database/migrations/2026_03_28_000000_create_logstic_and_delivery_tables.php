<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shipping Zones
        Schema::create('logistics_shipping_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // 2. Shipping Rates
        Schema::create('logistics_shipping_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('logistics_shipping_zone_id');
            $table->foreign('logistics_shipping_zone_id', 'fk_ship_rate_zone_id')
                ->references('id')
                ->on('logistics_shipping_zones')
                ->onDelete('cascade');
            $table->string('name');
            $table->integer('price');
            $table->integer('min_price')->default(0);
            $table->integer('min_weight')->default(0);
            $table->integer('max_weight')->default(31500);
            $table->timestamps();
        });

        // 3. Shipping Zone Countries
        Schema::create('logistics_shipping_zone_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('logistics_shipping_zone_id');
            $table->foreign('logistics_shipping_zone_id', 'fk_ship_zone_country_zone_id')
                ->references('id')
                ->on('logistics_shipping_zones')
                ->onDelete('cascade');
            $table->string('country_code', 2);
            $table->unique('country_code');
        });

        // 4. Delivery Times
        Schema::create('delivery_times', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_days')->default(3);
            $table->integer('max_days')->default(5);
            $table->text('description')->nullable();
            $table->string('color')->default('green');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 5. Delivery Settings
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_vacation_mode')->default(false);
            $table->date('vacation_start_date')->nullable();
            $table->date('vacation_end_date')->nullable();
            $table->text('vacation_description')->nullable();
            $table->boolean('is_sick_mode')->default(false);
            $table->text('sick_description')->nullable();
            $table->timestamps();
        });

        // 6. Delivery Feedbacks
        Schema::create('delivery_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_feedbacks');
        Schema::dropIfExists('delivery_settings');
        Schema::dropIfExists('delivery_times');
        Schema::dropIfExists('logistics_shipping_zone_countries');
        Schema::dropIfExists('logistics_shipping_rates');
        Schema::dropIfExists('logistics_shipping_zones');
    }
};
