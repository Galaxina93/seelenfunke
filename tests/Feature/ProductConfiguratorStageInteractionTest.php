<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression: Symbol hinzufügen (doppelte Werkzeugleiste, Symbol nicht verschiebbar, Doppel-Selektion),
 * zuckendes Textfeld beim langsamen Größerziehen sowie Alpine x-show/:style Kollision (Punkt 5 & Punkt 6).
 */
class ProductConfiguratorStageInteractionTest extends TestCase
{
    use RefreshDatabase;

    private function partial(string $name): string
    {
        return (string) file_get_contents(resource_path('views/livewire/shop/product/product-configurator/partials/' . $name));
    }

    #[Test]
    public function every_referenced_stage_helper_is_defined_in_the_alpine_engine(): void
    {
        $js = $this->partial('scripts_frontend_1.blade.php');
        $markup = $this->partial('preview.blade.php') . $this->partial('formluar.blade.php');

        foreach (['applySymbolToStage', 'getInteractiveStageZIndex', 'setSelectedElementSize'] as $fn) {
            $this->assertStringContainsString($fn . '(', $markup, "{$fn} wird im Markup genutzt");
            $this->assertMatchesRegularExpression('/^\s+' . $fn . '\(/m', $js, "{$fn} muss in der Alpine-Engine definiert sein");
        }

        $this->assertStringNotContainsString('wire:click="addStandardVector', $this->partial('formluar.blade.php'));
    }

    #[Test]
    public function floating_toolbar_is_shielded_from_livewire_morphing(): void
    {
        $preview = $this->partial('preview.blade.php');
        $this->assertMatchesRegularExpression('/<div wire:ignore\s+x-show="showDrawingBoard[^"]*selectedIndex !== null \|\| isCropping\)"/', $preview);
        $this->assertStringNotContainsString('<template x-if="!isCropping && selectedIndex !== null">', $preview);
    }

    #[Test]
    public function text_resize_uses_a_single_deterministic_measurement_source(): void
    {
        $js = $this->partial('scripts_frontend_1.blade.php');

        // fitTextarea darf während eines aktiven Text-Resize nicht per DOM-scrollWidth gegen die :style-Bindung messen
        $this->assertStringContainsString("if (this.isResizing && item && this.currentElement && this.currentElement.type === 'text')", $js);
        // Canvas-Maß und DOM-Maß enthalten beide das 2x10px Padding (keine 20px-Sprünge zwischen den Quellen)
        $this->assertStringContainsString('let widthPx = Math.max(60, canvasWidth + 20 + 24);', $js);
        $this->assertStringContainsString('let newWidth = canvasWidth + 20;', $js);
        // Resize-/Rotations-Anker identisch zu startAction (kein Sprung bei fehlendem x/y)
        $this->assertStringContainsString('Number(item.x ?? this.areaCenterX) / 100 * rect.width', $js);
        $this->assertStringNotContainsString('(item.x || 50) / 100 * rect.width', $js);
        // Nach dem Loslassen einmal pixelgenau nachmessen
        $this->assertStringContainsString('if (wasTextResize) {', $js);
    }

    #[Test]
    public function silent_symbol_tracking_is_renderless(): void
    {
        $method = new ReflectionMethod(ProductConfigurator::class, 'trackSymbolAddedSilent');
        $this->assertNotEmpty($method->getAttributes(Renderless::class));
    }

    #[Test]
    public function every_x_show_with_dynamic_style_binding_preserves_display_none_and_crop_ghost_uses_x_if(): void
    {
        $dir = resource_path('views/livewire/shop/product/product-configurator');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        $checkedCollisions = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $tags = $this->extractHtmlOpeningTags($content);

            foreach ($tags as $tagInfo) {
                $tag = $tagInfo['tag'];
                $hasXShow = str_contains($tag, 'x-show=');
                $hasDynamicStyle = str_contains($tag, ':style=') || str_contains($tag, 'x-bind:style=');

                if ($hasXShow && $hasDynamicStyle) {
                    $checkedCollisions++;
                    $normalizedTag = strtolower(preg_replace('/\s+/', '', $tag) ?? $tag);
                    $this->assertStringContainsString(
                        'display:none',
                        $normalizedTag,
                        sprintf(
                            'Alpine Kollisionsgefahr in %s:%d: Ein Element mit x-show und :style muss display:none im :style-Fallback fuehren.',
                            $file->getFilename(),
                            $tagInfo['line']
                        )
                    );
                }
            }
        }

        $this->assertGreaterThanOrEqual(8, $checkedCollisions);

        $preview = $this->partial('preview.blade.php');
        $js = $this->partial('scripts_frontend_1.blade.php');

