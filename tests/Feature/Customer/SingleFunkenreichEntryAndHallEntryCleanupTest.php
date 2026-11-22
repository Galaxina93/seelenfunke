<?php

namespace Tests\Feature\Customer;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\CustomerFunkiComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kundenbereich: genau EIN 3D-Einstieg ("Ins Funkenreich" auf dem Dashboard),
 * keine 0/5-Diamant-Zeile mehr in der Halle der Helden, sichtbares Funki-Icon in der aktiven Nav-Pill
 * und null-sicheres Shader-Warmup ohne verwaistes three.js compileAsync-Polling.
 */
class SingleFunkenreichEntryAndHallEntryCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'first_name' => 'Sarah',
            'last_name' => 'Sonnenschein',
            'email' => 'sarah-' . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password123'),
        ]);

        CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customer->id],
            [
                'is_active' => true,
                'funken_balance' => 120,
                'funken_total_earned' => 120,
                'level' => 1,
                'funki_level' => 1,
            ]
        );

        $this->actingAs($this->customer, 'customer');
    }

    private function viewSource(string $relative): string
    {
        return (string) file_get_contents(resource_path('views/' . $relative));
    }

    public function test_dashboard_renders_exactly_one_funkenreich_entry_and_no_other_3d_buttons(): void
    {
        $html = (string) Livewire::test(CustomerDashboardComponent::class)->html();

        $this->assertSame(1, substr_count($html, 'Ins Funkenreich'));
        $this->assertSame(1, substr_count($html, 'data-testid="dashboard-open-user-lobby-3d"'));
        $this->assertSame(1, substr_count($html, 'SeelenfunkeHeroes3D'));
        $this->assertStringContainsString("open('lobby')", $html);
        $this->assertStringContainsString('sf_visited_funkenreich_v1', $html);
        $this->assertStringContainsString('sf-funkenreich-visited', $html);
        $this->assertStringContainsString('sf-funkenreich-cta', $html);

        $this->assertStringNotContainsString('Funken im Funkenreich sammeln', $html);
        $this->assertStringNotContainsString('Halle der Helden 3D', $html);
        $this->assertStringNotContainsString("open('hall')", $html);
        $this->assertStringNotContainsString('modal-open-hall-of-heroes-3d', $html);
    }

    public function test_funki_page_has_no_3d_entry_buttons_anymore(): void
    {
        $html = (string) Livewire::test(CustomerFunkiComponent::class)->html();

        $this->assertStringNotContainsString('Funken im Funkenreich sammeln', $html);
        $this->assertStringNotContainsString('Halle der Helden 3D', $html);
        $this->assertStringNotContainsString('Ins Funkenreich', $html);
        $this->assertStringNotContainsString('SeelenfunkeHeroes3D', $html);
        $this->assertStringNotContainsString('funkenwerk-heroes-3d.js', $html);
        $this->assertStringNotContainsString('funki-open-hall-of-heroes-3d', $html);
        $this->assertStringNotContainsString('funki-vouchers-open-3d-lobby', $html);

        // Tab & Rangliste bleiben erhalten
        $this->assertStringContainsString('Halle der Helden', $html);
        $this->assertStringContainsString('Top Sammler', $html);
    }

    public function test_customer_view_sources_contain_no_removed_3d_entries_or_diamond_counter(): void
    {
        $customerViews = [
            'livewire/customer/customer-dashboard-component.blade.php',
            'livewire/customer/customer-funki-component.blade.php',
            'livewire/customer/gamification/game-global-stats-header.blade.php',
            'components/layouts/customer_layout.blade.php',
        ];

        $entryCount = 0;
        foreach ($customerViews as $view) {
            $src = $this->viewSource($view);
            $this->assertStringNotContainsString('Funken im Funkenreich sammeln', $src, $view);
            $this->assertStringNotContainsString('Halle der Helden 3D', $src, $view);
            $this->assertStringNotContainsString("open('hall')", $src, $view);
            $this->assertStringNotContainsString("\$leader['total_titles'] ?? 5) }} 💎", $src, $view);
            $this->assertStringNotContainsString(' – ', $src, $view);
            $entryCount += substr_count($src, 'Ins Funkenreich');
        }

        $this->assertSame(1, $entryCount);

        // Score bleibt in beiden Ranglisten erhalten
        foreach (['livewire/customer/customer-dashboard-component.blade.php', 'livewire/customer/customer-funki-component.blade.php'] as $view) {
            $this->assertStringContainsString("\$leader['hero_score_formatted']", $this->viewSource($view), $view);
        }
    }

    public function test_funkenreich_cta_is_animated_gold_and_respects_reduced_motion(): void
    {
        $src = $this->viewSource('livewire/customer/customer-dashboard-component.blade.php');

        $this->assertStringContainsString('.sf-funkenreich-cta', $src);
        $this->assertStringContainsString('conic-gradient', $src);
        $this->assertStringContainsString('@keyframes sfFrBorderOrbit', $src);
        $this->assertStringContainsString('@keyframes sfFrSparkRise', $src);
        $this->assertStringContainsString('animation: sfGoldShimmerSweep', $src);
        $this->assertStringContainsString('#C5A059', $src);
        $this->assertStringContainsString('.dark .sf-funkenreich-cta', $src);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $src);

        // Max. 8 Goldfunken-Partikel
        preg_match('/<span class="sf-fr-sparks">(.*?)<\/span>/s', $src, $m);
        $this->assertNotEmpty($m);
        $sparkCount = substr_count($m[1], '<i></i>');
        $this->assertGreaterThanOrEqual(6, $sparkCount);
        $this->assertLessThanOrEqual(8, $sparkCount);

        // Kein Lila/Neon im CTA-Block
        $ctaCss = substr($src, strpos($src, '.sf-funkenreich-cta {'), 9000);
        $this->assertStringNotContainsString('purple', $ctaCss);
        $this->assertStringNotContainsString('violet', $ctaCss);
        $this->assertStringNotContainsString('indigo', $ctaCss);
    }

    public function test_active_funki_nav_pill_renders_dark_icon_and_inactive_stays_gold(): void
    {
        $funkiPage = (string) $this->get(route('customer.funki'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-testid="customer-nav-funki-icon" class="w-4 h-4 text-gray-950"/', $funkiPage);
        $this->assertDoesNotMatchRegularExpression('/data-testid="customer-nav-funki-icon" class="w-4 h-4 text-\[#C5A059\]"/', $funkiPage);

        $dashboardPage = (string) $this->get(route('customer.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-testid="customer-nav-funki-icon" class="w-4 h-4 text-\[#C5A059\]"/', $dashboardPage);
        $this->assertSame(1, substr_count($dashboardPage, 'data-testid="dashboard-open-user-lobby-3d"'));
    }

    public function test_louvre_engine_uses_null_safe_shader_warmup_instead_of_vendor_compile_async(): void
    {
        $engine = (string) file_get_contents(public_path('js/seelenfunke-louvre-showroom-3d.js'));
        $host = (string) file_get_contents(public_path('js/funkenwerk-heroes-3d.js'));

        $this->assertStringNotContainsString('this.renderer.compileAsync(', $engine);
        $this->assertStringContainsString('_safeCompileAsync(maxWaitMs)', $engine);
        $this->assertStringContainsString('await this._safeCompileAsync(1400);', $engine);
        $this->assertStringContainsString("if (!program || typeof program.isReady !== 'function' || program.isReady())", $engine);
        $this->assertStringContainsString('this.destroyed || this.renderer !== renderer', $engine);
        $this->assertStringContainsString('seelenfunke-louvre-showroom-3d.js?v=20261120v36', $host);
    }
}
