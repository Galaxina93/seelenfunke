<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('support:auto-resolve-chats')]
#[Description('Automatically closes inactive support chats after 12 hours')]
class AutoResolveSupportChats extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = \App\Models\Support\SupportCustomerChat::whereIn('status', ['open', 'needs_employee'])
            ->where('updated_at', '<', now()->subHours(12))
            ->update(['status' => 'resolved_auto']);
            
        $this->info("Auto-resolved {$count} inactive chats.");
    }
}
