<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Product\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BeginnerSmartFitLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function createBoneDogTagProduct(): Product
    {
        return Product::create([
            'name' => 'Die Seelen-Pfote',
            'slug' => 'seelen-pfote-hundemarke',
            'sku' => 'MSF-PFOTE-TEST-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'is_personalizable' => true,
            'completion_step' => 4,
            'preview_image_path' => 'produkte/products/seelen-pfote-hundemarke/overlay.png',
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'template_package' => 'seelen-pfote-hundemarke',
                'allow_logo' => true,
                'has_back_side' => true,
                'overlay_type' => 'plane',
                'area_shape' => 'custom',
                'area_left' => 11.38,
                'area_top' => 31.13,
                'area_width' => 77.25,
                'area_height' => 37.75,
                'xtool_plane_width' => 40.0,
                'xtool_plane_height' => 22.0,
                'xtool_plate_box_pct' => ['left' => 10.0, 'top' => 29.75, 'width' => 80.0, 'height' => 40.5],
                'custom_points' => [
                    ['x' => 22.75, 'y' => 31.13],
                    ['x' => 28.37, 'y' => 33.29],
                    ['x' => 33.56, 'y' => 36.32],
                    ['x' => 38.03, 'y' => 36.76],
                    ['x' => 42.23, 'y' => 37.61],
                    ['x' => 45.94, 'y' => 38.63],
                    ['x' => 50.00, 'y' => 39.13],
                    ['x' => 54.06, 'y' => 38.63],
                    ['x' => 57.77, 'y' => 37.61],
                    ['x' => 61.97, 'y' => 36.76],
                    ['x' => 66.44, 'y' => 36.32],
                    ['x' => 77.25, 'y' => 31.13],
                    ['x' => 88.63, 'y' => 40.75],
                    ['x' => 87.00, 'y' => 48.50],
                    ['x' => 78.56, 'y' => 48.72],
                    ['x' => 71.25, 'y' => 51.00],
                    ['x' => 67.91, 'y' => 56.30],
                    ['x' => 67.00, 'y' => 63.38],
                    ['x' => 35.50, 'y' => 63.38],
                    ['x' => 22.75, 'y' => 68.88],
                    ['x' => 11.38, 'y' => 59.25],
                    ['x' => 13.31, 'y' => 50.00],
                    ['x' => 11.38, 'y' => 40.75],
                ],
            ],
        ]);
    }

    #[Test]
    public function bone_dog_tag_routes_phone_to_back_by_default_and_fits_front_and_back_without_overlap(): void
    {
        $dogTag = $this->createBoneDogTagProduct();

        $comp = Livewire::test(ProductConfigurator::class, [
            'product' => $dogTag,
            'context' => 'add',
            'initialData' => ['personalization_mode' => 'custom_fields'],
        ]);

        // Standardmäßig liegt die Telefonnummer auf der Rückseite ('back')
        $this->assertSame('front', $comp->get('customFieldSides.cf_wunschsymbol'));
        $this->assertSame('front', $comp->get('customFieldSides.cf_tiername'));
        $this->assertSame('back', $comp->get('customFieldSides.cf_notfallnummer'));

        // Exakt der Fall aus dem Kunden-Screenshot: Herz + Name + Telefonnummer
        $comp
            ->set('customFieldValues.cf_wunschsymbol', 'Herz ♥')
            ->set('customFieldValues.cf_tiername', 'adwadwada')
            ->set('customFieldValues.cf_notfallnummer', '3435353453')
            ->set('customFieldValues.cf_schriftart', 'Arial');

        $logos = $comp->get('logos');
        $texts = $comp->get('texts');
        $logosBack = $comp->get('logos_back');
        $textsBack = $comp->get('texts_back');

        $this->assertCount(1, $logos);
        $this->assertCount(1, $texts);
        $this->assertCount(0, $logosBack);
        $this->assertCount(1, $textsBack);

        // Vorderseite enthält nur das Symbol + den Tiernamen (kein Überlaufen durch die Telefonnummer)
        $this->assertSame('vectors/Liebe/herz.svg', $logos[0]['value']);
        $this->assertSame('adwadwada', $texts[0]['text']);
        $this->assertEquals(41.0, $logos[0]['x']);
        $this->assertEquals(41.0, $texts[0]['x']);

        // Geometrie-Beweis auf der Vorderseite: innerhalb der Knochen-Taille [38.11..63.38] und 0 Überlappung
        $logoHalfH = ($logos[0]['size'] * 0.19) / 2.0;
        $logoTop = $logos[0]['y'] - $logoHalfH;
        $logoBottom = $logos[0]['y'] + $logoHalfH;

        $textHalfH = (1 * 4.60 * $texts[0]['size']) / 2.0;
        $textTop = $texts[0]['y'] - $textHalfH;
        $textBottom = $texts[0]['y'] + $textHalfH;

        $this->assertGreaterThanOrEqual(39.0, $logoTop, 'Symbol ragt oben in die Ösen-Kerbe.');
        $this->assertLessThanOrEqual(63.0, $textBottom, 'Tiername ragt unten über die grüne Kontur.');
        $this->assertGreaterThanOrEqual(1.2, $textTop - $logoBottom, 'Symbol und Tiername überlappen sich.');

        // Rückseite enthält die Telefonnummer gespiegelt bei X = 59.0 (weicht gespiegelter Pfote bei X = 12..33 aus)
        $this->assertSame('3435353453', $textsBack[0]['text']);
        $this->assertEquals(59.0, $textsBack[0]['x']);
        $this->assertEquals(50.0, $textsBack[0]['y']);

        // Speichern in den Warenkorb übernimmt Vorder- UND Rückseite 1:1
        $comp
            ->set('config_confirmed', true)
            ->call('save', app(CartService::class));

        $cart = app(CartService::class)->getCart();
        $cartItem = $cart->items()->where('product_id', $dogTag->id)->latest()->first();
        $this->assertNotNull($cartItem);
        $this->assertSame('adwadwada', $cartItem->configuration['texts'][0]['text'] ?? null);
        $this->assertSame('3435353453', $cartItem->configuration['texts_back'][0]['text'] ?? null);
        $this->assertSame('back', $cartItem->configuration['custom_field_sides']['cf_notfallnummer'] ?? null);
        $this->assertStringContainsString('(Rückseite): 3435353453', $cartItem->configuration['text'] ?? '');
    }

    #[Test]
    public function bone_dog_tag_auto_scales_all_three_elements_collision_free_when_customer_switches_phone_to_front(): void
    {
        $dogTag = $this->createBoneDogTagProduct();

        $comp = Livewire::test(ProductConfigurator::class, [
            'product' => $dogTag,
            'context' => 'add',
            'initialData' => ['personalization_mode' => 'custom_fields'],
        ]);

        $comp
            ->set('customFieldValues.cf_wunschsymbol', 'Herz ♥')
            ->set('customFieldValues.cf_tiername', 'adwadwada')
            ->set('customFieldValues.cf_notfallnummer', '3435353453')
            ->call('setCustomFieldSide', 'cf_notfallnummer', 'front');

        $this->assertSame('front', $comp->get('customFieldSides.cf_notfallnummer'));
        $this->assertSame('front', $comp->get('activeSide'));
        $this->assertEmpty($comp->get('texts_back'));

        $logos = $comp->get('logos');
        $texts = $comp->get('texts');
        $this->assertCount(1, $logos);
        $this->assertCount(1, $texts);
        $this->assertSame("adwadwada\n3435353453", $texts[0]['text']);

        // Smart-Fit hat Symbol (< 58) und 2-zeiligen Text (< 1.67) automatisch herunterskaliert
        $this->assertLessThan(58.0, $logos[0]['size']);
        $this->assertLessThan(1.67, $texts[0]['size']);

        $logoHalfH = ($logos[0]['size'] * 0.19) / 2.0;
        $logoTop = $logos[0]['y'] - $logoHalfH;
        $logoBottom = $logos[0]['y'] + $logoHalfH;

        $textHalfH = (2 * 4.60 * $texts[0]['size']) / 2.0;
        $textTop = $texts[0]['y'] - $textHalfH;
        $textBottom = $texts[0]['y'] + $textHalfH;

        $this->assertGreaterThanOrEqual(39.0, $logoTop, 'Symbol ragt bei 3 Elementen oben über die Kontur.');
        $this->assertLessThanOrEqual(63.0, $textBottom, '2-zeiliger Text ragt unten über die Knochen-Taille.');
        $this->assertGreaterThanOrEqual(1.2, $textTop - $logoBottom, 'Symbol und 2-zeiliger Text kollidieren.');
    }

    #[Test]
    public function future_products_with_narrow_circle_or_custom_contour_auto_scale_long_names_and_center_without_symbol(): void
    {
        $narrowMedal = Product::create([
            'name' => 'Zukunfts-Medaille Schmal',
            'slug' => 'zukunfts-medaille-schmal',
            'sku' => 'MSF-FUTURE-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 3990,
            'is_personalizable' => true,
            'completion_step' => 4,
            'preview_image_path' => 'produkte/products/seelen-talisman/overlay.png',
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'allow_logo' => true,
                'has_back_side' => false,
                'area_shape' => 'circle',
                'area_left' => 36.0,
                'area_top' => 38.0,
                'area_width' => 28.0,
                'area_height' => 24.0,
            ],
        ]);

        $comp = Livewire::test(ProductConfigurator::class, [
            'product' => $narrowMedal,
            'context' => 'add',
            'initialData' => ['personalization_mode' => 'custom_fields'],
        ]);

        // 1. Mit Symbol + langem Doppelnamen + Datum in breiter Schriftart (Cinzel)
        $comp
            ->set('customFieldValues.cf_wunschsymbol', 'Stern ★')
            ->set('customFieldValues.cf_wunschtext', "Maximilian-Alexander\n14.08.2026")
            ->set('customFieldValues.cf_schriftart', 'Cinzel');

        $logos = $comp->get('logos');
        $texts = $comp->get('texts');
        $this->assertCount(1, $logos);
        $this->assertCount(1, $texts);

        // Prüfe horizontalen Fit in die schmale Kreisbreite (28%) und vertikalen Fit (38..62%)
        $estTextWidthPct = (20 * 4.0 * 0.62 * $texts[0]['size']) + 1.6;
        $this->assertLessThanOrEqual(26.0, $estTextWidthPct, 'Langer Name ragt seitlich über die Kreis-Kontur.');

        $logoBottom = $logos[0]['y'] + (($logos[0]['size'] * 0.19) / 2.0);
        $textTop = $texts[0]['y'] - ((2 * 4.60 * $texts[0]['size']) / 2.0);
        $textBottom = $texts[0]['y'] + ((2 * 4.60 * $texts[0]['size']) / 2.0);
        $this->assertGreaterThan($logoBottom, $textTop);
        $this->assertLessThanOrEqual(61.5, $textBottom);

        // 2. "Kein Symbol" -> Text rückt optisch exakt in die Kreismitte (Y = 50.0)
        $comp->set('customFieldValues.cf_wunschsymbol', 'Kein Symbol');
        $this->assertEmpty($comp->get('logos'));
        $centeredTexts = $comp->get('texts');
        $this->assertEquals(50.0, $centeredTexts[0]['y']);
    }
}
