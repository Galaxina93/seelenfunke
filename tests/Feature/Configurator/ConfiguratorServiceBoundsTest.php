<?php

namespace Tests\Feature\Configurator;

use App\Services\ConfiguratorService;
use Tests\TestCase;

class ConfiguratorServiceBoundsTest extends TestCase
{
    public function test_merge_with_defaults_clamps_coordinates_and_dimensions()
    {
        $service = new ConfiguratorService();

        $dangerousConfig = [
            'area_left' => -999,
            'area_top' => 250,
            'area_width' => 0,
            'area_height' => -50,
            'model_scale' => 0,
            'engraving_scale' => 50000,
            'custom_points' => [
                ['x' => -10, 'y' => 150],
                ['x' => 50, 'y' => 50],
            ],
        ];

        $merged = $service->mergeWithDefaults($dangerousConfig);

        // Coordinates clamped to 0..100
        $this->assertEquals(0, $merged['area_left']);
        $this->assertEquals(100, $merged['area_top']);

        // Width and height clamped to 1..100
        $this->assertEquals(1, $merged['area_width']);
        $this->assertEquals(1, $merged['area_height']);

        // Scale clamped to 10..1000
        $this->assertEquals(10, $merged['model_scale']);
        $this->assertEquals(1000, $merged['engraving_scale']);

        // Custom points clamped
        $this->assertEquals(0, $merged['custom_points'][0]['x']);
        $this->assertEquals(100, $merged['custom_points'][0]['y']);
        $this->assertEquals(50, $merged['custom_points'][1]['x']);
        $this->assertEquals(50, $merged['custom_points'][1]['y']);
    }

    public function test_merge_with_defaults_preserves_valid_settings()
    {
        $service = new ConfiguratorService();

        $validConfig = [
            'area_left' => 15,
            'area_top' => 20,
            'area_width' => 70,
            'area_height' => 60,
            'model_scale' => 150,
            'engraving_scale' => 90,
        ];

        $merged = $service->mergeWithDefaults($validConfig);

        $this->assertEquals(15, $merged['area_left']);
        $this->assertEquals(20, $merged['area_top']);
        $this->assertEquals(70, $merged['area_width']);
        $this->assertEquals(60, $merged['area_height']);
        $this->assertEquals(150, $merged['model_scale']);
        $this->assertEquals(90, $merged['engraving_scale']);
    }
}
