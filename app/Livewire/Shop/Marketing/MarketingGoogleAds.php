<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Product\Product;
use App\Models\Marketing\MarketingGoogleAdsCampaign;

#[Layout('components.layouts.backend_layout')]
class MarketingGoogleAds extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;

    public $themingDepartment = 'Marketing';
    public $loadingMessage = '';
    public $actionError = '';

    public function generateCampaign($productId)
    {
        $this->actionError = '';
        try {
            $product = Product::findOrFail($productId);

            if (MarketingGoogleAdsCampaign::where('product_id', $product->id)->exists()) {
                $this->actionError = 'Für dieses Produkt existiert bereits eine Google Ads Kampagne.';
                return;
            }

            $this->loadingMessage = 'KI generiert Zielgruppen & Ads für ' . $product->name . '...';

            $agent = \App\Models\Ai\AiAgent::where('name', 'like', '%Marketi%')
                ->orWhere('name', 'like', '%Marketing%')
                ->where('is_active', true)
                ->first() ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();

            if (!$agent) {
                $this->actionError = 'Es ist kein aktiver Marketing KI-Agent konfiguriert.';
                $this->loadingMessage = '';
                return;
            }

            $prompt = "Du bist ein Google Ads PPC Suchnetzwerk Experte für seelenfunke.com (Manufaktur für gravierte personalisierte Glas-Geschenke).\n"
                    . "Analysiere das folgende Produkt und erstelle eine hochperformante Google Ads Kampagne:\n"
                    . "Produkt: " . $product->name . "\n"
                    . "Preis: " . $product->formatted_price . "\n"
                    . "Beschreibung: " . $product->description . "\n\n"
                    . "PPC Richtlinien (STRIKT BEACHTEN!):\n"
                    . "- Headlines MAXIMAL 30 Zeichen inkl. Leerzeichen! (Werden live abgelehnt sonst)\n"
                    . "- Descriptions MAXIMAL 90 Zeichen inkl. Leerzeichen!\n"
                    . "- Wir brauchen ca. 10 exakte Target-Keywords, die Nutzer suchen würden (z.B. 'Glasfoto gravieren').\n"
                    . "- Wir brauchen mind. 15 Negative-Keywords, um Streuverlust zu meiden (z.B. 'kostenlos', 'selber machen', 'billig').\n\n"
                    . "Gib exakt ein JSON-Objekt zurück:\n"
                    . "{\n"
                    . "  \"campaign_name\": \"[Produktname] - Search - DE\",\n"
                    . "  \"ad_group_name\": \"Generisch - [Produktname]\",\n"
                    . "  \"keywords\": [\"keyword 1\", \"keyword 2\"],\n"
                    . "  \"negative_keywords\": [\"kostenlos\", \"stornieren\"],\n"
                    . "  \"headline_1\": \"Max 30 Zeichen\",\n"
                    . "  \"headline_2\": \"Max 30 Zeichen\",\n"
                    . "  \"headline_3\": \"Max 30 Zeichen\",\n"
                    . "  \"description_1\": \"Max 90 Zeichen Beschreibender Text\",\n"
                    . "  \"description_2\": \"Max 90 Zeichen Call to Action Text\"\n"
                    . "}";

            // Bypass OpenAI Wrapper completely und nutze natives Gemini 2.5 Flash mit JSON-Mode,
            // um die massiven 503 "High Demand" Timeouts und Markdown-Parsierungsfehler abzufangen!
            $apiKey = config('services.gemini.key');
            $googleUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
            
            $systemInstruction = $agent ? ($agent->system_prompt ?? '') : 'Du bist ein Senior Performance Marketing Manager.';
            
            $flashPayload = [
                'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'responseMimeType' => 'application/json', // Zwinge striktes JSON ohne Markdown!
                ]
            ];

            $ch = curl_init($googleUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($flashPayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $responseString = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode !== 200 || !$responseString) {
                \Illuminate\Support\Facades\Log::error("Google Ads Native API Error: {$httpCode} | cURL: {$curlError} | Resp: {$responseString}");
                $this->actionError = 'Die Google KI-Server sind aktuell stark ausgelastet (Timeout). Bitte versuche es später noch einmal!';
                $this->loadingMessage = '';
                return;
            }

            $responseArray = json_decode($responseString, true);
            $text = $responseArray['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$text) {
                $this->actionError = 'Unerwartetes API-Format von Google.';
                $this->loadingMessage = '';
                return;
            }

            $data = json_decode($text, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['headline_1'])) {
                \Illuminate\Support\Facades\Log::error("JSON Parsing Error nach nativem Aufruf: " . json_last_error_msg());
                $this->actionError = 'Die KI hat ein fehlerhaftes (unlesbares) Format geliefert (kein JSON).';
                $this->loadingMessage = '';
                return;
            }

            // Fallback truncation logic to guarantee limits if AI hallucinates
            MarketingGoogleAdsCampaign::create([
                'product_id' => $product->id,
                'campaign_name' => $data['campaign_name'] ?? ($product->name . ' - Search'),
                'ad_group_name' => $data['ad_group_name'] ?? 'Generic',
                'keywords' => $data['keywords'] ?? [],
                'negative_keywords' => $data['negative_keywords'] ?? [],
                'headline_1' => mb_substr($data['headline_1'] ?? 'Jetzt kaufen', 0, 30),
                'headline_2' => mb_substr($data['headline_2'] ?? 'Top Qualität', 0, 30),
                'headline_3' => mb_substr($data['headline_3'] ?? 'Seelenfunke', 0, 30),
                'description_1' => mb_substr($data['description_1'] ?? 'Hochwertige Glasgeschenke direkt aus unserer Manufaktur.', 0, 90),
                'description_2' => mb_substr($data['description_2'] ?? 'Jetzt online konfigurieren.', 0, 90),
                'status' => 'draft',
            ]);

            $this->actionError = '';
            $this->loadingMessage = '';
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Google Ads Kampagne für ' . $product->name . ' erstellt!']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Google Ads Generation Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->actionError = 'Systemfehler: ' . $e->getMessage();
            $this->loadingMessage = '';
        }
    }

    public function render()
    {
        $products = Product::with('googleAdsCampaign')->where('status', 'active')->get();

        $agent = \App\Models\Ai\AiAgent::where('name', 'like', '%Marketi%')
                ->orWhere('name', 'like', '%Marketing%')
                ->where('is_active', true)
                ->first() ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();

        return view('livewire.shop.marketing.marketing-google-ads', [
            'products' => $products,
            'agent' => $agent
        ]);
    }
}
