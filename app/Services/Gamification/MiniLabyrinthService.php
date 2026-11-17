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

class MiniLabyrinthService
{
    public const DAILY_REWARD = 3;
    public const TIMEZONE = 'Europe/Berlin';
    public const RUN_TTL_MINUTES = 15;
    public const MAX_RUN_DURATION_MS = 900000;
    public const EVENT_TYPE = 'mini_labyrinth_gag';

    public const ENTRANCE_X = -15.0;
    public const ENTRANCE_Z = 0.0;

    public const CP1_X = -22.0;
    public const CP1_Z = 0.0;
    public const CP1_MAX_DIST = 5.5;
    public const CP1_MIN_Y = -0.5;
    public const CP1_MAX_Y = 4.5;
    public const MIN_CP1_ELAPSED_MS = 1600;

    public const GOAL_X = -28.5;
    public const GOAL_Z = 0.0;
    public const GOAL_MAX_DIST = 4.8;
    public const GOAL_MIN_Y = -0.5;
    public const GOAL_MAX_Y = 4.5;
    public const MIN_CLAIM_AFTER_CP1_MS = 1600;
    public const MIN_TOTAL_RUN_MS = 3500;

    /**
     * Drei kompakte, spassige und garantiert loesbare 5x5-Mini-Grundrisse fuer den West-Fluegel (-X).
     * 0 = begehbarer Pfad, 1 = kleiner Hecken-/Mauerblock.
     * In allen 3 Varianten sind Eingang (2,0), Zwischenpunkt CP1 (2,2) und Ziel-Pokal (2,4) verbunden.
     *
     * @var array<int, array<int, array<int, int>>>
     */
    public const GRID_VARIANTS = [
        0 => [
            [1, 0, 0, 1, 1],
            [0, 0, 0, 1, 0],
            [0, 1, 0, 1, 0],
            [0, 1, 0, 0, 0],
            [1, 1, 1, 0, 1],
        ],
        1 => [
            [1, 1, 1, 0, 1],
            [0, 1, 0, 0, 0],
            [0, 1, 0, 1, 0],
            [0, 0, 0, 1, 0],
            [1, 0, 0, 1, 1],
        ],
        2 => [
            [0, 0, 0, 0, 0],
            [0, 1, 0, 1, 0],
            [0, 1, 0, 1, 0],
            [1, 1, 0, 1, 0],
            [1, 0, 0, 0, 0],
        ],
    ];

    public function __construct(
        private readonly GamificationService $gamificationService
    ) {
    }

