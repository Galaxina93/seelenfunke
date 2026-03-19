<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('mail_attachments');
    }
};
