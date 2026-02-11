<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Gruppen (z.B. "Bürokosten", "Versicherung", "Einnahmen")
        Schema::create('finance_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // WICHTIG: Verknüpfung zur 'admins' Tabelle!
            $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');

            $table->string('name');
            $table->enum('type', ['income', 'expense'])->default('expense');
            $table->integer('position')->default(0);
            $table->timestamps();
        });

        // 2. Kostenstellen (z.B. "Miete", "Haftpflicht")
        Schema::create('finance_cost_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Verknüpfung zur Gruppe
            $table->foreignUuid('finance_group_id')->constrained('finance_groups')->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2);

            // Intervall: 1=monatlich, 3=quartalsweise, 6=halbjährlich, 12=jährlich, 24=alle 2 jahre
            $table->integer('interval_months')->default(1);
            $table->date('first_payment_date');

            $table->boolean('is_business')->default(false); // Neu: Gewerblich/Privat Trennung

            $table->string('contract_file_path')->nullable();
            $table->timestamps();
        });

        // 3. Sonderausgaben (Erweitert)
        Schema::create('finance_special_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('execution_date');
            $table->text('note')->nullable();

            // Business Felder
            $table->boolean('is_business')->default(false);
            $table->integer('tax_rate')->nullable(); // 0, 7, 19
            $table->string('invoice_number')->nullable();

            // Datei-Uploads (Mehrere Dateien möglich)
            $table->json('file_paths')->nullable();

            $table->timestamps();
        });

        // 4. Kategorien Tracking (für Sortierung nach Häufigkeit)
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('name');
            $table->integer('usage_count')->default(0);
            $table->softDeletes(); // Fix für: Call to undefined method withTrashed()
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('finance_special_issues');
        Schema::dropIfExists('finance_cost_items');
        Schema::dropIfExists('finance_groups');
    }
};
