<?php

namespace App\Livewire\Landing;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Tracking\PageVisit;
use Illuminate\Support\Facades\Request;

#[Layout('components.layouts.frontend_layout')]
class LandingPageView extends Component
{
    public MarketingLandingPage $landingPage;

    public function mount($slug)
    {
        $this->landingPage = MarketingLandingPage::with('product')->where('slug', $slug)->firstOrFail();

        // Tracker logic
        try {
            $pathFilter = '/l/' . $slug;
            $ipHash = hash('sha256', Request::ip());
            
            $alreadyVisited = PageVisit::where('path', $pathFilter)
                ->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if (!$alreadyVisited) {
                PageVisit::create([
                    'session_id' => session()->getId() ?? 'no-session',
                    'ip_hash' => $ipHash,
                    'url' => Request::fullUrl(),
                    'path' => $pathFilter,
                    'method' => Request::method(),
                    'user_agent' => Request::userAgent(),
                    'referer' => Request::header('referer'),
                ]);
            }
        } catch (\Exception $e) {
            // Fehlertoleranz
        }
    }

    public function render()
    {
        return view('livewire.landing.landing-page-view');
    }
}
