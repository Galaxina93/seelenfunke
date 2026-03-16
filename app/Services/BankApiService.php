<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BankApiService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $webFormUrl;

    public function __construct()
    {
        $this->clientId = env('FINAPI_CLIENT_ID');
        $this->clientSecret = env('FINAPI_CLIENT_SECRET');
        $env = env('FINAPI_ENV', 'live'); // Auf Live zwingen laut User Wunsch

        // Die "Access" API
        $this->baseUrl = $env === 'live' ? 'https://live.finapi.io' : 'https://sandbox.finapi.io';

        // Die "Web Form 2.0" API (Hat eine eigene Subdomain!)
        $this->webFormUrl = $env === 'live' ? 'https://webform-live.finapi.io' : 'https://webform-sandbox.finapi.io';
    }

    /**
     * Holt den globalen Client-Token (OAuth) - Jetzt über die strikte V2 Route
     */
    private function getClientToken()
    {
        $response = Http::asForm()->post("{$this->baseUrl}/api/v2/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($response->failed()) {
            throw new \Exception('Konnte keinen finAPI Client-Token abrufen: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Generiert ein sicheres, immer gleiches Passwort für den finAPI User basierend auf deiner APP_KEY
     */
    private function generateUserPassword($userId)
    {
        return substr(hash_hmac('sha256', 'finapi_user_' . $userId, env('APP_KEY')), 0, 16) . 'A1!';
    }

    /**
     * Holt den Token für den spezifischen User. Wenn der User bei finAPI nicht existiert, wird er erstellt.
     */
    public function getUserToken($userId)
    {
        // FIX 1: Die ID darf maximal 36 Zeichen lang sein.
        // Wir hashen die User-ID per md5 (ergibt 32 Zeichen) und setzen ein "sf_" davor.
        // Ergibt immer exakt 35 Zeichen, egal ob du normale IDs oder lange UUIDs nutzt!
        $finapiUserId = 'sf_' . md5($userId);
        $password = $this->generateUserPassword($userId);

        // 1. Versuch: User Token abrufen (V2 Route)
        $response = Http::asForm()->post("{$this->baseUrl}/api/v2/oauth/token", [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $finapiUserId,
            'password' => $password,
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        // 2. Wenn fehlgeschlagen: User existiert vermutlich nicht. Wir erstellen ihn.
        $clientToken = $this->getClientToken();

        $createResponse = Http::withToken($clientToken)->post("{$this->baseUrl}/api/v2/users", [
            'id' => $finapiUserId,
            'password' => $password,
            'email' => auth()->user()->email ?? 'admin@mein-seelenfunke.de',
            // FIX 2: Das 'phone'-Feld haben wir komplett gelöscht, da leere Strings verboten sind
            'isAutoUpdateEnabled' => true
        ]);

        if ($createResponse->failed()) {
            throw new \Exception('Konnte finAPI User nicht erstellen: ' . $createResponse->body());
        }

        // 3. Neuer Versuch, den Token zu holen (V2 Route)
        $response = Http::asForm()->post("{$this->baseUrl}/api/v2/oauth/token", [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $finapiUserId,
            'password' => $password,
        ]);

        return $response->json('access_token');
    }

    /**
     * Erstellt einen WebForm-Link, um eine neue Bankverbindung (Bank Connection) hinzuzufügen
     */
    public function createBankImportWebForm($userToken, $redirectUrl)
    {
        // FIX: Nutzt jetzt $this->webFormUrl und den korrekten Pfad ohne /v2/
        $response = Http::withToken($userToken)->post("{$this->webFormUrl}/api/webForms/bankConnectionImport", [
            'bankConnectionName' => 'Mein Geschäftskonto',
            'callbacks' => [
                'finalised' => $redirectUrl // Leitet dich nach dem Login zurück zum Dashboard
            ]
        ]);

        if ($response->failed()) {
            throw new \Exception('Konnte finAPI WebForm nicht erstellen: ' . $response->body());
        }

        return $response->json('url');
    }

    public function getAccounts($userToken)
    {
        // 1. Hole alle BankConnections um die echten Banknamen zuzuordnen
        $connsResponse = Http::withToken($userToken)->get("{$this->baseUrl}/api/v2/bankConnections");
        $bankNames = [];
        if ($connsResponse->successful()) {
            $connections = $connsResponse->json('connections') ?? [];
            foreach ($connections as $conn) {
                // $conn['bank']['name'] enthält den echten Namen z.B. "Sparkasse"
                $bankNames[$conn['id']] = $conn['bank']['name'] ?? 'Unbekannte Bank';
            }
        }

        // 2. Hole alle Accounts
        $response = Http::withToken($userToken)->get("{$this->baseUrl}/api/v2/accounts");

        if ($response->failed()) {
            throw new \Exception('Fehler beim Abrufen der finAPI Konten: ' . $response->body());
        }

        $accounts = $response->json('accounts') ?? [];
        
        // 3. Mappe den echten Banknamen in das Account Array
        foreach ($accounts as &$acc) {
            $connId = $acc['bankConnectionId'] ?? null;
            $acc['bankName'] = $connId && isset($bankNames[$connId]) ? $bankNames[$connId] : 'Meine Bank';
        }

        return $accounts;
    }

    /**
     * Stößt ein manuelles Update (Sync) einer Bankverbindung an
     */
    public function updateBankConnection($userToken, $bankConnectionId)
    {
        $response = Http::withToken($userToken)->post("{$this->baseUrl}/api/v2/bankConnections/update", [
            'bankConnectionId' => (int) $bankConnectionId,
            'importNewAccounts' => true,
        ]);

        return $response->successful();
    }

    /**
     * Holt Transaktionen eines spezifischen Kontos 
     * @param string $userToken
     * @param int|string $accountId (Die finAPI Account ID, hier im System als plaid_account_id)
     * @param int $limit Default: 50
     */
    public function getTransactions($userToken, $accountId, $limit = 50)
    {
        $response = Http::withToken($userToken)->get("{$this->baseUrl}/api/v2/transactions", [
            'view' => 'userView',
            'accountIds' => $accountId,
            'order' => 'bankBookingDate,desc',
            'perPage' => $limit
        ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('finAPI Transactions Fetch Fehler: ' . $response->body());
            return [];
        }

        return $response->json('transactions') ?? [];
    }
}
