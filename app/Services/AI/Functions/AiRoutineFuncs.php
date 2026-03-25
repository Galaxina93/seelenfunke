<?php

namespace App\Services\AI\Functions;

use App\Models\Management\DayRoutine;

trait AiRoutineFuncs
{
    public static function getAiRoutineFuncsSchema(): array
    {
        return [
            [
                'name' => 'routine_get_day_routines',
                'description' => 'Ruft die aktiven, strukturierten Tagesroutinen ab. Stichworte: Wie sieht meine Routine aus, Meine Morning Routine, Tagesablauf, Abendroutine, was steht heute an.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetDayRoutines']
            ]
        ];
    }

    public static function executeGetDayRoutines(array $args)
    {
        try {
            $routines = DayRoutine::where('is_active', true)
                ->with(['steps' => function($q) {
                    $q->select('day_routine_id', 'title', 'duration_minutes', 'position');
                }])
                ->orderBy('start_time', 'asc')
                ->get(['id', 'title', 'start_time', 'duration_minutes', 'type']);

            return [
                'status' => 'success',
                'active_routines_count' => $routines->count(),
                'routines' => $routines->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
