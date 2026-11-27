<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductCreate;
use App\Models\Admin\Admin;
use App\Models\Product\Product;
use App\Services\ConfiguratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThreeDProductLightingCalibrationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function seelen_anhaenger_resolves_dark_metal_studio_lighting_without_frontal_overexposure(): void
    {
        $service = app(ConfiguratorService::class);

        $profile = $service->resolveThreeDLightingProfile([
            'template_package'               => 'seelen-anhaenger',
            'material_type'                  => 'metal',
            'xtool_photo_substrate_polarity' => 'white_on_dark',
            'lighting_preset'                => 'auto',
        ], 'produkte/products/seelen-anhaenger/overlay.png', false);

        $this->assertSame('dark_metal_studio', $profile['preset']);
        $this->assertTrue($profile['is_dark_metal']);
        $this->assertLessThanOrEqual(0.35, $profile['ambient_intensity']);
        $this->assertLessThan(0.95, $profile['tone_mapping_exposure']);
        $this->assertLessThanOrEqual(0.50, $profile['env_intensity']);

        // Key light must stay at a raking angle (small Z vs X/Y) so flat black faces stay black while rim light keeps highlights alive.
        $keyPos = $profile['key_light']['pos'];
        $this->assertLessThan($keyPos[0], $keyPos[2]);
        $this->assertGreaterThan(1.0, $profile['rim_light']['intensity']);
        $this->assertLessThan(0, $profile['rim_light']['pos'][2]);
    }

    #[Test]
    public function precious_metals_glass_and_wood_resolve_their_own_calibrated_studio_profiles(): void
    {
        $service = app(ConfiguratorService::class);

        $goldHeart = $service->resolveThreeDLightingProfile([
            'template_package' => 'goldenes-seelen-herz',
            'material_type'    => 'metal',
        ]);
        $this->assertSame('precious_metal_studio', $goldHeart['preset']);
        $this->assertFalse($goldHeart['is_dark_metal']);

        $silverBracelet = $service->resolveThreeDLightingProfile([
            'template_package' => 'id-armband-silber-herz',
            'material_type'    => 'silver',
        ]);
        $this->assertSame('precious_metal_studio', $silverBracelet['preset']);

        $crystal = $service->resolveThreeDLightingProfile([
            'template_package' => 'seelen-kristall',
            'material_type'    => 'glass',
        ]);
        $this->assertSame('crystal_studio', $crystal['preset']);

        $wood = $service->resolveThreeDLightingProfile([
            'template_package' => 'custom',
            'material_type'    => 'wood',
        ]);
        $this->assertSame('matte_studio', $wood['preset']);
    }

    #[Test]
    public function configurator_service_guards_invalid_lighting_material_and_path_inputs(): void
    {
        $service = app(ConfiguratorService::class);

        $merged = $service->mergeWithDefaults([
            'material_type'     => 'uranium_glow',
            'overlay_type'      => 'torus',
            'area_shape'        => 'hexagon',
            'lighting_preset'   => 'neon_blast',
            'lighting_exposure' => 99.5,
            'lighting_ambient'  => -5.0,
            'lighting_specular' => 'not-a-number',
            'model_scale'       => NAN,
            'area_width'        => -20,
            'custom_points'     => [
                ['x' => -10, 'y' => 150],
                ['x' => 'bad', 'y' => 50],
            ],
        ]);

        $this->assertSame('glass', $merged['material_type']);
        $this->assertSame('plane', $merged['overlay_type']);
        $this->assertSame('rect', $merged['area_shape']);
        $this->assertSame('auto', $merged['lighting_preset']);
        $this->assertSame(1.8, $merged['lighting_exposure']);
        $this->assertSame(0.15, $merged['lighting_ambient']);
        $this->assertNull($merged['lighting_specular']);
        $this->assertSame(100.0, $merged['model_scale']);
        $this->assertSame(1.0, $merged['area_width']);
        $this->assertSame([['x' => 0.0, 'y' => 100.0]], $merged['custom_points']);

        $traversalAnalysis = $service->analyzeOverlayImage('../../.env', [
            'template_package' => 'seelen-anhaenger',
            'material_type'    => 'metal',
            'area_width'       => 0,
            'area_height'      => -10,
        ]);
        $this->assertTrue($traversalAnalysis['is_dark_surface']);
        $this->assertTrue($traversalAnalysis['has_transparent_overlay']);
    }

    #[Test]
    public function three_d_engines_and_product_manager_controls_share_calibrated_studio_lighting_and_srgb_env(): void
    {
        $helper = file_get_contents(resource_path('views/livewire/shop/product/partials/three_d_studio_helper.blade.php'));
        $adminEngine = file_get_contents(resource_path('views/livewire/shop/product/partials/edit-partials/step_4_partials/three_js_main.blade.php'));
        $frontendEngine = file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/scripts_frontend_2.blade.php'));
        $frontendWrapper = file_get_contents(resource_path('views/livewire/shop/product/product-configurator/product-configurator.blade.php'));
        $rangesView = file_get_contents(resource_path('views/livewire/shop/product/partials/edit-partials/step_4_partials/3d_model_config_ranges.blade.php'));
        $seeder = file_get_contents(database_path('seeders/ProductSeeder.php'));

        $this->assertStringContainsString('SRGBColorSpace', $helper);
        $this->assertStringContainsString('ACESFilmicToneMapping', $helper);
        $this->assertStringContainsString('PMREMGenerator', $helper);
        $this->assertStringContainsString('dark_metal_studio', $helper);
        $this->assertStringContainsString('resolveLightingProfile', $helper);

        $this->assertStringContainsString('window.Seelenfunke3DStudio.applyStudioLighting', $adminEngine);
        $this->assertStringContainsString('window.Seelenfunke3DStudio.applyMaterialToModel', $adminEngine);
        $this->assertStringContainsString('resolveLightingProfile()', $adminEngine);
        $this->assertStringContainsString('applyLighting(', $adminEngine);

        $this->assertStringContainsString('window.Seelenfunke3DStudio.applyStudioLighting', $frontendEngine);
        $this->assertStringContainsString('window.Seelenfunke3DStudio.applyEnvironmentTexture', $frontendEngine);
        $this->assertStringContainsString('window.Seelenfunke3DStudio.applyMaterialToModel', $frontendEngine);
        $this->assertStringContainsString('lighting_preset:', $frontendWrapper);
        $this->assertStringContainsString('lighting_exposure:', $frontendWrapper);

        $this->assertStringContainsString('configSettings.lighting_preset', $rangesView);
        $this->assertStringContainsString('configSettings.lighting_exposure', $rangesView);
        $this->assertStringContainsString('configSettings.lighting_ambient', $rangesView);
        $this->assertStringContainsString('configSettings.lighting_specular', $rangesView);
        $this->assertStringContainsString("'lighting_preset' => 'dark_metal_studio'", $seeder);
    }

    #[Test]
    public function product_create_saves_and_clamps_lighting_calibration_settings(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $product = Product::create([
            'name'                    => 'Seelenanhänger Lighting Test',
            'slug'                    => 'seelen-anhaenger-lighting-' . Str::lower(Str::random(6)),
            'sku'                     => 'MSF-TEST-' . Str::upper(Str::random(6)),
            'type'                    => 'physical',
            'collection_line'         => 'manufaktur',
            'status'                  => 'draft',
            'price'                   => 2990,
            'is_personalizable'       => true,
            'three_d_active'          => true,
            'track_quantity'          => true,
            'quantity'                => 5,
            'continue_selling_when_out_of_stock' => false,
            'configurator_settings'   => [
                'template_package' => 'seelen-anhaenger',
                'material_type'    => 'metal',
                'lighting_preset'  => 'dark_metal_studio',
            ],
        ]);

        Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->set('configSettings.lighting_preset', 'dark_metal_studio')
            ->set('configSettings.lighting_exposure', 0.84)
            ->set('configSettings.lighting_ambient', 0.90)
            ->set('configSettings.lighting_specular', 1.15)
            ->call('save', false);

        $product->refresh();
        $saved = $product->configurator_settings;

        $this->assertSame('dark_metal_studio', $saved['lighting_preset'] ?? null);
        $this->assertSame(0.84, (float) ($saved['lighting_exposure'] ?? 0));
        $this->assertSame(0.90, (float) ($saved['lighting_ambient'] ?? 0));
        $this->assertSame(1.15, (float) ($saved['lighting_specular'] ?? 0));
        $this->assertSame('seelen-anhaenger', $saved['template_package'] ?? null);
    }
}
