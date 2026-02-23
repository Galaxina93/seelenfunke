<?php

namespace App\Livewire\Shop\Funki;

use Livewire\Component;
use App\Models\Funki\FunkiMapNode;
use App\Models\Funki\FunkiMapEdge;
use Illuminate\Support\Str;

class FunkiCompanyMap extends Component
{
    public $nodes = [];
    public $edges = [];

    // Formular-State für neuen Knoten
    public $showNodeForm = false;
    public $newNode = ['label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];

    // Formular-State für Knoten bearbeiten
    public $showEditForm = false;
    public $editNode = ['id' => '', 'label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];

    // Formular-State für neue Verbindung
    public $showEdgeForm = false;
    public $newEdge = ['source_id' => '', 'target_id' => '', 'label' => '', 'description' => '', 'status' => 'active'];

    // Node Panel (intelligente Datenanzeige)
    public $showNodePanel = false;
    public $activePanelNode = null;

    public function mount()
    {
        $this->loadMap();
    }

    public function loadMap()
    {
        $this->nodes = FunkiMapNode::all()->toArray();
        $this->edges = FunkiMapEdge::all()->toArray();
    }

    public function updateNodePosition($nodeId, $x, $y)
    {
        $node = FunkiMapNode::find($nodeId);
        if ($node) {
            $node->update([
                'pos_x' => round($x, 2),
                'pos_y' => round($y, 2)
            ]);
        }
    }

    public function createNode()
    {
        $this->validate([
            'newNode.label' => 'required|string|max:255',
            'newNode.link'  => 'nullable|url',
        ]);

        FunkiMapNode::create([
            'id'            => Str::uuid(),
            'label'         => $this->newNode['label'],
            'type'          => $this->newNode['type'],
            'status'        => $this->newNode['status'],
            'icon'          => $this->newNode['icon'],
            'description'   => $this->newNode['description'],
            'link'          => $this->newNode['link'],
            'component_key' => $this->newNode['component_key'],
            'pos_x'         => 10,
            'pos_y'         => 10,
        ]);

        $this->showNodeForm = false;
        $this->newNode = ['label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];
        $this->loadMap();
    }

    public function openEditForm($nodeId)
    {
        $node = FunkiMapNode::find($nodeId);
        if (!$node) return;

        $this->editNode = [
            'id'            => $node->id,
            'label'         => $node->label,
            'type'          => $node->type,
            'status'        => $node->status,
            'icon'          => $node->icon,
            'description'   => $node->description,
            'link'          => $node->link,
            'component_key' => $node->component_key ?? '',
        ];
        $this->showEditForm = true;
    }

    public function updateNode()
    {
        $this->validate([
            'editNode.label' => 'required|string|max:255',
            'editNode.link'  => 'nullable|url',
        ]);

        $node = FunkiMapNode::find($this->editNode['id']);
        if ($node) {
            $node->update([
                'label'         => $this->editNode['label'],
                'type'          => $this->editNode['type'],
                'status'        => $this->editNode['status'],
                'icon'          => $this->editNode['icon'],
                'description'   => $this->editNode['description'],
                'link'          => $this->editNode['link'],
                'component_key' => $this->editNode['component_key'],
            ]);
        }

        $this->showEditForm = false;
        $this->editNode = ['id' => '', 'label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];
        $this->loadMap();
    }

    public function openNodePanel($nodeId)
    {
        $node = FunkiMapNode::find($nodeId);
        if (!$node) return;

        $this->activePanelNode = $node->toArray();
        $this->showNodePanel   = true;
    }

    public function closeNodePanel()
    {
        $this->showNodePanel   = false;
        $this->activePanelNode = null;
    }

    public function createEdge()
    {
        $this->validate([
            'newEdge.source_id' => 'required|exists:funki_map_nodes,id',
            'newEdge.target_id' => 'required|exists:funki_map_nodes,id|different:newEdge.source_id',
        ]);

        FunkiMapEdge::create([
            'id'          => Str::uuid(),
            'source_id'   => $this->newEdge['source_id'],
            'target_id'   => $this->newEdge['target_id'],
            'label'       => $this->newEdge['label'],
            'description' => $this->newEdge['description'],
            'status'      => $this->newEdge['status'],
        ]);

        $this->showEdgeForm = false;
        $this->newEdge = ['source_id' => '', 'target_id' => '', 'label' => '', 'description' => '', 'status' => 'active'];
        $this->loadMap();
    }

    public function deleteNode($id)
    {
        FunkiMapNode::destroy($id);
        $this->loadMap();
    }

    public function deleteEdge($id)
    {
        FunkiMapEdge::destroy($id);
        $this->loadMap();
    }

    public function render()
    {
        return view('livewire.shop.funki.funki-company-map');
    }
}
