<?php

namespace App\Console\Commands\System;

use App\Models\Customer\Customer;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DeleteUnverifiedUsers extends Command
{
    protected $signature = 'system:delete-unverified-users';

    protected $description = 'Bereinigt unverifizierte Kunden-Accounts ohne Bestellungen, Angebotsanfragen oder Support-Vorgänge nach 24 Stunden.';

    public function handle(): int
    {
        $limit = now()->subHours(24);
        $deletedCount = 0;

        // Sicherheitsregel: Admins und Mitarbeiter unterliegen keiner öffentlichen Registrierung und
        // dürfen niemals automatisch per Cronjob gelöscht werden.
        // Ebenso werden Kunden mit Bestellungen, B2B-Angebotsanfragen, Support-Tickets oder Support-Chats strikt geschützt.
        $customers = Customer::query()
            ->where('created_at', '<', $limit)
            ->whereNull('email_verified_at')
            ->where(function ($query) {
                $query->whereDoesntHave('profile')
                    ->orWhereHas('profile', function ($q) {
                        $q->whereNull('email_verified_at');
                    });
            })
            ->doesntHave('orders')
            ->get();

        foreach ($customers as $customer) {
            $hasTicket = Schema::hasTable('support_tickets')
                && SupportTicket::where('customer_id', $customer->id)->exists();
            $hasChat = Schema::hasTable('support_customer_chats')
                && SupportCustomerChat::where('customer_id', $customer->id)->exists();
            $hasQuote = Schema::hasTable('order_quote_requests')
                && OrderQuoteRequest::query()
                    ->where('customer_id', $customer->id)
                    ->orWhere('email', $customer->email)
                    ->exists();

            if ($hasTicket || $hasChat || $hasQuote) {
                continue;
            }

            $customer->profile()->withTrashed()->forceDelete();
            $customer->forceDelete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:cleanup_unverified',
                'title' => 'Unverifizierte Benutzer gelöscht',
                'message' => "Der Cronjob hat {$deletedCount} unverifizierte Kunden-Konten ohne Aktivität (> 24 Std.) endgültig gelöscht.",
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            $this->info("Deleted {$deletedCount} unverified users.");
        } else {
            $this->info('No unverified users to delete.');
        }

        return self::SUCCESS;
    }
}
