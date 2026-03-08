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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('admin_id')->nullable();
            $table->string('plaid_item_id');
            $table->string('plaid_access_token');
            $table->string('plaid_account_id')->unique();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('iban')->nullable();
            $table->decimal('balance', 12, 2)->default(0);
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
        Schema::dropIfExists('bank_accounts');
    }
};
