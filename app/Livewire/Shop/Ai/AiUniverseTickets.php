<?php

namespace App\Livewire\Shop\Ai;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiMetric;
use App\Models\Ai\AiUniverseTicket;
use App\Services\AI\AgentSkillBudgetGuard;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\FunkiraPresenceContext;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\AI\TicketDeliverableService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class AiUniverseTickets extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    #[Url(as: 'view')]
    public string $mainView = 'tickets'; // 'tickets' | 'files' | 'organigramm' | 'knowledge'

    #[Url(as: 'fstate')]
    public string $fileStateFilter = 'active'; // 'active' | 'archived' | 'pinned' | 'all'

    #[Url(as: 'ftype')]
    public string $fileTypeFilter = 'all'; // 'all' | '3d' | 'concept' | 'document' | 'image'

    public string $fileSearch = '';
    public ?string $conceptModalTitle = null;
    public ?string $conceptModalHtml = null;
    public ?string $conceptModalDownloadUrl = null;

    #[Url(as: 'dept')]
    public string $selectedDepartmentId = 'all';

    #[Url(as: 'agent')]
    public string $selectedAgentId = 'all';

    #[Url(as: 'status')]
    public string $statusFilter = 'open'; // 'open' | 'waiting' | 'completed' | 'archived'

    public string $search = '';
    public string $commandInput = '';
    public string $ticketReplyInput = '';
    public $commandUploads = [];
    public $replyUploads = [];

    public ?string $fastLaneFeedback = null;

    #[Locked]
    public ?string $activeTicketId = null;

    public function boot(): void
    {
        $isAdmin = Auth::guard('admin')->check() || AiAuthHelper::isAdmin();
        if (!$isAdmin && (AiAuthHelper::isExternalPartner() || !AiAuthHelper::isEmployee())) {
            abort(403, 'Zugriff auf das zentrale Ticketsystem verweigert.');
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff auf das zentrale Ticketsystem verweigert.');
        }

        if (!in_array($this->mainView, ['tickets', 'files', 'organigramm', 'knowledge'], true)) {
            $this->mainView = 'tickets';
        }

        if (!in_array($this->fileStateFilter, ['active', 'archived', 'pinned', 'all'], true)) {
            $this->fileStateFilter = 'active';
        }

        if (!in_array($this->fileTypeFilter, ['all', '3d', 'concept', 'document', 'image'], true)) {
            $this->fileTypeFilter = 'all';
        }

        if (!in_array($this->statusFilter, ['open', 'waiting', 'completed', 'archived'], true)) {
            $this->statusFilter = 'open';
        }

        $requestedTicket = trim((string) request()->query('ticket', ''));
        if ($requestedTicket !== '' && Schema::hasTable('ai_universe_tickets')) {
            $found = AiUniverseTicket::where('id', $requestedTicket)
                ->orWhere('ticket_number', strtoupper($requestedTicket))
                ->first();
            if ($found) {
                $this->activeTicketId = $found->id;
            }
        }

        $this->ensureInitialSelection();
    }

    public function setMainView(string $view): void
    {
        if (in_array($view, ['tickets', 'files', 'organigramm', 'knowledge'], true)) {
            $this->mainView = $view;
        }
    }

    public function setFileStateFilter(string $state): void
    {
        if (in_array($state, ['active', 'archived', 'pinned', 'all'], true)) {
            $this->fileStateFilter = $state;
        }
    }

    public function setFileTypeFilter(string $type): void
    {
        if (in_array($type, ['all', '3d', 'concept', 'document', 'image'], true)) {
            $this->fileTypeFilter = $type;
        }
    }

    public function jumpToTicketFromFiles(string $ticketId): void
    {
        $ticket = AiUniverseTicket::find($ticketId);
        if (!$ticket) {
            return;
        }
        $this->mainView = 'tickets';
        $this->selectedDepartmentId = 'all';
        $this->selectedAgentId = 'all';
        $this->statusFilter = match ($ticket->status) {
            AiUniverseTicket::STATUS_COMPLETED => 'completed',
            AiUniverseTicket::STATUS_ARCHIVED => 'archived',
            default => 'open',
        };
        $this->activeTicketId = $ticket->id;
    }

    public function toggleAttachmentPin(string $messageId, string $attachmentKey): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin()) {
            abort(403);
        }

        TicketDeliverableService::togglePinOnAttachment($messageId, $attachmentKey);
    }

    public function restoreArchivedFile(string $messageId, string $attachmentKey): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin()) {
            abort(403);
        }

        TicketDeliverableService::restoreArchivedAttachment($messageId, $attachmentKey);
    }

    public function openConceptModal(string $relPath, string $title = 'Konzept'): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403);
        }

        $markdown = TicketDeliverableService::readConceptText($relPath);
        if ($markdown === null) {
            return;
        }

        $this->conceptModalTitle = $title;
        $this->conceptModalHtml = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $this->conceptModalDownloadUrl = route('admin.ai.workspace.file', ['path' => $relPath, 'download' => 1], false);
    }

    public function closeConceptModal(): void
    {
        $this->conceptModalTitle = null;
        $this->conceptModalHtml = null;
        $this->conceptModalDownloadUrl = null;
    }

    public function selectDepartment(string $deptId): void
    {
        $this->selectedDepartmentId = $deptId;
        $this->selectedAgentId = 'all';
        if ($deptId === 'all') {
            $this->themingDepartment = 'Leitung';
        } else {
            $deptName = AiDepartment::where('id', $deptId)->value('name');
            if (is_string($deptName) && $deptName !== '') {
                $this->themingDepartment = $deptName;
            }
        }
        $this->ensureInitialSelection();
    }

    public function toggleAgentFilter(string $agentId): void
    {
        $this->selectedAgentId = ($this->selectedAgentId === $agentId) ? 'all' : $agentId;
        $this->ensureInitialSelection();
    }

    public function setStatusFilter(string $filter): void
    {
        if (in_array($filter, ['open', 'waiting', 'completed', 'archived'], true)) {
            $this->statusFilter = $filter;
            $this->ensureInitialSelection();
        }
    }

    public function selectTicket(string $ticketId): void
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return;
        }
        $ticket = AiUniverseTicket::find($ticketId);
        if ($ticket) {
            $this->activeTicketId = $ticket->id;
            $this->fastLaneFeedback = null;
        }
    }

    public function submitCommand(): void
    {
        $prompt = trim($this->commandInput);
        $attachments = $this->storeSafeAttachments($this->commandUploads);

        if ($prompt === '' && empty($attachments)) {
            return;
        }

        $result = FunkiraTicketRouter::dispatchMessage(
            $prompt,
            [
                'channel' => 'web_portal',
                'active_route' => '/admin/tickets',
            ],
            null,
            $attachments
        );

        $this->commandInput = '';
        $this->commandUploads = [];

        if (($result['mode'] ?? '') === 'fast_lane_0_token' || empty($result['ticket'])) {
            $this->fastLaneFeedback = (string) ($result['reply'] ?? '');
            return;
        }

        /** @var AiUniverseTicket $ticket */
        $ticket = $result['ticket'];
        $this->fastLaneFeedback = null;
        $this->mainView = 'tickets';
        $this->selectedDepartmentId = 'all';
        $this->selectedAgentId = 'all';
        $this->statusFilter = ($ticket->status === AiUniverseTicket::STATUS_COMPLETED) ? 'completed' : 'open';
        $this->activeTicketId = $ticket->id;
    }

    public function submitTicketReply(): void
    {
        if (!$this->activeTicketId || !Schema::hasTable('ai_universe_tickets')) {
            return;
        }

        $ticket = AiUniverseTicket::find($this->activeTicketId);
        if (!$ticket) {
            return;
        }

        $reply = trim($this->ticketReplyInput);
        $attachments = $this->storeSafeAttachments($this->replyUploads);

        if ($reply === '' && empty($attachments)) {
            return;
        }

        $result = FunkiraTicketRouter::dispatchMessage(
            $reply,
            [
                'channel' => 'web_portal',
                'active_route' => '/admin/tickets',
            ],
            $ticket->id,
            $attachments
        );

        $this->ticketReplyInput = '';
        $this->replyUploads = [];

        $updatedTicket = ($result['ticket'] ?? null) instanceof AiUniverseTicket
            ? $result['ticket']
            : $ticket->fresh();

        if ($updatedTicket) {
            if ($this->statusFilter === 'completed' && $updatedTicket->status !== AiUniverseTicket::STATUS_COMPLETED) {
                $this->statusFilter = 'open';
            } elseif ($this->statusFilter === 'archived' && $updatedTicket->status !== AiUniverseTicket::STATUS_ARCHIVED) {
                $this->statusFilter = 'open';
            } elseif ($this->statusFilter === 'waiting' && $updatedTicket->status !== AiUniverseTicket::STATUS_WAITING_FOR_ALINA) {
                $this->statusFilter = ($updatedTicket->status === AiUniverseTicket::STATUS_COMPLETED) ? 'completed' : 'open';
            } elseif ($this->statusFilter === 'open' && $updatedTicket->status === AiUniverseTicket::STATUS_COMPLETED) {
                $this->statusFilter = 'completed';
            } elseif ($this->statusFilter === 'open' && $updatedTicket->status === AiUniverseTicket::STATUS_ARCHIVED) {
                $this->statusFilter = 'archived';
            }
        }
    }

    public function triggerTicketAction(string $action, ?string $optionValue = null): void
    {
        if (!$this->activeTicketId || !Schema::hasTable('ai_universe_tickets')) {
            return;
        }

        $ticket = AiUniverseTicket::find($this->activeTicketId);
        if (!$ticket) {
            return;
        }

        if ($action === 'quick_option' && $optionValue !== null && trim($optionValue) !== '') {
            FunkiraTicketRouter::dispatchMessage(
                trim($optionValue),
                ['channel' => 'web_portal', 'active_route' => '/admin/tickets'],
                $ticket->id
            );
            return;
        }

        if (!AiAuthHelper::isAdmin() && in_array($action, ['approve_procurement', 'handoff_antigravity', 'archive'], true)) {
            abort(403, 'Nur Administratoren dürfen Freigaben oder Übergaben auslösen.');
        }

        FunkiraTicketRouter::performTicketAction($ticket, $action, ['channel' => 'web_portal']);
    }

    public function changeTicketStatus(string $newStatus): void
    {
        if (!$this->activeTicketId || !Schema::hasTable('ai_universe_tickets')) {
            return;
        }

        if (!Auth::guard('admin')->check() && !AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403);
        }

        $ticket = AiUniverseTicket::find($this->activeTicketId);
        if (!$ticket) {
            return;
        }

        FunkiraTicketRouter::setManualTicketStatus($ticket, $newStatus, 'web_portal');

        // Filter automatisch mitführen, damit das geöffnete Ticket links und rechts synchron sichtbar bleibt
        $this->statusFilter = match ($newStatus) {
            AiUniverseTicket::STATUS_WAITING_FOR_ALINA => 'waiting',
            AiUniverseTicket::STATUS_COMPLETED => 'completed',
            AiUniverseTicket::STATUS_ARCHIVED => 'archived',
            default => 'open',
        };
    }

    /**
     * Löst das passende hochauflösende Profilbild (Alina oder jeweiliger KI-Spezialist) für den Chat-Stream auf.
     */
    public function resolveMessageAvatarUrl(\App\Models\Ai\AiUniverseTicketMessage $msg): string
    {
        if ($msg->sender_role === 'alina') {
            $adminUser = Auth::guard('admin')->user();
            $photoPath = $adminUser?->profile?->photo_path ?? \App\Models\Admin\AdminProfile::whereNotNull('photo_path')->value('photo_path');
            if (!empty($photoPath)) {
                return \Illuminate\Support\Str::startsWith($photoPath, ['http://', 'https://', '/'])
                    ? $photoPath
                    : (\Illuminate\Support\Str::startsWith($photoPath, 'shop/') ? asset($photoPath) : \Illuminate\Support\Facades\Storage::url($photoPath));
            }
            return asset('shop/projekt/about/gruender-profil.webp');
        }

        static $agentPictureMap = null;
        if ($agentPictureMap === null) {
            $agentPictureMap = [];
            if (Schema::hasTable('ai_agents')) {
                foreach (AiAgent::all(['id', 'name', 'profile_picture']) as $ag) {
                    if (!empty($ag->profile_picture)) {
                        $url = \Illuminate\Support\Str::startsWith($ag->profile_picture, 'shop/')
                            ? asset($ag->profile_picture)
                            : \Illuminate\Support\Facades\Storage::url($ag->profile_picture);
                        $agentPictureMap['id:' . $ag->id] = $url;
                        $agentPictureMap['name:' . strtolower(trim($ag->name))] = $url;
                    }
                }
            }
        }

        if ($msg->ai_agent_id && isset($agentPictureMap['id:' . $msg->ai_agent_id])) {
            return $agentPictureMap['id:' . $msg->ai_agent_id];
        }

        $normSender = strtolower(trim((string) $msg->sender_name));
        if (isset($agentPictureMap['name:' . $normSender])) {
            return $agentPictureMap['name:' . $normSender];
        }

        if (str_contains($normSender, 'antigravity')) {
            return asset('shop/projekt/logo/funkenwerk.png');
        }

        return $agentPictureMap['name:funkira'] ?? asset('shop/ai/images/funkira_selfie.png');
    }

    /**
     * Formatiert kompaktes Ticket-Markdown (**Fett**, `Code`, @Agent) XSS-sicher und mehrzeilig ohne rohe Sternchen.
     */
    public static function formatMessageHtml(?string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", (string) $content);
        $escaped = e($normalized);
        $formatted = preg_replace('/\*\*(.+?)\*\*/s', '<strong class="font-bold text-gray-900 dark:text-white">$1</strong>', $escaped) ?? $escaped;
        $formatted = preg_replace('/`([^`\n]+)`/', '<code class="px-1.5 py-0.5 rounded bg-gray-200/70 dark:bg-gray-800 font-mono text-[11px]">$1</code>', $formatted) ?? $formatted;

        $mentionRegex = '/(?:^|(?<=[\s(\[{"\',;:]))@(Antigravity|Dr\.\s*Funki|DrFunki|Bestelli|Einkaufi|Produkti|Funkira|Marketi|Systemi|Buchi|Globi|Lasi)(?=$|[\s.,!?:;)\]}"\'\-])/iu';
        $formatted = preg_replace(
            $mentionRegex,
            '<span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-bold bg-[var(--theme-color-20)] text-gray-900 dark:text-white border border-[var(--theme-color-30)]">@$1</span>',
            $formatted
        ) ?? $formatted;

        return $formatted;
    }

    /**
     * Interne Spezialisten + Antigravity für das @-Mention-Autocomplete im Ticket-Chat
     * (Funki bleibt als isolierter Frontend-Support-Agent strikt ausgeschlossen).
     *
     * @return array<int, array{id: string, name: string, dept: string, avatar: string}>
     */
    #[Computed]
    public function mentionableAgents(): array
    {
        if (!Schema::hasTable('ai_agents')) {
            return [];
        }

        $agents = AiAgent::with('department')
            ->where('is_active', true)
            ->get()
            ->reject(static fn (AiAgent $ag): bool => AgentSkillBudgetGuard::isFunkiAgent($ag))
            ->sortBy(static function (AiAgent $ag): string {
                $name = strtolower(trim((string) $ag->name));
                if ($name === 'funkira') {
                    return '00_funkira';
                }
                if ($name === 'antigravity') {
                    return '01_antigravity';
                }
                return '10_' . $name;
            })
            ->values();

        $list = [];
        foreach ($agents as $ag) {
            $pic = (string) ($ag->profile_picture ?? '');
            if ($pic !== '') {
                $avatar = Str::startsWith($pic, ['http://', 'https://', '/'])
                    ? $pic
                    : (Str::startsWith($pic, 'shop/') ? asset($pic) : \Illuminate\Support\Facades\Storage::url($pic));
            } elseif (strcasecmp((string) $ag->name, 'Antigravity') === 0) {
                $avatar = asset('shop/projekt/logo/funkenwerk.png');
            } else {
                $avatar = asset('shop/ai/images/funkira_selfie.png');
            }

            $list[] = [
                'id' => (string) $ag->id,
                'name' => (string) $ag->name,
                'dept' => (string) ($ag->department?->name ?? (strcasecmp((string) $ag->name, 'Antigravity') === 0 ? 'System' : 'Leitung')),
                'avatar' => $avatar,
            ];
        }

        return $list;
    }

    public function deleteLearnedPreference(string $prefId): void
    {
        if (!AiAuthHelper::isAdmin()) {
            abort(403);
        }

        if (Schema::hasTable('ai_learned_preferences')) {
            AiLearnedPreference::where('id', $prefId)->delete();
        }
    }

    #[Computed]
    public function departments()
    {
        if (!Schema::hasTable('ai_departments')) {
            return collect();
        }

        return AiDepartment::with(['agents' => function ($q) {
            $q->where('is_active', true)->orderBy('name');
        }])->orderBy('order_index')->get();
    }

    #[Computed]
    public function visibleAgents()
    {
        if (!Schema::hasTable('ai_agents')) {
            return collect();
        }

        return AiAgent::query()
            ->where('is_active', true)
            ->when($this->selectedDepartmentId !== 'all', fn ($q) => $q->where('ai_department_id', $this->selectedDepartmentId))
            ->orderByRaw("CASE WHEN name = 'Funkira' THEN 0 WHEN name = 'Antigravity' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->reject(static fn (AiAgent $ag): bool => AgentSkillBudgetGuard::isFunkiAgent($ag))
            ->values();
    }

    #[Computed]
    public function tickets()
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return collect();
        }

        return AiUniverseTicket::with(['department', 'agent'])
            ->when($this->selectedDepartmentId !== 'all', fn ($q) => $q->where('ai_department_id', $this->selectedDepartmentId))
            ->when($this->selectedAgentId !== 'all', fn ($q) => $q->where('assigned_agent_id', $this->selectedAgentId))
            ->when($this->statusFilter === 'open', function ($q) {
                $q->whereIn('status', [
                    AiUniverseTicket::STATUS_NEW,
                    AiUniverseTicket::STATUS_IN_PROGRESS,
                    AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                    AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
                    AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
                ]);
            })
            ->when($this->statusFilter === 'waiting', fn ($q) => $q->where('status', AiUniverseTicket::STATUS_WAITING_FOR_ALINA))
            ->when($this->statusFilter === 'completed', fn ($q) => $q->where('status', AiUniverseTicket::STATUS_COMPLETED))
            ->when($this->statusFilter === 'archived', fn ($q) => $q->where('status', AiUniverseTicket::STATUS_ARCHIVED))
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $q->where(function ($sq) use ($term) {
                    $sq->where('ticket_number', 'like', '%' . $term . '%')
                        ->orWhere('title', 'like', '%' . $term . '%');
                });
            })
            ->orderByRaw("CASE WHEN status = 'waiting_for_alina' THEN 0 WHEN status = 'in_progress' THEN 1 ELSE 2 END")
            ->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->limit(120)
            ->get();
    }

    #[Computed]
    public function activeTicket(): ?AiUniverseTicket
    {
        if (!$this->activeTicketId || !Schema::hasTable('ai_universe_tickets')) {
            return null;
        }

        return AiUniverseTicket::with(['department', 'agent', 'messages'])->find($this->activeTicketId);
    }

    #[Computed]
    public function learnedPreferences()
    {
        if (!Schema::hasTable('ai_learned_preferences')) {
            return collect();
        }

        return AiLearnedPreference::with('department')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function headerStats(): array
    {
        $waitingCount = 0;
        $openCount = 0;
        $deptOpenCounts = [];
        $agentStatusMap = [];

        if (Schema::hasTable('ai_universe_tickets')) {
            $waitingCount = AiUniverseTicket::where('status', AiUniverseTicket::STATUS_WAITING_FOR_ALINA)->count();
            $openCount = AiUniverseTicket::whereNotIn('status', [
                AiUniverseTicket::STATUS_COMPLETED,
                AiUniverseTicket::STATUS_ARCHIVED,
            ])->count();

            $deptOpenCounts = AiUniverseTicket::query()
                ->whereNotIn('status', [AiUniverseTicket::STATUS_COMPLETED, AiUniverseTicket::STATUS_ARCHIVED])
                ->whereNotNull('ai_department_id')
                ->selectRaw('ai_department_id, COUNT(*) as cnt')
                ->groupBy('ai_department_id')
                ->pluck('cnt', 'ai_department_id')
                ->all();

            $openByAgent = AiUniverseTicket::query()
                ->whereNotIn('status', [AiUniverseTicket::STATUS_COMPLETED, AiUniverseTicket::STATUS_ARCHIVED])
                ->whereNotNull('assigned_agent_id')
                ->get(['assigned_agent_id', 'status']);

            foreach ($openByAgent as $row) {
                $aid = (string) $row->assigned_agent_id;
                if ($row->status === AiUniverseTicket::STATUS_WAITING_FOR_ALINA) {
                    $agentStatusMap[$aid] = 'waiting';
                } elseif (($agentStatusMap[$aid] ?? '') !== 'waiting') {
                    $agentStatusMap[$aid] = 'working';
                }
            }
        }

        // Schlanke SQL-Kostenberechnung des aktuellen Monats (0 ApexCharts-Overhead)
        $monthlyEuro = 0.0;
        if (Schema::hasTable('ai_metrics') && Schema::hasColumn('ai_metrics', 'input_tokens')) {
            try {
                $sums = AiMetric::query()
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->selectRaw('COALESCE(SUM(input_tokens), 0) as in_tok, COALESCE(SUM(output_tokens), 0) as out_tok')
                    ->first();
                $inMio = ((int) ($sums->in_tok ?? 0)) / 1_000_000;
                $outMio = ((int) ($sums->out_tok ?? 0)) / 1_000_000;
                $usd = ($inMio * 0.15) + ($outMio * 0.60);
                $monthlyEuro = round($usd * 0.93, 2);
            } catch (\Throwable) {
            }
        }

        return [
            'waiting_count' => $waitingCount,
            'open_count' => $openCount,
            'dept_open_counts' => $deptOpenCounts,
            'agent_status_map' => $agentStatusMap,
            'monthly_euro_formatted' => number_format($monthlyEuro, 2, ',', '.') . ' €',
            'environment_tier' => FunkiraPresenceContext::resolveEnvironmentTier(),
            'notebook_online' => FunkiraPresenceContext::isAntigravityNotebookOnline(),
        ];
    }

    protected function ensureInitialSelection(): void
    {
        $list = $this->tickets;
        if ($list->isEmpty()) {
            $this->activeTicketId = null;
            return;
        }

        if (!$this->activeTicketId || !$list->contains('id', $this->activeTicketId)) {
            $this->activeTicketId = $list->first()->id;
        }
    }

    #[Computed]
    public function ticketDeliverables(): array
    {
        return TicketDeliverableService::collectAllTicketDeliverables(
            $this->fileStateFilter,
            $this->fileTypeFilter,
            $this->fileSearch
        );
    }

    /**
     * Speichert hochgeladene Ticket-Anhänge sicher ab (Bilder, PDFs, Konzepte, .3mf/.stl/.glb 3D-Modelle)
     * und gibt standardisierte Sticky-Metadaten zurück.
     *
     * @param array<int, mixed> $uploads
     * @return array<int, array<string, mixed>>
     */
    protected function storeSafeAttachments($uploads): array
    {
        if (empty($uploads) || !is_iterable($uploads)) {
            return [];
        }

        $stored = [];

        foreach ($uploads as $file) {
            if (!is_object($file) || !method_exists($file, 'getClientOriginalName')) {
                continue;
            }
            $origName = (string) $file->getClientOriginalName();
            $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext === '' && method_exists($file, 'guessExtension')) {
                $ext = strtolower((string) ($file->guessExtension() ?: ''));
            }
            if (!in_array($ext, TicketDeliverableService::ALLOWED_EXTENSIONS, true)) {
                continue;
            }
            $realPath = method_exists($file, 'getRealPath') ? (string) $file->getRealPath() : '';
            $rawBytes = ($realPath !== '' && is_file($realPath)) ? @file_get_contents($realPath) : false;
            if (!is_string($rawBytes) || $rawBytes === '') {
                continue;
            }
            $meta = TicketDeliverableService::storeBytesForTicket($rawBytes, $origName);
            if ($meta !== null) {
                $stored[] = $meta;
            }
        }

        return $stored;
    }

    public function render()
    {
        return view('livewire.shop.ai.ai-universe-tickets');
    }
}
