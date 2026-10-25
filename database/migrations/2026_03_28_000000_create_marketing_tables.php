<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Newsletter Subscribers
        Schema::create('marketing_newsletter_subscribers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('ip_address')->nullable(); // Wichtig für Nachweisbarkeit
            $table->boolean('privacy_accepted')->default(false); // Checkbox Status
            $table->timestamp('subscribed_at')->useCurrent();

            // Für Double-Opt-In (DOI) Prozess
            $table->boolean('is_verified')->default(false);
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });

        // Blog Categories
        Schema::create('marketing_blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Blog Posts
        Schema::create('marketing_blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('admins')->onDelete('cascade');// Author
            $table->string('author_name')->nullable();
            $table->foreignUuid('blog_category_id')->nullable()->constrained('marketing_blog_categories')->nullOnDelete();

            // Inhalt
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable(); // Für Meta Desc & Vorschau
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->string('header_image')->nullable();

            // Status & Zeitsteuerung
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->dateTime('published_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            // Rechtliches / Compliance
            $table->boolean('is_advertisement')->default(false)->comment('Kennzeichnung als Werbung/Anzeige');
            $table->boolean('contains_affiliate_links')->default(false);

            $table->softDeletes();
            $table->timestamps();

            // Index für Performance
            $table->index(['status', 'published_at']);
        });

        // Newsletters
        Schema::create('marketing_newsletters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title'); // Interner Name (z.B. "Muttertag Template")
            $table->string('subject'); // Betreff der Mail an den Kunden
            $table->longText('content')->nullable(); // Der HTML Inhalt

            // Typ: 'automated' (feiertagsbasiert) oder 'manual' (einmalig)
            $table->string('type')->default('automated');

            // Verknüpfung zum Feiertag (Key-Based, z.B. 'mothers_day', 'christmas') - Nur für automated
            $table->string('target_event_key')->index()->nullable();

            // Wann soll gesendet werden? (z.B. 14 Tage vorher) - Nur für automated
            $table->integer('days_offset')->default(14);

            // Exaktes Sendedatum - Nur für manual
            $table->timestamp('send_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();

            $table->timestamps();
        });

        // Vouchers
        Schema::create('marketing_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('influencer_id')->nullable()->index();
            $table->string('title'); // Z.B. "Februar Aktion"
            $table->string('code')->nullable();  // Pattern für Auto
            $table->string('recipient_email', 191)->nullable()->index();
            $table->enum('type', ['fixed', 'percent']);
            $table->integer('value');
            $table->integer('used_count')->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('min_order_value')->nullable();

            // Für Automatisierung
            $table->string('trigger_event')->nullable();
            $table->integer('days_offset')->default(0);
            $table->integer('validity_days')->nullable(); // Wie lange gültig nach Erstellung

            // Unterscheidung: Auto (Funki) vs Manual (Admin)
            $table->enum('mode', ['auto', 'manual'])->default('auto');

            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // Gift Vouchers
        Schema::create('marketing_gift_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->uuid('order_item_id')->nullable(); // Ohne DB-Constraint wegen Migrations-Reihenfolge
            $table->integer('initial_value'); // in Cent
            $table->integer('current_balance'); // in Cent
            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->text('personal_message')->nullable();
            $table->string('delivery_method')->default('email'); // 'email' or 'post'
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_until')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Gift Voucher Logs
        Schema::create('marketing_gift_voucher_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gift_voucher_id')->constrained('marketing_gift_vouchers')->onDelete('cascade');
            $table->uuid('order_id')->nullable(); // Ohne DB-Constraint wegen Migrations-Reihenfolge
            $table->integer('amount'); // in Cent
            $table->integer('remaining_balance'); // in Cent
            $table->timestamps();
        });

        // Instagram Posts
        Schema::create('marketing_instagram_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ai_agent_id')->nullable();
            
            $table->string('image_url')->nullable();
            $table->text('caption');
            $table->json('hashtags')->nullable();
            
            $table->string('status')->default('draft'); // draft, published, rejected
            
            $table->timestamps();
            
            $table->foreign('ai_agent_id')
                ->references('id')
                ->on('ai_agents')
                ->onDelete('set null');
        });

        // Landing Pages
        Schema::create('marketing_landing_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->string('headline')->nullable();
            $table->text('sales_copy')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('status')->default('active'); // active, draft
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });

        // Google Ads Campaigns
        Schema::create('marketing_google_ads_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            
            $table->string('campaign_name');
            $table->string('ad_group_name');
            $table->json('keywords');
            $table->json('negative_keywords');
            
            $table->string('headline_1', 30);
            $table->string('headline_2', 30);
            $table->string('headline_3', 30);
            $table->string('description_1', 90);
            $table->string('description_2', 90);
            
            $table->string('status')->default('draft'); // draft, active, exported
            
            $table->timestamps();
            
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });

        // Influencers & Affiliates
        Schema::create('marketing_influencers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id')->nullable()->unique();
            $table->string('invitation_label', 120)->nullable();
            $table->string('handle', 100)->nullable();
            $table->string('slug', 100)->nullable()->unique();
            $table->string('primary_channel', 40)->default('instagram');
            $table->string('profile_url', 500)->nullable();

            // Konditionen
            $table->string('commission_type', 20)->default('percent'); // percent, fixed_cents
            $table->unsignedInteger('commission_value')->default(15);
            $table->unsignedInteger('follower_discount_percent')->default(10);
            $table->unsignedInteger('cookie_days')->default(30);

            // Verknüpfte Standard-Werbemittel
            $table->uuid('default_voucher_id')->nullable()->index();
            $table->uuid('default_tracking_link_id')->nullable()->index();

            // Steuerliche & rechtliche Pflichtangaben (§ 14 Abs. 2 S. 2 UStG / § 19 UStG)
            $table->string('tax_status', 30)->default('small_business'); // small_business, standard_tax
            $table->string('tax_number', 100)->nullable();
            $table->boolean('self_employed_confirmed')->default(false);
            $table->timestamp('self_employed_confirmed_at')->nullable();

            // Auszahlungsdaten (IBAN wird im Model verschlüsselt gespeichert)
            $table->string('payout_method', 30)->default('bank_transfer'); // bank_transfer, paypal, shop_voucher
            $table->string('account_holder', 191)->nullable();
            $table->text('iban')->nullable();
            $table->string('bic', 30)->nullable();
            $table->string('paypal_email', 191)->nullable();

            // Sicheres 2-Faktor-Onboarding (Einladungslink + Verifizierungscode)
            $table->string('invitation_token_hash', 64)->nullable()->unique();
            $table->text('invitation_token_encrypted')->nullable();
            $table->string('invitation_code_hash', 64)->nullable();
            $table->text('invitation_code_encrypted')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });

        // Influencer Payouts & Provisions-Gutschriften (§ 14 Abs. 2 S. 2 UStG)
        Schema::create('marketing_influencer_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('influencer_id')->index();
            $table->string('credit_note_number', 60)->unique();
            $table->string('payout_method', 30)->default('bank_transfer');
            $table->unsignedInteger('net_amount_cents')->default(0);
            $table->unsignedInteger('tax_rate')->default(0);
            $table->unsignedInteger('tax_amount_cents')->default(0);
            $table->unsignedInteger('bonus_amount_cents')->default(0);
            $table->unsignedInteger('gross_amount_cents')->default(0);
            $table->uuid('gift_voucher_id')->nullable()->index();
            $table->uuid('accounting_special_issue_id')->nullable()->index();
            $table->string('pdf_path', 500)->nullable();
            $table->string('status', 30)->default('requested')->index(); // requested, paid, cancelled
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('paid_by_admin_id')->nullable();
            $table->json('recipient_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('influencer_id')
                ->references('id')
                ->on('marketing_influencers')
                ->onDelete('cascade');
        });

        // Influencer Commissions (Einzelne vermittelte Bestellungen)
        Schema::create('marketing_influencer_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('influencer_id')->index();
            $table->uuid('order_id')->unique();
            $table->uuid('tracking_link_id')->nullable()->index();
            $table->string('voucher_code', 100)->nullable();
            $table->string('attribution_source', 30)->default('link'); // link, coupon, both
            $table->string('order_number', 60);
            $table->unsignedInteger('order_net_amount_cents')->default(0);
            $table->unsignedInteger('commission_rate_snapshot')->default(0);
            $table->unsignedInteger('commission_amount_cents')->default(0);
            $table->string('status', 30)->default('pending')->index(); // pending, approved, paid, cancelled
            $table->uuid('payout_id')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('influencer_id')
                ->references('id')
                ->on('marketing_influencers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_influencer_commissions');
        Schema::dropIfExists('marketing_influencer_payouts');
        Schema::dropIfExists('marketing_influencers');
        Schema::dropIfExists('marketing_google_ads_campaigns');
        Schema::dropIfExists('marketing_landing_pages');
        Schema::dropIfExists('marketing_instagram_posts');
        Schema::dropIfExists('marketing_gift_voucher_logs');
        Schema::dropIfExists('marketing_gift_vouchers');
        Schema::dropIfExists('marketing_vouchers');
        Schema::dropIfExists('marketing_newsletters');
        Schema::dropIfExists('marketing_blog_posts');
        Schema::dropIfExists('marketing_blog_categories');
        Schema::dropIfExists('marketing_newsletter_subscribers');
    }
};
