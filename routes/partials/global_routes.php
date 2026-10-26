<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SeoController;
use App\Livewire\Shop\Cart\Cart;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess;
use App\Livewire\Shop\Marketing\MarketingNewsletterPage;
use App\Livewire\Shop\Order\OrderQuoteAcceptance;
use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Livewire\Shop\Product\ProductShow;
use App\Models\Tracking\PageVisit;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request; // Fassade für Request::ip()
use Illuminate\Support\Facades\Route;
use App\Livewire\Shop\Marketing\MarketingBlogIndex;
use App\Livewire\Shop\Marketing\MarketingBlogShow;
use Illuminate\Auth\Events\Verified;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

/*Cronjob & System-Wartung*/
Route::get('/system/cronjob/{token}', function ($token) {
    $throttleKey = 'cronjob_run_' . request()->ip();
    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 60)) {
        abort(429, 'Zu viele Aufrufe.');
    }
    \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

    $expected = config('services.system.cron_secret') ?: env('SYSTEM_CRON_SECRET');
    if (empty($expected) || !hash_equals((string)$expected, (string)$token)) {
        \App\Services\System\SystemSecurityService::logEvent(
            'security:cron_token_invalid',
            'Ungültiges Cronjob-Token abgewehrt',
            'Unbefugter Aufruf der System-Cronjob-Route mit falschem Sicherheitstoken.',
            'warning'
        );
        abort(403, 'Ungültiger Sicherheitstoken.');
    }

    Artisan::call('schedule:run');
    return 'Cronjob erfolgreich ausgeführt.';
});

Route::get('/system/cache/clear/{token}', function ($token) {
    $throttleKey = 'cache_clear_' . request()->ip();
    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 3)) {
        abort(429, 'Zu viele Cache-Leerungsversuche.');
    }
    \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 300);

    $expected = config('services.system.cache_secret') ?: env('SYSTEM_CACHE_SECRET');
    if (empty($expected) || !hash_equals((string)$expected, (string)$token)) {
        \App\Services\System\SystemSecurityService::logEvent(
            'security:cron_token_invalid',
            'Ungültiges Cache-Clear-Token abgewehrt',
            'Unbefugter Aufruf der System-Cache-Clear-Route mit falschem Sicherheitstoken.',
            'warning'
        );
        abort(403, 'Ungültiger Sicherheitstoken.');
    }

    Artisan::call('optimize:clear');
    return 'Cache erfolgreich geleert und optimiert.';
});

// --- 0. SEO & KI-Suchmaschinen Discovery (Sitemap, Google Shopping Feed, JSON Feed & LLMs.txt) ---
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/llms.txt', [SeoController::class, 'llmsTxt'])->name('seo.llms');
Route::get('/llms-full.txt', [SeoController::class, 'llmsFullTxt'])->name('seo.llms.full');
Route::get('/feed/google-shopping.xml', [SeoController::class, 'googleShoppingFeed'])->name('seo.google-shopping');
Route::get('/feed/products.json', [SeoController::class, 'productsJsonFeed'])->name('seo.products-json');

// --- 1. Shop ---
Route::get('/warenkorb', Cart::class)->name('cart');

// Wichtig: {product:slug} sagt Laravel, es soll in der Spalte 'slug' suchen, nicht 'id'
Route::get('/produkt/{product:slug}', ProductShow::class)->name('product.show');

// Die dedizierte Seite
Route::get('/newsletter', MarketingNewsletterPage::class)->name('newsletter.page');

// Der Link aus der E-Mail (Controller Action)
Route::get('/newsletter/verify/{token}', [NewsletterController::class, 'verify'])
    ->name('newsletter.verify')
    ->middleware('throttle:30,1');

// Warenkorb-Erinnerungen Abmeldung (Opt-Out nach § 7 Abs. 3 UWG / Art. 21 DSGVO)
Route::get('/warenkorb/erinnerung/abmelden/{id}/{hash}', [\App\Http\Controllers\CartReminderUnsubscribeController::class, 'unsubscribe'])
    ->name('cart.reminder.unsubscribe')
    ->middleware('throttle:30,1');

// Der Checkout
Route::get('/checkout', OrderCheckout::class)->name('checkout');
Route::get('/checkout/success', OrderCheckoutSuccess::class)->name('checkout.success');