    /**
     * Deterministischer Tages-Varianten-Index (0..2), rotiert jeden Kalendertag.
     */
    public function dailyVariant(?string $date = null): int
    {
        $targetDate = ($date !== null && trim($date) !== '')
            ? trim($date)
            : Carbon::now(self::TIMEZONE)->toDateString();

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $targetDate, 'UTC');
            if ($parsed !== false) {
                $dayNumber = intdiv((int) $parsed->startOfDay()->getTimestamp(), 86400);

                return (($dayNumber % 3) + 3) % 3;
            }
        } catch (\Throwable) {
            // Fallback auf deterministischen Hash bei unueblicher Datumsangabe
        }

        return abs(crc32($targetDate)) % 3;
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function getVariantLayout(int $variant): array
    {
        $normalized = (($variant % 3) + 3) % 3;

        return self::GRID_VARIANTS[$normalized];
    }

    public function hasClaimedToday(Customer $customer, ?CustomerGamification $profile = null): bool
    {
        $today = Carbon::now(self::TIMEZONE)->toDateString();

        $gamification = $profile;
        if ($gamification === null && Schema::hasTable('customer_gamifications')) {
            $gamification = CustomerGamification::where('customer_id', $customer->id)->first();
        }

        if ($gamification !== null && is_array($gamification->titles_progress)) {
            if (($gamification->titles_progress['_mini_labyrinth_last_claim_date'] ?? null) === $today) {
                return true;
            }
        }

        if (Schema::hasTable('customer_gamification_logs')) {
            $appTz = (string) config('app.timezone', 'UTC');
            $startOfDayDbTz = Carbon::now(self::TIMEZONE)->startOfDay()->setTimezone($appTz);

            $exists = CustomerGamificationLog::where('customer_id', $customer->id)
                ->where('event_type', self::EVENT_TYPE)
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
        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $variant = $this->dailyVariant($today);

        return [
            'enabled' => true,
            'daily_reward' => self::DAILY_REWARD,
            'variant' => $variant,
            'grid' => $this->getVariantLayout($variant),
            'is_authenticated' => $customer !== null,
            'claimed_today' => $customer !== null ? $this->hasClaimedToday($customer) : false,
            'entrance' => [
                'x' => self::ENTRANCE_X,
                'z' => self::ENTRANCE_Z,
            ],
            'cp1' => [
                'x' => self::CP1_X,
                'z' => self::CP1_Z,
            ],
            'goal' => [
                'x' => self::GOAL_X,
                'z' => self::GOAL_Z,
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

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $variant = $this->dailyVariant($today);
        $runId = Str::random(40);
        $secret = Str::random(48);
        $nowMs = Carbon::now()->getTimestampMs();

        Cache::put(
            $this->runCacheKey((string) $customer->id),
            [
                'run_id' => $runId,
                'secret' => $secret,
                'date' => $today,
                'variant' => $variant,
                'started_at_ms' => $nowMs,
                'cp1_at_ms' => null,
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
                'variant' => $variant,
                'grid' => $this->getVariantLayout($variant),
                'daily_reward' => self::DAILY_REWARD,
                'claimed_today' => $this->hasClaimedToday($customer, $gamification),
                'entrance' => [
                    'x' => self::ENTRANCE_X,
                    'z' => self::ENTRANCE_Z,
                ],
                'cp1' => [
                    'x' => self::CP1_X,
                    'z' => self::CP1_Z,
                ],
                'goal' => [
                    'x' => self::GOAL_X,
                    'z' => self::GOAL_Z,
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function recordCheckpoint(
        Customer $customer,
        string $runId,
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

        $currentStage = (int) ($run['stage'] ?? -1);
        if ($currentStage === 1 && !empty($run['claim_token']) && is_string($run['claim_token'])) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'claim_token' => $run['claim_token'],
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

        $secret = (string) ($run['secret'] ?? '');
        $date = (string) ($run['date'] ?? Carbon::now(self::TIMEZONE)->toDateString());
        if ($secret === '' || $date === '') {
            return $this->invalidRunResponse((string) $customer->id);
        }

        $claimToken = $this->computeClaimToken((string) $customer->id, $runId, $date, $secret);
        $run['stage'] = 1;
        $run['cp1_at_ms'] = $nowMs;
        $run['claim_token'] = $claimToken;

        Cache::put($cacheKey, $run, Carbon::now()->addMinutes(self::RUN_TTL_MINUTES));

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'claim_token' => $claimToken,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function claimReward(
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
            $today = Carbon::now(self::TIMEZONE)->toDateString();

            if (
                !is_array($run)
                || !is_string($run['run_id'] ?? null)
                || $runId === ''
                || !hash_equals($run['run_id'], $runId)
                || (int) ($run['stage'] ?? -1) !== 1
                || empty($run['claim_token'])
                || !is_string($run['claim_token'])
                || $claimToken === ''
                || !is_string($run['secret'] ?? null)
                || ($run['secret'] ?? '') === ''
                || !is_string($run['date'] ?? null)
                || $run['date'] !== $today
                || !is_int($run['started_at_ms'] ?? null)
                || !is_int($run['cp1_at_ms'] ?? null)
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

            $expectedHmac = $this->computeClaimToken(
                (string) $customer->id,
                $runId,
                $today,
                (string) $run['secret']
            );

            if (!hash_equals($expectedHmac, $claimToken) || !hash_equals($run['claim_token'], $claimToken)) {
                Cache::forget($cacheKey);

                return [
                    'status' => 422,
                    'body' => [
                        'success' => false,
                        'message' => 'Ungültiger Lauf.',
                    ],
                ];
            }

            $dist = hypot($x - self::GOAL_X, $z - self::GOAL_Z);
            if ($dist > self::GOAL_MAX_DIST || $y < self::GOAL_MIN_Y || $y > self::GOAL_MAX_Y) {
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
            $cp1AtMs = (int) $run['cp1_at_ms'];
            $sinceCp1Ms = $nowMs - $cp1AtMs;
            $totalDurationMs = $nowMs - $startedAtMs;

            if (
                $sinceCp1Ms < self::MIN_CLAIM_AFTER_CP1_MS
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

            $variant = (int) ($run['variant'] ?? $this->dailyVariant($today));

            // Single-Use: Run sofort entwerten, bevor DB-Transaktion startet (Replay ausgeschlossen)
            Cache::forget($cacheKey);

            return DB::transaction(function () use ($customer, $totalDurationMs, $today, $variant): array {
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
                            'sparks_awarded' => 0,
                            'new_balance' => (int) $gam->funken_balance,
                            'already_claimed' => true,
                            'claimed_today' => true,
                            'message' => 'Heute bereits gesichert. Morgen warten wieder 3 Bonus-Funken!',
                        ],
                    ];
                }

                $gam->funken_balance = (int) $gam->funken_balance + self::DAILY_REWARD;
                $gam->funken_total_earned = (int) $gam->funken_total_earned + self::DAILY_REWARD;
                $this->gamificationService->syncLevelFromLifetimeSparks($gam);

                $titlesProgress = is_array($gam->titles_progress) && !empty($gam->titles_progress)
                    ? $gam->titles_progress
                    : $this->gamificationService->getInitialTitlesProgress();
                $titlesProgress['funkenkoenig'] = (int) $gam->funken_total_earned;
                $titlesProgress['_mini_labyrinth_last_claim_date'] = $today;
                $titlesProgress['_mini_labyrinth_total_claims'] = (int) ($titlesProgress['_mini_labyrinth_total_claims'] ?? 0) + 1;
                $gam->titles_progress = $titlesProgress;
                $gam->save();

                CustomerGamificationLog::create([
                    'customer_id' => (string) $customer->id,
                    'event_type' => self::EVENT_TYPE,
                    'category' => 'labyrinth',
                    'title' => 'Mini-Labyrinth Bonus',
                    'description' => 'Mini-Labyrinth Gag gemeistert (+3 Funken)',
                    'sparks_delta' => self::DAILY_REWARD,
                    'balance_after' => (int) $gam->funken_balance,
                    'metadata' => [
                        'run_duration_ms' => $totalDurationMs,
                        'variant' => $variant,
                        'date' => $today,
                    ],
                ]);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'sparks_awarded' => self::DAILY_REWARD,
                        'rewarded_sparks' => self::DAILY_REWARD,
                        'new_balance' => (int) $gam->funken_balance,
                        'new_level' => (int) $gam->level,
                        'already_claimed' => false,
                        'claimed_today' => true,
                        'message' => '🎉 +3 Funken aus dem Mini-Labyrinth gesichert!',
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

    public function invalidateRun(?Customer $customer): void
    {
        if ($customer !== null) {
            Cache::forget($this->runCacheKey((string) $customer->id));
        }
    }

    private function computeClaimToken(string $customerId, string $runId, string $date, string $runSecret): string
    {
        $appKey = (string) (config('app.key') ?: 'seelenfunke_mini_labyrinth_hmac');

        return hash_hmac('sha256', $customerId . '|' . $runId . '|' . $date, $appKey . '|' . $runSecret);
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
        return 'sf_mini_labyrinth_run_' . $customerId;
    }

    private function claimLockKey(string $customerId): string
    {
        return 'sf_mini_labyrinth_claim_lock_' . $customerId;
    }
}
