<?php

namespace App\Livewire\Shop\Map;

use App\Models\Map\MapEdge;
use App\Models\Map\MapNode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class CompanyMap extends Component
{
    public $nodes = [];
    public $edges = [];
    public $apiStatuses = []; // Speichert den Status der Pings (up/down)

    public $showNodeForm = false;
    public $newNode = ['label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];

    public $showEditForm = false;
    public $editNode = ['id' => '', 'label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];

    public $showEdgeForm = false;
    public $newEdge = ['source_id' => '', 'target_id' => '', 'label' => '', 'description' => '', 'status' => 'active'];

    public $showNodePanel = false;
    public $activePanelNode = null;

    public $activeMap = 'erp'; // 'erp' oder 'ai'
    public $liveAiState = null; // Speichert Echtzeit Cache Status



    // Neues Feature: Environment Status für das Panel
    public $envStatus = [];

    public function mount()
    {
        // One-time auto-migration of map architecture (User complained about mixed-up coordinates from previous seed)
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\Seeders\MapSeeder', '--force' => true]);

        if (!\Illuminate\Support\Facades\Schema::hasColumn('map_nodes', 'map_id')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        $this->loadMap();
    }

    public function loadMap()
    {
        $this->nodes = MapNode::where('map_id', $this->activeMap)->get()->toArray();
        $this->edges = MapEdge::where('map_id', $this->activeMap)->get()->toArray();
    }

    public function switchMap($mapId)
    {
        $this->activeMap = $mapId;
        $this->closeNodePanel();
        $this->loadMap();
    }

    // --- NEU: AI LIVE STATE CHECK (1s Poll) ---
    public function pollAiState()
    {
        if ($this->activeMap === 'ai') {
            $this->liveAiState = \Illuminate\Support\Facades\Cache::get('ai_live_state', [
                'active_node' => null,
                'action_text' => 'Bereit für Spracheingabe...',
                'pulse_color' => 'gray'
            ]);
            $this->dispatch('ai-state-updated', state: $this->liveAiState);
        }
    }
    // --- NEU: API PING CHECK & LOGGING ---
    public function checkApiStatuses()
    {
        $this->apiStatuses = [];
        $errorCount = 0;

        foreach ($this->nodes as $node) {
            if (!empty($node['link']) && Str::startsWith($node['link'], 'http')) {
                try {
                    $response = Http::timeout(3)->get($node['link']);
                    // 401/403 bedeuten, die API ist da, wir haben nur keine Auth im GET - also "UP"
                    $isUp = $response->successful() || in_array($response->status(), [401, 403, 404, 405]);

                    $this->apiStatuses[$node['id']] = $isUp ? 'up' : 'down';

                    if (!$isUp) {
                        $this->writeGlobalLog("API Error: {$node['label']} ist nicht erreichbar (Code: {$response->status()}).", 'error');
                        $errorCount++;
                    }
                } catch (\Exception $e) {
                    $this->apiStatuses[$node['id']] = 'down';
                    $this->writeGlobalLog("API Timeout: {$node['label']} antwortet nicht.", 'error');
                    $errorCount++;
                }
            }
        }

        if ($errorCount === 0) {
            $this->writeGlobalLog("System-Check: Alle konfigurierten APIs sind erreichbar.", 'success');
        }

        $this->dispatch('apis-checked');
    }

    private function writeGlobalLog($message, $type = 'info')
    {
        try {
            // Speichere in DB, falls Model existiert (basiert auf deinen anderen Dateien)
            if (class_exists(\App\Models\Global\GlobalLog::class)) {
                \App\Models\Global\GlobalLog::create([
                    'title'   => 'Architektur Monitor',
                    'message' => $message,
                    'type'    => $type,
                    'action_id' => 'api:ping'
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Log Fehler: " . $e->getMessage());
        }
        // Toast ans Frontend senden
        $this->dispatch('toast', message: $message, type: $type);
    }
    // -------------------------------------

    public function updateNodePosition($nodeId, $x, $y)
    {
        $node = MapNode::find($nodeId);
        if ($node) {
            $node->update(['pos_x' => round($x, 2), 'pos_y' => round($y, 2)]);
        }
    }

    public function createNode()
    {
        $this->validate(['newNode.label' => 'required|string|max:255', 'newNode.link' => 'nullable|url']);
        MapNode::create(array_merge($this->newNode, ['id' => Str::uuid(), 'map_id' => $this->activeMap, 'pos_x' => 10, 'pos_y' => 10]));
        $this->showNodeForm = false;
        $this->newNode = ['label' => '', 'type' => 'default', 'status' => 'active', 'icon' => 'cube', 'description' => '', 'link' => '', 'component_key' => ''];
        $this->loadMap();
    }

    public function openEditForm($nodeId)
    {
        $node = MapNode::find($nodeId);
        if (!$node) return;
        $this->editNode = $node->toArray();
        $this->showEditForm = true;
    }

    public function updateNode()
    {
        $this->validate(['editNode.label' => 'required|string|max:255', 'editNode.link' => 'nullable|url']);
        $node = MapNode::find($this->editNode['id']);
        if ($node) $node->update($this->editNode);
        $this->showEditForm = false;
        $this->loadMap();
    }

    public function openNodePanel($nodeId)
    {
        $node = MapNode::find($nodeId);
        if (!$node) return;

        $this->activePanelNode = $node->toArray();
        $this->checkEnvironmentVars($node->icon); // Prüft auf verbundene ENV Keys
        $this->showNodePanel = true;
    }

    public function closeNodePanel()
    {
        $this->showNodePanel = false;
        $this->activePanelNode = null;
    }

    private function checkEnvironmentVars($icon)
    {
        // Ein nützliches Feature: Prüft ob die ENV Variablen für den Dienst gesetzt sind.
        $keys = match($icon) {
            'stripe' => ['STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET'],
            'etsy'   => ['ETSY_API_KEY', 'ETSY_SHOP_ID'],
            'google' => ['GOOGLE_CLIENT_ID', 'GOOGLE_MAPS_API_KEY'],
            'dhl'    => ['DHL_API_KEY', 'DHL_USER'],
            default  => []
        };

        $this->envStatus = [];
        foreach($keys as $key) {
            $this->envStatus[$key] = env($key) ? true : false;
        }
    }

    public function createEdge()
    {
        $this->validate([
            'newEdge.source_id' => 'required|exists:map_nodes,id',
            'newEdge.target_id' => 'required|exists:map_nodes,id|different:newEdge.source_id',
        ]);
        MapEdge::create(array_merge($this->newEdge, ['id' => Str::uuid(), 'map_id' => $this->activeMap]));
        $this->showEdgeForm = false;
        $this->newEdge = ['source_id' => '', 'target_id' => '', 'label' => '', 'description' => '', 'status' => 'active'];
        $this->loadMap();
    }

    public function deleteNode($id) { MapNode::destroy($id); $this->loadMap(); }
    public function deleteEdge($id) { MapEdge::destroy($id); $this->loadMap(); }

    public function render()
    {
        return view('livewire.shop.map.company-map');
    }
}
