<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class MarketingTrackingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prüfen, ob der Request UTM Parameter enthält
        if ($request->has('utm_source') || $request->has('utm_campaign') || $request->has('utm_medium')) {
            $rawSource = $request->input('utm_source');
            $rawCampaign = $request->input('utm_campaign');
            $rawMedium = $request->input('utm_medium');

            $source = is_string($rawSource) ? substr(trim($rawSource), 0, 100) : null;
            $campaign = is_string($rawCampaign) ? substr(trim($rawCampaign), 0, 100) : null;
            $medium = is_string($rawMedium) ? substr(trim($rawMedium), 0, 100) : null;

            if ($source !== null || $campaign !== null || $medium !== null) {
                $data = json_encode([
                    'source' => $source,
                    'campaign' => $campaign,
                    'medium' => $medium,
                    'timestamp' => now()->toDateTimeString()
                ]);

                // First-Touch Cookie: Nur setzen wenn es noch nicht existiert
                if (!$request->hasCookie('seelenfunke_first_touch')) {
                    // 30 Tage gültig (30 * 24 * 60 = 43200 Minuten)
                    Cookie::queue('seelenfunke_first_touch', $data, 43200);
                }

                // Last-Touch Cookie: Wird bei jedem neuen Besuch mit Tags überschrieben
                // 1 Tag gültig (24 * 60 = 1440 Minuten)
                Cookie::queue('seelenfunke_last_touch', $data, 1440);
            }
        }

        // Influencer-Zuordnung aus tid oder utm_campaign sichern, falls direkt über UTM-URL eingestiegen wurde
        if ($request->filled('tid') && \Illuminate\Support\Facades\Schema::hasTable('marketing_tracking_links')) {
            $tidSlug = substr(trim((string) $request->input('tid')), 0, 100);
            if ($tidSlug !== '') {
                $trkLink = \App\Models\Marketing\MarketingTrackingLink::where('slug', $tidSlug)
                    ->where('is_active', true)
                    ->whereNotNull('influencer_id')
                    ->first();
                if ($trkLink && $trkLink->influencer && $trkLink->influencer->is_active) {
                    $cookieMinutes = max(1, (int) ($trkLink->influencer->cookie_days ?: 30)) * 1440;
                    session([
                        'seelenfunke_influencer_id' => $trkLink->influencer_id,
                        'seelenfunke_tracking_link_id' => $trkLink->id,
                    ]);
                    Cookie::queue('seelenfunke_influencer_id', $trkLink->influencer_id, $cookieMinutes);
                    Cookie::queue('seelenfunke_tracking_link_id', $trkLink->id, $cookieMinutes);
                }
            }
        }

        // Eingeloggter Kunde für Validierung von Referral- und Neukundencodes
        $customer = Auth::guard('customer')->user();
        $referralService = app(\App\Services\ReferralService::class);

        // Referral Tracking (Freunde werben)
        if ($request->has('ref') && is_string($request->input('ref'))) {
            $ref = substr(trim($request->input('ref')), 0, 100);
            if ($ref !== '') {
                $refCheck = $customer ? $referralService->validateReferralCode($ref, $customer->email, $customer->id) : null;
                // Wenn eingeloggter Kunde sein eigener Werber oder Bestandskunde ist: nicht setzen & bereinigen
                if ($customer && $refCheck && $refCheck['is_referral'] && !$refCheck['valid']) {
                    session()->forget('seelenfunke_referrer');
                    Cookie::queue(Cookie::forget('seelenfunke_referrer'));
                } else {
                    Cookie::queue('seelenfunke_referrer', $ref, 43200); // 30 Tage
                    session(['seelenfunke_referrer' => $ref]);
                }
            }
        }

        // Gutscheincode aus Link (z.B. coupon=FREUNDE10)
        if ($request->has('coupon') && is_string($request->input('coupon'))) {
            $coupon = substr(strtoupper(trim($request->input('coupon'))), 0, 100);
            if ($coupon !== '') {
                $couponCheck = $customer ? $referralService->validateReferralCode($coupon, $customer->email, $customer->id) : null;
                // Wenn eingeloggter Kunde sein eigener Werber oder Bestandskunde ist: nicht setzen & bereinigen
                if ($customer && $couponCheck && $couponCheck['is_referral'] && !$couponCheck['valid']) {
                    session()->forget('seelenfunke_coupon');
                    Cookie::queue(Cookie::forget('seelenfunke_coupon'));
                } else {
                    Cookie::queue('seelenfunke_coupon', $coupon, 43200); // 30 Tage
                    session(['seelenfunke_coupon' => $coupon]);
                }
            }
        }

        // Bereinigung für eingeloggte Kunden, falls bereits alte/unzulässige Referral-Codes in Session/Cookie schlummern
        if ($customer) {
            $activeCoupon = session('seelenfunke_coupon') ?: $request->cookie('seelenfunke_coupon');
            if ($activeCoupon) {
                $check = $referralService->validateReferralCode($activeCoupon, $customer->email, $customer->id);
                if ($check['is_referral'] && !$check['valid']) {
                    session()->forget('seelenfunke_coupon');
                    Cookie::queue(Cookie::forget('seelenfunke_coupon'));
                }
            }

            $activeReferrer = session('seelenfunke_referrer') ?: $request->cookie('seelenfunke_referrer');
            if ($activeReferrer) {
                $refCheck = $referralService->validateReferralCode($activeReferrer, $customer->email, $customer->id);
                if ($refCheck['is_referral'] && !$refCheck['valid']) {
                    session()->forget('seelenfunke_referrer');
                    Cookie::queue(Cookie::forget('seelenfunke_referrer'));
                }
            }
        }

        return $response;
    }
}
