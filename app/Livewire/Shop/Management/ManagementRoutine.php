<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;

use App\Models\Management\ManagementDayRoutine as RoutineModel;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ManagementRoutine extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public $routines;
    public $isEditing = false;
    public $filterDays = 'all'; // 'all', 'weekdays', 'weekend'

    // Form fields
    #[\Livewire\Attributes\Locked]
    public $r_id;
    public $r_time, $r_title, $r_message, $r_duration, $r_type = 'general', $r_days_of_week = 'all';

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function render()
    {
        $query = RoutineModel::orderBy('start_time');

        if ($this->filterDays === 'weekdays') {
            $query->where(function($q) {
                $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
            });
        } elseif ($this->filterDays === 'weekend') {
            $query->where(function($q) {
                $q->whereIn('days_of_week', ['all', 'weekend'])->orWhereNull('days_of_week');
            });
        }

        $this->routines = $query->get();
        return view('livewire.shop.management.management-routine');
    }

    public function toggleActive($id)
    {
        $routine = RoutineModel::find($id);
        if ($routine) {
            $routine->is_active = !$routine->is_active;
            $routine->save();
        }
    }

    public function create()
    {
        $this->resetInput();
        $this->isEditing = true;
    }

    public function edit($id)
    {
        $r = RoutineModel::find($id);
        if (!$r) return;
        $this->r_id = $r->id;
        $this->r_time = \Carbon\Carbon::parse($r->start_time)->format('H:i');
        $this->r_title = $r->title;
        $this->r_message = $r->message;
        $this->r_duration = $r->duration_minutes;
        $this->r_type = $r->type ?? 'general';
        $this->r_days_of_week = $r->days_of_week ?? 'all';
        $this->isEditing = true;
    }

    public function save()
    {
        $this->validate([
            'r_time' => 'required',
            'r_title' => 'required|string|max:255',
            'r_message' => 'nullable|string|max:2000',
            'r_duration' => 'required|integer|min:1|max:1440',
            'r_type' => 'required|string|max:50',
            'r_days_of_week' => 'nullable|string|in:all,weekdays,weekend'
        ]);

        $existing = $this->r_id ? RoutineModel::find($this->r_id) : null;
        $attributes = [
            'start_time' => $this->r_time,
            'title' => $this->r_title,
            'message' => $this->r_message,
            'duration_minutes' => (int) $this->r_duration,
            'type' => $this->r_type,
            'days_of_week' => $this->r_days_of_week ?? 'all',
            'is_active' => $existing ? $existing->is_active : true,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            RoutineModel::create($attributes);
        }

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
        $this->r_type = 'general';
        $this->r_days_of_week = 'all';
    }
}
