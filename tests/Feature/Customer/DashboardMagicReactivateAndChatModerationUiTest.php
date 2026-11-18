<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;

/**
 * Sichert den edlen Gold-Shimmer am "Magie aktivieren"-Button, die Sofort-Reaktivierung ohne Seiten-Reload
 * sowie die Frontend-Reaktion auf Multiplayer-Killswitch und 3-Stufen-Chat-Abmahnung ab.
 */
class DashboardMagicReactivateAndChatModerationUiTest extends TestCase
{
    private function src(string $relative): string
    {
        $path = base_path($relative);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_dashboard_magic_button_glows_alone_and_card_grayscale_is_scoped_to_inner_content(): void
    {
        $blade = $this->src('resources/views/livewire/customer/customer-dashboard-component.blade.php');

        // Exklusiver 24K-Gold-Shimmer nur auf dem Button
        $this->assertStringContainsString('@keyframes sfGoldShimmerSweep', $blade);
        $this->assertStringContainsString('.sf-membercard-locked:hover .sf-magic-activate-cta::after', $blade);
        $this->assertStringContainsString('class="sf-magic-activate-cta', $blade);
        $this->assertStringContainsString('sf-magic-spark-icon', $blade);

        // Graufilter nicht mehr auf dem aeusseren Kartencontainer (sonst wird der Button ausgewaschen)
        $this->assertStringNotContainsString("{{ !\$isGameModeActive ? 'grayscale-[0.65] opacity-80 transition-all duration-300 group/membercard'", $blade);
        $this->assertStringContainsString("relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6 transition-all duration-300 {{ !\$isGameModeActive ? 'grayscale-[0.65] opacity-75' : '' }}", $blade);

        // Kein lila/Neon-Glow
        $this->assertDoesNotMatchRegularExpression('/sf-magic-activate-cta[^}]*(purple|violet|fuchsia)/i', $blade);
    }

    public function test_magic_can_be_reactivated_without_page_reload(): void
    {
        $dashboard = $this->src('resources/views/livewire/customer/customer-dashboard-component.blade.php');
        $funki = $this->src('resources/views/livewire/customer/customer-funki-component.blade.php');

        // Reaktiver Livewire-Zustand statt einmalig eingebranntem Blade-Literal
        $this->assertStringContainsString("this.\$wire.get('isGameModeActive')", $dashboard);
        $this->assertStringNotContainsString('if (!@js((bool) $isGameModeActive))', $dashboard);

        // Sauberer Opt-In-Reset bei jedem Oeffnen (auch nach Livewire-Morph des Teleports)
        foreach ([$dashboard, $funki] as $blade) {
            $this->assertStringContainsString("window.dispatchEvent(new CustomEvent('sf-reset-magic-optin'))", $blade);
            $this->assertStringContainsString('@sf-reset-magic-optin.window="resetOptInState()"', $blade);
        }

        // Versiegelungs-Overlay faellt garantiert zurueck und blockiert keine Klicks
        $this->assertStringContainsString('this.isSealingMagic = false;', $dashboard);
        $this->assertStringContainsString('safetyTimer', $dashboard);
    }

    public function test_lobby_frontend_reacts_to_multiplayer_killswitch_and_chat_sanctions(): void
    {
        $filterArea = $this->src('resources/views/livewire/shop/product/product-frontend-filter-area.blade.php');
        $heroes = $this->src('public/js/funkenwerk-heroes-3d.js');

        foreach ([$filterArea, $heroes] as $code) {
            $this->assertStringContainsString('_wsDisabledByServer', $code);
            $this->assertStringContainsString("'multiplayer_disabled'", $code);
            $this->assertStringContainsString('4001', $code);
            $this->assertStringContainsString('Halle (Solo)', $code);
            $this->assertStringContainsString('chatStrikeInfo', $code);
        }

        // Chat-Eingabe wird waehrend einer aktiven Sperre deaktiviert
        $this->assertStringContainsString(':disabled="chatSending || chatStrikeInfo.isMuted"', $filterArea);
        $this->assertStringContainsString('!multiplayerDisabled', $filterArea);
    }

    public function test_touched_customer_and_lobby_files_have_zero_ai_dashes_and_no_inline_try_catch_in_x_init(): void
    {
        foreach ([
            'resources/views/livewire/customer/customer-dashboard-component.blade.php',
            'resources/views/livewire/customer/customer-funki-component.blade.php',
        ] as $file) {
            $code = $this->src($file);
            $this->assertStringNotContainsString(" \u{2013} ", $code, $file);
            $this->assertStringNotContainsString(" \u{2014} ", $code, $file);
            $this->assertDoesNotMatchRegularExpression('/x-init="[^"]*try\s*\{/', $code, $file);
        }
    }
}
