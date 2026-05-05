<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;

class SystemHeartbeatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the scheduler heartbeat cache for the health dashboard';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            \Illuminate\Support\Facades\Cache::put('scheduler_last_run', now());
            $this->info('Heartbeat recorded.');
        } catch (\Exception $e) {
            // Lokal per CLI können Berechtigungsfehler auf Cache-Dateien von www-data auftreten.
            // Diese werden hier stumm geschaltet, um ein "FAIL" im Ausgabefenster zu verhindern.
            $this->error('Failed to record heartbeat: ' . $e->getMessage());
        }
    }
}
