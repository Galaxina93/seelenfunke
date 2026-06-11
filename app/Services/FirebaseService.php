<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;

class FirebaseService
{
    protected $projectId;
    protected $credentialsFile;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id', 'funkiapp-55960');
        $this->credentialsFile = config('services.firebase.credentials_file', storage_path('app/firebase-service-account.json'));
    }

    /**
     * Get OAuth2 access token using the service account JSON
     */
    protected function getAccessToken(): ?string
    {
        if (!file_exists($this->credentialsFile)) {
            Log::error("Firebase credentials file not found at: " . $this->credentialsFile);
            return null;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->credentialsFile);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->fetchAccessTokenWithAssertion();
            $token = $client->getAccessToken();
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            Log::error("Error fetching Firebase access token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send push notification to a specific token
     */
    public function sendPushNotification(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // Build the FCM payload
        $message = [
            'token' => $deviceToken,
            'data' => array_map('strval', $data), // FCM data values must be strings
            'android' => [
                'priority' => 'high',
            ],
        ];

        if (!empty($title) || !empty($body)) {
            $message['notification'] = [
                'title' => $title,
                'body' => $body,
            ];
            
            $isOrder = isset($data['order_id']);
            $channelId = $isOrder ? 'orders_notification_channel_v4' : 'default_notification_channel';
            $sound = $isOrder ? 'order_ching' : 'default';

            $message['android']['notification'] = [
                'sound' => $sound,
                'channel_id' => $channelId,
            ];
        }

        $payload = [
            'message' => $message
        ];

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info("FCM notification sent successfully to token: " . substr($deviceToken, 0, 10) . "...");
                return true;
            } else {
                Log::error("FCM send failed: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("FCM send exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all registered Admin devices
     */
    public function sendToAdmins(string $title, string $body, array $data = []): int
    {
        // Find all admins
        $admins = \App\Models\Admin\Admin::all();
        $sentCount = 0;

        foreach ($admins as $admin) {
            // Find devices for this admin
            $devices = \App\Models\System\SystemUserDevice::where('userable_id', $admin->id)
                ->where('userable_type', get_class($admin))
                ->get();

            foreach ($devices as $device) {
                if ($device->fcm_token) {
                    $success = $this->sendPushNotification($device->fcm_token, $title, $body, $data);
                    if ($success) {
                        $sentCount++;
                    }
                }
            }
        }

        return $sentCount;
    }
}
