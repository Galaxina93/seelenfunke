<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
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
                'success' => true,
                'founding_era' => true,
                'total_heroes' => 0,
                'my_hero_slot' => null,
                'customer_logged_in' => $customerLoggedIn,
                'customer_opt_in' => false,
                'heroes' => [],
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

        $heroes = [];
        $myHeroSlot = null;
        $slot = 0;

        foreach ($profiles as $profile) {
            if (!$profile->customer) {
                continue;
            }

            $slot++;
            $level = min(
                GameConfig::MAX_LEVEL,
                max(1, (int) ($profile->level ?: GameConfig::getLevelForLifetimeSparks((int) ($profile->funken_total_earned ?? 0))))
            );

            $displayName = $this->sanitizeZeroPiiDisplayName(
                $gameService->resolvePublicDisplayName($profile->customer, $profile),
                $slot
            );

            $isMe = $loggedInCustomer !== null && (string) $loggedInCustomer->id === (string) $profile->customer_id;
            if ($isMe) {
                $myHeroSlot = $slot;
            }

            $rankClass = $this->resolveRankClass($level);
            $aura = $this->resolveHeroAura($profile, $level, $slot);

            $heroes[] = [
                'slot' => $slot,
                'display_name' => $displayName,
                'level' => $level,
                'rank_class' => $rankClass,
                'aura' => $aura,
                'is_me' => $isMe,
            ];
        }

        $totalHeroes = count($heroes);

        return response()->json([
            'success' => true,
            'founding_era' => $totalHeroes < 48,
            'total_heroes' => $totalHeroes,
            'my_hero_slot' => $myHeroSlot,
            'customer_logged_in' => $customerLoggedIn,
            'customer_opt_in' => $customerOptIn,
            'heroes' => $heroes,
        ], 200, $this->securityHeaders());
    }

    /**
     * Garantiert 100 % Zero-PII: Keine E-Mail-Adressen, keine Domain-Muster, keine langen Klartextdaten.
     */
    private function sanitizeZeroPiiDisplayName(string $candidate, int $slot): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim($candidate)) ?? '';

        if ($clean === '' || str_contains($clean, '@')) {
            return 'Funken-Held #' . $slot;
        }

        // KI-Gedankenstriche konsequent vermeiden
        $clean = str_replace(' – ', ' · ', $clean);

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
