<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTimeEntry extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'employee_time_entries';

    protected $fillable = [
        'contract_id',
        'employee_id',
        'work_date',
        'started_at',
        'ended_at',
        'break_minutes',
        'net_minutes',
        'entry_type',
        'activity_note',
        'status',
        'arbzg_compliant',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'break_minutes' => 'integer',
        'net_minutes' => 'integer',
        'arbzg_compliant' => 'boolean',
    ];

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id')->withTrashed();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function getFormattedHoursAttribute(): string
    {
        $hours = intdiv((int) $this->net_minutes, 60);
        $mins = ((int) $this->net_minutes) % 60;

        return sprintf('%d:%02d Std.', $hours, $mins);
    }

    public function getDecimalHoursAttribute(): float
    {
        return round(((int) $this->net_minutes) / 60, 2);
    }
}
