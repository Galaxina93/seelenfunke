<?php

namespace App\Services\Gamification;

use App\Http\Controllers\Api\FunkenwerkHeroesController;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LavaParkourService
{
    public const MAX_MAGMA_LEVEL = 10;
    public const MASTER_KEY_MIN_LEVEL = 6;
    public const MASTER_KEY_MIN_GIFT_VOUCHER_CENTS = 1500;

    public const MAX_HEARTS = 6;
    public const MAX_SHIELD_USES_PER_RUN = 3;
    public const SHIELD_COOLDOWN_MS = 13500;
    public const HIT_DEBOUNCE_MS = 1000;
    public const SAFE_BALCONY_MAX_REL_X = 3.2;

    public const DAILY_REWARD = 5;
    public const DAILY_CAP_FREE = 15;
    public const DAILY_CAP_MASTER = 30;

    public const TIMEZONE = 'Europe/Berlin';
    public const RUN_TTL_MINUTES = 15;
    public const MAX_RUN_DURATION_MS = 900000;

    public const MIN_SPLIT_1_MS = 1600;
    public const MIN_SPLIT_2_MS = 2000;
    public const MIN_SPLIT_CLAIM_MS = 1500;
    public const MIN_TOTAL_RUN_MS = 5600;

    public const MIN_CP1_ELAPSED_MS = self::MIN_SPLIT_1_MS;
    public const MIN_CP2_ELAPSED_MS = self::MIN_SPLIT_2_MS;
    public const MIN_CLAIM_AFTER_CP2_MS = self::MIN_SPLIT_CLAIM_MS;

    public const ORIGIN_X = 16.5;
    public const ORIGIN_Y = 0.004;
    public const ORIGIN_Z = -2.0;

    public const ROOM1_ORIGIN_X = self::ORIGIN_X;
    public const ROOM1_ORIGIN_Z = self::ORIGIN_Z;

    public const REL_CP1_X = 18.5;
    public const REL_CP1_Y = 0.85;
    public const REL_CP1_Z = 7.5;

    public const REL_CP2_X = 37.5;
    public const REL_CP2_Y = 1.95;
    public const REL_CP2_Z = -8.3;

    public const REL_CHEST_X = 55.5;
    public const REL_CHEST_Y = 3.2;
    public const REL_CHEST_Z = 3.5;

    public const MIN_REL_Y = -1.0;
    public const MAX_REL_Y = 16.5;

    public const CP1_X = 35.0;
    public const CP1_Z = 5.5;
    public const CP1_MAX_DIST = 6.5;
    public const CP1_MIN_Y = self::MIN_REL_Y;
    public const CP1_MAX_Y = self::MAX_REL_Y;

    public const CP2_X = 54.0;
    public const CP2_Z = -10.3;
    public const CP2_MAX_DIST = 6.5;
    public const CP2_MIN_Y = self::MIN_REL_Y;
    public const CP2_MAX_Y = self::MAX_REL_Y;

    public const CHEST_X = 72.0;
    public const CHEST_Y = 3.204;
    public const CHEST_Z = 1.5;
    public const CHEST_MAX_DIST = 6.0;
    public const CHEST_MIN_Y = self::MIN_REL_Y;
    public const CHEST_MAX_Y = self::MAX_REL_Y;

    /**
     * @var array<int, string>
     */
    public const LEVEL_TITLES = [
        1  => 'Funkensteg',
        2  => 'Glutpendel',
        3  => 'Bröckelnde Essen',
        4  => 'Geysir-Schlucht',
        5  => 'Wächter-Prüfung',
        6  => 'Tiefenschmiede-Tor',
        7  => 'Obsidian-Ambosse',
        8  => 'Runen-Kaskade',
        9  => 'Flammen-Krone',
        10 => 'Thron des Seelenfunkens',
    ];

    /**
     * Einmalige Erstabschluss-Belohnung je Raum (Summe Lvl 1..5 = 50, Lvl 6..10 = 160).
     *
     * @var array<int, int>
     */
    public const FIRST_CLEAR_REWARDS = [
        1  => 5,
        2  => 8,
        3  => 10,
        4  => 12,
        5  => 15,
        6  => 20,
        7  => 25,
        8  => 30,
        9  => 35,
        10 => 50,
    ];

    /**
     * Taegliche Wiederholungs-Truhe je Raum (1x pro Raum & Kalendertag, gedeckelt auf 15 bzw. 30 Funken/Tag).
     *
     * @var array<int, int>
     */
    public const DAILY_REWARDS = [
        1  => 2,
        2  => 2,
        3  => 3,
        4  => 4,
        5  => 4,
        6  => 4,
        7  => 5,
        8  => 5,
        9  => 5,
        10 => 6,
    ];

    public function __construct(
        private readonly GamificationService $gamificationService
    ) {
    }

    public static function minTotalRunMs(int $level): int
    {
        $clamped = max(1, min(self::MAX_MAGMA_LEVEL, $level));

        return 5600 + ($clamped - 1) * 300;
    }

    /**
     * Prueft, ob der Kunde den Manufaktur-Meisterschluessel (Zugang zu Magma-Level 6 bis 10) besitzt:
     * Mindestens 1 bezahlte, nicht stornierte und nicht widerrufene Bestellung im Shop
     * (regulaeres Manufaktur-Produkt oder Seelenfunke-Geschenkgutschein ab 15,00 EUR / 1500 Cent).
     */
    public function hasMasterKey(Customer $customer): bool
    {
        if (!Schema::hasTable('order_orders')) {
            return false;
        }

        $orders = OrderOrder::with(['items.product'])
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled', 'refunded', 'revoked'])
            ->whereNotIn('payment_status', ['refunded', 'cancelled', 'revoked', 'failed'])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                    ->orWhereIn('status', ['completed', 'shipped', 'delivered', 'processing', 'paid']);
            })
            ->get();

        if ($orders->isEmpty()) {
            return false;
        }

        $hasRevocationsTable = Schema::hasTable('order_revocations');

        foreach ($orders as $order) {
            if ($hasRevocationsTable && $this->isOrderRevoked($order)) {
                continue;
            }

            if ($order->items->isNotEmpty()) {
                $hasRegularProduct = false;
                $voucherCents = 0;

                foreach ($order->items as $item) {
                    $cfg = is_array($item->configuration) ? $item->configuration : [];
                    $slug = strtolower(trim((string) ($item->product?->slug ?? '')));
                    $type = strtolower(trim((string) ($item->product?->type ?? '')));
                    $productName = mb_strtolower(trim((string) ($item->product_name ?: ($item->product?->name ?? ''))), 'UTF-8');

                    if ($slug === 'seelenfunke-ingame-artifact') {
                        continue;
                    }

                    $isGiftVoucher = $slug === 'geschenkgutschein'
                        || $type === 'geschenkgutschein'
                        || !empty($cfg['is_gift_voucher'])
                        || str_contains($productName, 'geschenkgutschein');

                    $lineCents = (int) ($item->total_price ?? ((int) $item->unit_price * max(1, (int) $item->quantity)));

                    if ($isGiftVoucher) {
                        $voucherCents += max(0, $lineCents);
                    } elseif ($lineCents > 0 || (int) ($order->total_price ?? 0) > 0) {
                        $hasRegularProduct = true;
                    }
                }

                if ($hasRegularProduct || $voucherCents >= self::MASTER_KEY_MIN_GIFT_VOUCHER_CENTS) {
                    return true;
                }

                continue;
            }

            $orderCents = max((int) ($order->total_price ?? 0), (int) ($order->subtotal_price ?? 0));
            if ($orderCents >= self::MASTER_KEY_MIN_GIFT_VOUCHER_CENTS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert die statischen Definitionen aller 10 Magma-Level (1..10) inkl. deterministischer S-Kurven-Anker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLevelsCatalog(): array
    {
        $levels = [];

        for ($lvl = 1; $lvl <= self::MAX_MAGMA_LEVEL; $lvl++) {
            $levels[$lvl] = $this->getLevelDefinition($lvl);
        }

        return $levels;
    }

    /**
     * Deterministische S-Kurven-Ankerkoordinaten fuer Raeume 1..10 (auf 0.01 m identisch mit dem 3D-Inselgenerator).
     *
     * @return array<string, mixed>
     */
    public function getLevelDefinition(int $level): array
    {
        $clamped = max(1, min(self::MAX_MAGMA_LEVEL, $level));
        $dir = ($clamped % 2 === 1) ? 1.0 : -1.0;
        $ampZ = round(7.5 + ($clamped - 1) * 0.55, 2);

        $relCp1 = [
            'x' => round(18.5 + ($clamped - 1) * 0.60, 2),
            'y' => round(0.85 + ($clamped - 1) * 0.14, 2),
            'z' => round($dir * $ampZ, 2),
        ];

        $relCp2 = [
            'x' => round(37.5 + ($clamped - 1) * 1.15, 2),
            'y' => round(1.95 + ($clamped - 1) * 0.22, 2),
            'z' => round(-$dir * ($ampZ + 0.80), 2),
        ];

        $relChest = [
            'x' => round(55.5 + ($clamped - 1) * 1.65, 2),
            'y' => round(3.20 + ($clamped - 1) * 0.28, 2),
            'z' => round($dir * (2.40 + ($clamped % 3) * 1.10), 2),
        ];

        return [
            'level' => $clamped,
            'title' => self::LEVEL_TITLES[$clamped],
            'requires_master_key' => $clamped >= self::MASTER_KEY_MIN_LEVEL,
            'first_clear_reward' => self::FIRST_CLEAR_REWARDS[$clamped],
            'daily_reward' => self::DAILY_REWARDS[$clamped],
            'min_total_run_ms' => self::minTotalRunMs($clamped),
            'origin' => [
                'x' => self::ORIGIN_X,
                'y' => self::ORIGIN_Y,
                'z' => self::ORIGIN_Z,
            ],
            'rel_cp1' => $relCp1,
            'rel_cp2' => $relCp2,
            'rel_chest' => $relChest,
        ];
    }

    public function hasClaimedToday(Customer $customer, ?CustomerGamification $profile = null, int $level = 1): bool
    {
        $state = $this->resolveProgressState($customer, $profile);

        return in_array($level, $state['daily_cleared_levels'], true);
    }

    /**
     * @return array{
     *     master_key_unlocked: bool,
     *     highest_unlocked_level: int,
     *     raw_progress_unlocked_level: int,
     *     hearts: int,
     *     max_hearts: int,
     *     unlocked_portals: array<int, int>,
     *     first_cleared_levels: array<int, int>,
     *     daily_cleared_levels: array<int, int>,
     *     daily_claimed_levels: array<int, int>,
     *     daily_sparks_earned: int,
     *     daily_sparks_cap: int
     * }
     */
    public function resolveProgressState(?Customer $customer, ?CustomerGamification $profile = null): array
    {
        if ($customer === null) {
            return [
                'master_key_unlocked' => false,
                'highest_unlocked_level' => 1,
                'raw_progress_unlocked_level' => 1,
                'hearts' => self::MAX_HEARTS,
                'max_hearts' => self::MAX_HEARTS,
                'unlocked_portals' => [],
                'first_cleared_levels' => [],
                'daily_cleared_levels' => [],
                'daily_claimed_levels' => [],
                'daily_sparks_earned' => 0,
                'daily_sparks_cap' => self::DAILY_CAP_FREE,
            ];
        }

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $masterKeyUnlocked = $this->hasMasterKey($customer);
        $dailyCap = $masterKeyUnlocked ? self::DAILY_CAP_MASTER : self::DAILY_CAP_FREE;

        $gamification = $profile;
        if ($gamification === null && Schema::hasTable('customer_gamifications')) {
            $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        }

        $titlesProgress = ($gamification !== null && is_array($gamification->titles_progress))
            ? $gamification->titles_progress
            : [];

        $clearedLevels = $this->normalizeLevelList($titlesProgress['lava_cleared_levels'] ?? []);

        $dailyClearedLevels = [];
        $dailySparksEarned = 0;

        $rawDaily = $titlesProgress['lava_daily_claims'] ?? null;
        if (is_array($rawDaily) && ($rawDaily['date'] ?? '') === $today) {
            $dailyClearedLevels = $this->normalizeLevelList($rawDaily['levels'] ?? []);
            $dailySparksEarned = max(0, min($dailyCap, (int) ($rawDaily['sparks'] ?? 0)));
        } elseif (($titlesProgress['_lava_chest_last_claim_date'] ?? null) === $today) {
            $dailyClearedLevels = [1];
        }

        $maxSequential = $this->computeMaxSequentialCleared($clearedLevels);
        $permanentFloorLevel = min(self::MAX_MAGMA_LEVEL, $maxSequential + 1);

        $rawProgressUnlocked = $permanentFloorLevel;
        $hearts = self::MAX_HEARTS;

        if ($gamification !== null) {
            $attrs = $gamification->getAttributes();
            if (array_key_exists('magma_unlocked_level', $attrs) && $attrs['magma_unlocked_level'] !== null) {
                $storedUnlocked = max(1, min(self::MAX_MAGMA_LEVEL, (int) $attrs['magma_unlocked_level']));
                $rawProgressUnlocked = max($permanentFloorLevel, $storedUnlocked);

                if ($storedUnlocked < $permanentFloorLevel && Schema::hasTable('customer_gamifications')) {
                    $gamification->magma_unlocked_level = $rawProgressUnlocked;
                    CustomerGamification::where('id', $gamification->id)
                        ->update(['magma_unlocked_level' => $rawProgressUnlocked]);
                }
            }
            if (array_key_exists('magma_hearts', $attrs) && $attrs['magma_hearts'] !== null && (int) $attrs['magma_hearts'] > 0) {
                $hearts = max(1, min(self::MAX_HEARTS, (int) $attrs['magma_hearts']));
            }
        }

        $highestUnlockedLevel = $masterKeyUnlocked
            ? $rawProgressUnlocked
            : min(5, $rawProgressUnlocked);

        $unlockedPortals = $highestUnlockedLevel >= 2
            ? range(2, $highestUnlockedLevel)
            : [];

        return [
            'master_key_unlocked' => $masterKeyUnlocked,
            'highest_unlocked_level' => $highestUnlockedLevel,
            'raw_progress_unlocked_level' => $rawProgressUnlocked,
            'hearts' => $hearts,
            'max_hearts' => self::MAX_HEARTS,
            'unlocked_portals' => $unlockedPortals,
            'first_cleared_levels' => $clearedLevels,
            'daily_cleared_levels' => $dailyClearedLevels,
            'daily_claimed_levels' => $dailyClearedLevels,
            'daily_sparks_earned' => $dailySparksEarned,
            'daily_sparks_cap' => $dailyCap,
        ];
    }

    /**
     * Prueft die Voraussetzungen fuer das gesamte Magma-Level (Stufen 1 bis 10, Portal-Hub, Checkpoints & Truhe).
     *
     * @return array{status: int, body: array<string, mixed>}|null
     */
    public function checkSanctumAccess(?Customer $customer): ?array
    {
        $access = LobbyInstanceService::evaluateSanctumAccess($customer);
        if ($access['can_enter_sanctums']) {
            return null;
        }

        return [
            'status' => 403,
            'body' => [
                'success' => false,
                'guest' => !$access['is_authenticated'],
                'code' => $access['sanctum_block_reason'],
                'sanctum_block_reason' => $access['sanctum_block_reason'],
                'has_completed_order' => $access['has_completed_order'],
                'gamification_active' => $access['gamification_active'],
                'can_enter_sanctums' => false,
                'login_url' => $access['login_url'],
                'dashboard_url' => $access['dashboard_url'],
                'message' => (string) $access['message'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildShowroomPayload(?Customer $customer): array
    {
        $access = LobbyInstanceService::evaluateSanctumAccess($customer);
        $gamification = null;
        if ($customer !== null && Schema::hasTable('customer_gamifications')) {
            $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        }
        $state = $this->resolveProgressState($customer, $gamification);
        $inventory = $customer !== null
            ? $this->getInventoryPayload($customer, $gamification)
            : $this->getDefaultGuestInventoryPayload();
        $levelsList = [];

        foreach ($this->getLevelsCatalog() as $lvlNum => $levelDef) {
            $isClaimedToday = in_array((int) $lvlNum, $state['daily_cleared_levels'], true);
            $isFirstCleared = in_array((int) $lvlNum, $state['first_cleared_levels'], true);
            $levelDef['claimed_today'] = $isClaimedToday;
            $levelDef['chest_locked_today'] = $isClaimedToday;
            $levelDef['first_cleared'] = $isFirstCleared;
            $levelDef['unlocked'] = (int) $lvlNum <= $state['highest_unlocked_level'];
            $levelsList[] = $levelDef;
        }

        $lvl1Def = $this->getLevelDefinition(1);

        return [
            'enabled' => true,
            'daily_reward' => self::FIRST_CLEAR_REWARDS[1],
            'is_authenticated' => $access['is_authenticated'],
            'has_completed_order' => $access['has_completed_order'],
            'gamification_active' => $access['gamification_active'],
            'can_enter_sanctums' => $access['can_enter_sanctums'],
            'sanctum_block_reason' => $access['sanctum_block_reason'],
            'claimed_today' => in_array(1, $state['daily_cleared_levels'], true),
            'master_key_unlocked' => $state['master_key_unlocked'],
            'highest_unlocked_level' => $state['highest_unlocked_level'],
            'unlocked_level' => $state['highest_unlocked_level'],
            'hearts' => $state['hearts'],
            'max_hearts' => self::MAX_HEARTS,
            'potion_count' => $inventory['potion_count'],
            'potion_price_sparks' => $inventory['potion_price_sparks'],
            'potion_heal_hearts' => $inventory['potion_heal_hearts'],
            'bag_slots' => $inventory['bag_slots'],
            'quick_slots' => $inventory['quick_slots'],
            'unlocked_portals' => $state['unlocked_portals'],
            'first_cleared_levels' => $state['first_cleared_levels'],
            'daily_cleared_levels' => $state['daily_cleared_levels'],
            'daily_claimed_levels' => $state['daily_cleared_levels'],
            'daily_sparks_earned' => $state['daily_sparks_earned'],
            'daily_sparks_cap' => $state['daily_sparks_cap'],
            'origin' => [
                'x' => self::ORIGIN_X,
                'y' => self::ORIGIN_Y,
                'z' => self::ORIGIN_Z,
            ],
            'levels' => $levelsList,
            'checkpoints' => [
                [
                    'stage' => 1,
                    'x' => round(self::ORIGIN_X + $lvl1Def['rel_cp1']['x'], 2),
                    'y' => round(self::ORIGIN_Y + $lvl1Def['rel_cp1']['y'], 3),
                    'z' => round(self::ORIGIN_Z + $lvl1Def['rel_cp1']['z'], 2),
                    'rel_x' => $lvl1Def['rel_cp1']['x'],
                    'rel_y' => $lvl1Def['rel_cp1']['y'],
                    'rel_z' => $lvl1Def['rel_cp1']['z'],
                ],
                [
                    'stage' => 2,
                    'x' => round(self::ORIGIN_X + $lvl1Def['rel_cp2']['x'], 2),
                    'y' => round(self::ORIGIN_Y + $lvl1Def['rel_cp2']['y'], 3),
                    'z' => round(self::ORIGIN_Z + $lvl1Def['rel_cp2']['z'], 2),
                    'rel_x' => $lvl1Def['rel_cp2']['x'],
                    'rel_y' => $lvl1Def['rel_cp2']['y'],
                    'rel_z' => $lvl1Def['rel_cp2']['z'],
                ],
            ],
            'chest_pos' => [
                'x' => round(self::ORIGIN_X + $lvl1Def['rel_chest']['x'], 2),
                'y' => round(self::ORIGIN_Y + $lvl1Def['rel_chest']['y'], 3),
                'z' => round(self::ORIGIN_Z + $lvl1Def['rel_chest']['z'], 2),
                'rel_x' => $lvl1Def['rel_chest']['x'],
                'rel_y' => $lvl1Def['rel_chest']['y'],
                'rel_z' => $lvl1Def['rel_chest']['z'],
            ],
        ];
    }

    /**
     * Startet einen Magma-Lauf (Pessimistic Run-Escrow: zieht bei abgebrochenem committed-Lauf 1 Herz ab).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function startRun(Customer $customer, int $requestedLevel = 1): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        if ($requestedLevel < 1 || $requestedLevel > self::MAX_MAGMA_LEVEL) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'INVALID_LEVEL',
                    'message' => 'Ungültige Magma-Stufe.',
                ],
            ];
        }

        $this->gamificationService->getProfile($customer);
        $customerId = (string) $customer->id;

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $requestedLevel): array {
            return DB::transaction(function () use ($customer, $customerId, $requestedLevel): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();

                $masterKeyUnlocked = $this->hasMasterKey($customer);
                $maxCap = $masterKeyUnlocked ? self::MAX_MAGMA_LEVEL : 5;

                $titlesProgress = is_array($gam->titles_progress) ? $gam->titles_progress : [];
                $clearedLevels = $this->normalizeLevelList($titlesProgress['lava_cleared_levels'] ?? []);
                $maxSequentialCleared = $this->computeMaxSequentialCleared($clearedLevels);
                $permanentFloorLevel = min(self::MAX_MAGMA_LEVEL, $maxSequentialCleared + 1);

                $storedUnlocked = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($gam->magma_unlocked_level ?: 1)));
                $rawUnlocked = max($permanentFloorLevel, $storedUnlocked);
                $hearts = max(1, min(self::MAX_HEARTS, (int) ($gam->magma_hearts ?: self::MAX_HEARTS)));

                $escrowPenaltyApplied = false;
                $escrowDemoted = false;

                // F5 / Tab-Close / Request-Block Schutz: Falls ein bereits gestarteter Lauf den Startbalkon verlassen hatte
                if (!empty($gam->magma_active_run_id) && (bool) $gam->magma_run_committed === true) {
                    $escrowPenaltyApplied = true;
                    $hearts--;

                    if ($hearts <= 0) {
                        $beforeDemote = $rawUnlocked;
                        $demotedLevel = max(1, $rawUnlocked - 1);
                        $rawUnlocked = max($permanentFloorLevel, $demotedLevel);
                        $hearts = self::MAX_HEARTS;
                        $escrowDemoted = $rawUnlocked < $beforeDemote;
                    }
                }

                $effectiveMaxUnlocked = max(1, min($maxCap, $rawUnlocked));

                if ($escrowPenaltyApplied) {
                    $level = max(1, min($requestedLevel, $effectiveMaxUnlocked));
                } else {
                    if ($requestedLevel >= self::MASTER_KEY_MIN_LEVEL && !$masterKeyUnlocked) {
                        return [
                            'status' => 403,
                            'body' => [
                                'success' => false,
                                'code' => 'MASTER_KEY_REQUIRED',
                                'master_key_unlocked' => false,
                                'highest_unlocked_level' => $effectiveMaxUnlocked,
                                'unlocked_level' => $effectiveMaxUnlocked,
                                'hearts' => $hearts,
                                'max_hearts' => self::MAX_HEARTS,
                                'gift_voucher_url' => '/produkt/geschenkgutschein',
                                'message' => 'Für Stufe 6 bis 10 wird der Manufaktur-Meisterschlüssel benötigt (ab deiner ersten bezahlten Bestellung oder einem Geschenkgutschein ab 15,00 €).',
                            ],
                        ];
                    }

                    if ($requestedLevel > $effectiveMaxUnlocked) {
                        return [
                            'status' => 403,
                            'body' => [
                                'success' => false,
                                'code' => 'LEVEL_LOCKED',
                                'highest_unlocked_level' => $effectiveMaxUnlocked,
                                'unlocked_level' => $effectiveMaxUnlocked,
                                'hearts' => $hearts,
                                'max_hearts' => self::MAX_HEARTS,
                                'message' => 'Bitte schließe zuerst Stufe ' . ($requestedLevel - 1) . ' ab, um Stufe ' . $requestedLevel . ' freizuschalten.',
                            ],
                        ];
                    }

                    $level = $requestedLevel;
                }

                $runId = Str::random(40);
                $lifeNonce = Str::random(32);
                $secret = Str::random(48);
                $nowMs = Carbon::now()->getTimestampMs();

                $gam->magma_unlocked_level = $rawUnlocked;
                $gam->magma_hearts = $hearts;
                $gam->magma_active_run_id = $runId;
                $gam->magma_run_committed = false;
                $gam->save();

                if ($escrowDemoted) {
                    FunkenwerkHeroesController::forgetPublicCache();
                }

                $this->storeActiveLifeNonce($customerId, $lifeNonce);

                Cache::put(
                    $this->runCacheKey($customerId),
                    [
                        'customer_id' => $customerId,
                        'run_id' => $runId,
                        'life_nonce' => $lifeNonce,
                        'level' => $level,
                        'secret' => $secret,
                        'started_at_ms' => $nowMs,
                        'cp1_at_ms' => null,
                        'cp2_at_ms' => null,
                        'stage' => 0,
                        'claim_token' => null,
                        'committed' => false,
                        'shield_uses' => 0,
                        'last_shield_at_ms' => null,
                        'last_hit_at_ms' => null,
                    ],
                    Carbon::now()->addMinutes(self::RUN_TTL_MINUTES)
                );

                $state = $this->resolveProgressState($customer, $gam);
                $levelDef = $this->getLevelDefinition($level);
                $isFirstClearAvailable = !in_array($level, $state['first_cleared_levels'], true);
                $isClaimedToday = in_array($level, $state['daily_cleared_levels'], true);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'run_id' => $runId,
                        'life_nonce' => $lifeNonce,
                        'level' => $level,
                        'title' => $levelDef['title'],
                        'hearts' => $hearts,
                        'max_hearts' => self::MAX_HEARTS,
                        'unlocked_level' => $effectiveMaxUnlocked,
                        'highest_unlocked_level' => $effectiveMaxUnlocked,
                        'shield_charges_remaining' => self::MAX_SHIELD_USES_PER_RUN,
                        'escrow_penalty_applied' => $escrowPenaltyApplied,
                        'demoted' => $escrowDemoted,
                        'is_first_clear_available' => $isFirstClearAvailable,
                        'claimed_today' => $isClaimedToday,
                        'already_claimed_today' => $isClaimedToday,
                        'chest_locked_today' => $isClaimedToday,
                        'daily_claimed_levels' => $state['daily_cleared_levels'],
                        'daily_cleared_levels' => $state['daily_cleared_levels'],
                        'first_clear_reward' => $levelDef['first_clear_reward'],
                        'daily_reward' => $isFirstClearAvailable ? $levelDef['first_clear_reward'] : $levelDef['daily_reward'],
                        'repeat_daily_reward' => $levelDef['daily_reward'],
                        'master_key_unlocked' => $masterKeyUnlocked,
                        'unlocked_portals' => $state['unlocked_portals'],
                        'anchors' => [
                            'cp1' => $levelDef['rel_cp1'],
                            'cp2' => $levelDef['rel_cp2'],
                            'chest' => $levelDef['rel_chest'],
                        ],
                        'checkpoints' => [
                            'cp1' => [
                                'stage' => 1,
                                'x' => $levelDef['rel_cp1']['x'],
                                'y' => $levelDef['rel_cp1']['y'],
                                'z' => $levelDef['rel_cp1']['z'],
                                'rel_x' => $levelDef['rel_cp1']['x'],
                                'rel_y' => $levelDef['rel_cp1']['y'],
                                'rel_z' => $levelDef['rel_cp1']['z'],
                            ],
                            'cp2' => [
                                'stage' => 2,
                                'x' => $levelDef['rel_cp2']['x'],
                                'y' => $levelDef['rel_cp2']['y'],
                                'z' => $levelDef['rel_cp2']['z'],
                                'rel_x' => $levelDef['rel_cp2']['x'],
                                'rel_y' => $levelDef['rel_cp2']['y'],
                                'rel_z' => $levelDef['rel_cp2']['z'],
                            ],
                        ],
                        'chest_pos' => [
                            'x' => $levelDef['rel_chest']['x'],
                            'y' => $levelDef['rel_chest']['y'],
                            'z' => $levelDef['rel_chest']['z'],
                            'rel_x' => $levelDef['rel_chest']['x'],
                            'rel_y' => $levelDef['rel_chest']['y'],
                            'rel_z' => $levelDef['rel_chest']['z'],
                        ],
                    ],
                ];
            });
        });
    }

    /**
     * Markiert den aktiven Lauf als committed, sobald der Spieler den sicheren Startbalkon (x > 19.35) verlaesst.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function commitRun(Customer $customer, string $runId): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        $customerId = (string) $customer->id;
        if ($runId === '') {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Ungültige Lauf-Kennung.',
                ],
            ];
        }

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $runId): array {
            $cacheKey = $this->runCacheKey($customerId);
            $run = Cache::get($cacheKey);

            if (!is_array($run) || !isset($run['run_id']) || !hash_equals((string) $run['run_id'], $runId)) {
                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'Kein passender aktiver Lauf gefunden.',
                    ],
                ];
            }

            $run['committed'] = true;
            $this->refreshRunTtl($cacheKey, $run);

            return DB::transaction(function () use ($customer, $runId, $run): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
                $gam->magma_active_run_id = $runId;
                $gam->magma_run_committed = true;
                $gam->save();

                $state = $this->resolveProgressState($customer, $gam);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'status' => 'committed',
                        'run_id' => $runId,
                        'committed' => true,
                        'hearts' => $state['hearts'],
                        'max_hearts' => self::MAX_HEARTS,
                        'unlocked_level' => $state['highest_unlocked_level'],
                        'life_nonce' => (string) ($run['life_nonce'] ?? ''),
                    ],
                ];
            });
        });
    }

    /**
     * Beendet den aktiven Lauf ohne Herzverlust NUR solange magma_run_committed === false ist
     * oder der Spieler regulaer auf den Startbalkon (rel_x <= 3.2) zurueckgekehrt ist.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function leaveRun(Customer $customer, string $runId, ?float $relX = null): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        $customerId = (string) $customer->id;
        if ($relX !== null && (!is_finite($relX) || abs($relX) > LobbyInstanceService::EXTREME_COORD_THRESHOLD)) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Ungültige Positionsdaten.',
                ],
            ];
        }

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $runId, $relX): array {
            return DB::transaction(function () use ($customer, $customerId, $runId, $relX): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                $cacheKey = $this->runCacheKey($customerId);
                $run = Cache::get($cacheKey);
                $state = $this->resolveProgressState($customer, $gam);

                if ($gam === null || ($gam->magma_active_run_id === null && !is_array($run))) {
                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'status' => 'left_safely',
                            'hearts' => $state['hearts'],
                            'max_hearts' => self::MAX_HEARTS,
                            'unlocked_level' => $state['highest_unlocked_level'],
                        ],
                    ];
                }

                $activeRunId = (string) ($gam->magma_active_run_id ?: ($run['run_id'] ?? ''));
                if ($runId === '' || ($activeRunId !== '' && !hash_equals($activeRunId, $runId))) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'Ungültige Lauf-Kennung.',
                        ],
                    ];
                }

                $isCommitted = (bool) $gam->magma_run_committed || !empty($run['committed']);
                $nowMs = Carbon::now()->getTimestampMs();

                $returnedSafelyToBalcony = false;
                if ($relX !== null && $relX >= -8.0 && $relX <= self::SAFE_BALCONY_MAX_REL_X) {
                    $cp2AtMs = is_array($run) && is_int($run['cp2_at_ms'] ?? null) ? (int) $run['cp2_at_ms'] : null;
                    $cp1AtMs = is_array($run) && is_int($run['cp1_at_ms'] ?? null) ? (int) $run['cp1_at_ms'] : null;

                    // Schutz gegen Instant-Teleport-Spoof auf den Balkon unmittelbar nach einem Checkpoint
                    $plausibleReturn = ($cp2AtMs === null || ($nowMs - $cp2AtMs) >= 1800)
                        && ($cp1AtMs === null || ($nowMs - $cp1AtMs) >= 1000);

                    if ($plausibleReturn) {
                        $returnedSafelyToBalcony = true;
                    }
                }

                if (!$isCommitted || $returnedSafelyToBalcony) {
                    $gam->magma_active_run_id = null;
                    $gam->magma_run_committed = false;
                    $gam->save();

                    Cache::forget($cacheKey);
                    $this->clearActiveLifeNonce($customerId);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'status' => 'left_safely',
                            'hearts' => $state['hearts'],
                            'max_hearts' => self::MAX_HEARTS,
                            'unlocked_level' => $state['highest_unlocked_level'],
                        ],
                    ];
                }

                return [
                    'status' => 409,
                    'body' => [
                        'success' => false,
                        'status' => 'run_still_committed',
                        'committed' => true,
                        'hearts' => $state['hearts'],
                        'max_hearts' => self::MAX_HEARTS,
                        'unlocked_level' => $state['highest_unlocked_level'],
                        'message' => 'Kehr zuerst auf den sicheren Startbalkon zurück, um den Lauf ohne Herzverlust zu verlassen.',
                    ],
                ];
            });
        });
    }

    /**
     * Verarbeitet einen Lava-Treffer / Schild-Einsatz inkl. 1.0s Debounce, 6-Herzen-Abzug & 0/6 Level-Rueckstufung.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function recordHit(
        Customer $customer,
        string $runId,
        ?int $requestedLevel = null,
        ?string $cause = null,
        bool $usedShield = false
    ): array {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        $customerId = (string) $customer->id;
        if ($runId === '') {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Ungültige Lauf-Kennung.',
                ],
            ];
        }

        return $this->withCustomerParkourLock(
            $customerId,
            function () use ($customer, $customerId, $runId, $requestedLevel, $usedShield): array {
                $nowMs = Carbon::now()->getTimestampMs();
                $lastHitMeta = Cache::get($this->lastHitMetaKey($customerId));

                if (
                    !$usedShield
                    && is_array($lastHitMeta)
                    && isset($lastHitMeta['at_ms'], $lastHitMeta['response'])
                    && ($nowMs - (int) $lastHitMeta['at_ms']) >= 0
                    && ($nowMs - (int) $lastHitMeta['at_ms']) < self::HIT_DEBOUNCE_MS
                    && in_array($runId, array_filter([
                        (string) ($lastHitMeta['old_run_id'] ?? ''),
                        (string) ($lastHitMeta['new_run_id'] ?? ''),
                    ]), true)
                ) {
                    $debouncedBody = $lastHitMeta['response'];
                    $debouncedBody['debounced'] = true;

                    return [
                        'status' => 200,
                        'body' => $debouncedBody,
                    ];
                }

                $cacheKey = $this->runCacheKey($customerId);
                $run = Cache::get($cacheKey);

                if (!is_array($run) || !is_string($run['run_id'] ?? null)) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'Kein aktiver Lauf gefunden.',
                        ],
                    ];
                }

                if (!isset($run['customer_id']) || !hash_equals((string) $run['customer_id'], $customerId)) {
                    return $this->destroyRunResponse($customerId, 'hit_customer_mismatch');
                }

                if (!hash_equals($run['run_id'], $runId)) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'message' => 'Ungültiger Lauf.',
                        ],
                    ];
                }

                $runLevel = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($run['level'] ?? 1)));
                if ($requestedLevel !== null && $requestedLevel !== $runLevel) {
                    return $this->destroyRunResponse($customerId, 'hit_level_mismatch', [
                        'run_level' => $runLevel,
                        'requested_level' => $requestedLevel,
                    ]);
                }

                // Schild-Pruefung (>= 13.5 s Cooldown und max. 3 Einsaetze pro Run)
                if ($usedShield) {
                    $shieldUses = (int) ($run['shield_uses'] ?? 0);
                    $lastShieldAtMs = isset($run['last_shield_at_ms']) && is_int($run['last_shield_at_ms'])
                        ? (int) $run['last_shield_at_ms']
                        : null;
                    $cooldownOk = ($lastShieldAtMs === null) || (($nowMs - $lastShieldAtMs) >= self::SHIELD_COOLDOWN_MS);

                    if ($shieldUses < self::MAX_SHIELD_USES_PER_RUN && $cooldownOk) {
                        $shieldUses++;
                        $run['shield_uses'] = $shieldUses;
                        $run['last_shield_at_ms'] = $nowMs;
                        $run['committed'] = true;
                        $this->refreshRunTtl($cacheKey, $run);

                        return DB::transaction(function () use ($customer, $runId, $run, $runLevel, $shieldUses): array {
                            $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
                            $gam->magma_active_run_id = $runId;
                            $gam->magma_run_committed = true;
                            $gam->save();

                            $state = $this->resolveProgressState($customer, $gam);

                            return [
                                'status' => 200,
                                'body' => [
                                    'success' => true,
                                    'status' => 'shield_saved',
                                    'hearts' => $state['hearts'],
                                    'max_hearts' => self::MAX_HEARTS,
                                    'demoted' => false,
                                    'unlocked_level' => $state['highest_unlocked_level'],
                                    'target_level' => $runLevel,
                                    'run_id' => $runId,
                                    'life_nonce' => (string) ($run['life_nonce'] ?? ''),
                                    'shield_charges_remaining' => max(0, self::MAX_SHIELD_USES_PER_RUN - $shieldUses),
                                ],
                            ];
                        });
                    }

                    $this->logCheatAttempt($customerId, 'shield_budget_or_cooldown_forged', [
                        'shield_uses' => $shieldUses,
                        'since_last_ms' => $lastShieldAtMs !== null ? ($nowMs - $lastShieldAtMs) : null,
                    ]);
                }

                // Regulaerer Lava-Treffer (1.0s Debounce zusaetzlich direkt auf dem Run pruefen)
                $lastRunHitAtMs = isset($run['last_hit_at_ms']) && is_int($run['last_hit_at_ms'])
                    ? (int) $run['last_hit_at_ms']
                    : null;
                if ($lastRunHitAtMs !== null && ($nowMs - $lastRunHitAtMs) >= 0 && ($nowMs - $lastRunHitAtMs) < self::HIT_DEBOUNCE_MS) {
                    $state = $this->resolveProgressState($customer);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'status' => 'heart_lost',
                            'debounced' => true,
                            'hearts' => $state['hearts'],
                            'max_hearts' => self::MAX_HEARTS,
                            'demoted' => false,
                            'unlocked_level' => $state['highest_unlocked_level'],
                            'target_level' => $runLevel,
                            'run_id' => $runId,
                            'life_nonce' => (string) ($run['life_nonce'] ?? ''),
                        ],
                    ];
                }

                return DB::transaction(function () use ($customer, $customerId, $cacheKey, $run, $runId, $runLevel, $nowMs): array {
                    $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->firstOrFail();
                    $masterKeyUnlocked = $this->hasMasterKey($customer);
                    $maxCap = $masterKeyUnlocked ? self::MAX_MAGMA_LEVEL : 5;

                    $titlesProgress = is_array($gam->titles_progress) ? $gam->titles_progress : [];
                    $clearedLevels = $this->normalizeLevelList($titlesProgress['lava_cleared_levels'] ?? []);
                    $maxSequentialCleared = $this->computeMaxSequentialCleared($clearedLevels);
                    $permanentFloorLevel = min(self::MAX_MAGMA_LEVEL, $maxSequentialCleared + 1);

                    $currentHearts = max(1, min(self::MAX_HEARTS, (int) ($gam->magma_hearts ?: self::MAX_HEARTS)));
                    $nextHearts = $currentHearts - 1;
                    $storedUnlocked = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($gam->magma_unlocked_level ?: 1)));
                    $currentRawUnlocked = max($permanentFloorLevel, $storedUnlocked);

                    if ($nextHearts > 0) {
                        $newLifeNonce = Str::random(32);
                        $newSecret = Str::random(48);

                        $gam->magma_unlocked_level = $currentRawUnlocked;
                        $gam->magma_hearts = $nextHearts;
                        $gam->magma_active_run_id = $runId;
                        $gam->magma_run_committed = false;
                        $gam->save();

                        $this->storeActiveLifeNonce($customerId, $newLifeNonce);

                        $run['stage'] = 0;
                        $run['started_at_ms'] = $nowMs;
                        $run['cp1_at_ms'] = null;
                        $run['cp2_at_ms'] = null;
                        $run['claim_token'] = null;
                        $run['committed'] = false;
                        $run['life_nonce'] = $newLifeNonce;
                        $run['secret'] = $newSecret;
                        $run['last_hit_at_ms'] = $nowMs;
                        $run['hearts'] = $nextHearts;
                        $this->refreshRunTtl($cacheKey, $run);

                        $effectiveUnlocked = max(1, min($maxCap, $currentRawUnlocked));
                        $levelDef = $this->getLevelDefinition($runLevel);

                        $body = [
                            'success' => true,
                            'status' => 'heart_lost',
                            'hearts' => $nextHearts,
                            'max_hearts' => self::MAX_HEARTS,
                            'demoted' => false,
                            'unlocked_level' => $effectiveUnlocked,
                            'highest_unlocked_level' => $effectiveUnlocked,
                            'target_level' => $runLevel,
                            'run_id' => $runId,
                            'life_nonce' => $newLifeNonce,
                            'shield_charges_remaining' => max(0, self::MAX_SHIELD_USES_PER_RUN - (int) ($run['shield_uses'] ?? 0)),
                            'anchors' => [
                                'cp1' => $levelDef['rel_cp1'],
                                'cp2' => $levelDef['rel_cp2'],
                                'chest' => $levelDef['rel_chest'],
                            ],
                        ];

                        Cache::put($this->lastHitMetaKey($customerId), [
                            'at_ms' => $nowMs,
                            'old_run_id' => $runId,
                            'new_run_id' => $runId,
                            'response' => $body,
                        ], 10);

                        return [
                            'status' => 200,
                            'body' => $body,
                        ];
                    }

                    // 0/6 Herzen erreicht -> Rueckstufung maximal bis auf das permanente Raum-Plateau & frische 6/6 Herzen
                    $demotedCandidate = max(1, $currentRawUnlocked - 1);
                    $newRawUnlocked = max($permanentFloorLevel, $demotedCandidate);
                    $newEffectiveUnlocked = max(1, min($maxCap, $newRawUnlocked));
                    $newTargetLevel = max(1, min($runLevel, $newEffectiveUnlocked));
                    $wasDemoted = ($newRawUnlocked < $currentRawUnlocked) || ($newTargetLevel < $runLevel);
                    $unlockedPortals = $newEffectiveUnlocked >= 2 ? range(2, $newEffectiveUnlocked) : [];

                    $newRunId = Str::random(40);
                    $newLifeNonce = Str::random(32);
                    $newSecret = Str::random(48);

                    $gam->magma_unlocked_level = $newRawUnlocked;
                    $gam->magma_hearts = self::MAX_HEARTS;
                    $gam->magma_active_run_id = $newRunId;
                    $gam->magma_run_committed = false;
                    $gam->save();

                    FunkenwerkHeroesController::forgetPublicCache();
                    LobbyInstanceService::forgetCustomerCache($customerId);
                    $this->storeActiveLifeNonce($customerId, $newLifeNonce);

                    Cache::put(
                        $cacheKey,
                        [
                            'customer_id' => $customerId,
                            'run_id' => $newRunId,
                            'life_nonce' => $newLifeNonce,
                            'level' => $newTargetLevel,
                            'secret' => $newSecret,
                            'started_at_ms' => $nowMs,
                            'cp1_at_ms' => null,
                            'cp2_at_ms' => null,
                            'stage' => 0,
                            'claim_token' => null,
                            'committed' => false,
                            'shield_uses' => 0,
                            'last_shield_at_ms' => null,
                            'last_hit_at_ms' => $nowMs,
                            'hearts' => self::MAX_HEARTS,
                        ],
                        Carbon::now()->addMinutes(self::RUN_TTL_MINUTES)
                    );

                    $newLevelDef = $this->getLevelDefinition($newTargetLevel);

                    $body = [
                        'success' => true,
                        'status' => $wasDemoted ? 'level_demoted' : 'hearts_reset',
                        'hearts' => self::MAX_HEARTS,
                        'max_hearts' => self::MAX_HEARTS,
                        'demoted' => $wasDemoted,
                        'unlocked_level' => $newEffectiveUnlocked,
                        'highest_unlocked_level' => $newEffectiveUnlocked,
                        'unlocked_portals' => $unlockedPortals,
                        'demoted_to_level' => $newTargetLevel,
                        'target_level' => $newTargetLevel,
                        'run_id' => $newRunId,
                        'life_nonce' => $newLifeNonce,
                        'shield_charges_remaining' => self::MAX_SHIELD_USES_PER_RUN,
                        'anchors' => [
                            'cp1' => $newLevelDef['rel_cp1'],
                            'cp2' => $newLevelDef['rel_cp2'],
                            'chest' => $newLevelDef['rel_chest'],
                        ],
                    ];

                    Cache::put($this->lastHitMetaKey($customerId), [
                        'at_ms' => $nowMs,
                        'old_run_id' => $runId,
                        'new_run_id' => $newRunId,
                        'response' => $body,
                    ], 10);

                    return [
                        'status' => 200,
                        'body' => $body,
                    ];
                });
            }
        );
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function recordCheckpoint(
        Customer $customer,
        string $runId,
        int $stage,
        float $relX,
        float $relY,
        float $relZ,
        ?int $requestedLevel = null,
        ?string $lifeNonce = null
    ): array {
        if ($denied = $this->checkSanctumAccess($customer)) {
            $this->invalidateActiveRunForCustomer((string) $customer->id);

            return $denied;
        }

        $customerId = (string) $customer->id;

        if (
            !is_finite($relX)
            || !is_finite($relY)
            || !is_finite($relZ)
            || abs($relX) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($relY) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($relZ) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || !in_array($stage, [1, 2], true)
        ) {
            $this->logCheatAttempt($customerId, 'checkpoint_non_finite_or_invalid_stage');

            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'retryable' => false,
                    'message' => 'Ungültige Positionsdaten am Checkpoint.',
                ],
            ];
        }

        return $this->withCustomerParkourLock(
            $customerId,
            function () use ($customerId, $runId, $stage, $relX, $relY, $relZ, $requestedLevel, $lifeNonce): array {
                $cacheKey = $this->runCacheKey($customerId);
                $run = Cache::get($cacheKey);

                if (!is_array($run) || !is_string($run['run_id'] ?? null)) {
                    $this->logCheatAttempt($customerId, 'checkpoint_missing_run', ['stage' => $stage]);

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => 0,
                            'message' => 'Kein aktiver Lauf gefunden. Bitte starte am Eingang der Magma-Kammer neu.',
                        ],
                    ];
                }

                if (!isset($run['customer_id']) || !hash_equals((string) $run['customer_id'], $customerId)) {
                    return $this->destroyRunResponse($customerId, 'checkpoint_customer_mismatch', ['stage' => $stage]);
                }

                $runLevel = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($run['level'] ?? 1)));
                if ($requestedLevel !== null && $requestedLevel !== $runLevel) {
                    return $this->destroyRunResponse($customerId, 'checkpoint_level_mismatch', [
                        'run_level' => $runLevel,
                        'requested_level' => $requestedLevel,
                    ]);
                }

                $nowMs = Carbon::now()->getTimestampMs();
                $startedAtMs = (int) ($run['started_at_ms'] ?? 0);
                if ($startedAtMs <= 0 || ($nowMs - $startedAtMs) > self::MAX_RUN_DURATION_MS || $nowMs < $startedAtMs) {
                    return $this->destroyRunResponse(
                        $customerId,
                        'checkpoint_expired_or_clock_skew',
                        ['elapsed_ms' => $nowMs - $startedAtMs],
                        'Dein Lauf ist abgelaufen. Bitte starte am Eingang der Magma-Kammer neu.'
                    );
                }

                $currentStage = (int) ($run['stage'] ?? 0);

                if ($runId === '') {
                    $this->refreshRunTtl($cacheKey, $run);

                    return $this->retryableRunResponse(
                        'Lauf wird noch initialisiert. Bitte kurz warten.',
                        $currentStage,
                        250
                    );
                }

                if (!hash_equals($run['run_id'], $runId)) {
                    $this->logCheatAttempt($customerId, 'checkpoint_invalid_run_id', ['stage' => $stage]);

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => $currentStage,
                            'message' => 'Ungültiger Lauf.',
                        ],
                    ];
                }

                $runLifeNonce = (string) ($run['life_nonce'] ?? '');
                if (!$this->verifyRunLifeNonce($customerId, $runLifeNonce, $lifeNonce)) {
                    $this->logCheatAttempt($customerId, 'checkpoint_life_nonce_mismatch', ['stage' => $stage]);

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => 0,
                            'message' => 'Dieser Versuch wurde bereits beendet. Bitte starte auf dem Startbalkon neu.',
                        ],
                    ];
                }

                $levelDef = $this->getLevelDefinition($runLevel);
                $cp1Anchor = $levelDef['rel_cp1'];
                $cp2Anchor = $levelDef['rel_cp2'];

                if ($stage === 1) {
                    $dist = hypot($relX - (float) $cp1Anchor['x'], $relZ - (float) $cp1Anchor['z']);
                    if ($dist > self::CP1_MAX_DIST || $relY < self::MIN_REL_Y || $relY > self::MAX_REL_Y) {
                        $this->refreshRunTtl($cacheKey, $run);
                        $this->logCheatAttempt($customerId, 'cp1_coordinates_violation', [
                            'dist' => round($dist, 2),
                            'rel_y' => round($relY, 2),
                            'level' => $runLevel,
                        ]);

                        return $this->retryableRunResponse(
                            'Bitte tritt näher an den ersten Checkpoint heran.',
                            $currentStage,
                            0
                        );
                    }

                    if ($currentStage === 1 || $currentStage === 2) {
                        $this->ensureRunCommittedInDb($customerId, $runId, $run, $cacheKey);

                        return [
                            'status' => 200,
                            'body' => [
                                'success' => true,
                                'level' => $runLevel,
                                'stage' => $currentStage,
                                'life_nonce' => $runLifeNonce,
                                'claim_token' => $currentStage === 2 && is_string($run['claim_token'] ?? null)
                                    ? $run['claim_token']
                                    : null,
                            ],
                        ];
                    }

                    if ($currentStage !== 0) {
                        $this->refreshRunTtl($cacheKey, $run);

                        return $this->retryableRunResponse(
                            'Bitte starte den Lauf am Eingang der Magma-Kammer neu.',
                            0,
                            0
                        );
                    }

                    $elapsedCp1Ms = $nowMs - $startedAtMs;
                    if ($elapsedCp1Ms < self::MIN_SPLIT_1_MS) {
                        $waitMs = self::MIN_SPLIT_1_MS - $elapsedCp1Ms;
                        $this->refreshRunTtl($cacheKey, $run);
                        $this->logCheatAttempt($customerId, 'cp1_speedhack_blocked', [
                            'elapsed_ms' => $elapsedCp1Ms,
                            'level' => $runLevel,
                        ]);

                        return $this->retryableRunResponse(
                            'Checkpoint 1 wurde zu früh ausgelöst. Bitte bleib kurz auf der Plattform.',
                            $currentStage,
                            $waitMs
                        );
                    }

                    $run['stage'] = 1;
                    $run['cp1_at_ms'] = $nowMs;
                    $this->ensureRunCommittedInDb($customerId, $runId, $run, $cacheKey);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'stage' => 1,
                            'life_nonce' => $runLifeNonce,
                            'claim_token' => null,
                        ],
                    ];
                }

                // Stage 2
                $dist = hypot($relX - (float) $cp2Anchor['x'], $relZ - (float) $cp2Anchor['z']);

                if ($currentStage === 2 && !empty($run['claim_token']) && is_string($run['claim_token'])) {
                    if ($dist > self::CP2_MAX_DIST || $relY < self::MIN_REL_Y || $relY > self::MAX_REL_Y) {
                        $this->refreshRunTtl($cacheKey, $run);
                        $this->logCheatAttempt($customerId, 'cp2_retry_coordinates_violation', [
                            'dist' => round($dist, 2),
                            'rel_y' => round($relY, 2),
                            'level' => $runLevel,
                        ]);

                        return $this->retryableRunResponse(
                            'Bitte tritt näher an den zweiten Checkpoint heran.',
                            2,
                            0
                        );
                    }

                    $this->ensureRunCommittedInDb($customerId, $runId, $run, $cacheKey);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'stage' => 2,
                            'life_nonce' => $runLifeNonce,
                            'claim_token' => $run['claim_token'],
                        ],
                    ];
                }

                $cp1AtMs = $run['cp1_at_ms'] ?? null;
                if ($currentStage !== 1 || !is_int($cp1AtMs) || $cp1AtMs <= 0) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'checkpoint_1_skipped', [
                        'current_stage' => $currentStage,
                        'level' => $runLevel,
                    ]);

                    return $this->retryableRunResponse(
                        'Bitte passiere zuerst den ersten Checkpoint.',
                        $currentStage,
                        0
                    );
                }

                if ($dist > self::CP2_MAX_DIST || $relY < self::MIN_REL_Y || $relY > self::MAX_REL_Y) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'cp2_coordinates_violation', [
                        'dist' => round($dist, 2),
                        'rel_y' => round($relY, 2),
                        'level' => $runLevel,
                    ]);

                    return $this->retryableRunResponse(
                        'Bitte tritt näher an den zweiten Checkpoint heran.',
                        1,
                        0
                    );
                }

                $elapsedCp2Ms = $nowMs - $cp1AtMs;
                if ($elapsedCp2Ms < self::MIN_SPLIT_2_MS) {
                    $waitMs = self::MIN_SPLIT_2_MS - $elapsedCp2Ms;
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'cp2_speedhack_blocked', [
                        'elapsed_ms' => $elapsedCp2Ms,
                        'level' => $runLevel,
                    ]);

                    return $this->retryableRunResponse(
                        'Checkpoint 2 wurde zu früh ausgelöst. Bitte bleib kurz auf der Plattform.',
                        1,
                        $waitMs
                    );
                }

                $secret = (string) ($run['secret'] ?? '');
                if ($secret === '') {
                    return $this->destroyRunResponse($customerId, 'missing_run_secret');
                }

                $claimToken = $this->computeClaimToken(
                    $customerId,
                    $runId,
                    $runLevel,
                    $runLifeNonce,
                    $cp1AtMs,
                    $nowMs,
                    $secret
                );
                $run['stage'] = 2;
                $run['cp2_at_ms'] = $nowMs;
                $run['claim_token'] = $claimToken;

                $this->ensureRunCommittedInDb($customerId, $runId, $run, $cacheKey);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'level' => $runLevel,
                        'stage' => 2,
                        'life_nonce' => $runLifeNonce,
                        'claim_token' => $claimToken,
                    ],
                ];
            }
        );
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function claimChest(
        Customer $customer,
        string $runId,
        string $claimToken,
        float $relX,
        float $relY,
        float $relZ,
        ?int $requestedLevel = null,
        ?string $lifeNonce = null
    ): array {
        if ($denied = $this->checkSanctumAccess($customer)) {
            $this->invalidateActiveRunForCustomer((string) $customer->id);

            return $denied;
        }

        $customerId = (string) $customer->id;

        if (
            !is_finite($relX)
            || !is_finite($relY)
            || !is_finite($relZ)
            || abs($relX) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($relY) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($relZ) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
        ) {
            $this->logCheatAttempt($customerId, 'claim_non_finite_coordinates');

            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'retryable' => false,
                    'message' => 'Ungültige Positionsdaten an der Schatztruhe.',
                ],
            ];
        }

        return $this->withCustomerParkourLock(
            $customerId,
            function () use ($customer, $customerId, $runId, $claimToken, $relX, $relY, $relZ, $requestedLevel, $lifeNonce): array {
                $cacheKey = $this->runCacheKey($customerId);

                if ($claimToken !== '' && Cache::has($this->usedTokenKey($claimToken))) {
                    return $this->destroyRunResponse($customerId, 'claim_token_replay_blocked');
                }

                $run = Cache::get($cacheKey);

                if (!is_array($run) || !is_string($run['run_id'] ?? null)) {
                    $this->logCheatAttempt($customerId, 'claim_missing_run');

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => 0,
                            'message' => 'Ungültiger Lauf. Bitte starte den Parkour am Eingang der Magma-Kammer neu.',
                        ],
                    ];
                }

                if (
                    !isset($run['customer_id'])
                    || !hash_equals((string) $run['customer_id'], $customerId)
                    || empty($run['secret'])
                    || !is_string($run['secret'])
                    || !is_int($run['started_at_ms'] ?? null)
                ) {
                    return $this->destroyRunResponse($customerId, 'claim_customer_or_secret_mismatch');
                }

                $runLevel = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($run['level'] ?? 1)));
                if ($requestedLevel !== null && $requestedLevel !== $runLevel) {
                    return $this->destroyRunResponse($customerId, 'claim_level_mismatch', [
                        'run_level' => $runLevel,
                        'requested_level' => $requestedLevel,
                    ]);
                }

                $nowMs = Carbon::now()->getTimestampMs();
                $startedAtMs = (int) $run['started_at_ms'];
                if ($startedAtMs <= 0 || ($nowMs - $startedAtMs) > self::MAX_RUN_DURATION_MS || $nowMs < $startedAtMs) {
                    return $this->destroyRunResponse(
                        $customerId,
                        'claim_run_expired',
                        ['elapsed_ms' => $nowMs - $startedAtMs],
                        'Dein Lauf ist abgelaufen. Bitte starte am Eingang der Magma-Kammer neu.'
                    );
                }

                $currentStage = (int) ($run['stage'] ?? 0);

                if ($runId === '') {
                    if ($claimToken !== '') {
                        return $this->destroyRunResponse($customerId, 'forged_token_empty_run_id');
                    }

                    $this->refreshRunTtl($cacheKey, $run);

                    return $this->retryableRunResponse(
                        'Lauf wird noch synchronisiert. Bitte kurz warten und erneut öffnen.',
                        $currentStage,
                        250
                    );
                }

                if (!hash_equals($run['run_id'], $runId)) {
                    $this->logCheatAttempt($customerId, 'claim_run_id_mismatch');

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => $currentStage,
                            'message' => 'Ungültiger Lauf.',
                        ],
                    ];
                }

                $runLifeNonce = (string) ($run['life_nonce'] ?? '');
                if (!$this->verifyRunLifeNonce($customerId, $runLifeNonce, $lifeNonce)) {
                    $this->logCheatAttempt($customerId, 'claim_life_nonce_mismatch');

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'current_stage' => 0,
                            'message' => 'Dieser Versuch wurde durch einen Treffer zurückgesetzt. Bitte passiere die Checkpoints erneut.',
                        ],
                    ];
                }

                if ($currentStage !== 2) {
                    if ($claimToken !== '') {
                        return $this->destroyRunResponse($customerId, 'forged_token_before_stage_2', [
                            'current_stage' => $currentStage,
                        ]);
                    }

                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'claim_before_stage_2_retryable', [
                        'current_stage' => $currentStage,
                    ]);

                    return $this->retryableRunResponse(
                        'Bitte passiere zuerst beide Checkpoints der Magma-Kammer.',
                        $currentStage,
                        300
                    );
                }

                if (
                    empty($run['claim_token'])
                    || !is_string($run['claim_token'])
                    || !is_int($run['cp1_at_ms'] ?? null)
                    || !is_int($run['cp2_at_ms'] ?? null)
                ) {
                    return $this->destroyRunResponse($customerId, 'corrupted_stage_2_state');
                }

                if ($claimToken === '') {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'claim_empty_token_in_flight_retryable');

                    return $this->retryableRunResponse(
                        'Checkpoint-Bestätigung wird noch synchronisiert. Bitte kurz warten und erneut öffnen.',
                        2,
                        250
                    );
                }

                if (!hash_equals($run['claim_token'], $claimToken)) {
                    return $this->destroyRunResponse($customerId, 'forged_token_mismatch');
                }

                $expectedToken = $this->computeClaimToken(
                    $customerId,
                    $runId,
                    $runLevel,
                    $runLifeNonce,
                    (int) $run['cp1_at_ms'],
                    (int) $run['cp2_at_ms'],
                    (string) $run['secret']
                );

                if (!hash_equals($expectedToken, $claimToken)) {
                    return $this->destroyRunResponse($customerId, 'claim_hmac_mismatch');
                }

                $levelDef = $this->getLevelDefinition($runLevel);
                $chestAnchor = $levelDef['rel_chest'];
                $dist = hypot($relX - (float) $chestAnchor['x'], $relZ - (float) $chestAnchor['z']);

                if ($dist > self::CHEST_MAX_DIST || $relY < self::MIN_REL_Y || $relY > self::MAX_REL_Y) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'chest_coordinates_violation', [
                        'dist' => round($dist, 2),
                        'rel_y' => round($relY, 2),
                        'level' => $runLevel,
                    ]);

                    return $this->retryableRunResponse(
                        'Bitte tritt direkt vor die Schatztruhe auf der Zielinsel.',
                        2,
                        0
                    );
                }

                $cp1AtMs = (int) $run['cp1_at_ms'];
                $cp2AtMs = (int) $run['cp2_at_ms'];
                $cp1SplitMs = $cp1AtMs - $startedAtMs;
                $cp2SplitMs = $cp2AtMs - $cp1AtMs;
                $sinceCp2Ms = $nowMs - $cp2AtMs;
                $totalDurationMs = $nowMs - $startedAtMs;

                if ($cp1SplitMs < self::MIN_SPLIT_1_MS || $cp2SplitMs < self::MIN_SPLIT_2_MS) {
                    return $this->destroyRunResponse($customerId, 'claim_corrupted_split_timestamps');
                }

                $minTotalForLevelMs = self::minTotalRunMs($runLevel);
                $waitAfterCp2Ms = max(0, self::MIN_SPLIT_CLAIM_MS - $sinceCp2Ms);
                $waitTotalMs = max(0, $minTotalForLevelMs - $totalDurationMs);
                $requiredWaitMs = max($waitAfterCp2Ms, $waitTotalMs);

                if ($requiredWaitMs > 0) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'claim_early_timing_retryable', [
                        'total_ms' => $totalDurationMs,
                        'since_cp2_ms' => $sinceCp2Ms,
                        'wait_ms' => $requiredWaitMs,
                    ]);

                    return $this->retryableRunResponse(
                        'Fast geschafft! Bitte kurz auf der Schatzinsel landen und erneut öffnen.',
                        2,
                        $requiredWaitMs
                    );
                }

                if ($runLevel >= self::MASTER_KEY_MIN_LEVEL && !$this->hasMasterKey($customer)) {
                    Cache::forget($cacheKey);
                    $this->clearActiveLifeNonce($customerId);

                    return [
                        'status' => 403,
                        'body' => [
                            'success' => false,
                            'code' => 'MASTER_KEY_REQUIRED',
                            'master_key_unlocked' => false,
                            'gift_voucher_url' => '/produkt/geschenkgutschein',
                            'message' => 'Für Stufe 6 bis 10 wird der Manufaktur-Meisterschlüssel benötigt.',
                        ],
                    ];
                }

                // Single-Use: Run sofort entwerten und atomaren Token-Tombstone setzen, bevor DB-Transaktion startet
                Cache::forget($cacheKey);
                $this->clearActiveLifeNonce($customerId);

                if (!Cache::add($this->usedTokenKey($claimToken), $nowMs, Carbon::now()->addDays(2))) {
                    $this->logCheatAttempt($customerId, 'parallel_claim_race_blocked');

                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'retryable' => false,
                            'message' => 'Ungültiger Lauf.',
                        ],
                    ];
                }

                return DB::transaction(function () use ($customer, $runLevel, $totalDurationMs): array {
                    if ($denied = $this->checkSanctumAccess($customer)) {
                        return $denied;
                    }

                    $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                    if ($gam === null || !$gam->is_active) {
                        return [
                            'status' => 403,
                            'body' => [
                                'success' => false,
                                'code' => 'magic_activation_required',
                                'sanctum_block_reason' => 'magic_activation_required',
                                'message' => 'Bitte führe zuerst Magie aktivieren aus, um die Halle der Helden oder das Magma-Level zu betreten.',
                            ],
                        ];
                    }

                    $today = Carbon::now(self::TIMEZONE)->toDateString();
                    $masterKeyUnlocked = $this->hasMasterKey($customer);
                    $dailyCap = $masterKeyUnlocked ? self::DAILY_CAP_MASTER : self::DAILY_CAP_FREE;

                    $titlesProgress = is_array($gam->titles_progress) && !empty($gam->titles_progress)
                        ? $gam->titles_progress
                        : $this->gamificationService->getInitialTitlesProgress();

                    // Anti-Farming: lava_cleared_levels bleibt permanentes Erstabschluss-Register (max. 1x pro Raum im Leben!)
                    $clearedLevels = $this->normalizeLevelList($titlesProgress['lava_cleared_levels'] ?? []);

                    $rawDaily = $titlesProgress['lava_daily_claims'] ?? null;
                    if (is_array($rawDaily) && ($rawDaily['date'] ?? '') === $today) {
                        $dailyLevels = $this->normalizeLevelList($rawDaily['levels'] ?? []);
                        $dailySparksEarned = max(0, min($dailyCap, (int) ($rawDaily['sparks'] ?? 0)));
                    } elseif (($titlesProgress['_lava_chest_last_claim_date'] ?? null) === $today && empty($titlesProgress['lava_daily_claims'])) {
                        $dailyLevels = [1];
                        $dailySparksEarned = 0;
                    } else {
                        $dailyLevels = [];
                        $dailySparksEarned = 0;
                    }

                    $alreadyClaimedToday = in_array($runLevel, $dailyLevels, true);
                    $wasEverCleared = in_array($runLevel, $clearedLevels, true);
                    $isFirstClear = !$wasEverCleared && !$alreadyClaimedToday;
                    $sparksAwarded = 0;

                    if (!$alreadyClaimedToday) {
                        if ($isFirstClear) {
                            $sparksAwarded = self::FIRST_CLEAR_REWARDS[$runLevel];
                            $clearedLevels[] = $runLevel;
                            $clearedLevels = $this->normalizeLevelList($clearedLevels);
                        } else {
                            $remainingDailyCap = max(0, $dailyCap - $dailySparksEarned);
                            $sparksAwarded = min(self::DAILY_REWARDS[$runLevel], $remainingDailyCap);
                            if ($sparksAwarded > 0) {
                                $dailySparksEarned += $sparksAwarded;
                            }
                        }

                        $dailyLevels[] = $runLevel;
                        $dailyLevels = $this->normalizeLevelList($dailyLevels);
                        $titlesProgress['_lava_chest_last_claim_date'] = $today;
                    }

                    if ($sparksAwarded > 0) {
                        $gam->funken_balance = (int) $gam->funken_balance + $sparksAwarded;
                        $gam->funken_total_earned = (int) $gam->funken_total_earned + $sparksAwarded;
                        $this->gamificationService->syncLevelFromLifetimeSparks($gam);

                        $titlesProgress['funkenkoenig'] = (int) $gam->funken_total_earned;
                        $titlesProgress['_lava_chest_total_claims'] = (int) ($titlesProgress['_lava_chest_total_claims'] ?? 0) + 1;
                    }

                    $titlesProgress['lava_cleared_levels'] = $clearedLevels;
                    $titlesProgress['lava_daily_claims'] = [
                        'date' => $today,
                        'levels' => $dailyLevels,
                        'sparks' => $dailySparksEarned,
                    ];

                    $maxSequentialCleared = $this->computeMaxSequentialCleared($clearedLevels);
                    $permanentFloorLevel = min(self::MAX_MAGMA_LEVEL, $maxSequentialCleared + 1);
                    $currentMagmaUnlocked = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($gam->magma_unlocked_level ?: 1)));
                    $nextRawUnlocked = max(
                        $permanentFloorLevel,
                        $currentMagmaUnlocked,
                        min(self::MAX_MAGMA_LEVEL, $runLevel + 1)
                    );

                    $gam->magma_unlocked_level = $nextRawUnlocked;
                    $gam->magma_active_run_id = null;
                    $gam->magma_run_committed = false;
                    $gam->titles_progress = $titlesProgress;
                    $gam->save();

                    FunkenwerkHeroesController::forgetPublicCache();
                    LobbyInstanceService::forgetCustomerCache((string) $customer->id);

                    if ($sparksAwarded > 0) {
                        $levelTitle = self::LEVEL_TITLES[$runLevel];
                        $logDescription = $isFirstClear
                            ? "Magma-Stufe {$runLevel} ({$levelTitle}) erstmals gemeistert (+{$sparksAwarded} Funken)"
                            : "Magma-Stufe {$runLevel} ({$levelTitle}) Tages-Truhe geöffnet (+{$sparksAwarded} Funken)";

                        CustomerGamificationLog::create([
                            'customer_id' => (string) $customer->id,
                            'event_type' => 'lava_parkour_chest',
                            'category' => 'parkour',
                            'title' => "Magma-Stufe {$runLevel}: {$levelTitle}",
                            'description' => $logDescription,
                            'sparks_delta' => $sparksAwarded,
                            'balance_after' => (int) $gam->funken_balance,
                            'metadata' => [
                                'level' => $runLevel,
                                'is_first_clear' => $isFirstClear,
                                'run_duration_ms' => $totalDurationMs,
                                'date' => $today,
                            ],
                        ]);
                    }

                    $playerLevel = min(
                        GameConfig::MAX_LEVEL,
                        max(1, (int) ($gam->level ?: GameConfig::getLevelForLifetimeSparks((int) $gam->funken_total_earned)))
                    );
                    $lvlCfg = GameConfig::getLevelConfig($playerLevel);
                    $modelUrl = '/' . ltrim((string) ($lvlCfg['model_path'] ?? 'shop/customer/gamification/models/funki_lvl_1_rags.glb'), '/');

                    $highestUnlockedLevel = $masterKeyUnlocked ? $nextRawUnlocked : min(5, $nextRawUnlocked);
                    $unlockedPortals = $highestUnlockedLevel >= 2 ? range(2, $highestUnlockedLevel) : [];
                    $heroMetrics = LobbyInstanceService::calculateHeroRankingMetrics($gam);
                    $isLockedToday = in_array($runLevel, $dailyLevels, true);
                    $hearts = max(1, min(self::MAX_HEARTS, (int) ($gam->magma_hearts ?: self::MAX_HEARTS)));

                    if ($alreadyClaimedToday) {
                        $message = 'Diese Truhe wurde heute bereits geöffnet. Morgen ab 00:00 Uhr ist sie wieder bereit!';
                    } elseif ($sparksAwarded > 0) {
                        $message = '+' . $sparksAwarded . ' Funken erhalten!';
                    } else {
                        $message = 'Tageslimit erreicht (' . $dailyCap . ' Funken pro Tag). Morgen ab 00:00 Uhr füllt sich die Truhe neu!';
                    }

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'hearts' => $hearts,
                            'max_hearts' => self::MAX_HEARTS,
                            'unlocked_level' => $highestUnlockedLevel,
                            'sparks_awarded' => $sparksAwarded,
                            'rewarded_sparks' => $sparksAwarded,
                            'is_first_clear' => $isFirstClear,
                            'already_claimed_today' => $alreadyClaimedToday,
                            'claimed_today' => $isLockedToday,
                            'chest_locked_today' => $isLockedToday,
                            'funken_balance' => (int) $gam->funken_balance,
                            'new_balance' => (int) $gam->funken_balance,
                            'funken_total_earned' => (int) $gam->funken_total_earned,
                            'hero_score' => $heroMetrics['hero_score'],
                            'magma_highest_level' => $heroMetrics['magma_highest_level'],
                            'magma_cleared_count' => $heroMetrics['magma_cleared_count'],
                            'magma_prestige_points' => $heroMetrics['magma_prestige_points'],
                            'player_level' => $playerLevel,
                            'new_level' => $playerLevel,
                            'model_url' => $modelUrl,
                            'master_key_unlocked' => $masterKeyUnlocked,
                            'highest_unlocked_level' => $highestUnlockedLevel,
                            'unlocked_portals' => $unlockedPortals,
                            'first_cleared_levels' => $clearedLevels,
                            'daily_cleared_levels' => $dailyLevels,
                            'daily_claimed_levels' => $dailyLevels,
                            'daily_sparks_earned' => $dailySparksEarned,
                            'daily_sparks_cap' => $dailyCap,
                            'message' => $message,
                        ],
                    ];
                });
            }
        );
    }

    /**
     * Konsistenter Admin-Vorschau-Modus fuer eingeloggte Admins ohne aktive Customer-Session (0 DB-Writes).
     *
     * @param array<string, mixed> $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handleAdminPreviewAction(Admin $admin, string $action, array $payload = []): array
    {
        $actorId = 'admin_' . $admin->id;
        $cacheKey = $this->runCacheKey($actorId);
        $nowMs = Carbon::now()->getTimestampMs();

        if ($action === 'start') {
            $level = max(1, min(self::MAX_MAGMA_LEVEL, (int) ($payload['level'] ?? 1)));
            $levelDef = $this->getLevelDefinition($level);
            $runId = Str::random(40);
            $lifeNonce = Str::random(32);
            $secret = Str::random(48);

            Cache::put($cacheKey, [
                'run_id' => $runId,
                'life_nonce' => $lifeNonce,
                'level' => $level,
                'secret' => $secret,
                'started_at_ms' => $nowMs,
                'cp1_at_ms' => null,
                'cp2_at_ms' => null,
                'stage' => 0,
                'claim_token' => null,
                'hearts' => self::MAX_HEARTS,
            ], Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'admin_preview' => true,
                    'run_id' => $runId,
                    'life_nonce' => $lifeNonce,
                    'level' => $level,
                    'title' => $levelDef['title'],
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'unlocked_level' => self::MAX_MAGMA_LEVEL,
                    'highest_unlocked_level' => self::MAX_MAGMA_LEVEL,
                    'shield_charges_remaining' => self::MAX_SHIELD_USES_PER_RUN,
                    'master_key_unlocked' => true,
                    'unlocked_portals' => range(2, 10),
                    'anchors' => [
                        'cp1' => $levelDef['rel_cp1'],
                        'cp2' => $levelDef['rel_cp2'],
                        'chest' => $levelDef['rel_chest'],
                    ],
                ],
            ];
        }

        $run = Cache::get($cacheKey);
        $level = is_array($run) ? max(1, min(self::MAX_MAGMA_LEVEL, (int) ($run['level'] ?? 1))) : 1;
        $levelDef = $this->getLevelDefinition($level);
        $runId = is_array($run) ? (string) ($run['run_id'] ?? Str::random(40)) : Str::random(40);
        $lifeNonce = is_array($run) ? (string) ($run['life_nonce'] ?? Str::random(32)) : Str::random(32);

        if ($action === 'commit') {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'status' => 'committed',
                    'run_id' => $runId,
                    'committed' => true,
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'unlocked_level' => self::MAX_MAGMA_LEVEL,
                    'life_nonce' => $lifeNonce,
                ],
            ];
        }

        if ($action === 'leave') {
            Cache::forget($cacheKey);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'status' => 'left_safely',
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'unlocked_level' => self::MAX_MAGMA_LEVEL,
                ],
            ];
        }

        if ($action === 'hit') {
            $newNonce = Str::random(32);
            if (is_array($run)) {
                $run['life_nonce'] = $newNonce;
                $run['stage'] = 0;
                $run['started_at_ms'] = $nowMs;
                $this->refreshRunTtl($cacheKey, $run);
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'status' => !empty($payload['used_shield']) ? 'shield_saved' : 'heart_lost',
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'demoted' => false,
                    'unlocked_level' => self::MAX_MAGMA_LEVEL,
                    'target_level' => $level,
                    'run_id' => $runId,
                    'life_nonce' => $newNonce,
                    'shield_charges_remaining' => self::MAX_SHIELD_USES_PER_RUN,
                    'anchors' => [
                        'cp1' => $levelDef['rel_cp1'],
                        'cp2' => $levelDef['rel_cp2'],
                        'chest' => $levelDef['rel_chest'],
                    ],
                ],
            ];
        }

        if ($action === 'checkpoint') {
            $stage = (int) ($payload['stage'] ?? 1);
            $tok = $stage === 2 ? hash_hmac('sha256', $actorId . '|' . $runId, (string) ($run['secret'] ?? 'adm')) : null;
            if (is_array($run)) {
                $run['stage'] = $stage;
                $run['claim_token'] = $tok;
                $this->refreshRunTtl($cacheKey, $run);
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'level' => $level,
                    'stage' => $stage,
                    'life_nonce' => $lifeNonce,
                    'claim_token' => $tok,
                ],
            ];
        }

        if ($action === 'buy_potion') {
            $qty = max(1, min(GameConfig::HEALING_POTION_MAX_STACK, (int) ($payload['quantity'] ?? 1)));
            $bagSlots = array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null);
            $bagSlots[0] = [
                'slot' => 0,
                'item_key' => GameConfig::HEALING_POTION_KEY,
                'name' => 'Heiltrank',
                'qty' => $qty,
                'heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
            ];

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'admin_preview' => true,
                    'item_key' => GameConfig::HEALING_POTION_KEY,
                    'purchased_qty' => $qty,
                    'potion_count' => $qty,
                    'potion_price_sparks' => GameConfig::HEALING_POTION_COST_SPARKS,
                    'potion_heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
                    'bag_slots' => $bagSlots,
                    'quick_slots' => [
                        'slot_1' => GameConfig::HEALING_POTION_KEY,
                        'slot_2' => null,
                    ],
                    'funken_balance' => 0,
                    'funken_total_earned' => 0,
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'message' => 'Admin-Vorschau: Heiltrank simuliert.',
                ],
            ];
        }

        if ($action === 'use_item') {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'admin_preview' => true,
                    'item_key' => GameConfig::HEALING_POTION_KEY,
                    'healed_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
                    'hearts_before' => 4,
                    'hearts' => self::MAX_HEARTS,
                    'max_hearts' => self::MAX_HEARTS,
                    'potion_count' => 1,
                    'bag_slots' => array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null),
                    'quick_slots' => [
                        'slot_1' => GameConfig::HEALING_POTION_KEY,
                        'slot_2' => null,
                    ],
                    'message' => '+2 Herzen regeneriert!',
                ],
            ];
        }

        if ($action === 'equip_slot') {
            $qSlot = (int) ($payload['quick_slot'] ?? 1);
            $rawKey = isset($payload['item_key']) && $payload['item_key'] === GameConfig::HEALING_POTION_KEY
                ? GameConfig::HEALING_POTION_KEY
                : null;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'admin_preview' => true,
                    'potion_count' => 1,
                    'bag_slots' => array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null),
                    'quick_slots' => [
                        'slot_1' => $qSlot === 1 ? $rawKey : null,
                        'slot_2' => $qSlot === 2 ? $rawKey : null,
                    ],
                ],
            ];
        }

        Cache::forget($cacheKey);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'admin_preview' => true,
                'level' => $level,
                'hearts' => self::MAX_HEARTS,
                'max_hearts' => self::MAX_HEARTS,
                'unlocked_level' => self::MAX_MAGMA_LEVEL,
                'sparks_awarded' => 0,
                'rewarded_sparks' => 0,
                'message' => 'Admin-Vorschau abgeschlossen.',
            ],
        ];
    }

    /**
     * @return array{
     *     potion_count: int,
     *     potion_price_sparks: int,
     *     potion_heal_hearts: int,
     *     bag_slots: array<int, array<string, mixed>|null>,
     *     quick_slots: array{slot_1: string|null, slot_2: string|null}
     * }
     */
    public function getDefaultGuestInventoryPayload(): array
    {
        return [
            'potion_count' => 0,
            'potion_price_sparks' => GameConfig::HEALING_POTION_COST_SPARKS,
            'potion_heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
            'bag_slots' => array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null),
            'quick_slots' => [
                'slot_1' => null,
                'slot_2' => null,
            ],
        ];
    }

    /**
     * Normalisierte Inventar- & Rucksack-Darstellung fuer den Kunden (6 Slots, max. 5 Heiltraenke je Slot = max. 30).
     *
     * @return array{
     *     potion_count: int,
     *     potion_price_sparks: int,
     *     potion_heal_hearts: int,
     *     bag_slots: array<int, array<string, mixed>|null>,
     *     quick_slots: array{slot_1: string|null, slot_2: string|null}
     * }
     */
    public function getInventoryPayload(?Customer $customer, ?CustomerGamification $gam = null): array
    {
        if ($customer === null) {
            return $this->getDefaultGuestInventoryPayload();
        }

        if ($gam === null && Schema::hasTable('customer_gamifications')) {
            $gam = CustomerGamification::where('customer_id', $customer->id)->first();
        }

        if ($gam === null) {
            return $this->getDefaultGuestInventoryPayload();
        }

        $normalized = $this->extractNormalizedInventoryState($gam);

        return [
            'potion_count' => $normalized['potion_count'],
            'potion_price_sparks' => GameConfig::HEALING_POTION_COST_SPARKS,
            'potion_heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
            'bag_slots' => $normalized['bag_slots'],
            'quick_slots' => $normalized['quick_slots'],
        ];
    }

    /**
     * Synchronisiert die Heiltrank-Menge (0..30) in 5er-Stacks auf den 6 Rucksack-Slots (fuer Admin-Support).
     *
     * @return array{
     *     potion_count: int,
     *     bag_slots: array<int, array<string, mixed>|null>,
     *     quick_slots: array{slot_1: string|null, slot_2: string|null}
     * }
     */
    public function syncCustomerPotionsCount(CustomerGamification $gam, int $targetCount): array
    {
        $clampedTotal = max(0, min(GameConfig::HEALING_POTION_MAX_TOTAL, $targetCount));
        $remaining = $clampedTotal;
        $bagSlots = array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null);

        for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS; $i++) {
            if ($remaining <= 0) {
                break;
            }
            $stackQty = min(GameConfig::HEALING_POTION_MAX_STACK, $remaining);
            $bagSlots[$i] = $this->buildPotionSlotEntry($i, $stackQty);
            $remaining -= $stackQty;
        }

        $rawInv = is_array($gam->inventory_items) ? $gam->inventory_items : [];
        $cleanedInv = [];
        foreach ($rawInv as $invKey => $invEntry) {
            if (is_int($invKey)) {
                if (is_array($invEntry) && ($invEntry['item_key'] ?? null) === GameConfig::HEALING_POTION_KEY) {
                    continue;
                }
                if ($invEntry === GameConfig::HEALING_POTION_KEY) {
                    continue;
                }
            }
            $cleanedInv[$invKey] = $invEntry;
        }
        $cleanedInv['bag_slots'] = $bagSlots;
        $cleanedInv[GameConfig::HEALING_POTION_KEY] = $clampedTotal;
        $gam->inventory_items = $cleanedInv;

        $equipped = is_array($gam->equipped_items) ? $gam->equipped_items : [];
        $quickSlots = $this->normalizeQuickSlots($equipped['quick_slots'] ?? null);
        if ($clampedTotal > 0 && $quickSlots['slot_1'] === null && $quickSlots['slot_2'] === null) {
            $quickSlots['slot_1'] = GameConfig::HEALING_POTION_KEY;
        }
        $equipped['quick_slots'] = $quickSlots;
        $gam->equipped_items = $equipped;

        return [
            'potion_count' => $clampedTotal,
            'bag_slots' => $bagSlots,
            'quick_slots' => $quickSlots,
        ];
    }

    /**
     * Kauf von 1..5 Heiltraenken beim NPC-Haendler (8 Funken je Trank, Abzug NUR von funken_balance!).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buyHealingPotion(Customer $customer, int $quantity = 1): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        $qty = max(1, min(GameConfig::HEALING_POTION_MAX_STACK, $quantity));
        $costSparks = GameConfig::HEALING_POTION_COST_SPARKS * $qty;
        $customerId = (string) $customer->id;

        $this->gamificationService->getProfile($customer);

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $qty, $costSparks): array {
            return DB::transaction(function () use ($customer, $customerId, $qty, $costSparks): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                if ($gam === null || !$gam->is_active) {
                    return [
                        'status' => 403,
                        'body' => [
                            'success' => false,
                            'code' => 'magic_activation_required',
                            'sanctum_block_reason' => 'magic_activation_required',
                            'message' => 'Bitte aktiviere zuerst deine Seelenfunken-Magie, um die Halle der Helden und die Magma-Prüfung zu betreten.',
                        ],
                    ];
                }

                $state = $this->extractNormalizedInventoryState($gam);
                $currentCount = $state['potion_count'];

                if (($currentCount + $qty) > GameConfig::HEALING_POTION_MAX_TOTAL) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'code' => 'BAG_FULL',
                            'potion_count' => $currentCount,
                            'bag_slots' => $state['bag_slots'],
                            'quick_slots' => $state['quick_slots'],
                            'message' => 'Dein Rucksack ist voll (maximal 30 Heiltränke).',
                        ],
                    ];
                }

                $currentBalance = (int) $gam->funken_balance;
                if ($currentBalance < $costSparks) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'code' => 'INSUFFICIENT_SPARKS',
                            'funken_balance' => $currentBalance,
                            'required_sparks' => $costSparks,
                            'potion_count' => $currentCount,
                            'message' => 'Nicht genügend Funken für diesen Kauf.',
                        ],
                    ];
                }

                $bagSlots = $state['bag_slots'];
                $toPlace = $qty;

                // 1. Vorhandene Heiltrank-Stacks auf bis zu 5 auffuellen
                for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS && $toPlace > 0; $i++) {
                    if (is_array($bagSlots[$i]) && ($bagSlots[$i]['item_key'] ?? '') === GameConfig::HEALING_POTION_KEY) {
                        $curSlotQty = (int) ($bagSlots[$i]['qty'] ?? 0);
                        if ($curSlotQty < GameConfig::HEALING_POTION_MAX_STACK) {
                            $add = min(GameConfig::HEALING_POTION_MAX_STACK - $curSlotQty, $toPlace);
                            $bagSlots[$i] = $this->buildPotionSlotEntry($i, $curSlotQty + $add);
                            $toPlace -= $add;
                        }
                    }
                }

                // 2. Restliche Traenke in freie Slots legen
                for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS && $toPlace > 0; $i++) {
                    if ($bagSlots[$i] === null) {
                        $add = min(GameConfig::HEALING_POTION_MAX_STACK, $toPlace);
                        $bagSlots[$i] = $this->buildPotionSlotEntry($i, $add);
                        $toPlace -= $add;
                    }
                }

                $newTotalPotions = $this->sumBagPotionCount($bagSlots);
                $quickSlots = $state['quick_slots'];
                if ($quickSlots['slot_1'] === null && $quickSlots['slot_2'] === null) {
                    $quickSlots['slot_1'] = GameConfig::HEALING_POTION_KEY;
                }

                // Abzug AUSSCHLIESSLICH von funken_balance (funken_total_earned bleibt 100 % unveraendert!)
                $gam->funken_balance = $currentBalance - $costSparks;

                $rawInv = is_array($gam->inventory_items) ? $gam->inventory_items : [];
                $rawInv['bag_slots'] = $bagSlots;
                $rawInv[GameConfig::HEALING_POTION_KEY] = $newTotalPotions;
                $gam->inventory_items = $rawInv;

                $equipped = is_array($gam->equipped_items) ? $gam->equipped_items : [];
                $equipped['quick_slots'] = $quickSlots;
                $gam->equipped_items = $equipped;
                $gam->save();

                CustomerGamificationLog::create([
                    'customer_id' => $customerId,
                    'event_type' => 'merchant_healing_potion_buy',
                    'category' => 'shop',
                    'title' => $qty > 1 ? "{$qty}x Heiltrank gekauft" : 'Heiltrank gekauft',
                    'description' => "{$qty}x Heiltrank beim Händler erworben (-{$costSparks} Funken)",
                    'sparks_delta' => -$costSparks,
                    'balance_after' => (int) $gam->funken_balance,
                    'metadata' => [
                        'item_key' => GameConfig::HEALING_POTION_KEY,
                        'quantity' => $qty,
                        'unit_price_sparks' => GameConfig::HEALING_POTION_COST_SPARKS,
                        'potion_count_after' => $newTotalPotions,
                    ],
                ]);

                LobbyInstanceService::forgetCustomerCache($customerId);

                $hearts = max(1, min(self::MAX_HEARTS, (int) ($gam->magma_hearts ?: self::MAX_HEARTS)));

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'item_key' => GameConfig::HEALING_POTION_KEY,
                        'purchased_qty' => $qty,
                        'potion_count' => $newTotalPotions,
                        'potion_price_sparks' => GameConfig::HEALING_POTION_COST_SPARKS,
                        'potion_heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
                        'bag_slots' => $bagSlots,
                        'quick_slots' => $quickSlots,
                        'funken_balance' => (int) $gam->funken_balance,
                        'funken_total_earned' => (int) $gam->funken_total_earned,
                        'hearts' => $hearts,
                        'max_hearts' => self::MAX_HEARTS,
                        'message' => $qty > 1 ? "{$qty} Heiltränke erhalten!" : 'Heiltrank erhalten!',
                    ],
                ];
            });
        });
    }

    /**
     * Setzt 1 Heiltrank ein (+2 Herzen bis maximal 6/6).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function useInventoryItem(Customer $customer, string $itemKey, ?int $slot = null): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        if ($itemKey !== GameConfig::HEALING_POTION_KEY) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'INVALID_ITEM',
                    'message' => 'Unbekannter Gegenstand.',
                ],
            ];
        }

        $customerId = (string) $customer->id;
        $this->gamificationService->getProfile($customer);

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $slot): array {
            return DB::transaction(function () use ($customer, $customerId, $slot): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                if ($gam === null || !$gam->is_active) {
                    return [
                        'status' => 403,
                        'body' => [
                            'success' => false,
                            'code' => 'magic_activation_required',
                            'sanctum_block_reason' => 'magic_activation_required',
                            'message' => 'Bitte aktiviere zuerst deine Seelenfunken-Magie, um die Halle der Helden und die Magma-Prüfung zu betreten.',
                        ],
                    ];
                }

                $state = $this->extractNormalizedInventoryState($gam);
                if ($state['potion_count'] < 1) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'code' => 'NO_POTION',
                            'potion_count' => 0,
                            'bag_slots' => $state['bag_slots'],
                            'quick_slots' => $state['quick_slots'],
                            'message' => 'Kein Heiltrank im Rucksack vorhanden.',
                        ],
                    ];
                }

                $oldHearts = max(1, min(self::MAX_HEARTS, (int) ($gam->magma_hearts ?: self::MAX_HEARTS)));
                if ($oldHearts >= self::MAX_HEARTS) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'code' => 'HEARTS_FULL',
                            'hearts' => self::MAX_HEARTS,
                            'max_hearts' => self::MAX_HEARTS,
                            'potion_count' => $state['potion_count'],
                            'bag_slots' => $state['bag_slots'],
                            'quick_slots' => $state['quick_slots'],
                            'message' => 'Deine Herzen sind bereits voll.',
                        ],
                    ];
                }

                $bagSlots = $state['bag_slots'];
                $consumed = false;
                for ($i = GameConfig::INVENTORY_BAG_SLOTS - 1; $i >= 0; $i--) {
                    if (is_array($bagSlots[$i]) && ($bagSlots[$i]['item_key'] ?? '') === GameConfig::HEALING_POTION_KEY) {
                        $curQty = (int) ($bagSlots[$i]['qty'] ?? 0);
                        if ($curQty > 1) {
                            $bagSlots[$i] = $this->buildPotionSlotEntry($i, $curQty - 1);
                        } else {
                            $bagSlots[$i] = null;
                        }
                        $consumed = true;
                        break;
                    }
                }

                if (!$consumed) {
                    return [
                        'status' => 422,
                        'body' => [
                            'success' => false,
                            'code' => 'NO_POTION',
                            'potion_count' => 0,
                            'message' => 'Kein Heiltrank im Rucksack vorhanden.',
                        ],
                    ];
                }

                $newTotalPotions = $this->sumBagPotionCount($bagSlots);
                $newHearts = min(self::MAX_HEARTS, $oldHearts + GameConfig::HEALING_POTION_HEAL_HEARTS);
                $healedHearts = $newHearts - $oldHearts;

                $rawInv = is_array($gam->inventory_items) ? $gam->inventory_items : [];
                $rawInv['bag_slots'] = $bagSlots;
                $rawInv[GameConfig::HEALING_POTION_KEY] = $newTotalPotions;
                $gam->inventory_items = $rawInv;
                $gam->magma_hearts = $newHearts;
                $gam->save();

                $runCacheKey = $this->runCacheKey($customerId);
                $activeRun = Cache::get($runCacheKey);
                if (is_array($activeRun)) {
                    $activeRun['hearts'] = $newHearts;
                    $this->refreshRunTtl($runCacheKey, $activeRun);
                }

                LobbyInstanceService::forgetCustomerCache($customerId);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'item_key' => GameConfig::HEALING_POTION_KEY,
                        'slot' => $slot,
                        'healed_hearts' => $healedHearts,
                        'hearts_before' => $oldHearts,
                        'hearts' => $newHearts,
                        'max_hearts' => self::MAX_HEARTS,
                        'potion_count' => $newTotalPotions,
                        'bag_slots' => $bagSlots,
                        'quick_slots' => $state['quick_slots'],
                        'message' => '+2 Herzen regeneriert!',
                    ],
                ];
            });
        });
    }

    /**
     * Belegt/leert Quick-Slots (1..2) oder tauscht Rucksack-Slots (0..5) unter strikter Mengen-Invariante.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function equipInventorySlot(
        Customer $customer,
        ?int $quickSlot = null,
        ?string $itemKey = null,
        ?int $fromBagSlot = null,
        ?int $toBagSlot = null
    ): array {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        $customerId = (string) $customer->id;
        $this->gamificationService->getProfile($customer);

        return $this->withCustomerParkourLock(
            $customerId,
            function () use ($customer, $customerId, $quickSlot, $itemKey, $fromBagSlot, $toBagSlot): array {
                return DB::transaction(function () use ($customer, $customerId, $quickSlot, $itemKey, $fromBagSlot, $toBagSlot): array {
                    $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                    if ($gam === null || !$gam->is_active) {
                        return [
                            'status' => 403,
                            'body' => [
                                'success' => false,
                                'code' => 'magic_activation_required',
                                'sanctum_block_reason' => 'magic_activation_required',
                                'message' => 'Bitte aktiviere zuerst deine Seelenfunken-Magie, um die Halle der Helden und die Magma-Prüfung zu betreten.',
                            ],
                        ];
                    }

                    $state = $this->extractNormalizedInventoryState($gam);
                    $beforeCount = $state['potion_count'];
                    $bagSlots = $state['bag_slots'];
                    $quickSlots = $state['quick_slots'];

                    if (
                        $fromBagSlot !== null
                        && $toBagSlot !== null
                        && $fromBagSlot >= 0
                        && $fromBagSlot < GameConfig::INVENTORY_BAG_SLOTS
                        && $toBagSlot >= 0
                        && $toBagSlot < GameConfig::INVENTORY_BAG_SLOTS
                        && $fromBagSlot !== $toBagSlot
                    ) {
                        $slotA = $bagSlots[$fromBagSlot];
                        $slotB = $bagSlots[$toBagSlot];

                        $bagSlots[$fromBagSlot] = is_array($slotB)
                            ? $this->buildPotionSlotEntry($fromBagSlot, (int) ($slotB['qty'] ?? 1))
                            : null;
                        $bagSlots[$toBagSlot] = is_array($slotA)
                            ? $this->buildPotionSlotEntry($toBagSlot, (int) ($slotA['qty'] ?? 1))
                            : null;
                    }

                    // Strikte Server-Invariante: Verschieben im Rucksack darf potion_count niemals veraendern!
                    $afterCount = $this->sumBagPotionCount($bagSlots);
                    if ($afterCount !== $beforeCount) {
                        $bagSlots = $state['bag_slots'];
                        $afterCount = $beforeCount;
                    }

                    if ($quickSlot === 1 || $quickSlot === 2) {
                        $targetKey = 'slot_' . $quickSlot;
                        $otherKey = $quickSlot === 1 ? 'slot_2' : 'slot_1';
                        $normalizedItem = ($itemKey === GameConfig::HEALING_POTION_KEY)
                            ? GameConfig::HEALING_POTION_KEY
                            : null;

                        $quickSlots[$targetKey] = $normalizedItem;
                        if ($normalizedItem !== null && $quickSlots[$otherKey] === $normalizedItem) {
                            $quickSlots[$otherKey] = null;
                        }
                    }

                    $rawInv = is_array($gam->inventory_items) ? $gam->inventory_items : [];
                    $rawInv['bag_slots'] = $bagSlots;
                    $rawInv[GameConfig::HEALING_POTION_KEY] = $afterCount;
                    $gam->inventory_items = $rawInv;

                    $equipped = is_array($gam->equipped_items) ? $gam->equipped_items : [];
                    $equipped['quick_slots'] = $quickSlots;
                    $gam->equipped_items = $equipped;
                    $gam->save();

                    LobbyInstanceService::forgetCustomerCache($customerId);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'potion_count' => $afterCount,
                            'bag_slots' => $bagSlots,
                            'quick_slots' => $quickSlots,
                        ],
                    ];
                });
            }
        );
    }

    /**
     * @return array{
     *     potion_count: int,
     *     bag_slots: array<int, array<string, mixed>|null>,
     *     quick_slots: array{slot_1: string|null, slot_2: string|null}
     * }
     */
    private function extractNormalizedInventoryState(CustomerGamification $gam): array
    {
        $rawInv = is_array($gam->inventory_items) ? $gam->inventory_items : [];
        $bagSlots = array_fill(0, GameConfig::INVENTORY_BAG_SLOTS, null);
        $totalCount = 0;

        if (isset($rawInv['bag_slots']) && is_array($rawInv['bag_slots'])) {
            for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS; $i++) {
                $entry = $rawInv['bag_slots'][$i] ?? null;
                if (
                    is_array($entry)
                    && ($entry['item_key'] ?? '') === GameConfig::HEALING_POTION_KEY
                    && (int) ($entry['qty'] ?? 0) > 0
                ) {
                    $allowedRemaining = GameConfig::HEALING_POTION_MAX_TOTAL - $totalCount;
                    if ($allowedRemaining <= 0) {
                        break;
                    }
                    $qty = max(1, min(GameConfig::HEALING_POTION_MAX_STACK, $allowedRemaining, (int) $entry['qty']));
                    $bagSlots[$i] = $this->buildPotionSlotEntry($i, $qty);
                    $totalCount += $qty;
                }
            }
        } elseif (isset($rawInv[GameConfig::HEALING_POTION_KEY]) && is_numeric($rawInv[GameConfig::HEALING_POTION_KEY])) {
            $remaining = max(0, min(GameConfig::HEALING_POTION_MAX_TOTAL, (int) $rawInv[GameConfig::HEALING_POTION_KEY]));
            $totalCount = $remaining;
            for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS && $remaining > 0; $i++) {
                $qty = min(GameConfig::HEALING_POTION_MAX_STACK, $remaining);
                $bagSlots[$i] = $this->buildPotionSlotEntry($i, $qty);
                $remaining -= $qty;
            }
        } else {
            $legacySum = 0;
            foreach ($rawInv as $invKey => $entry) {
                if (!is_int($invKey)) {
                    continue;
                }
                if (is_array($entry) && ($entry['item_key'] ?? null) === GameConfig::HEALING_POTION_KEY) {
                    $legacySum += max(0, (int) ($entry['quantity'] ?? $entry['qty'] ?? $entry['count'] ?? 1));
                } elseif ($entry === GameConfig::HEALING_POTION_KEY) {
                    $legacySum += 1;
                }
            }
            if ($legacySum > 0) {
                $remaining = max(0, min(GameConfig::HEALING_POTION_MAX_TOTAL, $legacySum));
                $totalCount = $remaining;
                for ($i = 0; $i < GameConfig::INVENTORY_BAG_SLOTS && $remaining > 0; $i++) {
                    $qty = min(GameConfig::HEALING_POTION_MAX_STACK, $remaining);
                    $bagSlots[$i] = $this->buildPotionSlotEntry($i, $qty);
                    $remaining -= $qty;
                }
            }
        }

        $equipped = is_array($gam->equipped_items) ? $gam->equipped_items : [];
        $quickSlots = $this->normalizeQuickSlots($equipped['quick_slots'] ?? null);

        return [
            'potion_count' => $totalCount,
            'bag_slots' => $bagSlots,
            'quick_slots' => $quickSlots,
        ];
    }

    /**
     * @return array{slot: int, item_key: string, name: string, qty: int, heal_hearts: int}
     */
    private function buildPotionSlotEntry(int $slotIndex, int $qty): array
    {
        return [
            'slot' => max(0, min(GameConfig::INVENTORY_BAG_SLOTS - 1, $slotIndex)),
            'item_key' => GameConfig::HEALING_POTION_KEY,
            'name' => 'Heiltrank',
            'qty' => max(1, min(GameConfig::HEALING_POTION_MAX_STACK, $qty)),
            'heal_hearts' => GameConfig::HEALING_POTION_HEAL_HEARTS,
        ];
    }

    /**
     * @param array<int, array<string, mixed>|null> $bagSlots
     */
    private function sumBagPotionCount(array $bagSlots): int
    {
        $sum = 0;
        foreach ($bagSlots as $slot) {
            if (is_array($slot) && ($slot['item_key'] ?? '') === GameConfig::HEALING_POTION_KEY) {
                $sum += max(0, (int) ($slot['qty'] ?? 0));
            }
        }

        return max(0, min(GameConfig::HEALING_POTION_MAX_TOTAL, $sum));
    }

    /**
     * @return array{slot_1: string|null, slot_2: string|null}
     */
    private function normalizeQuickSlots(mixed $rawQuickSlots): array
    {
        $slot1 = null;
        $slot2 = null;

        if (is_array($rawQuickSlots)) {
            if (($rawQuickSlots['slot_1'] ?? null) === GameConfig::HEALING_POTION_KEY) {
                $slot1 = GameConfig::HEALING_POTION_KEY;
            }
            if (($rawQuickSlots['slot_2'] ?? null) === GameConfig::HEALING_POTION_KEY) {
                $slot2 = GameConfig::HEALING_POTION_KEY;
            }
        }

        return [
            'slot_1' => $slot1,
            'slot_2' => $slot2,
        ];
    }

    public function invalidateActiveRunForCustomer(string $customerId): void
    {
        if ($customerId !== '') {
            Cache::forget($this->runCacheKey($customerId));
            $this->clearActiveLifeNonce($customerId);
        }
    }

    /**
     * @param array<int, int> $clearedLevels
     */
    private function computeMaxSequentialCleared(array $clearedLevels): int
    {
        $maxSequential = 0;
        for ($lvl = 1; $lvl <= self::MAX_MAGMA_LEVEL; $lvl++) {
            if (in_array($lvl, $clearedLevels, true)) {
                $maxSequential = $lvl;
            } else {
                break;
            }
        }

        return $maxSequential;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function ensureRunCommittedInDb(string $customerId, string $runId, array &$run, string $cacheKey): void
    {
        $wasCommitted = !empty($run['committed']);
        $run['committed'] = true;
        $this->refreshRunTtl($cacheKey, $run);

        if (!$wasCommitted && Schema::hasTable('customer_gamifications')) {
            CustomerGamification::where('customer_id', $customerId)
                ->update([
                    'magma_active_run_id' => $runId,
                    'magma_run_committed' => true,
                ]);
        }
    }

    private function storeActiveLifeNonce(string $customerId, string $lifeNonce): void
    {
        Cache::put($this->lifeNonceKey($customerId), $lifeNonce, Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));
    }

    private function clearActiveLifeNonce(string $customerId): void
    {
        Cache::forget($this->lifeNonceKey($customerId));
    }

    private function verifyRunLifeNonce(string $customerId, string $runLifeNonce, ?string $requestLifeNonce): bool
    {
        if ($runLifeNonce === '') {
            return false;
        }

        if ($requestLifeNonce !== null && $requestLifeNonce !== '' && !hash_equals($runLifeNonce, $requestLifeNonce)) {
            return false;
        }

        $authoritativeNonce = Cache::get($this->lifeNonceKey($customerId));
        if (is_string($authoritativeNonce) && $authoritativeNonce !== '' && !hash_equals($authoritativeNonce, $runLifeNonce)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $rawLevels
     * @return array<int, int>
     */
    private function normalizeLevelList(mixed $rawLevels): array
    {
        if (!is_array($rawLevels)) {
            return [];
        }

        $valid = [];
        foreach ($rawLevels as $val) {
            if (is_int($val) || (is_string($val) && ctype_digit($val))) {
                $lvl = (int) $val;
                if ($lvl >= 1 && $lvl <= self::MAX_MAGMA_LEVEL) {
                    $valid[$lvl] = $lvl;
                }
            }
        }

        ksort($valid, SORT_NUMERIC);

        return array_values($valid);
    }

    private function isOrderRevoked(OrderOrder $order): bool
    {
        $candidates = LobbyInstanceService::orderNumberRevocationCandidates((string) ($order->order_number ?? ''));
        if (empty($candidates)) {
            return false;
        }

        return OrderRevocation::whereIn('order_number', $candidates)
            ->whereIn('status', ['processed', 'approved', 'completed'])
            ->exists();
    }

    /**
     * @param array<string, mixed> $run
     */
    private function refreshRunTtl(string $cacheKey, array $run): void
    {
        Cache::put($cacheKey, $run, Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function retryableRunResponse(string $message, int $currentStage, int $waitMs): array
    {
        return [
            'status' => 422,
            'body' => [
                'success' => false,
                'retryable' => true,
                'wait_ms' => max(0, $waitMs),
                'current_stage' => max(0, $currentStage),
                'message' => $message,
            ],
        ];
    }

    private function computeClaimToken(
        string $customerId,
        string $runId,
        int $level,
        string $lifeNonce,
        int $cp1AtMs,
        int $cp2AtMs,
        string $secret
    ): string {
        return hash_hmac(
            'sha256',
            $customerId . '|' . $runId . '|lvl' . $level . '|nonce:' . $lifeNonce . '|cp2|' . $cp1AtMs . '|' . $cp2AtMs,
            $secret
        );
    }

    /**
     * @param Closure(): array{status: int, body: array<string, mixed>} $callback
     * @return array{status: int, body: array<string, mixed>}
     */
    private function withCustomerParkourLock(string $customerId, Closure $callback): array
    {
        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            try {
                return Cache::lock($this->claimLockKey($customerId), 10)->block(3, $callback);
            } catch (LockTimeoutException) {
                return [
                    'status' => 409,
                    'body' => [
                        'success' => false,
                        'retryable' => true,
                        'wait_ms' => 200,
                        'message' => 'Anfrage wird bereits verarbeitet.',
                    ],
                ];
            }
        }

        return $callback();
    }

    /**
     * @param array<string, mixed> $context
     * @return array{status: int, body: array<string, mixed>}
     */
    private function destroyRunResponse(
        string $customerId,
        string $reason = 'invalid_run',
        array $context = [],
        string $message = 'Ungültiger Lauf.'
    ): array {
        Cache::forget($this->runCacheKey($customerId));
        $this->clearActiveLifeNonce($customerId);
        $this->logCheatAttempt($customerId, $reason, $context);

        return [
            'status' => 422,
            'body' => [
                'success' => false,
                'retryable' => false,
                'current_stage' => 0,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logCheatAttempt(string $customerId, string $reason, array $context = []): void
    {
        LobbyInstanceService::logTamperThrottled(
            'customer:' . $customerId,
            'funkenwerk-welt/lava-parkour',
            $reason,
            $context
        );
    }

    private function runCacheKey(string $customerId): string
    {
        return 'sf_lava_run_' . $customerId;
    }

    private function lifeNonceKey(string $customerId): string
    {
        return 'sf_lava_nonce_' . $customerId;
    }

    private function lastHitMetaKey(string $customerId): string
    {
        return 'sf_lava_last_hit_' . $customerId;
    }

    private function claimLockKey(string $customerId): string
    {
        return 'sf_lava_claim_lock_' . $customerId;
    }

    private function usedTokenKey(string $claimToken): string
    {
        return 'sf_lava_used_tok_' . hash('sha256', $claimToken);
    }
}
