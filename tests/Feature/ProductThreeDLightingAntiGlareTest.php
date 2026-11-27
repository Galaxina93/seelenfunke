<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Services\ConfiguratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression: 3D-Produktmanager & Live-Konfigurator Beleuchtungs-Kalibrierung für den
 * schwarz eloxierten Seelenanhänger (Herz), damit schwarze Flächen nicht überstrahlt werden,
 * Kanten-/Silber-Lichteffekte erhalten bleiben und Admin + Frontend/Bestellvorschau 1:1 paritätisch laufen.
 */
class ProductThreeDLightingAntiGlareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function configurator_service_resolves_anti_glare_grazing_studio_profile_for_seelenanhaenger(): void
    {
        $service = app(ConfiguratorService::class);

        $darkProfile = $service->resolveThreeDLightingProfile([
            'template_package'               => 'seelen-anhaenger',
            'material_type'                  => 'metal',
            'xtool_photo_substrate_polarity' => 'white_on_dark',
            'lighting_preset'                => 'auto',
        ]);

        $this->assertSame('dark_metal_studio', $darkProfile['preset']);
        $this->assertTrue($darkProfile['is_dark_metal']);
        $this->assertLessThanOrEqual(0.30, $darkProfile['ambient_intensity']);
        $this->assertGreaterThanOrEqual(0.70, $darkProfile['material_metalness']);
        $this->assertLessThanOrEqual(0.30, $darkProfile['material_env_intensity']);

        // Streiflicht-Geometrie: seitlicher X/Y-Winkel >= 14, kleiner Z-Anteil <= 5 verhindert Frontal-Überstrahlung
        $this->assertGreaterThanOrEqual(14, abs($darkProfile['key_light']['pos'][0]));
        $this->assertLessThanOrEqual(5.0, abs($darkProfile['key_light']['pos'][2]));
        $this->assertGreaterThanOrEqual(14, abs($darkProfile['back_key_light']['pos'][0]));
        $this->assertLessThanOrEqual(5.0, abs($darkProfile['back_key_light']['pos'][2]));

        // Edelmetall & Kristall behalten ihre eigenen Studio-Profile
        $goldProfile = $service->resolveThreeDLightingProfile([
            'template_package' => 'goldenes-seelen-herz',
            'material_type'    => 'metal',
            'lighting_preset'  => 'auto',
        ]);
        $this->assertSame('precious_metal_studio', $goldProfile['preset']);
        $this->assertFalse($goldProfile['is_dark_metal']);

        $crystalProfile = $service->resolveThreeDLightingProfile([
            'template_package' => 'seelen-kristall',
            'material_type'    => 'glass',
            'lighting_preset'  => 'auto',
        ]);
        $this->assertSame('crystal_studio', $crystalProfile['preset']);
        $this->assertFalse($crystalProfile['is_dark_metal']);
    }

    #[Test]
    public function configurator_service_clamps_lighting_overrides_and_rejects_unknown_presets(): void
    {
        $service = app(ConfiguratorService::class);

        $merged = $service->mergeWithDefaults([
            'lighting_preset'   => 'invalid_hacked_preset',
            'lighting_exposure' => 99.9,
            'lighting_ambient'  => -5.0,
            'lighting_specular' => 'not-a-number',
        ]);

        $this->assertSame('auto', $merged['lighting_preset']);
        $this->assertSame(1.8, $merged['lighting_exposure']);
        $this->assertSame(0.15, $merged['lighting_ambient']);
        $this->assertNull($merged['lighting_specular']);
    }

    #[Test]
    public function admin_3d_manager_and_frontend_configurator_share_calibrated_studio_helper(): void
    {
        $helper = (string) file_get_contents(resource_path('views/livewire/shop/product/partials/three_d_studio_helper.blade.php'));
        $adminCreate = (string) file_get_contents(resource_path('views/livewire/shop/product/product-create.blade.php'));
        $adminMain = (string) file_get_contents(resource_path('views/livewire/shop/product/partials/edit-partials/step_4_partials/three_js_main.blade.php'));
        $adminRanges = (string) file_get_contents(resource_path('views/livewire/shop/product/partials/edit-partials/step_4_partials/3d_model_config_ranges.blade.php'));
        $frontendConfig = (string) file_get_contents(resource_path('views/livewire/shop/product/product-configurator/product-configurator.blade.php'));
        $frontendEngine = (string) file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_2.blade.php'));
        $frontendBoot = (string) file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_1.blade.php'));

        $this->assertStringContainsString("livewire.shop.product.partials.three_d_studio_helper", $adminCreate);
        $this->assertStringContainsString("livewire.shop.product.partials.three_d_studio_helper", $frontendConfig);
        $this->assertStringContainsString('window.Seelenfunke3DStudio', $frontendBoot);

        // Helper stellt sRGB-Farbraum, ACES Filmic ToneMapping, PMREM und Dunkelmetall-Erkennung sicher
        $this->assertStringContainsString('THREE.ACESFilmicToneMapping', $helper);
        $this->assertStringContainsString('THREE.SRGBColorSpace', $helper);
        $this->assertStringContainsString('THREE.PMREMGenerator', $helper);
        $this->assertStringContainsString('isDarkTextureImage', $helper);
        $this->assertStringContainsString('dark_metal_studio', $helper);

        // Alle im 3D-Produktmanager-UI verlinkten Methoden müssen in three_js_main definiert sein
        foreach (['resolveLightingProfile', 'applyLighting', 'resetLighting'] as $method) {
            $this->assertStringContainsString($method . '(', $adminRanges);
            $this->assertStringContainsString($method . '(', $adminMain);
        }

        $this->assertStringContainsString('lightingPresets', $adminMain);
        $this->assertStringContainsString('lightingPresets', $frontendConfig);
        $this->assertStringContainsString('cfg.lightingPresets', $frontendEngine);
        $this->assertStringContainsString('dark_metal', $frontendEngine);
    }

    #[Test]
    public function product_configurator_preview_renders_lighting_presets_for_seelenanhaenger(): void
    {
        $product = Product::create([
            'id'                  => (string) Str::uuid(),
            'name'                => 'Der Seelenanhänger',
            'slug'                => 'seelen-anhaenger',
            'type'                => 'physical',
            'price'               => 2990,
            'status'              => 'active',
            'is_personalizable'   => true,
            'three_d_active'      => true,
            'configurator_settings' => [
                'template_package'               => 'seelen-anhaenger',
                'material_type'                  => 'metal',
                'overlay_type'                   => 'plane',
                'has_back_side'                  => true,
                'xtool_photo_substrate_polarity' => 'white_on_dark',
                'lighting_preset'                => 'auto',
            ],
        ]);

        $customer = Customer::create([
            'id'         => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name'  => 'Steinhauer',
            'email'      => 'lighting-test@example.de',
            'password'   => bcrypt('Secret!123'),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(ProductConfigurator::class, [
                'product' => $product->id,
                'context' => 'preview',
            ])
            ->assertStatus(200)
            ->assertSee('dark_metal_studio', false)
            ->assertSee('isDarkSurface: true', false);
    }
}
