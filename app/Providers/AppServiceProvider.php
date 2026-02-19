<?php

namespace App\Providers;

use App\Models\ShopSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // 2. Füge die Morph Map hinzu
        Relation::enforceMorphMap([
            'admin'    => 'App\Models\Admin\Admin',
            'customer' => 'App\Models\Customer\Customer',
            'employee' => 'App\Models\Employee\Employee',
            'permission' => 'App\Models\Permission',
            'directory' => 'App\Models\Directory',
            'role' => 'App\Models\Role',
            'financial_special_issue' => 'App\Models\Financial\FinanceSpecialIssue',
            'order' => 'App\Models\Order\Order',
            'order_item' => 'App\Models\Order\OrderItem',
            'day_routine' => 'App\Models\DayRoutine',
            // Füge hier bei Bedarf weitere Models hinzu
        ]);

        // Dein Code zum Laden der Migrationen kann hier bleiben
        $migrationsPath = database_path('migrations');
        $directories    = glob($migrationsPath.'/*', GLOB_ONLYDIR);
        $paths          = array_merge([$migrationsPath], $directories);

        $this->loadMigrationsFrom($paths);

        // WICHTIG: Prüfen, ob die Tabelle existiert, sonst crashen Migrations beim Deployment
        if (Schema::hasTable('shop-settings')) {

            // 1. Settings aus dem Cache laden (oder aus DB holen und cachen)
            // Wir nutzen den gleichen Cache-Key wie in deiner ShopConfig beim Speichern
            $settings = Cache::rememberForever('global_shop_settings', function () {
                return ShopSetting::pluck('value', 'key')->toArray();
            });

            // 2. Stripe Konfiguration zur Laufzeit überschreiben
            if (!empty($settings['stripe_publishable_key'])) {
                Config::set('services.stripe.key', $settings['stripe_publishable_key']);
            }

            if (!empty($settings['stripe_secret_key'])) {
                Config::set('services.stripe.secret', $settings['stripe_secret_key']);
            }

            if (!empty($settings['stripe_webhook_secret'])) {
                // Hier wird der verschachtelte Pfad gesetzt: services -> stripe -> webhook -> secret
                Config::set('services.stripe.webhook.secret', $settings['stripe_webhook_secret']);
            }
        }

        // Überschreibt die Standard-Verifizierungsmail von Laravel
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Willkommen! Bitte bestätige deine E-Mail-Adresse ✨')
                // Hier verweist du auf deine neue Blade-Datei aus Schritt 1:
                ->view('global.mails.auth.new_register_mail_to_customer', [
                    'url' => $url,
                    'name' => $notifiable->first_name
                ]);
        });

        // 2. [NEU] Zwingt Laravel, eine eigene Route für Kunden zu nutzen!
        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'customer.verification.verify', // Unser eigener, neuer Routen-Name
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });




    }
}
