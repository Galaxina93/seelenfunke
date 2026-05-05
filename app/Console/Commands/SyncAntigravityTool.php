<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ai\AiTool;
use App\Models\Ai\AiAgent;

class SyncAntigravityTool extends Command
{
    protected $signature = 'seelenfunke:sync-antigravity';
    protected $description = 'Register Antigravity tool and assign to all agents';

    public function handle()
    {
        $tool = AiTool::firstOrCreate(
            ['identifier' => 'system_send_to_antigravity'],
            [
                'name' => 'Send to Antigravity',
                'description' => 'Ermöglicht dem Agenten direkt mit Antigravity zu kommunizieren.',
                'department_id' => 1 // Just give it some department
            ]
        );

        foreach(AiAgent::all() as $agent) {
            $agent->tools()->syncWithoutDetaching([$tool->id]);
        }

        $this->info('Tool registriert und allen Agenten zugewiesen.');
    }
}
