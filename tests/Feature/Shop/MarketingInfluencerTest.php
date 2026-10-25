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
}
