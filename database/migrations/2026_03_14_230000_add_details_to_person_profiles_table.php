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
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('relation_type');
            $table->json('links')->nullable()->after('avatar_path');
            $table->string('street')->nullable()->after('ai_learned_facts');
            $table->string('postal_code')->nullable()->after('street');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'links',
                'street',
                'postal_code',
                'city',
                'country'
            ]);
        });
    }
};
