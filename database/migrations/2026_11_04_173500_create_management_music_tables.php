<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('management_playlists')) {
            Schema::create('management_playlists', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('cover_url')->nullable();
                $table->string('source_url')->nullable();
                $table->string('icon')->default('musical-note');
                $table->string('color')->default('#C5A059');
                $table->integer('position')->default(0);
                $table->boolean('is_favorite')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('management_tracks')) {
            Schema::create('management_tracks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('playlist_id')->index();
                $table->string('title');
                $table->string('artist')->nullable();
                $table->integer('duration_seconds')->default(0);
                $table->string('file_path')->nullable();
                $table->string('cover_url')->nullable();
                $table->string('external_id')->nullable()->index();
                $table->string('source_url')->nullable();
                $table->string('status')->default('ready'); // 'ready', 'cached', 'downloading'
                $table->unsignedBigInteger('file_size')->default(0);
                $table->integer('position')->default(0);
                $table->boolean('is_favorite')->default(false);
                $table->unsignedInteger('play_count')->default(0);
                $table->timestamps();

                $table->foreign('playlist_id')
                    ->references('id')
                    ->on('management_playlists')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_tracks');
        Schema::dropIfExists('management_playlists');
    }
};
