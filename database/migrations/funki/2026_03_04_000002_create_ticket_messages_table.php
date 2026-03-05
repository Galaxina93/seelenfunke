<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();

            $table->string('sender_type'); // 'customer' oder 'system' / 'admin'
            $table->text('message');
            $table->json('attachments')->nullable(); // Für Drag & Drop Bilder

            $table->boolean('is_read_by_customer')->default(false);
            $table->boolean('is_read_by_admin')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
