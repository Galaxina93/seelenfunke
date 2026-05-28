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

        $types = [
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
        ];

        foreach ($types as $type => $class) {
            // Finde alle User, die vor mehr als 24 Stunden erstellt wurden und deren Profil kein email_verified_at hat.
            $users = $class::withTrashed()
                ->where('created_at', '<', $limit)
                ->whereHas('profile', function ($q) {
                    $q->whereNull('email_verified_at');
                })
                ->get();

            foreach ($users as $user) {
                // Falls ein Profil existiert, dieses zuerst permanent löschen
                if ($user->profile) {
                    $user->profile()->forceDelete();
                }
                
                // Benutzer permanent löschen
                $user->forceDelete();
                $deletedCount++;
            }
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
