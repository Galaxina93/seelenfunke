<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_gamifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->boolean('is_active')->default(false);
            $table->integer('funken_balance')->default(0);
            $table->integer('funken_total_earned')->default(0);
            $table->integer('funkenflug_highscore')->default(0);
            $table->integer('level')->default(1);
            $table->integer('sparks_collected_today')->default(0);
            $table->date('last_spark_collection_date')->nullable();

            $table->json('titles_progress')->nullable();
            $table->boolean('show_seelengott_badge')->default(false);

            $table->integer('energy_balance')->default(5);
            $table->timestamp('last_energy_refill_at')->nullable();

            $table->string('active_title')->nullable();
            $table->boolean('ranking_opt_in')->default(false);

            $table->boolean('ticket_emails_enabled')->default(true);

            // NEU: Speichert die generierten Gutscheincodes pro Level
            $table->json('unlocked_coupons')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gamifications');
    }
};
