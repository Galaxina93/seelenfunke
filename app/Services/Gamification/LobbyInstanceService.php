<?php

namespace App\Services\Gamification;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\System\SystemSecurityService;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Solo-Zonen: Jeder Besucher ist hier komplett alleine unterwegs (belegt keinen Instanz-Slot).
     *
     * @var array<int, string>
     */
    public const SOLO_ZONES = [
        'gallery',
        'lava',
        'labyrinth',
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
    ];

    private const CACHE_ACTIVE_IDS_KEY = 'sf_fw_lobby_active_instances_v1';
    private const CACHE_INSTANCE_PREFIX = 'sf_fw_lobby_inst_v1_';
    private const CACHE_PLAYER_PTR_PREFIX = 'sf_fw_lobby_pptr_v1_';
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

        $forgedMatched = [];
        foreach ($authorityCheckKeys as $key) {
            if (array_key_exists($key, $rawInput)) {
                $forgedMatched[] = $key;
            }
        }

        $coordFlags = [];
        foreach (['x', 'y', 'z', 'rot_y', 'rotY'] as $coordKey) {
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
            'instances' => [],
        ];
    }

    /**
     * Bucht einen eingeloggten Spieler zonenabhaengig ein:
     * - Solo-Zonen ('gallery', 'lava', 'labyrinth'): solo_mode = true, gibt ggf. belegten Heldenhallen-Slot frei.
     * - Halle der Helden ('heroes'): Auto-Sharding < SOFT_CAP = 16 oder Wunsch-Instanz < HARD_CAP = 20.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function join(array $actor, array $input = []): array
    {
        $zone = self::normalizeZone($input['zone'] ?? null, 'gallery');

        if (self::isSoloZone($zone)) {
            $this->evictPlayerFromHeroInstances((string) $actor['peer_id']);

            return $this->buildSoloModePayload($actor, $zone);
        }

        return $this->withLobbyLock(function () use ($actor, $input): array {
            $now = Carbon::now()->getTimestamp();
            $nowMs = Carbon::now()->getTimestampMs();
            $peerId = (string) $actor['peer_id'];
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
                'instances' => $this->buildInstancesOverview($rosters, $selectedInstance),
            ];
        });
    }

    /**
     * Aktualisiert Position, Blickrichtung, Geschwindigkeit und Zone inkl. 25s Heartbeat-Verlaengerung.
     * In Solo-Zonen ('gallery', 'lava', 'labyrinth') wird der Spieler sofort aus Heldenhallen-Instanzen ausgebucht.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sync(array $actor, array $input = []): array
    {
        $zone = self::normalizeZone($input['zone'] ?? null, 'gallery');

        if (self::isSoloZone($zone)) {
            $this->evictPlayerFromHeroInstances((string) $actor['peer_id']);

            return $this->buildSoloModePayload($actor, $zone);
        }

        return $this->withLobbyLock(function () use ($actor, $input): array {
            $now = Carbon::now()->getTimestamp();
            $nowMs = Carbon::now()->getTimestampMs();
            $peerId = (string) $actor['peer_id'];
            $actorKey = (string) ($actor['actor_key'] ?? $peerId);

            $rosters = $this->loadAndPruneActiveRosters($now);
            $instanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

            if ($instanceId === null) {
                $instanceId = $this->findAutoShardInstance($rosters, $peerId, $now);
                if (!isset($rosters[$instanceId])) {
                    $rosters[$instanceId] = $this->loadPrunedInstanceRoster($instanceId, $now);
                }
            }

            $previousEntry = $rosters[$instanceId][$peerId] ?? null;
            $input['zone'] = 'heroes';
            $state = $this->sanitizeState($input, $previousEntry, 'heroes', $nowMs, $actorKey);
            $rosters[$instanceId][$peerId] = $this->buildRosterEntry($actor, $state, $now);

            $this->persistInstanceRoster($instanceId, $rosters[$instanceId], $rosters);
            $this->persistActiveIdsIndex($rosters);
            Cache::put($this->playerPointerKey($peerId), $instanceId, self::HEARTBEAT_TTL_SECONDS);

            return [
                'ok' => true,
                'authenticated' => true,
                'solo_mode' => false,
                'zone' => 'heroes',
                'instance_id' => $instanceId,
                'soft_cap' => self::SOFT_CAP,
                'hard_cap' => self::HARD_CAP,
                'player' => $this->formatPlayerSelf($actor),
                'peers' => $this->formatPeersList($rosters[$instanceId], $peerId),
                'instances' => $this->buildInstancesOverview($rosters, $instanceId),
            ];
        });
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

        return $this->withLobbyLock(function () use ($actor, $targetInstance, $input): array {
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
                'instances' => $this->buildInstancesOverview($rosters, $targetInstance),
            ];
        });
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
     * Entfernt einen Spieler sauber aus allen aktiven Heldenhallen-Rostern und gibt seinen Slot sofort frei.
     */
    private function evictPlayerFromHeroInstances(string $peerId): void
    {
        $hasPointer = Cache::get($this->playerPointerKey($peerId)) !== null;
        $hasActiveIds = !empty(Cache::get(self::CACHE_ACTIVE_IDS_KEY, []));

        if (!$hasPointer && !$hasActiveIds) {
            return;
        }

        $this->withLobbyLock(function () use ($peerId): void {
            $now = now()->getTimestamp();
            $rosters = $this->loadAndPruneActiveRosters($now);
            $currentInstanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

            if ($currentInstanceId !== null && isset($rosters[$currentInstanceId][$peerId])) {
                unset($rosters[$currentInstanceId][$peerId]);
                $this->persistInstanceRoster($currentInstanceId, $rosters[$currentInstanceId], $rosters);
            }

            foreach (array_keys($rosters) as $instId) {
                if (isset($rosters[$instId][$peerId])) {
                    unset($rosters[$instId][$peerId]);
                    $this->persistInstanceRoster((int) $instId, $rosters[$instId], $rosters);
                }
            }

            $this->persistActiveIdsIndex($rosters);
            Cache::forget($this->playerPointerKey($peerId));
        });
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
            'instances' => [],
        ];
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
    private function buildCustomerActor(Customer $customer): array
    {
        $actorKey = 'customer:' . (string) $customer->id;
        $peerId = $this->makePeerId($actorKey);
        $anonFallback = 'Funki #' . strtoupper(substr($peerId, 0, 4));

        $profile = null;
        $level = 1;

        if (Schema::hasTable('customer_gamifications')) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->first();
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
            'peer_id' => $peerId,
            'own_display_name' => $ownDisplayName,
            'peer_display_name' => $peerDisplayName,
            'level' => $level,
            'level_title' => $levelTitle,
            'ship_class' => $shipClass,
        ];
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
     *     rot_y: float,
     *     speed: float,
     *     zone: string,
     *     combo_seq: int,
     *     combo_stage: int,
     *     emote: string,
     *     ability_slot: int
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
        $rawRot = $this->extractFiniteFloat($input, ['rot_y', 'rotY'], (float) ($previous['rot_y'] ?? 0.0));
        $rawSpeed = $this->extractFiniteFloat($input, ['speed'], (float) ($previous['speed'] ?? 0.0));

        $rawComboSeq = (int) $this->extractFiniteFloat($input, ['combo_seq', 'comboSeq'], (float) ($previous['combo_seq'] ?? 0));
        $rawComboStage = (int) $this->extractFiniteFloat($input, ['combo_stage', 'comboStage'], (float) ($previous['combo_stage'] ?? 0));
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
            $minX = 14.0;
            $maxX = 85.0;
            $minY = -2.0;
            $maxY = 12.0;
            $minZ = -18.0;
            $maxZ = 18.0;
        } elseif ($zone === 'labyrinth') {
            $minX = -36.0;
            $maxX = -12.0;
            $minY = -0.5;
            $maxY = 6.0;
            $minZ = -16.0;
            $maxZ = 16.0;
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
            'rot_y' => round(max(-6.3, min(6.3, $rawRot)), 4),
            'speed' => round(max(0.0, min(self::MAX_SPEED, $rawSpeed)), 3),
            'zone' => $zone,
            'combo_seq' => max(0, min(10000000, $rawComboSeq)),
            'combo_stage' => max(0, min(4, $rawComboStage)),
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
     * @param array{
     *     x: float,
     *     y: float,
     *     z: float,
     *     rot_y: float,
     *     speed: float,
     *     zone: string,
     *     combo_seq: int,
     *     combo_stage: int,
     *     emote: string,
     *     ability_slot: int
     * } $state
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
            'rot_y' => $state['rot_y'],
            'speed' => $state['speed'],
            'zone' => 'heroes',
            'combo_seq' => $state['combo_seq'],
            'combo_stage' => $state['combo_stage'],
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

            $peers[] = [
                'peer_id' => (string) $entry['peer_id'],
                'display_name' => (string) $entry['display_name'],
                'level' => (int) $entry['level'],
                'ship_class' => (string) ($entry['ship_class'] ?? 'KLASSE C'),
                'x' => (float) $entry['x'],
                'y' => (float) ($entry['y'] ?? 0.0),
                'z' => (float) $entry['z'],
                'rot_y' => (float) $entry['rot_y'],
                'speed' => (float) $entry['speed'],
                'zone' => 'heroes',
                'combo_seq' => (int) ($entry['combo_seq'] ?? 0),
                'combo_stage' => (int) ($entry['combo_stage'] ?? 0),
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

    private function playerPointerKey(string $peerId): string
    {
        return self::CACHE_PLAYER_PTR_PREFIX . $peerId;
    }

    /**
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @return TReturn
     */
    private function withLobbyLock(Closure $callback): mixed
    {
        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            return Cache::lock(self::CACHE_LOCK_KEY, 4)->block(2, $callback);
        }

        return $callback();
    }
}
