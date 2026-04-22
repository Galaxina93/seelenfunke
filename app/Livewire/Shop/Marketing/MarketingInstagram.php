<?php

namespace App\Livewire\Shop\Marketing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Ai\AiAgent;
use App\Models\Marketing\MarketingInstagramPost;
use Illuminate\Support\Str;

#[Layout('components.layouts.backend_layout')]
class MarketingInstagram extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;
    use \Livewire\WithFileUploads;

    public string $themingDepartment = 'Marketing';
    public $isGenerating = false;
    public $isGeneratingForPost = [];
    public $photos = [];

    public function render()
    {
        // Hole den Marketing-Agenten
        $marketingDepartmentId = '019d2222-2222-2222-2222-222222222222';
        $agent = AiAgent::where('ai_department_id', $marketingDepartmentId)
            ->where('is_active', true)
            ->first();

        // Lade alle Posts absteigend
        $posts = MarketingInstagramPost::orderBy('created_at', 'desc')->get();

        return view('livewire.shop.marketing.marketing-instagram', [
            'agent' => $agent,
            'posts' => $posts
        ]);
    }

    public function createDraftPost()
    {
        // Hole den Marketing-Agent dynamisch (anhand Name, oder einfach den ersten aktiven als Fallback)
        $agent = AiAgent::where('name', 'like', '%Marketi%')
            ->orWhere('name', 'like', '%Marketing%')
            ->where('is_active', true)
            ->first();

        if (!$agent) {
            $agent = AiAgent::where('is_active', true)->first();
        }

        // Erstelle eine komplett leere Vorlage
        $post = MarketingInstagramPost::create([
            'ai_agent_id' => $agent ? $agent->id : null,
            'caption' => '',
            'hashtags' => [],
            'status' => 'draft',
            'image_url' => null
        ]);
        
        $this->dispatch('post-draft-created');
    }

    public function updatedPhotos($value, $postId)
    {
        $post = MarketingInstagramPost::find($postId);
        if (!$post || !isset($this->photos[$postId])) {
            return;
        }

        $photo = $this->photos[$postId];
        
        // Verzeichnis für diesen spezifischen Post erstellen
        $directory = 'marketing/instagram/posts/' . $post->id;
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($directory)) {
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($directory);
        }

        // Speichern der physikalischen Datei als image.jpg (überschreibt ggf. existierende)
        $path = $photo->storeAs($directory, 'image.jpg', 'local');

        $post->update([
            'image_url' => $path
        ]);

        unset($this->photos[$postId]); // Clean up upload state
    }

    public function generateCaptionForPost($postId)
    {
        $post = MarketingInstagramPost::find($postId);
        if (!$post || !$post->image_url) {
            return;
        }

        $this->isGeneratingForPost[$postId] = true;

        $agent = AiAgent::where('name', 'like', '%Marketi%')
            ->orWhere('name', 'like', '%Marketing%')
            ->where('is_active', true)
            ->first() ?? AiAgent::where('is_active', true)->first();

        // Absoluter Serverpfad zum hochgeladenen Bild
        $absoluteImagePath = storage_path('app/' . $post->image_url);

        try {
            if (!file_exists($absoluteImagePath)) {
                throw new \Exception("Bilddatei nicht gefunden.");
            }

            // Wir konvertieren das Bild intern zu Base64 für den Prompt (Standard Vision Weg)
            $mimeType = mime_content_type($absoluteImagePath);
            $base64Image = base64_encode(file_get_contents($absoluteImagePath));
            
            $prompt = "Wir betreiben den Online-Shop 'Seelenfunke' für erstklassige 3D-Glasinnengravuren (B2B Trophäen, Jahrestagsgeschenke, Technik). 
Bitte analysiere dieses Bild und schreibe einen komplett neuen, kreativen und absolut professionellen Instagram-Post passend zum Foto.
Antworte EXAKT in valide formatierter, roher JSON Struktur ohne Markdown-Tags wie ```json:
{
  \"caption\": \"Hier den post-text mit passenden Emojis und konkretem Bezug zum Bild\",
  \"hashtags\": [\"#Beispiel1\", \"#Beispiel2\"],
  \"image_keyword\": \"\"
}";
            
            // Nutze die processVisionPrompt Funktion (die wir gleich im GeminiAgent implementieren)
            $responseString = \App\Services\AI\GeminiAgent::processVisionPrompt($agent, $prompt, $base64Image, $mimeType);
            
            // Wenn die Vision-API der Proxysumgebung limitiert ist (z.B. LiteLLM Proxy lehnt base64 URI ab), 
            // fallen wir auf das fantastische Text-Modell zurück für dynamischen Content (statt statischem Text)!
            if (str_contains($responseString, 'Fehler') || str_contains($responseString, 'Systemintegrität gestört')) {
                 $textPrompt = "Ich teile gerade ein neues tolles Foto einer 3D-Glasinnengravur (Seelenfunke) auf unserem Unternehmens-Instagram.
Bitte schreibe mir dafür einen extrem hochwertigen, absolut einzigartigen und unterschiedlichen B2B Marketing-Text! 
Variiere die Themen immer wieder mal zufällig (Mitarbeiter-Award, Firmenjubiläum, Handwerkskunst in München, oder emotionale Geschenke).
Antworte EXAKT in valide formatierter, roher JSON Struktur ohne Markdown-Tags wie ```json:
{
  \"caption\": \"Hier den komplett neuen post-text mit passenden Emojis\",
  \"hashtags\": [\"#Seelenfunke\", \"#Mitarbeiterbindung\", \"#Beispiel\"]
}";
                 $responseString = \App\Services\AI\AiAgentFactory::processDirectPrompt($agent, $textPrompt);
            }

            // JSON extrahieren (falls AI mit Text wie "Hier ist dein JSON: \n ```json..." antwortet)
            preg_match('/\{[\s\S]*\}/', $responseString, $matches);
            $jsonString = $matches[0] ?? $responseString;
            
            $idea = json_decode($jsonString, true);
            if (!$idea || !isset($idea['caption'])) {
                throw new \Exception("JSON Parse failed from Agent: " . substr($responseString, 0, 100));
            }

            $post->update([
                'caption' => $idea['caption'],
                'hashtags' => $idea['hashtags'] ?? []
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Instagram Vision AI Error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            // Absolute Fallback Liste, falls die KI API offline ist
            $post->update([
                'caption' => "Ein fantastischer neuer Blick in die Seelenfunke-Manufaktur ✨\n\nUnsere 3D-Glasinnengravuren bringen Erinnerungen in eine völlig neue Dimension.\n\n#Seelenfunke #Handwerk",
                'hashtags' => ['#Seelenfunke', '#Handwerk', '#Manufaktur']
            ]);
        }
        
        $this->isGeneratingForPost[$postId] = false;
    }

    public function deletePost($id)
    {
        $post = MarketingInstagramPost::find($id);
        if ($post) {
            // Delete local directory
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("marketing/marketing/instagram/posts/{$post->id}");
            $post->delete();
        }
    }

    public function togglePublishPost($id)
    {
        $post = MarketingInstagramPost::find($id);
        if ($post) {
            if ($post->status === 'published') {
                $post->status = 'draft';
            } else {
                $post->status = 'published';
            }
            $post->save();
        }
    }
}
