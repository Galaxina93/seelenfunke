<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\LobbyInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class FunkenwerkHeroesController extends Controller
{
    /**
     * DSGVO-konformer Zero-PII Endpunkt für die 3D-Wasserstadt „Funkenwerk“ & „Halle der Helden“.
     * Liefert ausschließlich aktive Kunden mit ausdrücklichem ranking_opt_in = true.
     * Gibt NIEMALS E-Mail-Adressen, volle Nachnamen, Adressen, Kunden-UUIDs oder Bestellnummern preis.
     */
    public function index(Request $request, GamificationService $gameService): JsonResponse
    {
        $gameService->ensureHangarColumns();

        $allLevels = GameConfig::getFunkiLevels();
        $levelModels = [];
        foreach ($allLevels as $lvl => $cfg) {
            if (!empty($cfg['model_path'])) {
                $levelModels[(int) $lvl] = '/' . ltrim((string) $cfg['model_path'], '/');
            }
        }

        /** @var Customer|null $loggedInCustomer */
        $loggedInCustomer = Auth::guard('customer')->user();
        $customerLoggedIn = $loggedInCustomer !== null;
        $customerOptIn = false;

        if ($loggedInCustomer && Schema::hasTable('customer_gamifications')) {
            $ownProfile = CustomerGamification::where('customer_id', $loggedInCustomer->id)->first();
            $customerOptIn = (bool) ($ownProfile && $ownProfile->is_active && $ownProfile->ranking_opt_in);
        }

        if (!Schema::hasTable('customer_gamifications')) {
            return response()->json([
                'ok' => true,
                'success' => true,
                'founding_era' => true,
                'total_heroes' => 0,
                'my_hero_slot' => null,
                'customer_logged_in' => $customerLoggedIn,
                'customer_opt_in' => false,
                'level_models' => $levelModels,
                'heroes' => $this->padEmptyHeroSlots([], 10),
            ], 200, $this->securityHeaders());
        }

        $query = CustomerGamification::with(['customer:id,first_name,last_name'])
            ->where('ranking_opt_in', true)
            ->where('is_active', true)
            ->whereHas('customer')
            ->orderByDesc('level');

        if (Schema::hasColumn('customer_gamifications', 'galactic_conquest_points')) {
            $query->orderByDesc('galactic_conquest_points');
        }
        if (Schema::hasColumn('customer_gamifications', 'funken_total_earned')) {
            $query->orderByDesc('funken_total_earned');
        }

        $profiles = $query
            ->orderBy('updated_at', 'asc')
            ->limit(500)
            ->get();

        $levelTitles = GameConfig::getLevelTitles();
        $heroes = [];
        $myHeroSlot = null;
        $slot = 0;

        foreach ($profiles as $profile) {
            if (!$profile->customer) {
                continue;
            }

            $slot++;
            $sparksTotal = max(0, (int) ($profile->funken_total_earned ?? 0));
            $level = min(
                GameConfig::MAX_LEVEL,
                max(1, (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks($sparksTotal)))
            );
            $levelCfg = GameConfig::getLevelConfig($level);
            $modelPath = !empty($levelCfg['model_path']) ? (string) $levelCfg['model_path'] : ('shop/customer/gamification/models/funki_lvl_1_rags.glb');

            $displayName = $this->sanitizeZeroPiiDisplayName(
                $gameService->resolvePublicDisplayName($profile->customer, $profile),
                $slot,
                $profile->customer->last_name
            );

            $isMe = $loggedInCustomer !== null && (string) $loggedInCustomer->id === (string) $profile->customer_id;
            if ($isMe) {
                $myHeroSlot = $slot;
            }

            $rankClass = $this->resolveRankClass($level);
            $shipClass = LobbyInstanceService::resolveShipClassForProfile($profile, $level);
            $titlesCount = LobbyInstanceService::resolveTitlesCountForProfile($profile, $level);
            $levelTitle = LobbyInstanceService::cleanZeroDashText(
                (string) ($levelTitles[$level]['name'] ?? ('Stufe ' . $level))
            );
            $aura = $this->resolveHeroAura($profile, $level, $slot);

            $heroes[] = [
                'slot' => $slot,
                'occupied' => true,
                'is_placeholder' => false,
                'display_name' => $displayName,
                'level' => $level,
                'level_title' => $levelTitle,
                'rank_class' => $rankClass,
                'ship_class' => $shipClass,
                'titles_count' => $titlesCount,
                'sparks' => $sparksTotal,
                'sparks_total' => $sparksTotal,
                'sparks_formatted' => number_format($sparksTotal, 0, ',', '.'),
                'aura' => $aura,
                'model_url' => asset($modelPath),
                'is_me' => $isMe,
            ];
        }

        $totalHeroes = count($heroes);
        $heroes = $this->padEmptyHeroSlots($heroes, 10);

        return response()->json([
            'ok' => true,
            'success' => true,
            'founding_era' => $totalHeroes < 48,
            'total_heroes' => $totalHeroes,
            'my_hero_slot' => $myHeroSlot,
            'customer_logged_in' => $customerLoggedIn,
            'customer_opt_in' => $customerOptIn,
            'level_models' => $levelModels,
            'heroes' => $heroes,
        ], 200, $this->securityHeaders());
    }

    /**
     * Fuellt mindestens die ersten 10 Ehrenplaetze auf, damit das 3D-Frontend sowohl belegte als auch freie Sockel kennt.
     *
     * @param array<int, array<string, mixed>> $occupiedHeroes
     * @return array<int, array<string, mixed>>
     */
    private function padEmptyHeroSlots(array $occupiedHeroes, int $minSlots = 10): array
    {
        $heroes = $occupiedHeroes;
        for ($emptySlot = count($occupiedHeroes) + 1; $emptySlot <= $minSlots; $emptySlot++) {
            $heroes[] = [
                'slot' => $emptySlot,
                'occupied' => false,
                'is_placeholder' => true,
                'display_name' => null,
                'level' => null,
                'level_title' => null,
                'rank_class' => null,
                'ship_class' => null,
                'titles_count' => 0,
                'sparks' => 0,
                'sparks_total' => 0,
                'sparks_formatted' => '0',
                'aura' => null,
                'model_url' => null,
                'is_me' => false,
            ];
        }

        return $heroes;
    }

    /**
     * Garantiert 100 % Zero-PII: Keine E-Mail-Adressen, keine Nachnamen, keine langen Klartextdaten.
     */
    private function sanitizeZeroPiiDisplayName(string $candidate, int $slot, ?string $rawLastName = null): string
    {
        $clean = LobbyInstanceService::cleanZeroDashText($candidate);

        if ($clean === '' || str_contains($clean, '@')) {
            return 'Funken-Held #' . $slot;
        }

        $lastNameLower = mb_strtolower(trim((string) $rawLastName), 'UTF-8');
        if ($lastNameLower !== '' && mb_strlen($lastNameLower) >= 3 && str_contains(mb_strtolower($clean, 'UTF-8'), $lastNameLower)) {
            return 'Funken-Held #' . $slot;
        }

        return mb_substr($clean, 0, 28);
    }

    private function resolveRankClass(int $level): string
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

    private function resolveHeroAura(CustomerGamification $profile, int $level, int $slot): string
    {
        $rawAura = strtolower(trim((string) ($profile->active_hall_aura ?? '')));
        if (in_array($rawAura, ['gold', 'sapphire', 'crystal', 'bronze', 'emerald'], true)) {
            return $rawAura;
        }

        if ($slot <= 3 || $level >= 8) {
            return 'gold';
        }
        if ($level >= 5) {
            return 'crystal';
        }

        return 'bronze';
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
