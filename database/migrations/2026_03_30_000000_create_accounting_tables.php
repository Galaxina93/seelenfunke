<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. tax_rates
        if (!Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // z.B. "Standard DE"
                $table->decimal('rate', 5, 2); // z.B. 19.00
                $table->string('country_code', 2)->default('DE'); // ISO Code
                $table->string('tax_class')->default('standard'); // Verknüpfung zum Produkt
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
            DB::table('tax_rates')->insert([
                ['name' => 'Standard DE', 'rate' => 19.00, 'tax_class' => 'standard', 'is_default' => true],
                ['name' => 'Ermäßigt DE', 'rate' => 7.00, 'tax_class' => 'reduced', 'is_default' => false],
            ]);
        }

        // 2. accounting_invoices
        if (!Schema::hasTable('accounting_invoices')) {
            Schema::create('accounting_invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->nullable()->constrained('order_orders')->cascadeOnDelete();
                $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignUuid('parent_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
                $table->string('invoice_number')->unique();
                $table->string('reference_number')->nullable();
                $table->enum('type', ['invoice', 'credit_note', 'cancellation'])->default('invoice');
                $table->string('status')->default('open');
                $table->boolean('is_e_invoice')->default(false);
                $table->date('invoice_date');
                $table->date('delivery_date')->nullable();
                $table->date('due_date')->nullable();
                $table->integer('due_days')->default(14);
                $table->timestamp('paid_at')->nullable();
                $table->string('subject')->nullable();
                $table->text('header_text')->nullable();
                $table->text('footer_text')->nullable();
                $table->json('billing_address');
                $table->json('shipping_address')->nullable();
                $table->integer('subtotal');
                $table->integer('tax_amount');
                $table->integer('shipping_cost')->default(0);
                $table->integer('discount_amount')->default(0);
                $table->integer('volume_discount')->default(0);
                $table->integer('express_price')->default(0); // NEU
                $table->integer('total');
                $table->json('custom_items')->nullable();
                $table->string('stripe_payment_intent_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('pdf_path')->nullable();
                $table->string('xml_path')->nullable();
                $table->string('file_hash')->nullable();
                $table->string('buyer_reference')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('invoice_number');
                $table->index('invoice_date');
                $table->index('status');
            });
        }

        // 3. accounting_groups
        if (!Schema::hasTable('accounting_groups')) {
            Schema::create('accounting_groups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
                $table->string('name');
                $table->enum('type', ['income', 'expense'])->default('expense');
                $table->integer('position')->default(0);
                $table->timestamps();
            });
        }

        // 4. accounting_cost_items
        if (!Schema::hasTable('accounting_cost_items')) {
            Schema::create('accounting_cost_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('accounting_group_id')->constrained('accounting_groups')->onDelete('cascade');
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('amount', 10, 2);
                $table->integer('interval_months')->default(1);
                $table->date('first_payment_date');
                $table->date('last_payment_date')->nullable();
                $table->boolean('is_business')->default(false);
                $table->integer('tax_rate')->nullable();
                $table->string('contract_file_path')->nullable();
                $table->json('tags')->nullable();
                $table->timestamps();
            });
        }

        // 5. accounting_special_issues
        if (!Schema::hasTable('accounting_special_issues')) {
            Schema::create('accounting_special_issues', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
                $table->string('title');
                $table->string('location')->nullable();
                $table->string('category')->nullable();
                $table->decimal('amount', 10, 2);
                $table->date('execution_date');
                $table->text('note')->nullable();
                $table->boolean('is_business')->default(false);
                $table->integer('tax_rate')->nullable();
                $table->string('invoice_number')->nullable();
                $table->json('file_paths')->nullable();
                $table->timestamps();
            });
        }

        // 6. accounting_categories
        if (!Schema::hasTable('accounting_categories')) {
            Schema::create('accounting_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('admin_id')->constrained('admins')->onDelete('cascade');
                $table->boolean('is_business')->default(false);
                $table->string('name');
                $table->integer('usage_count')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // 7. accounting_bank_accounts
        if (!Schema::hasTable('accounting_bank_accounts')) {
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

        // 8. accounting_bank_transactions
        if (!Schema::hasTable('accounting_bank_transactions')) {
            Schema::create('accounting_bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
                $table->foreignUuid('accounting_category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
                $table->foreignUuid('accounting_cost_item_id')->nullable()->constrained('accounting_cost_items')->nullOnDelete();
                $table->string('finapi_transaction_id')->unique();
                $table->decimal('amount', 12, 2);
                $table->string('currency')->default('EUR');
                $table->boolean('is_business')->nullable();
                $table->json('tags')->nullable();
                $table->string('assigned_by_type')->nullable();
                $table->string('assigned_by_name')->nullable();
                $table->json('file_paths')->nullable();
                $table->text('purpose')->nullable();
                $table->string('counterpart_name')->nullable();
                $table->string('counterpart_iban')->nullable();
                $table->dateTime('transaction_date')->nullable();
                $table->dateTime('value_date')->nullable();
                $table->string('type')->nullable();
                $table->boolean('is_pending')->default(false);
                $table->json('raw_data')->nullable();
                $table->timestamps();
                $table->index('transaction_date');
                $table->index('amount');
            });
        }

        // 9. accounting_categorization_rules
        if (!Schema::hasTable('accounting_categorization_rules')) {
            Schema::create('accounting_categorization_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->string('search_term')->index();
                $table->foreignUuid('accounting_category_id')->nullable()->constrained('accounting_categories')->nullOnDelete();
                $table->foreignUuid('accounting_cost_item_id')->nullable()->constrained('accounting_cost_items')->nullOnDelete();
                $table->string('amount_type')->default('variable');
                $table->decimal('amount_value', 10, 2)->nullable();
                $table->integer('priority')->default(0);
                $table->timestamps();
            });
        }

        // 10. accounting_cost_item_histories
        if (!Schema::hasTable('accounting_cost_item_histories')) {
            Schema::create('accounting_cost_item_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('accounting_cost_item_id')->constrained('accounting_cost_items')->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->integer('interval_months')->nullable();
                $table->text('description')->nullable();
                $table->string('name')->nullable();
                $table->boolean('is_business')->default(false);
                $table->integer('tax_rate')->nullable();
                $table->date('first_payment_date')->nullable();
                $table->date('last_payment_date')->nullable();
                $table->string('contract_file_path')->nullable();
                $table->json('tags')->nullable();
                $table->foreignUuid('accounting_group_id')->nullable()->constrained('accounting_groups')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_cost_item_histories');
        Schema::dropIfExists('accounting_categorization_rules');
        Schema::dropIfExists('accounting_bank_transactions');
        Schema::dropIfExists('accounting_bank_accounts');
        Schema::dropIfExists('accounting_categories');
        Schema::dropIfExists('accounting_special_issues');
        Schema::dropIfExists('accounting_cost_items');
        Schema::dropIfExists('accounting_groups');
        Schema::dropIfExists('accounting_invoices');
        Schema::dropIfExists('tax_rates');
    }
};
