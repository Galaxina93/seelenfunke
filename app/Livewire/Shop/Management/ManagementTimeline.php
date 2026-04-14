<?php

namespace App\Livewire\Shop\Management;

use App\Models\Management\ManagementTimelineEvent;
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ManagementTimeline extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public $events = [];
    public $showModal = false;
    public $editingId = null;

    // Form fields
    public $title;
    public $description;
    public $start_date;
    public $end_date;
    public $type = 'event';
    public $impact_level = 'medium';

    protected $rules = [
        'title' => 'required|min:3',
        'start_date' => 'required|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'type' => 'required|in:milestone,roadblock,event,phase',
        'impact_level' => 'required|in:high,medium,low',
    ];

    public function mount()
    {
        $this->loadEvents();
    }

    public function loadEvents()
    {
        $this->events = ManagementTimelineEvent::orderBy('start_date', 'asc')->get();
    }

    public function create()
    {
        $this->reset(['editingId', 'title', 'description', 'start_date', 'end_date', 'type', 'impact_level']);
        $this->start_date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function edit($id)
    {
        $event = ManagementTimelineEvent::findOrFail($id);
        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->description = $event->description;
        $this->start_date = $event->start_date?->format('Y-m-d');
        $this->end_date = $event->end_date?->format('Y-m-d');
        $this->type = $event->type;
        $this->impact_level = $event->impact_level;
        
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date ?: null,
            'type' => $this->type,
            'impact_level' => $this->impact_level,
        ];

        if ($this->editingId) {
            ManagementTimelineEvent::where('id', $this->editingId)->update($data);
            session()->flash('success', 'Ereignis erfolgreich aktualisiert.');
        } else {
            ManagementTimelineEvent::create($data);
            session()->flash('success', 'Ereignis erfolgreich erstellt.');
        }

        $this->showModal = false;
        $this->loadEvents();
    }

    public function delete()
    {
        if ($this->editingId) {
            ManagementTimelineEvent::destroy($this->editingId);
            session()->flash('success', 'Ereignis gelöscht.');
        }
        
        $this->showModal = false;
        $this->loadEvents();
    }

    public function render()
    {
        return view('livewire.shop.management.management-timeline');
    }
}
