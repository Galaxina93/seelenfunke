<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\System\SystemLog;

class DeleteUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:delete-unverified-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Löscht alle unverifizierten Benutzer (Admins, Kunden, Mitarbeiter), die älter als 24 Stunden sind.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = now()->subHours(24);
        $deletedCount = 0;

        // Sicherheitsregel: Admins und Mitarbeiter unterliegen keiner öffentlichen Registrierung und
        // keinem MustVerifyEmail-Workflow. Sie dürfen niemals automatisch per Cronjob gelöscht werden.
        // Nur unverifizierte Kunden ohne Bestellungen werden nach Ablauf von 24 Stunden bereinigt.
        $customers = Customer::withTrashed()
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
            if ($customer->profile) {
                $customer->profile()->forceDelete();
            }
            $customer->forceDelete();
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:cleanup_unverified',
                'title' => 'Unverifizierte Benutzer gelöscht',
                'message' => "Der Cronjob hat {$deletedCount} unverifizierte Benutzer, die älter als 24 Stunden waren, endgültig gelöscht.",
                'status' => 'success',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            $this->info("Deleted {$deletedCount} unverified users.");
        } else {
            $this->info("No unverified users to delete.");
        }

        return self::SUCCESS;
    }
}
