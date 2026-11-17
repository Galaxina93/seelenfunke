<?php

namespace App\Services\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
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
    public const DAILY_REWARD = 5;
    public const TIMEZONE = 'Europe/Berlin';
    public const RUN_TTL_MINUTES = 15;
    public const MAX_RUN_DURATION_MS = 900000;

    public const MIN_CP1_ELAPSED_MS = 1400;
    public const MIN_CP2_ELAPSED_MS = 1600;
    public const MIN_CLAIM_AFTER_CP2_MS = 1400;
    public const MIN_TOTAL_RUN_MS = 4800;

    public const CP1_X = 31.5;
    public const CP1_Z = -2.0;
    public const CP1_MAX_DIST = 5.0;
    public const CP1_MIN_Y = -0.5;
    public const CP1_MAX_Y = 7.5;

    public const CP2_X = 51.0;
    public const CP2_Z = -2.0;
    public const CP2_MAX_DIST = 5.0;
    public const CP2_MIN_Y = 0.0;
    public const CP2_MAX_Y = 8.0;

    public const CHEST_X = 66.5;
    public const CHEST_Y = 3.2;
    public const CHEST_Z = -2.0;
    public const CHEST_MAX_DIST = 5.2;
    public const CHEST_MIN_Y = 1.4;
    public const CHEST_MAX_Y = 8.0;

    public function __construct(
        private readonly GamificationService $gamificationService
    ) {
    }

    public function hasClaimedToday(Customer $customer, ?CustomerGamification $profile = null): bool
    {
        $today = Carbon::now(self::TIMEZONE)->toDateString();

        $gamification = $profile;
        if ($gamification === null && Schema::hasTable('customer_gamifications')) {
            $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        }

        if ($gamification !== null && is_array($gamification->titles_progress)) {
            if (($gamification->titles_progress['_lava_chest_last_claim_date'] ?? null) === $today) {
                return true;
            }
        }

        if (Schema::hasTable('customer_gamification_logs')) {
            $appTz = (string) config('app.timezone', 'UTC');
            $startOfDayDbTz = Carbon::now(self::TIMEZONE)->startOfDay()->setTimezone($appTz);

            $exists = CustomerGamificationLog::where('customer_id', $customer->id)
                ->where('event_type', 'lava_parkour_chest')
                ->where('created_at', '>=', $startOfDayDbTz)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildShowroomPayload(?Customer $customer): array
    {
        return [
            'enabled' => true,
            'daily_reward' => self::DAILY_REWARD,
            'is_authenticated' => $customer !== null,
            'claimed_today' => $customer !== null ? $this->hasClaimedToday($customer) : false,
            'checkpoints' => [
                ['stage' => 1, 'x' => self::CP1_X, 'z' => self::CP1_Z],
                ['stage' => 2, 'x' => self::CP2_X, 'z' => self::CP2_Z],
            ],
            'chest_pos' => [
                'x' => self::CHEST_X,
                'y' => self::CHEST_Y,
                'z' => self::CHEST_Z,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function startRun(Customer $customer): array
    {
        $gamification = $this->gamificationService->getProfile($customer);
        if (!$gamification->is_active) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel im Kundenbereich.',
                ],
            ];
        }

        $customerId = (string) $customer->id;

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $gamification): array {
            $runId = Str::random(40);
            $secret = Str::random(48);
            $nowMs = Carbon::now()->getTimestampMs();

            Cache::put(
                $this->runCacheKey($customerId),
                [
                    'customer_id' => $customerId,
                    'run_id' => $runId,
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
                    'claimed_today' => $this->hasClaimedToday($customer, $gamification),
                    'daily_reward' => self::DAILY_REWARD,
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
        float $x,
        float $y,
        float $z
    ): array {
        $gamification = $this->gamificationService->getProfile($customer);
        if (!$gamification->is_active) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel im Kundenbereich.',
                ],
            ];
        }

        $customerId = (string) $customer->id;

        if (
            !is_finite($x)
            || !is_finite($y)
            || !is_finite($z)
            || abs($x) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($y) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($z) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
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

        return $this->withCustomerParkourLock($customerId, function () use ($customerId, $runId, $stage, $x, $y, $z): array {
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
                $dist = hypot($x - self::CP1_X, $z - self::CP1_Z);
                if ($dist > self::CP1_MAX_DIST || $y < self::CP1_MIN_Y || $y > self::CP1_MAX_Y) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'cp1_coordinates_violation', [
                        'dist' => round($dist, 2),
                        'y' => round($y, 2),
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
                        'stage' => 1,
                        'claim_token' => null,
                    ],
                ];
            }

            // Stage 2
            $dist = hypot($x - self::CP2_X, $z - self::CP2_Z);

            if ($currentStage === 2 && !empty($run['claim_token']) && is_string($run['claim_token'])) {
                if ($dist > self::CP2_MAX_DIST || $y < self::CP2_MIN_Y || $y > self::CP2_MAX_Y) {
                    $this->refreshRunTtl($cacheKey, $run);
                    $this->logCheatAttempt($customerId, 'cp2_retry_coordinates_violation', [
                        'dist' => round($dist, 2),
                        'y' => round($y, 2),
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
                ]);

                return $this->retryableRunResponse(
                    'Bitte passiere zuerst den ersten Checkpoint.',
                    $currentStage,
                    0
                );
            }

            if ($dist > self::CP2_MAX_DIST || $y < self::CP2_MIN_Y || $y > self::CP2_MAX_Y) {
                $this->refreshRunTtl($cacheKey, $run);
                $this->logCheatAttempt($customerId, 'cp2_coordinates_violation', [
                    'dist' => round($dist, 2),
                    'y' => round($y, 2),
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

            $claimToken = $this->computeClaimToken($customerId, $runId, $cp1AtMs, $nowMs, $secret);
            $run['stage'] = 2;
            $run['cp2_at_ms'] = $nowMs;
            $run['claim_token'] = $claimToken;

            $this->refreshRunTtl($cacheKey, $run);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'stage' => 2,
                    'claim_token' => $claimToken,
                ],
            ];
        });
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function claimChest(
        Customer $customer,
        string $runId,
        string $claimToken,
        float $x,
        float $y,
        float $z
    ): array {
        $gamification = $this->gamificationService->getProfile($customer);
        if (!$gamification->is_active) {
            return [
                'status' => 403,
                'body' => [
                    'success' => false,
                    'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel im Kundenbereich.',
                ],
            ];
        }

        $customerId = (string) $customer->id;

        if (
            !is_finite($x)
            || !is_finite($y)
            || !is_finite($z)
            || abs($x) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($y) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
            || abs($z) > LobbyInstanceService::EXTREME_COORD_THRESHOLD
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

        return $this->withCustomerParkourLock($customerId, function () use ($customer, $customerId, $runId, $claimToken, $x, $y, $z): array {
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
                (int) $run['cp1_at_ms'],
                (int) $run['cp2_at_ms'],
                (string) $run['secret']
            );

            if (!hash_equals($expectedToken, $claimToken)) {
                return $this->destroyRunResponse($customerId, 'claim_hmac_mismatch');
            }

            $dist = hypot($x - self::CHEST_X, $z - self::CHEST_Z);
            if ($dist > self::CHEST_MAX_DIST || $y < self::CHEST_MIN_Y || $y > self::CHEST_MAX_Y) {
                $this->refreshRunTtl($cacheKey, $run);
                $this->logCheatAttempt($customerId, 'chest_coordinates_violation', [
                    'dist' => round($dist, 2),
                    'y' => round($y, 2),
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

            return DB::transaction(function () use ($customer, $totalDurationMs): array {
                $gam = CustomerGamification::where('customer_id', $customer->id)->lockForUpdate()->first();
                if ($gam === null || !$gam->is_active) {
                    return [
                        'status' => 403,
                        'body' => [
                            'success' => false,
                            'message' => 'Bitte aktiviere zuerst das Seelenfunken-Sammelspiel im Kundenbereich.',
                        ],
                    ];
                }

                if ($this->hasClaimedToday($customer, $gam)) {
                    return [
                        'status' => 409,
                        'body' => [
                            'success' => false,
                            'already_claimed' => true,
                            'claimed_today' => true,
                            'message' => 'Heute bereits geöffnet. Morgen warten neue 5 Funken!',
                        ],
                    ];
                }

                $today = Carbon::now(self::TIMEZONE)->toDateString();

                $gam->funken_balance = (int) $gam->funken_balance + self::DAILY_REWARD;
                $gam->funken_total_earned = (int) $gam->funken_total_earned + self::DAILY_REWARD;
                $this->gamificationService->syncLevelFromLifetimeSparks($gam);

                $titlesProgress = is_array($gam->titles_progress) && !empty($gam->titles_progress)
                    ? $gam->titles_progress
                    : $this->gamificationService->getInitialTitlesProgress();
                $titlesProgress['funkenkoenig'] = (int) $gam->funken_total_earned;
                $titlesProgress['_lava_chest_last_claim_date'] = $today;
                $titlesProgress['_lava_chest_total_claims'] = (int) ($titlesProgress['_lava_chest_total_claims'] ?? 0) + 1;
                $gam->titles_progress = $titlesProgress;
                $gam->save();

                CustomerGamificationLog::create([
                    'customer_id' => (string) $customer->id,
                    'event_type' => 'lava_parkour_chest',
                    'category' => 'parkour',
                    'title' => 'Prüfungs-Kammer Schatztruhe',
                    'description' => 'Lava-Parkour gemeistert (+5 Funken)',
                    'sparks_delta' => self::DAILY_REWARD,
                    'balance_after' => (int) $gam->funken_balance,
                    'metadata' => [
                        'run_duration_ms' => $totalDurationMs,
                        'date' => $today,
                    ],
                ]);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'rewarded_sparks' => self::DAILY_REWARD,
                        'new_balance' => (int) $gam->funken_balance,
                        'new_level' => (int) $gam->level,
                        'claimed_today' => true,
                        'message' => '+5 Funken erhalten!',
                    ],
                ];
            });
        });
    }

    public function invalidateActiveRunForCustomer(string $customerId): void
    {
        if ($customerId !== '') {
            Cache::forget($this->runCacheKey($customerId));
        }
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
        int $cp1AtMs,
        int $cp2AtMs,
        string $secret
    ): string {
        return hash_hmac('sha256', $customerId . '|' . $runId . '|cp2|' . $cp1AtMs . '|' . $cp2AtMs, $secret);
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
