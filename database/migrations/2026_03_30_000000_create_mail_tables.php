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
                
                // Verhindere gleichnamige Ordner pro Account
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_rules');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_accounts');
    }
};
