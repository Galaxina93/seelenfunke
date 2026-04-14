<?php

namespace App\Livewire\Traits;

use App\Models\Ai\AiDepartment;
use Illuminate\Support\Facades\Cache;

trait WithDepartmentTheming
{
    public function getThemeColorClassProperty()
    {
        $departmentName = property_exists($this, 'themingDepartment') ? $this->themingDepartment : 'Marketing';
        $cacheKey = strtolower($departmentName) . '_dept_class';

        return Cache::remember($cacheKey, 300, function () use ($departmentName) {
            $dept = AiDepartment::where('name', $departmentName)
                        ->orWhere('name', rtrim($departmentName, 'e'))
                        ->first();
            return $dept ? $dept->color : 'primary';
        });
    }
    public function getThemeColorHexProperty()
    {
        $departmentName = property_exists($this, 'themingDepartment') ? $this->themingDepartment : 'Marketing';
        $cacheKey = strtolower($departmentName) . '_dept_color';

        // Cache the color lookup for 5 minutes to avoid DB bottlenecks 
        // when Livewire performs many roundtrips (like updating an input)
        $color = Cache::remember($cacheKey, 300, function () use ($departmentName) {
            $dept = AiDepartment::where('name', $departmentName)
                        ->orWhere('name', rtrim($departmentName, 'e'))
                        ->first();
            return $dept ? $dept->color : 'primary';
        });

        // Map Tailwind classes to Hex codes natively so we can use them in CSS vars
        return match ($color) {
            'cyan-500' => '#06b6d4',
            'emerald-500' => '#10b981',
            'blue-500' => '#3b82f6',
            'indigo-500' => '#6366f1',
            'purple-500' => '#a855f7',
            'pink-500' => '#ec4899',
            'rose-500' => '#f43f5e',
            'red-500' => '#ef4444',
            'orange-500' => '#f97316',
            'amber-500' => '#f59e0b',
            'yellow-500' => '#eab308',
            'green-500' => '#22c55e',
            'sky-500' => '#0ea5e9',
            'primary' => '#c5a059',
            default => '#c5a059'
        };
    }
}
