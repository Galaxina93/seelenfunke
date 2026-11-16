<?php

namespace App\Services\Gamification;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LobbyInstanceService
{
    public const SOFT_CAP = 16;
    public const HARD_CAP = 20;
    public const HEARTBEAT_TTL_SECONDS = 25;
    public const MAX_INSTANCES = 50;

    private const CACHE_ACTIVE_IDS_KEY = 'sf_fw_lobby_active_instances_v1';
    private const CACHE_INSTANCE_PREFIX = 'sf_fw_lobby_inst_v1_';
    private const CACHE_PLAYER_PTR_PREFIX = 'sf_fw_lobby_pptr_v1_';
    private const CACHE_LOCK_KEY = 'sf_fw_lobby_mutex_v1';

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
            'instance_id' => null,
            'peers' => [],
            'instances' => [],
        ];
    }

    /**
     * Bucht einen eingeloggten Spieler in eine Lobby-Instanz ein (Auto-Sharding < SOFT_CAP = 16
     * oder Wunsch-Instanz < HARD_CAP = 20).
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function join(array $actor, array $input = []): array
    {
        return $this->withLobbyLock(function () use ($actor, $input): array {
            $now = now()->getTimestamp();
            $peerId = (string) $actor['peer_id'];

            $rosters = $this->loadAndPruneActiveRosters($now);
            $currentInstanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

            $rawPreferred = $input['preferred_instance'] ?? null;
            $preferredInstance = (is_numeric($rawPreferred) && (int) $rawPreferred >= 1 && (int) $rawPreferred <= self::MAX_INSTANCES)
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

            $state = $this->sanitizeState($input, $previousEntry);
            $rosters[$selectedInstance][$peerId] = $this->buildRosterEntry($actor, $state, $now);

            $this->persistInstanceRoster($selectedInstance, $rosters[$selectedInstance], $rosters);
            $this->persistActiveIdsIndex($rosters);
            Cache::put($this->playerPointerKey($peerId), $selectedInstance, self::HEARTBEAT_TTL_SECONDS);

            return [
                'ok' => true,
                'authenticated' => true,
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
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sync(array $actor, array $input = []): array
    {
        return $this->withLobbyLock(function () use ($actor, $input): array {
            $now = now()->getTimestamp();
            $peerId = (string) $actor['peer_id'];

            $rosters = $this->loadAndPruneActiveRosters($now);
            $instanceId = $this->findPlayerCurrentInstance($peerId, $rosters, $now);

            if ($instanceId === null) {
                $instanceId = $this->findAutoShardInstance($rosters, $peerId, $now);
                if (!isset($rosters[$instanceId])) {
                    $rosters[$instanceId] = $this->loadPrunedInstanceRoster($instanceId, $now);
                }
            }

            $previousEntry = $rosters[$instanceId][$peerId] ?? null;
            $state = $this->sanitizeState($input, $previousEntry);
            $rosters[$instanceId][$peerId] = $this->buildRosterEntry($actor, $state, $now);

            $this->persistInstanceRoster($instanceId, $rosters[$instanceId], $rosters);
            $this->persistActiveIdsIndex($rosters);
            Cache::put($this->playerPointerKey($peerId), $instanceId, self::HEARTBEAT_TTL_SECONDS);

            return [
                'ok' => true,
                'authenticated' => true,
                'instance_id' => $instanceId,
                'peers' => $this->formatPeersList($rosters[$instanceId], $peerId),
                'instances' => $this->buildInstancesOverview($rosters, $instanceId),
            ];
        });
    }

    /**
     * Wechselt manuell in eine Ziel-Instanz (1..50), sofern dort < HARD_CAP (20) andere Spieler aktiv sind.
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function switchInstance(array $actor, int $targetInstance, array $input = []): array
    {
        return $this->withLobbyLock(function () use ($actor, $targetInstance, $input): array {
            $now = now()->getTimestamp();
            $peerId = (string) $actor['peer_id'];

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

            $state = $this->sanitizeState($input, $previousEntry);
            $targetRoster[$peerId] = $this->buildRosterEntry($actor, $state, $now);
            $rosters[$targetInstance] = $targetRoster;

            $this->persistInstanceRoster($targetInstance, $targetRoster, $rosters);
            $this->persistActiveIdsIndex($rosters);
            Cache::put($this->playerPointerKey($peerId), $targetInstance, self::HEARTBEAT_TTL_SECONDS);

            return [
                'ok' => true,
                'status' => 200,
                'authenticated' => true,
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
        $this->withLobbyLock(function () use ($actor): void {
            $now = now()->getTimestamp();
            $peerId = (string) $actor['peer_id'];

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
     * @return array{x: float, z: float, rot_y: float, speed: float, zone: string}
     */
    private function sanitizeState(array $input, ?array $previous = null): array
    {
        $rawX = array_key_exists('x', $input) && is_numeric($input['x'])
            ? (float) $input['x']
            : (float) ($previous['x'] ?? 0.0);

        $rawZ = array_key_exists('z', $input) && is_numeric($input['z'])
            ? (float) $input['z']
            : (float) ($previous['z'] ?? 0.0);

        $rawRot = (array_key_exists('rot_y', $input) && is_numeric($input['rot_y']))
            ? (float) $input['rot_y']
            : ((array_key_exists('rotY', $input) && is_numeric($input['rotY']))
                ? (float) $input['rotY']
                : (float) ($previous['rot_y'] ?? 0.0));

        $rawSpeed = array_key_exists('speed', $input) && is_numeric($input['speed'])
            ? (float) $input['speed']
            : (float) ($previous['speed'] ?? 0.0);

        $rawZone = array_key_exists('zone', $input) && is_string($input['zone'])
            ? trim($input['zone'])
            : (string) ($previous['zone'] ?? 'gallery');

        $zone = in_array($rawZone, ['gallery', 'heroes'], true) ? $rawZone : 'gallery';

        return [
            'x' => round(max(-45.0, min(45.0, $rawX)), 3),
            'z' => round(max(-110.0, min(45.0, $rawZ)), 3),
            'rot_y' => round(max(-6.3, min(6.3, $rawRot)), 4),
            'speed' => round(max(0.0, min(10.0, $rawSpeed)), 3),
            'zone' => $zone,
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array{x: float, z: float, rot_y: float, speed: float, zone: string} $state
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
            'z' => $state['z'],
            'rot_y' => $state['rot_y'],
            'speed' => $state['speed'],
            'zone' => $state['zone'],
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

            $peers[] = [
                'peer_id' => (string) $entry['peer_id'],
                'display_name' => (string) $entry['display_name'],
                'level' => (int) $entry['level'],
                'ship_class' => (string) ($entry['ship_class'] ?? 'KLASSE C'),
                'x' => (float) $entry['x'],
                'z' => (float) $entry['z'],
                'rot_y' => (float) $entry['rot_y'],
                'speed' => (float) $entry['speed'],
                'zone' => (string) $entry['zone'],
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
            if ((string) $pid !== $excludePeerId && is_array($entry)) {
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
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) <= $now) {
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
