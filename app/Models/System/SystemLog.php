<?php

namespace App\Models\System;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model implements HasSystemBlueprint
{
    use InteractsWithSystemBlueprint;

    /**
     * Kanonische Ökosystem-Bereiche für Spalte 1 & Filter in /admin/global-logs.
     */
    public const PLATFORMS = [
        'portal' => [
            'key' => 'portal',
            'label' => 'Webportal',
            'short' => 'Webportal',
        ],
        'android' => [
            'key' => 'android',
            'label' => 'Android App',
            'short' => 'Android',
        ],
        'minipc_linux' => [
            'key' => 'minipc_linux',
            'label' => 'Mini-PC',
            'short' => 'Mini-PC',
        ],
        'windows_desktop' => [
            'key' => 'windows_desktop',
            'label' => 'Funkenwerk Software',
            'short' => 'Funkenwerk',
        ],
    ];

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'System-, Sicherheits- & DSGVO-Audit-Log',
            description: 'Zentrales Ereignisprotokoll für Sicherheitsvorfälle, Hintergrundjobs und DSGVO-Einwilligungsnachweise.',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.logs'],
            lifecycle: LifecycleBehavior::AppendOnlyLog,
            sensitivity: DataSensitivity::PersonalPII,
            retention: RetentionPolicy::ShortLived_30Days,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin, ActorScope::SystemCron],
            regulations: [ComplianceRegulation::DSGVO_Art15_Art30],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

    protected $table = 'system_logs';

    public const MAX_TYPE_LENGTH = 64;
    public const MAX_ACTION_ID_LENGTH = 191;
    public const MAX_TITLE_LENGTH = 191;
    public const MAX_STATUS_LENGTH = 64;
    public const MAX_MESSAGE_LENGTH = 16000;

    protected $fillable = [
        'ai_agent_id',
        'type',
        'action_id',
        'title',
        'message',
        'status',
        'payload',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Begrenzt einen String deterministisch inklusive Suffix strikt auf maximal $maxLength Zeichen
     * (verhindert z. B. den Str::limit(..., 190)-Überlauf von 190 + 3 = 193 > VARCHAR(191)).
     */
    public static function clampColumnValue(?string $value, int $maxLength, string $suffix = '...'): string
    {
        $str = (string) ($value ?? '');
        if ($maxLength <= 0) {
            return '';
        }

        if (mb_strlen($str, 'UTF-8') <= $maxLength) {
            return $str;
        }

        $suffixLen = mb_strlen($suffix, 'UTF-8');
        if ($suffixLen === 0 || $maxLength <= $suffixLen) {
            return mb_substr($str, 0, $maxLength, 'UTF-8');
        }

        return rtrim(mb_substr($str, 0, $maxLength - $suffixLen, 'UTF-8')) . $suffix;
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $rawType = trim((string) ($model->attributes['type'] ?? 'system'));
            $model->attributes['type'] = self::clampColumnValue($rawType !== '' ? $rawType : 'system', self::MAX_TYPE_LENGTH, '');

            $rawActionId = (string) ($model->attributes['action_id'] ?? '');
            $model->attributes['action_id'] = self::clampColumnValue($rawActionId, self::MAX_ACTION_ID_LENGTH, '');

            $rawTitle = (string) ($model->attributes['title'] ?? '');
            $model->attributes['title'] = self::clampColumnValue($rawTitle, self::MAX_TITLE_LENGTH, '...');

            $rawStatus = trim((string) ($model->attributes['status'] ?? 'info'));
            $model->attributes['status'] = self::clampColumnValue($rawStatus !== '' ? $rawStatus : 'info', self::MAX_STATUS_LENGTH, '');

            if (array_key_exists('message', $model->attributes) && $model->attributes['message'] !== null) {
                $model->attributes['message'] = self::clampColumnValue((string) $model->attributes['message'], self::MAX_MESSAGE_LENGTH, '...');
            }
        });
    }

    /**
     * Relation zum ausführenden KI-Agenten
     */
    public function agent()
    {
        return $this->belongsTo(\App\Models\Ai\AiAgent::class, 'ai_agent_id');
    }

    public static function normalizePlatformAlias(?string $raw): ?string
    {
        $clean = strtolower(trim((string) $raw));
        if ($clean === '') {
            return null;
        }

        return match ($clean) {
            'portal', 'web', 'shop', 'laravel', 'frontend', 'frontend_error', 'backend' => 'portal',
            'android', 'android_app' => 'android',
            'minipc_linux', 'minipc', 'mini_pc', 'mini-pc', 'funkenwerk_os', 'atelier' => 'minipc_linux',
            'windows_desktop', 'funkenwerk', 'funkenwerk_desktop', 'desktop', 'windows' => 'windows_desktop',
            default => null,
        };
    }

    public static function detectPlatformKey(?string $type, ?string $actionId, ?array $payload = null): string
    {
        if (is_array($payload) && !empty($payload['platform'])) {
            $fromPayload = self::normalizePlatformAlias((string) $payload['platform']);
            if ($fromPayload !== null) {
                return $fromPayload;
            }
        }

        $fromType = self::normalizePlatformAlias($type);
        if ($fromType !== null && $fromType !== 'portal') {
            return $fromType;
        }

        $actionStr = strtolower(trim((string) $actionId));
        if ($actionStr !== '' && str_contains($actionStr, ':')) {
            $prefix = explode(':', $actionStr, 2)[0];
            $fromPrefix = self::normalizePlatformAlias($prefix);
            if ($fromPrefix !== null && $fromPrefix !== 'portal') {
                return $fromPrefix;
            }
        }

        return 'portal';
    }

    public function resolvePlatformKey(): string
    {
        $payload = is_array($this->payload) ? $this->payload : (json_decode((string) $this->payload, true) ?: []);
        return self::detectPlatformKey($this->type, $this->action_id, $payload);
    }

    public function resolvePlatformMeta(): array
    {
        $key = $this->resolvePlatformKey();
        return self::PLATFORMS[$key] ?? self::PLATFORMS['portal'];
    }

    /**
     * Ermittelt für Spalte 1 ("Bereich") und die Detailansicht in /admin/global-logs
     * sowohl die Plattform & Umgebung (LOKAL / STAGE / LIVE) als auch die exakte Unterseite / Route / Komponente.
     */
    public function resolveAreaAndSubpageMeta(): array
    {
        $payload = is_array($this->payload) ? $this->payload : (json_decode((string) $this->payload, true) ?: []);
        $platformMeta = $this->resolvePlatformMeta();
        $platformKey = $platformMeta['key'];

        $titleStr = (string) $this->title;
        $actionId = (string) $this->action_id;
        $messageStr = (string) $this->message;

        $execCtx = is_array($payload['execution_context'] ?? null) ? $payload['execution_context'] : [];
        $serverEnv = is_array($payload['server_environment'] ?? null) ? $payload['server_environment'] : [];
        $dossier = is_array($payload['antigravity_dossier'] ?? null) ? $payload['antigravity_dossier'] : [];

        $fullUrl = trim((string) ($payload['url'] ?? $execCtx['url'] ?? ''));
        $referer = trim((string) ($payload['referer'] ?? $payload['referrer'] ?? $execCtx['referer'] ?? ''));

        // 1. Umgebung ermitteln (LOKAL, STAGE, LIVE)
        $rawEnv = strtolower(trim((string) (
            $payload['origin_environment']
            ?? $serverEnv['resolved_env']
            ?? $serverEnv['app_env']
            ?? ''
        )));

        $urlLower = strtolower($fullUrl . ' ' . $referer . ' ' . (string) ($serverEnv['app_url'] ?? ''));
        $envKey = match (true) {
            str_starts_with($titleStr, '[STAGE]') || in_array($rawEnv, ['stage', 'staging'], true) || str_contains($urlLower, 'stage.mein-seelenfunke.de') => 'stage',
            str_starts_with($titleStr, '[LIVE]') || str_starts_with($titleStr, '[PROD]') || in_array($rawEnv, ['production', 'live', 'prod'], true) || (str_contains($urlLower, 'mein-seelenfunke.de') && !str_contains($urlLower, 'stage.')) => 'live',
            default => 'local',
        };

        $envMeta = match ($envKey) {
            'stage' => [
                'key' => 'stage',
                'label' => 'STAGE',
                'badge_class' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
            ],
            'live' => [
                'key' => 'live',
                'label' => 'LIVE',
                'badge_class' => 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
            ],
            default => [
                'key' => 'local',
                'label' => 'LOKAL',
                'badge_class' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
            ],
        };

        // 2. Exakte Unterseite / Route / CLI-Befehl ermitteln
        $subpagePath = trim((string) ($payload['subpage_path'] ?? $execCtx['subpage_path'] ?? ''));

        if ($subpagePath === '' && !empty($payload['request_uri'])) {
            $subpagePath = '/' . ltrim(trim((string) $payload['request_uri']), '/');
        }

        if (($subpagePath === '' || $subpagePath === '/livewire/update' || $subpagePath === 'livewire/update') && $referer !== '') {
            $refPath = parse_url($referer, PHP_URL_PATH);
            if (is_string($refPath) && $refPath !== '') {
                $subpagePath = '/' . ltrim($refPath, '/');
            }
        }

        if ($subpagePath === '' && $fullUrl !== '') {
            $parsedPath = parse_url($fullUrl, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $candidate = '/' . ltrim($parsedPath, '/');
                if ($candidate !== '/livewire/update' && $candidate !== '/api/log/frontend-error') {
                    $subpagePath = $candidate;
                }
            }
        }

        if ($subpagePath === '' && !empty($execCtx['route']) && !in_array((string) $execCtx['route'], ['livewire.update', 'livewire/update'], true)) {
            $routeStr = trim((string) $execCtx['route']);
            $subpagePath = str_starts_with($routeStr, '/') ? $routeStr : ('/' . $routeStr);
        }

        if ($subpagePath === '') {
            $cmd = trim((string) ($payload['command'] ?? $execCtx['command'] ?? ''));
            if ($cmd !== '') {
                $shortCmd = trim((string) preg_replace('/^.*?\bartisan\s+/i', '', $cmd));
                $subpagePath = 'CLI: ' . ($shortCmd !== '' ? $shortCmd : $cmd);
            } elseif (str_starts_with($actionId, 'scheduler:cronjob_failed:')) {
                $subpagePath = 'CLI: ' . substr($actionId, strlen('scheduler:cronjob_failed:'));
            }
        }

        if ($subpagePath === '' && !empty($dossier['route_or_command']) && $dossier['route_or_command'] !== 'unbekannt') {
            $rc = trim((string) $dossier['route_or_command']);
            if ($rc !== 'livewire.update' && $rc !== 'livewire/update') {
                $subpagePath = str_starts_with($rc, 'php ') || str_starts_with($rc, 'CLI:')
                    ? ('CLI: ' . trim((string) preg_replace('/^.*?\bartisan\s+/i', '', $rc)))
                    : (str_starts_with($rc, '/') ? $rc : '/' . $rc);
            }
        }

        $fileStr = (string) ($payload['file'] ?? $dossier['file'] ?? '');
        if ($subpagePath === '') {
            if ($platformKey === 'android' && !empty($payload['component'])) {
                $subpagePath = 'Android · ' . trim((string) $payload['component']);
            } elseif ($platformKey === 'minipc_linux' && !empty($payload['component'])) {
                $subpagePath = 'Mini-PC · ' . trim((string) $payload['component']);
            } elseif ($platformKey === 'windows_desktop' && !empty($payload['component'])) {
                $subpagePath = 'Desktop · ' . trim((string) $payload['component']);
            } elseif ($actionId === 'system:health_fail') {
                $subpagePath = '/admin/dashboard';
            } elseif ($actionId === 'security:package_audit') {
                $subpagePath = 'CLI: system:security-audit';
            } elseif (str_contains($fileStr, 'XtoolLaserCockpitService') || str_contains($messageStr, 'XtoolLaserCockpitService')) {
                $subpagePath = '/admin/orders';
            } elseif (str_contains($messageStr, '0.0.0.0:6001') || str_contains($messageStr, 'reverb')) {
                $subpagePath = 'CLI: reverb:start';
            } elseif (str_contains($fileStr, 'InfluencerDashboard')) {
                $subpagePath = '/employee/influencer/dashboard';
            } elseif (str_contains($fileStr, 'InfluencerInvitationAccept')) {
                $subpagePath = '/influencer/einladung';
            } elseif (str_contains($fileStr, 'MarketingInfluencer')) {
                $subpagePath = '/admin/marketing-influencers';
            } elseif (str_contains($fileStr, 'TaxAdvisorDashboard')) {
                $subpagePath = '/employee/steuerberater/dashboard';
            } elseif (!empty($payload['source_file'])) {
                $subpagePath = 'Log: ' . trim((string) $payload['source_file']);
            } else {
                $subpagePath = '/';
            }
        }

        $subpageLabel = !empty($payload['subpage_label'])
            ? (string) $payload['subpage_label']
            : self::humanizeSubpageLabel($subpagePath, $actionId, $payload);

        $livewireComponent = trim((string) ($payload['livewire_component'] ?? $execCtx['livewire_component'] ?? ''));
        $livewireMethod = trim((string) ($payload['livewire_method'] ?? $execCtx['livewire_method'] ?? ''));
        $livewireAction = $livewireComponent !== ''
            ? ($livewireMethod !== '' ? "{$livewireComponent}::{$livewireMethod}()" : $livewireComponent)
            : ($livewireMethod !== '' ? "{$livewireMethod}()" : null);

        $httpMethod = trim((string) ($execCtx['method'] ?? ($execCtx['mode'] ?? '') === 'cli' ? 'CLI' : ''));

        return [
            'platform_key' => $platformKey,
            'platform_short' => $platformMeta['short'],
            'platform_label' => $platformMeta['label'],
            'env_key' => $envMeta['key'],
            'env_label' => $envMeta['label'],
            'env_badge_class' => $envMeta['badge_class'],
            'subpage_path' => $subpagePath,
            'subpage_label' => $subpageLabel,
            'livewire_component' => $livewireComponent !== '' ? $livewireComponent : null,
            'livewire_method' => $livewireMethod !== '' ? $livewireMethod : null,
            'livewire_action' => $livewireAction,
            'http_method' => $httpMethod !== '' ? $httpMethod : null,
            'full_url' => $fullUrl !== '' ? $fullUrl : null,
            'referer' => $referer !== '' ? $referer : null,
        ];
    }

    /**
     * Übersetzt einen technischen Route-Pfad oder CLI-Befehl in eine sprechende deutsche Bereichs-/Unterseiten-Bezeichnung.
     */
    public static function humanizeSubpageLabel(string $subpagePath, ?string $actionId = null, ?array $payload = null): string
    {
        $clean = strtolower(trim($subpagePath));
        $act = strtolower(trim((string) $actionId));
        $file = strtolower(trim((string) ($payload['file'] ?? '')));
        $msg = strtolower(trim((string) ($payload['log_message'] ?? '')));

        if (str_starts_with($act, 'security:exploit_path_ban') || str_starts_with($act, 'security:auto_ip_ban')) {
            return 'Firewall · Honeypot-Abwehr';
        }

        if (str_contains($file, 'xtoollasercockpitservice') || str_contains($msg, 'xtoollasercockpitservice')) {
            return 'Produktion · xTool Laser-Cockpit';
        }

        $exactMap = [
            '/' => 'Shop · Startseite',
            '/shop' => 'Shop · Produktkatalog',
            '/cart' => 'Shop · Warenkorb',
            '/checkout' => 'Shop · Kasse & Checkout',
            '/links' => 'Marketing · Linktree (/links)',
            '/login' => 'Auth · Anmeldung',
            '/admin/dashboard' => 'Leitung · Kommandozentrale',
            '/admin/global-logs' => 'System · Globale Logs',
            '/admin/settings' => 'System · Shop-Konfiguration',
            '/admin/users' => 'System · Benutzerverwaltung',
            '/admin/backups' => 'System · Datenbank-Backups',
            '/admin/shipping' => 'Logistik · Versand & Zonen',
            '/admin/delivery-display' => 'Logistik · Lieferzeiten',
            '/admin/orders' => 'Bestellungen · Übersicht & Laser',
            '/admin/carts' => 'Bestellungen · Warenkörbe',
            '/admin/quote-requests' => 'Bestellungen · Angebotsanfragen',
            '/admin/revocations' => 'Bestellungen · Widerrufe',
            '/admin/products' => 'Produkte · Produktverwaltung',
            '/admin/packaging' => 'Produkte · Verpackungs-Kalkulator',
            '/admin/suppliers' => 'Produkte · Lieferanten',
            '/admin/product-templates' => 'Produkte · Vorlagen',
            '/admin/product-categories' => 'Produkte · Kategorien',
            '/admin/product-attributes' => 'Produkte · Attribute',
            '/admin/product-reviews' => 'Produkte · Bewertungen',
            '/admin/product-fracture' => 'Produkte · Bruch & Verlust',
            '/admin/product-crawler' => 'Produkte · Nischen-Crawler',
            '/admin/financial-fix-costs' => 'Buchhaltung · Fixkosten & Verträge',
            '/admin/financial-variable-costs' => 'Buchhaltung · Sonderausgaben',
            '/admin/financial-invoices' => 'Buchhaltung · Rechnungen & Gutschriften',
            '/admin/financial-banks' => 'Buchhaltung · Bank & Konten',
            '/admin/financial-tax-exports' => 'Buchhaltung · Steuer-Export',
            '/admin/financial-analytics' => 'Buchhaltung · Liquidität & Analyse',
            '/admin/marketing-influencers' => 'Marketing · Influencer & Creator',
            '/admin/marketing-vouchers' => 'Marketing · Gutscheine & Coupons',
            '/admin/marketing-blog' => 'Marketing · Magazin & Blog',
            '/admin/marketing-newsletter' => 'Marketing · Newsletter',
            '/admin/marketing-landing-pages' => 'Marketing · Landingpages',
            '/admin/marketing-herzensprojekte' => 'Marketing · Herzensprojekte',
            '/admin/marketing-linktree' => 'Marketing · Linktree-Editor',
            '/admin/mail' => 'Leitung · E-Mail-Zentrale',
            '/admin/calendar' => 'Leitung · Kalender',
            '/admin/contacts' => 'Leitung · Kontakte',
            '/admin/routine' => 'Leitung · Tagesroutine',
            '/admin/tasks' => 'Leitung · Aufgaben & To-Do',
            '/admin/employees' => 'Leitung · Mitarbeiter & Rollen',
            '/admin/organigramm' => 'KI & 3D · Organigramm & Wasserstadt',
            '/admin/ai-workspace' => 'KI & 3D · KI-Workspace & Cronjobs',
            '/admin/ai/workspace' => 'KI & 3D · KI-Workspace & Cronjobs',
            '/admin/support-tickets' => 'Support · Tickets',
            '/admin/support-chats' => 'Support · Live-Chats',
            '/admin/support-telephony' => 'Support · KI-Telefonie',
            '/employee/influencer/dashboard' => 'Creator Cockpit · Influencer Portal',
            '/employee/steuerberater/dashboard' => 'Steuerberater Cockpit · DATEV Portal',
            '/employee/dashboard' => 'Mitarbeiter · Portal',
            '/customer/dashboard' => 'Kundenkonto · Übersicht',
        ];

        if (isset($exactMap[$clean])) {
            return $exactMap[$clean];
        }

        if (str_starts_with($clean, '/influencer/einladung')) {
            return 'Creator · Einladungs-Onboarding';
        }
        if (str_starts_with($clean, '/mitarbeiter/einladung')) {
            return 'Mitarbeiter · Einladungs-Annahme';
        }
        if (str_starts_with($clean, '/produkt/') || str_starts_with($clean, '/product/')) {
            return 'Shop · Produkt & 3D-Konfigurator';
        }
        if (str_starts_with($clean, 'cli:')) {
            $cmdPart = trim(substr($clean, 4));
            return match (true) {
                str_contains($cmdPart, 'crm:fetch-mails') => 'Cronjob · IMAP E-Mail-Abruf',
                str_contains($cmdPart, 'system:security-audit') => 'Cronjob · Sicherheits- & System-Audit',
                str_contains($cmdPart, 'reverb:start') => 'Daemon · Reverb WebSocket-Server',
                str_contains($cmdPart, 'sentinel') => 'Cronjob · Sentinel Selbstheilung',
                str_contains($cmdPart, 'backup') => 'Cronjob · Datenbank-Backup',
                default => 'Konsole · ' . $cmdPart,
            };
        }
        if (str_starts_with($clean, 'android ·')) {
            return match (true) {
                str_contains($clean, 'financerepo') => 'Android · Finanzen-Modul',
                str_contains($clean, 'organizerrepo') => 'Android · Organizer-Modul',
                str_contains($clean, 'airepo') => 'Android · KI-Agenten-Modul',
                default => trim($subpagePath),
            };
        }
        if (str_starts_with($clean, 'log:')) {
            return 'Server-Log · laravel.log';
        }

        return $subpagePath !== '' ? $subpagePath : 'Webportal · Allgemein';
    }

    /**
     * Prüft, ob dieser Log-Eintrag ein schützenswerter DSGVO-, Einwilligungs- oder Sicherheits-Auditnachweis ist
     * (30 Tage Aufbewahrung gemäß RetentionPolicy::ShortLived_30Days statt 7-Tage Kurzzeit-Archiv).
     */
    public function isAuditProtected(): bool
    {
        $actionId = strtolower((string) $this->action_id);
        $type = strtolower((string) $this->type);

        if ($type === 'security' && $actionId !== 'security:package_audit') {
            return true;
        }

        $protectedPrefixes = [
            'gdpr:',
            'consent:',
            'customer:',
            'user:security_update',
            'security:',
            'admin_order_force_delete',
        ];

        foreach ($protectedPrefixes as $prefix) {
            if (str_starts_with($actionId, $prefix) && $actionId !== 'security:package_audit') {
                return true;
            }
        }

        return false;
    }

    public function scopeOpenIssues(Builder $query): Builder
    {
        return $query->whereIn('status', ['error', 'warning']);
    }

    public function scopeQueuedForAntigravity(Builder $query): Builder
    {
        return $query->openIssues()->where('payload->triage->state', 'queued_for_antigravity');
    }

    public function scopeAutoHealedEntries(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('payload->auto_healed', true)
                ->orWhere('payload->triage->state', 'auto_healed')
                ->orWhere('title', 'like', '[AUTO-GEHEILT]%');
        });
    }

    public function triageState(): ?string
    {
        $payload = is_array($this->payload) ? $this->payload : (json_decode((string) $this->payload, true) ?: []);
        if (!empty($payload['triage']['state']) && is_string($payload['triage']['state'])) {
            return $payload['triage']['state'];
        }
        if (!empty($payload['auto_healed']) || str_starts_with((string) $this->title, '[AUTO-GEHEILT]')) {
            return 'auto_healed';
        }
        return null;
    }

    public function scopeArchivedEntries(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['error', 'warning']);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        $normalized = self::normalizePlatformAlias($platform);
        if ($normalized === null) {
            return $query;
        }

        if ($normalized === 'android') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'android')
                    ->orWhere('action_id', 'like', 'android:%')
                    ->orWhere('payload->platform', 'android');
            });
        }

        if ($normalized === 'minipc_linux') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'minipc_linux')
                    ->orWhere('action_id', 'like', 'minipc_linux:%')
                    ->orWhere('action_id', 'like', 'minipc:%')
                    ->orWhere('payload->platform', 'minipc_linux');
            });
        }

        if ($normalized === 'windows_desktop') {
            return $query->where(function (Builder $q) {
                $q->where('type', 'windows_desktop')
                    ->orWhere('action_id', 'like', 'windows_desktop:%')
                    ->orWhere('action_id', 'like', 'funkenwerk:%')
                    ->orWhere('payload->platform', 'windows_desktop');
            });
        }

        return $query->where(function (Builder $q) {
            $q->where(function (Builder $tq) {
                $tq->whereNull('type')
                    ->orWhereNotIn('type', ['android', 'minipc_linux', 'windows_desktop']);
            })
            ->where(function (Builder $aq) {
                $aq->whereNull('action_id')
                    ->orWhere(function (Builder $naq) {
                        $naq->where('action_id', 'not like', 'android:%')
                            ->where('action_id', 'not like', 'minipc_linux:%')
                            ->where('action_id', 'not like', 'minipc:%')
                            ->where('action_id', 'not like', 'windows_desktop:%')
                            ->where('action_id', 'not like', 'funkenwerk:%');
                    });
            })
            ->where(function (Builder $pq) {
                $pq->whereNull('payload')
                    ->orWhereNull('payload->platform')
                    ->orWhere('payload->platform', 'portal');
            });
        });
    }

    public static function isTransientFrontendNoise(string $message, ?string $title = null): bool
    {
        $combined = strtolower($message . ' | ' . ($title ?? ''));
        $noiseMarkers = [
            'could not find livewire component in dom tree',
            'resizeobserver loop',
            'the user aborted a request',
            'aborterror',
            'networkerror when attempting to fetch resource',
            'failed to fetch',
            'unexpected token \'<\', "<!doctype',
            'is not valid json',
        ];

        foreach ($noiseMarkers as $marker) {
            if (str_contains($combined, $marker)) {
                return true;
            }
        }

        if (class_exists(\App\Services\System\SystemErrorLogger::class)
            && \App\Services\System\SystemErrorLogger::isBenignClientCancellation($message, null, $title)) {
            return true;
        }

        return false;
    }

    /**
     * Erstellt einen SystemLog-Eintrag mit automatischer Bereichs-Kennzeichnung (Webportal, Android, Mini-PC, Funkenwerk)
     * und 24h-Deduplizierung (occurrences_count) gegen Log-Flutung.
     */
    public static function create(array $attributes = [])
    {
        $rawPayload = $attributes['payload'] ?? [];
        $payload = is_array($rawPayload)
            ? $rawPayload
            : (is_string($rawPayload) ? (json_decode($rawPayload, true) ?: []) : []);

        $rawType = trim((string) ($attributes['type'] ?? 'system'));
        $type = self::clampColumnValue($rawType !== '' ? $rawType : 'system', self::MAX_TYPE_LENGTH, '');
        $actionId = self::clampColumnValue((string) ($attributes['action_id'] ?? ''), self::MAX_ACTION_ID_LENGTH, '');
        $title = self::clampColumnValue((string) ($attributes['title'] ?? ''), self::MAX_TITLE_LENGTH, '...');
        $message = array_key_exists('message', $attributes) && $attributes['message'] !== null
            ? self::clampColumnValue((string) $attributes['message'], self::MAX_MESSAGE_LENGTH, '...')
            : '';
        $rawStatus = trim((string) ($attributes['status'] ?? 'info'));
        $status = self::clampColumnValue($rawStatus !== '' ? $rawStatus : 'info', self::MAX_STATUS_LENGTH, '');

        $attributes['type'] = $type;
        $attributes['action_id'] = $actionId;
        $attributes['title'] = $title;
        $attributes['status'] = $status;
        if (array_key_exists('message', $attributes) && $attributes['message'] !== null) {
            $attributes['message'] = $message;
        }

        $platformKey = self::detectPlatformKey($type, $actionId, $payload);
        $payload['platform'] = $payload['platform'] ?? $platformKey;
        $payload['platform_label'] = $payload['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');

        if (class_exists(\App\Services\System\SystemErrorLogger::class)) {
            if (empty($payload['server_environment'])) {
                $payload['server_environment'] = \App\Services\System\SystemErrorLogger::buildServerEnvironment();
            }
            if (empty($payload['origin_environment'])) {
                $payload['origin_environment'] = $payload['server_environment']['resolved_env'] ?? config('app.env', 'local');
            }
            if (empty($payload['execution_context']) && in_array($status, ['error', 'warning'], true)) {
                $payload['execution_context'] = \App\Services\System\SystemErrorLogger::buildExecutionContext();
            }
            if (empty($payload['subpage_path']) && !empty($payload['execution_context']['subpage_path'])) {
                $payload['subpage_path'] = $payload['execution_context']['subpage_path'];
            }
            if (empty($payload['diagnosis']) && in_array($status, ['error', 'warning'], true)) {
                $payload['diagnosis'] = \App\Services\System\SystemErrorLogger::diagnose(
                    $message,
                    isset($payload['exception_class']) ? (string) $payload['exception_class'] : null,
                    isset($payload['file']) ? (string) $payload['file'] : null
                );
            }
        }

        if ($type === 'frontend_error' && self::isTransientFrontendNoise($message, $title)) {
            $attributes['payload'] = $payload;
            return new static($attributes);
        }

        // 24h-Deduplizierung für offene Fehler/Warnungen ohne vorab berechneten Fingerprint (z. B. /api/log/frontend-error, /api/log/websocket, Security-Events)
        if (in_array($status, ['error', 'warning'], true) && empty($payload['fingerprint'])) {
            $normalizedMsg = preg_replace('/\b\d+\b/', '#', strtolower(trim($message)));
            $fileSig = (string) ($payload['file'] ?? $payload['source'] ?? '');
            $lineSig = (string) ($payload['line'] ?? $payload['lineno'] ?? '');
            $fingerprint = hash('sha256', strtolower($type . '|' . $actionId . '|' . trim($title) . '|' . $fileSig . '|' . $lineSig . '|' . mb_substr((string) $normalizedMsg, 0, 180)));
            $payload['fingerprint'] = $fingerprint;

            try {
                $candidates = static::query()
                    ->where('type', $type)
                    ->where('action_id', $actionId)
                    ->whereIn('status', ['error', 'warning'])
                    ->where(function (Builder $q) {
                        $q->where('started_at', '>=', now()->subHours(24))
                            ->orWhere('created_at', '>=', now()->subHours(24));
                    })
                    ->latest('id')
                    ->limit(15)
                    ->get();

                foreach ($candidates as $candidate) {
                    $cp = is_array($candidate->payload) ? $candidate->payload : [];
                    $candNormMsg = preg_replace('/\b\d+\b/', '#', strtolower(trim((string) $candidate->message)));
                    $candFp = $cp['fingerprint'] ?? hash(
                        'sha256',
                        strtolower(
                            (string) $candidate->type . '|'
                            . (string) $candidate->action_id . '|'
                            . trim((string) $candidate->title) . '|'
                            . (string) ($cp['file'] ?? $cp['source'] ?? '') . '|'
                            . (string) ($cp['line'] ?? $cp['lineno'] ?? '') . '|'
                            . mb_substr((string) $candNormMsg, 0, 180)
                        )
                    );

                    if ($candFp === $fingerprint) {
                        $cp['fingerprint'] = $fingerprint;
                        $cp['platform'] = $cp['platform'] ?? $platformKey;
                        $cp['platform_label'] = $cp['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');
                        $cp['occurrences_count'] = ((int) ($cp['occurrences_count'] ?? 1)) + 1;
                        $cp['last_seen_at'] = now()->toIso8601String();
                        $candidate->payload = $cp;
                        $candidate->finished_at = now();
                        if ($status === 'error' && $candidate->status === 'warning') {
                            $candidate->status = 'error';
                        }
                        $candidate->save();

                        return $candidate;
                    }
                }
            } catch (\Throwable $ignore) {
            }

            $payload['occurrences_count'] = (int) ($payload['occurrences_count'] ?? 1);
            $payload['first_seen_at'] = $payload['first_seen_at'] ?? now()->toIso8601String();
            $payload['last_seen_at'] = $payload['last_seen_at'] ?? now()->toIso8601String();
        } elseif (
            in_array($status, ['success', 'info'], true)
            && ($type === 'websocket' || $actionId === 'security:package_audit')
        ) {
            try {
                $existingRoutine = static::query()
                    ->where('type', $type)
                    ->where('action_id', $actionId)
                    ->whereIn('status', ['success', 'info'])
                    ->where('created_at', '>=', now()->subHours(24))
                    ->latest('id')
                    ->first();

                if ($existingRoutine) {
                    $rp = is_array($existingRoutine->payload) ? $existingRoutine->payload : [];
                    $rp['platform'] = $rp['platform'] ?? $platformKey;
                    $rp['platform_label'] = $rp['platform_label'] ?? (self::PLATFORMS[$platformKey]['label'] ?? 'Webportal');
                    $rp['occurrences_count'] = ((int) ($rp['occurrences_count'] ?? 1)) + 1;
                    $rp['last_seen_at'] = now()->toIso8601String();
                    $existingRoutine->message = $message !== '' ? $message : $existingRoutine->message;
                    $existingRoutine->payload = array_merge($rp, $payload, [
                        'occurrences_count' => $rp['occurrences_count'],
                        'last_seen_at' => $rp['last_seen_at'],
                    ]);
                    $existingRoutine->finished_at = now();
                    $existingRoutine->save();

                    return $existingRoutine;
                }
            } catch (\Throwable $ignore) {
            }
        }

        $attributes['payload'] = $payload;

        return static::query()->create($attributes);
    }

    /**
     * Helper um einen Log-Eintrag schnell zu starten
     */
    public static function start($actionId, $title, $type = 'automation', $aiAgentId = null)
    {
        return self::create([
            'ai_agent_id' => $aiAgentId,
            'action_id' => $actionId,
            'title' => $title,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Helper um einen laufenden Log zu beenden
     */
    public function finish($status = 'success', $message = null, $payload = null)
    {
        $this->update([
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'finished_at' => now(),
        ]);
    }
}
