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
     * DSGVO-konformer Zero-PII Endpunkt fuer die 3D-Halle der Helden (/funkenwerk-welt/heroes-data).
     * Liefert alle berechtigten aktiven Seelenfunken-Teilnehmer deterministisch sortiert nach hero_score
     * (Lebenszeit-Funken + Magma-Ehrenwertung) und schuetzt Klardaten ohne leere Podeste:
     * - ranking_opt_in = true: gewaehlter Spielername bzw. Vorname + Initial
     * - ranking_opt_in = false + player_name: Pseudonym (0 Klardaten)
     * - ranking_opt_in = false ohne player_name: eigener Vorname nur fuer $isMe, sonst "Funken-Hüter #<Rang>"
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
        $sanctumAccess = LobbyInstanceService::evaluateSanctumAccess($loggedInCustomer instanceof Customer ? $loggedInCustomer : null);

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
                'has_completed_order' => $sanctumAccess['has_completed_order'],
                'gamification_active' => false,
                'can_enter_heroes' => false,
                'can_enter_sanctums' => false,
                'sanctum_block_reason' => $sanctumAccess['sanctum_block_reason'],
                'login_url' => $sanctumAccess['login_url'],
                'dashboard_url' => $sanctumAccess['dashboard_url'],
                'level_models' => $levelModels,
                'heroes' => $this->padEmptyHeroSlots([], 10),
            ], 200, $this->securityHeaders());
        }

        $rankedEntries = LobbyInstanceService::loadRankedHeroEntries(500);
        $levelTitles = GameConfig::getLevelTitles();
        $heroes = [];
        $myHeroSlot = null;
        $slot = 0;

        foreach ($rankedEntries as $entry) {
            /** @var CustomerGamification $profile */
            $profile = $entry['profile'];
            $metrics = $entry['metrics'];

            if (!$profile->customer) {
                continue;
            }

            $slot++;
            $sparksTotal = $metrics['sparks_total'];
            $level = $metrics['level'];
            $levelCfg = GameConfig::getLevelConfig($level);
            $modelPath = !empty($levelCfg['model_path'])
                ? (string) $levelCfg['model_path']
                : 'shop/customer/gamification/models/funki_lvl_1_rags.glb';

            $isMe = $loggedInCustomer !== null && (string) $loggedInCustomer->id === (string) $profile->customer_id;
            if ($isMe) {
                $myHeroSlot = $slot;
            }

            $displayName = LobbyInstanceService::resolveHallOfHeroesDisplayName(
                $profile->customer,
                $profile,
                $slot,
                $isMe,
                $gameService
            );

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
                'hero_score' => $metrics['hero_score'],
                'hero_score_formatted' => number_format($metrics['hero_score'], 0, ',', '.'),
                'magma_highest_level' => $metrics['magma_highest_level'],
                'magma_cleared_count' => $metrics['magma_cleared_count'],
                'magma_prestige_points' => $metrics['magma_prestige_points'],
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
            'has_completed_order' => $sanctumAccess['has_completed_order'],
            'gamification_active' => $sanctumAccess['gamification_active'],
            'can_enter_heroes' => $sanctumAccess['can_enter_sanctums'],
            'can_enter_sanctums' => $sanctumAccess['can_enter_sanctums'],
            'sanctum_block_reason' => $sanctumAccess['sanctum_block_reason'],
            'login_url' => $sanctumAccess['login_url'],
            'dashboard_url' => $sanctumAccess['dashboard_url'],
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
                'hero_score' => 0,
                'hero_score_formatted' => '0',
                'magma_highest_level' => 0,
                'magma_cleared_count' => 0,
                'magma_prestige_points' => 0,
                'aura' => null,
                'model_url' => null,
                'is_me' => false,
            ];
        }

        return $heroes;
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
