<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('management_tracking_links')) {
            Schema::create('management_tracking_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug', 100)->unique();
                $table->string('target_url', 2048)->default('/shop');
                $table->string('channel', 40)->default('social'); // social, campaign, referral, search, direct
                $table->string('utm_source', 100);
                $table->string('utm_medium', 100)->nullable();
                $table->string('utm_campaign', 150)->nullable();
                $table->unsignedBigInteger('clicks_count')->default(0);
                $table->unsignedBigInteger('unique_clicks_count')->default(0);
                $table->timestamp('last_clicked_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('management_tracking_link_hits')) {
            Schema::create('management_tracking_link_hits', function (Blueprint $table) {
                $table->id();
                $table->uuid('tracking_link_id')->index();
                $table->string('ip_hash', 64)->index();
                $table->string('device_type', 20)->default('desktop');
                $table->string('referrer', 255)->nullable();
                $table->timestamps();

                $table->foreign('tracking_link_id')
                    ->references('id')
                    ->on('management_tracking_links')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_tracking_link_hits');
        Schema::dropIfExists('management_tracking_links');
    }
};
