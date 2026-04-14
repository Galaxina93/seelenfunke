<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\NewsletterController;
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

/*Cronjob*/
Route::get('/system/cronjob/run-secret-847294', function () {
    Artisan::call('schedule:run');
    return 'Cronjob erfolgreich ausgeführt.';
});

// --- 1. Shop ---
Route::get('/warenkorb', Cart::class)->name('cart');

// Wichtig: {product:slug} sagt Laravel, es soll in der Spalte 'slug' suchen, nicht 'id'
Route::get('/produkt/{product:slug}', ProductShow::class)->name('product.show');

// Die dedizierte Seite
Route::get('/newsletter', MarketingNewsletterPage::class)->name('newsletter.page');

// Der Link aus der E-Mail (Controller Action)
Route::get('/newsletter/verify/{token}', [NewsletterController::class, 'verify'])->name('newsletter.verify');

// Der Checkout
Route::get('/checkout', OrderCheckout::class)->name('checkout');
Route::get('/checkout/success', OrderCheckoutSuccess::class)->name('checkout.success');

// Angebot annehmen
Route::get('/angebot/{token}/annehmen', OrderQuoteAcceptance::class)->name('quote.accept');

// Bezahlungen über Bezahllink zuordenen und Bestellung als bezahlt markieren
Route::post('stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);

// Google Accounts
Route::get('auth/{guard}/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);


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

// Marketing Landing Page
Route::get('/l/{slug}', \App\Livewire\Landing\LandingPageView::class)->name('landing-page');

Route::get('/blog', MarketingBlogIndex::class)->name('blog');

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

Route::get('/widerruf', function () {
    return view('frontend.pages.widerruf');
})->name('widerruf');

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
    ->middleware(['signed'])
    ->name('customer.verification.verify');

// Resend-Link, falls die E-Mail nicht ankam (erfordert eingeloggten Zustand)
Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verifizierungs-Link gesendet!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// --- 6. Rechnungsdownload Route ---
Route::get('/invoice/{invoice}/download', [\App\Http\Controllers\Accounting\InvoiceDownloadController::class, 'download'])
    ->name('invoice.download');
