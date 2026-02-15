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
        Schema::create('newsletter_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('title'); // Interner Name (z.B. "Muttertag Template")
            $table->string('subject'); // Betreff der Mail an den Kunden
            $table->longText('content')->nullable(); // Der HTML Inhalt

            // Verknüpfung zum Feiertag (Key-Based, z.B. 'mothers_day', 'christmas')
            $table->string('target_event_key')->index();

            // Wann soll gesendet werden? (z.B. 14 Tage vorher)
            $table->integer('days_offset')->default(14);

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_templates');
    }
};
