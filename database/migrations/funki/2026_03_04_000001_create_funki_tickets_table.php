<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funki_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique(); // z.B. MSF-26-A8F9B
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete(); // Optional: Bezug zu einer Bestellung

            $table->string('subject');
            $table->string('category'); // support, return, bug, question
            $table->string('status')->default('open'); // open, answered, closed
            $table->string('priority')->default('normal'); // low, normal, high

            $table->boolean('reward_claimed')->default(false); // Für Gamification (Bug-Bounty)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funki_tickets');
    }
};
