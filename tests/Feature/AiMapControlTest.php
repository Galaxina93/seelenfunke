<?php

namespace Tests\Feature\Ai;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiWorkspaceTask;
use App\Services\AI\Functions\AiMapControlFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AiMapControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_map_control_toggle_focus_function_returns_correct_frontend_event()
    {
        $args = ['active' => true];
        $result = AiMapControlFuncs::executeMapToggleMapfocus($args);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('_frontend_event', $result);
        $this->assertEquals('toggle-mapfocus', $result['_frontend_event']['name']);
        $this->assertTrue($result['_frontend_event']['detail']['active']);
    }

    public function test_ai_map_control_toggle_livedata_function_returns_correct_frontend_event()
    {
        $args = ['active' => true];
        $result = AiMapControlFuncs::executeMapToggleLivedata($args);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('_frontend_event', $result);
        $this->assertEquals('toggle-livedata', $result['_frontend_event']['name']);
        $this->assertTrue($result['_frontend_event']['detail']['active']);
    }

    public function test_ai_map_control_search_and_fly_function_returns_correct_frontend_event()
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.mapbox.com/geocoding/*' => \Illuminate\Support\Facades\Http::response([
                'features' => [
                    [
                        'center' => [13.404954, 52.520008],
                        'place_name' => 'Berlin, Germany',
                        'place_type' => ['place']
                    ]
                ]
            ], 200)
        ]);

        putenv('MAPBOX_TOKEN=testing_fake_token');

        $args = ['query' => 'Berlin'];
        $result = AiMapControlFuncs::executeMapSearchAndFly($args);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('_frontend_events', $result);
        
        $hasFlyEvent = collect($result['_frontend_events'])->contains(function($event) {
            return $event['name'] === 'map-fly-to' && $event['detail']['markerText'] === 'Berlin';
        });
        
        $this->assertTrue($hasFlyEvent);
    }
}
