<?php

namespace App\Livewire\Shop\Blog;

use App\Models\Blog\BlogPost;
use Illuminate\Support\Str;
use Livewire\Component;

class BlogFrontendShow extends Component
{
    public $post;

    public function mount($slug)
    {
        // Wir suchen den Post anhand des Slugs.
        // WICHTIG: Wir nutzen auch hier ->published(), damit niemand unveröffentlichte Posts erraten kann.
        $this->post = BlogPost::published()
            ->where('slug', $slug)
            ->with(['author', 'category'])
            ->firstOrFail();
    }

    public function render()
    {
        return view('livewire.shop.blog.frontend-show')
            ->layout('components.layouts.frontend_layout')
            ->title($this->post->seo_title . ' - Mein Seelenfunke Magazin')
            ->with([
                // Meta Description dynamisch setzen für das Layout
                'metaDescription' => $this->post->meta_description ?? Str::limit($this->post->excerpt, 155)
            ]);
    }
}
