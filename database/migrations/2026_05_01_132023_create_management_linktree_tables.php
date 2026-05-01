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
        Schema::create('management_linktrees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('url');
            $table->string('icon')->nullable();
            $table->enum('type', ['standard', 'secure', 'highlight'])->default('standard');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_linktree_visits', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash');
            $table->text('referrer')->nullable();
            $table->string('device_type')->nullable(); // desktop, mobile, tablet
            $table->timestamps();
        });

        Schema::create('management_linktree_clicks', function (Blueprint $table) {
            $table->id();
            $table->uuid('link_id');
            $table->string('ip_hash');
            $table->string('device_type')->nullable();
            $table->timestamps();

            $table->foreign('link_id')->references('id')->on('management_linktrees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('management_linktree_clicks');
        Schema::dropIfExists('management_linktree_visits');
        Schema::dropIfExists('management_linktrees');
    }
};
