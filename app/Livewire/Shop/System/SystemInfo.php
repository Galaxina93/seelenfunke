<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

#[Layout('components.layouts.backend_layout')]
class SystemInfo extends Component
{
    use WithFileUploads, \App\Livewire\Traits\WithDepartmentTheming;

    protected string $themingDepartment = 'System';

    public $uploads = [];
    public $selectedReports = [];
    public $reportFiles = [];

    public function mount()
    {
        $this->loadReports();
    }

    public function loadReports()
    {
        try {
            Storage::makeDirectory('reports/laravel-updates');
            $files = Storage::files('reports/laravel-updates');
            rsort($files);
            $this->reportFiles = $files;
        } catch (\Exception $e) {
            $this->reportFiles = [];
        }
    }

    public function toggleReport($filename)
    {
        if (in_array($filename, $this->selectedReports)) {
            $this->selectedReports = array_diff($this->selectedReports, [$filename]);
        } else {
            $this->selectedReports[] = $filename;
        }
    }

    public function saveUploads()
    {
        $this->validate([
            'uploads.*' => 'file|max:10240', // 10MB max per file
        ]);

        foreach ($this->uploads as $file) {
            $filename = $file->getClientOriginalName();
            // Store directly in our updates folder
            $file->storeAs('reports/laravel-updates', $filename);
        }

        $this->uploads = [];
        $this->loadReports();
        
        session()->flash('message', 'Dateien erfolgreich hochgeladen.');
    }

    public function deleteReport($filename)
    {
        if (Storage::exists($filename)) {
            Storage::delete($filename);
            $this->selectedReports = array_diff($this->selectedReports, [$filename]);
            $this->loadReports();
            session()->flash('message', 'Bericht gelöscht.');
        }
    }

    public function getReportContent($filename)
    {
        if (Storage::exists($filename)) {
            return Storage::get($filename);
        }
        return 'Fehler: Bericht nicht gefunden.';
    }

    public function render()
    {
        $laravelVersion = app()->version();
        $phpVersion = PHP_VERSION;

        return view('livewire.shop.system.system-info', [
            'laravelVersion' => $laravelVersion,
            'phpVersion' => $phpVersion,
        ]);
    }
}
