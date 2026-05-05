<?php

namespace App\Livewire\Shop\Ai\PartialsAiWorkspace;

use Livewire\Component;
use App\Models\System\SystemCronjob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

class TabCronjobs extends Component
{
    public $editingJobId = null;
    public $editingSchedule = '';

    #[Computed]
    public function cronjobs()
    {
        return SystemCronjob::orderBy('name')->get();
    }

    public function editSchedule($id)
    {
        $job = SystemCronjob::find($id);
        if ($job) {
            $this->editingJobId = $job->id;
            $this->editingSchedule = $job->schedule;
        }
    }

    public function cancelEdit()
    {
        $this->editingJobId = null;
        $this->editingSchedule = '';
    }

    public function saveSchedule()
    {
        if (!$this->editingJobId) return;
        
        $job = SystemCronjob::find($this->editingJobId);
        if ($job) {
            $job->schedule = trim($this->editingSchedule);
            $job->save();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Intervall für "' . $job->name . '" wurde erfolgreich gespeichert.'
            ]);
        }
        
        $this->cancelEdit();
    }

    public function toggleCronjob($id)
    {
        $job = SystemCronjob::find($id);
        if ($job) {
            $job->is_active = !$job->is_active;
            $job->save();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Cronjob "' . $job->name . '" wurde ' . ($job->is_active ? 'aktiviert' : 'pausiert') . '.'
            ]);
        }
    }

    public function runNow($id)
    {
        $job = SystemCronjob::find($id);
        if ($job) {
            $job->update(['status' => 'running']);
            
            try {
                $parameters = $job->parameters ? explode(' ', $job->parameters) : [];
                Artisan::call($job->command, $parameters);
                
                $job->update([
                    'status' => 'success',
                    'last_run_at' => now(),
                ]);
                
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Cronjob "' . $job->name . '" erfolgreich ausgeführt!'
                ]);
            } catch (\Exception $e) {
                $job->update([
                    'status' => 'error',
                    'last_run_at' => now(),
                ]);
                
                Log::error("Manuelle Cronjob-Ausführung fehlgeschlagen: " . $e->getMessage());
                
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Fehler bei der Ausführung: ' . $e->getMessage()
                ]);
            }
        }
    }

    public function render()
    {
        return view('livewire.shop.ai.partials-ai-workspace.tab-cronjobs');
    }
}
