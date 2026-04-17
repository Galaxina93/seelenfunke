<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart\Cart;
use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AbandonedCartReminder;

class SendAbandonedCartReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:send-abandoned-cart-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends email reminders to customers who abandoned their carts passing the yellow limit.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get yellow limit from settings, default to 3 hours
        $setting = SystemSetting::where('key', 'cart_abandoned_yellow_hours')->first();
        $yellowLimit = $setting ? (int)$setting->value : 3;

        // Fetch carts that are older than yellow limit but haven't received an email
        $carts = Cart::with('customer')
            ->where('updated_at', '<=', now()->subHours($yellowLimit))
            ->whereNull('reminder_email_sent_at')
            ->whereNotNull('customer_id') // we need a known customer to send emails
            ->has('items') // don't send emails for empty carts
            ->get();

        $count = 0;

        foreach ($carts as $cart) {
            if ($cart->customer && $cart->customer->email) {
                try {
                    Mail::to($cart->customer->email)->send(new AbandonedCartReminder($cart));
                    $cart->update(['reminder_email_sent_at' => now()]);
                    $count++;
                    $this->info("Sent reminder to {$cart->customer->email} for cart {$cart->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to send scheduled abandoned cart reminder to {$cart->customer->email}: " . $e->getMessage());
                    $this->error("Failed to send to {$cart->customer->email}");
                }
            }
        }

        $this->info("Successfully sent $count reminder emails.");
    }
}
