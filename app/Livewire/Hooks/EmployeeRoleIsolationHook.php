<?php

namespace App\Livewire\Hooks;

use App\Services\AI\AiAuthHelper;
use Illuminate\Support\Facades\Auth;
use Livewire\ComponentHook;

class EmployeeRoleIsolationHook extends ComponentHook
{
    /**
     * Öffentliche Shop-Komponenten, die auch von eingeloggten Creatorn/Influencern im Frontend genutzt werden dürfen.
     */
    private const PUBLIC_SHOP_COMPONENTS = [
        \App\Livewire\Shop\Cart\Cart::class,
        \App\Livewire\Shop\Cart\CartIcon::class,
        \App\Livewire\Shop\Order\OrderCheckout\OrderCheckout::class,
        \App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess::class,
        \App\Livewire\Shop\Order\OrderQuoteAcceptance::class,
        \App\Livewire\Shop\Product\ProductFrontendFilterArea::class,
        \App\Livewire\Shop\Product\ProductShow::class,
        \App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class,
        \App\Livewire\Shop\Product\ProductCalculator::class,
        \App\Livewire\Shop\Marketing\MarketingVoucherSlider::class,
        \App\Livewire\Shop\Marketing\MarketingNewsletterPage::class,
        \App\Livewire\Shop\Marketing\MarketingBlogIndex::class,
        \App\Livewire\Shop\Marketing\MarketingBlogShow::class,
        \App\Livewire\Shop\System\SystemDeliveryDisplay::class,
    ];

    public function boot(): void
    {
        // Wenn ein Admin authentifiziert ist, greift keine Partner-Einschränkung
        if (Auth::guard('admin')->check() || AiAuthHelper::isAdmin()) {
            return;
        }

        $employee = Auth::guard('employee')->user();
        if (!$employee || $employee->isInternalEmployee()) {
            return;
        }

        $componentClass = get_class($this->component);

        // 1. Creator-Cockpit ist nur für Rolle 'influencer' erlaubt
        if (str_starts_with($componentClass, 'App\\Livewire\\Employee\\Influencer\\')) {
            if (!$employee->isInfluencer()) {
                abort(403, 'Zugriff nur für registrierte Creator.');
            }
            return;
        }

        // 2. Steuerberater-Cockpit ist nur für Rolle 'tax_advisor' erlaubt
        if (str_starts_with($componentClass, 'App\\Livewire\\Employee\\TaxAdvisor\\')) {
            if (!$employee->isTaxAdvisor()) {
                abort(403, 'Zugriff nur für autorisierte Steuerberater.');
            }
            return;
        }

        // 3. Interne Mitarbeiter-, Backend- und Admin-Shop-Komponenten sind für externe Partner (Influencer / Steuerberater) strikt gesperrt
        if (
            str_starts_with($componentClass, 'App\\Livewire\\Employee\\')
            || str_starts_with($componentClass, 'App\\Livewire\\Backend\\')
            || (
                str_starts_with($componentClass, 'App\\Livewire\\Shop\\')
                && !in_array($componentClass, self::PUBLIC_SHOP_COMPONENTS, true)
            )
        ) {
            abort(403, 'Zugriff für externe Partner verweigert.');
        }
    }
}
