<?php

namespace App\Services\Gamification;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use Illuminate\Support\Facades\Auth;

class FunkenreichSsoBridgeService
{
    public static function resolveSigningSecret(): string
    {
        $secret = (string) config('services.funkenreich.sso_secret', '');
        if ($secret === '') {
            $secret = (string) config('app.key', '');
        }

        return $secret;
    }

    public static function canCurrentVisitorEnterMmorpg(): bool
    {
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            return $admin instanceof Admin && (!method_exists($admin, 'trashed') || !$admin->trashed());
        }

        if (Auth::guard('customer')->check()) {
            $c = Auth::guard('customer')->user();
            if (!$c instanceof Customer) {
                return false;
            }

            return !$c->trashed()
                && $c->hasVerifiedEmail()
                && !(bool) $c->needs_password_change
                && (bool) $c->mmorpg_access_enabled === true;
        }

        return false;
    }

    public static function buildSignedEntryRedirectUrl(): ?string
    {
        if (!self::canCurrentVisitorEnterMmorpg()) {
            return null;
        }

        $isAdmin = Auth::guard('admin')->check();
        $user = $isAdmin ? Auth::guard('admin')->user() : Auth::guard('customer')->user();
        if (!$user) {
            return null;
        }

        $gamification = (!$isAdmin && $user instanceof Customer) ? $user->gamification : null;
        $displayName = self::resolveDisplayName($user, $gamification, $isAdmin);
        $healingPotions = isset($gamification->healing_potions)
            ? (int) $gamification->healing_potions
            : self::countHealingPotions($gamification);

        $now = time();
        $payload = [
            'iss' => 'mein-seelenfunke',
            'sub_type' => $isAdmin ? 'admin' : 'customer',
            'sub_id' => (string) $user->id,
            'display_name' => $displayName,
            'funki_level' => (int) ($gamification->funki_level ?? ($isAdmin ? 10 : 1)),
            'funken_balance' => (int) ($gamification->funken_balance ?? 0),
            'funken_total_earned' => (int) ($gamification->funken_total_earned ?? 0),
            'magma_unlocked_level' => (int) ($gamification->magma_unlocked_level ?? 1),
            'magma_hearts' => (int) ($gamification->magma_hearts ?? 6),
            'healing_potions' => $healingPotions,
            'mmorpg_access' => true,
            'iat' => $now,
            'exp' => $now + 60,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $b64 = rtrim(strtr(base64_encode((string) json_encode($payload, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $secret = self::resolveSigningSecret();
        $sig = hash_hmac('sha256', $b64, $secret);
        $gameUrl = (string) (config('services.funkenreich.url') ?: 'https://game.mein-seelenfunke.de');

        return rtrim($gameUrl, '/') . '/sso/callback?token=' . urlencode($b64 . '.' . $sig);
    }

    public static function verifyAccessStatusSignature(
        string $subType,
        string $subId,
        string $timestampHeader,
        string $signatureHeader
    ): bool {
        if (!in_array($subType, ['admin', 'customer'], true)) {
            return false;
        }

        if ($subId === '' || strlen($subId) > 64) {
            return false;
        }

        $tsClean = trim($timestampHeader);
        if ($tsClean === '' || !ctype_digit($tsClean)) {
            return false;
        }

        $ts = (int) $tsClean;
        if (abs(time() - $ts) > 120) {
            return false;
        }

        $sigClean = strtolower(trim($signatureHeader));
        if ($sigClean === '' || strlen($sigClean) !== 64) {
            return false;
        }

        $secret = self::resolveSigningSecret();
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', "{$subType}|{$subId}|{$tsClean}", $secret);

        return hash_equals($expected, $sigClean);
    }

    /**
     * @return array{allowed: bool, funki_level: int, funken_balance: int}
     */
    public static function resolveLiveAccessStatus(string $subType, string $subId): array
    {
        if ($subType === 'admin') {
            $admin = Admin::find($subId);
            $allowed = $admin instanceof Admin && (!method_exists($admin, 'trashed') || !$admin->trashed());

            return [
                'allowed' => $allowed,
                'funki_level' => $allowed ? 10 : 0,
                'funken_balance' => 0,
            ];
        }

        if ($subType === 'customer') {
            $customer = Customer::withTrashed()->with(['profile', 'gamification'])->find($subId);
            if (!$customer) {
                return [
                    'allowed' => false,
                    'funki_level' => 0,
                    'funken_balance' => 0,
                ];
            }

            $allowed = !$customer->trashed()
                && $customer->hasVerifiedEmail()
                && !(bool) $customer->needs_password_change
                && (bool) $customer->mmorpg_access_enabled === true;

            $gamification = $customer->gamification;

            return [
                'allowed' => $allowed,
                'funki_level' => (int) ($gamification->funki_level ?? 1),
                'funken_balance' => (int) ($gamification->funken_balance ?? 0),
            ];
        }

        return [
            'allowed' => false,
            'funki_level' => 0,
            'funken_balance' => 0,
        ];
    }

    private static function resolveDisplayName(object $user, ?CustomerGamification $gamification, bool $isAdmin): string
    {
        if ($isAdmin) {
            $full = trim((string) ($user->first_name ?? '') . ' ' . (string) ($user->last_name ?? ''));
            return $full !== '' ? $full : 'Administrator';
        }

        $playerName = trim((string) ($gamification?->player_name ?? ''));
        if ($playerName !== '') {
            return $playerName;
        }

        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));

        if (!empty($gamification?->use_real_name)) {
            $full = trim($first . ' ' . $last);
            return $full !== '' ? $full : 'Funkenwächter';
        }

        if ($first !== '') {
            return $last !== ''
                ? $first . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.'
                : $first;
        }

        return 'Funkenwächter';
    }

    private static function countHealingPotions(?CustomerGamification $gamification): int
    {
        if (!$gamification || !is_array($gamification->inventory_items)) {
            return 0;
        }

        $inv = $gamification->inventory_items;

        if (isset($inv['bag_slots']) && is_array($inv['bag_slots'])) {
            $slotSum = 0;
            foreach ($inv['bag_slots'] as $slot) {
                if (is_array($slot) && ($slot['item_key'] ?? null) === 'healing_potion') {
                    $slotSum += max(0, (int) ($slot['qty'] ?? $slot['quantity'] ?? $slot['count'] ?? 0));
                }
            }

            return min(30, max(0, $slotSum));
        }

        if (isset($inv['healing_potion']) && is_numeric($inv['healing_potion'])) {
            return min(30, max(0, (int) $inv['healing_potion']));
        }

        $sum = 0;
        foreach ($inv as $entry) {
            if (is_array($entry) && ($entry['item_key'] ?? null) === 'healing_potion') {
                $qty = $entry['quantity'] ?? $entry['qty'] ?? $entry['count'] ?? 1;
                $sum += max(0, (int) $qty);
            } elseif ($entry === 'healing_potion') {
                $sum += 1;
            }
        }

        return min(30, max(0, $sum));
    }
}
