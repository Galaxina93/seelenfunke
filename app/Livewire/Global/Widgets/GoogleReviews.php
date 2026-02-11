<?php

namespace App\Livewire\Global\Widgets;

use App\Services\GoogleReviewsService;
use Livewire\Component;

class GoogleReviews extends Component
{
    public array $reviewsData = [];

    public function mount(GoogleReviewsService $service)
    {
        // Daten holen (kommen aus dem Cache oder frisch von Google)
        $this->reviewsData = $service->getReviews();
    }

    public function render()
    {
        return view('livewire.global.widgets.google-reviews');
    }
}
