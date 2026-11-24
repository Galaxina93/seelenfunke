<?php

namespace App\Services\Management;

use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskMessage;
use App\Models\Management\ManagementTaskOperatorProfile;
use App\Models\System\SystemCheckConfig;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ManagementTaskTicketService
{
    public const ALLOWED_ATTACHMENT_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif',
        'txt', 'md', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4',
    ];

    public const BLOCKED_ATTACHMENT_MIMES = [
        'text/x-php',
        'application/x-httpd-php',
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'application/x-executable',
        'application/x-msdownload',
    ];

    public const DEFAULT_UI_CONFIG = [
        'left_col_width_px' => 330,
        'my_tickets_height_pct' => 52,
        'sound_muted' => false,
        'my_tickets_order' => [],
        'open_tickets_order' => [],
        'lists_order' => [],
    ];

    /**
     * Ermittelt den aktuell handelnden Akteur (`admin` oder interner `employee`).
     *
     * @return array{type: string, id: string, key: string, name: string, role_label: string, avatar: string|null, admin_id: string|null}
     */
    public function resolveCurrentActor(?Admin $explicitAdmin = null, ?Employee $explicitEmployee = null): array
    {
        if ($explicitEmployee instanceof Employee) {
            return $this->buildEmployeeActor($explicitEmployee);
        }
        if ($explicitAdmin instanceof Admin) {
            return $this->buildAdminActor($explicitAdmin);
        }

        if (Auth::guard('admin')->check()) {
            $u = Auth::guard('admin')->user();
            if ($u instanceof Admin) {
                return $this->buildAdminActor($u);
            }
        }

        if (Auth::guard('employee')->check()) {
            $emp = Auth::guard('employee')->user();
            if ($emp instanceof Employee) {
                return $this->buildEmployeeActor($emp);
            }
        }

        $sanctumUser = Auth::user();
        if ($sanctumUser instanceof Admin) {
            return $this->buildAdminActor($sanctumUser);
        }
        if ($sanctumUser instanceof Employee) {
            return $this->buildEmployeeActor($sanctumUser);
        }

        $fallbackAdmin = Admin::with('profile')->where('email', 'kontakt@mein-seelenfunke.de')->first()
            ?? Admin::with('profile')->first();

        if ($fallbackAdmin) {
            return $this->buildAdminActor($fallbackAdmin);
        }

        return [
            'type' => 'admin',
            'id' => '00000000-0000-0000-0000-000000000001',
            'key' => 'admin:00000000-0000-0000-0000-000000000001',
            'name' => 'Alina Steinhauer',
            'role_label' => 'Leitung',
            'avatar' => null,
            'admin_id' => null,
        ];
    }

    /**
     * @return array{type: string, id: string, key: string, name: string, role_label: string, avatar: string|null, admin_id: string|null}
     */
    public function buildAdminActor(Admin $admin): array
    {
        $admin->loadMissing('profile');
        $fullName = trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) ?: 'Alina Steinhauer';
        $avatar = null;
        if (!empty($admin->profile_photo_path)) {
            $avatar = (string) $admin->profile_photo_path;
        } elseif ($admin->profile && !empty($admin->profile->photo_path)) {
            $avatar = (string) $admin->profile->photo_path;
        }

        return [
            'type' => 'admin',
            'id' => (string) $admin->id,
            'key' => 'admin:' . $admin->id,
            'name' => $fullName,
            'role_label' => 'Leitung',
            'avatar' => $avatar,
            'admin_id' => (string) $admin->id,
        ];
    }

    /**
     * @return array{type: string, id: string, key: string, name: string, role_label: string, avatar: string|null, admin_id: string|null}
     */
    public function buildEmployeeActor(Employee $employee): array
    {
        $employee->loadMissing(['profile', 'employmentContract']);
        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'Mitarbeiter';
        $avatar = ($employee->profile && !empty($employee->profile->photo_path))
            ? (string) $employee->profile->photo_path
            : null;
        $roleLabel = $employee->employmentContract?->department
            ?: ($employee->employmentContract?->job_title ?: 'Team');

        return [
            'type' => 'employee',
            'id' => (string) $employee->id,
            'key' => 'employee:' . $employee->id,
            'name' => $fullName,
            'role_label' => (string) $roleLabel,
            'avatar' => $avatar,
            'admin_id' => null,
        ];
    }

    /**
     * Liefert alle zuweisbaren Personen (aktive Admins + aktive interne Mitarbeiter mit Aufgaben-Zugriff;
     * externe Influencer und Steuerberater sind 100 % ausgeschlossen) inkl. fehlertoleranter Fuzzy-Suche.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssignableActors(?string $search = null): array
    {
        $actors = [];

        if (Schema::hasTable('admins')) {
            foreach (Admin::with('profile')->orderBy('first_name')->get() as $admin) {
                $actor = $this->buildAdminActor($admin);
                $actors[] = $this->enrichAssignableActor($actor);
            }
        }

        if (Schema::hasTable('employees')) {
            $employees = Employee::with(['profile', 'employmentContract'])
                ->orderBy('first_name')
                ->get();

            foreach ($employees as $emp) {
                if (!$emp->isInternalEmployee() || !$emp->isAccountActive()) {
                    continue;
                }
                $contract = $emp->employmentContract;
                if ($contract && Schema::hasColumn('employee_contracts', 'can_access_tasks') && !$contract->can_access_tasks) {
                    continue;
                }
                $actor = $this->buildEmployeeActor($emp);
                $actors[] = $this->enrichAssignableActor($actor);
            }
        }

        $query = trim((string) $search);
        if ($query === '') {
            return $actors;
        }

        $scored = [];
        foreach ($actors as $item) {
            $score = self::fuzzyMatchScore($query, (string) $item['search_haystack'], (string) $item['initials']);
            if ($score > 0) {
                $item['match_score'] = $score;
                $scored[] = $item;
            }
        }

        usort($scored, fn (array $a, array $b) => ($b['match_score'] <=> $a['match_score']) ?: strcmp($a['name'], $b['name']));

        return $scored;
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    protected function enrichAssignableActor(array $actor): array
    {
        $initials = collect(preg_split('/\s+/u', trim((string) $actor['name'])) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('') ?: 'SF';

        $actor['initials'] = $initials;
        $actor['avatar_url'] = self::resolveAvatarUrl($actor['avatar'] ?? null);
        $actor['search_haystack'] = trim($actor['name'] . ' ' . ($actor['role_label'] ?? '') . ' ' . $initials);

        return $actor;
    }

    /**
     * Fehlertolerante Fuzzy-Suche (Umlaut-Normalisierung, Initialen, Teilworte und Levenshtein-Tippfehler).
     */
    public static function fuzzyMatchScore(string $rawQuery, string $rawTarget, string $initials = ''): int
    {
        $norm = function (string $s): string {
            $lower = mb_strtolower(trim($s));
            return strtr($lower, [
                'ä' => 'ae',
                'ö' => 'oe',
                'ü' => 'ue',
                'ß' => 'ss',
                '-' => ' ',
            ]);
        };

        $q = $norm($rawQuery);
        $t = $norm($rawTarget);
        $init = mb_strtolower(trim($initials));

        if ($q === '') {
            return 100;
        }
        if ($t === $q || ($init !== '' && $init === $q)) {
            return 100;
        }
        if (str_starts_with($t, $q)) {
            return 95;
        }
        if (str_contains($t, $q)) {
            return 85;
        }

        $qTokens = array_values(array_filter(explode(' ', $q)));
        $tTokens = array_values(array_filter(explode(' ', $t)));

        $matchedTokens = 0;
        foreach ($qTokens as $qt) {
            $bestTokenScore = 0;
            foreach ($tTokens as $tt) {
                if (str_starts_with($tt, $qt)) {
                    $bestTokenScore = max($bestTokenScore, 80);
                } elseif (str_contains($tt, $qt)) {
                    $bestTokenScore = max($bestTokenScore, 70);
                } elseif (strlen($qt) >= 3 && strlen($tt) >= 3) {
                    $cmpSlice = substr($tt, 0, min(strlen($tt), strlen($qt) + 2));
                    $dist = levenshtein($qt, $cmpSlice);
                    $maxAllowed = strlen($qt) >= 5 ? 2 : 1;
                    if ($dist <= $maxAllowed) {
                        $bestTokenScore = max($bestTokenScore, 65 - ($dist * 10));
                    }
                }
            }
            if ($bestTokenScore > 0) {
                $matchedTokens++;
            } else {
                return 0;
            }
        }

        return $matchedTokens > 0 ? 68 : 0;
    }

    /**
     * Loest einen Actor anhand von `admin:<uuid>`, `employee:<uuid>`, reiner UUID oder Name auf.
     *
     * @return array{type: string, id: string, key: string, name: string, role_label: string, avatar: string|null, admin_id: string|null}|null
     */
    public function resolveActorByKey(?string $rawKey): ?array
    {
        $clean = trim((string) $rawKey);
        if ($clean === '' || in_array(strtolower($clean), ['none', 'null', 'unassigned', 'niemand', 'keiner'], true)) {
            return null;
        }

        if (str_contains($clean, ':')) {
            [$type, $id] = explode(':', $clean, 2);
            $type = strtolower(trim($type));
            $id = trim($id);

            if ($type === 'admin') {
                $admin = Admin::with('profile')->find($id);
                return $admin ? $this->buildAdminActor($admin) : null;
            }
            if ($type === 'employee') {
                $emp = Employee::with(['profile', 'employmentContract'])->find($id);
                if ($emp && $emp->isInternalEmployee() && $emp->isAccountActive()) {
                    return $this->buildEmployeeActor($emp);
                }
                return null;
            }
        }

        // Fallback: Suche per UUID oder Fuzzy-Name (z.B. fuer Funkira Sprachbefehle "Weise Max zu")
        $candidates = $this->getAssignableActors();
        foreach ($candidates as $cand) {
            if ($cand['id'] === $clean) {
                return $cand;
            }
        }

        $fuzzy = $this->getAssignableActors($clean);

        return $fuzzy[0] ?? null;
    }

    /**
     * Prueft strikt, ob ein Mitarbeiter Zugriff auf ein konkretes Ticket hat (Chef-Datenschutz!).
     */
    public function isTaskVisibleToEmployee(ManagementTask $task, Employee $employee): bool
    {
        if (!$employee->isInternalEmployee() || !$employee->isAccountActive()) {
            return false;
        }

        $contract = $employee->employmentContract;
        if ($contract && Schema::hasColumn('employee_contracts', 'can_access_tasks') && !$contract->can_access_tasks) {
            return false;
        }

        if ($task->assignee_type === 'employee' && (string) $task->assignee_id === (string) $employee->id) {
            return true;
        }

        $list = $task->relationLoaded('list') ? $task->list : $task->list()->first();

        return (bool) ($list && $list->is_team_visible);
    }

    /**
     * Ermittelt die aktive Benutzer-ID fuer SystemCheckConfig (`system_check_configs`).
     */
    public function resolveConfigUserId(?Admin $explicitAdmin = null): ?string
    {
        if ($explicitAdmin && !empty($explicitAdmin->id)) {
            return (string) $explicitAdmin->id;
        }
        $actor = $this->resolveCurrentActor();

        return !empty($actor['id']) ? (string) $actor['id'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadUiConfig(?string $userId = null): array
    {
        $defaults = self::DEFAULT_UI_CONFIG;
        if (!Schema::hasTable('system_check_configs')) {
            return $defaults;
        }

        $uid = $userId ?: $this->resolveConfigUserId();
        if (!$uid) {
            return $defaults;
        }

        $config = SystemCheckConfig::query()->where('user_id', $uid)->first();
        if (!$config || !is_array($config->widgets)) {
            return $defaults;
        }

        $saved = $config->widgets['tasks_ui_config'] ?? [];
        if (!is_array($saved)) {
            return $defaults;
        }

        return [
            'left_col_width_px' => max(230, min(620, (int) ($saved['left_col_width_px'] ?? $defaults['left_col_width_px']))),
            'my_tickets_height_pct' => max(20, min(82, (int) ($saved['my_tickets_height_pct'] ?? $defaults['my_tickets_height_pct']))),
            'sound_muted' => (bool) ($saved['sound_muted'] ?? $defaults['sound_muted']),
            'my_tickets_order' => is_array($saved['my_tickets_order'] ?? null) ? array_values($saved['my_tickets_order']) : [],
            'open_tickets_order' => is_array($saved['open_tickets_order'] ?? null) ? $saved['open_tickets_order'] : [],
            'lists_order' => is_array($saved['lists_order'] ?? null) ? array_values($saved['lists_order']) : [],
        ];
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public function saveUiConfig(array $patch, ?string $userId = null): array
    {
        $current = $this->loadUiConfig($userId);
        if (array_key_exists('left_col_width_px', $patch)) {
            $current['left_col_width_px'] = max(230, min(620, (int) $patch['left_col_width_px']));
        }
        if (array_key_exists('my_tickets_height_pct', $patch)) {
            $current['my_tickets_height_pct'] = max(20, min(82, (int) $patch['my_tickets_height_pct']));
        }
        if (array_key_exists('sound_muted', $patch)) {
            $current['sound_muted'] = (bool) $patch['sound_muted'];
        }
        if (array_key_exists('my_tickets_order', $patch) && is_array($patch['my_tickets_order'])) {
            $current['my_tickets_order'] = array_values(array_filter(array_map('strval', $patch['my_tickets_order'])));
        }
        if (array_key_exists('open_tickets_order', $patch) && is_array($patch['open_tickets_order'])) {
            $current['open_tickets_order'] = $patch['open_tickets_order'];
        }
        if (array_key_exists('lists_order', $patch) && is_array($patch['lists_order'])) {
            $current['lists_order'] = array_values(array_filter(array_map('strval', $patch['lists_order'])));
        }

        if (!Schema::hasTable('system_check_configs')) {
            return $current;
        }

        $uid = $userId ?: $this->resolveConfigUserId();
        if (!$uid) {
            return $current;
        }

        $existing = SystemCheckConfig::query()->where('user_id', $uid)->first();
        $widgets = is_array($existing?->widgets) ? $existing->widgets : [];
        $widgets['tasks_ui_config'] = $current;

        $payload = [
            'filter_type' => $existing?->filter_type ?: 'all',
            'date_start' => $existing?->date_start ?: now()->startOfYear()->format('Y-m-d'),
            'date_end' => $existing?->date_end ?: now()->endOfYear()->format('Y-m-d'),
            'range_mode' => $existing?->range_mode ?: 'custom',
            'widgets' => $widgets,
        ];

        SystemCheckConfig::updateOrCreate(
            ['user_id' => $uid],
            $payload
        );

        return $current;
    }

    /**
     * @param array<int, string> $orderedIds
     */
    public function reorderTickets(array $orderedIds, bool $isMinePool, string $listScopeKey = 'all', ?string $userId = null): void
    {
        $cleanIds = array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $orderedIds
        ))));

        if (empty($cleanIds)) {
            return;
        }

        $column = $isMinePool ? 'my_position' : 'position';
        DB::transaction(function () use ($cleanIds, $column) {
            foreach ($cleanIds as $idx => $id) {
                DB::table('management_tasks')
                    ->where('id', $id)
                    ->update([$column => $idx + 1]);
            }
        });

        if ($isMinePool) {
            $this->saveUiConfig(['my_tickets_order' => $cleanIds], $userId);
        } else {
            $cfg = $this->loadUiConfig($userId);
            $openMap = is_array($cfg['open_tickets_order'] ?? null) ? $cfg['open_tickets_order'] : [];
            $openMap[$listScopeKey ?: 'all'] = $cleanIds;
            $this->saveUiConfig(['open_tickets_order' => $openMap], $userId);
        }
    }

    /**
     * Schiebt ein Ticket per Drag & Drop in "Meine Tickets" (`is_mine = true`, ordnet es der ziehenden Person zu)
     * oder zurueck in "Offene Tickets" (`is_mine = false`), OHNE den Ticket-Status zu veraendern!
     */
    public function moveToMine(ManagementTask $task, bool $isMine, ?int $targetPosition = null, ?array $actorOverride = null): ManagementTask
    {
        $actor = $actorOverride ?: $this->resolveCurrentActor();

        $updates = [
            'is_mine' => $isMine,
            'last_activity_at' => now(),
        ];

        if ($isMine && Schema::hasColumn('management_tasks', 'assignee_id')) {
            $updates['assignee_type'] = $actor['type'];
            $updates['assignee_id'] = $actor['id'];
            $updates['assignee_name'] = $actor['name'];
            $updates['assignee_avatar'] = $actor['avatar'];
        }

        if ($isMine) {
            $pos = $targetPosition ?? (((int) ManagementTask::where('is_mine', true)->whereNull('parent_id')->max('my_position')) + 1);
            $updates['my_position'] = max(1, $pos);
        } elseif ($targetPosition !== null) {
            $updates['position'] = max(1, $targetPosition);
        }

        $task->update($updates);

        return $task->fresh();
    }

    /**
     * Weist einem Ticket gezielt einen Bearbeiter zu (oder entfernt die Zuweisung bei `none` / `null`).
     */
    public function assignTicket(
        ManagementTask $task,
        ?string $targetActorKey,
        ?array $actingActor = null,
        bool $logChatEvent = true
    ): ManagementTask {
        if (!Schema::hasColumn('management_tasks', 'assignee_id')) {
            return $task;
        }

        $actor = $actingActor ?: $this->resolveCurrentActor();
        $resolvedTarget = $this->resolveActorByKey($targetActorKey);

        if ($resolvedTarget === null) {
            $hadAssignee = !empty($task->assignee_id);
            $task->assignee_type = null;
            $task->assignee_id = null;
            $task->assignee_name = null;
            $task->assignee_avatar = null;
            $task->is_mine = false;
            $task->last_activity_at = now();
            $task->save();

            if ($hadAssignee && $logChatEvent && Schema::hasTable('management_task_messages')) {
                ManagementTaskMessage::create([
                    'management_task_id' => $task->id,
                    'admin_id' => $actor['admin_id'],
                    'sender_actor_type' => $actor['type'],
                    'sender_actor_id' => $actor['id'],
                    'sender_name' => $actor['name'],
                    'sender_avatar' => $actor['avatar'],
                    'message_type' => ManagementTaskMessage::TYPE_STATUS_CHANGE,
                    'content' => 'Bearbeiter-Zuweisung aufgehoben.',
                    'attachments' => [],
                ]);
            }

            return $task->fresh();
        }

        $alreadySame = ($task->assignee_type === $resolvedTarget['type'] && (string) $task->assignee_id === (string) $resolvedTarget['id']);
        $task->assignee_type = $resolvedTarget['type'];
        $task->assignee_id = $resolvedTarget['id'];
        $task->assignee_name = $resolvedTarget['name'];
        $task->assignee_avatar = $resolvedTarget['avatar'];

        if ($task->status === ManagementTask::STATUS_IN_PROGRESS) {
            $task->is_mine = true;
        }
        $task->last_activity_at = now();
        $task->save();

        $this->getOrCreateOperatorProfile(
            $resolvedTarget['type'],
            $resolvedTarget['id'],
            $resolvedTarget['name'],
            $resolvedTarget['avatar'],
            $resolvedTarget['role_label'] ?? null
        );

        if (!$alreadySame && $logChatEvent && Schema::hasTable('management_task_messages')) {
            ManagementTaskMessage::create([
                'management_task_id' => $task->id,
                'admin_id' => $actor['admin_id'],
                'sender_actor_type' => $actor['type'],
                'sender_actor_id' => $actor['id'],
                'sender_name' => $actor['name'],
                'sender_avatar' => $actor['avatar'],
                'message_type' => ManagementTaskMessage::TYPE_STATUS_CHANGE,
                'content' => 'Bearbeiter gesetzt auf: ' . $resolvedTarget['name'],
                'attachments' => [],
            ]);
        }

        return $task->fresh();
    }

    /**
     * Prueft alle nicht erledigten Haupt-Tickets deterministisch auf ueberschrittene Faelligkeit
     * oder >= 72h Stillstand in "Meine Tickets".
     */
    public function evaluateEscalations(?Carbon $now = null): int
    {
        if (
            !Schema::hasTable('management_tasks')
            || !Schema::hasColumn('management_tasks', 'escalated_at')
        ) {
            return 0;
        }

        $refNow = $now ? $now->copy() : now();
        $hasMessagesTable = Schema::hasTable('management_task_messages');
        $escalatedCount = 0;

        $candidates = ManagementTask::query()
            ->whereNull('parent_id')
            ->where('is_completed', false)
            ->where('is_archived', false)
            ->where('status', '!=', ManagementTask::STATUS_COMPLETED)
            ->get();

        foreach ($candidates as $task) {
            $reason = $task->evaluateAutoEscalationReason($refNow);

            if ($reason !== null) {
                $wasAlreadyEscalated = ($task->escalated_at !== null);
                if (!$wasAlreadyEscalated || $task->escalation_reason !== $reason) {
                    $task->escalated_at = $task->escalated_at ?: $refNow;
                    $task->escalation_reason = $reason;
                    $task->saveQuietly();
                }

                if (!$wasAlreadyEscalated && $hasMessagesTable) {
                    ManagementTaskMessage::create([
                        'management_task_id' => $task->id,
                        'admin_id' => null,
                        'sender_actor_type' => 'system',
                        'sender_actor_id' => null,
                        'sender_name' => 'Ticket-Wächter',
                        'sender_avatar' => null,
                        'message_type' => ManagementTaskMessage::TYPE_SYSTEM_ESCALATION,
                        'content' => 'Ticket automatisch eskaliert: ' . $reason . '. Bitte neues Zieldatum wählen und kurzes Status-Update im Chat eintragen.',
                        'attachments' => [],
                    ]);
                }
                $escalatedCount++;
            }
        }

        return $escalatedCount;
    }

    /**
     * @return array{admin_id: string|null, sender_actor_type: string, sender_actor_id: string, sender_name: string, sender_avatar: string|null, role_label: string}
     */
    public function resolveSenderIdentity(?Admin $explicitAdmin = null, ?Employee $explicitEmployee = null): array
    {
        $actor = $this->resolveCurrentActor($explicitAdmin, $explicitEmployee);

        return [
            'admin_id' => $actor['admin_id'],
            'sender_actor_type' => $actor['type'],
            'sender_actor_id' => $actor['id'],
            'sender_name' => $actor['name'],
            'sender_avatar' => $actor['avatar'],
            'role_label' => $actor['role_label'],
        ];
    }

    public static function resolveAvatarUrl(?string $rawPath): ?string
    {
        if ($rawPath === null || trim($rawPath) === '') {
            return null;
        }
        $pp = trim($rawPath);
        if (str_starts_with($pp, 'http://') || str_starts_with($pp, 'https://')) {
            return $pp;
        }
        if (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/')) {
            return asset($pp);
        }

        return Storage::url($pp);
    }

    /**
     * Wechselt den Ticket-Status (`open` | `waiting` | `in_progress` | `completed`).
     * - Setzt man den Status auf `in_progress`, wird die handelnde Person automatisch als `Bearbeiter`
     *   gesetzt und das Ticket wandert in ihre `Meine Tickets` (`is_mine = true`)!
     * - Im Status `waiting` pausiert die Netto-Loesungszeit-Uhr automatisch (`waiting_paused_seconds`).
     * - Vergibt einmalig EP im Operator-Profil (ohne Ping-Pong-Farming).
     */
    public function changeStatus(
        ManagementTask $task,
        string $targetStatus,
        ?Admin $admin = null,
        bool $logChatEvent = true,
        ?array $actorOverride = null
    ): ManagementTask {
        $normalized = ManagementTask::normalizeStatus($targetStatus);
        if (!in_array($normalized, ManagementTask::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('Ungültiger Ticket-Status.');
        }

        if ($task->is_escalated && $normalized !== ManagementTask::STATUS_COMPLETED) {
            throw new InvalidArgumentException(
                'Dieses Ticket ist eskaliert. Bitte trage im Ticket-Chat ein neues Fälligkeitsdatum und einen kurzen Status ein.'
            );
        }

        $actor = $actorOverride ?: $this->resolveCurrentActor($admin);
        $now = now();
        $oldStatus = (string) $task->status;
        $hasScoreCols = Schema::hasColumn('management_tasks', 'waiting_paused_seconds');
        $hasAssigneeCols = Schema::hasColumn('management_tasks', 'assignee_id');

        if (
            $oldStatus === $normalized
            && (($normalized === ManagementTask::STATUS_COMPLETED) === (bool) $task->is_completed)
            && !($normalized === ManagementTask::STATUS_IN_PROGRESS && $hasAssigneeCols && empty($task->assignee_id))
        ) {
            return $task;
        }

        // 1. Warten-Pause sauber abrechnen oder starten
        if ($hasScoreCols) {
            if ($oldStatus === ManagementTask::STATUS_WAITING && $normalized !== ManagementTask::STATUS_WAITING && $task->waiting_started_at) {
                $pausedDelta = max(0, (int) $task->waiting_started_at->diffInSeconds($now, false));
                $task->waiting_paused_seconds = ((int) $task->waiting_paused_seconds) + $pausedDelta;
                $task->waiting_started_at = null;
            } elseif ($normalized === ManagementTask::STATUS_WAITING && $oldStatus !== ManagementTask::STATUS_WAITING) {
                $task->waiting_started_at = $now;
            }
        }

        // 2. Automatische Bearbeiter-Zuweisung + Meine Tickets bei "In Bearbeitung"
        if ($normalized === ManagementTask::STATUS_IN_PROGRESS) {
            $task->is_mine = true;
            if ($hasAssigneeCols) {
                $task->assignee_type = $actor['type'];
                $task->assignee_id = $actor['id'];
                $task->assignee_name = $actor['name'];
                $task->assignee_avatar = $actor['avatar'];
            }
            if ($hasScoreCols && !$task->claimed_at) {
                $task->claimed_at = $now;
            }
            if ((int) $task->my_position <= 0) {
                $maxMyPos = (int) ManagementTask::where('is_mine', true)->whereNull('parent_id')->max('my_position');
                $task->my_position = $maxMyPos + 1;
            }
        }

        // 3. Bei Abschluss ohne bisherigen Bearbeiter die abschliessende Person als Bearbeiter setzen
        if ($normalized === ManagementTask::STATUS_COMPLETED && $hasAssigneeCols && empty($task->assignee_id)) {
            $task->assignee_type = $actor['type'];
            $task->assignee_id = $actor['id'];
            $task->assignee_name = $actor['name'];
            $task->assignee_avatar = $actor['avatar'];
        }

        $deadlineBeforeClear = $task->getEscalationDeadline();
        $wasOverdueAtCompletion = $deadlineBeforeClear ? $deadlineBeforeClear->lt($now) : false;

        $task->status = $normalized;
        $task->is_completed = ($normalized === ManagementTask::STATUS_COMPLETED);
        if ($normalized === ManagementTask::STATUS_COMPLETED) {
            $task->escalated_at = null;
            $task->escalation_reason = null;
            if ($hasScoreCols) {
                $task->completed_at = $now;
            }
        }
        $task->last_activity_at = $now;
        $task->save();

        // 4. Einmalige EP- & Score-Vergabe (Anti-Exploit geschuetzt)
        if ($hasScoreCols && Schema::hasTable('management_task_operator_profiles')) {
            if ($normalized === ManagementTask::STATUS_IN_PROGRESS) {
                $this->awardClaimEpOnce($task, $actor);
            } elseif ($normalized === ManagementTask::STATUS_COMPLETED) {
                $this->awardCompletionEpOnce($task, $actor, !$wasOverdueAtCompletion, $now);
            }
        }

        if ($logChatEvent && Schema::hasTable('management_task_messages')) {
            $statusLabel = ManagementTask::statusLabelFor($normalized);
            $content = $normalized === ManagementTask::STATUS_IN_PROGRESS
                ? ('Ticket übernommen (' . $statusLabel . ') von ' . $actor['name'])
                : ('Status geändert auf: ' . $statusLabel);

            ManagementTaskMessage::create([
                'management_task_id' => $task->id,
                'admin_id' => $actor['admin_id'],
                'sender_actor_type' => $actor['type'],
                'sender_actor_id' => $actor['id'],
                'sender_name' => $actor['name'],
                'sender_avatar' => $actor['avatar'],
                'message_type' => ManagementTaskMessage::TYPE_STATUS_CHANGE,
                'content' => $content,
                'attachments' => [],
            ]);
        }

        return $task->fresh();
    }

    /**
     * @param array<int, UploadedFile|mixed> $files
     */
    public function postMessage(
        ManagementTask $task,
        ?string $content,
        array $files = [],
        ?Admin $admin = null,
        string $messageType = ManagementTaskMessage::TYPE_COMMENT,
        ?array $actorOverride = null
    ): ManagementTaskMessage {
        $cleanContent = trim((string) $content);
        $storedAttachments = $this->storeUploadedFiles($task, $files);

        if ($cleanContent === '' && empty($storedAttachments)) {
            throw new InvalidArgumentException('Bitte gib eine Nachricht ein oder füge mindestens eine Datei hinzu.');
        }

        $actor = $actorOverride ?: $this->resolveCurrentActor($admin);
        $now = now();

        $msgData = [
            'management_task_id' => $task->id,
            'admin_id' => $actor['admin_id'],
            'sender_name' => $actor['name'],
            'sender_avatar' => $actor['avatar'],
            'message_type' => $messageType,
            'content' => $cleanContent !== '' ? $cleanContent : null,
            'attachments' => $storedAttachments,
        ];
        if (Schema::hasColumn('management_task_messages', 'sender_actor_id')) {
            $msgData['sender_actor_type'] = $actor['type'];
            $msgData['sender_actor_id'] = $actor['id'];
        }

        $msg = ManagementTaskMessage::create($msgData);

        if (!empty($storedAttachments)) {
            $existingPaths = is_array($task->file_paths) ? $task->file_paths : [];
            foreach ($storedAttachments as $att) {
                if (!empty($att['path']) && !in_array($att['path'], $existingPaths, true)) {
                    $existingPaths[] = $att['path'];
                }
            }
            $task->file_paths = array_values($existingPaths);
        }

        $task->last_activity_at = $now;
        $task->save();

        if (
            $messageType === ManagementTaskMessage::TYPE_COMMENT
            && Schema::hasColumn('management_tasks', 'ep_awarded_flags')
            && Schema::hasTable('management_task_operator_profiles')
        ) {
            $this->awardChatResponseEpOnce($task, $actor, $now);
        }

        return $msg;
    }

    /**
     * @param array<int, UploadedFile|mixed> $newFiles
     * @param array<int, string>|null $keepAttachmentPaths
     */
    public function editMessage(
        ManagementTaskMessage $message,
        ?string $newContent,
        array $newFiles = [],
        ?array $keepAttachmentPaths = null,
        ?Carbon $now = null
    ): ManagementTaskMessage {
        $refNow = $now ? $now->copy() : now();
        if (!$message->isEditable($refNow)) {
            throw new InvalidArgumentException('Chateinträge können nur innerhalb von 5 Minuten nach dem Absenden angepasst werden.');
        }

        $task = $message->task ?: ManagementTask::findOrFail($message->management_task_id);
        $existingAttachments = is_array($message->attachments) ? $message->attachments : [];

        if ($keepAttachmentPaths !== null) {
            $keepSet = array_flip(array_map('strval', $keepAttachmentPaths));
            $retained = [];
            $removedPaths = [];

            foreach ($existingAttachments as $att) {
                $p = (string) ($att['path'] ?? '');
                if ($p !== '' && isset($keepSet[$p])) {
                    $retained[] = $att;
                } elseif ($p !== '') {
                    $removedPaths[] = $p;
                }
            }

            foreach ($removedPaths as $remPath) {
                if (
                    !str_contains($remPath, '..')
                    && !str_contains($remPath, "\0")
                    && str_starts_with(ltrim($remPath, '/'), 'leitung/tasks/')
                    && Storage::disk('local')->exists($remPath)
                ) {
                    Storage::disk('local')->delete($remPath);
                }
            }

            if (!empty($removedPaths) && is_array($task->file_paths)) {
                $task->file_paths = array_values(array_diff($task->file_paths, $removedPaths));
            }

            $existingAttachments = $retained;
        }

        $addedAttachments = !empty($newFiles) ? $this->storeUploadedFiles($task, $newFiles) : [];
        $mergedAttachments = array_values(array_merge($existingAttachments, $addedAttachments));

        $cleanContent = trim((string) $newContent);
        if ($cleanContent === '' && empty($mergedAttachments)) {
            throw new InvalidArgumentException('Der Chateintrag darf nicht komplett leer sein.');
        }

        $message->update([
            'content' => $cleanContent !== '' ? $cleanContent : null,
            'attachments' => $mergedAttachments,
            'edited_at' => $refNow,
        ]);

        if (!empty($addedAttachments)) {
            $paths = is_array($task->file_paths) ? $task->file_paths : [];
            foreach ($addedAttachments as $att) {
                if (!empty($att['path']) && !in_array($att['path'], $paths, true)) {
                    $paths[] = $att['path'];
                }
            }
            $task->file_paths = array_values($paths);
        }

        $task->last_activity_at = $refNow;
        $task->save();

        return $message->fresh();
    }

    /**
     * @param array<int, UploadedFile|mixed> $files
     */
    public function resolveEscalation(
        ManagementTask $task,
        string $newDueDate,
        ?string $newDueTime,
        string $chatStatusText,
        string $targetStatus = ManagementTask::STATUS_IN_PROGRESS,
        ?int $durationMinutes = null,
        ?Admin $admin = null,
        array $files = [],
        ?array $actorOverride = null
    ): ManagementTask {
        $cleanNote = trim($chatStatusText);
        if (mb_strlen($cleanNote) < 3) {
            throw new InvalidArgumentException('Bitte schreibe kurz den aktuellen Status oder Grund in den Chat (mind. 3 Zeichen).');
        }

        $cleanDate = trim($newDueDate);
        if ($cleanDate === '') {
            throw new InvalidArgumentException('Bitte wähle ein neues Fälligkeitsdatum in der Zukunft.');
        }

        try {
            $parsedDate = Carbon::parse($cleanDate)->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Ungültiges Datumsformat.');
        }

        $normalizedTime = ManagementTask::normalizeDueTime($newDueTime);
        if ($normalizedTime !== null) {
            [$h, $m] = array_map('intval', explode(':', $normalizedTime));
            $effectiveDeadline = $parsedDate->copy()->setTime($h, $m, 0);
        } else {
            $effectiveDeadline = $parsedDate->copy()->endOfDay();
        }

        if ($effectiveDeadline->lte(now())) {
            throw new InvalidArgumentException('Das neue Fälligkeitsdatum (inkl. Uhrzeit) muss in der Zukunft liegen.');
        }

        $normalizedTarget = ManagementTask::normalizeStatus($targetStatus);
        if (!in_array($normalizedTarget, ManagementTask::VALID_STATUSES, true)) {
            $normalizedTarget = ManagementTask::STATUS_IN_PROGRESS;
        }

        $actor = $actorOverride ?: $this->resolveCurrentActor($admin);
        $now = now();
        $escalatedSince = $task->escalated_at ? $task->escalated_at->copy() : null;

        // Warten-Pause fortfuehren oder starten
        if (Schema::hasColumn('management_tasks', 'waiting_paused_seconds')) {
            if ($task->status === ManagementTask::STATUS_WAITING && $normalizedTarget !== ManagementTask::STATUS_WAITING && $task->waiting_started_at) {
                $task->waiting_paused_seconds = ((int) $task->waiting_paused_seconds) + max(0, (int) $task->waiting_started_at->diffInSeconds($now, false));
                $task->waiting_started_at = null;
            } elseif ($normalizedTarget === ManagementTask::STATUS_WAITING && $task->status !== ManagementTask::STATUS_WAITING) {
                $task->waiting_started_at = $now;
            }
        }

        $task->relevant_from = $parsedDate;
        $task->due_time = $normalizedTime;
        if ($durationMinutes !== null && $durationMinutes > 0) {
            $task->duration_minutes = max(15, min(720, $durationMinutes));
        }
        $task->status = $normalizedTarget;
        $task->is_completed = ($normalizedTarget === ManagementTask::STATUS_COMPLETED);
        if ($normalizedTarget === ManagementTask::STATUS_IN_PROGRESS && Schema::hasColumn('management_tasks', 'assignee_id')) {
            $task->is_mine = true;
            $task->assignee_type = $actor['type'];
            $task->assignee_id = $actor['id'];
            $task->assignee_name = $actor['name'];
            $task->assignee_avatar = $actor['avatar'];
        }
        $task->escalated_at = null;
        $task->escalation_reason = null;
        $task->last_activity_at = $now;
        $task->save();

        $formattedTarget = $task->formattedDueDisplay() ?: $parsedDate->format('d.m.Y');
        $headerLine = "Eskalation aufgelöst (Neues Ziel: {$formattedTarget}): {$cleanNote}";

        $this->postMessage(
            task: $task,
            content: $headerLine,
            files: $files,
            admin: $admin,
            messageType: ManagementTaskMessage::TYPE_ESCALATION_RESOLUTION,
            actorOverride: $actor
        );

        if (Schema::hasColumn('management_tasks', 'ep_awarded_flags') && Schema::hasTable('management_task_operator_profiles')) {
            $wasFastRescue = $escalatedSince ? ($escalatedSince->diffInHours($now) <= 24) : true;
            $this->awardEscalationRescueEpOnce($task, $actor, $wasFastRescue);
        }

        return $task->fresh();
    }

    // =====================================================================
    // GAMIFICATION SCORE, EP, WARTE-PAUSE & PROFIL-MODAL ENGINE
    // =====================================================================

    public function getOrCreateOperatorProfile(
        string $actorType,
        string $actorId,
        ?string $fallbackName = null,
        ?string $fallbackAvatar = null,
        ?string $fallbackRole = null
    ): ManagementTaskOperatorProfile {
        $normalizedType = trim($actorType) !== '' ? mb_substr(trim($actorType), 0, 24) : 'admin';
        $normalizedId = trim($actorId);
        if ($normalizedId === '') {
            throw new InvalidArgumentException('Operator-Profil benötigt eine gültige actor_id.');
        }

        $resolved = $this->resolveActorByKey($normalizedType . ':' . $normalizedId);
        $rawName = trim((string) ($resolved['name'] ?? ($fallbackName ?: 'Teammitglied')));
        $name = mb_substr($rawName !== '' ? $rawName : 'Teammitglied', 0, 120);
        $avatar = isset($resolved['avatar']) && trim((string) $resolved['avatar']) !== ''
            ? mb_substr(trim((string) $resolved['avatar']), 0, 255)
            : ($fallbackAvatar !== null && trim($fallbackAvatar) !== '' ? mb_substr(trim($fallbackAvatar), 0, 255) : null);
        $rawRole = trim((string) ($resolved['role_label'] ?? ($fallbackRole ?: ($normalizedType === 'admin' ? 'Leitung' : 'Team'))));
        $role = mb_substr($rawRole !== '' ? $rawRole : ($normalizedType === 'admin' ? 'Leitung' : 'Team'), 0, 80);

        $attributes = ['actor_type' => $normalizedType, 'actor_id' => $normalizedId];
        $defaults = [
            'display_name' => $name,
            'role_label' => $role,
            'avatar_path' => $avatar,
            'ep_total' => 0,
            'efficiency_score' => 100,
            'equipped_background_key' => $normalizedType === 'admin' ? 'obsidian_slate' : 'crystal_light',
        ];

        try {
            $profile = ManagementTaskOperatorProfile::firstOrCreate($attributes, $defaults);
        } catch (QueryException $e) {
            $profile = ManagementTaskOperatorProfile::where($attributes)->first();
            if (!$profile) {
                throw $e;
            }
        }

        $dirty = false;
        if ($name !== '' && $profile->display_name !== $name) {
            $profile->display_name = $name;
            $dirty = true;
        }
        if ($role !== '' && $profile->role_label !== $role) {
            $profile->role_label = $role;
            $dirty = true;
        }
        if ($avatar !== null && $profile->avatar_path !== $avatar) {
            $profile->avatar_path = $avatar;
            $dirty = true;
        }
        if ($dirty) {
            $profile->save();
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOperatorProfileModalPayload(
        string $actorType,
        string $actorId,
        ?array $viewerActor = null,
        ?string $fallbackName = null
    ): array {
        $viewer = is_array($viewerActor) && !empty($viewerActor) ? $viewerActor : $this->resolveCurrentActor();
        $profile = $this->getOrCreateOperatorProfile($actorType, $actorId, $fallbackName);
        $isOwn = (
            (string) ($viewer['type'] ?? '') === trim($actorType)
            && (string) ($viewer['id'] ?? '') === trim($actorId)
        );

        return $profile->toModalPayload($isOwn);
    }

    public function equipOperatorCosmetic(
        string $actorType,
        string $actorId,
        ?string $titleKey = null,
        ?string $backgroundKey = null
    ): ManagementTaskOperatorProfile {
        $profile = $this->getOrCreateOperatorProfile($actorType, $actorId);

        $dirty = false;
        if ($titleKey !== null && trim($titleKey) !== '' && $profile->isTitleUnlocked($titleKey)) {
            $normalizedTitle = trim($titleKey);
            if ($profile->equipped_title_key !== $normalizedTitle) {
                $profile->equipped_title_key = $normalizedTitle;
                $dirty = true;
            }
        }
        if ($backgroundKey !== null && trim($backgroundKey) !== '' && $profile->isBackgroundUnlocked($backgroundKey)) {
            $normalizedBg = trim($backgroundKey);
            if ($profile->equipped_background_key !== $normalizedBg) {
                $profile->equipped_background_key = $normalizedBg;
                $dirty = true;
            }
        }

        if ($dirty) {
            $profile->save();
        }

        return $profile->fresh() ?? $profile;
    }

    protected function awardClaimEpOnce(ManagementTask $task, array $actor): void
    {
        if ($task->isSubtask() || empty($actor['id']) || empty($actor['type'])) {
            return;
        }
        $flags = is_array($task->ep_awarded_flags) ? $task->ep_awarded_flags : [];
        if (!empty($flags['claim'])) {
            return;
        }

        $flags['claim'] = true;
        $task->ep_awarded_flags = $flags;
        $task->saveQuietly();

        $profile = $this->getOrCreateOperatorProfile(
            (string) $actor['type'],
            (string) $actor['id'],
            $actor['name'] ?? null,
            $actor['avatar'] ?? null,
            $actor['role_label'] ?? null
        );
        $profile->ep_total = max(0, (int) $profile->ep_total) + 10;
        $profile->tickets_claimed_count = max(0, (int) $profile->tickets_claimed_count) + 1;
        $profile->efficiency_score = $profile->recalculateEfficiencyScore();
        $profile->save();
    }

    protected function awardCompletionEpOnce(ManagementTask $task, array $actor, bool $onTime, Carbon $now): void
    {
        if ($task->isSubtask()) {
            return;
        }
        $flags = is_array($task->ep_awarded_flags) ? $task->ep_awarded_flags : [];
        if (!empty($flags['complete'])) {
            return;
        }

        $targetType = !empty($task->assignee_type) ? (string) $task->assignee_type : (string) ($actor['type'] ?? '');
        $targetId = !empty($task->assignee_id) ? (string) $task->assignee_id : (string) ($actor['id'] ?? '');
        if ($targetType === '' || $targetId === '') {
            return;
        }

        $flags['complete'] = true;
        $task->ep_awarded_flags = $flags;
        $task->saveQuietly();

        // Anti-Dummy-Spam: Selbst angelegtes leeres Mini-Ticket in < 2 Min bringt nur +5 EP Basis
        $startRef = $task->claimed_at ?: ($task->created_at ?: $now);
        $grossSeconds = max(0, (int) $startRef->diffInSeconds($now, false));
        $netSeconds = max(30, $grossSeconds - max(0, (int) $task->waiting_paused_seconds));

        $commentCount = Schema::hasTable('management_task_messages')
            ? $task->messages()->where('message_type', ManagementTaskMessage::TYPE_COMMENT)->count()
            : 0;

        $isInstantSelfDummy = (
            $task->created_at
            && $task->created_at->diffInSeconds($now, false) < 120
            && empty(trim((string) $task->ai_plan))
            && $commentCount === 0
            && $task->created_by_id === ($actor['id'] ?? null)
        );

        $epGain = $isInstantSelfDummy ? 5 : (30 + ($onTime ? 15 : 0));
        $targetName = !empty($task->assignee_name) ? (string) $task->assignee_name : ($actor['name'] ?? null);

        $profile = $this->getOrCreateOperatorProfile($targetType, $targetId, $targetName);
        $profile->ep_total = max(0, (int) $profile->ep_total) + $epGain;
        $profile->tickets_completed_count = max(0, (int) $profile->tickets_completed_count) + 1;
        if ($onTime) {
            $profile->tickets_on_time_count = max(0, (int) $profile->tickets_on_time_count) + 1;
        }
        $profile->total_net_resolution_seconds = max(0, (int) $profile->total_net_resolution_seconds) + $netSeconds;
        $profile->efficiency_score = $profile->recalculateEfficiencyScore();
        $profile->save();
    }

    protected function awardEscalationRescueEpOnce(ManagementTask $task, array $actor, bool $fastRescue): void
    {
        if ($task->isSubtask() || empty($actor['id']) || empty($actor['type'])) {
            return;
        }
        $flags = is_array($task->ep_awarded_flags) ? $task->ep_awarded_flags : [];
        if (!empty($flags['escalation'])) {
            return;
        }

        $flags['escalation'] = true;
        $task->ep_awarded_flags = $flags;
        $task->saveQuietly();

        $epGain = 20 + ($fastRescue ? 10 : 0);
        $profile = $this->getOrCreateOperatorProfile(
            (string) $actor['type'],
            (string) $actor['id'],
            $actor['name'] ?? null,
            $actor['avatar'] ?? null,
            $actor['role_label'] ?? null
        );
        $profile->ep_total = max(0, (int) $profile->ep_total) + $epGain;
        $profile->escalations_resolved_count = max(0, (int) $profile->escalations_resolved_count) + 1;
        if ($fastRescue) {
            $profile->fast_escalations_count = max(0, (int) $profile->fast_escalations_count) + 1;
        }
        $profile->efficiency_score = $profile->recalculateEfficiencyScore();
        $profile->save();
    }

    protected function awardChatResponseEpOnce(ManagementTask $task, array $actor, Carbon $now): void
    {
        if ($task->isSubtask() || empty($actor['id']) || empty($actor['type'])) {
            return;
        }
        $flagKey = 'chat_' . $actor['type'] . '_' . $actor['id'];
        $flags = is_array($task->ep_awarded_flags) ? $task->ep_awarded_flags : [];
        if (!empty($flags[$flagKey])) {
            return;
        }

        $flags[$flagKey] = true;
        $task->ep_awarded_flags = $flags;
        $task->saveQuietly();

        $responseSeconds = $task->created_at
            ? max(10, min(86400, (int) $task->created_at->diffInSeconds($now, false)))
            : 120;

        $profile = $this->getOrCreateOperatorProfile(
            (string) $actor['type'],
            (string) $actor['id'],
            $actor['name'] ?? null,
            $actor['avatar'] ?? null,
            $actor['role_label'] ?? null
        );
        $profile->ep_total = max(0, (int) $profile->ep_total) + 5;
        $profile->chat_responses_count = max(0, (int) $profile->chat_responses_count) + 1;
        $profile->total_first_response_seconds = max(0, (int) $profile->total_first_response_seconds) + $responseSeconds;
        $profile->efficiency_score = $profile->recalculateEfficiencyScore();
        $profile->save();
    }

    // =====================================================================
    // DATEI-ANHAENGE & MESSAGE SERIALISIERUNG
    // =====================================================================

    /**
     * @param array<int, UploadedFile|mixed> $files
     * @return array<int, array<string, mixed>>
     */
    public function storeUploadedFiles(ManagementTask $task, array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $origName = $file->getClientOriginalName() ?: 'datei';
            $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
                throw new InvalidArgumentException("Dateiendung .{$ext} ist im Ticket-Chat nicht freigegeben.");
            }

            $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream'));
            if (in_array($mime, self::BLOCKED_ATTACHMENT_MIMES, true)) {
                throw new InvalidArgumentException('Ausführbare oder aktive Skriptdateien sind im Ticket-Chat gesperrt.');
            }

            $safeBase = Str::slug(pathinfo($origName, PATHINFO_FILENAME)) ?: 'anhang';
            $safeBase = substr($safeBase, 0, 70);
            $filename = $safeBase . '_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(5)) . '.' . $ext;

            $storedPath = $file->storeAs('leitung/tasks/attachments', $filename, 'local');
            if ($storedPath) {
                $stored[] = [
                    'original_name' => mb_substr($origName, 0, 160),
                    'path' => $storedPath,
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                    'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        return $stored;
    }

    public function purgeTaskAttachments(ManagementTask $task): void
    {
        $paths = is_array($task->file_paths) ? $task->file_paths : [];

        if (Schema::hasTable('management_task_messages')) {
            foreach ($task->messages as $msg) {
                foreach (($msg->attachments ?? []) as $att) {
                    if (is_array($att) && !empty($att['path'])) {
                        $paths[] = (string) $att['path'];
                    }
                }
            }
        }

        foreach (array_unique($paths) as $pathStr) {
            if (
                $pathStr !== ''
                && !str_contains($pathStr, '..')
                && !str_contains($pathStr, "\0")
                && str_starts_with(ltrim($pathStr, '/'), 'leitung/tasks/')
                && Storage::disk('local')->exists($pathStr)
            ) {
                Storage::disk('local')->delete($pathStr);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeMessage(ManagementTaskMessage $msg): array
    {
        $actorType = $msg->sender_actor_type ?: ($msg->admin_id ? 'admin' : null);
        $actorId = $msg->sender_actor_id ?: $msg->admin_id;

        return [
            'id' => $msg->id,
            'management_task_id' => $msg->management_task_id,
            'sender_actor_type' => $actorType,
            'sender_actor_id' => $actorId,
            'sender_actor_key' => ($actorType && $actorId) ? ($actorType . ':' . $actorId) : null,
            'sender_name' => $msg->sender_name,
            'sender_avatar_url' => self::resolveAvatarUrl($msg->sender_avatar),
            'message_type' => $msg->message_type,
            'content' => $msg->content,
            'attachments' => $msg->attachments ?? [],
            'can_edit' => $msg->isEditable(),
            'is_editable' => $msg->isEditable(),
            'remaining_edit_seconds' => $msg->remainingEditSeconds(),
            'edited_at' => optional($msg->edited_at)->toIso8601String(),
            'edited_human' => optional($msg->edited_at)->format('H:i'),
            'created_at' => optional($msg->created_at)->toIso8601String(),
            'created_human' => optional($msg->created_at)->format('d.m.Y H:i'),
        ];
    }
}
