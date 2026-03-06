<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_times', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_days')->default(3);
            $table->integer('max_days')->default(5);
            $table->text('description')->nullable();
            $table->string('color')->default('green'); // NEU: Ampel-Farbe
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

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

        Schema::create('delivery_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('type'); // 'vacation' oder 'sick'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_feedbacks');
        Schema::dropIfExists('delivery_settings');
        Schema::dropIfExists('delivery_times');
    }
};
