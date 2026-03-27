<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemSchedulerLog extends Model
{
    protected $table = 'system_scheduler_logs';
    protected $fillable = ['task_id', 'task_name', 'started_at', 'finished_at', 'status', 'output'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];
}