// Angebot annehmen
Route::get('/angebot/{token}/annehmen', OrderQuoteAcceptance::class)->name('quote.accept');

// Bezahlungen über Bezahllink zuordenen und Bestellung als bezahlt markieren
Route::post('stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])
    ->middleware('throttle:60,1');

// Google Accounts
Route::get('auth/{guard}/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');


// --- 2. Hauptseiten ---

// Startseite
Route::get('/', function () {
    // Tracker
    try {
        $alreadyVisited = PageVisit::where('page', 'home')
            ->where('ip_address', Request::ip())
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if (!$alreadyVisited) {
            PageVisit::create([
                'page' => 'home',
                'ip_address' => Request::ip(),
            ]);
        }
    } catch (\Exception $e) {
        // Wichtig: Falls die Datenbank mal klemmt, soll die Seite trotzdem laden!
        // (Ignoriere Fehler beim Tracking)
    }

    // Die View zurückgeben
    return view('frontend.pages.welcome');
})->name('home');

// Manufaktur & Qualität
Route::get('/manufaktur', function () {
    return view('frontend.pages.manufacture');
})->name('manufacture');

Route::get('/shop', ProductFrontendFilterArea::class)->name('shop');
Route::redirect('/gutscheine', '/produkt/geschenkgutschein', 301)->name('vouchers');

// Marketing Landing Page
Route::get('/l/{slug}', \App\Livewire\Landing\LandingPageView::class)->name('landing-page');

Route::get('/blog', MarketingBlogIndex::class)->name('marketing/marketing/blog');

// Blog Einzelansicht (muss nach der Übersicht kommen, damit "magazin" nicht als Slug interpretiert wird)
Route::get('/blog/{slug}', MarketingBlogShow::class)->name('blog.show');

// Kontakt
Route::get('/kontakt', function () {
    return view('frontend.pages.contact');
})->name('contact');


// --- 3. Tools ---
// Kalkulator
Route::get('/calculator', function () {
    return view('frontend.pages.calculator');
})->name('calculator');

// --- 4. Rechtliches & Weiteres ---
Route::get('/impressum', function () {
    return view('frontend.pages.impressum');
})->name('impressum');

Route::get('/datenschutz', function () {
    return view('frontend.pages.datenschutz');
})->name('datenschutz');

Route::get('/agb', function () {
    return view('frontend.pages.agb');
})->name('agb');

Route::get('/links', \App\Livewire\Frontend\Marketing\MarketingLinktreePage::class)->name('frontend.linktree');

// DSGVO-konformer Kurz-Tracking-Link Redirect (Lokal, Stage & Live)
Route::get('/t/{slug}', function (\Illuminate\Http\Request $request, string $slug) {
    $cleanSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $slug);
    if ($cleanSlug === '' || !\Illuminate\Support\Facades\Schema::hasTable('marketing_tracking_links')) {
        return redirect()->route('shop')->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }

    $trackingLink = \App\Models\Marketing\MarketingTrackingLink::with('influencer')
        ->where('slug', $cleanSlug)
        ->where('is_active', true)
        ->first();

    if (!$trackingLink) {
        return redirect()->route('shop')->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }

    // Falls der Kurzlink zu einem pausierten oder archivierten Influencer gehört -> keine Attribution, sauber zum Shop weiterleiten
    if (!empty($trackingLink->influencer_id)) {
        $inf = $trackingLink->influencer;
        if (!$inf || !$inf->is_active || $inf->is_archived || $inf->is_pending_invitation) {
            return redirect()->route('shop')->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
        }
    }

    $agent = strtolower((string) $request->userAgent());
    $isBot = $agent === '' || preg_match('/(bot|crawl|slurp|spider|mediapartners|apis-google|yandex|bing|http|monitor|inspect|health)/i', $agent);

    if (!$isBot) {
        try {
            $trackingLink->recordHit($request->ip(), $request->userAgent(), $request->headers->get('referer'));
            session()->put('attributed_tracking_link_id', $trackingLink->id);

            if (!empty($trackingLink->influencer_id) && $trackingLink->influencer) {
                $cookieMinutes = max(1, (int) ($trackingLink->influencer->cookie_days ?: 30)) * 1440;
                session([
                    'seelenfunke_influencer_id' => $trackingLink->influencer_id,
                    'seelenfunke_tracking_link_id' => $trackingLink->id,
                ]);
                \Illuminate\Support\Facades\Cookie::queue('seelenfunke_influencer_id', $trackingLink->influencer_id, $cookieMinutes);
                \Illuminate\Support\Facades\Cookie::queue('seelenfunke_tracking_link_id', $trackingLink->id, $cookieMinutes);
            }
        } catch (\Throwable $e) {
            // Tracking-Fehler dürfen die Weiterleitung niemals blockieren
        }
    }

    return redirect()->away($trackingLink->full_utm_url)->withHeaders([
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ]);
})->name('frontend.tracking.redirect')->middleware('throttle:120,1');

