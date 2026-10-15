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
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->timestamp('cart_reminder_unsubscribed_at')->nullable()->after('last_ip')
                ->comment('Zeitstempel, falls der Kunde dem Erhalt von Warenkorb-Erinnerungen widersprochen hat (Opt-Out nach § 7 UWG / DSGVO)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn('cart_reminder_unsubscribed_at');
        });
    }
};
