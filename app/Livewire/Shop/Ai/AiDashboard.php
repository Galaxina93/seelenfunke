<?php

namespace App\Livewire\Shop\Ai;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.backend_layout')]
class AiDashboard extends Component
{
    #[Url(history: true, keep: true)]
    public $activeTab = 'agents';

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $tabs = [
            'analytics' => [
                'name' => 'Analyse',
                'icon' => 'chart-bar'
            ],
            'roles' => [
                'name' => 'Rollen',
                'icon' => 'briefcase'
            ],
            'agents' => [
                'name' => 'Agenten',
                'icon' => 'cpu-chip'
            ],
            'chat' => [
                'name' => 'Chat',
                'icon' => 'chat-bubble-left-ellipsis'
            ],
            'wiki' => [
                'name' => 'Wiki',
                'icon' => 'book-open'
            ],
            'genui' => [
                'name' => 'Gen-UI',
                'icon' => 'window'
            ],
        ];

        return view('livewire.shop.ai.ai-dashboard', [
            'tabs' => $tabs
        ]);
    }
}
