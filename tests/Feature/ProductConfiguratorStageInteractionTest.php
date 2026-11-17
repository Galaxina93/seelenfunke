<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use Livewire\Attributes\Renderless;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression: Symbol hinzufügen (doppelte Werkzeugleiste, Symbol nicht verschiebbar, Doppel-Selektion)
 * sowie zuckendes Textfeld beim langsamen Größerziehen im 2D-Studio.
 */
class ProductConfiguratorStageInteractionTest extends TestCase
{
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
}
