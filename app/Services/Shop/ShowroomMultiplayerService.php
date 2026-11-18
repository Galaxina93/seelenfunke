<?php

namespace App\Services\Shop;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\System\SystemSetting;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\LobbyInstanceService;
use App\Services\Gamification\PlayerNameValidator;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ShowroomMultiplayerService
{
    public const MAX_PLAYERS_PER_ROOM = 12;
    public const MAX_SHARD_ROOMS = 64;
    public const PLAYER_TTL_SECONDS = 20;
    public const CHAT_RING_BUFFER_SIZE = 15;
    public const CHAT_TTL_SECONDS = 600; // 10 Minuten flüchtiger Speicher
    public const MAX_SPEED = 9.5;
    public const MIN_SYNC_TELEPORT_STEP = 14.0;
    public const MAX_SPEED_WITH_MARGIN = 13.5;
    public const EMOTE_COOLDOWN_MS = 2500;
    public const CHAT_BURST_WINDOW_SECONDS = 15;
    public const CHAT_BURST_MAX_ATTEMPTS = 8;

    /**
     * Strikte Whitelist erlaubter Showroom-Emotes.
     *
     * @var array<int, string>
     */
    public const ALLOWED_EMOTES = [
        'wave',
        'dance',
        'cheer',
        'spark',
        'bow',
        'sit',
        'clap',
        'heart',
        'gg',
    ];

    /**
     * Strikte Whitelist erlaubter 3D-Waffen-Effekte.
     *
     * @var array<int, string>
     */
    public const ALLOWED_WEAPON_EFFECTS = [
        'gold_blade',
        'sapphire_staff',
        'emerald_bow',
        'cosmos_blade',
    ];

    public function __construct(
        private readonly ShowroomChatGuardService $chatGuard
    ) {
    }

    public function isMultiplayerEnabled(): bool
    {
        if (Schema::hasTable('shop_settings')) {
            $raw = SystemSetting::get('showroom_multiplayer_enabled', '1');

            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        $raw = shop_setting('showroom_multiplayer_enabled', true);

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Erzeugt den initialen Multiplayer-Block für GET /shop/showroom-data.
     * Gäste bleiben strikt Solo (0 Netzwerk-Ticks, 0 Chat, 0 Raum-Belegung).
     *
     * @return array<string, mixed>
     */
    public function buildBootstrapPayload(?Customer $customer): array
    {
        if ($customer === null) {
            return [
                'enabled' => false,
                'is_authenticated' => false,
                'solo_only' => true,
                'room_id' => null,
                'max_players_per_room' => self::MAX_PLAYERS_PER_ROOM,
                'sync_interval_ms' => 0,
                'player_id' => null,
                'display_name' => null,
                'level' => 2,
                'weapon' => null,
            ];
        }

        $identity = $this->resolveCustomerIdentity($customer);

        if (!$this->isMultiplayerEnabled()) {
            return [
                'enabled' => false,
                'is_authenticated' => true,
                'solo_only' => true,
                'room_id' => null,
                'max_players_per_room' => self::MAX_PLAYERS_PER_ROOM,
                'sync_interval_ms' => 0,
                'player_id' => $identity['player_id'],
                'display_name' => $identity['display_name'],
                'level' => $identity['level'],
                'weapon' => $identity['weapon'],
            ];
        }

        $roomId = $this->assignOrRefreshRoom($identity, [
            'x' => 0.0,
            'y' => 0.0,
            'z' => 8.4,
            'rot_y' => 3.1416,
            'speed' => 0.0,
            'zone' => 'gallery',
            'jumping' => false,
            'weapon_seq' => 0,
            'emote' => null,
            'solo_mode' => false,
            'pos_synced' => false,
        ], 'customer:' . (string) $customer->id);

        return [
            'enabled' => true,
            'is_authenticated' => true,
            'solo_only' => false,
            'room_id' => $roomId,
            'max_players_per_room' => self::MAX_PLAYERS_PER_ROOM,
            'sync_interval_ms' => 750,
            'player_id' => $identity['player_id'],
            'display_name' => $identity['display_name'],
            'level' => $identity['level'],
            'weapon' => $identity['weapon'],
        ];
    }

    /**
     * Verarbeitet einen 5-Hz State-Tick eines eingeloggten Kunden und liefert alle aktiven Mitspieler + Raum-Nachrichten.
     *
     * @param array<string, mixed> $stateInput
     * @return array<string, mixed>
     */
    public function syncPlayerState(Customer $customer, array $stateInput): array
    {
        $actorKey = 'customer:' . (string) $customer->id;
        $identity = $this->resolveCustomerIdentity($customer);
        $soloMode = filter_var($stateInput['soloMode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $rawRotInput = array_key_exists('rotY', $stateInput)
            ? $stateInput['rotY']
            : ($stateInput['rot_y'] ?? 3.1416);

        $rawZone = isset($stateInput['zone']) && is_string($stateInput['zone'])
            ? trim($stateInput['zone'])
            : 'gallery';
        $zone = in_array($rawZone, ['gallery', 'heroes', 'lava'], true) ? $rawZone : 'gallery';

        $rawWeaponSeq = array_key_exists('weaponActionSeq', $stateInput) && LobbyInstanceService::isValidFiniteNumeric($stateInput['weaponActionSeq'])
            ? (int) $stateInput['weaponActionSeq']
            : 0;

        $emote = $this->sanitizeAndThrottleEmote(
            $identity['player_id'],
            $stateInput['emote'] ?? null,
            $actorKey
        );

        $sanitizedState = [
            'x' => round(max(-12.0, min(12.0, LobbyInstanceService::parseFiniteFloat($stateInput['x'] ?? null, 0.0))), 3),
            'y' => round(max(0.0, min(4.5, LobbyInstanceService::parseFiniteFloat($stateInput['y'] ?? null, 0.0))), 3),
            'z' => round(max(-240.0, min(20.0, LobbyInstanceService::parseFiniteFloat($stateInput['z'] ?? null, 8.4))), 3),
            'rot_y' => round(max(-7.0, min(7.0, LobbyInstanceService::parseFiniteFloat($rawRotInput, 3.1416))), 3),
            'speed' => round(max(0.0, min(self::MAX_SPEED, LobbyInstanceService::parseFiniteFloat($stateInput['speed'] ?? null, 0.0))), 3),
            'zone' => $zone,
            'jumping' => filter_var($stateInput['jumping'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'weapon_seq' => max(0, min(1000000, $rawWeaponSeq)),
            'emote' => $emote,
            'solo_mode' => $soloMode,
            'pos_synced' => true,
            'has_explicit_x' => array_key_exists('x', $stateInput) && LobbyInstanceService::isValidFiniteNumeric($stateInput['x']),
            'has_explicit_z' => array_key_exists('z', $stateInput) && LobbyInstanceService::isValidFiniteNumeric($stateInput['z']),
        ];

        $roomId = $this->assignOrRefreshRoom($identity, $sanitizedState, $actorKey);

        if ($soloMode) {
            return [
                'success' => true,
                'room_id' => $roomId,
                'player_id' => $identity['player_id'],
                'display_name' => $identity['display_name'],
                'level' => $identity['level'],
                'solo_mode' => true,
                'peers' => [],
                'messages' => [],
            ];
        }

        $peers = $this->getActiveRoomPeers($roomId, $identity['player_id']);
        $messages = $this->getRoomMessages($roomId);

        return [
            'success' => true,
            'room_id' => $roomId,
            'player_id' => $identity['player_id'],
            'display_name' => $identity['display_name'],
            'level' => $identity['level'],
            'solo_mode' => false,
            'peers' => $peers,
            'messages' => $messages,
        ];
    }

    /**
     * Prüft und veröffentlicht eine kurze Nachricht im aktuellen 12er-Raum des Kunden (flüchtiger Ringpuffer, 0 DB-Zeilen).
     *
     * @return array{success: bool, status: int, code?: ?string, message?: string, entry?: array<string, mixed>, room_id?: string}
     */
    public function postChatMessage(Customer $customer, string $rawMessage): array
    {
        $customerId = (string) $customer->id;
        $actorKey = 'customer:' . $customerId;

        if ($this->isChatFloodRateLimited($customerId)) {
            LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-chat', 'chat_flood_rate_limited');

            return [
                'success' => false,
                'status' => 429,
                'code' => 'rate_limited',
                'message' => 'Zu viele Chat-Anfragen in kurzer Zeit. Bitte warte einen Moment.',
            ];
        }

        if ($this->containsHtmlOrScriptPayload($rawMessage)) {
            $this->chatGuard->touchCooldown($customerId);
            LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-chat', 'xss_or_html_chat_payload');

            return [
                'success' => false,
                'status' => 422,
                'code' => 'blocked_html',
                'message' => 'HTML- und Skript-Inhalte sind im Showroom-Chat nicht erlaubt.',
            ];
        }

        $check = $this->chatGuard->inspectAndSanitize($customerId, $rawMessage, true);
        if (!$check['allowed']) {
            return [
                'success' => false,
                'status' => 422,
                'code' => $check['code'],
                'message' => $check['reason'] ?? 'Nachricht wurde blockiert.',
            ];
        }

        $cleanSanitizedText = trim(str_replace(['<', '>'], '', $check['sanitized']));
        if ($cleanSanitizedText === '') {
            return [
                'success' => false,
                'status' => 422,
                'code' => 'empty',
                'message' => 'Bitte gib eine Nachricht ein.',
            ];
        }

        $identity = $this->resolveCustomerIdentity($customer);
        $roomId = $this->resolveExistingOrAssignRoom($identity, $actorKey);

        return $this->withShowroomLock(
            $roomId,
            function () use ($identity, $roomId, $cleanSanitizedText): array {
                $nowMs = Carbon::now()->getTimestampMs();
                $entry = [
                    'id' => 'm_' . Str::lower(Str::random(12)),
                    'player_id' => $identity['player_id'],
                    'display_name' => $identity['display_name'],
                    'level' => $identity['level'],
                    'rank_class' => $identity['weapon']['rank_class'] ?? 'C',
                    'class_label' => $identity['weapon']['class_label'] ?? 'KLASSE C',
                    'text' => $cleanSanitizedText,
                    'created_at_ms' => $nowMs,
                    'expires_at_ms' => $nowMs + (self::CHAT_TTL_SECONDS * 1000),
                ];

                $chatKey = $this->roomChatCacheKey($roomId);
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

                $validMessages[] = $entry;
                if (count($validMessages) > self::CHAT_RING_BUFFER_SIZE) {
                    $validMessages = array_slice($validMessages, -self::CHAT_RING_BUFFER_SIZE);
                }

                Cache::put($chatKey, $validMessages, self::CHAT_TTL_SECONDS);

                return [
                    'success' => true,
                    'status' => 200,
                    'room_id' => $roomId,
                    'entry' => $entry,
                ];
            },
            static fn (): array => [
                'success' => false,
                'status' => 409,
                'code' => 'busy',
                'message' => 'Der Showroom-Chat verarbeitet gerade eine Anfrage. Bitte kurz warten.',
            ]
        );
    }

    /**
     * Meldet einen Mitspieler anhand seiner anonymen Showroom-Spieler-ID (p_...).
     *
     * @return array{success: bool, status: int, shadow_muted?: bool, reports_count?: int, message?: string}
     */
    public function reportPlayerByPublicId(Customer $reporter, string $targetPublicPlayerId): array
    {
        $cleanTargetPid = preg_replace('/[^a-zA-Z0-9_]/', '', trim($targetPublicPlayerId)) ?? '';
        if ($cleanTargetPid === '') {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Ungültige Spieler-Kennung.',
            ];
        }

        $targetCustomerId = $this->resolveCustomerIdFromPublicId($cleanTargetPid);
        if ($targetCustomerId === null || $targetCustomerId === (string) $reporter->id) {
            return [
                'success' => false,
                'status' => 422,
                'message' => 'Meldung konnte nicht zugeordnet werden.',
            ];
        }

        // Nachweisbare Praesenz des Meldenden UND des gemeldeten Spielers im selben aktiven Raum pruefen
        $reporterIdentity = $this->resolveCustomerIdentity($reporter);
        $reporterPublicId = $reporterIdentity['player_id'];
        $reporterRoomId = Cache::get($this->playerRoomCacheKey($reporterPublicId));

        if (!is_string($reporterRoomId) || !str_starts_with($reporterRoomId, 'louvre-room-')) {
            return [
                'success' => false,
                'status' => 422,
                'code' => 'not_in_room',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Du befindest dich aktuell in keinem aktiven Showroom-Raum.',
            ];
        }

        $now = Carbon::now()->getTimestamp();
        $nowMs = Carbon::now()->getTimestampMs();
        $roster = $this->loadPrunedRoomRoster($reporterRoomId, $now);

        if (!isset($roster[$reporterPublicId]) || !empty($roster[$reporterPublicId]['solo_mode'])) {
            return [
                'success' => false,
                'status' => 422,
                'code' => 'not_in_room',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Meldungen sind nur im aktiven Mehrspieler-Raum möglich.',
            ];
        }

        $targetInRoster = isset($roster[$cleanTargetPid]) && empty($roster[$cleanTargetPid]['solo_mode']);
        $targetInRoomChat = false;

        if (!$targetInRoster) {
            $rawChat = Cache::get($this->roomChatCacheKey($reporterRoomId), []);
            if (is_array($rawChat)) {
                foreach ($rawChat as $msg) {
                    if (
                        is_array($msg)
                        && (int) ($msg['expires_at_ms'] ?? 0) > $nowMs
                        && (string) ($msg['player_id'] ?? '') === $cleanTargetPid
                    ) {
                        $targetInRoomChat = true;
                        break;
                    }
                }
            }
        }

        if (!$targetInRoster && !$targetInRoomChat) {
            LobbyInstanceService::logTamperThrottled(
                'customer:' . $reporter->id,
                'shop/showroom-report',
                'cross_room_or_phantom_report_blocked',
                ['target_pid' => $cleanTargetPid, 'room_id' => $reporterRoomId]
            );

            return [
                'success' => false,
                'status' => 422,
                'code' => 'not_in_same_room',
                'reports_count' => 0,
                'shadow_muted' => false,
                'message' => 'Spieler befindet sich nicht im selben Raum.',
            ];
        }

        $result = $this->chatGuard->reportPlayer((string) $reporter->id, $targetCustomerId);

        return [
            'success' => $result['accepted'],
            'status' => $result['accepted'] ? 200 : 422,
            'reports_count' => $result['reports_count'],
            'shadow_muted' => $result['shadow_muted'],
        ];
    }

    public function makePublicPlayerId(string $customerId): string
    {
        $secret = (string) (config('app.key') ?: 'seelenfunke_louvre_showroom');
        $publicId = 'p_' . substr(hash_hmac('sha256', $customerId, $secret), 0, 16);

        Cache::put('sf_showroom_pid_map_' . $publicId, $customerId, 86400);

        return $publicId;
    }

    public function resolveCustomerIdFromPublicId(string $publicPlayerId): ?string
    {
        $val = Cache::get('sf_showroom_pid_map_' . $publicPlayerId);

        return is_string($val) && $val !== '' ? $val : null;
    }

    /**
     * Ermittelt 100 % DSGVO-konform die Showroom-Identität des Kunden (NIEMALS Vorname, Nachname oder E-Mail!).
     *
     * @return array{player_id: string, display_name: string, level: int, glb_url: string, weapon: array<string, string>}
     */
    public function resolveCustomerIdentity(Customer $customer): array
    {
        $publicId = $this->makePublicPlayerId((string) $customer->id);
        $profile = null;
        $level = 1;

        if (Schema::hasTable('customer_gamifications')) {
            $profile = CustomerGamification::where('customer_id', $customer->id)->first();
            if ($profile) {
                $sparks = (int) ($profile->funken_total_earned ?? 0);
                $rawLvl = (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks($sparks));
                $level = min(GameConfig::MAX_LEVEL, max(1, $rawLvl));
            }
        }

        $lvlCfg = GameConfig::getLevelConfig($level);
        $modelRel = ltrim((string) ($lvlCfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_1_rags.glb'), '/');
        $displayName = $this->resolveSafeZeroPiiDisplayName($customer, $profile);
        $weapon = $this->resolvePlayerWeapon($profile, $level);

        return [
            'player_id' => $publicId,
            'display_name' => $displayName,
            'level' => $level,
            'glb_url' => '/' . $modelRel,
            'weapon' => $weapon,
        ];
    }

    /**
     * Garantiert 0 PII im Showroom: Niemals bürgerlicher Vorname, Nachname oder E-Mail-Adresse.
     * Nutzt ausschließlich geprüfte Gamification-Spielernamen oder den anonymen Fallback "Funki #XXXX".
     */
    public function resolveSafeZeroPiiDisplayName(Customer $customer, ?CustomerGamification $profile): string
    {
        $fallbackNumber = str_pad((string) ((abs(crc32((string) $customer->id)) % 9000) + 1000), 4, '0', STR_PAD_LEFT);
        $fallbackName = 'Funki #' . $fallbackNumber;

        if (!$profile || (bool) $profile->use_real_name) {
            return $fallbackName;
        }

        $candidate = trim((string) ($profile->player_name ?? ''));
        if ($candidate === '' || str_contains($candidate, '@')) {
            return $fallbackName;
        }

        // Sicherheits-Sperre: Falls der Spieler in player_name seinen eigenen Klarnamen oder seine E-Mail eingetragen hat
        $candidateLower = mb_strtolower($candidate, 'UTF-8');
        $firstName = mb_strtolower(trim((string) ($customer->first_name ?? '')), 'UTF-8');
        $lastName = mb_strtolower(trim((string) ($customer->last_name ?? '')), 'UTF-8');
        $emailLocal = mb_strtolower(trim((string) explode('@', (string) ($customer->email ?? ''))[0]), 'UTF-8');

        if ($firstName !== '' && mb_strlen($firstName) >= 3 && str_contains($candidateLower, $firstName)) {
            return $fallbackName;
        }
        if ($lastName !== '' && mb_strlen($lastName) >= 3 && str_contains($candidateLower, $lastName)) {
            return $fallbackName;
        }
        if ($emailLocal !== '' && mb_strlen($emailLocal) >= 4 && $candidateLower === $emailLocal) {
            return $fallbackName;
        }

        $validation = PlayerNameValidator::validate($candidate, (string) $customer->id, false);
        if (!$validation['valid']) {
            return $fallbackName;
        }

        return str_replace([' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' '], ' ', $validation['sanitized']);
    }

    /**
     * Ermittelt die aktuell ausgerüstete Gamification-Waffe des Kunden inkl. Klasse C/B/A/S & 3D-Effekt-Typ.
     *
     * @return array{key: string, name: string, rank_class: string, class_label: string, effect_type: string, accent_hex: string}
     */
    public function resolvePlayerWeapon(?CustomerGamification $profile, int $level): array
    {
        $equipped = ($profile && is_array($profile->equipped_items)) ? $profile->equipped_items : [];
        $customWeapon = null;

        if (isset($equipped['weapon']) && is_array($equipped['weapon'])) {
            $customWeapon = $equipped['weapon'];
        } elseif (isset($equipped['main_hand']) && is_array($equipped['main_hand'])) {
            $customWeapon = $equipped['main_hand'];
        } else {
            foreach ($equipped as $slotItem) {
                if (is_array($slotItem) && in_array(($slotItem['slot'] ?? $slotItem['type'] ?? ''), ['weapon', 'main_hand'], true)) {
                    $customWeapon = $slotItem;
                    break;
                }
            }
        }

        if (is_array($customWeapon) && !empty($customWeapon['name'])) {
            $rawClass = strtoupper(trim((string) ($customWeapon['rank_class'] ?? $customWeapon['class'] ?? '')));
            $rawClass = str_replace('KLASSE ', '', $rawClass);
            if (!in_array($rawClass, ['C', 'B', 'A', 'S'], true)) {
                $rawClass = $this->defaultClassForLevel($level);
            }

            $rawEffect = strtolower(trim((string) ($customWeapon['effect_type'] ?? '')));
            if (!in_array($rawEffect, self::ALLOWED_WEAPON_EFFECTS, true)) {
                $rawEffect = match ($rawClass) {
                    'S' => 'cosmos_blade',
                    'A' => 'gold_blade',
                    'B' => 'sapphire_staff',
                    default => 'gold_blade',
                };
            }

            $rawHex = trim((string) ($customWeapon['accent_hex'] ?? ''));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $rawHex)) {
                $rawHex = $this->defaultHexForEffect($rawEffect);
            }

            $cleanName = mb_substr(
                str_replace(
                    ['<', '>', ' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' '],
                    ['', '', ' · ', ' · '],
                    trim(strip_tags((string) $customWeapon['name']))
                ),
                0,
                36
            );

            return [
                'key' => preg_replace('/[^a-z0-9_-]/i', '', (string) ($customWeapon['key'] ?? 'custom_weapon')) ?: 'custom_weapon',
                'name' => $cleanName ?: 'Funkenklinge',
                'rank_class' => $rawClass,
                'class_label' => 'KLASSE ' . $rawClass,
                'effect_type' => $rawEffect,
                'accent_hex' => strtoupper($rawHex),
            ];
        }

        if ($level >= 9) {
            return [
                'key' => 'cosmos_blade_s',
                'name' => 'Kosmos-Klinge',
                'rank_class' => 'S',
                'class_label' => 'KLASSE S',
                'effect_type' => 'cosmos_blade',
                'accent_hex' => '#FBBF24',
            ];
        }

        if ($level >= 6) {
            return [
                'key' => 'master_blade_a',
                'name' => 'Meister-Goldklinge',
                'rank_class' => 'A',
                'class_label' => 'KLASSE A',
                'effect_type' => 'gold_blade',
                'accent_hex' => '#D4AF37',
            ];
        }

        if ($level === 5) {
            return [
                'key' => 'emerald_bow_b',
                'name' => 'Smaragd-Bogen',
                'rank_class' => 'B',
                'class_label' => 'KLASSE B',
                'effect_type' => 'emerald_bow',
                'accent_hex' => '#10B981',
            ];
        }

        if ($level === 4) {
            return [
                'key' => 'sapphire_staff_b',
                'name' => 'Saphir-Stab',
                'rank_class' => 'B',
                'class_label' => 'KLASSE B',
                'effect_type' => 'sapphire_staff',
                'accent_hex' => '#38BDF8',
            ];
        }

        if ($level === 3) {
            return [
                'key' => 'atelier_blade_b',
                'name' => 'Lichtklinge',
                'rank_class' => 'B',
                'class_label' => 'KLASSE B',
                'effect_type' => 'gold_blade',
                'accent_hex' => '#EAB308',
            ];
        }

        return [
            'key' => 'spark_blade_c',
            'name' => 'Goldene Funkenklinge',
            'rank_class' => 'C',
            'class_label' => 'KLASSE C',
            'effect_type' => 'gold_blade',
            'accent_hex' => '#C5A059',
        ];
    }

    /**
     * Weist einem Spieler automatisch den ersten Raum mit < 12 aktiven Spielern zu (oder aktualisiert seinen bestehenden Platz).
     *
     * @param array{player_id: string, display_name: string, level: int, glb_url: string, weapon: array<string, string>} $identity
     * @param array<string, mixed> $state
     */
    private function assignOrRefreshRoom(
        array $identity,
        array $state,
        string $actorKey = 'showroom_actor'
    ): string {
        $fallbackRoomId = Cache::get($this->playerRoomCacheKey($identity['player_id']));
        $safeFallbackRoom = (is_string($fallbackRoomId) && str_starts_with($fallbackRoomId, 'louvre-room-'))
            ? $fallbackRoomId
            : 'louvre-room-1';

        return $this->withShowroomLock(
            'global_shard',
            function () use ($identity, $state, $actorKey): string {
                $publicId = $identity['player_id'];
                $now = Carbon::now()->getTimestamp();
                $nowMs = Carbon::now()->getTimestampMs();

                $existingRoomId = Cache::get($this->playerRoomCacheKey($publicId));
                $selectedRoomId = null;
                $selectedRoster = [];

                if (is_string($existingRoomId) && str_starts_with($existingRoomId, 'louvre-room-')) {
                    $roster = $this->loadPrunedRoomRoster($existingRoomId, $now);
                    if (isset($roster[$publicId]) || count($roster) < self::MAX_PLAYERS_PER_ROOM) {
                        $selectedRoomId = $existingRoomId;
                        $selectedRoster = $roster;
                    }
                }

                if ($selectedRoomId === null) {
                    for ($idx = 1; $idx <= self::MAX_SHARD_ROOMS; $idx++) {
                        $candidateRoomId = 'louvre-room-' . $idx;
                        $roster = $this->loadPrunedRoomRoster($candidateRoomId, $now);
                        if (isset($roster[$publicId]) || count($roster) < self::MAX_PLAYERS_PER_ROOM) {
                            $selectedRoomId = $candidateRoomId;
                            $selectedRoster = $roster;
                            break;
                        }
                    }
                }

                if ($selectedRoomId === null) {
                    $selectedRoomId = 'louvre-room-1';
                    $selectedRoster = $this->loadPrunedRoomRoster($selectedRoomId, $now);
                }

                $prevEntry = is_array($selectedRoster[$publicId] ?? null) ? $selectedRoster[$publicId] : [];
                $wasPosSynced = !empty($prevEntry['pos_synced']);
                $isSyncTick = !empty($state['pos_synced']);

                $prevX = LobbyInstanceService::parseFiniteFloat($prevEntry['x'] ?? 0.0, 0.0);
                $prevY = LobbyInstanceService::parseFiniteFloat($prevEntry['y'] ?? 0.0, 0.0);
                $prevZ = LobbyInstanceService::parseFiniteFloat($prevEntry['z'] ?? 8.4, 8.4);
                $prevZone = is_string($prevEntry['zone'] ?? null) ? $prevEntry['zone'] : 'gallery';

                $nextX = array_key_exists('x', $state) ? (float) $state['x'] : $prevX;
                $nextY = array_key_exists('y', $state) ? (float) $state['y'] : $prevY;
                $nextZ = array_key_exists('z', $state) ? (float) $state['z'] : $prevZ;
                $nextZone = is_string($state['zone'] ?? null) ? $state['zone'] : $prevZone;

                if ($isSyncTick && empty($state['has_explicit_x']) && !empty($prevEntry)) {
                    $nextX = $prevX;
                }
                if ($isSyncTick && empty($state['has_explicit_z']) && !empty($prevEntry)) {
                    $nextZ = $prevZ;
                }

                if ($wasPosSynced && $isSyncTick && isset($prevEntry['updated_at_ms'])) {
                    $elapsedSec = max(0.0, ($nowMs - (int) $prevEntry['updated_at_ms']) / 1000.0);

                    if (!$this->isLegitimateShowroomZoneJump($nextX, $nextZ, $nextZone, $prevZone, $prevZ)) {
                        $dx = $nextX - $prevX;
                        $dz = $nextZ - $prevZ;
                        $dist = hypot($dx, $dz);
                        $maxStep = max(self::MIN_SYNC_TELEPORT_STEP, $elapsedSec * self::MAX_SPEED_WITH_MARGIN);

                        if ($dist > $maxStep && $dist > 0.0001) {
                            if ($dist > $maxStep + 10.0) {
                                LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-sync', 'unplausible_teleport_clamped', [
                                    'requested_distance' => round($dist, 2),
                                    'allowed_max_step' => round($maxStep, 2),
                                    'elapsed_sec' => round($elapsedSec, 3),
                                ]);
                            }

                            $scale = $maxStep / $dist;
                            $nextX = round(max(-12.0, min(12.0, $prevX + ($dx * $scale))), 3);
                            $nextZ = round(max(-240.0, min(20.0, $prevZ + ($dz * $scale))), 3);
                        }
                    }
                }

                $prevWeaponSeq = (int) ($prevEntry['weapon_seq'] ?? 0);
                $requestedWeaponSeq = (int) ($state['weapon_seq'] ?? $prevWeaponSeq);
                if ($wasPosSynced && $requestedWeaponSeq > $prevWeaponSeq + 3) {
                    $requestedWeaponSeq = $prevWeaponSeq + 1;
                }
                $cleanWeaponSeq = max(0, min(1000000, $requestedWeaponSeq));

                $selectedRoster[$publicId] = [
                    'player_id' => $publicId,
                    'display_name' => $identity['display_name'],
                    'level' => $identity['level'],
                    'glb_url' => $identity['glb_url'],
                    'weapon' => $identity['weapon'],
                    'x' => round(max(-12.0, min(12.0, $nextX)), 3),
                    'y' => round(max(0.0, min(4.5, $nextY)), 3),
                    'z' => round(max(-240.0, min(20.0, $nextZ)), 3),
                    'rot_y' => (float) ($state['rot_y'] ?? ($prevEntry['rot_y'] ?? 3.1416)),
                    'speed' => round(max(0.0, min(self::MAX_SPEED, (float) ($state['speed'] ?? ($prevEntry['speed'] ?? 0.0)))), 3),
                    'zone' => $nextZone,
                    'jumping' => (bool) ($state['jumping'] ?? ($prevEntry['jumping'] ?? false)),
                    'weapon_seq' => $cleanWeaponSeq,
                    'emote' => $state['emote'] ?? null,
                    'solo_mode' => (bool) ($state['solo_mode'] ?? false),
                    'pos_synced' => $wasPosSynced || $isSyncTick,
                    'updated_at_ms' => $nowMs,
                    'expires_at' => $now + self::PLAYER_TTL_SECONDS,
                ];

                Cache::put($this->roomRosterCacheKey($selectedRoomId), $selectedRoster, self::PLAYER_TTL_SECONDS + 40);
                Cache::put($this->playerRoomCacheKey($publicId), $selectedRoomId, self::PLAYER_TTL_SECONDS);

                return $selectedRoomId;
            },
            static fn (): string => $safeFallbackRoom
        );
    }

    private function isLegitimateShowroomZoneJump(
        float $targetX,
        float $targetZ,
        string $targetZone,
        string $prevZone,
        float $prevZ
    ): bool {
        if ($targetZone === 'gallery') {
            if ($prevZone !== 'gallery' && abs($targetX) <= 12.0 && $targetZ >= -12.0 && $targetZ <= 14.5) {
                return true;
            }
            if (abs($targetX) <= 6.5 && $targetZ >= 2.0 && $targetZ <= 12.5) {
                return true;
            }
        }

        if ($targetZone === 'heroes' && ($prevZone !== 'heroes' || $prevZ > -18.0) && abs($targetX) <= 10.0 && $targetZ <= -18.0) {
            return true;
        }

        return false;
    }

    /**
     * @param array{player_id: string, display_name: string, level: int, glb_url: string, weapon: array<string, string>} $identity
     */
    private function resolveExistingOrAssignRoom(array $identity, string $actorKey = 'showroom_actor'): string
    {
        $existing = Cache::get($this->playerRoomCacheKey($identity['player_id']));
        if (is_string($existing) && str_starts_with($existing, 'louvre-room-')) {
            return $existing;
        }

        return $this->assignOrRefreshRoom($identity, [], $actorKey);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getActiveRoomPeers(string $roomId, string $excludePlayerId): array
    {
        $roster = $this->loadPrunedRoomRoster($roomId, Carbon::now()->getTimestamp());
        $peers = [];

        foreach ($roster as $pid => $entry) {
            if ($pid === $excludePlayerId || !empty($entry['solo_mode'])) {
                continue;
            }
            $peers[] = [
                'player_id' => $entry['player_id'],
                'display_name' => $entry['display_name'],
                'level' => (int) $entry['level'],
                'glb_url' => $entry['glb_url'],
                'weapon' => $entry['weapon'],
                'x' => (float) $entry['x'],
                'y' => (float) $entry['y'],
                'z' => (float) $entry['z'],
                'rot_y' => (float) $entry['rot_y'],
                'speed' => (float) ($entry['speed'] ?? 0.0),
                'jumping' => (bool) $entry['jumping'],
                'weapon_seq' => (int) $entry['weapon_seq'],
                'emote' => in_array(($entry['emote'] ?? null), self::ALLOWED_EMOTES, true) ? $entry['emote'] : null,
                'updated_at_ms' => (int) $entry['updated_at_ms'],
            ];
        }

        return $peers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoomMessages(string $roomId): array
    {
        $raw = Cache::get($this->roomChatCacheKey($roomId), []);
        if (!is_array($raw)) {
            return [];
        }

        $nowMs = Carbon::now()->getTimestampMs();
        $valid = [];
        foreach ($raw as $msg) {
            if (is_array($msg) && (int) ($msg['expires_at_ms'] ?? 0) > $nowMs) {
                $cleanText = str_replace(['<', '>'], '', strip_tags((string) ($msg['text'] ?? '')));
                if ($cleanText === '') {
                    continue;
                }
                $valid[] = [
                    'id' => (string) ($msg['id'] ?? ''),
                    'player_id' => (string) ($msg['player_id'] ?? ''),
                    'display_name' => (string) ($msg['display_name'] ?? 'Funki'),
                    'level' => (int) ($msg['level'] ?? 1),
                    'rank_class' => (string) ($msg['rank_class'] ?? 'C'),
                    'class_label' => (string) ($msg['class_label'] ?? 'KLASSE C'),
                    'text' => $cleanText,
                    'created_at_ms' => (int) ($msg['created_at_ms'] ?? $nowMs),
                ];
            }
        }

        return $valid;
    }

    /**
     * Whitelist- & Rate-Limit-Pruefung fuer Showroom-Emotes (0 HTML/Script, kein Konsolen-Flooding).
     */
    private function sanitizeAndThrottleEmote(string $publicPlayerId, mixed $rawEmote, string $actorKey): ?string
    {
        if ($rawEmote === null || $rawEmote === '') {
            return null;
        }

        if (!is_string($rawEmote)) {
            LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-sync', 'invalid_emote_type');

            return null;
        }

        if ($this->containsHtmlOrScriptPayload($rawEmote)) {
            LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-sync', 'xss_emote_attempt');

            return null;
        }

        $normalized = strtolower(trim($rawEmote));
        if ($normalized === '') {
            return null;
        }

        if (!in_array($normalized, self::ALLOWED_EMOTES, true)) {
            LobbyInstanceService::logTamperThrottled($actorKey, 'shop/showroom-sync', 'unwhitelisted_emote', [
                'emote' => substr($normalized, 0, 24),
            ]);

            return null;
        }

        $nowMs = Carbon::now()->getTimestampMs();
        $cdKey = 'sf_showroom_emote_cd_' . $publicPlayerId;
        $untilMs = (int) Cache::get($cdKey, 0);
        if ($untilMs > $nowMs) {
            return null;
        }

        Cache::put($cdKey, $nowMs + self::EMOTE_COOLDOWN_MS, 5);

        return $normalized;
    }

    /**
     * Schuetzt den Chat-Endpunkt gegen Konsolen-Schleifen (for(let i=0;i<100;i++) fetch(...)).
     */
    private function isChatFloodRateLimited(string $customerId): bool
    {
        if (trim($customerId) === '') {
            return true;
        }

        $now = Carbon::now()->getTimestamp();
        $key = 'sf_showroom_chat_burst_' . md5($customerId);
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

    /**
     * Erkennt jegliche HTML-Tags, Script-Blöcke, Event-Handler oder gefährliche URI-Schemata vor strip_tags().
     */
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
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param (Closure(): TReturn)|null $onTimeout
     * @return TReturn
     */
    private function withShowroomLock(string $scope, Closure $callback, ?Closure $onTimeout = null): mixed
    {
        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            try {
                return Cache::lock('sf_showroom_mutex_' . $scope, 4)->block(2, $callback);
            } catch (LockTimeoutException) {
                if ($onTimeout !== null) {
                    return $onTimeout();
                }

                return null;
            }
        }

        return $callback();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadPrunedRoomRoster(string $roomId, int $now): array
    {
        $raw = Cache::get($this->roomRosterCacheKey($roomId), []);
        if (!is_array($raw)) {
            return [];
        }

        $active = [];
        $dirty = false;
        foreach ($raw as $pid => $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < $now) {
                $dirty = true;
                continue;
            }
            $active[(string) $pid] = $entry;
        }

        if ($dirty) {
            Cache::put($this->roomRosterCacheKey($roomId), $active, self::PLAYER_TTL_SECONDS + 40);
        }

        return $active;
    }

    private function defaultClassForLevel(int $level): string
    {
        if ($level >= 9) {
            return 'S';
        }
        if ($level >= 6) {
            return 'A';
        }
        if ($level >= 3) {
            return 'B';
        }

        return 'C';
    }

    private function defaultHexForEffect(string $effectType): string
    {
        return match ($effectType) {
            'sapphire_staff' => '#38BDF8',
            'emerald_bow' => '#10B981',
            'cosmos_blade' => '#FBBF24',
            default => '#C5A059',
        };
    }

    private function roomRosterCacheKey(string $roomId): string
    {
        return 'sf_showroom_roster_' . $roomId;
    }

    private function playerRoomCacheKey(string $publicPlayerId): string
    {
        return 'sf_showroom_proom_' . $publicPlayerId;
    }

    private function roomChatCacheKey(string $roomId): string
    {
        return 'sf_showroom_chat_' . $roomId;
    }
}
