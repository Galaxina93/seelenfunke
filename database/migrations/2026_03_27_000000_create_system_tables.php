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
        // Admins
        if (!Schema::hasTable('admins')) {
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
        }

        if (!Schema::hasTable('admin_profiles')) {
            Schema::create('admin_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('admin_id');
                $table->boolean('is_business')->nullable()->default(0);
                $table->string('company_name')->nullable();
                $table->string('vat_id')->nullable();
                $table->text('internal_note')->nullable();
                $table->date('birthday')->nullable();
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

                $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
            });
        }

        // Customers
        if (!Schema::hasTable('customers')) {
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
        }

        if (!Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id');
                $table->boolean('is_business')->nullable()->default(0);
                $table->string('company_name')->nullable();
                $table->string('vat_id')->nullable();
                $table->text('internal_note')->nullable();
                $table->date('birthday')->nullable();
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

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }

        // Employees
        if (!Schema::hasTable('employees')) {
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
        }

        if (!Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->boolean('is_business')->nullable()->default(0);
                $table->string('company_name')->nullable();
                $table->string('vat_id')->nullable();
                $table->text('internal_note')->nullable();
                $table->date('birthday')->nullable();
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

                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }

        // Permissions & Roles
        if (!Schema::hasTable('system_roles')) {
            Schema::create('system_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('system_permissions')) {
            Schema::create('system_permissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->foreignUuid('permission_id')->constrained('system_permissions')->onDelete('cascade');
                $table->foreignUuid('role_id')->constrained('system_roles')->onDelete('cascade');
                $table->primary(['permission_id', 'role_id']);
            });
        }

        if (!Schema::hasTable('roleables')) {
            Schema::create('roleables', function (Blueprint $table) {
                $table->foreignUuid('role_id')->constrained('system_roles')->onDelete('cascade');
                $table->uuid('roleable_id');
                $table->string('roleable_type', 50);
                $table->primary(['role_id', 'roleable_id', 'roleable_type']);
            });
        }

        // Auth & System core
        if (!Schema::hasTable('system_password_reset_tokens')) {
            Schema::create('system_password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('guard');
                $table->string('token');
                $table->timestamp('created_at');
            });
        }

        if (!Schema::hasTable('system_sessions')) {
            Schema::create('system_sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignUuid('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device_type')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_login_attempts')) {
            Schema::create('system_login_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->boolean('success')->default(false);
                $table->timestamp('attempted_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_directories')) {
            Schema::create('system_directories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('path')->unique();
                $table->timestamps();
            });
        }

        // Shop Settings (fixed table name consistency)
        if (!Schema::hasTable('shop_settings') && !Schema::hasTable('shop-settings')) {
            Schema::create('shop_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        } elseif (Schema::hasTable('shop-settings') && !Schema::hasTable('shop_settings')) {
            Schema::rename('shop-settings', 'shop_settings');
        }

        // Logs
        if (!Schema::hasTable('system_logs')) {
            Schema::create('system_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('ai_agent_id')->nullable();
                $table->string('type')->default('automation');
                $table->string('action_id');
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('status');
                $table->longText('payload')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        // Check Configs
        if (!Schema::hasTable('system_check_configs')) {
            Schema::create('system_check_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id')->index()->onDelete('cascade');
                $table->string('filter_type')->default('all');
                $table->date('date_start');
                $table->date('date_end');
                $table->string('range_mode')->default('year');
                $table->timestamps();
            });
        }

        // User Devices
        if (!Schema::hasTable('system_user_devices')) {
            Schema::create('system_user_devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('userable_id');
                $table->string('userable_type');
                $table->string('fcm_token')->unique();
                $table->string('device_name')->nullable();
                $table->timestamps();
                $table->index(['userable_id', 'userable_type']);
            });
        }

        // Tickets
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('ticket_number')->unique();
                $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignUuid('order_id')->nullable();
                $table->string('subject');
                $table->string('category');
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');
                $table->boolean('reward_claimed')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('sender_type');
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->boolean('is_read_by_customer')->default(false);
                $table->boolean('is_read_by_admin')->default(false);
                $table->timestamps();
            });
        }

        // Templates & Jobs
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('page_visits')) {
            Schema::create('page_visits', function (Blueprint $table) {
                $table->id();
                $table->string('session_id')->index();
                $table->string('ip_hash')->index();
                $table->text('url');
                $table->string('path')->index();
                $table->string('method', 10);
                $table->text('user_agent')->nullable();
                $table->text('referer')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('directory_user')) {
            Schema::create('directory_user', function (Blueprint $table) {
                $table->foreignId('directory_id')->constrained('system_directories')->onDelete('cascade');
                $table->uuidMorphs('user');
                $table->primary(['directory_id', 'user_id', 'user_type']);
            });
        }

        // System Maps
        if (!Schema::hasTable('system_map_nodes')) {
            Schema::create('system_map_nodes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('map_id')->default('erp');
                $table->string('label');
                $table->text('description')->nullable();
                $table->string('icon')->nullable();
                $table->string('type')->default('default'); // core, sales, finance, api
                $table->string('status')->default('active'); // active, inactive, planned
                $table->string('link')->nullable(); // NEU: Verlinkung
                $table->string('component_key')->nullable();
                $table->float('pos_x')->default(50);
                $table->float('pos_y')->default(50);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_map_edges')) {
            Schema::create('system_map_edges', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('map_id')->default('erp');
                $table->uuid('source_id');
                $table->uuid('target_id');
                $table->string('label')->nullable();
                $table->string('description')->nullable(); // NEU: Beschreibung für Tooltip
                $table->string('status')->default('active'); // active, inactive (rot)
                $table->timestamps();
    
                $table->foreign('source_id')->references('id')->on('system_map_nodes')->onDelete('cascade');
                $table->foreign('target_id')->references('id')->on('system_map_nodes')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_map_edges');
        Schema::dropIfExists('system_map_nodes');
        Schema::dropIfExists('directory_user');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('page_visits');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('system_user_devices');
        Schema::dropIfExists('system_check_configs');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('shop_settings');
        Schema::dropIfExists('system_directories');
        Schema::dropIfExists('system_login_attempts');
        Schema::dropIfExists('system_sessions');
        Schema::dropIfExists('system_password_reset_tokens');
        Schema::dropIfExists('roleables');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('system_permissions');
        Schema::dropIfExists('system_roles');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('admins');
    }
};
