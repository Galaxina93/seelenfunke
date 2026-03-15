<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            // Statt user_id nutzen wir userable (polymorph)
            // Erstellt userable_id (UUID string) und userable_type (string)
            $table->uuid('userable_id');
            $table->string('userable_type');

            $table->string('fcm_token')->unique();
            $table->string('device_name')->nullable();
            $table->timestamps();

            $table->index(['userable_id', 'userable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
