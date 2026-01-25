<?php

namespace App\Livewire\Global\Widgets;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

class HeaderSlider extends Component
{
    public array $sliderConfig = [
        'direction' => 'horizontal',
        'loop' => true,
        'allowTouchMove' => false,
        'pagination' => ['el' => '.swiper-pagination', 'clickable' => false],
        // 'navigation' => ['nextEl' => '.swiper-button-next', 'prevEl' => '.swiper-button-prev'],
        // 'scrollbar' => ['el' => '.swiper-scrollbar', 'draggable' => true],
        'autoplay' => ['delay' => 4000],
    ];

    public array $config = [
        'width' => '1920px',
        'height' => '800px',
        'pagination_active' => false,
        'navigation_active' => false,
        'scrollbar_active' => false,
        'image_title_active' => true,
        'image_description_active' => false,
    ];

    public array $slides = [];

    public function mount()
    {
        $this->slides = collect(File::files(public_path('images/slider')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['jpg', 'jpeg', 'png', 'webp']))
            ->values()
            ->map(function ($file) {
                $filename = $file->getFilenameWithoutExtension();
                return [
                    'title' => Str::headline($filename), // macht z. B. "zaunbau" → "Zaunbau"
                    'description' => '', // oder optional leer lassen
                    'image' => '/images/slider/' . $file->getFilename(),
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.widgets.header-slider');
    }
}
