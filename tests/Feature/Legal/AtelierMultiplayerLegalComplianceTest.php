<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtelierMultiplayerLegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agb_renders_with_3d_atelier_multiplayer_dsa_moderation_and_feedback_clauses(): void
    {
        $response = $this->get('/agb');

        $response->assertOk();

        // § 3 Abs. 10: Urheberrecht an Gravur-Studio, 3D-Atelier, Halle der Helden & 3D-Modellen
        $response->assertSee('id="konfigurator-urheberrecht"', false);
        $response->assertSee('2D/3D-Gravur-Studio');
        $response->assertSee('3D-Produkt-Atelier');
        $response->assertSee('3D-Sternebewertungs-Station');
        $response->assertSee('Halle der Helden');

        // § 8: Treueprogramm, Solo-Modus, Multiplayer & DSA 3-Stufen-Abmahnungssystem
        $response->assertSee('id="agb-gamification"', false);
        $response->assertSee('Solo-Modus');
        $response->assertSee('ranking_opt_in');
        $response->assertSee('250 Seelenfunken');
        $response->assertSee('600 Seelenfunken');
        $response->assertSee('1.200 Seelenfunken');
        $response->assertSee('Digital Services Act');
        $response->assertSee('1-Klick-Meldefunktion');
        $response->assertSee('Verwarnung 1/3');
        $response->assertSee('Abmahnung 2/3');
        $response->assertSee('15 Minuten');
        $response->assertSee('Abmahnung 3/3');
        $response->assertSee('24 Stunden');
        $response->assertSee('menschliche Überprüfung');

        // § 8a: Kundenbewertungen & freiwilliges Erlebnis-/Funktions-Feedback
        $response->assertSee('id="agb-kundenbewertungen"', false);
        $response->assertSee('§ 5b Abs. 3 UWG');
        $response->assertSee('Freiwilliges Erlebnis- & Funktions-Feedback');

        // Bestehende Pflichtklauseln bleiben 100 % erhalten
        $response->assertSee('§ 11 PAngV');
        $response->assertSee('Stripe Payments Europe');
        $response->assertSee('GPSR');
        $response->assertSee('§ 36 VSBG');
        $response->assertSee('Seelenfunke Creator Partnerprogramm');
        $response->assertSee('Widerrufsbelehrung');

        // Ausgelagertes Magma-Spiel darf nicht mehr in den Shop-AGB versprochen werden
        $response->assertDontSee('Magma-Spiel');
        $response->assertDontSee('Manufaktur-Meisterschlüssel');
    }

    public function test_datenschutz_renders_with_websocket_lobby_ws_ringbuffer_and_dsa_privacy_clauses(): void
    {
        $response = $this->get('/datenschutz');

        $response->assertOk();

        // Abschnitt 5: Funktions- & Erlebnis-Feedback an der 3D-Sternebewertungs-Station
        $response->assertSee('3D-Sternebewertungs-Station');
        $response->assertSee('3D-Atelier');
        $response->assertSee('Produktkonfigurator');
        $response->assertSee('Angebots-Kalkulator');

        // Abschnitt 9: Solo-Modus, WebSocket in Deutschland, Ringpuffer & DSA-Moderation
        $response->assertSee('id="datenschutz-gamification"', false);
        $response->assertSee('WebGL/Three.js');
        $response->assertSee('wss://api-live-bridge.mein-seelenfunke.de/lobby-ws');
        $response->assertSee('Deutschland');
        $response->assertSee('Mittwald');
        $response->assertSee('30 Hz');
        $response->assertSee('HMAC-SHA256');
        $response->assertSee('peer_id');
        $response->assertSee('In-Memory');
        $response->assertSee('25 Sekunden');
        $response->assertSee('Funki #XXXX');
        $response->assertSee('lobby-ping');
        $response->assertSee('120 Sekunden');
        $response->assertSee('Stufe 1 bis 3');
        $response->assertSee('15 Minuten');
        $response->assertSee('24 Stunden');
        $response->assertSee('SystemLog');
        $response->assertSee('Art. 22 DSGVO');
        $response->assertSee('menschlich überprüft');
        $response->assertSee('Verordnung (EU) 2022/2065 (DSA)');
        $response->assertSee('§ 25 Abs. 2 Nr. 2 TDDDG');

        // Bestehende Pflichtklauseln bleiben 100 % erhalten
        $response->assertSee('Google Consent Mode v2');
        $response->assertSee('Stripe Payments Europe');
        $response->assertSee('PayPal');
        $response->assertSee('Seelenfunke Creator Partnerprogramm');

        // Ausgelagertes Magma-Spiel bereinigt
        $response->assertDontSee('Magma-Spiel');
        $response->assertDontSee('lava_cleared_levels');
    }

    public function test_rendered_agb_and_datenschutz_pages_contain_zero_ai_dashes(): void
    {
        foreach (['/agb', '/datenschutz'] as $uri) {
            $response = $this->get($uri);
            $response->assertOk();

            $rawHtml = $response->getContent();
            $visibleText = html_entity_decode(strip_tags($rawHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $this->assertStringNotContainsString(
                " \u{2013} ",
                $rawHtml,
                "Gerendertes HTML unter {$uri} enthaelt einen KI-Gedankenstrich (En-Dash)."
            );
            $this->assertStringNotContainsString(
                " \u{2014} ",
                $rawHtml,
                "Gerendertes HTML unter {$uri} enthaelt einen KI-Gedankenstrich (Em-Dash)."
            );
            $this->assertStringNotContainsString(
                " \u{2013} ",
                $visibleText,
                "Sichtbarer Text unter {$uri} enthaelt einen KI-Gedankenstrich (En-Dash)."
            );
            $this->assertStringNotContainsString(
                " \u{2014} ",
                $visibleText,
                "Sichtbarer Text unter {$uri} enthaelt einen KI-Gedankenstrich (Em-Dash)."
            );
        }
    }
}
