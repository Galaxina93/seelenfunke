<?php

namespace App\Livewire\Shop\Shipping;

use Livewire\Component;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Delivery\DeliveryFeedback;

class DeliveryDisplay extends Component
{
    public $product;

    // Status, ob der User bereits geklickt hat
    public $hasWishedVacation = false;
    public $hasWishedSick = false;

    public function mount()
    {
        if (auth()->guard('customer')->check()) {
            $userId = auth()->guard('customer')->id();

            // Prüft ob der Kunde in den letzten 30 Tagen bereits diesen Wunsch abgeschickt hat
            $this->hasWishedVacation = DeliveryFeedback::where('user_id', $userId)
                ->where('type', 'vacation')
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();

            $this->hasWishedSick = DeliveryFeedback::where('user_id', $userId)
                ->where('type', 'sick')
                ->where('created_at', '>=', now()->subDays(30))
                ->exists();
        }
    }

    public function sendFeedback($type)
    {
        if (!auth()->guard('customer')->check()) {
            return;
        }

        $user = auth()->guard('customer')->user();

        // Sicherheits-Check im Backend (verhindert Spam via Scripts)
        $alreadyExists = DeliveryFeedback::where('user_id', $user->id)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        if (!$alreadyExists) {
            DeliveryFeedback::create([
                'user_id' => $user->id,
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'type' => $type
            ]);

            if ($type === 'vacation') {
                $this->hasWishedVacation = true;
                $msg = 'Vielen Dank! Das Team freut sich sehr über deine Wünsche!';
            } else {
                $this->hasWishedSick = true;
                $msg = 'Danke! Deine Besserungswünsche wurden direkt weitergeleitet.';
            }

            $this->dispatch('feedback-sent', msg: $msg);
        }
    }

    public function render()
    {
        return view('livewire.shop.shipping.delivery-display', [
            'setting' => DeliverySetting::first(),
            'activeTime' => DeliveryTime::where('is_active', true)->first(),
            'deliveryText' => DeliverySetting::getCurrentDeliveryText()
        ]);
    }
}
