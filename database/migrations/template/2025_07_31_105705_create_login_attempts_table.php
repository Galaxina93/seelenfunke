<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginAttemptsTable extends Migration
{
    public function up()
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();       // Email-Adresse des Versuches
            $table->ipAddress('ip_address')->nullable(); // IP-Adresse des Nutzers
            $table->boolean('success')->default(false); // Erfolg oder Fehler beim Login
            $table->timestamp('attempted_at')->useCurrent(); // Zeitpunkt des Versuches
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('login_attempts');
    }
}
