<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_experience_reviews')) {
            return;
        }

        Schema::create('support_experience_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->string('area', 32);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('done')->index();
            $table->string('device_type', 16)->default('desktop');
            $table->uuid('product_id')->nullable();
            $table->string('product_name', 190)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_experience_reviews');
    }
};
