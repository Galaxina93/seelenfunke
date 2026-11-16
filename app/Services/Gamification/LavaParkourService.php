<?php

namespace App\Services\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Customer\CustomerGamificationLog;
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

    public const MIN_CP1_ELAPSED_MS = 2800;
    public const MIN_CP2_ELAPSED_MS = 2800;
    public const MIN_CLAIM_AFTER_CP2_MS = 2400;
    public const MIN_TOTAL_RUN_MS = 8500;

    public const CP1_X = 31.5;
    public const CP1_Z = -2.0;
    public const CP1_MAX_DIST = 4.5;
    public const CP1_MIN_Y = -0.5;
    public const CP1_MAX_Y = 5.5;

    public const CP2_X = 51.0;
    public const CP2_Z = -2.0;
    public const CP2_MAX_DIST = 4.5;
    public const CP2_MIN_Y = 0.0;
    public const CP2_MAX_Y = 6.5;

    public const CHEST_X = 66.5;
    public const CHEST_Y = 2.8;
    public const CHEST_Z = -2.0;
    public const CHEST_MAX_DIST = 4.5;
    public const CHEST_MIN_Y = 1.5;
    public const CHEST_MAX_Y = 6.5;

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

        $runId = Str::random(40);
        $secret = Str::random(48);
        $nowMs = Carbon::now()->getTimestampMs();

        Cache::put(
            $this->runCacheKey((string) $customer->id),
            [
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

        if (!is_finite($x) || !is_finite($y) || !is_finite($z) || !in_array($stage, [1, 2], true)) {
            return $this->invalidRunResponse((string) $customer->id);
        }

        $cacheKey = $this->runCacheKey((string) $customer->id);
        $run = Cache::get($cacheKey);

        if (
            !is_array($run)
            || !is_string($run['run_id'] ?? null)
            || $runId === ''
            || !hash_equals($run['run_id'], $runId)
        ) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Ungültiger Lauf.',
                ],
            ];
        }

        $nowMs = Carbon::now()->getTimestampMs();
        $startedAtMs = (int) ($run['started_at_ms'] ?? 0);
        if ($startedAtMs <= 0 || ($nowMs - $startedAtMs) > self::MAX_RUN_DURATION_MS || $nowMs < $startedAtMs) {
            return $this->invalidRunResponse((string) $customer->id);
        }

        if ($stage === 1) {
            $currentStage = (int) ($run['stage'] ?? -1);
            if ($currentStage === 1) {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'stage' => 1,
                        'claim_token' => null,
                    ],
                ];
            }

            if ($currentStage !== 0) {
                return $this->invalidRunResponse((string) $customer->id);
            }

            $dist = hypot($x - self::CP1_X, $z - self::CP1_Z);
            if ($dist > self::CP1_MAX_DIST || $y < self::CP1_MIN_Y || $y > self::CP1_MAX_Y) {
                return $this->invalidRunResponse((string) $customer->id);
            }

            if (($nowMs - $startedAtMs) < self::MIN_CP1_ELAPSED_MS) {
                return $this->invalidRunResponse((string) $customer->id);
            }

            $run['stage'] = 1;
            $run['cp1_at_ms'] = $nowMs;
            Cache::put($cacheKey, $run, Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));

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
        $currentStage = (int) ($run['stage'] ?? -1);
        if ($currentStage === 2 && !empty($run['claim_token']) && is_string($run['claim_token'])) {
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
            return $this->invalidRunResponse((string) $customer->id);
        }

        $dist = hypot($x - self::CP2_X, $z - self::CP2_Z);
        if ($dist > self::CP2_MAX_DIST || $y < self::CP2_MIN_Y || $y > self::CP2_MAX_Y) {
            return $this->invalidRunResponse((string) $customer->id);
        }

        if (($nowMs - $cp1AtMs) < self::MIN_CP2_ELAPSED_MS) {
            return $this->invalidRunResponse((string) $customer->id);
        }

        $secret = (string) ($run['secret'] ?? '');
        if ($secret === '') {
            return $this->invalidRunResponse((string) $customer->id);
        }

        $claimToken = hash_hmac('sha256', $customer->id . '|' . $runId . '|cp2|' . $cp1AtMs, $secret);
        $run['stage'] = 2;
        $run['cp2_at_ms'] = $nowMs;
        $run['claim_token'] = $claimToken;

        Cache::put($cacheKey, $run, Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'stage' => 2,
                'claim_token' => $claimToken,
            ],
        ];
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

        if (!is_finite($x) || !is_finite($y) || !is_finite($z)) {
            return $this->invalidRunResponse((string) $customer->id);
        }

        $executeClaim = function () use ($customer, $runId, $claimToken, $x, $y, $z): array {
            $cacheKey = $this->runCacheKey((string) $customer->id);
            $run = Cache::get($cacheKey);

            if (
                !is_array($run)
                || !is_string($run['run_id'] ?? null)
                || $runId === ''
                || !hash_equals($run['run_id'], $runId)
                || (int) ($run['stage'] ?? -1) !== 2
                || empty($run['claim_token'])
                || !is_string($run['claim_token'])
                || $claimToken === ''
                || !hash_equals($run['claim_token'], $claimToken)
                || !is_int($run['started_at_ms'] ?? null)
                || !is_int($run['cp2_at_ms'] ?? null)
            ) {
                Cache::forget($cacheKey);

                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'Ungültiger Lauf.',
                    ],
                ];
            }

            $dist = hypot($x - self::CHEST_X, $z - self::CHEST_Z);
            if ($dist > self::CHEST_MAX_DIST || $y < self::CHEST_MIN_Y || $y > self::CHEST_MAX_Y) {
                Cache::forget($cacheKey);

                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'Ungültiger Lauf.',
                    ],
                ];
            }

            $nowMs = Carbon::now()->getTimestampMs();
            $startedAtMs = (int) $run['started_at_ms'];
            $cp2AtMs = (int) $run['cp2_at_ms'];
            $sinceCp2Ms = $nowMs - $cp2AtMs;
            $totalDurationMs = $nowMs - $startedAtMs;

            if (
                $sinceCp2Ms < self::MIN_CLAIM_AFTER_CP2_MS
                || $totalDurationMs < self::MIN_TOTAL_RUN_MS
                || $totalDurationMs > self::MAX_RUN_DURATION_MS
            ) {
                Cache::forget($cacheKey);

                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'Ungültiger Lauf.',
                    ],
                ];
            }

            // Single-Use: Run sofort entwerten, bevor DB-Transaktion startet (Replay ausgeschlossen)
            Cache::forget($cacheKey);

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
        };

        $store = Cache::getStore();
        if ($store instanceof LockProvider) {
            try {
                return Cache::lock($this->claimLockKey((string) $customer->id), 10)->block(3, $executeClaim);
            } catch (LockTimeoutException) {
                return [
                    'status' => 409,
                    'body' => [
                        'success' => false,
                        'message' => 'Anfrage wird bereits verarbeitet.',
                    ],
                ];
            }
        }

        return $executeClaim();
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function invalidRunResponse(string $customerId): array
    {
        Cache::forget($this->runCacheKey($customerId));

        return [
            'status' => 422,
            'body' => [
                'success' => false,
                'message' => 'Ungültiger Lauf.',
            ],
        ];
    }

    private function runCacheKey(string $customerId): string
    {
        return 'sf_lava_run_' . $customerId;
    }

    private function claimLockKey(string $customerId): string
    {
        return 'sf_lava_claim_lock_' . $customerId;
    }
}
