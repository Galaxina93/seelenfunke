<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->foreignUuid('accounting_category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
            $table->foreignUuid('accounting_cost_item_id')->nullable()->constrained('accounting_cost_items')->nullOnDelete();
            // Die ID von finAPI für diese Transaktion
            $table->string('finapi_transaction_id')->unique();

            // Betrag (Positiv oder Negativ)
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('EUR');

            $table->boolean('is_business')->nullable();
            $table->json('tags')->nullable();

            $table->string('assigned_by_type')->nullable(); // e.g. 'admin' or 'agent'
            $table->string('assigned_by_name')->nullable(); // e.g. 'Alina' or 'Agent: Funkira'

            $table->json('file_paths')->nullable();

            // Zweck / Verwendungszweck (oft lang)
            $table->text('purpose')->nullable();

            // Gegenpartei (Empfänger / Sender)
            $table->string('counterpart_name')->nullable();
            $table->string('counterpart_iban')->nullable();

            // Daten
            $table->dateTime('transaction_date')->nullable();
            $table->dateTime('value_date')->nullable();

            // Typ (z.B. Lastschrift, Überweisung, etc.)
            $table->string('type')->nullable();

            // Status
            $table->boolean('is_pending')->default(false);

            // Unstrukturierte Roh-Daten für evtl. spätere Auswertungen
            $table->json('raw_data')->nullable();

            $table->timestamps();

            $table->index('transaction_date');
            $table->index('amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_transactions');
    }
};
