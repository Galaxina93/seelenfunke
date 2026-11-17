<?php

namespace App\Services\Gamification;

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

    public const DAILY_REWARD = 5;
    public const DAILY_CAP_FREE = 15;
    public const DAILY_CAP_MASTER = 30;

    public const TIMEZONE = 'Europe/Berlin';
    public const RUN_TTL_MINUTES = 15;
    public const MAX_RUN_DURATION_MS = 900000;

    public const MIN_CP1_ELAPSED_MS = 1200;
    public const MIN_CP2_ELAPSED_MS = 1400;
    public const MIN_CLAIM_AFTER_CP2_MS = 1200;
    public const MIN_TOTAL_RUN_MS = 4200;

    public const ROOM1_ORIGIN_X = 12.0;
    public const ROOM1_ORIGIN_Z = -2.0;

    public const REL_CP1_X = 19.5;
    public const REL_CP1_Z = 0.0;
    public const REL_CP2_X = 39.0;
    public const REL_CP2_Z = 0.0;
    public const REL_CHEST_X = 54.5;
    public const REL_CHEST_Y = 3.2;
    public const REL_CHEST_Z = 0.0;

    public const CP1_X = 31.5;
    public const CP1_Z = -2.0;
    public const CP1_MAX_DIST = 6.0;
    public const CP1_MIN_Y = -1.0;
    public const CP1_MAX_Y = 10.0;

    public const CP2_X = 51.0;
    public const CP2_Z = -2.0;
    public const CP2_MAX_DIST = 6.0;
    public const CP2_MIN_Y = -0.5;
    public const CP2_MAX_Y = 11.0;

    public const CHEST_X = 66.5;
    public const CHEST_Y = 3.2;
    public const CHEST_Z = -2.0;
    public const CHEST_MAX_DIST = 6.0;
    public const CHEST_MIN_Y = 1.0;
    public const CHEST_MAX_Y = 11.0;

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
     * Liefert die statischen Definitionen aller 10 Magma-Level (1..10).
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
     * @return array<string, mixed>
     */
    public function getLevelDefinition(int $level): array
    {
        $clamped = max(1, min(self::MAX_MAGMA_LEVEL, $level));

        return [
            'level' => $clamped,
            'title' => self::LEVEL_TITLES[$clamped],
            'requires_master_key' => $clamped >= self::MASTER_KEY_MIN_LEVEL,
            'first_clear_reward' => self::FIRST_CLEAR_REWARDS[$clamped],
            'daily_reward' => self::DAILY_REWARDS[$clamped],
            'rel_cp1' => [
                'stage' => 1,
                'rel_x' => self::REL_CP1_X,
                'rel_z' => self::REL_CP1_Z,
                'x' => self::REL_CP1_X,
                'z' => self::REL_CP1_Z,
            ],
            'rel_cp2' => [
                'stage' => 2,
                'rel_x' => self::REL_CP2_X,
                'rel_z' => self::REL_CP2_Z,
                'x' => self::REL_CP2_X,
                'z' => self::REL_CP2_Z,
            ],
            'rel_chest' => [
                'rel_x' => self::REL_CHEST_X,
                'rel_y' => self::REL_CHEST_Y,
                'rel_z' => self::REL_CHEST_Z,
                'x' => self::REL_CHEST_X,
                'y' => self::REL_CHEST_Y,
                'z' => self::REL_CHEST_Z,
            ],
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
     *     unlocked_portals: array<int, int>,
     *     first_cleared_levels: array<int, int>,
     *     daily_cleared_levels: array<int, int>,
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
                'unlocked_portals' => [],
                'first_cleared_levels' => [],
                'daily_cleared_levels' => [],
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

        $maxSequential = 0;
        for ($lvl = 1; $lvl <= self::MAX_MAGMA_LEVEL; $lvl++) {
            if (in_array($lvl, $clearedLevels, true)) {
                $maxSequential = $lvl;
            } else {
                break;
            }
        }

        $rawProgressUnlocked = min(self::MAX_MAGMA_LEVEL, $maxSequential + 1);
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
            'unlocked_portals' => $unlockedPortals,
            'first_cleared_levels' => $clearedLevels,
            'daily_cleared_levels' => $dailyClearedLevels,
            'daily_sparks_earned' => $dailySparksEarned,
            'daily_sparks_cap' => $dailyCap,
        ];
    }

    /**
     * Prueft die 3 Voraussetzungen fuer das gesamte Magma-Level (Stufen 1 bis 10, Portal-Hub, Checkpoints & Truhe):
     * 1. Eingeloggter Kunde ('login_required')
     * 2. Mindestens eine bezahlte, nicht stornierte/erstattete Bestellung ('order_required')
     * 3. Seelenfunken-Magie aktiviert ('magic_activation_required')
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
        $state = $this->resolveProgressState($customer);
        $levelsList = array_values($this->getLevelsCatalog());

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
            'unlocked_portals' => $state['unlocked_portals'],
            'first_cleared_levels' => $state['first_cleared_levels'],
            'daily_cleared_levels' => $state['daily_cleared_levels'],
            'daily_sparks_earned' => $state['daily_sparks_earned'],
            'daily_sparks_cap' => $state['daily_sparks_cap'],
            'levels' => $levelsList,
            'checkpoints' => [
                ['stage' => 1, 'x' => self::CP1_X, 'z' => self::CP1_Z, 'rel_x' => self::REL_CP1_X, 'rel_z' => self::REL_CP1_Z],
                ['stage' => 2, 'x' => self::CP2_X, 'z' => self::CP2_Z, 'rel_x' => self::REL_CP2_X, 'rel_z' => self::REL_CP2_Z],
            ],
            'chest_pos' => [
                'x' => self::CHEST_X,
                'y' => self::CHEST_Y,
                'z' => self::CHEST_Z,
                'rel_x' => self::REL_CHEST_X,
                'rel_y' => self::REL_CHEST_Y,
                'rel_z' => self::REL_CHEST_Z,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function startRun(Customer $customer, int $level = 1): array
    {
        if ($denied = $this->checkSanctumAccess($customer)) {
            return $denied;
        }

        if ($level < 1 || $level > self::MAX_MAGMA_LEVEL) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'code' => 'INVALID_LEVEL',
                    'message' => 'Ungültige Magma-Stufe.',
                ],
            ];
        }

        $gamification = $this->gamificationService->getProfile($customer);
        $state = $this->resolveProgressState($customer, $gamification);

        if ($level >= self::MASTER_KEY_MIN_LEVEL && !$state['master_key_unlocked']) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'code' => 'MASTER_KEY_REQUIRED',
                    'master_key_unlocked' => false,
                    'highest_unlocked_level' => $state['highest_unlocked_level'],
                    'gift_voucher_url' => '/produkt/geschenkgutschein',
                    'message' => 'Für Stufe 6 bis 10 wird der Manufaktur-Meisterschlüssel benötigt (ab deiner ersten bezahlten Bestellung oder einem Geschenkgutschein ab 15,00 €).',
                ],
            ];
        }

        if ($level > $state['highest_unlocked_level']) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'code' => 'LEVEL_LOCKED',
                    'highest_unlocked_level' => $state['highest_unlocked_level'],
                    'message' => 'Bitte schließe zuerst Stufe ' . ($level - 1) . ' ab, um Stufe ' . $level . ' freizuschalten.',
                ],
            ];
        }

        $customerId = (string) $customer->id;
        $levelDef = $this->getLevelDefinition($level);
        $isFirstClearAvailable = !in_array($level, $state['first_cleared_levels'], true);
        $isClaimedToday = in_array($level, $state['daily_cleared_levels'], true);

        return $this->withCustomerParkourLock($customerId, function () use (
            $customerId,
            $level,
            $levelDef,
            $isFirstClearAvailable,
            $isClaimedToday,
            $state
        ): array {
            $runId = Str::random(40);
            $secret = Str::random(48);
            $nowMs = Carbon::now()->getTimestampMs();

            Cache::put(
                $this->runCacheKey($customerId),
                [
                    'customer_id' => $customerId,
                    'run_id' => $runId,
                    'level' => $level,
                    'secret' => $secret,
                    'started_at_ms' => $nowMs,
                    'cp1_at_ms' => null,
                    'cp2_at_ms' => null,
                    'stage' => 0,
                    'claim_token' => null,
                ],
                Carbon::now()->addMinutes(self::RUN_TTL_MINUTES)
            );

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'run_id' => $runId,
                    'level' => $level,
                    'title' => $levelDef['title'],
                    'is_first_clear_available' => $isFirstClearAvailable,
                    'claimed_today' => $isClaimedToday,
                    'already_claimed_today' => $isClaimedToday,
                    'first_clear_reward' => $levelDef['first_clear_reward'],
                    'daily_reward' => $isFirstClearAvailable ? $levelDef['first_clear_reward'] : $levelDef['daily_reward'],
                    'repeat_daily_reward' => $levelDef['daily_reward'],
                    'master_key_unlocked' => $state['master_key_unlocked'],
                    'highest_unlocked_level' => $state['highest_unlocked_level'],
                    'unlocked_portals' => $state['unlocked_portals'],
                    'checkpoints' => [
                        'cp1' => [
                            'stage' => 1,
                            'rel_x' => self::REL_CP1_X,
                            'rel_z' => self::REL_CP1_Z,
                        ],
                        'cp2' => [
                            'stage' => 2,
                            'rel_x' => self::REL_CP2_X,
                            'rel_z' => self::REL_CP2_Z,
                        ],
                    ],
                    'chest_pos' => [
                        'rel_x' => self::REL_CHEST_X,
                        'rel_y' => self::REL_CHEST_Y,
                        'rel_z' => self::REL_CHEST_Z,
                    ],
                ],
            ];
        });
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
        ?int $requestedLevel = null
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
            function () use ($customerId, $runId, $stage, $relX, $relY, $relZ, $requestedLevel): array {
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

                if ($stage === 1) {
                    $dist = hypot($relX - self::REL_CP1_X, $relZ - self::REL_CP1_Z);
                    if ($dist > self::CP1_MAX_DIST || $relY < self::CP1_MIN_Y || $relY > self::CP1_MAX_Y) {
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
                        $this->refreshRunTtl($cacheKey, $run);

                        return [
                            'status' => 200,
                            'body' => [
                                'success' => true,
                                'level' => $runLevel,
                                'stage' => $currentStage,
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
                    if ($elapsedCp1Ms < self::MIN_CP1_ELAPSED_MS) {
                        $waitMs = self::MIN_CP1_ELAPSED_MS - $elapsedCp1Ms;
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
                    $this->refreshRunTtl($cacheKey, $run);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'stage' => 1,
                            'claim_token' => null,
                        ],
                    ];
                }

                // Stage 2
                $dist = hypot($relX - self::REL_CP2_X, $relZ - self::REL_CP2_Z);

                if ($currentStage === 2 && !empty($run['claim_token']) && is_string($run['claim_token'])) {
                    if ($dist > self::CP2_MAX_DIST || $relY < self::CP2_MIN_Y || $relY > self::CP2_MAX_Y) {
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

                    $this->refreshRunTtl($cacheKey, $run);

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'stage' => 2,
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

                if ($dist > self::CP2_MAX_DIST || $relY < self::CP2_MIN_Y || $relY > self::CP2_MAX_Y) {
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
                if ($elapsedCp2Ms < self::MIN_CP2_ELAPSED_MS) {
                    $waitMs = self::MIN_CP2_ELAPSED_MS - $elapsedCp2Ms;
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

                $claimToken = $this->computeClaimToken($customerId, $runId, $runLevel, $cp1AtMs, $nowMs, $secret);
                $run['stage'] = 2;
                $run['cp2_at_ms'] = $nowMs;
                $run['claim_token'] = $claimToken;

                $this->refreshRunTtl($cacheKey, $run);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'level' => $runLevel,
                        'stage' => 2,
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
        ?int $requestedLevel = null
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
            function () use ($customer, $customerId, $runId, $claimToken, $relX, $relY, $relZ, $requestedLevel): array {
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
                    (int) $run['cp1_at_ms'],
                    (int) $run['cp2_at_ms'],
                    (string) $run['secret']
                );

                if (!hash_equals($expectedToken, $claimToken)) {
                    return $this->destroyRunResponse($customerId, 'claim_hmac_mismatch');
                }

                $dist = hypot($relX - self::REL_CHEST_X, $relZ - self::REL_CHEST_Z);
                if ($dist > self::CHEST_MAX_DIST || $relY < self::CHEST_MIN_Y || $relY > self::CHEST_MAX_Y) {
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

                if ($cp1SplitMs < self::MIN_CP1_ELAPSED_MS || $cp2SplitMs < self::MIN_CP2_ELAPSED_MS) {
                    return $this->destroyRunResponse($customerId, 'claim_corrupted_split_timestamps');
                }

                $waitAfterCp2Ms = max(0, self::MIN_CLAIM_AFTER_CP2_MS - $sinceCp2Ms);
                $waitTotalMs = max(0, self::MIN_TOTAL_RUN_MS - $totalDurationMs);
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
                                'message' => 'Bitte führe im Kundenbereich zuerst Magie aktivieren aus, um die Halle der Helden oder das Magma-Level zu betreten.',
                            ],
                        ];
                    }

                    $today = Carbon::now(self::TIMEZONE)->toDateString();
                    $masterKeyUnlocked = $this->hasMasterKey($customer);
                    $dailyCap = $masterKeyUnlocked ? self::DAILY_CAP_MASTER : self::DAILY_CAP_FREE;

                    $titlesProgress = is_array($gam->titles_progress) && !empty($gam->titles_progress)
                        ? $gam->titles_progress
                        : $this->gamificationService->getInitialTitlesProgress();

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
                            $dailyLevels[] = $runLevel;
                            $dailyLevels = $this->normalizeLevelList($dailyLevels);
                        } else {
                            $remainingDailyCap = max(0, $dailyCap - $dailySparksEarned);
                            $sparksAwarded = min(self::DAILY_REWARDS[$runLevel], $remainingDailyCap);
                            if ($sparksAwarded > 0) {
                                $dailySparksEarned += $sparksAwarded;
                                $dailyLevels[] = $runLevel;
                                $dailyLevels = $this->normalizeLevelList($dailyLevels);
                            }
                        }
                    }

                    if ($sparksAwarded > 0) {
                        $gam->funken_balance = (int) $gam->funken_balance + $sparksAwarded;
                        $gam->funken_total_earned = (int) $gam->funken_total_earned + $sparksAwarded;
                        $this->gamificationService->syncLevelFromLifetimeSparks($gam);

                        $titlesProgress['funkenkoenig'] = (int) $gam->funken_total_earned;
                        $titlesProgress['_lava_chest_last_claim_date'] = $today;
                        $titlesProgress['_lava_chest_total_claims'] = (int) ($titlesProgress['_lava_chest_total_claims'] ?? 0) + 1;
                    }

                    $titlesProgress['lava_cleared_levels'] = $clearedLevels;
                    $titlesProgress['lava_daily_claims'] = [
                        'date' => $today,
                        'levels' => $dailyLevels,
                        'sparks' => $dailySparksEarned,
                    ];

                    $gam->titles_progress = $titlesProgress;
                    $gam->save();

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

                    $maxSequential = 0;
                    for ($lvl = 1; $lvl <= self::MAX_MAGMA_LEVEL; $lvl++) {
                        if (in_array($lvl, $clearedLevels, true)) {
                            $maxSequential = $lvl;
                        } else {
                            break;
                        }
                    }
                    $rawUnlocked = min(self::MAX_MAGMA_LEVEL, $maxSequential + 1);
                    $highestUnlockedLevel = $masterKeyUnlocked ? $rawUnlocked : min(5, $rawUnlocked);
                    $unlockedPortals = $highestUnlockedLevel >= 2 ? range(2, $highestUnlockedLevel) : [];

                    if ($alreadyClaimedToday) {
                        $message = 'Diese Magma-Truhe wurde heute bereits geöffnet.';
                    } elseif ($sparksAwarded > 0) {
                        $message = '+' . $sparksAwarded . ' Funken erhalten!';
                    } else {
                        $message = 'Tageslimit erreicht (' . $dailyCap . ' Funken pro Tag).';
                    }

                    return [
                        'status' => 200,
                        'body' => [
                            'success' => true,
                            'level' => $runLevel,
                            'sparks_awarded' => $sparksAwarded,
                            'rewarded_sparks' => $sparksAwarded,
                            'is_first_clear' => $isFirstClear,
                            'already_claimed_today' => $alreadyClaimedToday,
                            'claimed_today' => in_array($runLevel, $dailyLevels, true),
                            'funken_balance' => (int) $gam->funken_balance,
                            'new_balance' => (int) $gam->funken_balance,
                            'funken_total_earned' => (int) $gam->funken_total_earned,
                            'player_level' => $playerLevel,
                            'new_level' => $playerLevel,
                            'model_url' => $modelUrl,
                            'master_key_unlocked' => $masterKeyUnlocked,
                            'highest_unlocked_level' => $highestUnlockedLevel,
                            'unlocked_portals' => $unlockedPortals,
                            'first_cleared_levels' => $clearedLevels,
                            'daily_cleared_levels' => $dailyLevels,
                            'daily_sparks_earned' => $dailySparksEarned,
                            'daily_sparks_cap' => $dailyCap,
                            'message' => $message,
                        ],
                    ];
                });
            }
        );
    }

    public function invalidateActiveRunForCustomer(string $customerId): void
    {
        if ($customerId !== '') {
            Cache::forget($this->runCacheKey($customerId));
        }
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
        $rawNumber = trim((string) ($order->order_number ?? ''));
        if ($rawNumber === '') {
            return false;
        }

        $cleanNumber = strtoupper(trim(ltrim((string) preg_replace('/^(bestellung|order|nr\.?|rechnung)\s*/i', '', $rawNumber), '#')));

        $candidates = array_values(array_unique(array_filter([
            $rawNumber,
            $cleanNumber,
            $cleanNumber !== '' ? ('ORD-' . $cleanNumber) : '',
            $cleanNumber !== '' ? ('SF-' . $cleanNumber) : '',
        ])));

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
        int $cp1AtMs,
        int $cp2AtMs,
        string $secret
    ): string {
        return hash_hmac('sha256', $customerId . '|' . $runId . '|lvl' . $level . '|cp2|' . $cp1AtMs . '|' . $cp2AtMs, $secret);
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

    private function claimLockKey(string $customerId): string
    {
        return 'sf_lava_claim_lock_' . $customerId;
    }

    private function usedTokenKey(string $claimToken): string
    {
        return 'sf_lava_used_tok_' . hash('sha256', $claimToken);
    }
}
