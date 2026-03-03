<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index(); // Um User-Pfade zu verfolgen (Sitzung)
            $table->string('ip_hash')->index(); // Gehashte IP für Anonymität!
            $table->text('url');
            $table->string('path')->index();
            $table->string('method', 10);
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->index(); // Falls eingeloggt
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('page_visits');
    }
};
