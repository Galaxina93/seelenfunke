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
        // 1. 'roles' Tabelle
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // Rollennamen sollten einzigartig sein
            $table->timestamps();
            $table->softDeletes(); // NEU: Notwendig für das SoftDeletes-Trait im Model
        });

        // 2. 'permissions' Tabelle
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // Berechtigungen sollten auch einzigartig sein
            $table->timestamps();
        });

        // 3. Pivot-Tabelle für Berechtigungen und Rollen
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('role_id')->constrained()->onDelete('cascade');

            // NEU: Verhindert doppelte Einträge (gleiche Permission für gleiche Rolle)
            $table->primary(['permission_id', 'role_id']);
        });

        // 4. Polymorphe Pivot-Tabelle, die 'admin_role', 'customer_role' etc. ersetzt
        Schema::create('roleables', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->onDelete('cascade');

            // Wir definieren die Spalten von 'uuidMorphs' manuell
            $table->uuid('roleable_id');
            $table->string('roleable_type', 50); // Explizit eine kürzere Spalte!

            $table->primary(['role_id', 'roleable_id', 'roleable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tabellen in umgekehrter Reihenfolge der Erstellung löschen, um Foreign-Key-Konflikte zu vermeiden
        Schema::dropIfExists('roleables');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
