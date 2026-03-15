<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiChatMemory;
use Carbon\Carbon;

class SearchChatHistory
{
    /**
     * @return array
     */
    public static function schema(): array
    {
        return [
            "type" => "function",
            "function" => [
                "name" => "search_chat_history",
                "description" => "Suche in deinen alten Chat-Logs und Erinnerungen nach Themen, an die du dich erinnern sollst (z.B. worüber gestern gesprochen wurde). Nutze dies IMMER, wenn der User nach einer früheren Unterhaltung fragt.",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "time_filter" => [
                            "type" => "string",
                            "description" => "Zeitraum Filter. Erlaubt: 'today', 'yesterday', 'last_week', 'all' (Standard: 'all')",
                            "enum" => ["today", "yesterday", "last_week", "all"]
                        ],
                        "keyword" => [
                            "type" => "string",
                            "description" => "Ein optionales Suchwort, um die Historie einzugrenzen."
                        ]
                    ],
                    "required" => ["time_filter"]
                ]
            ]
        ];
    }

    /**
     * @param array $args
     * @return array
     */
    public static function call(array $args): array
    {
        $timeFilter = $args['time_filter'] ?? 'all';
        $keyword = $args['keyword'] ?? null;

        // Hole alle Chat Memories der aktuellen Session (oder global vom aktuellen Benutzer)
        // Einfachheitshalber filtern wir nach Datum und dem Keyword weltweit (falls es nur eine Alina gibt)
        $query = AiChatMemory::query()->orderBy('created_at', 'desc');

        // Zeitfilter
        switch ($timeFilter) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'yesterday':
                $query->whereDate('created_at', Carbon::yesterday());
                break;
            case 'last_week':
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
                break;
            // 'all' ignores date
        }

        // Suchwort
        if ($keyword) {
            $query->where('content', 'like', '%' . $keyword . '%');
        }

        // Limitiere auf die letzten 50 Einträge, um den Context nicht zu sprengen
        $memories = $query->limit(50)->get();

        if ($memories->isEmpty()) {
            return [
                'status' => 'empty',
                'message' => 'Es wurden keine passenden Erinnerungen oder Logs zu dieser Suchanfrage in der Datenbank gefunden.'
            ];
        }

        // Formatieren für die AI
        $formattedLogs = $memories->map(function ($m) {
            return "[{$m->created_at->format('d.m. H:i')}] - Rolle: {$m->role} - Inhalt: {$m->content}";
        })->implode("\n");

        return [
            'status' => 'success',
            'summary' => 'Folgende Protokoll-Fetzen wurden in deinem Langzeitgedächtnis gefunden (neueste zuerst):',
            'logs' => $formattedLogs
        ];
    }
}
