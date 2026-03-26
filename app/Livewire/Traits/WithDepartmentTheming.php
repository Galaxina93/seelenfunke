<?php

namespace App\Livewire\Traits;

use App\Models\Ai\AiDepartment;
use Illuminate\Support\Facades\Cache;

trait WithDepartmentTheming
{
    public function getThemeColorHexProperty()
    {
        $departmentName = property_exists($this, 'themingDepartment') ? $this->themingDepartment : 'Marketing';
        $cacheKey = strtolower($departmentName) . '_dept_color';

        // Cache the color lookup for 5 minutes to avoid DB bottlenecks 
        // when Livewire performs many roundtrips (like updating an input)
        $color = Cache::remember($cacheKey, 300, function () use ($departmentName) {
            $dept = AiDepartment::where('name', $departmentName)->first();
            return $dept ? $dept->color : 'primary';
        });

        // Map Tailwind classes to Hex codes natively so we can use them in CSS vars
        return match ($color) {
            'blue-500' => '#3b82f6',
            'purple-500' => '#a855f7',
            'amber-500' => '#f59e0b',
            'emerald-500' => '#10b981',
            'red-500' => '#ef4444',
            'rose-500' => '#f43f5e',
            'cyan-500' => '#06b6d4',
            'primary' => '#c5a059',
            default => '#c5a059'
        };
    }
}
