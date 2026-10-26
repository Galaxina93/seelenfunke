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
            $table->dateTime('relevant_from')->nullable();
            $table->json('file_paths')->nullable();
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
            $table->string('days_of_week')->default('all'); // all, weekdays, weekend
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

            $table->boolean('send_email')->default(false);

            $table->timestamps();
        });


        // Missions
        Schema::create('management_missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_agent_id')->nullable();
            $table->text('mission_text');
            $table->timestamps();
        });

        // Shopping Categories
        Schema::create('management_shopping_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('icon')->nullable()->default('shopping-cart');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        // Shopping Items
        Schema::create('management_shopping_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained('management_shopping_categories')->nullOnDelete();
            $table->string('name');
            $table->string('quantity')->nullable(); // z.B. 5x, 50 Stk, 2 Liter
            $table->string('status')->default('stocked'); // 'stocked', 'needed'
            $table->timestamp('last_purchased_at')->nullable();
            $table->integer('purchase_count')->default(0);
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
                $table->text('cc_email')->nullable();
                $table->text('bcc_email')->nullable();
                $table->longText('body_html')->nullable();
                $table->longText('body_plain')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('has_attachments')->default(false);
                $table->string('priority')->default('normal');
                $table->string('category')->nullable();
                $table->json('tags')->nullable();
                $table->string('ai_status')->default('pending'); // pending, processed, needs_human_review, replied
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

        // Personalakte, Arbeitsvertrag & Onboarding (Leitung -> Mitarbeiter)
        if (!Schema::hasTable('employee_contracts')) {
            Schema::create('employee_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id')->nullable()->index();
                $table->string('invitation_label', 191)->nullable();
                $table->string('job_title', 150)->default('Manufaktur- & Versand-Assistent/in');
                $table->string('department', 100)->default('Produktion & Versand');
                $table->string('employment_type', 40)->default('minijob')->index(); // minijob, part_time, full_time, working_student, short_term
                $table->string('compensation_type', 30)->default('hourly'); // hourly, fixed_salary
                $table->unsignedInteger('hourly_rate_cents')->default(1400);
                $table->unsignedInteger('monthly_gross_cents')->default(54000);
                $table->decimal('weekly_hours', 5, 2)->default(9.00);
                $table->unsignedInteger('vacation_days_per_year')->default(20);
                $table->unsignedInteger('vacation_days_taken')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_fixed_term')->default(false);
                $table->unsignedInteger('probation_months')->default(3);
                $table->string('notice_period_text', 191)->default('4 Wochen zum 15. oder zum Ende eines Kalendermonats (§ 622 BGB)');
                $table->string('work_location', 191)->default('Manufaktur Gifhorn / Mobiles Arbeiten nach Absprache');
                $table->string('working_hours_schedule', 255)->nullable();

                // Personalfragebogen / ELStAM / Sozialversicherung (sensible Felder im Model verschlüsselt)
                $table->date('birth_date')->nullable();
                $table->string('birth_place', 120)->nullable();
                $table->string('nationality', 80)->default('Deutsch');
                $table->text('tax_id')->nullable();
                $table->string('tax_class', 20)->default('I');
                $table->boolean('church_tax')->default(false);
                $table->decimal('children_allowances', 3, 1)->default(0.0);
                $table->text('social_security_number')->nullable();
                $table->string('health_insurance_name', 150)->nullable();
                $table->string('health_insurance_type', 30)->default('statutory'); // statutory, private, family
                $table->boolean('rv_exemption_requested')->default(false);
                $table->boolean('has_other_employment')->default(false);

                // Bankverbindung für Gehaltsauszahlung (IBAN im Model verschlüsselt)
                $table->string('account_holder', 191)->nullable();
                $table->text('iban')->nullable();
                $table->string('bic', 30)->nullable();
                $table->string('bank_name', 150)->nullable();

                // Digitale Unterschrift & Dokumente (§ 2 NachwG / BEG IV)
                $table->string('signature_name', 191)->nullable();
                $table->longText('signature_data')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_ip_hash', 64)->nullable();
                $table->string('contract_number', 60)->nullable()->unique();
                $table->string('contract_pdf_path', 500)->nullable();
                $table->string('questionnaire_pdf_path', 500)->nullable();

                // Sicheres 2-Faktor-Onboarding (Einladungslink + 6-stelliger Verifizierungscode)
                $table->string('invitation_token_hash', 64)->nullable()->unique();
                $table->text('invitation_token_encrypted')->nullable();
                $table->string('invitation_code_hash', 64)->nullable();
                $table->text('invitation_code_encrypted')->nullable();
                $table->timestamp('invitation_expires_at')->nullable();
                $table->timestamp('invitation_accepted_at')->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_archived')->default(false)->index();
                $table->text('internal_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        // Arbeitszeiterfassung (§ 17 MiLoG / § 4 ArbZG)
        if (!Schema::hasTable('employee_time_entries')) {
            Schema::create('employee_time_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('contract_id')->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->date('work_date')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('break_minutes')->default(0);
                $table->unsignedInteger('net_minutes')->default(0);
                $table->string('entry_type', 30)->default('work'); // work, vacation, sick
                $table->string('activity_note', 255)->nullable();
                $table->string('status', 30)->default('recorded')->index(); // running, recorded, approved
                $table->boolean('arbzg_compliant')->default(true);
                $table->timestamps();

                $table->foreign('contract_id')
                    ->references('id')
                    ->on('employee_contracts')
                    ->onDelete('cascade');
            });
        }

        // Monatliche Lohn- & Gehaltsabrechnungen (§ 108 GewO Tresor & Buchhaltungs-Brücke)
        if (!Schema::hasTable('employee_payslips')) {
            Schema::create('employee_payslips', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('contract_id')->index();
                $table->uuid('employee_id')->nullable()->index();
                $table->string('payslip_number', 60)->unique();
                $table->unsignedSmallInteger('period_year')->index();
                $table->unsignedTinyInteger('period_month')->index();
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('hourly_rate_cents_snapshot')->default(0);
                $table->unsignedInteger('gross_amount_cents')->default(0);
                $table->unsignedInteger('net_payout_cents')->default(0);
                $table->unsignedInteger('employer_charges_cents')->default(0);
                $table->unsignedInteger('total_employer_cost_cents')->default(0);
                $table->string('tax_class_snapshot', 20)->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->string('timesheet_pdf_path', 500)->nullable();
                $table->string('uploaded_external_pdf_path', 500)->nullable();
                $table->uuid('accounting_special_issue_id')->nullable()->index();
                $table->string('status', 30)->default('open')->index(); // open, paid, cancelled
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->uuid('paid_by_admin_id')->nullable();
                $table->string('notes', 255)->nullable();
                $table->json('recipient_snapshot')->nullable();
                $table->timestamps();

                $table->foreign('contract_id')
                    ->references('id')
                    ->on('employee_contracts')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('employee_payslips');
        Schema::dropIfExists('employee_time_entries');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_rules');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_accounts');
        Schema::dropIfExists('management_shopping_items');
        Schema::dropIfExists('management_shopping_categories');
        Schema::dropIfExists('management_missions');

        Schema::dropIfExists('management_calendar_events');
        Schema::dropIfExists('management_day_routine_steps');
        Schema::dropIfExists('management_day_routines');
        Schema::dropIfExists('management_tasks');
        Schema::dropIfExists('management_task_lists');
        Schema::dropIfExists('management_contacts');
    }
};
