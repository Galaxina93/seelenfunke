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

        // Create Admin Tables
        Schema::create('admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');

            $table->string('google_id')->nullable();

            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_id');

            $table->string('photo_path')->nullable();
            $table->longText('about')->nullable();
            $table->string('url')->nullable();

            $table->string('phone_number')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            $table->boolean('two_factor_is_active')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });

        // Create Customer Tables
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');

            $table->string('google_id')->nullable();

            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');

            $table->string('photo_path')->nullable();
            $table->longText('about')->nullable();
            $table->string('url')->nullable();

            $table->string('phone_number')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            $table->boolean('two_factor_is_active')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });

        // Create Employee Tables
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');

            $table->string('google_id')->nullable();

            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');

            $table->string('photo_path')->nullable();
            $table->longText('about')->nullable();
            $table->string('url')->nullable();

            $table->string('phone_number')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            $table->boolean('two_factor_is_active')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // WICHTIG: Erst die Kinder (Profile) löschen
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('employee_profiles');

        // DANN die Eltern (Haupt-Tabellen) löschen
        Schema::dropIfExists('admins');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('employees');
    }
};
