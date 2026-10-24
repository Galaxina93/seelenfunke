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
    #[\Livewire\Attributes\Locked]
    public MarketingLandingPage $landingPage;

    public function mount($slug)
    {
        $this->landingPage = MarketingLandingPage::with('product')->where('slug', $slug)->firstOrFail();

        // Security check: Draft landing pages or inactive products must not be publicly visible to customers/guests
        $isAdmin = auth()->guard('admin')->check() || \App\Services\AI\AiAuthHelper::isAdmin();
        if (!$isAdmin) {
            if ($this->landingPage->status !== 'active') {
                abort(404);
            }
            if ($this->landingPage->product && $this->landingPage->product->status !== 'active') {
                abort(404);
            }
        }

        // Tracker logic (DSGVO-konform mit App-Key-Salt und Längenbegrenzung)
        try {
            $pathFilter = '/l/' . mb_substr((string) $slug, 0, 180);
            $ipHash = hash('sha256', (string) Request::ip() . config('app.key'));
            
            $alreadyVisited = PageVisit::where('path', $pathFilter)
                ->where('ip_hash', $ipHash)
                ->where('created_at', '>=', now()->subHour())
                ->exists();

            if (!$alreadyVisited) {
                $safeUrl = preg_replace('/([?&](?:token|signature|password|code|key)=)[^&]+/i', '$1[REDACTED]', (string) Request::fullUrl());
                $safeReferer = Request::header('referer')
                    ? preg_replace('/([?&](?:token|signature|password|code|key)=)[^&]+/i', '$1[REDACTED]', (string) Request::header('referer'))
                    : null;

                PageVisit::create([
                    'session_id' => mb_substr((string) (session()->getId() ?: 'no-session'), 0, 100),
                    'ip_hash' => $ipHash,
                    'url' => mb_substr((string) $safeUrl, 0, 500),
                    'path' => $pathFilter,
                    'method' => mb_substr((string) Request::method(), 0, 10),
                    'user_agent' => Request::userAgent() ? mb_substr((string) Request::userAgent(), 0, 500) : null,
                    'referer' => $safeReferer ? mb_substr((string) $safeReferer, 0, 500) : null,
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
