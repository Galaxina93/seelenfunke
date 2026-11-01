<?php

namespace App\Models\System;


use App\Support\Blueprint\Concerns\InteractsWithSystemBlueprint;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\ActorScope;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\LifecycleBehavior;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SystemCronjob extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Geplanter Hintergrundprozess (Cronjob)',
            description: 'Verwaltung und Zeitplan wiederkehrender Automatisierungsaufgaben (Pruning, Backups, Autopilot).',
            department: SystemDepartment::System,
            navigationNodes: ['admin.system.cronjobs'],
            lifecycle: LifecycleBehavior::MasterData,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::PermanentMasterData,
            aiAccess: AiAccessPolicy::ReadOnly,
            actorsRead: [ActorScope::Admin],
            actorsWrite: [ActorScope::Admin],
            regulations: [],
            externalChannels: [],
            dependsOnSettings: [],
            isGdprArt15Exportable: false,
            customerForeignKey: null,
            containsProductionAssets: false,
            assetColumns: [],
        );
    }

use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'command',
        'parameters',
        'schedule',
        'is_active',
        'last_run_at',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function getNextRunAtAttribute()
    {
        $schedule = $this->schedule;
        if (!$schedule || !$this->is_active) {
            return null;
        }

        $cronExpression = match($schedule) {
            'everyMinute' => '* * * * *',
            'everyTwoMinutes' => '*/2 * * * *',
            'everyThreeMinutes' => '*/3 * * * *',
            'everyFourMinutes' => '*/4 * * * *',
            'everyFiveMinutes' => '*/5 * * * *',
            'everyTenMinutes' => '*/10 * * * *',
            'everyFifteenMinutes' => '*/15 * * * *',
            'everyThirtyMinutes' => '*/30 * * * *',
            'hourly' => '0 * * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *',
            'yearly' => '0 0 1 1 *',
            default => $schedule,
        };

        try {
            $cron = new \Cron\CronExpression($cronExpression);
            return \Carbon\Carbon::instance($cron->getNextRunDate());
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPreviousRunAtAttribute()
    {
        $schedule = $this->schedule;
        if (!$schedule || !$this->is_active) {
            return null;
        }

        $cronExpression = match($schedule) {
            'everyMinute' => '* * * * *',
            'everyTwoMinutes' => '*/2 * * * *',
            'everyThreeMinutes' => '*/3 * * * *',
            'everyFourMinutes' => '*/4 * * * *',
            'everyFiveMinutes' => '*/5 * * * *',
            'everyTenMinutes' => '*/10 * * * *',
            'everyFifteenMinutes' => '*/15 * * * *',
            'everyThirtyMinutes' => '*/30 * * * *',
            'hourly' => '0 * * * *',
            'daily' => '0 0 * * *',
            'weekly' => '0 0 * * 0',
            'monthly' => '0 0 1 * *',
            'yearly' => '0 0 1 1 *',
            default => $schedule,
        };

        try {
            $cron = new \Cron\CronExpression($cronExpression);
            // Get previous run date relative to now
            return \Carbon\Carbon::instance($cron->getPreviousRunDate(now(), 0, true));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getProgressPercentAttribute()
    {
        $next = $this->next_run_at;
        $last = $this->last_run_at ?? $this->previous_run_at;
        
        if (!$next || !$last) return 0;

        $total = $next->timestamp - $last->timestamp;
        $current = now()->timestamp - $last->timestamp;
        
        if ($total <= 0) return 100;
        if ($current < 0) return 0;
        if ($current > $total) return 100;
        
        return ($current / $total) * 100;
    }
}
