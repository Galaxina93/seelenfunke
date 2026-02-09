<?php

use App\Http\Controllers\NewsletterController;
use App\Livewire\Shop\Cart\Cart;
use App\Livewire\Shop\Checkout\Checkout;
use App\Livewire\Shop\Checkout\CheckoutSuccess;
use App\Livewire\Shop\Newsletter\NewsletterPage;
use App\Livewire\Shop\Offer\QuoteAcceptance;
use App\Livewire\Shop\Product\ProductIndex;
use App\Livewire\Shop\Product\ProductShow;
use App\Models\PageVisit;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/


// --- 1. Shop ---
Route::get('/warenkorb', Cart::class)->name('cart');

Route::get('/shop', ProductIndex::class)->name('shop');
// Wichtig: {product:slug} sagt Laravel, es soll in der Spalte 'slug' suchen, nicht 'id'
Route::get('/produkt/{product:slug}', ProductShow::class)->name('product.show');

// Die dedizierte Seite
Route::get('/newsletter', NewsletterPage::class)->name('newsletter.page');

// Der Link aus der E-Mail (Controller Action)
Route::get('/newsletter/verify/{token}', [NewsletterController::class, 'verify'])->name('newsletter.verify');

// Der Checkout
Route::get('/checkout', Checkout::class)->name('checkout');
Route::get('/checkout/success', CheckoutSuccess::class)->name('checkout.success');

// Angebot annehmen
Route::get('/angebot/{token}/annehmen', QuoteAcceptance::class)->name('quote.accept');

// Bezahlungen über Bezahllink zuordenen und Bestellung als bezahlt markieren
Route::post('stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle']);

// --- 1. Hauptseiten ---

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

    // 2. Die View zurückgeben
    return view('frontend.pages.welcome');

})->name('home');

// Produkt-Detailseite
Route::get('/seelen-kristall', function () {
    return view('frontend.pages.product');
})->name('product.detail');

// Manufaktur & Qualität
Route::get('/manufaktur', function () {
    return view('frontend.pages.manufacture');
})->name('manufacture');

// Kontakt
Route::get('/kontakt', function () {
    return view('frontend.pages.contact');
})->name('contact');

// --- 2. Tools ---

// Kalkulator
Route::get('/calculator', function () {
    return view('frontend.pages.calculator');
})->name('calculator');

Route::get('/application', function () {
    return view('frontend.pages.application');
})->name('application');


// --- 3. Rechtliches & Weiteres ---

Route::get('/impressum', function () {
    return view('frontend.pages.impressum');
})->name('impressum');

Route::get('/datenschutz', function () {
    return view('frontend.pages.datenschutz');
})->name('datenschutz');

Route::get('/agb', function () {
    return view('frontend.pages.agb');
})->name('agb');

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



Route::get('/login', function () {
    return view('global/pages/auth/login');
})->middleware('guest:' . implode(',', array_keys(config('auth.guards'))))->name('login');

Route::get('/register', App\Livewire\Global\Auth\Register::class)->name('register');


Route::get('/forgot-password', function () {
    return view('global/pages/password/forgot-password');
})->name('forgot-password');


// Rechnungsdownload Route
Route::get('/invoice/{invoice}/download', function (App\Models\Invoice $invoice) {

    // Security Gate: Darf der User das sehen?
    // Admin darf alles, Customer nur seine eigenen
    if (auth()->guard('admin')->check()) {
        // ok
    } elseif (auth()->guard('customer')->check() && auth()->guard('customer')->id() === $invoice->customer_id) {
        // ok
    } else {
        abort(403);
    }

    $service = new App\Services\InvoiceService();
    $pdf = $service->generatePdf($invoice);

    return $pdf->download('Rechnung_' . $invoice->invoice_number . '.pdf');

})->name('invoice.download');

