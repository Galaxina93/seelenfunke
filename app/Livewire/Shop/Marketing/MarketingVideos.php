<?php
 
namespace App\Livewire\Shop\Marketing;
 
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Ai\AiAgent;
use App\Models\Marketing\MarketingVideo;
 
#[Layout('components.layouts.backend_layout')]
class MarketingVideos extends Component
{
    use \App\Livewire\Traits\WithDepartmentTheming;
    use \Livewire\WithFileUploads;
 
    public string $themingDepartment = 'Marketing';
    public $videoFile;
    public $activeVideoId;
 
    // Fields for current editing template
    public $title = 'mein-seelenfunke';
    public $subtitle = 'EIN FUNKE, DER BLEIBT';
    public $themeColor = '#C5A059';
    public $hasParticles = true;
    public $aspectRatio = '16:9';
    public $config = [];

    // Fields for new project creation form
    public string $newTitle = '';
    public string $newSubtitle = '';
    public string $newAspectRatio = '16:9';
    public string $newDuration = '6';
    public string $newDurationCustom = '';
    public string $newDesignMode = 'seelenfunke';
 
    protected $listeners = ['video-saved' => '$refresh'];
 
    public function mount()
    {
        $this->config = $this->getDefaultConfig();
    }
 
    public function render()
    {
        // Get the Marketing AI Agent
        $marketingDepartmentId = '019d2222-2222-2222-2222-222222222222';
        $agent = AiAgent::where('ai_department_id', $marketingDepartmentId)
            ->where('is_active', true)
            ->first();
 
        // Load active and archived marketing videos
        $videos = MarketingVideo::where('status', '!=', 'archived')->orderBy('created_at', 'desc')->get();
        $archivedVideos = MarketingVideo::where('status', 'archived')->orderBy('created_at', 'desc')->get();
 
        return view('livewire.shop.marketing.marketing-videos', [
            'agent' => $agent,
            'videos' => $videos,
            'archivedVideos' => $archivedVideos
        ]);
    }
 
    public function getDefaultConfig()
    {
        return $this->getDefaultConfigWithDuration(6.0);
    }

    public function getDefaultConfigWithDuration($duration)
    {
        $isSeelenfunke = ($this->newDesignMode === 'seelenfunke');
        
        return [
            [
                'id' => 'metadata',
                'aspectRatio' => $this->aspectRatio,
                'duration' => $duration
            ],
            [
                'id' => 'layer-bg',
                'name' => 'Hintergrund',
                'type' => 'background',
                'color' => $isSeelenfunke ? '#FAF9F6' : '#111827',
                'gradientColor' => $this->themeColor,
                'useGradient' => $isSeelenfunke,
                'opacity' => 1.0,
                'startTime' => 0.0,
                'endTime' => $duration
            ],
            [
                'id' => 'layer-particles',
                'name' => 'Seelenfunken',
                'type' => 'particles',
                'particleType' => 'sparks',
                'color' => $this->themeColor,
                'x' => 480,
                'y' => 160,
                'width' => 120,
                'height' => 120,
                'opacity' => $this->hasParticles ? 0.8 : 0.0,
                'startTime' => 1.8,
                'endTime' => $duration
            ],
            [
                'id' => 'layer-logo',
                'name' => 'Marken-Logo',
                'type' => 'image',
                'imageUrl' => $isSeelenfunke ? 'shop/projekt/logo/mein-seelenfunke-logo.png' : 'shop/ai/images/funkira_selfie.png',
                'x' => 480,
                'y' => 160,
                'width' => 120,
                'height' => 120,
                'opacity' => 1.0,
                'startTime' => 0.2,
                'endTime' => $duration,
                'animation' => 'fade'
            ],
            [
                'id' => 'layer-title',
                'name' => 'Haupttitel',
                'type' => 'text',
                'text' => $this->title,
                'x' => 480,
                'y' => 370,
                'fontSize' => 32,
                'color' => $this->themeColor,
                'fontFamily' => $isSeelenfunke ? 'Playfair Display' : 'Outfit',
                'opacity' => 1.0,
                'startTime' => 2.3,
                'endTime' => $duration,
                'animation' => 'fade'
            ],
            [
                'id' => 'layer-subtitle',
                'name' => 'Slogan',
                'type' => 'text',
                'text' => $this->subtitle,
                'x' => 480,
                'y' => 405,
                'fontSize' => 12,
                'color' => $isSeelenfunke ? '#5C5549' : '#9CA3AF',
                'fontFamily' => 'Outfit',
                'opacity' => 1.0,
                'startTime' => 2.6,
                'endTime' => $duration,
                'animation' => 'fade'
            ]
        ];
    }

