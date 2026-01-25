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
        Schema::create('directory_user', function (Blueprint $table) {
            $table->foreignId('directory_id')->constrained()->onDelete('cascade');

            // KORREKTUR: uuidMorphs statt morphs verwenden, um UUIDs zu unterstützen.
            $table->uuidMorphs('user');

            // Der Primärschlüssel muss die Spaltennamen von uuidMorphs widerspiegeln.
            // uuidMorphs erstellt 'user_id' (als UUID) und 'user_type' (als String).
            $table->primary(['directory_id', 'user_id', 'user_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_user');
    }
};
