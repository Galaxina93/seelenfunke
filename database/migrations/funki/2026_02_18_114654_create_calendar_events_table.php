<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');

            // Zeit & Datum
            $table->dateTime('start_date'); // Beginn Datum & Zeit
            $table->dateTime('end_date')->nullable(); // Ende Datum & Zeit
            $table->boolean('is_all_day')->default(true);

            // Wiederholung (RFC 5545 Stil oder simpel)
            // null = keine, 'daily', 'weekly', 'monthly', 'yearly'
            $table->string('recurrence')->nullable();
            // Bis wann wiederholen?
            $table->date('recurrence_end_date')->nullable();

            // Erinnerung (Minuten vor Termin)
            // 0 = zum Zeitpunkt, 60 = 1h vorher, 1440 = 1 Tag vorher
            $table->integer('reminder_minutes')->nullable();

            // Kategorie & Meta
            $table->string('category')->default('general');
            $table->text('description')->nullable();

            // UID aus ICS (für Updates wichtig, um Duplikate zu vermeiden)
            $table->string('ics_uid')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
