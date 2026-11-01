<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_gamifications')) {
            Schema::create('customer_gamifications', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();

                $table->boolean('is_active')->default(false);
                $table->integer('funken_balance')->default(0);
                $table->integer('funken_total_earned')->default(0);
                $table->unsignedInteger('astral_crystals')->default(0);
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

                // Speichert die generierten Gutscheincodes pro Level
                $table->json('unlocked_coupons')->nullable();

                // Hangar, Prestige, Fraktionen & kosmetische Freischaltungen
                $table->json('unlocked_skins')->nullable();
                $table->string('active_ship_skin', 64)->default('default');
                $table->json('unlocked_hall_auras')->nullable();
                $table->string('active_hall_aura', 64)->default('default');
                $table->json('unlocked_backgrounds')->nullable();
                $table->string('active_background', 64)->default('default');
                $table->json('unlocked_champion_skins')->nullable();
                $table->json('active_champion_skins')->nullable();
                $table->string('selected_planet_key', 48)->default('zenith');
                $table->unsignedBigInteger('galactic_conquest_points')->default(0);
                $table->timestamp('seelen_pass_until')->nullable();
                $table->boolean('has_backpack')->default(false);
                $table->json('equipped_items')->nullable();
                $table->json('inventory_items')->nullable();
                $table->json('buyback_items')->nullable();
                $table->string('hero_motto_key', 64)->nullable();
                $table->string('player_name', 40)->nullable();
                $table->boolean('use_real_name')->default(false);
                $table->string('faction', 32)->nullable()->index();
                $table->unsignedInteger('name_change_tokens')->default(0);

                $table->boolean('left_handed_mode')->default(false);

                $table->timestamps();
            });
        }

        if (!Schema::hasTable('gamification_galactic_planets')) {
            Schema::create('gamification_galactic_planets', function (Blueprint $table) {
                $table->id();
                $table->string('planet_key', 48)->unique();
                $table->string('sector_key', 32)->index();
                $table->unsignedBigInteger('sternenwaechter_points')->default(1000);
                $table->unsignedBigInteger('chaosbringer_points')->default(1000);
                $table->string('controlling_faction', 32)->default('contested');
                $table->uuid('top_commander_customer_id')->nullable();
                $table->string('top_commander_name', 64)->nullable();
                $table->unsignedBigInteger('top_commander_points')->default(0);
                $table->unsignedInteger('total_battles')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_gamification_logs')) {
            Schema::create('customer_gamification_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->nullable()->index();
                $table->uuid('admin_id')->nullable()->index();
                $table->string('event_type', 64)->index();
                $table->string('category', 32)->default('progression')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->integer('sparks_delta')->default(0);
                $table->integer('balance_after')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('customer_gamification_bug_reports')) {
            Schema::create('customer_gamification_bug_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->index();
                $table->string('category', 40)->default('funkenflug')->index();
                $table->string('title');
                $table->text('description');
                $table->string('status', 32)->default('open')->index();
                $table->integer('reward_sparks')->default(0);
                $table->text('admin_reply')->nullable();
                $table->uuid('resolved_by_admin_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('technical_snapshot')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gamification_bug_reports');
        Schema::dropIfExists('customer_gamification_logs');
        Schema::dropIfExists('gamification_galactic_planets');
        Schema::dropIfExists('customer_gamifications');
    }
};
