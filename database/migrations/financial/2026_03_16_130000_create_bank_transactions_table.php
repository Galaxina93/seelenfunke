<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignUuid('finance_category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignUuid('finance_cost_item_id')->nullable()->constrained('finance_cost_items')->nullOnDelete();
            // Die ID von finAPI für diese Transaktion
            $table->string('finapi_transaction_id')->unique();

            // Betrag (Positiv oder Negativ)
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('EUR');

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
        Schema::dropIfExists('bank_transactions');
    }
};