        // Punkt 5 & Punkt 6: Crop-Hintergrundbild existiert dank x-if ausserhalb des aktiven Zuschnitt-Modus gar nicht im DOM
        $this->assertStringContainsString(
            '<template x-if="isCropping && selectedType===\'logo\' && selectedIndex===index && context!==\'preview\'">',
            $preview
        );
        $this->assertStringNotContainsString(
            '<img x-show="isCropping && selectedType===\'logo\' && selectedIndex===index"',
            $preview
        );
        $this->assertStringContainsString('.logo-crop-wrapper img:not([data-snapshot-ignore])', $js);
    }

    #[Test]
    public function template_loading_and_preview_context_keep_single_logo_and_measure_real_custom_polygon_contrast(): void
    {
        $js = $this->partial('scripts_frontend_1.blade.php');

        // analyzeOverlayContrast darf nicht auf undefinierte this.areaLeft / this.areaTop zugreifen, sondern nutzt config & custom_points
        $this->assertStringNotContainsString('this.areaLeft ??', $js);
        $this->assertStringNotContainsString('this.areaTop ??', $js);
        $this->assertStringContainsString('this.config.custom_points', $js);
        $this->assertStringContainsString('this.isStagePointInsideArea(pxPct, pyPct)', $js);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Herz-Schlüsselanhänger Test',
            'slug' => 'seelen-anhaenger-test-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'price' => 1990,
            'is_personalizable' => true,
            'configurator_settings' => [
                'allow_logo' => true,
                'area_shape' => 'custom',
                'custom_points' => [
                    ['x' => 50, 'y' => 60],
                    ['x' => 68, 'y' => 55],
                    ['x' => 72, 'y' => 72],
                    ['x' => 50, 'y' => 91],
                    ['x' => 28, 'y' => 72],
                    ['x' => 32, 'y' => 55],
                ],
            ],
        ]);

        $templateConfig = [
            'text' => "Frohe Weihnachten!\nFamilie Mustermann",
            'background_theme' => 'winter',
            'texts' => [
                [
                    'id' => 'tpl_txt_1',
                    'text' => "Frohe Weihnachten!\nFamilie Mustermann",
                    'font' => 'Dancing Script',
                    'align' => 'center',
                    'x' => 50,
                    'y' => 74,
                    'size' => 0.68,
                    'rotation' => 0,
                ],
            ],
            'logos' => [
                [
                    'id' => 'tpl_logo_1',
                    'type' => 'vector',
                    'value' => 'vectors/Feiertage/weihnachtsbaum.svg',
                    'url' => '/storage/produkte/configurator/vectors/Feiertage/weihnachtsbaum.svg',
                    'x' => 58.5,
                    'y' => 63.0,
                    'size' => 42,
                    'rotation' => 0,
                ],
            ],
            'logo_path' => 'vectors/Feiertage/weihnachtsbaum.svg',
        ];

        // 1. Öffnen des Templates im Kunden-Konfigurator (context = 'add'): Exakt 1 Logo (keine Verdopplung aus logo_path)
        $addComp = Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'add',
            'initialData' => $templateConfig,
        ]);

        $addLogos = $addComp->get('logos');
        $this->assertCount(1, $addLogos);
        $this->assertSame('vectors/Feiertage/weihnachtsbaum.svg', $addLogos[0]['value']);
        $this->assertEquals(58.5, $addLogos[0]['x']);
        $this->assertEquals(63.0, $addLogos[0]['y']);
        $this->assertEquals(42, $addLogos[0]['size']);

        // 2. Öffnen derselben Konfiguration in den Manufaktur-Details (context = 'preview'): 1:1 identische 2D/3D-Koordinaten
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Kundin',
            'email' => 'preview.test@example.de',
            'password' => bcrypt('Secret!123'),
        ]);

        $previewComp = Livewire::actingAs($customer, 'customer')->test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'preview',
            'initialData' => $templateConfig,
        ]);

        $previewLogos = $previewComp->get('logos');
        $this->assertCount(1, $previewLogos);
        $this->assertSame($addLogos[0]['x'], $previewLogos[0]['x']);
        $this->assertSame($addLogos[0]['y'], $previewLogos[0]['y']);
        $this->assertSame($addLogos[0]['size'], $previewLogos[0]['size']);

        // 3. Doppelte Element-IDs werden in normalizeStageItemsList deterministisch dedupliziert
        $dupComp = Livewire::test(ProductConfigurator::class, [
            'product' => $product,
            'context' => 'add',
            'initialData' => [
                'logos' => [
                    ['id' => 'dup_id', 'type' => 'vector', 'value' => 'vectors/Feiertage/weihnachtsbaum.svg', 'x' => 45, 'y' => 65, 'size' => 40],
                    ['id' => 'dup_id', 'type' => 'vector', 'value' => 'vectors/Liebe/herz.svg', 'x' => 55, 'y' => 65, 'size' => 40],
                ],
            ],
        ]);

        $dedupLogos = $dupComp->get('logos');
        $this->assertCount(2, $dedupLogos);
        $this->assertNotSame($dedupLogos[0]['id'], $dedupLogos[1]['id']);
    }

    /**
     * @return array<int, array{tag: string, line: int}>
     */
    private function extractHtmlOpeningTags(string $content): array
    {
        $tags = [];
        $len = strlen($content);
        $i = 0;

        while ($i < $len) {
            if ($content[$i] === '<' && ($i + 1) < $len && (ctype_alpha($content[$i + 1]) || $content[$i + 1] === '_')) {
                $start = $i;
                $line = substr_count($content, "\n", 0, $start) + 1;
                $i++;
                $inQuote = null;
                $inBlade = 0;

                while ($i < $len) {
                    if ($inBlade > 0) {
                        if (substr($content, $i, 2) === '}}') {
                            $inBlade--;
                            $i += 2;
                            continue;
                        }
                        $i++;
                        continue;
                    }
                    if (substr($content, $i, 2) === '{{') {
                        $inBlade++;
                        $i += 2;
                        continue;
                    }
                    if ($inQuote !== null) {
                        if ($content[$i] === $inQuote && $content[$i - 1] !== '\\') {
                            $inQuote = null;
                        }
                        $i++;
                        continue;
                    }
                    if ($content[$i] === '"' || $content[$i] === "'") {
                        $inQuote = $content[$i];
                        $i++;
                        continue;
                    }
                    if ($content[$i] === '>') {
                        $i++;
                        break;
                    }
                    $i++;
                }

                $tags[] = [
                    'tag' => substr($content, $start, $i - $start),
                    'line' => $line,
                ];
            } else {
                $i++;
            }
        }

        return $tags;
    }
}