    public function createDraftVideo()
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newSubtitle' => 'nullable|string|max:255',
            'newAspectRatio' => 'required|string|in:16:9,9:16,1:1,4:5,2:3',
            'newDuration' => 'required|string',
            'newDurationCustom' => 'required_if:newDuration,custom|nullable|numeric|min:1',
            'newDesignMode' => 'required|string|in:seelenfunke,standard'
        ]);

        $agent = AiAgent::where('name', 'like', '%Marketi%')
            ->orWhere('name', 'like', '%Marketing%')
            ->where('is_active', true)
            ->first() ?? AiAgent::where('is_active', true)->first();

        $finalDuration = 6.0;
        if ($this->newDuration === 'custom') {
            $finalDuration = floatval($this->newDurationCustom) ?: 6.0;
        } else {
            $finalDuration = floatval($this->newDuration);
        }

        if ($this->newDesignMode === 'seelenfunke') {
            $this->themeColor = '#C5A059';
            $this->hasParticles = true;
        } else {
            $this->themeColor = '#3B82F6';
            $this->hasParticles = false;
        }

        $this->title = $this->newTitle;
        $this->subtitle = $this->newSubtitle ?: ($this->newDesignMode === 'seelenfunke' ? 'EIN FUNKE, DER BLEIBT' : '');
        $this->aspectRatio = $this->newAspectRatio;
        
        $this->config = $this->getDefaultConfigWithDuration($finalDuration);

        $video = MarketingVideo::create([
            'ai_agent_id' => $agent ? $agent->id : null,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'theme_color' => $this->themeColor,
            'has_particles' => $this->hasParticles,
            'config' => $this->config,
            'status' => 'draft',
            'video_path' => null
        ]);

        $this->activeVideoId = $video->id;
        $this->newTitle = '';
        $this->newSubtitle = '';

        $this->dispatch('video-draft-created', ['id' => $video->id]);
    }
 
    public function saveVideoConfig($id, $title, $subtitle, $themeColor, $hasParticles, $configData)
    {
        $video = MarketingVideo::find($id);
        if ($video) {
            $parsedConfig = is_string($configData) ? json_decode($configData, true) : $configData;
            $video->update([
                'title' => $title,
                'subtitle' => $subtitle,
                'theme_color' => $themeColor,
                'has_particles' => (bool)$hasParticles,
                'config' => $parsedConfig
            ]);
            $this->dispatch('config-saved', ['message' => 'Konfiguration erfolgreich gespeichert!']);
        }
    }
 
    public function updatedVideoFile()
    {
        if (!$this->activeVideoId || !$this->videoFile) {
            return;
        }
 
        $video = MarketingVideo::find($this->activeVideoId);
        if ($video) {
            $directory = 'marketing/marketing/videos/' . $video->id;
            
            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($directory)) {
                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($directory);
            }
 
            // Save binary blob as video.webm
            $path = $this->videoFile->storeAs($directory, 'video.webm', 'local');
 
            $video->update([
                'video_path' => $path,
                'status' => 'completed'
            ]);
        }
 
        $this->reset(['activeVideoId', 'videoFile']);
        $this->dispatch('video-saved');
    }
 
    public function loadVideoTemplate($id)
    {
        $video = MarketingVideo::find($id);
        if ($video) {
            $this->activeVideoId = $video->id;
            $this->title = $video->title;
            $this->subtitle = $video->subtitle;
            $this->themeColor = $video->theme_color;
            $this->hasParticles = $video->has_particles;
            $this->config = $video->config ?? $this->getDefaultConfig();
            
            // Read aspect ratio from config metadata layer if present
            $aspectRatio = '16:9';
            if (is_array($this->config)) {
                foreach ($this->config as $layer) {
                    if (isset($layer['id']) && $layer['id'] === 'metadata' && isset($layer['aspectRatio'])) {
                        $aspectRatio = $layer['aspectRatio'];
                        break;
                    }
                }
            }
            $this->aspectRatio = $aspectRatio;
            
            $this->dispatch('video-loaded', [
                'id' => $video->id,
                'title' => $video->title,
                'subtitle' => $video->subtitle,
                'themeColor' => $video->theme_color,
                'hasParticles' => $video->has_particles,
                'aspectRatio' => $this->aspectRatio,
                'config' => $this->config
            ]);
        }
    }
 
    public function deleteVideo($id)
    {
        $video = MarketingVideo::find($id);
        if ($video) {
            $video->update(['status' => 'archived']);
            
            if ($this->activeVideoId === $id) {
                $this->reset(['activeVideoId']);
                $this->config = $this->getDefaultConfig();
            }
            $this->dispatch('video-saved');
        }
    }

    public function restoreVideo($id)
    {
        $video = MarketingVideo::find($id);
        if ($video) {
            $video->update(['status' => 'draft']);
            $this->dispatch('video-saved');
        }
    }

    public function forceDeleteVideo($id)
    {
        $video = MarketingVideo::find($id);
        if ($video) {
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory("marketing/marketing/videos/{$video->id}");
            $video->delete();
            
            if ($this->activeVideoId === $id) {
                $this->reset(['activeVideoId']);
                $this->config = $this->getDefaultConfig();
            }
            $this->dispatch('video-saved');
        }
    }

    public function closeProject()
    {
        $this->reset(['activeVideoId', 'title', 'subtitle', 'themeColor', 'hasParticles', 'aspectRatio', 'newAspectRatio', 'config']);
    }

    public function renameProject($id, $newTitle)
    {
        $video = MarketingVideo::find($id);
        if ($video && !empty($newTitle)) {
            $config = $video->config;
            if (is_array($config)) {
                foreach ($config as &$layer) {
                    if ($layer['id'] === 'layer-title' || $layer['id'] === 'l-t1') {
                        $layer['text'] = $newTitle;
                    }
                }
            }
            $video->update([
                'title' => $newTitle,
                'config' => $config
            ]);

            if ($this->activeVideoId === $id) {
                $this->title = $newTitle;
                $this->config = $config;
                $this->dispatch('video-loaded', [
                    'id' => $video->id,
                    'title' => $video->title,
                    'subtitle' => $video->subtitle,
                    'themeColor' => $video->theme_color,
                    'hasParticles' => $video->has_particles,
                    'aspectRatio' => $this->aspectRatio,
                    'config' => $config
                ]);
            }
            $this->dispatch('video-saved');
        }
    }
}
