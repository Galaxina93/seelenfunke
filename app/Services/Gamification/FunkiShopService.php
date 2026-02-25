<?php

namespace App\Services\Gamification;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerFunkiItem;
use App\Models\Customer\CustomerGamification;
use App\Models\Funki\FunkiItem;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class FunkiShopService
{
    protected GamificationService $gamificationService;

    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;
    }

    /**
     * Kauft ein Item mit der In-Game Währung "Funken"
     */
    public function buyWithFunken(Customer $customer, FunkiItem $item): array
    {
        $profile = $this->gamificationService->getProfile($customer);

        if ($this->ownsItem($customer, $item->id)) {
            return ['success' => false, 'message' => 'Du besitzt dieses Item bereits.'];
        }

        if (!$item->price_funken || $profile->funken_balance < $item->price_funken) {
            return ['success' => false, 'message' => 'Nicht genügend Funken.'];
        }

        DB::transaction(function () use ($profile, $customer, $item) {
            // Funken abziehen
            $profile->funken_balance -= $item->price_funken;
            $profile->save();

            // Dem Inventar hinzufügen
            CustomerFunkiItem::create([
                'customer_id' => $customer->id,
                'funki_item_id' => $item->id,
                'purchased_via' => 'funken'
            ]);
        });

        return ['success' => true, 'message' => 'Item erfolgreich erworben!'];
    }

    /**
     * Erstellt eine Stripe Checkout-Session für den Kauf mit Echtgeld
     */
    public function createStripeCheckout(Customer $customer, FunkiItem $item): array
    {
        if ($this->ownsItem($customer, $item->id)) {
            return ['success' => false, 'message' => 'Du besitzt dieses Item bereits.'];
        }

        if (!$item->price_money) {
            return ['success' => false, 'message' => 'Dieses Item kann nicht mit Echtgeld gekauft werden.'];
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeSession::create([
            'payment_method_types' => ['card', 'paypal', 'klarna'],
            'customer_email' => $customer->email,
            'client_reference_id' => $customer->id, // Wichtig für Webhook
            'metadata' => [
                'type' => 'funki_cosmetic',
                'funki_item_id' => $item->id,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Funki Cosmetic: ' . $item->name,
                        'images' => [asset('storage/' . $item->preview_image_path)],
                    ],
                    'unit_amount' => $item->price_money, // In Cent
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('customer.dashboard') . '?cosmetic_success=1',
            'cancel_url' => route('customer.dashboard') . '?cosmetic_cancelled=1',
        ]);

        return ['success' => true, 'url' => $session->url];
    }

    /**
     * Rüstet ein Item aus (oder legt es ab)
     */
    public function toggleEquipItem(Customer $customer, FunkiItem $item): array
    {
        if (!$this->ownsItem($customer, $item->id)) {
            return ['success' => false, 'message' => 'Dir fehlt dieses Item.'];
        }

        $profile = $this->gamificationService->getProfile($customer);
        $field = 'active_' . $item->type . '_id'; // z.B. active_background_id

        if ($profile->$field === $item->id) {
            // Ablegen
            $profile->$field = null;
            $msg = 'Item abgelegt.';
        } else {
            // Ausrüsten
            $profile->$field = $item->id;
            $msg = 'Item ausgerüstet!';
        }

        $profile->save();
        return ['success' => true, 'message' => $msg];
    }

    public function ownsItem(Customer $customer, int $itemId): bool
    {
        return CustomerFunkiItem::where('customer_id', $customer->id)
            ->where('funki_item_id', $itemId)
            ->exists();
    }
}
