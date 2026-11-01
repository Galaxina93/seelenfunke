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
        \App\Livewire\Shop\Order\OrderRevocationForm::class,
        \App\Livewire\Shop\Product\ProductFrontendFilterArea::class,
        \App\Livewire\Shop\Product\ProductShow::class,
        \App\Livewire\Shop\Product\ProductReviews::class,
        \App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class,
        \App\Livewire\Shop\Product\ProductCalculator\ProductCalculator::class,
        \App\Livewire\Shop\Marketing\MarketingVoucherSlider::class,
        \App\Livewire\Shop\Marketing\MarketingVoucherBalanceChecker::class,
        \App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage::class,
        \App\Livewire\Shop\Marketing\MarketingNewsletterPage::class,
        \App\Livewire\Shop\Marketing\MarketingNewsletterSignup::class,
        \App\Livewire\Shop\Marketing\MarketingBlogIndex::class,
        \App\Livewire\Shop\Marketing\MarketingBlogShow::class,
        \App\Livewire\Shop\System\SystemDeliveryDisplay::class,
    ];

    /**
     * Geschützte Kundenportal-Komponenten, die zwingend einen eingeloggten Kunden erfordern.
     */
    private const PROTECTED_CUSTOMER_COMPONENTS = [
        \App\Livewire\Customer\CustomerDashboardComponent::class,
        \App\Livewire\Customer\CustomerOrdersComponent::class,
        \App\Livewire\Customer\CustomerInvoicesComponent::class,
        \App\Livewire\Customer\CustomerTicketsComponent::class,
    ];

    public function boot(): void
    {
        $componentClass = get_class($this->component);

        // 0. Geschützte Kundenportal-Komponenten erfordern immer den 'customer'-Guard
        if (in_array($componentClass, self::PROTECTED_CUSTOMER_COMPONENTS, true)) {
            $customer = Auth::guard('customer')->user();
            if (!$customer || !empty($customer->needs_password_change)) {
                abort(403, 'Zugriff nur für authentifizierte Kunden.');
            }
            return;
        }

        // Wenn ein Admin authentifiziert ist, greift keine Backend-/Partner-Einschränkung
        if (Auth::guard('admin')->check() || AiAuthHelper::isAdmin()) {
            return;
        }

        $isProtectedBackendOrEmployeeComponent = str_starts_with($componentClass, 'App\\Livewire\\Employee\\')
            || str_starts_with($componentClass, 'App\\Livewire\\Backend\\')
            || (
                str_starts_with($componentClass, 'App\\Livewire\\Shop\\')
                && !in_array($componentClass, self::PUBLIC_SHOP_COMPONENTS, true)
            );

        $employee = Auth::guard('employee')->user();
        if (!$employee) {
            // Weder Admin noch Mitarbeiter eingeloggt (Gast oder Kunde):
            // Jeglicher Zugriff auf Backend-, Mitarbeiter- oder nicht-öffentliche Shop-Komponenten wird strikt blockiert
            if ($isProtectedBackendOrEmployeeComponent) {
                abort(403, 'Unbefugter Zugriff auf geschützte Systemkomponente.');
            }
            return;
        }

        if (method_exists($employee, 'isAccountActive') && !$employee->isAccountActive()) {
            Auth::guard('employee')->logout();
            abort(403, 'Dieser Account ist pausiert oder deaktiviert.');
        }

        if ($employee->isInternalEmployee()) {
            // Interne Mitarbeiter dürfen weder das Creator- noch das Steuerberater-Cockpit aufrufen
            if (
                str_starts_with($componentClass, 'App\\Livewire\\Employee\\Influencer\\')
                || str_starts_with($componentClass, 'App\\Livewire\\Employee\\TaxAdvisor\\')
            ) {
                abort(403, 'Zugriff für interne Mitarbeiter nicht vorgesehen.');
            }
            return;
        }

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
        if ($isProtectedBackendOrEmployeeComponent) {
            abort(403, 'Zugriff für externe Partner verweigert.');
        }
    }
}
