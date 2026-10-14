<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('marketing_herzensprojekt_transactions');
        Schema::dropIfExists('marketing_herzensprojekt_actions');

        Schema::create('marketing_herzensprojekt_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('target_recipient');
            $table->text('description')->nullable();
            $table->integer('allocated_amount_cents')->default(0);
            $table->enum('status', ['planned', 'in_progress', 'completed'])->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->json('photos')->nullable();
            $table->text('public_story')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_herzensprojekt_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')
                ->nullable()
                ->constrained('order_orders', indexName: 'hp_tx_order_id_fk')
                ->nullOnDelete();
            $table->foreignUuid('action_id')
                ->nullable()
                ->constrained('marketing_herzensprojekt_actions', indexName: 'hp_tx_action_id_fk')
                ->nullOnDelete();
            $table->enum('type', ['order_contribution', 'manual_deposit', 'disbursement'])->default('order_contribution');
            $table->integer('amount_cents');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_herzensprojekt_transactions');
        Schema::dropIfExists('marketing_herzensprojekt_actions');
    }
};
