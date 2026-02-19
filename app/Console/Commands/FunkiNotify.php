<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FunkiBotService;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;

class FunkiNotify extends Command
{
    protected $signature = 'funki:notify';
    protected $description = 'Sendet Push Notifications an App';

    public function handle(FunkiBotService $service)
    {
        $data = $service->getUltimateCommand();

        // Caching Logik (Nur bei Änderung oder High Priority)
        $currentHash = md5($data['title'] . $data['message']);
        $lastHash = Cache::get('funki_last_push_hash');

        if ($lastHash !== $currentHash || $data['score'] >= 500) {

            $this->sendFCMNotification(
                $data['title'],
                $data['message'],
                $data // Ganze Daten als Payload mitsenden
            );

            Cache::put('funki_last_push_hash', $currentHash, now()->addMinutes(30)); // 30 Min Ruhe
            $this->info('Push gesendet: ' . $data['title']);
        }
    }

    private function sendFCMNotification($title, $body, $dataPayload)
    {
        // 1. Google Client Auth
        $client = new GoogleClient();
        $client->setAuthConfig(storage_path('app/firebase_credentials.json'));
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];

        // 2. Alle registrierten Devices holen
        $tokens = UserDevice::pluck('fcm_token')->toArray();

        if (empty($tokens)) return;

        // 3. Senden (FCM v1 API)
        $projectId = json_decode(file_get_contents(storage_path('app/firebase_credentials.json')))->project_id;
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $dataPayload['icon'] . ' ' . $title,
                        'body'  => $body,
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'score' => (string)$dataPayload['score'],
                        'route' => $dataPayload['action_route']
                    ]
                ]
            ];

            Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        }
    }
}
