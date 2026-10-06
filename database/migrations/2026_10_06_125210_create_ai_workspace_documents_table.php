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
        Schema::create('ai_workspace_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('filename', 255)->index();
            $table->string('file_path', 500)->unique();
            $table->string('file_type', 50)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('title', 255)->nullable()->index();
            $table->string('category', 100)->nullable()->index();
            $table->json('tags')->nullable();
            $table->text('purpose')->nullable(); // Wofür ist das Dokument da?
            $table->text('summary')->nullable(); // Zusammenfassung des Inhalts
            $table->json('key_facts')->nullable(); // Wichtige Eckdaten/Fakten
            $table->date('extracted_date')->nullable()->index();
            $table->text('content_preview')->nullable();
            $table->longText('full_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_workspace_documents');
    }
};
