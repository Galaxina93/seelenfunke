<?php

namespace App\Models\Management;


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

class ManagementCalendarEvent extends Model implements HasSystemBlueprint
{
    
    use InteractsWithSystemBlueprint;

    /**
     * Digitales Architektur- & Compliance-Typenschild (Living Documentation).
     */
    public static function blueprint(): ModelBlueprint
    {
        return ModelBlueprint::make(
            label: 'Management- & Produktions-Kalendertermin',
            description: 'Termine, Fristen, Lieferanten-Lieferfenster und Saison-Kampagnen im Unternehmenskalender.',
            department: SystemDepartment::Management,
            navigationNodes: ['admin.management.calendar'],
            lifecycle: LifecycleBehavior::TransactionalWorkflow,
            sensitivity: DataSensitivity::InternalErp,
            retention: RetentionPolicy::StatutoryWarranty_3Years,
            aiAccess: AiAccessPolicy::AutonomousReadWrite,
            actorsRead: [ActorScope::Admin, ActorScope::EmployeeProduction],
            actorsWrite: [ActorScope::Admin, ActorScope::EmployeeProduction],
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

    protected $table = 'management_calendar_events';

    protected $fillable = [
        'title', 'start_date', 'end_date', 'is_all_day',
        'recurrence', 'recurrence_end_date',
        'reminder_minutes',
        'category', 'description', 'ics_uid', 'send_email'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'recurrence_end_date' => 'date',
        'is_all_day' => 'boolean',
        'send_email' => 'boolean',
    ];

    // Helper: Ist es ein wiederkehrender Termin?
    public function isRecurring()
    {
        return !empty($this->recurrence);
    }

    protected static function booted()
    {
        static::created(function ($event) {
            self::sendSyncNotification();

            if (!$event->send_email) {
                return;
            }
            // Find Alina or fallback to the first Admin
            $recipient = \App\Models\Admin\Admin::where('first_name', 'like', '%Alina%')->first();
            if (!$recipient) {
                $recipient = \App\Models\Admin\Admin::first();
            }

            if ($recipient && $recipient->email) {
                \Illuminate\Support\Facades\Mail::to($recipient->email)
                    ->send(new \App\Mail\CalendarEventCreated($event));
            }
        });

        static::updated(function ($event) {
            self::sendSyncNotification();
        });

        static::deleted(function ($event) {
            self::sendSyncNotification();
        });
    }

    protected static function sendSyncNotification()
    {
        try {
            $firebase = resolve(\App\Services\FirebaseService::class);
            $firebase->sendToAdmins("", "", [
                'action' => 'sync_calendar'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("FCM Calendar Sync Trigger Error: " . $e->getMessage());
        }
    }
}
