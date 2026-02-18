<?php

namespace App\Livewire\Shop\Funki;

use App\Models\DayRoutine as RoutineModel;
use Livewire\Component;
use Illuminate\Support\Str;

class DayRoutine extends Component
{
    public $routines;
    public $isEditing = false;

    // Form fields
    public $r_id, $r_time, $r_title, $r_message, $r_duration;

    public function render()
    {
        $this->routines = RoutineModel::orderBy('start_time')->get();
        return view('livewire.shop.funki.day-routine');
    }

    public function create()
    {
        $this->resetInput();
        $this->isEditing = true;
    }

    public function edit($id)
    {
        $r = RoutineModel::find($id);
        $this->r_id = $r->id;
        $this->r_time = \Carbon\Carbon::parse($r->start_time)->format('H:i');
        $this->r_title = $r->title;
        $this->r_message = $r->message;
        $this->r_duration = $r->duration_minutes;
        $this->isEditing = true;
    }

    public function save()
    {
        $this->validate([
            'r_time' => 'required',
            'r_title' => 'required',
            'r_duration' => 'required|integer'
        ]);

        RoutineModel::updateOrCreate(
            ['id' => $this->r_id ?? (string) Str::uuid()],
            [
                'start_time' => $this->r_time,
                'title' => $this->r_title,
                'message' => $this->r_message,
                'duration_minutes' => $this->r_duration,
                'type' => 'general', // Simplified for manual entry
                'is_active' => true
            ]
        );

        $this->isEditing = false;
        $this->resetInput();
    }

    public function delete($id)
    {
        RoutineModel::destroy($id);
    }

    public function cancel()
    {
        $this->isEditing = false;
        $this->resetInput();
    }

    private function resetInput()
    {
        $this->reset(['r_id', 'r_time', 'r_title', 'r_message', 'r_duration']);
    }
}
