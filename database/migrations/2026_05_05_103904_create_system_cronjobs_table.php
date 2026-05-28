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
        Schema::create('system_cronjobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('command');
            $table->string('parameters')->nullable();
            $table->string('schedule');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('status')->default('pending'); // success, error, pending
            $table->timestamps();
        });

        // Seed default cronjobs
        \Illuminate\Support\Facades\DB::table('system_cronjobs')->insertOrIgnore([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Unverifizierte Benutzer löschen',
            'description' => 'Löscht alle unverifizierten Benutzer (Kunden, Mitarbeiter, Admins) nach 24 Stunden.',
            'command' => 'system:delete-unverified-users',
            'parameters' => null,
            'schedule' => 'daily',
            'is_active' => true,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_cronjobs');
    }
};
