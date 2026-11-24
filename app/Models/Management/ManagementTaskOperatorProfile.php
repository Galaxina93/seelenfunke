<?php

namespace App\Models\Management;

use App\Services\Management\ManagementTaskTicketService;
use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ManagementTaskOperatorProfile extends Model implements HasSystemBlueprint
{
    use HasUuids;
    use InteractsWithSystemBlueprint;

    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Ticket-Operator-Profil & Effizienz-Score',
            description: 'Positives Gamification- und Wirksamkeits-Profil für Leitung und interne Mitarbeiter im Aufgaben-Ticketsystem inklusive EP, Rangklasse, Titel und Profil-Hintergrund.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.tasks', 'employee.dashboard'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: ['avatar_path'],
        );
    }

    protected $table = 'management_task_operator_profiles';

    protected $fillable = [
        'id',
        'actor_type',
        'actor_id',
        'display_name',
        'role_label',
        'avatar_path',
        'ep_total',
        'efficiency_score',
        'tickets_claimed_count',
        'tickets_completed_count',
        'tickets_on_time_count',
        'escalations_resolved_count',
        'fast_escalations_count',
        'chat_responses_count',
        'total_net_resolution_seconds',
        'total_first_response_seconds',
        'equipped_title_key',
        'equipped_background_key',
    ];

    protected $casts = [
        'ep_total' => 'integer',
        'efficiency_score' => 'integer',
        'tickets_claimed_count' => 'integer',
        'tickets_completed_count' => 'integer',
        'tickets_on_time_count' => 'integer',
        'escalations_resolved_count' => 'integer',
        'fast_escalations_count' => 'integer',
        'chat_responses_count' => 'integer',
        'total_net_resolution_seconds' => 'integer',
        'total_first_response_seconds' => 'integer',
    ];

    /**
     * 10 Stufen mit EP-Schwellen, Rangklassen (KLASSE C bis KLASSE S) und Standard-Titeln.
     *
     * @return array<int, array{level: int, min_ep: int, rank_class: string, title_key: string, title_label: string}>
     */
    public static function levelDefinitions(): array
    {
        return [
            1 => ['level' => 1, 'min_ep' => 0, 'rank_class' => 'KLASSE C', 'title_key' => 'starter', 'title_label' => 'Starter'],
            2 => ['level' => 2, 'min_ep' => 60, 'rank_class' => 'KLASSE C', 'title_key' => 'anpacker', 'title_label' => 'Anpacker'],
            3 => ['level' => 3, 'min_ep' => 150, 'rank_class' => 'KLASSE C', 'title_key' => 'taktgeber', 'title_label' => 'Taktgeber'],
            4 => ['level' => 4, 'min_ep' => 280, 'rank_class' => 'KLASSE B', 'title_key' => 'struktur_hueter', 'title_label' => 'Struktur-Hüter'],
            5 => ['level' => 5, 'min_ep' => 450, 'rank_class' => 'KLASSE B', 'title_key' => 'krisen_loeser', 'title_label' => 'Krisen-Löser'],
            6 => ['level' => 6, 'min_ep' => 680, 'rank_class' => 'KLASSE B', 'title_key' => 'fokus_waechter', 'title_label' => 'Fokus-Wächter'],
            7 => ['level' => 7, 'min_ep' => 980, 'rank_class' => 'KLASSE A', 'title_key' => 'praezisions_profi', 'title_label' => 'Präzisions-Profi'],
            8 => ['level' => 8, 'min_ep' => 1350, 'rank_class' => 'KLASSE A', 'title_key' => 'manufaktur_meister', 'title_label' => 'Manufaktur-Meister'],
            9 => ['level' => 9, 'min_ep' => 1800, 'rank_class' => 'KLASSE S', 'title_key' => 'leitstern', 'title_label' => 'Leitstern'],
            10 => ['level' => 10, 'min_ep' => 2400, 'rank_class' => 'KLASSE S', 'title_key' => 'seelenfunke_legende', 'title_label' => 'Seelenfunke-Legende'],
        ];
    }

    /**
     * Alle ausruestbaren Titel (Stufen-Titel + Spezial-Meilensteine).
     *
     * @return array<string, array{key: string, label: string, min_level: int, special_metric?: string, special_min?: int}>
     */
    public static function titleCatalog(): array
    {
        $catalog = [];
        foreach (self::levelDefinitions() as $lvl => $def) {
            $catalog[$def['title_key']] = [
                'key' => $def['title_key'],
                'label' => $def['title_label'],
                'min_level' => $lvl,
            ];
        }

        $catalog['uhrwerk'] = [
            'key' => 'uhrwerk',
            'label' => 'Uhrwerk',
            'min_level' => 1,
            'special_metric' => 'tickets_on_time_count',
            'special_min' => 5,
        ];

        $catalog['feuerwehr'] = [
            'key' => 'feuerwehr',
            'label' => 'Schnelle Eingreiftruppe',
            'min_level' => 1,
            'special_metric' => 'escalations_resolved_count',
            'special_min' => 3,
        ];

        return $catalog;
    }

    /**
     * Epische, stufenweise aufsteigende Seelenfunke-Welten fuer das Profil-Modal (Stufe 1 bis Stufe 10).
     * Jede Stufe von KLASSE C (Werkbank & Atelier) ueber KLASSE B (Wasserstadt & Smaragd-Hain)
     * und KLASSE A (Meisterschmiede, Eklipse & Astrales Uhrwerk) bis KLASSE S (Schwarz-Gold Kathedrale
     * & Heiligtum des Ewigen Seelenfunkens) schaltet eine sichtbar maechtigere Kulisse frei.
     *
     * @return array<string, array{key: string, label: string, min_level: int, rank_class: string, image_url: string, thumb_css: string, banner_css: string, border_css: string, accent_hex: string, sparks_count: int}>
     */
    public static function backgroundCatalog(): array
    {
        $raw = [
            'obsidian_slate' => [
                'label' => 'Abendliche Funken-Werkbank',
                'min_level' => 1,
                'rank_class' => 'KLASSE C',
                'file' => 'funken_werkbank.webp',
                'fallback_grad' => 'linear-gradient(135deg, #2b1d12 0%, #14100d 100%)',
                'border_css' => 'border-amber-500/40',
                'accent_hex' => '#E2C275',
                'sparks_count' => 5,
            ],
            'crystal_light' => [
                'label' => 'Nachtblaues Gravur-Atelier',
                'min_level' => 1,
                'rank_class' => 'KLASSE C',
                'file' => 'schiefer_atelier.webp',
                'fallback_grad' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
                'border_css' => 'border-slate-500/60',
                'accent_hex' => '#CBD5E1',
                'sparks_count' => 5,
            ],
            'warm_sand' => [
                'label' => 'Kristall-Laser-Kammer',
                'min_level' => 2,
                'rank_class' => 'KLASSE C',
                'file' => 'uv_kristall_kammer.webp',
                'fallback_grad' => 'linear-gradient(135deg, #172554 0%, #0f172a 100%)',
                'border_css' => 'border-sky-400/50',
                'accent_hex' => '#7DD3FC',
                'sparks_count' => 7,
            ],
            'bernstein_halle' => [
                'label' => 'Goldene Manufaktur-Halle',
                'min_level' => 3,
                'rank_class' => 'KLASSE C',
                'file' => 'bernstein_manufaktur.webp',
                'fallback_grad' => 'linear-gradient(135deg, #45270d 0%, #1c1208 100%)',
                'border_css' => 'border-amber-500/60',
                'accent_hex' => '#FBBF24',
                'sparks_count' => 8,
            ],
            'deep_emerald' => [
                'label' => 'Wasserstadt Funkenwerk',
                'min_level' => 4,
                'rank_class' => 'KLASSE B',
                'file' => 'wasserstadt_funkenwerk.webp',
                'fallback_grad' => 'linear-gradient(135deg, #113841 0%, #091921 100%)',
                'border_css' => 'border-teal-400/60',
                'accent_hex' => '#2DD4BF',
                'sparks_count' => 11,
            ],
            'smaragd_hain' => [
                'label' => 'Smaragd-Hain der Seele',
                'min_level' => 5,
                'rank_class' => 'KLASSE B',
                'file' => 'smaragd_polarlicht.webp',
                'fallback_grad' => 'linear-gradient(135deg, #0f3d2e 0%, #071914 100%)',
                'border_css' => 'border-emerald-400/70',
                'accent_hex' => '#34D399',
                'sparks_count' => 13,
            ],
            'seelen_gold' => [
                'label' => 'Meisterschmiede Feuer & Gold',
                'min_level' => 6,
                'rank_class' => 'KLASSE A',
                'file' => 'goldene_laserschmiede.webp',
                'fallback_grad' => 'linear-gradient(135deg, #4a2508 0%, #1a0d05 100%)',
                'border_css' => 'border-[#C5A059]',
                'accent_hex' => '#F59E0B',
                'sparks_count' => 16,
            ],
            'eklipse_sonne' => [
                'label' => 'Eklipse der Schwarzen Sonne',
                'min_level' => 7,
                'rank_class' => 'KLASSE A',
                'file' => 'eklipse_seelenanker.webp',
                'fallback_grad' => 'linear-gradient(135deg, #3b120c 0%, #0b090c 100%)',
                'border_css' => 'border-amber-500/80',
                'accent_hex' => '#FB923C',
                'sparks_count' => 18,
            ],
            'obsidian_master' => [
                'label' => 'Astrales Uhrwerk der Meister',
                'min_level' => 8,
                'rank_class' => 'KLASSE A',
                'file' => 'sternenschmiede_fokus.webp',
                'fallback_grad' => 'linear-gradient(135deg, #271e10 0%, #0c0f1d 100%)',
                'border_css' => 'border-[#C5A059]',
                'accent_hex' => '#E2C275',
                'sparks_count' => 20,
            ],
            'kathedrale_licht' => [
                'label' => 'Schwarz-Gold Kathedrale',
                'min_level' => 9,
                'rank_class' => 'KLASSE S',
                'file' => 'kathedrale_seelenlicht.webp',
                'fallback_grad' => 'linear-gradient(135deg, #3d2e10 0%, #0b0d13 100%)',
                'border_css' => 'border-[#E2C275]',
                'accent_hex' => '#FDE68A',
                'sparks_count' => 24,
            ],
            'ewiger_seelenfunke' => [
                'label' => 'Heiligtum des Seelenfunkens',
                'min_level' => 10,
                'rank_class' => 'KLASSE S',
                'file' => 'thron_ewiger_seelenfunke.webp',
                'fallback_grad' => 'linear-gradient(135deg, #523b0f 0%, #1c1306 55%, #090a0f 100%)',
                'border_css' => 'border-[#FDE047]',
                'accent_hex' => '#FEF08A',
                'sparks_count' => 30,
            ],
        ];

        $out = [];
        foreach ($raw as $key => $cfg) {
            $imgUrl = '/shop/gamification/operator_banners/' . $cfg['file'] . '?v=3';
            $heroOverlay = 'linear-gradient(92deg, rgba(7, 9, 14, 0.82) 0%, rgba(7, 9, 14, 0.40) 46%, rgba(7, 9, 14, 0.04) 100%), linear-gradient(180deg, rgba(7, 9, 14, 0.06) 0%, rgba(7, 9, 14, 0.28) 68%, rgba(7, 9, 14, 0.78) 100%)';
            $thumbOverlay = 'linear-gradient(180deg, rgba(7, 9, 14, 0.06) 0%, rgba(7, 9, 14, 0.76) 100%)';

            $out[$key] = [
                'key' => $key,
                'label' => $cfg['label'],
                'min_level' => $cfg['min_level'],
                'rank_class' => $cfg['rank_class'],
                'image_url' => $imgUrl,
                'banner_css' => "background-image: {$heroOverlay}, url('{$imgUrl}'), {$cfg['fallback_grad']}; background-size: cover; background-position: center right;",
                'thumb_css' => "background-image: {$thumbOverlay}, url('{$imgUrl}'), {$cfg['fallback_grad']}; background-size: cover; background-position: center;",
                'border_css' => $cfg['border_css'],
                'accent_hex' => $cfg['accent_hex'],
                'sparks_count' => $cfg['sparks_count'],
            ];
        }

        return $out;
    }

    public function resolveLevel(): int
    {
        $ep = max(0, (int) ($this->ep_total ?? 0));
        $resolved = 1;
        foreach (self::levelDefinitions() as $lvl => $def) {
            if (isset($def['min_ep']) && $ep >= (int) $def['min_ep']) {
                $resolved = (int) $lvl;
            }
        }

        return $resolved;
    }

    public function resolveRankClass(): string
    {
        $lvl = $this->resolveLevel();

        return (string) (self::levelDefinitions()[$lvl]['rank_class'] ?? 'KLASSE C');
    }

    public function isTitleUnlocked(?string $key): bool
    {
        $normalizedKey = trim((string) ($key ?? ''));
        if ($normalizedKey === '') {
            return false;
        }

        $catalog = self::titleCatalog();
        if (!isset($catalog[$normalizedKey])) {
            return false;
        }
        $def = $catalog[$normalizedKey];
        if (!empty($def['special_metric'])) {
            $metric = (string) $def['special_metric'];
            $metricValue = max(0, (int) ($this->getAttribute($metric) ?? 0));

            return $metricValue >= max(1, (int) ($def['special_min'] ?? 1));
        }

        return $this->resolveLevel() >= max(1, (int) ($def['min_level'] ?? 1));
    }

    public function isBackgroundUnlocked(?string $key): bool
    {
        $normalizedKey = trim((string) ($key ?? ''));
        if ($normalizedKey === '') {
            return false;
        }

        $catalog = self::backgroundCatalog();
        if (!isset($catalog[$normalizedKey])) {
            return false;
        }

        return $this->resolveLevel() >= max(1, (int) ($catalog[$normalizedKey]['min_level'] ?? 1));
    }

    public function resolveEquippedTitleKey(): string
    {
        $key = trim((string) ($this->equipped_title_key ?? ''));
        $catalog = self::titleCatalog();
        if ($key !== '' && isset($catalog[$key]) && $this->isTitleUnlocked($key)) {
            return $key;
        }
        $lvl = $this->resolveLevel();

        return (string) (self::levelDefinitions()[$lvl]['title_key'] ?? 'starter');
    }

    public function resolveEquippedTitleLabel(): string
    {
        $key = $this->resolveEquippedTitleKey();
        $catalog = self::titleCatalog();
        if (isset($catalog[$key]['label'])) {
            return (string) $catalog[$key]['label'];
        }
        $lvl = $this->resolveLevel();

        return (string) (self::levelDefinitions()[$lvl]['title_label'] ?? 'Starter');
    }

    /**
     * @return array{key: string, label: string, min_level: int, banner_css: string, border_css: string, accent_hex: string}
     */
    public function resolveEquippedBackground(): array
    {
        $key = trim((string) ($this->equipped_background_key ?? 'obsidian_slate'));
        $catalog = self::backgroundCatalog();
        if ($key !== '' && isset($catalog[$key]) && $this->isBackgroundUnlocked($key)) {
            return $catalog[$key];
        }

        return $catalog['obsidian_slate'];
    }

    public function recalculateEfficiencyScore(): int
    {
        $completed = max(0, (int) ($this->tickets_completed_count ?? 0));
        $claimed = max(0, (int) ($this->tickets_claimed_count ?? 0));
        $onTime = max(0, (int) ($this->tickets_on_time_count ?? 0));
        $escSaved = max(0, (int) ($this->escalations_resolved_count ?? 0));
        $fastEsc = max(0, (int) ($this->fast_escalations_count ?? 0));
        $totalNetSeconds = max(0, (int) ($this->total_net_resolution_seconds ?? 0));

        if ($completed === 0 && $claimed === 0 && $escSaved === 0) {
            return 100;
        }

        $onTimeRatio = $completed > 0 ? min(1.0, max(0.0, $onTime / $completed)) : 1.0;
        $completionRatio = $claimed > 0 ? min(1.0, max(0.0, $completed / $claimed)) : 1.0;

        $avgResHours = $completed > 0
            ? (($totalNetSeconds / $completed) / 3600.0)
            : 4.0;
        $speedScore = $avgResHours <= 12 ? 1.0 : ($avgResHours <= 48 ? 0.88 : ($avgResHours <= 120 ? 0.76 : 0.65));

        $rescueBonus = min(10, max(0, $escSaved * 2 + $fastEsc * 2));

        $raw = (int) round(($onTimeRatio * 45) + ($completionRatio * 30) + ($speedScore * 25) + $rescueBonus);

        return max(50, min(100, $raw));
    }

    /**
     * Serialisiert das komplette Operator-Profil fuer das 1-Klick Profil-Modal (Web, Mitarbeiter-Cockpit, Desktop & Android).
     *
     * @return array<string, mixed>
     */
    public function toModalPayload(bool $isOwnProfile = false): array
    {
        $lvl = $this->resolveLevel();
        $defs = self::levelDefinitions();
        $epTotal = max(0, (int) ($this->ep_total ?? 0));
        $currentMin = (int) ($defs[$lvl]['min_ep'] ?? 0);
        $nextDef = $defs[$lvl + 1] ?? null;
        $nextEp = $nextDef ? (int) ($nextDef['min_ep'] ?? $currentMin) : $currentMin;
        $progressPct = ($nextDef && $nextEp > $currentMin)
            ? max(0, min(100, (int) round((($epTotal - $currentMin) / ($nextEp - $currentMin)) * 100)))
            : 100;

        $completed = max(0, (int) ($this->tickets_completed_count ?? 0));
        $onTime = max(0, (int) ($this->tickets_on_time_count ?? 0));
        $onTimePct = $completed > 0
            ? max(0, min(100, (int) round(($onTime / $completed) * 100)))
            : 100;

        $totalNetSeconds = max(0, (int) ($this->total_net_resolution_seconds ?? 0));
        $avgNetSeconds = $completed > 0 ? max(0, (int) round($totalNetSeconds / $completed)) : 0;
        if ($avgNetSeconds <= 0) {
            $avgResolutionLabel = 'Sofort';
        } elseif ($avgNetSeconds < 3600) {
            $avgResolutionLabel = max(1, (int) round($avgNetSeconds / 60)) . ' Min.';
        } elseif ($avgNetSeconds < 86400) {
            $avgResolutionLabel = number_format($avgNetSeconds / 3600, 1, ',', '') . ' Std.';
        } else {
            $avgResolutionLabel = number_format($avgNetSeconds / 86400, 1, ',', '') . ' Tg.';
        }

        $respCount = max(0, (int) ($this->chat_responses_count ?? 0));
        $totalFirstResp = max(0, (int) ($this->total_first_response_seconds ?? 0));
        $avgRespSec = $respCount > 0 ? max(0, (int) round($totalFirstResp / $respCount)) : 0;
        if ($respCount === 0 || $avgRespSec < 900) {
            $reactionLabel = 'Blitzschnell';
        } elseif ($avgRespSec < 7200) {
            $reactionLabel = 'Direkt';
        } else {
            $reactionLabel = 'Zuverlässig';
        }

        $bg = $this->resolveEquippedBackground();
        $equippedTitleKey = $this->resolveEquippedTitleKey();

        $titlesList = [];
        foreach (self::titleCatalog() as $tKey => $tDef) {
            $unlocked = $this->isTitleUnlocked($tKey);
            $titlesList[] = [
                'key' => $tKey,
                'label' => (string) ($tDef['label'] ?? $tKey),
                'unlocked' => $unlocked,
                'min_level' => (int) ($tDef['min_level'] ?? 1),
            ];
        }

        $bgList = [];
        foreach (self::backgroundCatalog() as $bKey => $bDef) {
            $bgList[] = [
                'key' => $bKey,
                'label' => (string) ($bDef['label'] ?? $bKey),
                'unlocked' => $this->isBackgroundUnlocked($bKey),
                'min_level' => (int) ($bDef['min_level'] ?? 1),
                'rank_class' => (string) ($bDef['rank_class'] ?? 'KLASSE C'),
                'image_url' => (string) ($bDef['image_url'] ?? ''),
                'thumb_css' => (string) ($bDef['thumb_css'] ?? ($bDef['banner_css'] ?? '')),
                'banner_css' => (string) ($bDef['banner_css'] ?? ''),
                'accent_hex' => (string) ($bDef['accent_hex'] ?? '#94A3B8'),
                'sparks_count' => (int) ($bDef['sparks_count'] ?? 6),
            ];
        }

        $actorType = trim((string) ($this->actor_type ?? '')) ?: 'admin';
        $actorId = trim((string) ($this->actor_id ?? ''));
        $displayName = trim((string) ($this->display_name ?? ''));
        $safeDisplayName = $displayName !== '' ? $displayName : 'Teammitglied';

        $initials = collect(preg_split('/\s+/u', $safeDisplayName) ?: [])
            ->filter(fn ($part) => is_string($part) && $part !== '')
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('') ?: 'SF';

        $roleLabel = trim((string) ($this->role_label ?? '')) ?: ($actorType === 'admin' ? 'Leitung' : 'Team');
        $efficiencyScore = $this->efficiency_score !== null
            ? max(0, min(100, (int) $this->efficiency_score))
            : 100;

        return [
            'actor_key' => $actorType . ':' . $actorId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'display_name' => $safeDisplayName,
            'initials' => $initials,
            'role_label' => $roleLabel,
            'avatar_url' => ManagementTaskTicketService::resolveAvatarUrl($this->avatar_path ?? null),
            'level' => $lvl,
            'rank_class' => $this->resolveRankClass(),
            'ep_total' => $epTotal,
            'next_level_ep' => $nextDef ? (int) ($nextDef['min_ep'] ?? $currentMin) : null,
            'progress_pct' => $progressPct,
            'efficiency_score' => $efficiencyScore,
            'equipped_title_key' => $equippedTitleKey,
            'title_label' => $this->resolveEquippedTitleLabel(),
            'equipped_background_key' => (string) ($bg['key'] ?? 'obsidian_slate'),
            'background' => $bg,
            'stats' => [
                'claimed' => max(0, (int) ($this->tickets_claimed_count ?? 0)),
                'completed' => $completed,
                'on_time_pct' => $onTimePct,
                'escalations_saved' => max(0, (int) ($this->escalations_resolved_count ?? 0)),
                'avg_resolution' => $avgResolutionLabel,
                'reaction_label' => $reactionLabel,
            ],
            'is_own_profile' => $isOwnProfile,
            'titles' => $titlesList,
            'backgrounds' => $bgList,
        ];
    }
}
