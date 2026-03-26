<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('accounting_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('admin_id')->nullable();
            $table->string('plaid_item_id');
            $table->string('plaid_access_token');
            $table->string('plaid_account_id')->unique();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('iban')->nullable();
            $table->boolean('is_active_for_analysis')->default(true);
            $table->boolean('is_business')->default(true);
            $table->decimal('balance', 10, 2)->default(0);
            $table->string('currency')->default('EUR');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_accounts');
    }
};
