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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_rules');
    }
};
