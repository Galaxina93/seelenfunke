<?php

namespace App\Livewire\Shop\Funki;

use App\Services\FunkiBotService;
use Livewire\Component;

class FunkiBot extends Component
{
    public $activeTab = 'instructions';

    public function getWorkInstructionsProperty()
    {
        $service = app(FunkiBotService::class);
        return [
            'priority_order' => $service->getPriorityOrder(),
            'product_status' => $service->getProductStatus(),
            'quote_status' => $service->getQuoteStatus(),
            'invoice_status' => $service->getInvoiceStatus(),
            'blog_status' => $service->getBlogStatus(),
            'shipping_status' => $service->getShippingStatus(),
            'system_status' => $service->getSystemStatus(),
        ];
    }

    public function getUltimateCommandProperty()
    {
        return app(FunkiBotService::class)->getUltimateCommand();
    }

    public function getStats()
    {
        $abandonedCarts = \App\Models\Cart\Cart::with('items')
            ->where('updated_at', '>=', now()->subHours(24))
            ->where('updated_at', '<=', now()->subHours(2))
            ->get();

        $potentialRevenueCents = 0;
        foreach ($abandonedCarts as $cart) {
            foreach ($cart->items as $item) {
                $potentialRevenueCents += ($item->quantity * $item->unit_price);
            }
        }
        $potentialRevenue = $potentialRevenueCents / 100;

        return [
            'active_vouchers' => \App\Models\Voucher::where('is_active', true)->where('mode', 'auto')->count(),
            'manual_coupons' => \App\Models\Voucher::where('is_active', true)->where('mode', 'manual')->count(),
            'abandoned_carts' => [
                'count' => $abandonedCarts->count(),
                'potential_revenue' => $potentialRevenue,
            ],
        ];
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('livewire.shop.funki.funki-bot', [
            'stats' => $this->getStats(),
        ]);
    }
}