// Influencer / Creator Onboarding (Magic Einladungslink)
Route::get('/partner/einladung/{token}', \App\Livewire\Frontend\Marketing\InfluencerInvitationAccept::class)
    ->name('influencer.invitation')
    ->middleware('throttle:30,1');

// Mitarbeiter Onboarding & Digitaler Arbeitsvertrag (§ 2 NachwG / BEG IV)
Route::get('/mitarbeiter/einladung/{token}', \App\Livewire\Frontend\Employee\EmployeeInvitationAccept::class)
    ->name('employee.invitation')
    ->middleware('throttle:30,1');

Route::get('/widerruf', function () {
    return view('frontend.pages.widerruf');
})->name('widerruf');

Route::get('/widerruf/pdf', [\App\Http\Controllers\RevocationPdfController::class, 'download'])
    ->name('widerruf.pdf')
    ->middleware('throttle:30,1');
Route::get('/widerrufsformular.pdf', [\App\Http\Controllers\RevocationPdfController::class, 'download'])
    ->name('widerrufsformular.pdf')
    ->middleware('throttle:30,1');

Route::get('/verhaltenskodex', function () {
    return view('frontend.pages.verhaltenskodex');
})->name('verhaltenskodex');

Route::get('/versand', function () {
    return view('frontend.pages.versand');
})->name('versand');

Route::get('/barrierefreiheit', function () {
    return view('frontend.pages.barrierefreiheit');
})->name('barrierefreiheit');

// Redirects für alte/falsche Links
Route::redirect('/datenschutzerklaerung', '/datenschutz');

// --- 4.5. Digitaler Notfall & Nachlass ---
Route::get('/notfall', \App\Livewire\Frontend\Emergency\EmergencyProtocol::class)->name('emergency.index');

// --- 5. Authentifizierung & Kundenbereich ---

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->name('login');


// REGISTRATIONS ROUTE (Name korrigiert zu 'livewire.auth.register')
Route::get('/register', App\Livewire\Auth\AuthRegister::class)->name('livewire.auth.register');

// Zeigt den Hinweis an, dass der User seine E-Mail bestätigen muss (nur wenn eingeloggter User unbestätigt ist)
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');


// NEU: Unsere maßgeschneiderte, ungeschützte Gast-Route für die E-Mail-Verifizierung
Route::get('/email/verify-customer/{id}/{hash}', [\App\Http\Controllers\Customer\CustomerVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('customer.verification.verify');

// Resend-Link, falls die E-Mail nicht ankam (erfordert eingeloggten Zustand als Web-User oder Kunde)
Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verifizierungs-Link gesendet!');
})->middleware(['auth:web,customer', 'throttle:6,1'])->name('verification.send');

// --- 6. Rechnungsdownload Route ---
Route::get('/invoice/{invoice}/download', [\App\Http\Controllers\Accounting\InvoiceDownloadController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('invoice.download');

// --- 7. Digitaler Produkt-Download Route ---
Route::get('/digital-product/{item}/download', [\App\Http\Controllers\Customer\DigitalProductDownloadController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('digital-product.download');

// --- 8. Legacy Snapshot URL Fallback Route (Leitet alte Pfade auf GoBD/System-Pfad um) ---
Route::get('/storage/snapshots/{path}', function ($path) {
    $rawPath = (string) $path;
    if ($rawPath === '' || str_contains($rawPath, '..') || str_contains($rawPath, "\0") || str_contains($rawPath, '\\')) {
        abort(404);
    }
    $cleanPath = ltrim($rawPath, '/');
    return redirect('/storage/system/snapshots/' . $cleanPath, 301);
})->where('path', '.*');
