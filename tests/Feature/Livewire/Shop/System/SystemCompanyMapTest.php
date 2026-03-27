<?php

namespace Tests\Feature\Livewire\Shop\System;

use App\Livewire\Shop\System\SystemCompanyMap;
use App\Models\System\SystemMapEdge;
use App\Models\System\SystemMapNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemCompanyMapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_map_component_and_loads_erp_map()
    {
        // Seeding a test node
        $node = SystemMapNode::create([
            'map_id' => 'erp',
            'label' => 'Test ERP Node',
            'type' => 'core',
            'status' => 'active',
            'pos_x' => 10,
            'pos_y' => 10
        ]);

        Livewire::test(SystemCompanyMap::class)
            ->assertStatus(200)
            ->assertSet('activeMap', 'erp')
            ->assertCount('nodes', 1);
    }

    #[Test]
    public function it_can_switch_maps_and_reload_nodes()
    {
        SystemMapNode::create([
            'map_id' => 'erp',
            'label' => 'ERP Node',
            'type' => 'core',
        ]);
        
        SystemMapNode::create([
            'map_id' => 'ai',
            'label' => 'AI Node',
            'type' => 'core',
        ]);

        Livewire::test(SystemCompanyMap::class)
            ->assertCount('nodes', 1)
            ->call('switchMap', 'ai')
            ->assertSet('activeMap', 'ai')
            ->assertCount('nodes', 1);
    }

    #[Test]
    public function it_validates_and_creates_a_new_node()
    {
        Livewire::test(SystemCompanyMap::class)
            ->set('newNode.label', '') // Empty label
            ->call('createNode')
            ->assertHasErrors(['newNode.label' => 'required']);

        Livewire::test(SystemCompanyMap::class)
            ->set('newNode.label', 'New Stripe API')
            ->set('newNode.link', 'https://api.stripe.com')
            ->set('newNode.icon', 'stripe')
            ->call('createNode')
            ->assertHasNoErrors()
            ->assertSet('showNodeForm', false);

        $this->assertDatabaseHas('system_map_nodes', [
            'label' => 'New Stripe API',
            'link' => 'https://api.stripe.com',
            'icon' => 'stripe',
            'map_id' => 'erp'
        ]);
    }

    #[Test]
    public function it_can_open_edit_form_and_update_a_node()
    {
        $node = SystemMapNode::create([
            'map_id' => 'erp',
            'label' => 'Old Label',
            'type' => 'core'
        ]);

        Livewire::test(SystemCompanyMap::class)
            ->call('openEditForm', $node->id)
            ->assertSet('showEditForm', true)
            ->assertSet('editNode.label', 'Old Label')
            ->set('editNode.label', 'Updated Label')
            ->call('updateNode')
            ->assertSet('showEditForm', false);

        $this->assertDatabaseHas('system_map_nodes', [
            'id' => $node->id,
            'label' => 'Updated Label'
        ]);
    }

    #[Test]
    public function it_can_delete_a_node_and_cascade_edges()
    {
        $node1 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Source']);
        $node2 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Target']);
        
        $edge = SystemMapEdge::create([
            'map_id' => 'erp',
            'source_id' => $node1->id,
            'target_id' => $node2->id,
            'label' => 'Connection'
        ]);

        Livewire::test(SystemCompanyMap::class)
            ->call('deleteNode', $node1->id);

        $this->assertDatabaseMissing('system_map_nodes', ['id' => $node1->id]);
        $this->assertDatabaseMissing('system_map_edges', ['id' => $edge->id]);
    }

    #[Test]
    public function it_validates_and_creates_edges_between_nodes()
    {
        $node1 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Source']);
        $node2 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Target']);

        Livewire::test(SystemCompanyMap::class)
            ->set('newEdge.source_id', $node1->id)
            ->set('newEdge.target_id', $node1->id) // Same node
            ->call('createEdge')
            ->assertHasErrors(['newEdge.target_id']);

        Livewire::test(SystemCompanyMap::class)
            ->set('newEdge.source_id', $node1->id)
            ->set('newEdge.target_id', $node2->id)
            ->set('newEdge.label', 'Data Flow')
            ->call('createEdge')
            ->assertHasNoErrors()
            ->assertSet('showEdgeForm', false);

        $this->assertDatabaseHas('system_map_edges', [
            'source_id' => $node1->id,
            'target_id' => $node2->id,
            'label' => 'Data Flow'
        ]);
    }

    #[Test]
    public function it_can_delete_an_edge()
    {
        $node1 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Source']);
        $node2 = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Target']);
        $edge = SystemMapEdge::create(['map_id' => 'erp', 'source_id' => $node1->id, 'target_id' => $node2->id]);

        Livewire::test(SystemCompanyMap::class)
            ->call('deleteEdge', $edge->id);

        $this->assertDatabaseMissing('system_map_edges', ['id' => $edge->id]);
    }

    #[Test]
    public function it_updates_node_cartesian_coordinates_via_javascript_event()
    {
        $node = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Floating Node', 'pos_x' => 10, 'pos_y' => 10]);

        Livewire::test(SystemCompanyMap::class)
            ->call('updateNodePosition', $node->id, 45.5, 76.2);

        $this->assertDatabaseHas('system_map_nodes', [
            'id' => $node->id,
            'pos_x' => 45.5,
            'pos_y' => 76.2
        ]);
    }

    #[Test]
    public function it_polls_live_ai_state()
    {
        \Illuminate\Support\Facades\Cache::put('ai_live_state', ['test_state' => true]);

        Livewire::test(SystemCompanyMap::class)
            ->call('switchMap', 'ai')
            ->call('pollAiState')
            ->assertDispatched('ai-state-updated', state: ['test_state' => true]);
    }

    #[Test]
    public function it_pings_external_apis_and_emits_statuses()
    {
        Http::fake([
            'https://up.com' => Http::response('OK', 200),
            'https://down.com' => Http::response('Error', 500),
        ]);

        SystemMapNode::create(['map_id' => 'erp', 'label' => 'Working API', 'link' => 'https://up.com']);
        $badNode = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Broken API', 'link' => 'https://down.com']);

        Livewire::test(SystemCompanyMap::class)
            ->call('checkApiStatuses')
            ->assertDispatched('apis-checked')
            ->assertDispatched('toast', type: 'error') // Because one failed
            ->assertSet('apiStatuses.'.$badNode->id, 'down');
    }

    #[Test]
    public function it_opens_and_closes_the_node_informational_panel()
    {
        $node = SystemMapNode::create(['map_id' => 'erp', 'label' => 'Stripe Gateway', 'icon' => 'stripe']);

        Livewire::test(SystemCompanyMap::class)
            ->call('openNodePanel', $node->id)
            ->assertSet('showNodePanel', true)
            ->assertSet('activePanelNode.label', 'Stripe Gateway')
            ->call('closeNodePanel')
            ->assertSet('showNodePanel', false)
            ->assertSet('activePanelNode', null);
    }
}
