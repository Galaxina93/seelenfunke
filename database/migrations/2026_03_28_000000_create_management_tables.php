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
            $table->integer('position')->default(0);
            $table->boolean('is_archived')->default(false);
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
            $table->longText('ai_plan')->nullable();
            $table->boolean('is_archived')->default(false);
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

            // Priority for color coding (low=gray, medium=yellow, high=red)
            $table->string('priority')->default('low');

            $table->timestamps();
        });


        // Missions
        Schema::create('management_missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_agent_id')->nullable();
            $table->text('mission_text');
            $table->timestamps();
        });

        // Mail System
        if (!Schema::hasTable('mail_accounts')) {
            Schema::create('mail_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('imap_username')->nullable();
                $table->string('smtp_username')->nullable();
                $table->text('password');
                $table->string('imap_host');
                $table->integer('imap_port')->default(993);
                $table->string('imap_encryption')->default('ssl');
                $table->text('signature')->nullable();
                $table->string('smtp_host');
                $table->integer('smtp_port')->default(465);
                $table->string('smtp_encryption')->default('ssl');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_commercial')->default(true);
                $table->string('status')->default('connected');
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained()->onDelete('cascade');
                $table->string('message_id')->unique()->nullable();
                $table->string('folder')->default('INBOX');
                $table->boolean('is_archived')->default(false);
                $table->text('subject')->nullable();
                $table->string('from_name')->nullable();
                $table->string('from_email');
                $table->string('to_email');
                $table->longText('body_html')->nullable();
                $table->longText('body_plain')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('has_attachments')->default(false);
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_rules')) {
            Schema::create('mail_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained()->onDelete('cascade');
                $table->string('type')->default('spam'); // spam, blacklist
                $table->string('condition_field'); // from_email, subject
                $table->string('condition_value'); // example@spam.com
                $table->string('action'); // mark_spam, block
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_folders')) {
            Schema::create('mail_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();
                $table->unique(['mail_account_id', 'name']);
            });
        }

        if (!Schema::hasTable('mail_attachments')) {
            Schema::create('mail_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_message_id')->constrained()->cascadeOnDelete();
                $table->string('filename');
                $table->string('content_type')->nullable(); // mime type
                $table->integer('size')->default(0); // in bytes
                $table->string('path'); // actual file path on storage
                $table->string('content_id')->nullable(); // for inline cid attachments
                $table->timestamps();
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_rules');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_accounts');
        Schema::dropIfExists('management_missions');

        Schema::dropIfExists('management_calendar_events');
        Schema::dropIfExists('management_day_routine_steps');
        Schema::dropIfExists('management_day_routines');
        Schema::dropIfExists('management_tasks');
        Schema::dropIfExists('management_task_lists');
        Schema::dropIfExists('management_contacts');
    }
};
