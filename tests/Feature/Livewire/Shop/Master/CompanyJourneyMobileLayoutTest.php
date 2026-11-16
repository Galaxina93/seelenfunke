<?php

namespace Tests\Feature\Livewire\Shop\Master;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyJourneyMobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    #[Test]
    public function company_journey_tile_uses_mobile_safe_wrapping_and_single_column_grids(): void
    {
        $this->actingAs($this->admin, 'admin');

        $html = Livewire::test(MasterAnalytics::class)
            ->set('focusMode', false)
            ->assertStatus(200)
            ->assertSee('10-Jahres Firmenreise & Umsatzplan', false)
            ->assertSee('Aktueller Status:', false)
            ->assertSee('Umsatzplan (1-Laser & 10J)', false)
            ->assertSee('Skalierung (1,2 bis 12 Mio./J.)', false)
            ->html();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // 1. Header H2 ("Aktueller Status: <Rang>") muss flex-wrap und min-w-0 besitzen
        $statusHeading = $xpath->query('//h2[.//span[contains(text(), "Aktueller Status:")]]')->item(0);
        $this->assertNotNull($statusHeading, 'Status-Ueberschrift der Firmenreise muss vorhanden sein.');
        $h2Class = (string) $statusHeading->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('flex-wrap', $h2Class);
        $this->assertStringContainsString('min-w-0', $h2Class);
        $this->assertStringContainsString('break-words', $h2Class);

        // 2. Sub-Tab-Switcher nutzt auf Mobile grid-cols-1 und mehrzeilig umbrechende Buttons ohne whitespace-nowrap
        $tabButtons = $xpath->query(
            '//button[contains(., "Umsatzplan (1-Laser") or contains(., "1-Laser-Etappen bis 70K/Mt.") or contains(., "Skalierung (1,2 bis 12 Mio./J.)") or contains(., "Alle (12)")]'
        );
        $this->assertNotFalse($tabButtons);
        $this->assertEquals(4, $tabButtons->length);

        $switcherParent = $tabButtons->item(0)?->parentNode;
        $this->assertNotNull($switcherParent);
        $switcherClass = (string) $switcherParent->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('grid-cols-1', $switcherClass);
        $this->assertStringContainsString('min-w-0', $switcherClass);

        foreach ($tabButtons as $btn) {
            $btnClass = (string) $btn->attributes?->getNamedItem('class')?->nodeValue;
            $this->assertStringNotContainsString('whitespace-nowrap', $btnClass);
            $this->assertStringContainsString('whitespace-normal', $btnClass);
            $this->assertStringContainsString('break-words', $btnClass);
            $this->assertStringContainsString('min-w-0', $btnClass);
        }

        // 3. Floating-Pin nutzt sicheren 80px-Clamp passend zu max-w-[160px], damit kein Links/Rechts-Ausbruch entsteht
        $floatingPins = $xpath->query('//div[contains(@style, "clamp(80px,")]');
        $this->assertEquals(1, $floatingPins->length, 'Floating-Pin muss mit clamp(80px, ...) begrenzt sein.');

        // 4. Rand-Pins (0 € bei 2% und 1 Mio bei 100%) sind buendig ausgerichtet, Zwischen-Pins auf Mobile ausgeblendet
        $endPinLabel = $xpath->query('//div[contains(@style, "left: 100%;")]//div[contains(@class, "top-full")]')->item(0);
        $this->assertNotNull($endPinLabel);
        $endPinClass = (string) $endPinLabel->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('right-0', $endPinClass);
        $this->assertStringContainsString('items-end', $endPinClass);

        $midPinLabel = $xpath->query('//div[contains(@style, "left: 10%;")]//div[contains(@class, "top-full")]')->item(0);
        $this->assertNotNull($midPinLabel);
        $midPinClass = (string) $midPinLabel->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('hidden md:flex', $midPinClass);

        // 5. Stage-Karten (Teil 1) und Roadmap-Karten (Teil 2) duerfen kein hartes whitespace-nowrap oder ungeschuetztes grid-cols-3 auf Mobile haben
        $stageTitleNode = $xpath->query('//p[contains(text(), "4. Technisches Maximum")]')->item(0);
        $this->assertNotNull($stageTitleNode);
        $stageCard = $stageTitleNode->parentNode?->parentNode?->parentNode;
        $this->assertNotNull($stageCard);
        $stageCardClass = (string) $stageCard->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('min-w-0', $stageCardClass);
        $this->assertStringContainsString('overflow-hidden', $stageCardClass);

        $metricsGrid = $xpath->query('.//div[contains(@class, "sm:grid-cols-3")]', $stageCard)->item(0);
        $this->assertNotNull($metricsGrid, 'Kennzahlen-Zeile in Stage-Karte muss grid-cols-1 sm:grid-cols-3 nutzen.');
        $metricsGridClass = (string) $metricsGrid->attributes?->getNamedItem('class')?->nodeValue;
        $this->assertStringContainsString('grid-cols-1', $metricsGridClass);
        $this->assertStringContainsString('min-w-0', $metricsGridClass);
    }

    #[Test]
    public function company_journey_handles_long_rank_titles_and_high_revenues_without_clean_design_violations(): void
    {
        $this->actingAs($this->admin, 'admin');

        $tileSource = (string) file_get_contents(
            resource_path('views/livewire/shop/master/master-analytics-partials/company-journey-tile.blade.php')
        );

        // Keine KI-Gedankenstriche (Leerzeichen + En-Dash + Leerzeichen) und keine Lila-Klassen im Backend-Tile
        $forbiddenAiDash = ' ' . "\u{2013}" . ' ';
        $this->assertStringNotContainsString($forbiddenAiDash, $tileSource);
        $this->assertStringNotContainsString('purple-', $tileSource);

        // Teste extremen Umsatz & langen Rangnamen ("Logistikhalle" bei 55.000 EUR/Mt. & "Imperium" bei 1.000.000 EUR/Mt.)
        $component = Livewire::test(MasterAnalytics::class)
            ->set('stats.avg_revenue_monthly', 55000)
            ->assertStatus(200)
            ->assertSee('Logistikhalle')
            ->assertSee('55.000 € / Mt.');

        $rank = $component->get('currentCompanyRank');
        $this->assertSame('Logistikhalle', $rank['title']);

        $componentImperium = Livewire::test(MasterAnalytics::class)
            ->set('stats.avg_revenue_monthly', 1000000)
            ->set('journeyStartDate', '2026-11-01')
            ->call('saveJourneyStartDate')
            ->assertStatus(200)
            ->assertSee('Imperium')
            ->assertSee('01.11.2036');

        $this->assertSame('Imperium', $componentImperium->get('currentCompanyRank')['title']);
        $this->assertEquals(100, $componentImperium->get('journeyProgress')['track_percent']);
    }
}
