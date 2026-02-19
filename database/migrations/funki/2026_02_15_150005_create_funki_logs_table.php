<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wir löschen die alte Tabelle, da wir auf das neue System umsteigen
        Schema::dropIfExists('scheduler_logs');

        Schema::create('funki_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('automation'); // automation, ai, marketing, system
            $table->string('action_id');   // z.B. 'newsletter:send'
            $table->string('title');       // z.B. 'Newsletter-Marketing'
            $table->text('message')->nullable(); // Kurzbeschreibung dessen, was passiert ist

            $table->string('status');      // running, success, error, info
            $table->longText('payload')->nullable(); // JSON für Debug-Daten oder Fehler-Stacks

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funki_logs');
    }
};
