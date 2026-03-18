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
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained()->onDelete('cascade');
            $table->string('message_id')->unique()->nullable();
            $table->string('folder')->default('INBOX');

            $table->boolean('is_archived')->default(false);

            $table->string('subject')->nullable();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
