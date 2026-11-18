<?php

namespace App\Services\Gamification;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Services\Shop\ShowroomChatGuardService;
use App\Services\System\SystemSecurityService;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LobbyInstanceService
{
    public const SOFT_CAP = 16;
    public const HARD_CAP = 20;
    public const HEARTBEAT_TTL_SECONDS = 25;
    public const MAX_INSTANCES = 50;
    public const MAX_SPEED = 9.5;
    public const MIN_SYNC_TELEPORT_STEP = 14.0;
    public const MAX_SPEED_WITH_MARGIN = 13.5;
    public const EXTREME_COORD_THRESHOLD = 500.0;
    public const MAGMA_PRESTIGE_MULTIPLIER = 15;

    public const CHAT_RING_BUFFER_SIZE = 25;
    public const CHAT_TTL_SECONDS = 120;
    public const CHAT_MAX_LENGTH = 180;
    public const CHAT_COOLDOWN_SECONDS = 2;
    public const CHAT_BURST_WINDOW_SECONDS = 15;
    public const CHAT_BURST_MAX_ATTEMPTS = 8;
    public const REPORT_THRESHOLD = 2;
    public const SHADOW_MUTE_SECONDS = 86400;
    public const ACTOR_CACHE_TTL_SECONDS = 6;

    /**
     * Solo-Zonen: Jeder Besucher ist hier komplett alleine unterwegs (belegt keinen Instanz-Slot).
     *
     * @var array<int, string>
     */
    public const SOLO_ZONES = [
        'gallery',
        'lava',
    ];

    /**
     * Multiplayer-Zonen: Eingeloggte Besucher sehen sich gemeinsam ueber Instanzen (#1..50).
     *
     * @var array<int, string>
     */
    public const MULTIPLAYER_ZONES = [
        'heroes',
    ];

    /**
     * Zonen, die serverseitig strikt nur fuer eingeloggte Nutzer zugaenglich sind.
     *
     * @var array<int, string>
     */
    public const AUTH_REQUIRED_ZONES = [
        'lava',
        'heroes',
    ];

    /**
     * Erlaubte Emote-Schluessel in der Halle der Helden.
     *
     * @var array<int, string>
     */
    public const ALLOWED_EMOTES = [
        '',
        'wave',
        'dance',
        'cheer',
        'bow',
        'spark',
        'sit',
        'clap',
    ];

    private const CACHE_ACTIVE_IDS_KEY = 'sf_fw_lobby_active_instances_v1';
    private const CACHE_INSTANCE_PREFIX = 'sf_fw_lobby_inst_v1_';
    private const CACHE_INST_LOCK_PREFIX = 'sf_fw_lobby_inst_lock_v1_';
    private const CACHE_INST_CHAT_PREFIX = 'sf_fw_lobby_chat_v1_';
    private const CACHE_PLAYER_PTR_PREFIX = 'sf_fw_lobby_pptr_v1_';
    private const CACHE_PEER_TO_CUST_PREFIX = 'sf_fw_lobby_pid_map_v1_';
    private const CACHE_CUST_MUTE_PREFIX = 'sf_lobby_mute_v1_';
    private const CACHE_CUST_REPORTS_PREFIX = 'sf_lobby_reports_v1_';
    private const CACHE_SANCTUM_ACCESS_PREFIX = 'sf_fw_sanctum_v1_';
    private const CACHE_CUST_ACTOR_PREFIX = 'sf_fw_actor_v1_';
    private const CACHE_LOCK_KEY = 'sf_fw_lobby_mutex_v1';
    private const CACHE_TAMPER_LOG_PREFIX = 'sf_sec_lobby_tamper_v1_';

    /**
     * Explizite Konsolen-Cheat-Flags, die auf allen Lobby-, Showroom- & Parkour-Endpunkten sofort mit 422 abgelehnt werden.
     *
     * @var array<int, string>
     */
    public const HARD_REJECT_CHEAT_KEYS = [
        'command',
        'cmd',
        'exec',
        'eval',
        'script',
        'admin',
        'admin_override',
        'adminOverride',
        'god_mode',
        'godMode',
        'give_sparks',
        'giveSparks',
        'teleport_all',
        'teleportAll',
        'teleport_hack',
        'set_level',
        'setLevel',
        'cheat',
        'cheats',
        'noclip',
        'no_clip',
        'infinite_sparks',
        'speedhack',
        'speed_hack',
        'bypass_anticheat',
        'unlock_all',
        'force',
        'force_claim',
    ];

    /**
     * Verbotene Konsolen-, Command- und Manipulations-Felder (fuehren im Controller sofort zu HTTP 422).
     *
     * @var array<int, string>
     */
    public const FORBIDDEN_CONSOLE_KEYS = [
        'command',
        'cmd',
        'exec',
        'eval',
        'script',
        'admin',
        'is_admin',
        'isAdmin',
        'admin_override',
        'adminOverride',
        'god_mode',
        'godMode',
        'give_sparks',
        'giveSparks',
        'sparks',
        'funken',
        'funken_balance',
        'funken_total_earned',
        'amount',
        'reward',
        'rewarded_sparks',
        'sparks_awarded',
        'daily_reward',
        'teleport_all',
        'teleportAll',
        'teleport_hack',
        'set_level',
        'setLevel',
        'cheat',
        'cheats',
        'noclip',
        'no_clip',
        'infinite_sparks',
        'speedhack',
        'speed_hack',
        'bypass_anticheat',
        'unlock_all',
        'force',
        'force_claim',
    ];

    /**
     * Explizite Konsolen-Cheat-Flags (Abwaertskompatible Konstante).
     *
     * @var array<int, string>
     */
    public const EXPLICIT_CHEAT_KEYS = self::FORBIDDEN_CONSOLE_KEYS;

    /**
     * Server-autoritative Profil- und Identitaetsfelder, denen vom Client niemals vertraut wird.
     *
     * @var array<int, string>
     */
    public const FORGED_AUTHORITY_KEYS = [
        'level',
        'level_title',
        'levelTitle',
        'display_name',
        'displayName',
        'player_name',
        'playerName',
        'own_display_name',
        'peer_display_name',
        'ship_class',
        'shipClass',
        'rank_class',
        'class_label',
        'weapon',
        'weapon_effect',
        'glb_url',
        'model_url',
        'role',
        'customer_id',
        'customerId',
        'peer_id',
        'peerId',
        'player_id',
        'playerId',
        'actor_key',
        'titles',
        'unlocked_titles',
        'titles_progress',
    ];

    /**
     * Prueft rekursiv, ob ein Request-Array verbotene Cheat- oder Command-Schluessel enthaelt.
     *
     * @param array<mixed> $payload
     */
    public static function containsForbiddenPayloadKeys(array $payload, int $depth = 0): bool
    {
        if ($depth > 5) {
            return true;
        }

        $lookup = array_flip(self::FORBIDDEN_CONSOLE_KEYS);

        foreach ($payload as $key => $value) {
            if (is_string($key) && isset($lookup[$key])) {
                return true;
            }
            if (is_array($value) && self::containsForbiddenPayloadKeys($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prueft eingehende Request-Parameter auf Konsolen-Cheats, gefaelschte Autoritaetsfelder
     * oder nicht-finite / extreme Koordinaten und protokolliert Manipulationsversuche gedrosselt.
     *
     * @param array<string, mixed> $rawInput
     * @return array{
     *     has_explicit_cheat: bool,
     *     has_forged_authority: bool,
     *     has_invalid_coords: bool,
     *     flagged_keys: array<int, string>
     * }
     */
    public static function inspectRequestTampering(
        array $rawInput,
        string $endpoint,
        ?string $actorIdentifier = null
    ): array {
        $explicitMatched = [];
        foreach (self::HARD_REJECT_CHEAT_KEYS as $key) {
            if (array_key_exists($key, $rawInput)) {
                $explicitMatched[] = $key;
            }
        }

        $authorityCheckKeys = array_merge(self::FORGED_AUTHORITY_KEYS, [
            'is_admin',
            'isAdmin',
            'sparks',
            'funken',
            'funken_balance',
            'funken_total_earned',
            'amount',
            'reward',
            'rewarded_sparks',
            'sparks_awarded',
            'daily_reward',
        ]);

        $isLavaParkourEndpoint = str_contains($endpoint, 'lava-parkour');
        $isReportEndpoint = str_contains($endpoint, 'report');
        $forgedMatched = [];
        foreach ($authorityCheckKeys as $key) {
            if (!array_key_exists($key, $rawInput)) {
                continue;
            }
            if ($key === 'level' && $isLavaParkourEndpoint && self::isValidFiniteNumeric($rawInput['level'])) {
                continue;
            }
            if (in_array($key, ['peer_id', 'peerId', 'player_id', 'playerId'], true) && $isReportEndpoint && is_string($rawInput[$key])) {
                continue;
            }
            $forgedMatched[] = $key;
        }

        $coordFlags = [];
        foreach (['x', 'y', 'z', 'vx', 'vz', 'rel_x', 'rel_y', 'rel_z', 'rot_y', 'rotY'] as $coordKey) {
            if (!array_key_exists($coordKey, $rawInput) || $rawInput[$coordKey] === null || $rawInput[$coordKey] === '') {
                continue;
            }
            $val = $rawInput[$coordKey];
            if (!self::isValidFiniteNumeric($val)) {
                $coordFlags[] = $coordKey . ':non_finite';
                continue;
            }
            if (abs((float) $val) > self::EXTREME_COORD_THRESHOLD) {
                $coordFlags[] = $coordKey . ':extreme';
            }
        }

        if (array_key_exists('speed', $rawInput) && $rawInput['speed'] !== null && $rawInput['speed'] !== '') {
            $rawSpd = $rawInput['speed'];
            if (!self::isValidFiniteNumeric($rawSpd)) {
                $coordFlags[] = 'speed:non_finite';
            } elseif ((float) $rawSpd < 0.0 || (float) $rawSpd > 25.0) {
                $coordFlags[] = 'speed:extreme';
            }
        }

        $actorKey = ($actorIdentifier !== null && $actorIdentifier !== '')
            ? $actorIdentifier
            : ('ip:' . (string) (request()?->ip() ?: 'unknown'));

        if (!empty($explicitMatched)) {
            self::logTamperThrottled($actorKey, $endpoint, 'explicit_cheat_params', [
                'cheat_keys' => $explicitMatched,
            ]);
        }

        if (!empty($forgedMatched)) {
            self::logTamperThrottled($actorKey, $endpoint, 'forged_authority_fields', [
                'forged_keys' => $forgedMatched,
            ]);
        }

        if (!empty($coordFlags)) {
            self::logTamperThrottled($actorKey, $endpoint, 'invalid_or_extreme_motion', [
                'motion_flags' => $coordFlags,
            ]);
        }

        return [
            'has_explicit_cheat' => !empty($explicitMatched),
            'has_forged_authority' => !empty($forgedMatched),
            'has_invalid_coords' => !empty($coordFlags),
            'flagged_keys' => array_values(array_unique(array_merge($explicitMatched, $forgedMatched, $coordFlags))),
        ];
    }

    /**
     * Protokolliert abgewehrte Lobby-/Showroom-/Parkour-Manipulationsversuche gedrosselt (max. 1x pro 30s je Vektor).
     *
     * @param array<string, mixed> $extraContext
     */
    public static function logTamperThrottled(
        string $actorKey,
        string $endpoint,
        string $reason,
        array $extraContext = []
    ): void {
        try {
            $throttleKey = self::CACHE_TAMPER_LOG_PREFIX . md5($actorKey . '|' . $endpoint . '|' . $reason);
            if (!Cache::add($throttleKey, 1, 30)) {
                return;
            }

            SystemSecurityService::logEvent(
                'security:lobby_tamper_blocked',
                'Spiele-Lobby Manipulation abgewehrt',
                sprintf(
                    'Manipulationsversuch auf [%s] blockiert (Grund: %s, Akteur: %s).',
                    $endpoint,
                    $reason,
                    substr($actorKey, 0, 64)
                ),
                'warning',
                array_merge([
                    'category' => 'idor_integrity',
                    'endpoint' => $endpoint,
                    'reason' => $reason,
                    'actor' => substr($actorKey, 0, 64),
                ], $extraContext)
            );
        } catch (\Throwable) {
            // Niemals Hauptprozess durch Security-Logging unterbrechen
        }
    }

    /**
     * Strikte Pruefung auf echte endliche Dezimal-/Ganzzahl (lehnt Arrays, Objekte, Booleans, NaN, INF, Infinity ab).
     */
    public static function isValidFiniteNumeric(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_float($value)) {
            return is_finite($value);
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || !is_numeric($trimmed)) {
                return false;
            }
            $upper = strtoupper($trimmed);
            if (str_contains($upper, 'NAN') || str_contains($upper, 'INF')) {
                return false;
            }

            return is_finite((float) $trimmed);
        }

        return false;
    }

    /**
     * Extrahiert einen garantierten endlichen Float-Wert oder liefert den sicheren Fallback.
     */
    public static function parseFiniteFloat(mixed $value, float $fallback = 0.0): float
    {
        if (!self::isValidFiniteNumeric($value)) {
            return is_finite($fallback) ? $fallback : 0.0;
        }

        $parsed = (float) $value;

        return is_finite($parsed) ? $parsed : (is_finite($fallback) ? $fallback : 0.0);
    }

    /**
     * Normalisiert einen Zonen-Namen auf erlaubte Zonen (unbekannte Zonen fallen auf 'gallery' zurueck).
     */
    public static function normalizeZone(mixed $rawZone, string $defaultZone = 'gallery'): string
    {
        if (!is_string($rawZone)) {
            return $defaultZone;
        }

        $trimmed = strtolower(trim($rawZone));
        if ($trimmed === '') {
            return $defaultZone;
        }

        if (in_array($trimmed, self::SOLO_ZONES, true) || in_array($trimmed, self::MULTIPLAYER_ZONES, true)) {
            return $trimmed;
        }

        return 'gallery';
    }

    public static function isSoloZone(string $zone): bool
    {
        return !in_array($zone, self::MULTIPLAYER_ZONES, true);
    }

    public static function isAuthRequiredZone(mixed $rawZone): bool
    {
        if (!is_string($rawZone)) {
            return false;
        }

        return in_array(strtolower(trim($rawZone)), self::AUTH_REQUIRED_ZONES, true);
    }

    /**
     * Bildet alle normierten Bestellnummer-Kandidaten fuer den Abgleich mit order_revocations.
     *
     * @return array<int, string>
     */
    public static function orderNumberRevocationCandidates(string $rawNumber): array
    {
        $trimmed = trim($rawNumber);
        if ($trimmed === '') {
            return [];
        }

        $cleanNumber = strtoupper(trim(ltrim((string) preg_replace('/^(bestellung|order|nr\.?|rechnung)\s*/i', '', $trimmed), '#')));

        return array_values(array_unique(array_filter([
            $trimmed,
            strtoupper($trimmed),
            $cleanNumber,
            $cleanNumber !== '' ? ('ORD-' . $cleanNumber) : '',
            $cleanNumber !== '' ? ('SF-' . $cleanNumber) : '',
        ])));
    }

    /**
     * Ermittelt fuer eine Liste von Kunden-IDs in konstanten Queries alle Kunden mit mindestens 1 bezahlten,
     * nicht stornierten, nicht erstatteten und nicht widerrufenen Bestellung.
     *
     * @param array<int, string> $customerIds
     * @return array<string, bool>
     */
    public static function resolveCustomerIdsWithValidPaidOrder(array $customerIds): array
    {
        $uniqueIds = array_values(array_unique(array_filter(array_map('strval', $customerIds))));
        if (empty($uniqueIds) || !Schema::hasTable('order_orders')) {
            return [];
        }

        $orders = OrderOrder::query()
            ->select(['id', 'customer_id', 'order_number'])
            ->whereIn('customer_id', $uniqueIds)
            ->where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded', 'revoked'])
            ->whereNotIn('payment_status', ['refunded', 'cancelled', 'revoked', 'failed'])
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $revokedLookup = [];
        if (Schema::hasTable('order_revocations')) {
            $allCandidates = [];
            foreach ($orders as $order) {
                foreach (self::orderNumberRevocationCandidates((string) ($order->order_number ?? '')) as $cand) {
                    $allCandidates[$cand] = $cand;
                }
            }

            if (!empty($allCandidates)) {
                $revokedNumbers = OrderRevocation::query()
                    ->whereIn('order_number', array_values($allCandidates))
                    ->whereIn('status', ['approved', 'processed', 'completed'])
                    ->pluck('order_number')
                    ->all();

                foreach ($revokedNumbers as $revNum) {
                    foreach (self::orderNumberRevocationCandidates((string) $revNum) as $cand) {
                        $revokedLookup[strtoupper($cand)] = true;
                    }
                }
            }
        }

        $validCustomers = [];
        foreach ($orders as $order) {
            $cid = (string) $order->customer_id;
            if (isset($validCustomers[$cid])) {
                continue;
            }

            $isRevoked = false;
            if (!empty($revokedLookup)) {
                foreach (self::orderNumberRevocationCandidates((string) ($order->order_number ?? '')) as $cand) {
                    if (isset($revokedLookup[strtoupper($cand)])) {
                        $isRevoked = true;
                        break;
                    }
                }
            }

            if (!$isRevoked) {
                $validCustomers[$cid] = true;
            }
        }

        return $validCustomers;
    }

    public static function hasValidPaidNonRevokedOrder(string $customerId): bool
    {
        if ($customerId === '') {
            return false;
        }

        $lookup = self::resolveCustomerIdsWithValidPaidOrder([$customerId]);

        return isset($lookup[$customerId]);
    }

    /**
     * Invalidiert den 6-Sekunden-Cache fuer Sanctum-Zugang und Lobby-Akteur eines Kunden sofort.
     */
    public static function forgetCustomerCache(string $customerId): void
    {
        $cleanId = trim($customerId);
        if ($cleanId === '') {
            return;
        }

        try {
            Cache::forget(self::CACHE_SANCTUM_ACCESS_PREFIX . $cleanId);
            Cache::forget(self::CACHE_CUST_ACTOR_PREFIX . $cleanId);
        } catch (\Throwable) {
        }
    }

    /**
     * Prueft zentral die verbindlichen Voraussetzungen fuer die Halle der Helden ('heroes')
     * und die Magma-Pruefung ('lava', Raum 1 bis 5 kostenlos ab aktivierter Magie, Raum 6 bis 10 mit Meisterschluessel):
     * 1. Aktiver, verifizierter Kunde ohne offenes Initialpasswort
     * 2. Seelenfunken-Magie aktiviert (magic_activation_required)
     * (has_completed_order wird als Info fuer den Meisterschluessel mitgeliefert, blockiert aber NICHT can_enter_sanctums!)
     *
     * @return array{
     *     is_authenticated: bool,
     *     has_completed_order: bool,
     *     gamification_active: bool,
     *     can_enter_sanctums: bool,
     *     sanctum_block_reason: ?string,
     *     login_url: string,
     *     dashboard_url: string,
     *     message: ?string
     * }
     */
    public static function evaluateSanctumAccess(?Customer $customer): array
    {
        $loginUrl = '/login';
        $dashboardUrl = '/dashboard';

        if ($customer === null) {
            return [
                'is_authenticated' => false,
                'has_completed_order' => false,
                'gamification_active' => false,
                'can_enter_sanctums' => false,
                'sanctum_block_reason' => 'login_required',
                'login_url' => $loginUrl,
                'dashboard_url' => $dashboardUrl,
                'message' => 'Bitte melde dich an, um die Halle der Helden oder das Magma-Level zu betreten.',
            ];
        }

        if (method_exists($customer, 'trashed') && $customer->trashed()) {
            return [
                'is_authenticated' => false,
                'has_completed_order' => false,
                'gamification_active' => false,
                'can_enter_sanctums' => false,
                'sanctum_block_reason' => 'account_deactivated',
                'login_url' => $loginUrl,
                'dashboard_url' => $dashboardUrl,
                'message' => 'Dieses Kundenkonto ist deaktiviert.',
            ];
        }

        if (method_exists($customer, 'hasVerifiedEmail') && !$customer->hasVerifiedEmail()) {
            return [
                'is_authenticated' => true,
                'has_completed_order' => false,
                'gamification_active' => false,
                'can_enter_sanctums' => false,
                'sanctum_block_reason' => 'email_verification_required',
                'login_url' => $loginUrl,
                'dashboard_url' => '/email/verify',
                'message' => 'Bitte bestätige zuerst deine E-Mail-Adresse über den Link in deinem Postfach.',
            ];
        }

        if (!empty($customer->needs_password_change)) {
            return [
                'is_authenticated' => true,
                'has_completed_order' => false,
                'gamification_active' => false,
                'can_enter_sanctums' => false,
                'sanctum_block_reason' => 'password_change_required',
                'login_url' => $loginUrl,
                'dashboard_url' => '/customer/password-change-force',
                'message' => 'Bitte ändere zuerst dein vorläufiges Passwort.',
            ];
        }

        $customerId = (string) $customer->id;
        $cacheKey = self::CACHE_SANCTUM_ACCESS_PREFIX . $customerId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['gamification_active'])) {
            return $cached;
        }

        $hasCompletedOrder = self::hasValidPaidNonRevokedOrder($customerId);

        $gamificationActive = Schema::hasTable('customer_gamifications')
            && CustomerGamification::where('customer_id', $customerId)
                ->where('is_active', true)
                ->exists();

        $canEnterSanctums = $gamificationActive;
        $blockReason = null;
        $message = null;

        if (!$gamificationActive) {
            $blockReason = 'magic_activation_required';
            $message = 'Bitte aktiviere zuerst deine Seelenfunken-Magie, um die Halle der Helden und die Magma-Prüfung zu betreten.';
        }

        $result = [
            'is_authenticated' => true,
            'has_completed_order' => $hasCompletedOrder,
            'gamification_active' => $gamificationActive,
            'can_enter_sanctums' => $canEnterSanctums,
            'sanctum_block_reason' => $blockReason,
            'login_url' => $loginUrl,
            'dashboard_url' => $dashboardUrl,
            'message' => $message,
        ];

        if ($gamificationActive) {
            Cache::put($cacheKey, $result, self::ACTOR_CACHE_TTL_SECONDS);
        }

        return $result;
    }

    /**
     * Prueft fuer geschuetzte Zonen ('lava' oder 'heroes') alle 3 Voraussetzungen.
     * Die Produkt-Lobby ('gallery' / 'lobby') bleibt fuer alle Besucher frei zugaenglich.
     *
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public function checkZoneSanctumRestriction(?Customer $customer, mixed $rawZone, bool $isAdmin = false): ?array
    {
        if ($isAdmin || !self::isAuthRequiredZone($rawZone)) {
            return null;
        }

        $access = self::evaluateSanctumAccess($customer);
        if ($access['can_enter_sanctums']) {
            return null;
        }

        $zone = strtolower(trim((string) $rawZone));

        return [
            'status' => 403,
            'body' => [
                'ok' => false,
                'success' => false,
                'authenticated' => $access['is_authenticated'],
                'guest' => !$access['is_authenticated'],
                'code' => $access['sanctum_block_reason'],
                'sanctum_block_reason' => $access['sanctum_block_reason'],
                'has_completed_order' => $access['has_completed_order'],
                'gamification_active' => $access['gamification_active'],
                'can_enter_sanctums' => false,
                'login_url' => $access['login_url'],
                'dashboard_url' => $access['dashboard_url'],
                'zone' => $zone,
                'message' => (string) $access['message'],
            ],
        ];
    }

    /**
     * Prueft fuer nicht eingeloggte Gaeste, ob eine geschuetzte Zone ('lava' oder 'heroes') angefragt wurde.
     *
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public function checkGuestZoneRestriction(mixed $rawZone): ?array
    {
        return $this->checkZoneSanctumRestriction(null, $rawZone, false);
    }

    /**
     * Ermittelt den aktuell authentifizierten Spieler (Customer oder Admin) strikt Zero-PII.
     * Gibt fuer Gaeste null zurueck.
     *
     * @return array{
     *     actor_key: string,
     *     peer_id: string,
     *     own_display_name: string,
     *     peer_display_name: string,
     *     level: int,
     *     level_title: string,
     *     ship_class: string
     * }|null
     */
    public function resolveActorFromAuth(): ?array
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            return $this->buildCustomerActor($customer);
        }

        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if ($admin instanceof Admin) {
            return $this->buildAdminActor($admin);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function guestJoinPayload(): array
    {
        return [
            'ok' => true,
            'authenticated' => false,
            'solo_mode' => true,
            'zone' => 'gallery',
            'instance_id' => null,
            'soft_cap' => self::SOFT_CAP,
            'hard_cap' => self::HARD_CAP,
            'player' => null,
            'peers' => [],
            'messages' => [],
            'instances' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function guestSyncPayload(): array
    {
        return [
            'ok' => true,
            'authenticated' => false,
            'solo_mode' => true,
            'zone' => 'gallery',
            'instance_id' => null,
            'peers' => [],
            'messages' => [],
            'instances' => [],
        ];
    }

    /**
     * Bucht einen eingeloggten Spieler zonenabhaengig ein:
     * - Solo-Zonen ('gallery', 'lava'): solo_mode = true, gibt ggf. belegten Heldenhallen-Slot frei.
     * - Halle der Helden ('heroes'): Auto-Sharding < SOFT_CAP = 16 oder Wunsch-Instanz < HARD_CAP = 20.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function join(array $actor, array $input = []): array
    {
        if (empty($actor['peer_id']) || empty($actor['actor_key'])) {
            if ($denied = $this->checkGuestZoneRestriction($input['zone'] ?? null)) {
                return $denied['body'];
            }

            return $this->guestJoinPayload();
        }

        $zone = self::normalizeZone($input['zone'] ?? null, 'gallery');

        if (self::isSoloZone($zone)) {
            $this->evictPlayerFromHeroInstances((string) $actor['peer_id']);

            return $this->buildSoloModePayload($actor, $zone);
        }

        $peerId = (string) $actor['peer_id'];

        return $this->withLobbyLock(
            function () use ($actor, $input, $peerId): array {
                $now = Carbon::now()->getTimestamp();
                $nowMs = Carbon::now()->getTimestampMs();
                $actorKey = (string) ($actor['actor_key'] ?? $peerId);

                $rosters = $this->loadAndPruneActiveRosters($now);
                $currentInstanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

                $rawPreferred = $input['preferred_instance'] ?? null;
                $preferredInstance = (
                    is_numeric($rawPreferred)
                    && is_finite((float) $rawPreferred)
                    && (int) $rawPreferred >= 1
                    && (int) $rawPreferred <= self::MAX_INSTANCES
                )
                    ? (int) $rawPreferred
                    : null;

                $selectedInstance = null;

                if ($preferredInstance !== null) {
                    $prefRoster = $rosters[$preferredInstance] ?? $this->loadPrunedInstanceRoster($preferredInstance, $now);
                    $otherCount = $this->countOtherPlayers($prefRoster, $peerId);
                    if ($otherCount < self::HARD_CAP) {
                        $selectedInstance = $preferredInstance;
                        $rosters[$preferredInstance] = $prefRoster;
                    }
                }

                if ($selectedInstance === null && $currentInstanceId !== null) {
                    $selectedInstance = $currentInstanceId;
                }

                if ($selectedInstance === null) {
                    $selectedInstance = $this->findAutoShardInstance($rosters, $peerId, $now);
                    if (!isset($rosters[$selectedInstance])) {
                        $rosters[$selectedInstance] = $this->loadPrunedInstanceRoster($selectedInstance, $now);
                    }
                }

                $previousEntry = ($currentInstanceId !== null && isset($rosters[$currentInstanceId][$peerId]))
                    ? $rosters[$currentInstanceId][$peerId]
                    : null;

                if ($currentInstanceId !== null && $currentInstanceId !== $selectedInstance) {
                    unset($rosters[$currentInstanceId][$peerId]);
                    $this->persistInstanceRoster($currentInstanceId, $rosters[$currentInstanceId] ?? [], $rosters);
                }

                $input['zone'] = 'heroes';
                $state = $this->sanitizeState($input, $previousEntry, 'heroes', $nowMs, $actorKey);
                $rosters[$selectedInstance][$peerId] = $this->buildRosterEntry($actor, $state, $now);

                $this->persistInstanceRoster($selectedInstance, $rosters[$selectedInstance], $rosters);
                $this->persistActiveIdsIndex($rosters);
                Cache::put($this->playerPointerKey($peerId), $selectedInstance, self::HEARTBEAT_TTL_SECONDS);

                return [
                    'ok' => true,
                    'authenticated' => true,
                    'solo_mode' => false,
                    'zone' => 'heroes',
                    'instance_id' => $selectedInstance,
                    'soft_cap' => self::SOFT_CAP,
                    'hard_cap' => self::HARD_CAP,
                    'player' => $this->formatPlayerSelf($actor),
                    'peers' => $this->formatPeersList($rosters[$selectedInstance], $peerId),
                    'messages' => $this->getInstanceMessages($selectedInstance, $peerId),
                    'instances' => $this->buildInstancesOverview($rosters, $selectedInstance),
                ];
            },
            fn (): array => $this->buildReadOnlyHeroSnapshot($actor, $peerId)
        );
    }

    /**
     * Aktualisiert Position, Blickrichtung, Geschwindigkeit und Zone inkl. 25s Heartbeat-Verlaengerung.
     * Nutzt einen schnellen Per-Instanz-Lock (sf_fw_lobby_inst_lock_v1_{instanceId}) ohne globalen Mutex
     * und liefert sowohl peers als auch die Instanz-Chat-Nachrichten (messages) zurueck.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sync(array $actor, array $input = []): array
    {
        if (empty($actor['peer_id']) || empty($actor['actor_key'])) {
            if ($denied = $this->checkGuestZoneRestriction($input['zone'] ?? null)) {
                return $denied['body'];
            }

            return $this->guestSyncPayload();
        }

        $zone = self::normalizeZone($input['zone'] ?? null, 'gallery');
        $peerId = (string) $actor['peer_id'];

        if (self::isSoloZone($zone)) {
            $this->evictPlayerFromHeroInstances($peerId);

            return $this->buildSoloModePayload($actor, $zone);
        }

        $now = Carbon::now()->getTimestamp();
        $targetInstanceId = $this->resolveTargetInstanceForSync($peerId, $now);

        return $this->withInstanceLock(
            $targetInstanceId,
            function () use ($actor, $input, $peerId, $targetInstanceId): array {
                $nowInner = Carbon::now()->getTimestamp();
                $nowMs = Carbon::now()->getTimestampMs();
                $actorKey = (string) ($actor['actor_key'] ?? $peerId);

                $roster = $this->loadPrunedInstanceRoster($targetInstanceId, $nowInner);
                $previousEntry = $roster[$peerId] ?? null;

                $input['zone'] = 'heroes';
                $state = $this->sanitizeState($input, $previousEntry, 'heroes', $nowMs, $actorKey);
                $roster[$peerId] = $this->buildRosterEntry($actor, $state, $nowInner);

                Cache::put($this->instanceRosterKey($targetInstanceId), $roster, self::HEARTBEAT_TTL_SECONDS + 30);
                $this->ensureInstanceInActiveIndex($targetInstanceId);
                Cache::put($this->playerPointerKey($peerId), $targetInstanceId, self::HEARTBEAT_TTL_SECONDS);

                $rostersForOverview = $this->loadAndPruneActiveRosters($nowInner);
                $rostersForOverview[$targetInstanceId] = $roster;

                return [
                    'ok' => true,
                    'authenticated' => true,
                    'solo_mode' => false,
                    'zone' => 'heroes',
                    'instance_id' => $targetInstanceId,
                    'soft_cap' => self::SOFT_CAP,
                    'hard_cap' => self::HARD_CAP,
                    'player' => $this->formatPlayerSelf($actor),
                    'peers' => $this->formatPeersList($roster, $peerId),
                    'messages' => $this->getInstanceMessages($targetInstanceId, $peerId),
                    'instances' => $this->buildInstancesOverview($rostersForOverview, $targetInstanceId),
                ];
            },
            fn (): array => $this->buildReadOnlyHeroSnapshot($actor, $peerId, $targetInstanceId)
        );
    }

    /**
     * Wechselt manuell in eine Ziel-Instanz (1..50) der Halle der Helden,
     * sofern dort < HARD_CAP (20) andere Spieler aktiv sind.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function switchInstance(array $actor, int $targetInstance, array $input = []): array
    {
        if (array_key_exists('zone', $input) && $input['zone'] !== null && trim((string) $input['zone']) !== '') {
            $requestedZone = self::normalizeZone($input['zone'], 'heroes');
            if ($requestedZone !== 'heroes') {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Instanzen sind nur in der Halle der Helden aktiv.',
                ];
            }
        }

        return $this->withLobbyLock(
            function () use ($actor, $targetInstance, $input): array {
                $now = Carbon::now()->getTimestamp();
                $nowMs = Carbon::now()->getTimestampMs();
                $peerId = (string) $actor['peer_id'];
                $actorKey = (string) ($actor['actor_key'] ?? $peerId);

                $rosters = $this->loadAndPruneActiveRosters($now);
                $currentInstanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

                $targetRoster = $rosters[$targetInstance] ?? $this->loadPrunedInstanceRoster($targetInstance, $now);
                $otherCount = $this->countOtherPlayers($targetRoster, $peerId);

                if ($otherCount >= self::HARD_CAP) {
                    $this->persistActiveIdsIndex($rosters);

                    return [
                        'ok' => false,
                        'status' => 409,
                        'message' => 'Diese Instanz ist voll (20/20).',
                    ];
                }

                $previousEntry = ($currentInstanceId !== null && isset($rosters[$currentInstanceId][$peerId]))
                    ? $rosters[$currentInstanceId][$peerId]
                    : ($targetRoster[$peerId] ?? null);

                if ($currentInstanceId !== null && $currentInstanceId !== $targetInstance) {
                    unset($rosters[$currentInstanceId][$peerId]);
                    $this->persistInstanceRoster($currentInstanceId, $rosters[$currentInstanceId] ?? [], $rosters);
                }

                $input['zone'] = 'heroes';
                $state = $this->sanitizeState($input, $previousEntry, 'heroes', $nowMs, $actorKey);
                $targetRoster[$peerId] = $this->buildRosterEntry($actor, $state, $now);
                $rosters[$targetInstance] = $targetRoster;

                $this->persistInstanceRoster($targetInstance, $targetRoster, $rosters);
                $this->persistActiveIdsIndex($rosters);
                Cache::put($this->playerPointerKey($peerId), $targetInstance, self::HEARTBEAT_TTL_SECONDS);

                return [
                    'ok' => true,
                    'status' => 200,
                    'authenticated' => true,
                    'solo_mode' => false,
                    'zone' => 'heroes',
                    'instance_id' => $targetInstance,
                    'soft_cap' => self::SOFT_CAP,
                    'hard_cap' => self::HARD_CAP,
                    'player' => $this->formatPlayerSelf($actor),
                    'peers' => $this->formatPeersList($targetRoster, $peerId),
                    'messages' => $this->getInstanceMessages($targetInstance, $peerId),
                    'instances' => $this->buildInstancesOverview($rosters, $targetInstance),
                ];
            },
            static fn (): array => [
                'ok' => false,
                'status' => 409,
                'message' => 'Instanzwechsel wird bereits verarbeitet. Bitte kurz warten.',
            ]
        );
    }

    /**
     * Entfernt den Spieler sofort aus seiner aktiven Lobby-Instanz.
     *
     * @param array<string, mixed> $actor
     */
    public function leave(array $actor): void
    {
        $this->evictPlayerFromHeroInstances((string) $actor['peer_id']);
    }

    /**
     * Deterministischer Zero-PII Kurz-Hash (12 Hex-Zeichen, nicht rueckrechenbar).
     */
    public function makePeerId(string $actorKey): string
    {
        $secret = (string) (config('app.key') ?: 'seelenfunke_lobby_hmac_key');

        return substr(hash_hmac('sha256', $actorKey, $secret), 0, 12);
    }

    /**
     * Ermittelt die Clean-Design Schiffsklasse ("KLASSE C", "KLASSE B", "KLASSE A", "KLASSE S").
     */
    public static function resolveShipClassForProfile(?CustomerGamification $profile, int $level): string
    {
        if ($profile !== null && is_array($profile->equipped_items)) {
            foreach (['ship', 'spaceship', 'weapon', 'main_hand'] as $slotKey) {
                $item = $profile->equipped_items[$slotKey] ?? null;
                if (is_array($item)) {
                    $raw = strtoupper(trim((string) ($item['ship_class'] ?? $item['rank_class'] ?? $item['class'] ?? '')));
                    $letter = trim(str_replace('KLASSE', '', $raw));
                    if (in_array($letter, ['C', 'B', 'A', 'S'], true)) {
                        return 'KLASSE ' . $letter;
                    }
                }
            }
        }

        if ($level >= 9) {
            return 'KLASSE S';
        }
        if ($level >= 7) {
            return 'KLASSE A';
        }
        if ($level >= 4) {
            return 'KLASSE B';
        }

        return 'KLASSE C';
    }

    /**
     * Ermittelt die Anzahl freigeschalteter Titel und Level-Meilensteine ohne Zusatz-Queries.
     */
    public static function resolveTitlesCountForProfile(?CustomerGamification $profile, int $level): int
    {
        $clampedLevel = max(1, min(GameConfig::MAX_LEVEL, $level));
        $levelMilestones = $clampedLevel;

        if ($profile === null) {
            return $levelMilestones;
        }

        $rawUnlockedTitles = $profile->getAttribute('unlocked_titles');
        if (is_string($rawUnlockedTitles) && $rawUnlockedTitles !== '') {
            $decoded = json_decode($rawUnlockedTitles, true);
            $rawUnlockedTitles = is_array($decoded) ? $decoded : null;
        }

        $explicitCount = is_array($rawUnlockedTitles) ? count($rawUnlockedTitles) : 0;

        $coreUnlocked = 0;
        $diamonds = 0;
        $progress = is_array($profile->titles_progress) ? $profile->titles_progress : [];
        $titleConfigs = GameConfig::getTitles();

        foreach ($titleConfigs as $key => $cfg) {
            $val = (int) floor((float) ($progress[$key] ?? 0));
            $silberReq = (int) ($cfg['tiers']['silber']['req'] ?? PHP_INT_MAX);
            $diamantReq = (int) ($cfg['tiers']['diamant']['req'] ?? PHP_INT_MAX);

            if ($val >= $silberReq) {
                $coreUnlocked++;
            }
            if ($val >= $diamantReq) {
                $diamonds++;
            }
        }

        if ($diamonds >= count($titleConfigs) && $clampedLevel >= GameConfig::MAX_LEVEL) {
            $coreUnlocked++;
        }

        return max(1, $explicitCount, $levelMilestones + $coreUnlocked);
    }

    /**
     * Entfernt verbotene KI-Gedankenstriche und normalisiert Leerzeichen.
     */
    public static function cleanZeroDashText(string $value): string
    {
        $clean = str_replace(
            [' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' ', "\u{2013}", "\u{2014}"],
            [' · ', ' · ', '-', '-'],
            trim(strip_tags($value))
        );

        return preg_replace('/\s+/u', ' ', $clean) ?? $clean;
    }

    /**
     * Zentrale, deterministische Bewertungsformel fuer die 3D- und 2D-Halle der Helden:
     * - magma_cleared_levels: gemeisterte Magma-Raeume (1..10 aus titles_progress['lava_cleared_levels'])
     * - magma_highest_level: hoechste gemeisterte Magma-Stufe (0..10)
     * - magma_cleared_count: Anzahl gemeisterter Magma-Raeume (0..10)
     * - magma_prestige_points: Summe ueber gemeisterte Stufen L von (L * 15), max. +825 Punkte
     * - hero_score = funken_total_earned + magma_prestige_points
     *
     * @return array{
     *     sparks_total: int,
     *     level: int,
     *     magma_cleared_levels: array<int, int>,
     *     magma_highest_level: int,
     *     magma_cleared_count: int,
     *     magma_prestige_points: int,
     *     hero_score: int
     * }
     */
    public static function calculateHeroRankingMetrics(?CustomerGamification $profile): array
    {
        $sparksTotal = $profile !== null ? max(0, (int) ($profile->funken_total_earned ?? 0)) : 0;
        $resolvedLevel = $profile !== null
            ? min(GameConfig::MAX_LEVEL, max(1, (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks($sparksTotal))))
            : 1;

        $progress = ($profile !== null && is_array($profile->titles_progress))
            ? $profile->titles_progress
            : [];

        $rawCleared = $progress['lava_cleared_levels'] ?? [];
        $validLevels = [];
        if (is_array($rawCleared)) {
            foreach ($rawCleared as $val) {
                if (is_int($val) || (is_string($val) && ctype_digit($val))) {
                    $lvl = (int) $val;
                    if ($lvl >= 1 && $lvl <= LavaParkourService::MAX_MAGMA_LEVEL) {
                        $validLevels[$lvl] = $lvl;
                    }
                }
            }
        }
        ksort($validLevels, SORT_NUMERIC);
        $allClearedLevels = array_values($validLevels);

        $maxSeq = 0;
        for ($i = 1; $i <= LavaParkourService::MAX_MAGMA_LEVEL; $i++) {
            if (in_array($i, $allClearedLevels, true)) {
                $maxSeq = $i;
            } else {
                break;
            }
        }
        $permanentFloor = max(1, min(LavaParkourService::MAX_MAGMA_LEVEL, $maxSeq + 1));

        $magmaUnlockedCap = $permanentFloor;
        if ($profile !== null) {
            $attrs = $profile->getAttributes();
            if (array_key_exists('magma_unlocked_level', $attrs) && $attrs['magma_unlocked_level'] !== null) {
                $storedCap = max(1, min(LavaParkourService::MAX_MAGMA_LEVEL, (int) $attrs['magma_unlocked_level']));
                $magmaUnlockedCap = max($permanentFloor, $storedCap);
            }
        }

        $clearedLevels = array_values(array_filter(
            $allClearedLevels,
            static fn (int $lvl): bool => $lvl <= $magmaUnlockedCap
        ));

        $magmaHighestLevel = !empty($clearedLevels) ? max($clearedLevels) : 0;
        $magmaClearedCount = count($clearedLevels);
        $magmaPrestigePoints = 0;
        foreach ($clearedLevels as $lvl) {
            $magmaPrestigePoints += $lvl * self::MAGMA_PRESTIGE_MULTIPLIER;
        }

        return [
            'sparks_total' => $sparksTotal,
            'level' => $resolvedLevel,
            'magma_cleared_levels' => $clearedLevels,
            'magma_highest_level' => $magmaHighestLevel,
            'magma_cleared_count' => $magmaClearedCount,
            'magma_prestige_points' => $magmaPrestigePoints,
            'hero_score' => $sparksTotal + $magmaPrestigePoints,
        ];
    }

    /**
     * Laedt und sortiert alle berechtigten aktiven Seelenfunken-Teilnehmer identisch fuer die
     * 3D-Halle der Helden (/funkenwerk-welt/heroes-data) und die 2D-Rangliste:
     * - is_active = true + gueltiger Customer
     * - zwingend mindestens 1 bezahlte, nicht stornierte und nicht widerrufene Bestellung
     * - Sortierung: hero_score DESC -> magma_highest_level DESC -> funken_total_earned DESC -> level DESC -> updated_at ASC -> id ASC
     *
     * @return array<int, array{
     *     profile: CustomerGamification,
     *     metrics: array{
     *         sparks_total: int,
     *         level: int,
     *         magma_cleared_levels: array<int, int>,
     *         magma_highest_level: int,
     *         magma_cleared_count: int,
     *         magma_prestige_points: int,
     *         hero_score: int
     *     }
     * }>
     */
    public static function loadRankedHeroEntries(int $limit = 500): array
    {
        if (!Schema::hasTable('customer_gamifications') || !Schema::hasTable('order_orders')) {
            return [];
        }

        $relations = ['customer:id,first_name,last_name,email,email_verified_at,needs_password_change,created_at'];
        if (Schema::hasTable('customer_profiles')) {
            $relations[] = 'customer.profile:id,customer_id,photo_path,email_verified_at';
        }

        $profiles = CustomerGamification::with($relations)
            ->where('is_active', true)
            ->whereHas('customer')
            ->get();

        if ($profiles->isEmpty()) {
            return [];
        }

        $allCustomerIds = [];
        foreach ($profiles as $profile) {
            if ($profile->customer && !(method_exists($profile->customer, 'trashed') && $profile->customer->trashed())) {
                $allCustomerIds[] = (string) $profile->customer_id;
            }
        }

        $validPaidOrderCustomerLookup = self::resolveCustomerIdsWithValidPaidOrder($allCustomerIds);
        if (empty($validPaidOrderCustomerLookup)) {
            return [];
        }

        $entries = [];
        foreach ($profiles as $profile) {
            if (!$profile->customer || (method_exists($profile->customer, 'trashed') && $profile->customer->trashed())) {
                continue;
            }

            $cid = (string) $profile->customer_id;
            if (!isset($validPaidOrderCustomerLookup[$cid])) {
                continue;
            }

            $entries[] = [
                'profile' => $profile,
                'metrics' => self::calculateHeroRankingMetrics($profile),
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            $ma = $a['metrics'];
            $mb = $b['metrics'];

            if ($ma['hero_score'] !== $mb['hero_score']) {
                return $mb['hero_score'] <=> $ma['hero_score'];
            }
            if ($ma['magma_highest_level'] !== $mb['magma_highest_level']) {
                return $mb['magma_highest_level'] <=> $ma['magma_highest_level'];
            }
            if ($ma['sparks_total'] !== $mb['sparks_total']) {
                return $mb['sparks_total'] <=> $ma['sparks_total'];
            }

            $lvlA = (int) ($a['profile']->level ?: $ma['level']);
            $lvlB = (int) ($b['profile']->level ?: $mb['level']);
            if ($lvlA !== $lvlB) {
                return $lvlB <=> $lvlA;
            }

            $tsA = $a['profile']->updated_at ? $a['profile']->updated_at->getTimestamp() : 0;
            $tsB = $b['profile']->updated_at ? $b['profile']->updated_at->getTimestamp() : 0;
            if ($tsA !== $tsB) {
                return $tsA <=> $tsB;
            }

            return strcmp((string) $a['profile']->id, (string) $b['profile']->id);
        });

        if ($limit > 0 && count($entries) > $limit) {
            return array_slice($entries, 0, $limit);
        }

        return $entries;
    }

    /**
     * DSGVO-saubere Namensanzeige ohne leere Ehrenplaetze (Baustein 8):
     * 1. ranking_opt_in = true: gewaehlter player_name bzw. Vorname + Initial (niemals voller Nachname / E-Mail).
     * 2. ranking_opt_in = false + gesetzter player_name (Pseudonym, 0 Klardaten): zeige player_name.
     * 3. ranking_opt_in = false + kein player_name:
     *    - fuer den eingeloggten Spieler selbst ($isMe === true): eigener Vorname (z. B. "Alina")
     *    - fuer alle anderen Besucher ($isMe === false): anonymes Ehren-Pseudonym "Funken-Hüter #<Rang>"
     */
    public static function resolveHallOfHeroesDisplayName(
        ?Customer $customer,
        ?CustomerGamification $profile,
        int $rankSlot,
        bool $isMe = false,
        ?GamificationService $gameService = null
    ): string {
        $safeSlot = max(1, $rankSlot);
        $guardianFallback = 'Funken-Hüter #' . $safeSlot;

        if ($customer === null || $profile === null) {
            return $guardianFallback;
        }

        if ((bool) $profile->ranking_opt_in) {
            if ((bool) $profile->use_real_name) {
                $service = $gameService ?? app(GamificationService::class);
                $publicReal = self::cleanZeroDashText($service->resolvePublicDisplayName($customer, $profile));
                if ($publicReal !== '' && !str_contains($publicReal, '@') && !self::containsFullLastName($publicReal, $customer)) {
                    return mb_substr($publicReal, 0, 28);
                }

                return $isMe ? self::sanitizeOwnFirstName($customer, $safeSlot) : $guardianFallback;
            }

            $optInPseudo = self::sanitizePublicPseudonym($customer, (string) ($profile->player_name ?? ''));
            if ($optInPseudo !== null) {
                return $optInPseudo;
            }

            return $isMe ? self::sanitizeOwnFirstName($customer, $safeSlot) : $guardianFallback;
        }

        $pseudonym = self::sanitizePublicPseudonym($customer, (string) ($profile->player_name ?? ''));
        if ($pseudonym !== null) {
            return $pseudonym;
        }

        if ($isMe) {
            return self::sanitizeOwnFirstName($customer, $safeSlot);
        }

        return $guardianFallback;
    }

    private static function sanitizePublicPseudonym(Customer $customer, string $rawPseudonym): ?string
    {
        $clean = self::cleanZeroDashText($rawPseudonym);
        if ($clean === '' || str_contains($clean, '@')) {
            return null;
        }

        if (self::containsFullLastName($clean, $customer)) {
            return null;
        }

        return mb_substr($clean, 0, 28);
    }

    private static function sanitizeOwnFirstName(Customer $customer, int $rankSlot): string
    {
        $first = self::cleanZeroDashText((string) ($customer->first_name ?? ''));
        if ($first === '' || str_contains($first, '@') || self::containsFullLastName($first, $customer)) {
            return 'Funken-Hüter #' . max(1, $rankSlot);
        }

        return mb_substr($first, 0, 28);
    }

    private static function containsFullLastName(string $candidate, Customer $customer): bool
    {
        $lastNameLower = mb_strtolower(trim((string) ($customer->last_name ?? '')), 'UTF-8');
        $firstNameLower = mb_strtolower(trim((string) ($customer->first_name ?? '')), 'UTF-8');

        if ($lastNameLower === '' || mb_strlen($lastNameLower) < 3 || $lastNameLower === $firstNameLower) {
            return false;
        }

        return str_contains(mb_strtolower($candidate, 'UTF-8'), $lastNameLower);
    }

    /**
     * Ermittelt die aktuell aktive Heldenhallen-Instanz (1..50) eines Kunden oder null, falls er nicht in 'heroes' aktiv ist.
     */
    public function getActiveHeroInstanceForCustomer(Customer $customer): ?int
    {
        $actor = $this->buildCustomerActor($customer);

        return $this->getActiveHeroInstanceForActor($actor);
    }

    /**
     * @param array<string, mixed> $actor
     */
    public function getActiveHeroInstanceForActor(array $actor): ?int
    {
        $peerId = (string) ($actor['peer_id'] ?? '');
        if ($peerId === '') {
            return null;
        }

        $now = Carbon::now()->getTimestamp();
        $ptr = Cache::get($this->playerPointerKey($peerId));
        if (is_numeric($ptr)) {
            $ptrId = (int) $ptr;
            if ($ptrId >= 1 && $ptrId <= self::MAX_INSTANCES) {
                $roster = $this->loadPrunedInstanceRoster($ptrId, $now);
                if (isset($roster[$peerId])) {
                    return $ptrId;
                }
            }
        }

        $rosters = $this->loadAndPruneActiveRosters($now);

        return $this->findPlayerCurrentInstance($peerId, $rosters, $now);
    }

    public function isCustomerShadowMuted(string $customerId): bool
    {
        $cleanId = trim($customerId);
        if ($cleanId === '') {
            return false;
        }

        $until = (int) Cache::get(self::CACHE_CUST_MUTE_PREFIX . $cleanId, 0);

        return $until > Carbon::now()->getTimestamp();
    }

    /**
     * Liest die letzten bis zu 25 aktiven Chat-Nachrichten (TTL 120s) einer Heldenhallen-Instanz (#1..50).
     * Shadow-Mute-isoliert via sf_lobby_mute_v1_{customerId}: Gemutete Spieler sehen nur ihre eigenen Nachrichten,
     * alle anderen Mitspieler sehen 0 Nachrichten des gemuteten Akteurs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInstanceMessages(int $instanceId, ?string $viewerPeerId = null): array
    {
        if ($instanceId < 1 || $instanceId > self::MAX_INSTANCES) {
            return [];
        }

        $raw = Cache::get($this->instanceChatKey($instanceId), []);
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $nowMs = Carbon::now()->getTimestampMs();
        $visible = [];

        foreach ($raw as $msg) {
            if (!is_array($msg) || (int) ($msg['expires_at_ms'] ?? 0) <= $nowMs) {
                continue;
            }

            $msgPeerId = (string) ($msg['peer_id'] ?? '');
            $authorCustomerId = is_string($msg['_author_cid'] ?? null) ? $msg['_author_cid'] : '';
            $isMutedMsg = !empty($msg['_shadow_muted'])
                || ($authorCustomerId !== '' && $this->isCustomerShadowMuted($authorCustomerId));

            if ($isMutedMsg && ($viewerPeerId === null || $viewerPeerId === '' || $msgPeerId !== $viewerPeerId)) {
                continue;
            }

            $cleanText = str_replace(['<', '>'], '', strip_tags((string) ($msg['message'] ?? $msg['text'] ?? '')));
            $cleanText = self::cleanZeroDashText($cleanText);
            if ($cleanText === '') {
                continue;
            }

            $shipClass = (string) ($msg['ship_class'] ?? 'KLASSE C');

            $visible[] = [
                'id' => (string) ($msg['id'] ?? ''),
                'peer_id' => $msgPeerId,
                'player_id' => $msgPeerId,
                'display_name' => (string) ($msg['display_name'] ?? 'Funki'),
                'level' => max(1, min(GameConfig::MAX_LEVEL, (int) ($msg['level'] ?? 1))),
                'ship_class' => $shipClass,
                'class_label' => $shipClass,
                'message' => $cleanText,
                'text' => $cleanText,
                'created_at_ms' => (int) ($msg['created_at_ms'] ?? $nowMs),
            ];
        }

        return $visible;
    }

    /**
     * Veroeffentlicht eine moderierte Chat-Nachricht direkt in der aktiven Heldenhallen-Instanz (#1..50) eines Kunden.
     *
     * @return array<string, mixed>
     */
    public function postInstanceChatForCustomer(Customer $customer, string $rawMessage, bool $autoJoinIfNeeded = false): array
    {
        $actor = $this->buildCustomerActor($customer);

        return $this->postChatMessage($actor, $rawMessage, $autoJoinIfNeeded);
    }

    /**
     * Einheitlicher Instanz-Chat fuer die Halle der Helden (#1..50):
     * - Ringpuffer max. 25 Nachrichten, TTL 120s
     * - HTML/XSS hart geblockt (422 blocked_html), Laenge max. 180 Zeichen (422 too_long)
     * - 4-Stufen Wort-, Link- & PII-Filter ueber ShowroomChatGuardService
     * - Shadow-Mute-isoliert via sf_lobby_mute_v1_{customerId}
     * - Per-Instanz-Lock sf_fw_lobby_inst_lock_v1_{instanceId} ohne globalen Mutex
     *
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function postChatMessage(array $actor, string $rawMessage, bool $autoJoinIfNeeded = true): array
    {
        $peerId = (string) ($actor['peer_id'] ?? '');
        $actorKey = (string) ($actor['actor_key'] ?? '');
        $customerId = isset($actor['customer_id']) && is_string($actor['customer_id']) ? $actor['customer_id'] : '';

        if ($peerId === '' || $actorKey === '') {
            return [
                'ok' => false,
                'success' => false,
                'status' => 403,
                'code' => 'login_required',
                'message' => 'Nur eingeloggte Mitglieder können den Chat nutzen.',
            ];
        }

        if ($this->isLobbyChatFloodRateLimited($actorKey)) {
            self::logTamperThrottled($actorKey, 'funkenwerk-welt/lobby/chat', 'chat_flood_rate_limited');

            return [
                'ok' => false,
                'success' => false,
                'status' => 429,
                'code' => 'rate_limited',
                'message' => 'Zu viele Chat-Anfragen in kurzer Zeit. Bitte warte einen Moment.',
            ];
        }

        if ($this->containsHtmlOrScriptPayload($rawMessage)) {
            self::logTamperThrottled($actorKey, 'funkenwerk-welt/lobby/chat', 'xss_or_html_chat_payload');

            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'blocked_html',
                'message' => 'HTML- und Skript-Inhalte sind im Chat nicht erlaubt.',
            ];
        }

        $withoutInvisible = preg_replace('/[\x{0000}-\x{001F}\x{007F}\x{00AD}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $rawMessage) ?? $rawMessage;
        $stripped = trim(preg_replace('/\s+/u', ' ', strip_tags($withoutInvisible)) ?? '');

        if ($stripped === '') {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'empty',
                'message' => 'Bitte gib eine Nachricht ein.',
            ];
        }

        if (mb_strlen($stripped, 'UTF-8') > self::CHAT_MAX_LENGTH) {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'too_long',
                'message' => 'Maximal ' . self::CHAT_MAX_LENGTH . ' Zeichen pro Nachricht erlaubt.',
            ];
        }

        if ($this->isOnLobbyChatCooldown($actorKey)) {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'cooldown',
                'message' => 'Bitte warte einen kurzen Moment vor der nächsten Nachricht.',
            ];
        }

        /** @var ShowroomChatGuardService $chatGuard */
        $chatGuard = app(ShowroomChatGuardService::class);
        $guardCheckId = 'lobby_guard_' . md5($actorKey);
        $chunks = mb_strlen($stripped, 'UTF-8') <= ShowroomChatGuardService::MAX_MESSAGE_LENGTH
            ? [$stripped]
            : [
                mb_substr($stripped, 0, 95, 'UTF-8'),
                mb_substr($stripped, max(0, mb_strlen($stripped, 'UTF-8') - 95), 95, 'UTF-8'),
            ];

        foreach ($chunks as $chunk) {
            $check = $chatGuard->inspectAndSanitize($guardCheckId, $chunk, false);
            if (!$check['allowed']) {
                return [
                    'ok' => false,
                    'success' => false,
                    'status' => 422,
                    'code' => $check['code'] ?? 'blocked_language',
                    'message' => $check['reason'] ?? 'Nachricht wurde blockiert.',
                ];
            }
        }

        $cleanMessage = self::cleanZeroDashText(str_replace(['<', '>'], '', $stripped));
        if ($cleanMessage === '') {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'empty',
                'message' => 'Bitte gib eine Nachricht ein.',
            ];
        }

        $instanceId = $this->getActiveHeroInstanceForActor($actor);
        if ($instanceId === null) {
            if (!$autoJoinIfNeeded) {
                return [
                    'ok' => false,
                    'success' => false,
                    'status' => 422,
                    'code' => 'not_in_instance',
                    'message' => 'Du befindest dich aktuell in keiner aktiven Heldenhallen-Instanz.',
                ];
            }

            $joined = $this->join($actor, ['zone' => 'heroes']);
            $instanceId = (int) ($joined['instance_id'] ?? 1);
        }

        $this->touchLobbyChatCooldown($actorKey);
        $isShadowMuted = $customerId !== '' && $this->isCustomerShadowMuted($customerId);

        return $this->withInstanceLock(
            $instanceId,
            function () use ($actor, $peerId, $customerId, $instanceId, $cleanMessage, $isShadowMuted): array {
                $nowMs = Carbon::now()->getTimestampMs();
                $shipClass = (string) ($actor['ship_class'] ?? 'KLASSE C');
                $displayName = (string) ($actor['peer_display_name'] ?? 'Funki');
                $level = max(1, min(GameConfig::MAX_LEVEL, (int) ($actor['level'] ?? 1)));

                $storedEntry = [
                    'id' => 'lm_' . Str::lower(Str::random(12)),
                    'peer_id' => $peerId,
                    'display_name' => $displayName,
                    'level' => $level,
                    'ship_class' => $shipClass,
                    'message' => $cleanMessage,
                    'created_at_ms' => $nowMs,
                    'expires_at_ms' => $nowMs + (self::CHAT_TTL_SECONDS * 1000),
                    '_author_cid' => $customerId,
                    '_shadow_muted' => $isShadowMuted,
                ];

                $chatKey = $this->instanceChatKey($instanceId);
                $existing = Cache::get($chatKey, []);
                if (!is_array($existing)) {
                    $existing = [];
                }

                $validMessages = [];
                foreach ($existing as $msg) {
                    if (is_array($msg) && (int) ($msg['expires_at_ms'] ?? 0) > $nowMs) {
                        $validMessages[] = $msg;
                    }
                }

                $validMessages[] = $storedEntry;
                if (count($validMessages) > self::CHAT_RING_BUFFER_SIZE) {
                    $validMessages = array_slice($validMessages, -self::CHAT_RING_BUFFER_SIZE);
                }

                Cache::put($chatKey, $validMessages, self::CHAT_TTL_SECONDS);

                $publicEntry = [
                    'id' => $storedEntry['id'],
                    'peer_id' => $peerId,
                    'player_id' => $peerId,
                    'display_name' => $displayName,
                    'level' => $level,
                    'ship_class' => $shipClass,
                    'class_label' => $shipClass,
                    'message' => $cleanMessage,
                    'text' => $cleanMessage,
                    'created_at_ms' => $nowMs,
                ];

                return [
                    'ok' => true,
                    'success' => true,
                    'status' => 200,
                    'instance_id' => $instanceId,
                    'room_id' => 'heroes-instance-' . $instanceId,
                    'shadow_muted' => $isShadowMuted,
                    'entry' => $publicEntry,
                    'messages' => $this->getInstanceMessages($instanceId, $peerId),
                ];
            },
            static fn (): array => [
                'ok' => false,
                'success' => false,
                'status' => 409,
                'code' => 'busy',
                'message' => 'Der Instanz-Chat verarbeitet gerade eine Anfrage. Bitte kurz warten.',
            ]
        );
    }

    /**
     * Meldet einen Mitspieler in derselben aktiven Heldenhallen-Instanz anhand seiner 12-Hex peer_id.
     * Ab 2 verschiedenen Meldungen greift ein 24h Shadow-Mute unter sf_lobby_mute_v1_{customerId}.
     *
     * @return array<string, mixed>
     */
    public function reportPeerInHeroInstance(Customer $reporter, string $rawTargetPeerId): array
    {
        $actor = $this->buildCustomerActor($reporter);

        return $this->reportPeer($actor, $rawTargetPeerId);
    }

    /**
     * @param array<string, mixed> $reporterActor
     * @return array<string, mixed>
     */
    public function reportPeer(array $reporterActor, string $rawTargetPeerId): array
    {
        $reporterPeerId = (string) ($reporterActor['peer_id'] ?? '');
        $reporterActorKey = (string) ($reporterActor['actor_key'] ?? '');
        $reporterCustomerId = isset($reporterActor['customer_id']) && is_string($reporterActor['customer_id'])
            ? $reporterActor['customer_id']
            : (str_starts_with($reporterActorKey, 'customer:') ? substr($reporterActorKey, 9) : '');

        $cleanTargetPeerId = strtolower(trim($rawTargetPeerId));
        if (!preg_match('/^[0-9a-f]{12}$/', $cleanTargetPeerId) || $cleanTargetPeerId === $reporterPeerId) {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'invalid_target',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Ungültige Spieler-Kennung.',
            ];
        }

        $instanceId = $this->getActiveHeroInstanceForActor($reporterActor);
        if ($instanceId === null) {
            return [
                'ok' => false,
                'success' => false,
                'status' => 422,
                'code' => 'not_in_instance',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Meldungen sind nur in einer aktiven Heldenhallen-Instanz möglich.',
            ];
        }

        return $this->withInstanceLock(
            $instanceId,
            function () use ($instanceId, $cleanTargetPeerId, $reporterActorKey, $reporterCustomerId): array {
                $now = Carbon::now()->getTimestamp();
                $nowMs = Carbon::now()->getTimestampMs();
                $roster = $this->loadPrunedInstanceRoster($instanceId, $now);

                $targetInRoster = isset($roster[$cleanTargetPeerId]);
                $targetInChat = false;
                $chatAuthorCid = null;

                $rawChat = Cache::get($this->instanceChatKey($instanceId), []);
                if (is_array($rawChat)) {
                    foreach ($rawChat as $msg) {
                        if (
                            is_array($msg)
                            && (int) ($msg['expires_at_ms'] ?? 0) > $nowMs
                            && (string) ($msg['peer_id'] ?? '') === $cleanTargetPeerId
                        ) {
                            $targetInChat = true;
                            if (!empty($msg['_author_cid']) && is_string($msg['_author_cid'])) {
                                $chatAuthorCid = $msg['_author_cid'];
                            }
                            break;
                        }
                    }
                }

                if (!$targetInRoster && !$targetInChat) {
                    self::logTamperThrottled(
                        $reporterActorKey ?: 'lobby_reporter',
                        'funkenwerk-welt/lobby/report',
                        'cross_instance_or_phantom_report_blocked',
                        ['target_peer_id' => $cleanTargetPeerId, 'instance_id' => $instanceId]
                    );

                    return [
                        'ok' => false,
                        'success' => false,
                        'status' => 422,
                        'code' => 'not_in_same_instance',
                        'reports_count' => 0,
                        'shadow_muted' => false,
                        'message' => 'Spieler befindet sich nicht in derselben Instanz.',
                    ];
                }

                $targetCustomerId = Cache::get(self::CACHE_PEER_TO_CUST_PREFIX . $cleanTargetPeerId);
                if ((!is_string($targetCustomerId) || $targetCustomerId === '') && $chatAuthorCid !== null) {
                    $targetCustomerId = $chatAuthorCid;
                }

                if (!is_string($targetCustomerId) || $targetCustomerId === '' || $targetCustomerId === $reporterCustomerId) {
                    return [
                        'ok' => false,
                        'success' => false,
                        'status' => 422,
                        'code' => 'invalid_target',
                        'reports_count' => 0,
                        'shadow_muted' => false,
                        'message' => 'Meldung konnte nicht zugeordnet werden.',
                    ];
                }

                $reporterKey = $reporterCustomerId !== '' ? $reporterCustomerId : $reporterActorKey;
                $reportCacheKey = self::CACHE_CUST_REPORTS_PREFIX . $targetCustomerId;
                $reporters = Cache::get($reportCacheKey, []);
                if (!is_array($reporters)) {
                    $reporters = [];
                }

                $reporters[$reporterKey] = $now;
                Cache::put($reportCacheKey, $reporters, self::SHADOW_MUTE_SECONDS);

                $reportsCount = count($reporters);
                $muted = $this->isCustomerShadowMuted($targetCustomerId);

                if ($reportsCount >= self::REPORT_THRESHOLD && !$muted) {
                    Cache::put(
                        self::CACHE_CUST_MUTE_PREFIX . $targetCustomerId,
                        $now + self::SHADOW_MUTE_SECONDS,
                        self::SHADOW_MUTE_SECONDS
                    );
                    $muted = true;
                }

                return [
                    'ok' => true,
                    'success' => true,
                    'status' => 200,
                    'instance_id' => $instanceId,
                    'reports_count' => $reportsCount,
                    'shadow_muted' => $muted,
                    'message' => 'Meldung verarbeitet.',
                ];
            },
            static fn (): array => [
                'ok' => false,
                'success' => false,
                'status' => 409,
                'code' => 'busy',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Meldung wird bereits verarbeitet.',
            ]
        );
    }

    /**
     * Entfernt einen Spieler sauber aus allen aktiven Heldenhallen-Rostern und gibt seinen Slot sofort frei.
     */
    private function evictPlayerFromHeroInstances(string $peerId): void
    {
        $ptr = Cache::get($this->playerPointerKey($peerId));
        $hasActiveIds = !empty(Cache::get(self::CACHE_ACTIVE_IDS_KEY, []));

        if ($ptr === null && !$hasActiveIds) {
            return;
        }

        if (is_numeric($ptr)) {
            $ptrId = (int) $ptr;
            if ($ptrId >= 1 && $ptrId <= self::MAX_INSTANCES) {
                $this->withInstanceLock(
                    $ptrId,
                    function () use ($ptrId, $peerId): void {
                        $now = Carbon::now()->getTimestamp();
                        $roster = $this->loadPrunedInstanceRoster($ptrId, $now);
                        if (isset($roster[$peerId])) {
                            unset($roster[$peerId]);
                            if (empty($roster)) {
                                Cache::forget($this->instanceRosterKey($ptrId));
                            } else {
                                Cache::put($this->instanceRosterKey($ptrId), $roster, self::HEARTBEAT_TTL_SECONDS + 30);
                            }
                        }
                    },
                    static function (): void {
                    }
                );
            }
        }

        $this->withLobbyLock(
            function () use ($peerId): void {
                $now = Carbon::now()->getTimestamp();
                $rosters = $this->loadAndPruneActiveRosters($now);
                foreach (array_keys($rosters) as $instId) {
                    if (isset($rosters[$instId][$peerId])) {
                        unset($rosters[$instId][$peerId]);
                        $this->persistInstanceRoster((int) $instId, $rosters[$instId], $rosters);
                    }
                }
                $this->persistActiveIdsIndex($rosters);
                Cache::forget($this->playerPointerKey($peerId));
            },
            function () use ($peerId): void {
                Cache::forget($this->playerPointerKey($peerId));
            }
        );
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function buildSoloModePayload(array $actor, string $zone): array
    {
        return [
            'ok' => true,
            'authenticated' => true,
            'solo_mode' => true,
            'zone' => $zone,
            'instance_id' => null,
            'soft_cap' => self::SOFT_CAP,
            'hard_cap' => self::HARD_CAP,
            'player' => $this->formatPlayerSelf($actor),
            'peers' => [],
            'messages' => [],
            'instances' => [],
        ];
    }

    /**
     * @return array{
     *     actor_key: string,
     *     customer_id: string,
     *     peer_id: string,
     *     own_display_name: string,
     *     peer_display_name: string,
     *     level: int,
     *     level_title: string,
     *     ship_class: string
     * }
     */
    private function buildCustomerActor(Customer $customer): array
    {
        $customerId = (string) $customer->id;
        $actorKey = 'customer:' . $customerId;
        $peerId = $this->makePeerId($actorKey);

        Cache::put(self::CACHE_PEER_TO_CUST_PREFIX . $peerId, $customerId, self::SHADOW_MUTE_SECONDS);

        return Cache::remember(
            self::CACHE_CUST_ACTOR_PREFIX . $customerId,
            self::ACTOR_CACHE_TTL_SECONDS,
            function () use ($customer, $customerId, $actorKey, $peerId): array {
                $anonFallback = 'Funki #' . strtoupper(substr($peerId, 0, 4));
                $profile = null;
                $level = 1;

                if (Schema::hasTable('customer_gamifications')) {
                    $profile = CustomerGamification::where('customer_id', $customerId)->first();
                    if ($profile !== null) {
                        $sparks = max(0, (int) ($profile->funken_total_earned ?? 0));
                        $rawLevel = (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks($sparks));
                        $level = max(1, min(GameConfig::MAX_LEVEL, $rawLevel));
                    }
                }

                $safeGamerName = $this->sanitizeCustomerGamerName($customer, $profile);
                $ownDisplayName = $safeGamerName ?? $anonFallback;
                $peerDisplayName = ($safeGamerName !== null && $profile !== null && (bool) $profile->ranking_opt_in)
                    ? $safeGamerName
                    : $anonFallback;

                $levelTitles = GameConfig::getLevelTitles();
                $levelTitle = self::cleanZeroDashText((string) ($levelTitles[$level]['name'] ?? ('Stufe ' . $level)));
                $shipClass = self::resolveShipClassForProfile($profile, $level);

                return [
                    'actor_key' => $actorKey,
                    'customer_id' => $customerId,
                    'peer_id' => $peerId,
                    'own_display_name' => $ownDisplayName,
                    'peer_display_name' => $peerDisplayName,
                    'level' => $level,
                    'level_title' => $levelTitle,
                    'ship_class' => $shipClass,
                ];
            }
        );
    }

    /**
     * @return array{
     *     actor_key: string,
     *     peer_id: string,
     *     own_display_name: string,
     *     peer_display_name: string,
     *     level: int,
     *     level_title: string,
     *     ship_class: string
     * }
     */
    private function buildAdminActor(Admin $admin): array
    {
        $actorKey = 'admin:' . (string) $admin->id;
        $peerId = $this->makePeerId($actorKey);

        $rawFirst = self::cleanZeroDashText((string) ($admin->first_name ?? ''));
        $displayName = ($rawFirst !== '' && !str_contains($rawFirst, '@'))
            ? mb_substr($rawFirst, 0, 24)
            : 'Alina';

        $levelTitles = GameConfig::getLevelTitles();
        $levelTitle = self::cleanZeroDashText((string) ($levelTitles[10]['name'] ?? 'Erhabener des Seelenlichts'));

        return [
            'actor_key' => $actorKey,
            'peer_id' => $peerId,
            'own_display_name' => $displayName,
            'peer_display_name' => $displayName,
            'level' => 10,
            'level_title' => $levelTitle,
            'ship_class' => 'KLASSE S',
        ];
    }

    /**
     * Erlaubt fuer Kunden ausschliesslich einen sauberen Gamification-Spielernamen ohne E-Mail oder Rechnungs-Nachnamen.
     */
    private function sanitizeCustomerGamerName(Customer $customer, ?CustomerGamification $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $candidate = self::cleanZeroDashText((string) ($profile->player_name ?? ''));
        if ($candidate === '' || str_contains($candidate, '@')) {
            return null;
        }

        $candidateLower = mb_strtolower($candidate, 'UTF-8');
        $lastNameLower = mb_strtolower(trim((string) ($customer->last_name ?? '')), 'UTF-8');
        $emailLocal = mb_strtolower(trim((string) explode('@', (string) ($customer->email ?? ''))[0]), 'UTF-8');

        if ($lastNameLower !== '' && mb_strlen($lastNameLower) >= 3 && str_contains($candidateLower, $lastNameLower)) {
            return null;
        }
        if ($emailLocal !== '' && mb_strlen($emailLocal) >= 4 && str_contains($candidateLower, $emailLocal)) {
            return null;
        }

        return mb_substr($candidate, 0, 28);
    }

    /**
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed> $input
     * @return array{
     *     x: float,
     *     y: float,
     *     z: float,
     *     vx: float,
     *     vz: float,
     *     rot_y: float,
     *     speed: float,
     *     zone: string,
     *     combo_seq: int,
     *     combo_stage: int,
     *     weapon_seq: int,
     *     emote: string,
     *     ability_slot: int,
     *     last_sync_ms: ?int
     * }
     */
    private function sanitizeState(
        array $input,
        ?array $previous = null,
        string $defaultZone = 'heroes',
        ?int $nowMs = null,
        string $actorKey = 'lobby_actor'
    ): array {
        $nowMs = $nowMs ?? Carbon::now()->getTimestampMs();
        $zone = self::normalizeZone($input['zone'] ?? null, $defaultZone);
        $defaultZ = ($zone === 'heroes') ? -20.0 : 0.0;

        $prevX = (float) ($previous['x'] ?? 0.0);
        $prevY = (float) ($previous['y'] ?? 0.0);
        $prevZ = (float) ($previous['z'] ?? $defaultZ);

        $hasInputCoords = (array_key_exists('x', $input) && $input['x'] !== null && $input['x'] !== '')
            || (array_key_exists('y', $input) && $input['y'] !== null && $input['y'] !== '')
            || (array_key_exists('z', $input) && $input['z'] !== null && $input['z'] !== '');

        $rawX = $this->extractFiniteFloat($input, ['x'], $prevX);
        $rawY = $this->extractFiniteFloat($input, ['y'], $prevY);
        $rawZ = $this->extractFiniteFloat($input, ['z'], $prevZ);
        $rawVx = $this->extractFiniteFloat($input, ['vx'], (float) ($previous['vx'] ?? 0.0));
        $rawVz = $this->extractFiniteFloat($input, ['vz'], (float) ($previous['vz'] ?? 0.0));
        $rawRot = $this->extractFiniteFloat($input, ['rot_y', 'rotY'], (float) ($previous['rot_y'] ?? 0.0));
        $rawSpeed = $this->extractFiniteFloat($input, ['speed'], (float) ($previous['speed'] ?? 0.0));

        $rawComboSeq = (int) $this->extractFiniteFloat($input, ['combo_seq', 'comboSeq'], (float) ($previous['combo_seq'] ?? 0));
        $rawComboStage = (int) $this->extractFiniteFloat($input, ['combo_stage', 'comboStage'], (float) ($previous['combo_stage'] ?? 0));
        $rawWeaponSeq = (int) $this->extractFiniteFloat(
            $input,
            ['weapon_seq', 'weaponActionSeq', 'combo_seq', 'comboSeq'],
            (float) ($previous['weapon_seq'] ?? ($previous['combo_seq'] ?? 0))
        );
        $rawAbilitySlot = (int) $this->extractFiniteFloat($input, ['ability_slot', 'abilitySlot'], (float) ($previous['ability_slot'] ?? 0));

        $rawEmote = array_key_exists('emote', $input) && is_string($input['emote'])
            ? strtolower(trim($input['emote']))
            : (string) ($previous['emote'] ?? '');
        $emote = in_array($rawEmote, self::ALLOWED_EMOTES, true) ? $rawEmote : '';

        if ($zone === 'heroes') {
            $minX = -32.0;
            $maxX = 32.0;
            $minY = -0.5;
            $maxY = 8.5;
            $minZ = -110.0;
            $maxZ = -14.0;
        } elseif ($zone === 'lava') {
            $minX = -260.0;
            $maxX = 260.0;
            $minY = -4.0;
            $maxY = 24.0;
            $minZ = -260.0;
            $maxZ = 260.0;
        } else {
            $minX = -32.0;
            $maxX = 32.0;
            $minY = -0.5;
            $maxY = 8.5;
            $minZ = -14.0;
            $maxZ = 32.0;
        }

        $clampedX = max($minX, min($maxX, $rawX));
        $clampedY = max($minY, min($maxY, $rawY));
        $clampedZ = max($minZ, min($maxZ, $rawZ));

        $prevLastSyncMs = (isset($previous['last_sync_ms']) && is_int($previous['last_sync_ms']) && $previous['last_sync_ms'] > 0)
            ? $previous['last_sync_ms']
            : null;

        // Anti-Teleport-Guard: Wenn bereits ein vorheriger Koordinaten-Sync vorlag, begrenze Riesenspruenge pro Tick sanft.
        if ($prevLastSyncMs !== null && $hasInputCoords) {
            $elapsedSec = max(0.0, ($nowMs - $prevLastSyncMs) / 1000.0);
            $dx = $clampedX - $prevX;
            $dz = $clampedZ - $prevZ;
            $dist = hypot($dx, $dz);
            $maxStep = max(self::MIN_SYNC_TELEPORT_STEP, $elapsedSec * self::MAX_SPEED_WITH_MARGIN);

            if ($dist > $maxStep && $dist > 0.0001) {
                if ($dist > $maxStep + 10.0) {
                    self::logTamperThrottled($actorKey, 'funkenwerk-welt/lobby/sync', 'unplausible_teleport_clamped', [
                        'requested_distance' => round($dist, 2),
                        'allowed_max_step' => round($maxStep, 2),
                        'elapsed_sec' => round($elapsedSec, 3),
                        'zone' => $zone,
                    ]);
                }

                $scale = $maxStep / $dist;
                $clampedX = max($minX, min($maxX, $prevX + ($dx * $scale)));
                $clampedZ = max($minZ, min($maxZ, $prevZ + ($dz * $scale)));
            }

            $dy = $clampedY - $prevY;
            $maxStepY = max(6.5, $elapsedSec * 12.0);
            if (abs($dy) > $maxStepY) {
                $clampedY = max($minY, min($maxY, $prevY + ($dy > 0.0 ? $maxStepY : -$maxStepY)));
            }
        }

        return [
            'x' => round($clampedX, 3),
            'y' => round($clampedY, 3),
            'z' => round($clampedZ, 3),
            'vx' => round(max(-self::MAX_SPEED, min(self::MAX_SPEED, $rawVx)), 3),
            'vz' => round(max(-self::MAX_SPEED, min(self::MAX_SPEED, $rawVz)), 3),
            'rot_y' => round(max(-6.3, min(6.3, $rawRot)), 4),
            'speed' => round(max(0.0, min(self::MAX_SPEED, $rawSpeed)), 3),
            'zone' => $zone,
            'combo_seq' => max(0, min(10000000, $rawComboSeq)),
            'combo_stage' => max(0, min(4, $rawComboStage)),
            'weapon_seq' => max(0, min(10000000, $rawWeaponSeq)),
            'emote' => $emote,
            'ability_slot' => max(0, min(4, $rawAbilitySlot)),
            'last_sync_ms' => $hasInputCoords ? $nowMs : $prevLastSyncMs,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function extractFiniteFloat(array $input, array $keys, float $fallback): float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input) && (is_int($input[$key]) || is_float($input[$key]) || is_string($input[$key]))) {
                if (is_numeric($input[$key])) {
                    $val = (float) $input[$key];
                    if (is_finite($val)) {
                        return $val;
                    }
                }
            }
        }

        return is_finite($fallback) ? $fallback : 0.0;
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function buildRosterEntry(array $actor, array $state, int $now): array
    {
        return [
            'peer_id' => (string) $actor['peer_id'],
            'display_name' => (string) $actor['peer_display_name'],
            'level' => (int) $actor['level'],
            'ship_class' => (string) $actor['ship_class'],
            'x' => $state['x'],
            'y' => $state['y'],
            'z' => $state['z'],
            'vx' => $state['vx'] ?? 0.0,
            'vz' => $state['vz'] ?? 0.0,
            'rot_y' => $state['rot_y'],
            'speed' => $state['speed'],
            'zone' => 'heroes',
            'combo_seq' => $state['combo_seq'],
            'combo_stage' => $state['combo_stage'],
            'weapon_seq' => $state['weapon_seq'] ?? $state['combo_seq'],
            'emote' => $state['emote'],
            'ability_slot' => $state['ability_slot'],
            'last_sync_ms' => $state['last_sync_ms'] ?? null,
            'expires_at' => $now + self::HEARTBEAT_TTL_SECONDS,
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function formatPlayerSelf(array $actor): array
    {
        return [
            'peer_id' => (string) $actor['peer_id'],
            'display_name' => (string) $actor['own_display_name'],
            'level' => (int) $actor['level'],
            'level_title' => (string) $actor['level_title'],
            'ship_class' => (string) $actor['ship_class'],
        ];
    }

    /**
     * Gibt ausschliesslich aktive Mitspieler derselben Instanz mit zone === 'heroes' zurueck.
     *
     * @param array<string, array<string, mixed>> $roster
     * @return array<int, array<string, mixed>>
     */
    private function formatPeersList(array $roster, string $excludePeerId): array
    {
        $peers = [];

        foreach ($roster as $pid => $entry) {
            if ((string) $pid === $excludePeerId || !is_array($entry)) {
                continue;
            }

            if (($entry['zone'] ?? 'heroes') !== 'heroes') {
                continue;
            }

            $rawEmote = is_string($entry['emote'] ?? null) ? $entry['emote'] : '';
            $comboSeq = (int) ($entry['combo_seq'] ?? 0);

            $peers[] = [
                'peer_id' => (string) $entry['peer_id'],
                'display_name' => (string) $entry['display_name'],
                'level' => (int) $entry['level'],
                'ship_class' => (string) ($entry['ship_class'] ?? 'KLASSE C'),
                'x' => (float) $entry['x'],
                'y' => (float) ($entry['y'] ?? 0.0),
                'z' => (float) $entry['z'],
                'vx' => (float) ($entry['vx'] ?? 0.0),
                'vz' => (float) ($entry['vz'] ?? 0.0),
                'rot_y' => (float) $entry['rot_y'],
                'speed' => (float) $entry['speed'],
                'zone' => 'heroes',
                'combo_seq' => $comboSeq,
                'combo_stage' => (int) ($entry['combo_stage'] ?? 0),
                'weapon_seq' => (int) ($entry['weapon_seq'] ?? $comboSeq),
                'emote' => in_array($rawEmote, self::ALLOWED_EMOTES, true) ? $rawEmote : '',
                'ability_slot' => max(0, min(4, (int) ($entry['ability_slot'] ?? 0))),
            ];
        }

        return $peers;
    }

    /**
     * Baut die Instanzen-Liste fuer das UI:
     * Enthaelt immer alle aktuell belegten Instanzen PLUS mindestens 1 komplett freie Ausweich-Instanz (0/20).
     *
     * @param array<int, array<string, array<string, mixed>>> $rosters
     * @return array<int, array{id: int, count: int, capacity: int, is_full: bool, is_current: bool}>
     */
    private function buildInstancesOverview(array $rosters, int $currentInstanceId): array
    {
        $occupiedIds = [];
        foreach ($rosters as $instId => $roster) {
            if (is_array($roster) && count($roster) > 0) {
                $occupiedIds[(int) $instId] = count($roster);
            }
        }

        if (!isset($occupiedIds[$currentInstanceId]) && $currentInstanceId >= 1 && $currentInstanceId <= self::MAX_INSTANCES) {
            $occupiedIds[$currentInstanceId] = count($rosters[$currentInstanceId] ?? []);
        }

        $freeFallbackId = null;
        for ($id = 1; $id <= self::MAX_INSTANCES; $id++) {
            if (($occupiedIds[$id] ?? 0) === 0) {
                $freeFallbackId = $id;
                break;
            }
        }

        $idsToShow = array_keys($occupiedIds);
        if ($freeFallbackId !== null && !in_array($freeFallbackId, $idsToShow, true)) {
            $idsToShow[] = $freeFallbackId;
        }

        if (empty($idsToShow)) {
            $idsToShow = [1];
        }

        sort($idsToShow, SORT_NUMERIC);

        $list = [];
        foreach ($idsToShow as $id) {
            $count = (int) ($occupiedIds[$id] ?? 0);
            $list[] = [
                'id' => (int) $id,
                'count' => $count,
                'capacity' => self::HARD_CAP,
                'is_full' => $count >= self::HARD_CAP,
                'is_current' => (int) $id === $currentInstanceId,
            ];
        }

        return $list;
    }

    /**
     * Waehlt die kleinste Instanz 1..MAX_INSTANCES mit < SOFT_CAP (16) aktiven Spielern.
     *
     * @param array<int, array<string, array<string, mixed>>> $rosters
     */
    private function findAutoShardInstance(array &$rosters, string $peerId, int $now): int
    {
        for ($id = 1; $id <= self::MAX_INSTANCES; $id++) {
            $roster = $rosters[$id] ?? $this->loadPrunedInstanceRoster($id, $now);
            if (!empty($roster)) {
                $rosters[$id] = $roster;
            }
            $otherCount = $this->countOtherPlayers($roster, $peerId);
            if ($otherCount < self::SOFT_CAP) {
                return $id;
            }
        }

        for ($id = 1; $id <= self::MAX_INSTANCES; $id++) {
            $roster = $rosters[$id] ?? [];
            if ($this->countOtherPlayers($roster, $peerId) < self::HARD_CAP) {
                return $id;
            }
        }

        return self::MAX_INSTANCES;
    }

    /**
     * @param array<int, array<string, array<string, mixed>>> $rosters
     */
    private function findPlayerCurrentInstance(string $peerId, array &$rosters, int $now): ?int
    {
        $ptr = Cache::get($this->playerPointerKey($peerId));
        if (is_numeric($ptr)) {
            $ptrId = (int) $ptr;
            if ($ptrId >= 1 && $ptrId <= self::MAX_INSTANCES) {
                $roster = $rosters[$ptrId] ?? $this->loadPrunedInstanceRoster($ptrId, $now);
                if (isset($roster[$peerId])) {
                    $rosters[$ptrId] = $roster;

                    return $ptrId;
                }
            }
        }

        foreach ($rosters as $instId => $roster) {
            if (isset($roster[$peerId])) {
                return (int) $instId;
            }
        }

        return null;
    }

    private function resolveTargetInstanceForSync(string $peerId, int $now): int
    {
        $ptr = Cache::get($this->playerPointerKey($peerId));
        if (is_numeric($ptr)) {
            $ptrId = (int) $ptr;
            if ($ptrId >= 1 && $ptrId <= self::MAX_INSTANCES) {
                return $ptrId;
            }
        }

        $rosters = $this->loadAndPruneActiveRosters($now);
        $current = $this->findPlayerCurrentInstance($peerId, $rosters, $now);
        if ($current !== null) {
            return $current;
        }

        return $this->findAutoShardInstance($rosters, $peerId, $now);
    }

    private function ensureInstanceInActiveIndex(int $instanceId): void
    {
        $rawIds = Cache::get(self::CACHE_ACTIVE_IDS_KEY, []);
        $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];
        if (!in_array($instanceId, $ids, true)) {
            $ids[] = $instanceId;
            sort($ids, SORT_NUMERIC);
            Cache::put(self::CACHE_ACTIVE_IDS_KEY, array_values(array_unique($ids)), 300);
        }
    }

    /**
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function buildReadOnlyHeroSnapshot(array $actor, string $peerId, ?int $fallbackInstanceId = null): array
    {
        $now = Carbon::now()->getTimestamp();
        $rosters = $this->loadAndPruneActiveRosters($now);
        $instanceId = $fallbackInstanceId ?? ($this->findPlayerCurrentInstance($peerId, $rosters, $now) ?? 1);
        $roster = $rosters[$instanceId] ?? $this->loadPrunedInstanceRoster($instanceId, $now);

        return [
            'ok' => true,
            'authenticated' => true,
            'solo_mode' => false,
            'zone' => 'heroes',
            'instance_id' => $instanceId,
            'soft_cap' => self::SOFT_CAP,
            'hard_cap' => self::HARD_CAP,
            'player' => $this->formatPlayerSelf($actor),
            'peers' => $this->formatPeersList($roster, $peerId),
            'messages' => $this->getInstanceMessages($instanceId, $peerId),
            'instances' => $this->buildInstancesOverview($rosters, $instanceId),
        ];
    }

    private function isLobbyChatFloodRateLimited(string $actorKey): bool
    {
        $now = Carbon::now()->getTimestamp();
        $key = 'sf_fw_lobby_chat_burst_v1_' . md5($actorKey);
        $timestamps = Cache::get($key, []);
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $cutoff = $now - self::CHAT_BURST_WINDOW_SECONDS;
        $recent = [];
        foreach ($timestamps as $ts) {
            if (is_int($ts) && $ts >= $cutoff) {
                $recent[] = $ts;
            }
        }

        $recent[] = $now;
        Cache::put($key, array_slice($recent, -25), self::CHAT_BURST_WINDOW_SECONDS + 5);

        return count($recent) > self::CHAT_BURST_MAX_ATTEMPTS;
    }

    private function isOnLobbyChatCooldown(string $actorKey): bool
    {
        $until = (float) Cache::get('sf_fw_lobby_chat_cd_v1_' . md5($actorKey), 0);

        return $until > microtime(true);
    }

    private function touchLobbyChatCooldown(string $actorKey): void
    {
        Cache::put(
            'sf_fw_lobby_chat_cd_v1_' . md5($actorKey),
            microtime(true) + self::CHAT_COOLDOWN_SECONDS,
            self::CHAT_COOLDOWN_SECONDS + 2
        );
    }

    private function containsHtmlOrScriptPayload(string $raw): bool
    {
        if ($raw === '') {
            return false;
        }

        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ([$raw, $decoded] as $candidate) {
            if (preg_match('/<\s*\/?\s*[a-z!?\[]/iu', $candidate)) {
                return true;
            }
            if (preg_match('/(?:javascript|vbscript|data)\s*:/iu', $candidate)) {
                return true;
            }
            if (preg_match('/\bon(?:error|load|click|mouseover|mouseenter|focus|blur|animationstart|toggle)\s*=/iu', $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $roster
     */
    private function countOtherPlayers(array $roster, string $excludePeerId): int
    {
        $count = 0;
        foreach ($roster as $pid => $entry) {
            if ((string) $pid !== $excludePeerId && is_array($entry) && (($entry['zone'] ?? 'heroes') === 'heroes')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array<string, array<string, mixed>>>
     */
    private function loadAndPruneActiveRosters(int $now): array
    {
        $rawIds = Cache::get(self::CACHE_ACTIVE_IDS_KEY, []);
        $ids = is_array($rawIds) ? $rawIds : [];

        $rosters = [];
        foreach ($ids as $rawId) {
            $id = (int) $rawId;
            if ($id < 1 || $id > self::MAX_INSTANCES) {
                continue;
            }
            $pruned = $this->loadPrunedInstanceRoster($id, $now);
            if (!empty($pruned)) {
                $rosters[$id] = $pruned;
            }
        }

        return $rosters;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPrunedInstanceRoster(int $instanceId, int $now): array
    {
        $key = $this->instanceRosterKey($instanceId);
        $raw = Cache::get($key, []);
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $active = [];
        $dirty = false;

        foreach ($raw as $pid => $entry) {
            if (
                !is_array($entry)
                || (int) ($entry['expires_at'] ?? 0) <= $now
                || (($entry['zone'] ?? 'heroes') !== 'heroes')
            ) {
                $dirty = true;
                continue;
            }
            $active[(string) $pid] = $entry;
        }

        if ($dirty) {
            if (empty($active)) {
                Cache::forget($key);
            } else {
                Cache::put($key, $active, self::HEARTBEAT_TTL_SECONDS + 30);
            }
        }

        return $active;
    }

    /**
     * @param array<string, array<string, mixed>> $roster
     * @param array<int, array<string, array<string, mixed>>> $rostersMap
     */
    private function persistInstanceRoster(int $instanceId, array $roster, array &$rostersMap): void
    {
        $key = $this->instanceRosterKey($instanceId);
        if (empty($roster)) {
            unset($rostersMap[$instanceId]);
            Cache::forget($key);

            return;
        }

        $rostersMap[$instanceId] = $roster;
        Cache::put($key, $roster, self::HEARTBEAT_TTL_SECONDS + 30);
    }

    /**
     * @param array<int, array<string, array<string, mixed>>> $rosters
     */
    private function persistActiveIdsIndex(array $rosters): void
    {
        $activeIds = [];
        foreach ($rosters as $instId => $roster) {
            if (is_array($roster) && !empty($roster)) {
                $activeIds[] = (int) $instId;
            }
        }

        if (empty($activeIds)) {
            Cache::forget(self::CACHE_ACTIVE_IDS_KEY);

            return;
        }

        sort($activeIds, SORT_NUMERIC);
        Cache::put(self::CACHE_ACTIVE_IDS_KEY, array_values(array_unique($activeIds)), 300);
    }

    private function instanceRosterKey(int $instanceId): string
    {
        return self::CACHE_INSTANCE_PREFIX . $instanceId;
    }

    private function instanceChatKey(int $instanceId): string
    {
        return self::CACHE_INST_CHAT_PREFIX . $instanceId;
    }

    private function instanceLockKey(int $instanceId): string
    {
        return self::CACHE_INST_LOCK_PREFIX . $instanceId;
    }

    private function playerPointerKey(string $peerId): string
    {
        return self::CACHE_PLAYER_PTR_PREFIX . $peerId;
    }

    /**
     * Schneller Per-Instanz-Lock (sf_fw_lobby_inst_lock_v1_{instanceId}) mit sauberem LockTimeoutException-Abfang (0x HTTP 500).
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param Closure(): TReturn $onTimeoutFallback
     * @return TReturn
     */
    private function withInstanceLock(int $instanceId, Closure $callback, Closure $onTimeoutFallback): mixed
    {
        $safeId = max(1, min(self::MAX_INSTANCES, $instanceId));
        $store = Cache::getStore();

        if ($store instanceof LockProvider) {
            try {
                return Cache::lock($this->instanceLockKey($safeId), 4)->block(2, $callback);
            } catch (LockTimeoutException) {
                return $onTimeoutFallback();
            }
        }

        return $callback();
    }

    /**
     * Globaler Sharding-Lock (nur fuer Join / Switch / Evict) mit sauberem LockTimeoutException-Abfang (0x HTTP 500).
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param (Closure(): TReturn)|null $onTimeoutFallback
     * @return TReturn
     */
    private function withLobbyLock(Closure $callback, ?Closure $onTimeoutFallback = null): mixed
    {
        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            try {
                return Cache::lock(self::CACHE_LOCK_KEY, 4)->block(2, $callback);
            } catch (LockTimeoutException) {
                if ($onTimeoutFallback !== null) {
                    return $onTimeoutFallback();
                }

                return null;
            }
        }

        return $callback();
    }
}
