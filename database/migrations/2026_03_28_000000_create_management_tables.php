<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Contact Management (ehemals Person Profiles)
        Schema::create('management_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('is_favorite')->default(false);
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('relation_type')->nullable(); // e.g. "Brother", "Mother", "Colleague"
            $table->date('birthday')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('links')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->text('system_instructions')->nullable();
            $table->text('ai_learned_facts')->nullable();
            $table->timestamps();
        });

        // Task Lists
        Schema::create('management_task_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('icon')->default('list-bullet');
            $table->string('color')->default('#C5A059');
            $table->timestamps();
        });

        // Tasks
        Schema::create('management_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_list_id')->constrained('management_task_lists')->onDelete('cascade');
            $table->foreignUuid('parent_id')->nullable()->constrained('management_tasks')->onDelete('cascade');
            $table->text('title');
            $table->boolean('is_completed')->default(false);
            $table->integer('position')->default(0);
            $table->string('priority')->default('low');
            $table->timestamps();
        });

        // Day Routines
        Schema::create('management_day_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->time('start_time'); // z.B. 13:00:00
            $table->string('title');
            $table->text('message')->nullable(); // Der Spruch dazu
            $table->string('icon')->default('clock');
            $table->string('type')->default('general'); // food, hygiene, sport, work, sleep
            $table->integer('duration_minutes')->default(30); // Wie lange dauert das?
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Day Routine Steps
        Schema::create('management_day_routine_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('day_routine_id')->constrained('management_day_routines')->onDelete('cascade');
            $table->string('title');
            $table->integer('position')->default(0);
            $table->integer('duration_minutes')->default(5); // Wie lange dauert dieser EINZELNE Schritt?
            $table->timestamps();
        });

        // Calendar Events
        Schema::create('management_calendar_events', function (Blueprint $table) {
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

    public function down(): void {
        Schema::dropIfExists('management_calendar_events');
        Schema::dropIfExists('management_day_routine_steps');
        Schema::dropIfExists('management_day_routines');
        Schema::dropIfExists('management_tasks');
        Schema::dropIfExists('management_task_lists');
        Schema::dropIfExists('management_contacts');
    }
};
