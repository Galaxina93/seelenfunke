<?php

namespace Tests\Feature\Shop;

use App\Livewire\Employee\Influencer\InfluencerDashboard;
use App\Livewire\Frontend\Marketing\InfluencerInvitationAccept;
use App\Livewire\Shop\Marketing\MarketingInfluencer as MarketingInfluencerComponent;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingInfluencerCommission;
use App\Models\Marketing\MarketingInfluencerPayout;
use App\Models\Order\OrderOrder;
use App\Services\Marketing\InfluencerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingInfluencerTest extends TestCase
{
    use DatabaseTransactions;

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Master',
            'last_name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@mein-seelenfunke.de',
            'password' => Hash::make('SecretAdmin123!'),
        ]);
    }

    public function test_admin_can_generate_one_click_invitation_and_manage_influencer(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.marketing-influencer'));
        $response->assertStatus(200);
        $response->assertSee('Influencer &amp; Affiliate Hub', false);
        $response->assertSee('Influencer einladen', false);

        // 1. Admin klickt auf "+ Influencer einladen" -> Link + 6-stelliger Code werden generiert
        $component = Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('createInvitation')
            ->assertSet('showInviteModal', true)
            ->assertHasNoErrors();

        $inviteId = $component->get('activeInviteId');
        $this->assertNotNull($inviteId);

        $slot = MarketingInfluencer::find($inviteId);
        $this->assertNotNull($slot);
        $this->assertNull($slot->employee_id);
        $this->assertNotNull($slot->invitation_url);
        $this->assertNotNull($slot->verification_code);
        $this->assertEquals(6, strlen($slot->verification_code));
    }

    public function test_influencer_step_by_step_self_registration_with_verification_code_and_role_isolation(): void
    {
        $admin = $this->createAdmin();
        $service = app(InfluencerService::class);

        // 1. Admin erzeugt Einladungs-Slot (15% Provision, 10% Follower-Rabatt)
        $slot = $service->createInvitationSlot([
            'invitation_label' => 'Für Mia (@mia.stern)',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
        ]);

        $rawToken = $slot->invitation_token_encrypted;
        $rawCode = $slot->verification_code;
        $this->assertNotEmpty($rawToken);
        $this->assertNotEmpty($rawCode);

        // 2. Öffentliche Einladungsseite aufrufen -> Zeigt Schritt 1 (Verifizierungscode)
        $this->get(route('influencer.invitation', ['token' => $rawToken]))
            ->assertStatus(200)
            ->assertSee('Einladung verifizieren');

        // 3. Falscher Verifizierungscode wird abgelehnt
        Livewire::test(InfluencerInvitationAccept::class, ['token' => $rawToken])
            ->set('verificationCode', '000000')
            ->call('verifyCode')
            ->assertHasErrors(['verificationCode'])
            ->assertSet('step', 1);

        // 4. Korrekter 6-stelliger Code schaltet Schritt 2 & 3 frei -> Vollständige Selbst-Registrierung
        Livewire::test(InfluencerInvitationAccept::class, ['token' => $rawToken])
            ->set('verificationCode', $rawCode)
            ->call('verifyCode')
            ->assertHasNoErrors()
            ->assertSet('isCodeVerified', true)
            ->assertSet('step', 2)
            // Schritt 2: Creator-Profil & Wunsch-Codes
            ->set('firstName', 'Mia')
            ->set('lastName', 'Stern')
            ->set('email', 'mia.stern@creator-test.de')
            ->set('password', 'SuperSafeCreator123!')
            ->set('password_confirmation', 'SuperSafeCreator123!')
            ->set('primaryChannel', 'tiktok')
            ->set('handle', '@mia.stern')
            ->set('slug', 'mia-stern')
            ->set('customVoucherCode', 'MIA10')
            ->call('nextToStepThree')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            // Schritt 3: Anschrift, Steuerstatus & Bankdaten
            ->set('street', 'Sternenweg')
            ->set('houseNumber', '9')
            ->set('postal', '80331')
            ->set('city', 'München')
            ->set('country', 'DE')
            ->set('taxStatus', 'small_business')
            ->set('taxNumber', '143/123/45678')
            ->set('payoutMethod', 'bank_transfer')
            ->set('accountHolder', 'Mia Stern')
            ->set('iban', 'DE89370400440532013000')
            ->set('selfEmployedConfirmed', true)
            ->set('termsAccepted', true)
            ->call('completeRegistration')
            ->assertHasNoErrors()
            ->assertRedirect(route('employee.influencer.dashboard'));

        // Token & Verifizierungscode müssen nach Abschluss sofort entwertet sein (Einmal-Schutz)
        $influencer = $slot->fresh(['employee.profile', 'defaultVoucher', 'defaultTrackingLink']);
        $this->assertNotNull($influencer->invitation_accepted_at);
        $this->assertNull($influencer->invitation_token_hash);
        $this->assertNull($influencer->invitation_token_encrypted);
        $this->assertNull($influencer->invitation_code_hash);
        $this->assertNull($influencer->invitation_code_encrypted);
        $this->assertNotNull($influencer->employee);
        $this->assertEquals('influencer', $influencer->employee->role);
        $this->assertEquals('@mia.stern', $influencer->handle);
        $this->assertEquals('mia-stern', $influencer->defaultTrackingLink?->slug);
        $this->assertEquals('MIA10', $influencer->defaultVoucher?->code);
        $this->assertTrue($influencer->isReadyForPayout());

        // Admin kann den registrierten Influencer jetzt in der Tabelle konfigurieren & archivieren
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('editInfluencer', $influencer->id)
            ->assertSet('showConfigModal', true)
            ->set('commissionValue', 18)
            ->call('saveInfluencer')
            ->assertHasNoErrors()
            ->call('toggleArchive', $influencer->id);

        $this->assertEquals(18, $influencer->fresh()->commission_value);
        $this->assertTrue($influencer->fresh()->is_archived);

        // Wiederherstellen für Dashboard-Tests
        $influencer->update(['is_archived' => false, 'is_active' => true]);

        // 5. Influencer darf eigenes Creator-Dashboard sehen
        $this->actingAs($influencer->employee, 'employee')
            ->get(route('employee.influencer.dashboard'))
            ->assertStatus(200)
            ->assertSee('Seelenfunke Creator Cockpit');

        // 6. SICHERHEITSHÄRTE: Influencer darf KEINE internen Mitarbeiter-Seiten (/employee/payslips, /employee/projects) sehen!
        $this->actingAs($influencer->employee, 'employee')
            ->get('/employee/payslips')
            ->assertRedirect(route('employee.influencer.dashboard'));

        $this->actingAs($influencer->employee, 'employee')
            ->get('/employee/projects')
            ->assertRedirect(route('employee.influencer.dashboard'));

        // 7. Umgekehrt darf ein interner Mitarbeiter NICHT auf das Influencer-Dashboard
        $internalEmployee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Werkstatt',
            'email' => 'max.werkstatt@mein-seelenfunke.de',
            'role' => 'internal',
            'password' => Hash::make('InternalPass123!'),
        ]);

        $this->actingAs($internalEmployee, 'employee')
            ->get(route('employee.influencer.dashboard'))
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_dual_attribution_self_purchase_block_and_cancellation_reversal(): void
    {
        $service = app(InfluencerService::class);
        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Nina',
            'last_name' => 'Glanz',
            'email' => 'nina@creator-test.de',
            'handle' => '@nina.glanz',
            'slug' => 'nina-glanz',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
            'custom_voucher_code' => 'NINA10',
        ]);

        // 1. Klick auf Kurzlink /t/nina-glanz
        $this->get('/t/nina-glanz')->assertRedirect();
        $this->assertEquals(1, $influencer->defaultTrackingLink->fresh()->clicks_count);

        $dummyAddress = [
            'first_name' => 'Max',
            'last_name' => 'Kunde',
            'street' => 'Testweg 1',
            'postal_code' => '38518',
            'city' => 'Gifhorn',
            'country' => 'DE',
        ];

        // 2. Bestellung A: Über Tracking-Link (utm_campaign_last = aff:nina-glanz), Brutto 119,00 € (Netto 100,00 €) -> 15% = 15,00 €
        $orderByLink = OrderOrder::create([
            'order_number' => 'ORD-2026-INFL01',
            'email' => 'kaeufer1@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'subtotal_price' => 11900,
            'discount_amount' => 0,
            'tax_amount' => 1900,
            'shipping_price' => 490,
            'total_price' => 12390,
            'is_small_business' => false,
            'utm_source_last' => 'influencer',
            'utm_campaign_last' => 'aff:nina-glanz',
        ]);

        $commA = $service->processOrderCommission($orderByLink);
        $this->assertNotNull($commA);
        $this->assertEquals('link', $commA->attribution_source);
        $this->assertEquals(10000, $commA->order_net_amount_cents);
        $this->assertEquals(1500, $commA->commission_amount_cents);
        $this->assertEquals('pending', $commA->status);

        // 3. Bestellung B: Nur über Rabattcode NINA10 (ohne vorherige Link-Session!)
        session()->forget(['seelenfunke_influencer_id', 'seelenfunke_tracking_link_id']);

        $orderByCoupon = OrderOrder::create([
            'order_number' => 'ORD-2026-INFL02',
            'email' => 'kaeufer2@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'coupon_code' => 'NINA10',
            'subtotal_price' => 11900,
            'discount_amount' => 1190,
            'tax_amount' => 1710,
            'shipping_price' => 0,
            'total_price' => 10710,
            'is_small_business' => false,
        ]);

        $commB = $service->processOrderCommission($orderByCoupon);
        $this->assertNotNull($commB);
        $this->assertEquals('coupon', $commB->attribution_source);
        $this->assertEquals('approved', $commB->status); // da Order bereits 'completed'
        $this->assertEquals(9000, $commB->order_net_amount_cents); // 107,10 € Brutto / 1,19 = 90,00 € Netto
        $this->assertEquals(1350, $commB->commission_amount_cents); // 15% von 90,00 € = 13,50 €

        // 4. Eigenkauf-Sperre: Nina bestellt selbst mit ihrer E-Mail nina@creator-test.de
        $selfOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-SELF01',
            'email' => 'nina@creator-test.de',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'coupon_code' => 'NINA10',
            'subtotal_price' => 11900,
            'discount_amount' => 1190,
            'tax_amount' => 1710,
            'shipping_price' => 0,
            'total_price' => 10710,
        ]);

        $this->assertNull($service->processOrderCommission($selfOrder));

        // 5. Storno-Schutz: Order A wird storniert -> Provision wird automatisch storniert
        $orderByLink->handleCancellation('Kundenwiderruf');
        $this->assertEquals('cancelled', $commA->fresh()->status);
    }

    public function test_payout_credit_note_pdf_and_automatic_accounting_variable_cost_booking(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $service = app(InfluencerService::class);

        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Elena',
            'last_name' => 'Gold',
            'email' => 'elena@creator-test.de',
            'handle' => '@elena.gold',
            'slug' => 'elena-gold',
            'commission_value' => 20,
            'follower_discount_percent' => 10,
        ]);

        // Elena hinterlegt ihre Steuer- & Bankdaten im Creator-Portal
        Livewire::actingAs($influencer->employee, 'employee')
            ->test(InfluencerDashboard::class)
            ->set('street', 'Musterstraße')
            ->set('houseNumber', '12a')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('taxStatus', 'small_business')
            ->set('taxNumber', '27/123/45678')
            ->set('selfEmployedConfirmed', true)
            ->set('payoutMethod', 'bank_transfer')
            ->set('accountHolder', 'Elena Gold')
            ->set('iban', 'DE89370400440532013000')
            ->call('savePayoutAndTaxSettings')
            ->assertHasNoErrors();

        $influencer->refresh();
        $this->assertTrue($influencer->isReadyForPayout());
        $this->assertEquals('DE89 •••• •••• •••• 3000', $influencer->masked_iban);

        // Freigegebene Provision über 25,00 € anlegen
        $dummyAddress = [
            'first_name' => 'Kunde',
            'last_name' => 'Eins',
            'street' => 'Hauptstr. 5',
            'postal_code' => '38518',
            'city' => 'Gifhorn',
            'country' => 'DE',
        ];
        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-PAY01',
            'email' => 'kunde@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'subtotal_price' => 14875,
            'discount_amount' => 0,
            'tax_amount' => 2375,
            'shipping_price' => 0,
            'total_price' => 14875,
            'utm_campaign_last' => 'aff:elena-gold',
        ]);

        $comm = $service->processOrderCommission($order);
        $this->assertNotNull($comm);
        $this->assertEquals('approved', $comm->status);
        $this->assertEquals(2500, $comm->commission_amount_cents); // 20% von 125,00 € Netto = 25,00 €

        // Elena fordert Auszahlung an -> Gutschrift PROV-YYYY-XXXX + PDF wird generiert
        $payout = $service->requestPayout($influencer, 'bank_transfer');
        $this->assertEquals('requested', $payout->status);
        $this->assertEquals(2500, $payout->gross_amount_cents);
        $this->assertNotNull($payout->pdf_path);
        Storage::disk('local')->assertExists($payout->pdf_path);

        // Elena kann ihr PDF im Portal herunterladen
        $this->actingAs($influencer->employee, 'employee')
            ->get(route('employee.influencer.payout.pdf', $payout->id))
            ->assertStatus(200);

        // Admin markiert Gutschrift als bezahlt -> Automatische Verbuchung in AccountingSpecialIssue (Variable Kosten)
        $paidPayout = $service->markPayoutAsPaid($payout, $admin->id);
        $this->assertEquals('paid', $paidPayout->status);
        $this->assertEquals('paid', $comm->fresh()->status);
        $this->assertNotNull($paidPayout->accounting_special_issue_id);

        $specialIssue = AccountingSpecialIssue::find($paidPayout->accounting_special_issue_id);
        $this->assertNotNull($specialIssue);
        $this->assertEquals('Marketing & Affiliate', $specialIssue->category);
        $this->assertEquals(-25.00, (float) $specialIssue->amount);
        $this->assertTrue($specialIssue->is_business);
        $this->assertEquals($paidPayout->credit_note_number, $specialIssue->invoice_number);

        // Sicherheits-Check: Ein anderer Influencer darf Elenas Gutschrift-PDF NICHT abrufen (403)
        $otherInfluencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Tom',
            'last_name' => 'Fremd',
            'email' => 'tom@creator-test.de',
            'handle' => '@tom.fremd',
            'slug' => 'tom-fremd',
        ]);

        $this->actingAs($otherInfluencer->employee, 'employee')
            ->get(route('employee.influencer.payout.pdf', $payout->id))
            ->assertStatus(403);
    }

    public function test_shop_voucher_payout_boost_and_creator_deep_link_generator(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $service = app(InfluencerService::class);

        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Sophie',
            'last_name' => 'Herz',
            'email' => 'sophie@creator-test.de',
            'handle' => '@sophie.herz',
            'slug' => 'sophie-herz',
            'commission_value' => 20,
        ]);

        // Sophie erstellt im Creator-Portal einen eigenen Deep-Link und speichert Steuerdaten für Gutschein-Prämie
        Livewire::actingAs($influencer->employee, 'employee')
            ->test(InfluencerDashboard::class)
            ->set('newLinkTitle', 'Story Kristall')
            ->call('createProductTrackingLink')
            ->assertHasNoErrors()
            ->set('street', 'Rosenweg')
            ->set('houseNumber', '7')
            ->set('postal', '20095')
            ->set('city', 'Hamburg')
            ->set('country', 'DE')
            ->set('taxStatus', 'small_business')
            ->set('taxNumber', '41/999/12345')
            ->set('selfEmployedConfirmed', true)
            ->set('payoutMethod', 'shop_voucher')
            ->call('savePayoutAndTaxSettings')
            ->assertHasNoErrors();

        $this->assertEquals(2, $influencer->trackingLinks()->count());

        // 50,00 € Provision erzeugen
        $dummyAddress = ['first_name' => 'Lisa', 'last_name' => 'Test', 'country' => 'DE'];
        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-BOOST1',
            'email' => 'lisa@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'subtotal_price' => 29750,
            'discount_amount' => 0,
            'tax_amount' => 4750,
            'shipping_price' => 0,
            'total_price' => 29750,
            'utm_campaign_last' => 'aff:sophie-herz',
        ]);

        $comm = $service->processOrderCommission($order);
        $this->assertEquals(5000, $comm->commission_amount_cents); // 50,00 € Netto-Provision

        // Auszahlung als Shop-Wertgutschein (+20% Bonus -> 60,00 € = 6000 Cent)
        $payout = $service->requestPayout($influencer->fresh(), 'shop_voucher');
        $this->assertEquals(5000, $payout->net_amount_cents);
        $this->assertEquals(1000, $payout->bonus_amount_cents);
        $this->assertEquals(6000, $payout->gross_amount_cents);

        $paid = $service->markPayoutAsPaid($payout, $admin->id);
        $this->assertNotNull($paid->gift_voucher_id);
        $this->assertEquals(6000, $paid->giftVoucher->current_balance);
        $this->assertStringStartsWith('CREATOR-', $paid->giftVoucher->code);
    }

    public function test_cross_guard_email_uniqueness_prevents_reusing_customer_or_admin_email_and_dynamic_tax_rate(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $service = app(InfluencerService::class);

        // 1. Existing Customer email must NOT be reusable for Influencer onboarding
        $existingCustomer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'bereits-kunde@seelenfunke.de',
        ]);

        $slot = $service->createInvitationSlot([
            'invitation_label' => 'Duplikat-Test',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
        ]);

        Livewire::test(InfluencerInvitationAccept::class, ['token' => $slot->invitation_token_encrypted])
            ->set('verificationCode', $slot->invitation_code_encrypted)
            ->call('verifyCode')
            ->assertSet('step', 2)
            ->set('firstName', 'Anna')
            ->set('lastName', 'Test')
            ->set('handle', '@anna.test')
            ->set('slug', 'anna-test')
            ->set('email', 'BEREITS-KUNDE@seelenfunke.de') // auch Groß-/Kleinschreibung wird abgefangen
            ->set('password', 'SafePass2026!')
            ->set('password_confirmation', 'SafePass2026!')
            ->call('nextToStepThree')
            ->assertHasErrors(['email']);

        // Auch die Admin-E-Mail darf nicht wiederverwendet werden
        Livewire::test(InfluencerInvitationAccept::class, ['token' => $slot->invitation_token_encrypted])
            ->set('verificationCode', $slot->invitation_code_encrypted)
            ->call('verifyCode')
            ->set('firstName', 'Anna')
            ->set('lastName', 'Test')
            ->set('handle', '@anna.test')
            ->set('slug', 'anna-test')
            ->set('email', $admin->email)
            ->set('password', 'SafePass2026!')
            ->set('password_confirmation', 'SafePass2026!')
            ->call('nextToStepThree')
            ->assertHasErrors(['email']);

        // 2. Dynamischer MwSt.-Satz aus Shop-Settings (z. B. 7 % statt 19 %) wird im Formular & bei Gutschrift übernommen
        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'default_tax_rate'],
            ['value' => '16']
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Klara',
            'last_name' => 'Steuer',
            'email' => 'klara@creator-test.de',
            'handle' => '@klara.steuer',
            'slug' => 'klara-steuer',
            'commission_value' => 20,
        ]);

        Livewire::actingAs($influencer->employee, 'employee')
            ->test(InfluencerDashboard::class)
            ->assertSee('zzgl. 16 % USt.')
            ->set('street', 'Steuerweg')
            ->set('houseNumber', '1')
            ->set('postal', '10115')
            ->set('city', 'Berlin')
            ->set('country', 'DE')
            ->set('taxStatus', 'standard_tax')
            ->set('taxNumber', 'DE999888777')
            ->set('selfEmployedConfirmed', true)
            ->set('payoutMethod', 'bank_transfer')
            ->set('accountHolder', 'Klara Steuer')
            ->set('iban', 'DE89370400440532013000')
            ->call('savePayoutAndTaxSettings')
            ->assertHasNoErrors();

        $dummyAddress = ['first_name' => 'Tax', 'last_name' => 'Test', 'country' => 'DE'];
        $taxOrder = OrderOrder::create([
            'order_number' => 'ORD-2026-TAX16',
            'email' => 'taxbuyer@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'subtotal_price' => 11600,
            'discount_amount' => 0,
            'tax_amount' => 1600,
            'shipping_price' => 0,
            'total_price' => 11600,
        ]);

        \App\Models\Marketing\MarketingInfluencerCommission::create([
            'influencer_id' => $influencer->id,
            'order_id' => $taxOrder->id,
            'order_number' => 'ORD-2026-TAX16',
            'order_net_amount_cents' => 10000,
            'commission_rate_snapshot' => 20,
            'commission_amount_cents' => 2000, // 20,00 € Netto
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $payout = $service->requestPayout($influencer->fresh(), 'bank_transfer');
        $this->assertEquals(16, (int) $payout->tax_rate);
        $this->assertEquals(320, $payout->tax_amount_cents); // 16 % von 20,00 € = 3,20 €
        $this->assertEquals(2320, $payout->gross_amount_cents);
    }

    public function test_customer_gdpr_ai_data_consent_sync_and_gatekeeper(): void
    {
        $customer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'dsgvo-kunde@seelenfunke.de',
        ]);
        $customer->profile()->update([
            'ai_data_consent' => false,
            'ai_data_consent_at' => null,
        ]);

        $this->actingAs($customer, 'customer');

        // 1. Solange ai_data_consent = false ist, blockiert AiSupportFuncs den Zugriff auf Kundendaten technisch
        $blockedResult = \App\Services\AI\Functions\AiSupportFuncs::executeGetCustomerFullProfile([]);
        $this->assertEquals('error', $blockedResult['status']);
        $this->assertStringContainsString('DSGVO-Freigabe', $blockedResult['message']);

        // 2. Kunde akzeptiert Datenschutz im Frontend-Support-Chat (eingeloggt) -> Profil wird automatisch aktualisiert!
        Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Frontend\Support\AiFrontendSupportChat::class)
            ->assertSet('hasAcceptedPrivacy', false)
            ->set('privacyChecked', true)
            ->call('acceptPrivacy')
            ->assertSet('hasAcceptedPrivacy', true);

        $this->assertTrue($customer->fresh()->hasAiDataConsent());
        $this->assertNotNull($customer->fresh()->profile->ai_data_consent_at);

        // 3. Jetzt darf AiSupportFuncs die Profildaten abrufen
        $allowedResult = \App\Services\AI\Functions\AiSupportFuncs::executeGetCustomerFullProfile([]);
        $this->assertEquals('success', $allowedResult['status']);

        // 4. Kunde widerruft die Einwilligung in den Profileinstellungen -> sofort wieder gesperrt
        $customer->updateAiDataConsent(false, 'profile_settings');
        $this->assertFalse($customer->fresh()->hasAiDataConsent());

        $revokedResult = \App\Services\AI\Functions\AiSupportFuncs::executeGetCustomerFullProfile([]);
        $this->assertEquals('error', $revokedResult['status']);
    }

    public function test_user_seeder_creates_influencer_and_ai_tools_anonymize_customers_without_gdpr_consent(): void
    {
        // 1. UserSeeder legt den Influencer-Testbenutzer (Lara Living) inkl. Tracking-Link & Gutschein LARA10 an
        $this->seed(\Database\Seeders\UserSeeder::class);

        $seededInfluencerEmployee = Employee::where('email', 'influencer@mein-seelenfunke.de')->first();
        $this->assertNotNull($seededInfluencerEmployee);
        $this->assertTrue($seededInfluencerEmployee->isInfluencer());

        $seededInfluencer = MarketingInfluencer::where('employee_id', $seededInfluencerEmployee->id)->first();
        $this->assertNotNull($seededInfluencer);
        $this->assertEquals('@lara.living', $seededInfluencer->handle);
        $this->assertNotNull($seededInfluencer->defaultTrackingLink);
        $this->assertNotNull($seededInfluencer->defaultVoucher);
        $this->assertEquals('LARA10', $seededInfluencer->defaultVoucher->code);

        // 2. Kunde OHNE DSGVO-KI-Freigabe bestellt -> KI-Agenten erhalten "Anonyme Person (Keine DSGVO-KI-Freigabe)" + sachliche Bestelldaten
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $anonCustomer = \App\Models\Customer\Customer::factory()->create([
            'first_name' => 'Geheim',
            'last_name' => 'Kundin',
            'email' => 'geheim@example.com',
        ]);
        $anonCustomer->profile()->updateOrCreate(
            ['customer_id' => $anonCustomer->id],
            ['ai_data_consent' => false, 'ai_data_consent_at' => null]
        );

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-DSGVO99',
            'customer_id' => $anonCustomer->id,
            'email' => 'geheim@example.com',
            'status' => 'processing',
            'payment_status' => 'paid',
            'billing_address' => [
                'first_name' => 'Geheim',
                'last_name' => 'Kundin',
                'street' => 'Privatweg 7',
                'postal_code' => '12345',
                'city' => 'Geheimstadt',
                'country' => 'DE',
            ],
            'shipping_address' => [],
            'subtotal_price' => 4990,
            'discount_amount' => 0,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
        ]);

        // 2a. AiOrderFuncs::executeGetOrder anonymisiert Name, E-Mail & Adresse, behält aber Bestellnummer, Status & Betrag
        $orderResult = \App\Services\AI\Functions\AiOrderFuncs::executeGetOrder(['order_number' => 'ORD-2026-DSGVO99']);
        $this->assertEquals('success', $orderResult['status']);
        $fetchedOrder = $orderResult['orders'][0];
        $this->assertEquals('ORD-2026-DSGVO99', $fetchedOrder['order_number']);
        $this->assertEquals('processing', $fetchedOrder['status']);
        $this->assertEquals('49,90 €', $fetchedOrder['cost_overview']['total']);
        $this->assertFalse($fetchedOrder['customer']['gdpr_ai_consent']);
        $this->assertEquals('Anonyme Person (Keine DSGVO-KI-Freigabe)', $fetchedOrder['customer']['name']);
        $this->assertEquals('[E-Mail DSGVO-geschützt]', $fetchedOrder['customer']['email']);
        $this->assertEquals('[Straße DSGVO-geschützt]', $fetchedOrder['address_info']['billing']['street']);

        // 2b. AiScoutFuncs::executeSearchCustomers anonymisiert den Kunden ebenfalls, zeigt aber sachliche Bestellhistorie
        $scoutResult = \App\Services\AI\Functions\AiScoutFuncs::executeSearchCustomers(['search_query' => 'Geheim']);
        $this->assertEquals('success', $scoutResult['status']);
        $this->assertEquals('Anonyme Person (Keine DSGVO-KI-Freigabe)', $scoutResult['customers'][0]['name']);
        $this->assertEquals('[E-Mail DSGVO-geschützt]', $scoutResult['customers'][0]['email']);
        $this->assertEquals(1, $scoutResult['customers'][0]['total_orders']);
        $this->assertEquals('ORD-2026-DSGVO99', $scoutResult['customers'][0]['recent_orders'][0]['order_number']);

        // 3. Sobald der Kunde den DSGVO-Haken aktiviert, erhält die KI den Klarnamen und die E-Mail
        $anonCustomer->updateAiDataConsent(true, 'profile_settings');

        $consentedOrderResult = \App\Services\AI\Functions\AiOrderFuncs::executeGetOrder(['order_number' => 'ORD-2026-DSGVO99']);
        $consentedOrder = $consentedOrderResult['orders'][0];
        $this->assertTrue($consentedOrder['customer']['gdpr_ai_consent']);
        $this->assertEquals('Geheim Kundin', $consentedOrder['customer']['name']);
        $this->assertEquals('geheim@example.com', $consentedOrder['customer']['email']);
        $this->assertEquals('Privatweg 7', $consentedOrder['address_info']['billing']['street']);
    }

    public function test_influencer_vouchers_hidden_from_public_slider_repeat_customer_usage_pause_delete_consistency_min_payout_and_master_dashboard_todo(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        $service = app(InfluencerService::class);

        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Alina',
            'last_name' => 'Creator',
            'email' => 'alina-creator@example.com',
            'handle' => '@alinarofl',
            'slug' => 'alinarofl',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
            'custom_voucher_code' => 'ALINAROFL10',
        ]);

        // 1. Öffentlicher Gutschein-Slider ("Deine Funken – Aktuelle Rabatte & Gutscheine") darf Influencer-Codes NIEMALS öffentlich leaken
        Livewire::test(\App\Livewire\Shop\Marketing\MarketingVoucherSlider::class)
            ->assertDontSee('ALINAROFL10');

        $this->assertFalse(
            \App\Models\Marketing\MarketingVoucher::current()->where('code', 'ALINAROFL10')->exists(),
            'Influencer-Codes dürfen nicht im öffentlichen scopeCurrent() auftauchen.'
        );

        // 2. Derselbe Stammkunde darf den Influencer-Rabattcode mehrfach (bei jeder neuen Bestellung) nutzen
        $loyalCustomer = \App\Models\Customer\Customer::factory()->create([
            'email' => 'stammkunde@example.com',
        ]);
        $dummyAddress = ['first_name' => 'Stamm', 'last_name' => 'Kunde', 'country' => 'DE'];

        $order1 = OrderOrder::create([
            'order_number' => 'ORD-2026-REP01',
            'customer_id' => $loyalCustomer->id,
            'email' => 'stammkunde@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'coupon_code' => 'ALINAROFL10',
            'subtotal_price' => 5950,
            'discount_amount' => 595,
            'tax_amount' => 855,
            'shipping_price' => 0,
            'total_price' => 5355,
            'is_small_business' => false,
        ]);

        $order2 = OrderOrder::create([
            'order_number' => 'ORD-2026-REP02',
            'customer_id' => $loyalCustomer->id,
            'email' => 'stammkunde@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => $dummyAddress,
            'shipping_address' => $dummyAddress,
            'coupon_code' => 'ALINAROFL10',
            'subtotal_price' => 11900,
            'discount_amount' => 1190,
            'tax_amount' => 1710,
            'shipping_price' => 0,
            'total_price' => 10710,
            'is_small_business' => false,
        ]);

        $comm1 = $service->processOrderCommission($order1);
        $comm2 = $service->processOrderCommission($order2);
        $this->assertNotNull($comm1);
        $this->assertNotNull($comm2);
        $this->assertNotEquals($comm1->id, $comm2->id);
        $this->assertEquals(675, $comm1->commission_amount_cents);  // 6,75 € Netto-Provision
        $this->assertEquals(1350, $comm2->commission_amount_cents); // 13,50 € Netto-Provision (Summe = 20,25 €)

        // 3. Eigenbestellungs-Sperre im CartService für den eigenen Creator-Code
        $voucher = $influencer->defaultVoucher->fresh();
        $cartService = app(\App\Services\CartService::class);
        $this->assertTrue($cartService->isInfluencerSelfRedemption($voucher, 'alina-creator@example.com'));
        $this->assertFalse($cartService->isInfluencerSelfRedemption($voucher, 'stammkunde@example.com'));

        // 4. Pausieren des Influencers sperrt sofort Gutschein & Tracking-Links
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('toggleActive', $influencer->id);

        $this->assertFalse($influencer->fresh()->is_active);
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertFalse($voucher->fresh()->isValid());
        $this->assertFalse($influencer->defaultTrackingLink->fresh()->is_active);

        // Wieder aktivieren
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('toggleActive', $influencer->id);
        $this->assertTrue($voucher->fresh()->isValid());

        // 5. Mindestauszahlungsbetrag (20,00 € Netto = 2000 Cent) im Creator-Selbstabruf:
        // Steuerprofil vervollständigen
        $influencer->employee->profile()->updateOrCreate([], [
            'street' => 'Creatorweg',
            'house_number' => '1',
            'postal' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);
        $influencer->update([
            'tax_status' => 'small_business',
            'tax_number' => '12/345/67890',
            'self_employed_confirmed' => true,
            'self_employed_confirmed_at' => now(),
            'payout_method' => 'bank_transfer',
            'account_holder' => 'Alina Creator',
            'iban' => 'DE89370400440532013000',
        ]);

        // Wenn wir comm2 kurz auf pending setzen, sind nur 6,75 € (< 20,00 €) freigegeben -> Creator-Selbstabruf wird blockiert!
        $comm2->update(['status' => 'pending']);
        try {
            $service->requestPayout($influencer->fresh(), 'bank_transfer', true);
            $this->fail('Auszahlung unter 20,00 € hätte im Creator-Selbstabruf blockiert werden müssen.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Mindestauszahlungsbetrag von 20,00 €', $e->getMessage());
        }

        // Sobald comm2 wieder approved ist (6,75 € + 13,50 € = 20,25 € >= 20,00 €), klappt die Auszahlung!
        $comm2->update(['status' => 'approved']);
        $payout = $service->requestPayout($influencer->fresh(), 'bank_transfer', true);
        $this->assertEquals(2025, $payout->net_amount_cents);

        // 6. Route [admin.influencer.payout.pdf] existiert und liefert dem Admin das PDF (kein 500 Server Error!)
        $this->actingAs($admin, 'admin')
            ->get(route('admin.influencer.payout.pdf', ['payout' => $payout->id]))
            ->assertStatus(200);

        // 7. Master Analytics Dashboard ("To-do Liste & Operatives") meldet offene Influencer-Auszahlungen
        $this->actingAs($admin, 'admin');
        $healthChecks = app(\App\Services\AnalyticsService::class)->getHealthChecks();
        $this->assertArrayHasKey('influencer_payouts', $healthChecks);
        $this->assertGreaterThan(0, $healthChecks['influencer_payouts']['count']);
        $this->assertEquals('warning', $healthChecks['influencer_payouts']['status']);
        $this->assertStringContainsString('20,25 €', $healthChecks['influencer_payouts']['message']);

        // 8. GoBD-Löschschutz: Da Alina bereits Provisionen & Gutschriften besitzt, darf deleteInfluencer sie NICHT hart löschen, sondern archiviert sie GoBD-sicher!
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('deleteInfluencer', $influencer->id);

        $archivedInfluencer = MarketingInfluencer::find($influencer->id);
        $this->assertNotNull($archivedInfluencer, 'Influencer mit Buchungen darf wegen GoBD nicht hart gelöscht werden.');
        $this->assertTrue($archivedInfluencer->is_archived);
        $this->assertFalse($archivedInfluencer->is_active);
        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertNotNull(MarketingInfluencerPayout::find($payout->id));

        // Ein ungenutzter Influencer OHNE Buchungen hingegen wird rückstandsfrei inkl. Voucher & TrackingLink gelöscht
        $unusedInfluencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Unused',
            'last_name' => 'Test',
            'email' => 'unused-creator@example.com',
            'handle' => '@unused.creator',
            'slug' => 'unused-creator',
        ]);
        $unusedVoucherId = $unusedInfluencer->default_voucher_id;
        $unusedLinkId = $unusedInfluencer->default_tracking_link_id;
        $unusedEmployeeId = $unusedInfluencer->employee_id;

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingInfluencerComponent::class)
            ->call('deleteInfluencer', $unusedInfluencer->id);

        $this->assertNull(MarketingInfluencer::find($unusedInfluencer->id));
        $this->assertNull(\App\Models\Marketing\MarketingVoucher::find($unusedVoucherId));
        $this->assertNull(\App\Models\Marketing\MarketingTrackingLink::find($unusedLinkId));
        $this->assertNull(Employee::find($unusedEmployeeId));
    }

    public function test_centralized_product_promotion_preview_autopilot_sale_filtering_and_one_click_deeplink_in_creator_dashboard(): void
    {
        $service = app(InfluencerService::class);

        $influencer = $service->createOrUpdateInfluencer([
            'first_name' => 'Lara',
            'last_name' => 'Preview',
            'email' => 'lara-preview@example.com',
            'handle' => '@lara.preview',
            'slug' => 'lara-preview',
            'commission_type' => 'percentage',
            'commission_value' => 15,
            'follower_discount_percent' => 10,
            'custom_voucher_code' => 'LARAPREV10',
        ]);

        // 1. Reguläres Produkt (59,50 € Brutto, 19% USt. -> mit 10% Code = 53,55 € Brutto = 45,00 € Netto -> 15% Provision = 6,75 €)
        $regularProduct = \App\Models\Product\Product::create([
            'name' => '3D Kristall-Herz Unikat',
            'slug' => '3d-kristall-herz-unikat',
            'sku' => 'KRISTALL-HERZ-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 5950,
            'compare_at_price' => null,
            'track_quantity' => true,
            'quantity' => 12,
            'continue_selling_when_out_of_stock' => false,
            'three_d_active' => true,
        ]);

        // 2. Bereits reduziertes Feiertags-/Autopilot-Sale-Unikat (47,60 € Brutto statt 59,50 €, 19% USt. -> kein Code-Abzug -> 40,00 € Netto -> 15% Provision = 6,00 €)
        $saleProduct = \App\Models\Product\Product::create([
            'name' => 'Muttertags-Sonderedition Schiefer',
            'slug' => 'muttertags-sonderedition-schiefer',
            'sku' => 'SCHIEFER-SALE-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4760,
            'compare_at_price' => 5950,
            'configurator_settings' => [
                'autopilot_base_price' => 5950,
                'autopilot_event_key' => 'mothers_day',
                'autopilot_campaign_label' => 'Muttertags-Aktion',
            ],
            'track_quantity' => true,
            'quantity' => 3,
            'continue_selling_when_out_of_stock' => false,
        ]);

        // 3. Ausverkauftes Produkt (darf NICHT beworben werden)
        $outOfStockProduct = \App\Models\Product\Product::create([
            'name' => 'Ausverkauftes Holz-Unikat',
            'slug' => 'ausverkauftes-holz-unikat',
            'sku' => 'HOLZ-OOS-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => false,
        ]);

        // 4. Geschenkgutschein (Wertgutschein -> darf NICHT rabattiert oder im Produkt-Grid beworben werden)
        $giftVoucherProduct = \App\Models\Product\Product::create([
            'name' => 'Seelenfunke Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'sku' => 'VOUCHER-MAIN',
            'type' => 'digital',
            'status' => 'active',
            'price' => 5000,
            'track_quantity' => false,
            'quantity' => 999,
        ]);

        // Zentrale Modell-Helfer prüfen
        $this->assertTrue($regularProduct->isPromotableByInfluencer());
        $this->assertTrue($regularProduct->isDiscountableWithCoupon());
        $this->assertFalse($regularProduct->isInAutopilotSale());

        $this->assertTrue($saleProduct->isPromotableByInfluencer());
        $this->assertTrue($saleProduct->isInAutopilotSale());
        $this->assertFalse($saleProduct->isDiscountableWithCoupon());

        $this->assertFalse($outOfStockProduct->isPromotableByInfluencer());
        $this->assertTrue($giftVoucherProduct->isGiftVoucher());
        $this->assertFalse($giftVoucherProduct->isDiscountableWithCoupon());
        $this->assertFalse($giftVoucherProduct->isPromotableByInfluencer());

        // Zentrale Liste bewerbbarer Produkte abrufen
        $promotable = $service->getPromotableProductsForInfluencer($influencer);
        $slugs = $promotable->pluck('slug')->all();

        $this->assertContains('3d-kristall-herz-unikat', $slugs);
        $this->assertContains('muttertags-sonderedition-schiefer', $slugs);
        $this->assertNotContains('ausverkauftes-holz-unikat', $slugs);
        $this->assertNotContains('geschenkgutschein', $slugs);

        // Mathematische Präzision: Reguläres Produkt vs. Aktions-Unikat
        $regularPreview = $promotable->firstWhere('slug', '3d-kristall-herz-unikat');
        $this->assertEquals('code_and_link', $regularPreview['mode']);
        $this->assertTrue($regularPreview['is_coupon_compatible']);
        $this->assertEquals(5950, $regularPreview['gross_price_cents']);
        $this->assertEquals(595, $regularPreview['follower_discount_cents']);
        $this->assertEquals(5355, $regularPreview['follower_final_gross_cents']);
        $this->assertEquals(4500, $regularPreview['net_basis_cents']);
        $this->assertEquals(675, $regularPreview['estimated_commission_cents']); // 6,75 € Netto-Provision

        $salePreview = $promotable->firstWhere('slug', 'muttertags-sonderedition-schiefer');
        $this->assertEquals('link_only', $salePreview['mode']);
        $this->assertFalse($salePreview['is_coupon_compatible']);
        $this->assertTrue($salePreview['is_autopilot_sale']);
        $this->assertEquals(4760, $salePreview['gross_price_cents']);
        $this->assertEquals(0, $salePreview['follower_discount_cents']);
        $this->assertEquals(4760, $salePreview['follower_final_gross_cents']);
        $this->assertEquals(4000, $salePreview['net_basis_cents']);
        $this->assertEquals(600, $salePreview['estimated_commission_cents']); // 6,00 € Netto-Provision trotz Aktions-Sperre über Kurzlink!

        // Livewire Creator Dashboard zeigt die neue Kachel + generiert 1-Klick Deep-Link
        Livewire::actingAs($influencer->employee, 'employee')
            ->test(InfluencerDashboard::class)
            ->assertSee('Aktuelle Produkte bewerben')
            ->assertSee('3D Kristall-Herz Unikat')
            ->assertSee('+6,75 €')
            ->assertSee('Muttertags-Sonderedition Schiefer')
            ->assertSee('+6,00 €')
            ->assertDontSee('Ausverkauftes Holz-Unikat')
            ->call('quickCreateProductLink', '3d-kristall-herz-unikat')
            ->assertSee('/t/lara-preview-3d-kristall-herz-unikat');

        $this->assertTrue(
            \App\Models\Marketing\MarketingTrackingLink::where('influencer_id', $influencer->id)
                ->where('target_url', '/produkt/3d-kristall-herz-unikat')
                ->exists()
        );

        // Sicherheits-Prüfung: Externer Influencer darf NIEMALS als interner Mitarbeiter eingestuft werden
        // oder interne Shop-/Admin-Livewire-Komponenten aufrufen
        $this->actingAs($influencer->employee, 'employee');
        $this->assertFalse(\App\Services\AI\AiAuthHelper::isEmployee());

        Livewire::actingAs($influencer->employee, 'employee')
            ->test(\App\Livewire\Shop\Order\OrderOverview::class)
            ->assertForbidden();

        Livewire::actingAs($influencer->employee, 'employee')
            ->test(\App\Livewire\Shop\Order\OrderShoppingCarts::class)
            ->assertForbidden();
    }
}
