<?php

namespace App\Livewire\Global\Widgets;

use App\Models\FunkiLog;
use App\Services\AiSupportService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/* Models für System-Health & Quick Actions */
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Product\Product;

class Funki extends Component
{
    use WithPagination, WithFileUploads;

    /* --- UI STATUS --- */
    public bool $isOpen = false;
    public string $activeMode = 'chat'; // chat, automations, health
    public bool $hasHealthIssues = false;
    public ?string $expandedHealthKey = null; // Für das Aufklappen der Kacheln

    /* --- CHAT STATE --- */
    public string $input = '';
    public array $messages = [];
    public bool $isTyping = false;

    /* --- QUICK ACTION STATE --- */
    public $uploadFile;
    public array $stockUpdate = []; // Produkt-ID => Neuer Wert

    /**
     * Initialisierung
     */
    public function mount(): void
    {
        if (auth()->guard('admin')->check()) {
            $this->checkGlobalHealth();
        }

        if (auth()->check()) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Hallo ' . auth()->user()->first_name . '! 👋 Ich bin Funki. Sollen wir direkt ein paar Dinge erledigen?'
            ];
        }
    }

    /**
     * Setzt den globalen Health-Status für den Badge
     */
    public function checkGlobalHealth(): void
    {
        $checks = $this->performAllChecks();
        $this->hasHealthIssues = collect($checks)->contains(fn($c) => $c['status'] === 'danger');
    }

    /* --- BUSINESS LOGIC CHECKS (Health Check & Data) --- */

    #[Computed]
    public function performAllChecks(): array
    {
        if (!auth()->guard('admin')->check()) return [];

        return array_filter([
            'inventory' => $this->checkInventory(),
            'special_issues' => $this->checkSpecialIssues(),
            'contracts' => $this->checkContracts(),
        ]);
    }

    private function checkInventory(): array {
        $threshold = shop_setting('inventory_low_stock_threshold', 5);
        $lowStockProducts = Product::where('type', 'physical')
            ->where('track_quantity', true)
            ->where('quantity', '<', $threshold)
            ->where('status', 'active')
            ->get();

        return [
            'title' => 'Lagerbestand',
            'status' => $lowStockProducts->count() > 0 ? 'danger' : 'success',
            'message' => $lowStockProducts->count() > 0 ? $lowStockProducts->count() . " Artikel unter Limit!" : "Lagerbestände optimal.",
            'icon' => 'bi-box-seam',
            'count' => $lowStockProducts->count(),
            'data' => $lowStockProducts
        ];
    }

    private function checkSpecialIssues(): array {
        $missing = FinanceSpecialIssue::where(function($query) {
            $query->whereNull('file_paths')->orWhere('file_paths', '[]')->orWhere('file_paths', '');
        })->orderBy('execution_date', 'desc')->get();

        return [
            'title' => 'Sonderausgaben',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Positionen ohne Beleg." : "Alle Ausgaben belegt.",
            'icon' => 'bi-receipt',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    private function checkContracts(): array {
        $missing = FinanceCostItem::whereNull('contract_file_path')->with('group')->get();

        return [
            'title' => 'Verträge',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Unterlagen fehlen." : "Dokumente vollständig.",
            'icon' => 'bi-file-earmark-text',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    /* --- QUICK ACTIONS --- */

    public function updateStock($productId)
    {
        $newQty = $this->stockUpdate[$productId] ?? null;
        if ($newQty === null || $newQty < 0) return;

        $product = Product::find($productId);
        if ($product) {
            $product->update(['quantity' => $newQty]);
            unset($this->stockUpdate[$productId]);
            $this->checkGlobalHealth();
            session()->flash('success', 'Bestand aktualisiert.');
        }
    }

    public function uploadContract($itemId)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $item = FinanceCostItem::find($itemId);
        if ($item) {
            $path = $this->uploadFile->store('contracts', 'public');
            $item->update(['contract_file_path' => $path]);
            $this->reset('uploadFile');
            $this->checkGlobalHealth();
        }
    }

    public function uploadSpecialReceipt($issueId)
    {
        $this->validate(['uploadFile' => 'required|file|max:10240']);
        $issue = FinanceSpecialIssue::find($issueId);
        if ($issue) {
            $path = $this->uploadFile->store('financial/receipts', 'public');
            $files = $issue->file_paths ?? [];
            $files[] = $path;
            $issue->update(['file_paths' => $files]);
            $this->reset('uploadFile');
            $this->checkGlobalHealth();
        }
    }

    public function toggleHealthCard($key)
    {
        $this->expandedHealthKey = ($this->expandedHealthKey === $key) ? null : $key;
    }

    /**
     * Holt die neuesten Aktivitäten aus dem FunkiLog
     */
    #[Computed]
    public function history()
    {
        return FunkiLog::latest()->take(15)->get();
    }

    public function toggleChat(): void
    {
        $this->isOpen = !$this->isOpen;
        if ($this->isOpen && auth()->guard('admin')->check()) {
            $this->checkGlobalHealth();
        }
    }

    public function setMode(string $mode): void
    {
        if ($mode !== 'chat' && !auth()->guard('admin')->check()) return;
        $this->activeMode = $mode;
        $this->expandedHealthKey = null;
    }

    public function sendMessage(AiSupportService $aiService): void
    {
        if (trim($this->input) === '') return;

        $userMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $userMessage];
        $this->input = '';
        $this->isTyping = true;

        $response = $aiService->askFunki($this->messages, $userMessage);

        $this->messages[] = ['role' => 'assistant', 'content' => $response];
        $this->isTyping = false;
    }

    public function render()
    {
        $autoTasks = [
            [
                'id' => 'newsletter:send',
                'name' => 'Newsletter-Marketing',
                'description' => 'Ich analysiere den Kalender und versende autonome Kampagnen.',
                'schedule' => 'Alle 15 Min',
                'status' => 'active',
                'icon' => 'bi-envelope-paper-heart',
                'last_run' => FunkiLog::where('action_id', 'newsletter:send')->where('status', 'success')->latest()->first()?->started_at?->diffForHumans() ?? 'Wartet...'
            ],
            [
                'id' => 'coupons:generate',
                'name' => 'Gutschein-Agent',
                'description' => 'Autonome Rabattcodes basierend auf Kunden-Interaktionen.',
                'schedule' => 'Täglich',
                'status' => 'coming_soon',
                'icon' => 'bi-ticket-perforated',
                'last_run' => 'In Planung'
            ],
            [
                'id' => 'blog:ai-writer',
                'name' => 'KI-Redaktion',
                'description' => 'SEO-Beiträge über Achtsamkeit und Kristalle verfassen.',
                'schedule' => 'Wöchentlich',
                'status' => 'coming_soon',
                'icon' => 'bi-journal-richtext',
                'last_run' => 'In Planung'
            ]
        ];

        return view('livewire.global.widgets.funki', [
            'autoTasks' => $autoTasks
        ]);
    }
}
