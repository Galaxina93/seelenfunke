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
        if (!Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('session_id')->nullable()->index();
                $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('coupon_code')->nullable();
                $table->boolean('is_express')->default(false);
                $table->timestamp('reminder_email_sent_at')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                
                $table->uuid('cart_id');
                $table->uuid('product_id');

                $table->foreign('cart_id')
                    ->references('id')
                    ->on('carts')
                    ->onDelete('cascade');

                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');

                $table->integer('quantity')->default(1);
                $table->integer('unit_price');
                $table->json('configuration')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
