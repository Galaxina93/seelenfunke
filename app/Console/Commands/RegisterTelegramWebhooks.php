<?php

namespace App\Console\Commands;

use App\Models\Ai\AiAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RegisterTelegramWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:register-webhooks {--domain= : The base URL for the webhook (e.g. https://seelenfunke.de)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers the Telegram Bot API Webhook dynamically for all AI Agents with a Token.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domain = $this->option('domain') ?: config('app.url');

        if (!str_starts_with($domain, 'https://')) {
            $this->error('Telegram Webhooks require a secure HTTPS Domain. Set --domain=https://... or APP_URL in .env');
            return Command::FAILURE;
        }

        $agents = AiAgent::whereNotNull('telegram_bot_token')->where('telegram_bot_token', '!=', '')->where('is_active', true)->get();

        if ($agents->isEmpty()) {
            $this->info('No active AI Agents with a Telegram Token found.');
            return Command::SUCCESS;
        }

        $this->info("Registering Webhooks on domain: {$domain}");

        foreach ($agents as $agent) {
            $token = $agent->telegram_bot_token;
            // The URL we tell telegram to push to
            $webhookUrl = rtrim($domain, '/') . "/api/telegram/webhook/{$token}";

            $this->line("Registering Agent: <comment>{$agent->name}</comment>...");
            
            $telegramResponse = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
            ]);

            if ($telegramResponse->successful()) {
                $this->info("✅ Success! Webhook active.");
            } else {
                $this->error("❌ Failed! Telegram API responded with:");
                $this->error($telegramResponse->body());
            }
        }

        return Command::SUCCESS;
    }
}
