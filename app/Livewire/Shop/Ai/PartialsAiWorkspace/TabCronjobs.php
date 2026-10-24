<?php

namespace App\Livewire\Shop\Ai\PartialsAiWorkspace;

use Livewire\Component;
use App\Models\System\SystemCronjob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

class TabCronjobs extends Component
{
    #[\Livewire\Attributes\Locked]
    public $editingJobId = null;
    public $editingSchedule = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

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
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
        if (!$this->editingJobId) return;

        $schedule = trim((string) $this->editingSchedule);
        $allowedScheduleMethods = [
            'everyMinute', 'everyTwoMinutes', 'everyThreeMinutes', 'everyFourMinutes', 'everyFiveMinutes',
            'everyTenMinutes', 'everyFifteenMinutes', 'everyThirtyMinutes',
            'hourly', 'everyTwoHours', 'everyThreeHours', 'everyFourHours', 'everySixHours',
            'daily', 'weekly', 'monthly', 'quarterly', 'yearly',
        ];
        $isAllowedMethod = in_array($schedule, $allowedScheduleMethods, true);
        $isValidCron = preg_match('/^[0-9*\/,\-]+(\s+[0-9*\/,\-]+){4}$/', $schedule) === 1
            && (!class_exists(\Cron\CronExpression::class) || \Cron\CronExpression::isValidExpression($schedule));

        if ($schedule === '' || strlen($schedule) > 100 || (!$isAllowedMethod && !$isValidCron)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ungültiger Cron-Ausdruck oder nicht unterstütztes Intervall.'
            ]);
            return;
        }
        
        $job = SystemCronjob::find($this->editingJobId);
        if ($job) {
            $job->schedule = $schedule;
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
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
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
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
        $job = SystemCronjob::find($id);
        if ($job) {
            $commandClean = trim((string) $job->command);
            $commandClean = preg_replace('/^(?:\/usr\/bin\/)?php(?:[0-9.]+)?\s+/', '', $commandClean);
            $commandClean = preg_replace('/^(?:(?:\S+)?artisan)\s+/', '', $commandClean);
            $commandClean = trim((string) $commandClean);

            $blockedCommands = ['reverb:start', 'queue:work', 'queue:listen', 'websockets:serve', 'migrate:fresh', 'migrate:reset', 'db:wipe', 'tinker'];
            if ($commandClean === '' || !preg_match('/^[a-zA-Z0-9:_-]+$/', $commandClean) || in_array($commandClean, $blockedCommands, true)) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Dieser Befehl darf nicht synchron über das Dashboard ausgeführt werden.'
                ]);
                return;
            }

            $job->update(['status' => 'running']);
            
            try {
                $parameters = [];
                $blockedOpts = ['--env', '--database', '--path', '--realpath', '--bootstrap'];
                if (!empty($job->parameters)) {
                    foreach (preg_split('/\s+/', trim((string) $job->parameters), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                        if (str_starts_with($token, '--')) {
                            if (str_contains($token, '=')) {
                                [$optKey, $optVal] = explode('=', $token, 2);
                                if (preg_match('/^--[a-zA-Z0-9_-]+$/', $optKey) && !in_array(strtolower($optKey), $blockedOpts, true)) {
                                    $parameters[$optKey] = $optVal;
                                }
                            } elseif (preg_match('/^--[a-zA-Z0-9_-]+$/', $token) && !in_array(strtolower($token), $blockedOpts, true)) {
                                $parameters[$token] = true;
                            }
                        }
                    }
                }
                Artisan::call($commandClean, $parameters);
                
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
