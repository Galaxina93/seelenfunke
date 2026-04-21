<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_customer_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // To link the chat back to a customer session or logged-in user if available.
            $table->string('session_token')->index()->nullable();
            $table->uuid('customer_id')->nullable()->index();

            // Enum-like string for current status
            $table->string('status')->default('open')->comment('open, resolved, needs_employee');

            $table->uuid('support_ticket_id')->nullable()->index()->comment('Verknüpftes Ticket bei Eskalation');
            $table->unsignedTinyInteger('rating')->nullable()->comment('1-5 Sterne Bewertung nach Abschluss');
            $table->text('feedback_text')->nullable()->comment('Kundenfeedback');

            $table->integer('avg_response_time_ms')->nullable()->comment('Average LLM Response Time in MS');
            $table->integer('ai_confidence_score')->nullable()->comment('Calculated Telemetry Confidence %');

            // AI Analysis fields
            $table->string('top_topic')->nullable();
            $table->string('mentioned_product')->nullable();
            $table->text('ai_summary')->nullable();

            $table->timestamps();
        });

        Schema::create('support_customer_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('support_customer_chat_id')->index();

            // Who sent it? customer | ai | employee
            $table->string('sender')->default('customer');

            $table->text('message');
            $table->tinyInteger('severity')->default(0)->comment('0=Normal, 1-10=Penalty severity');
            $table->string('tag')->nullable()->comment('E.g. SMALLTALK, JOKE, INSULT');
            
            $table->timestamps();

            // We use UUID string mapping so we don't strictly bind the foreign key constraint
            // to allow easier wiping of chats without cascading foreign key exceptions on MySQL.
        });

        // Tickets
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ticket_number')->unique();
                $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignUuid('order_id')->nullable();
                $table->string('subject');
                $table->string('category');
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');
                $table->unsignedTinyInteger('rating')->nullable()->comment('1-5 Sterne Bewertung nach Abschluss');
                $table->text('feedback_text')->nullable()->comment('Kundenfeedback');
                $table->text('close_reason')->nullable()->comment('Grund für das Schließen, vom Kunden oder Admin angegeben');
                $table->boolean('reward_claimed')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('sender_type');
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->boolean('is_read_by_customer')->default(false);
                $table->boolean('is_read_by_admin')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_contact_requests')) {
            Schema::create('support_contact_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ticket_number')->unique();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->index();
                $table->string('phone')->nullable();
                
                $table->string('subject');
                $table->string('category')->nullable();
                
                // Status: new, in_progress, waiting_for_customer, resolved
                $table->string('status')->default('new')->index();
                
                $table->text('message');
                $table->text('admin_notes')->nullable();

                // UTM Tracking
                $table->string('utm_source_first')->nullable();
                $table->string('utm_campaign_first')->nullable();
                $table->string('utm_medium_first')->nullable();
                $table->string('utm_source_last')->nullable();
                $table->string('utm_campaign_last')->nullable();
                $table->string('utm_medium_last')->nullable();
                
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_contact_request_messages')) {
            Schema::create('support_contact_request_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('support_contact_request_id')
                      ->constrained('support_contact_requests', 'id', 'scr_id_foreign')
                      ->cascadeOnDelete();
                      
                $table->string('sender_type')->default('customer')->comment('customer or admin');
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->boolean('is_read_by_customer')->default(false);
                $table->boolean('is_read_by_admin')->default(false);
                
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_contact_request_messages');
        Schema::dropIfExists('support_contact_requests');
        Schema::dropIfExists('support_customer_chat_messages');
        Schema::dropIfExists('support_customer_chats');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_ticket_messages');
    }
};
