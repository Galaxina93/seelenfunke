<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineStep;
use App\Models\Management\ManagementDayRoutineLog;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Str;

Route::prefix('funki/routine')->group(function () {

    // 0. Get & Sync Funkenwerk Android Alarm / Timer / Wake-Up Package
    $defaultAlarmPackage = function (): array {
        return [
            'last_synced_at' => null,
            'device_name' => 'Funkenwerk Android',
            'active_alarm_time' => '08:30',
            'active_alarm_label' => 'Jeden Tag',
            'alarms' => [
                [
                    'id' => 'alarm_0645',
                    'hour' => 6,
                    'minute' => 45,
                    'label' => 'Frühschicht / Manufaktur',
                    'schedule_text' => 'Jeden Tag',
                    'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                    'is_enabled' => false,
                    'smart_plug_ips' => ['192.168.188.109'],
                    'trigger_plug_on_ring' => true,
                    'ai_briefing_enabled' => true,
                    'sync_with_routine' => true,
                ],
                [
                    'id' => 'alarm_0830',
                    'hour' => 8,
                    'minute' => 30,
                    'label' => 'Seelenfunke Fokus-Start',
                    'schedule_text' => 'Jeden Tag',
                    'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                    'is_enabled' => true,
                    'smart_plug_ips' => ['192.168.188.109', '192.168.188.110'],
                    'trigger_plug_on_ring' => true,
                    'ai_briefing_enabled' => true,
                    'sync_with_routine' => true,
                ],
                [
                    'id' => 'alarm_1300',
                    'hour' => 13,
                    'minute' => 0,
                    'label' => 'Mittags-Check / Express',
                    'schedule_text' => 'Nicht geplant',
                    'days_of_week' => [],
                    'is_enabled' => false,
                    'smart_plug_ips' => [],
                    'trigger_plug_on_ring' => false,
                    'ai_briefing_enabled' => false,
                    'sync_with_routine' => false,
                ],
            ],
            'timers' => [
                ['id' => 'timer_5', 'label' => 'Timer: 5 Min.', 'duration_seconds' => 300],
                ['id' => 'timer_10', 'label' => 'Timer: 10 Min.', 'duration_seconds' => 600],
                ['id' => 'timer_15', 'label' => 'Timer: 15 Min.', 'duration_seconds' => 900],
                ['id' => 'timer_20', 'label' => 'Timer: 20 Min.', 'duration_seconds' => 1200],
                ['id' => 'timer_30', 'label' => 'Timer: 30 Min.', 'duration_seconds' => 1800],
            ],
            'smart_plugs' => [
                [
                    'id' => 'c85151a0c0064f259ee18c352f626363',
                    'name' => 'Lichterkette Fußleiste',
                    'ip' => '192.168.188.109',
                    'mac' => '98-03-8E-7D-A7-96',
                    'model' => 'Tapo P100 (HW 2.0)',
                    'firmware' => '1.4.6 Build 260617 Rel.064743',
                    'room' => 'easy-AP · Heimnetz',
                ],
                [
                    'id' => 'dfb60661bf0945cf87cfe84f0e2db861',
                    'name' => 'Packtisch Warm Licht',
                    'ip' => '192.168.188.110',
                    'mac' => 'Tapo SHIP 2.0',
                    'model' => 'Tapo P100/P110',
                    'firmware' => '1.4.x',
                    'room' => 'easy-AP · Heimnetz',
                ],
                [
                    'id' => '53a51fd44692437f8669263ab58b2323',
                    'name' => 'Lichterkette Spiegel',
                    'ip' => '192.168.188.123',
                    'mac' => 'Tapo SHIP 2.0',
                    'model' => 'Tapo P100/P110',
                    'firmware' => '1.4.x',
                    'room' => 'easy-AP · Heimnetz',
                ],
            ],
            'wake_up_logs' => [],
        ];
    };

    $defaultSmartHomeConfig = function () use ($defaultAlarmPackage): array {
        return [
            'updated_at' => null,
            'grid_layout_count' => 4,
            'selected_camera_name' => 'Terasse',
            'plugs' => $defaultAlarmPackage()['smart_plugs'],
            'cameras' => [
                [
                    'id' => '433ccbb85a0a42b18304153cf95e3c7e',
                    'name' => 'Terasse',
                    'ip' => '192.168.188.105',
                    'stream_path' => '/stream2',
                    'service_url' => 'http://192.168.188.105/onvif/device_service',
                    'device_model' => 'Tapo C520WS',
                    'description' => 'Terrasse und Gartenbereich',
                    'motion_record' => true,
                    'motion_sensitivity' => 70,
                    'position' => 0,
                    'invert_pan' => true,
                    'invert_tilt' => false,
                ],
                [
                    'id' => 'c336115ddb5744ba9befa67cd668fbbc',
                    'name' => 'Einfahrt',
                    'ip' => '192.168.188.132',
                    'stream_path' => '/stream2',
                    'service_url' => 'http://192.168.188.132/onvif/device_service',
                    'device_model' => 'Tapo C520WS',
                    'description' => 'Hofeinfahrt und Eingangsbereich',
                    'motion_record' => false,
                    'motion_sensitivity' => 50,
                    'position' => 1,
                    'invert_pan' => true,
                    'invert_tilt' => false,
                ],
                [
                    'id' => '89314360083541a38f9e591453b5202a',
                    'name' => 'Garage',
                    'ip' => '192.168.188.102',
                    'stream_path' => '/stream2',
                    'service_url' => 'http://192.168.188.102/onvif/device_service',
                    'device_model' => 'Tapo C210',
                    'description' => 'Garagentor und Stellplatz',
                    'motion_record' => false,
                    'motion_sensitivity' => 50,
                    'position' => 2,
                    'invert_pan' => true,
                    'invert_tilt' => false,
                ],
                [
                    'id' => 'd0dd7c3b4b64431e9fe1ae3f2bf73331',
                    'name' => 'Gewerbe',
                    'ip' => '192.168.188.101',
                    'stream_path' => '/stream1',
                    'service_url' => 'http://192.168.188.101/onvif/device_service',
                    'device_model' => 'Tapo C210',
                    'description' => 'Gewerbe & Manufaktur (192.168.188.101)',
                    'motion_record' => false,
                    'motion_sensitivity' => 55,
                    'position' => 3,
                    'invert_pan' => true,
                    'invert_tilt' => false,
                ],
            ],
            'label_printer' => [
                'name' => 'MUNBYN ITPP941AP',
                'ip' => '192.168.188.149',
                'hostname' => 'ITPP941AP.fritz.box',
                'mac' => 'F0:23:AE:7D:51:35',
                'serial_number' => '941AP261000423',
                'firmware' => '1.2.4',
                'ipp_port' => 631,
                'raw_port' => 9100,
                'auto_print_shop_jobs' => true,
                'preferred_protocol' => 'IPP',
            ],
        ];
    };

    Route::get('/smarthome-sync', function () use ($defaultSmartHomeConfig) {
        $raw = SystemSetting::where('key', 'funki_smarthome_config')->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $cfg = is_array($decoded) ? array_merge($defaultSmartHomeConfig(), $decoded) : $defaultSmartHomeConfig();

        return response()->json([
            'success' => true,
            'data' => $cfg,
        ]);
    });

    Route::post('/smarthome-sync', function (Request $request) use ($defaultSmartHomeConfig, $defaultAlarmPackage) {
        $raw = SystemSetting::where('key', 'funki_smarthome_config')->value('value');
        $existing = is_string($raw) ? json_decode($raw, true) : null;
        $cfg = is_array($existing) ? array_merge($defaultSmartHomeConfig(), $existing) : $defaultSmartHomeConfig();

        if ($request->filled('grid_layout_count')) {
            $cfg['grid_layout_count'] = max(1, min(8, (int) $request->input('grid_layout_count')));
        }
        if ($request->filled('selected_camera_name')) {
            $cfg['selected_camera_name'] = substr(trim((string) $request->input('selected_camera_name')), 0, 80);
        }

        $plugsInput = $request->input('plugs', $request->input('smart_plugs'));
        if (is_string($plugsInput)) {
            $plugsInput = json_decode($plugsInput, true);
        }
        if (is_array($plugsInput)) {
            $sanitizedPlugs = [];
            foreach ($plugsInput as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $name = substr(trim((string) ($p['name'] ?? $p['Name'] ?? '')), 0, 120);
                $ip = substr(trim((string) ($p['ip'] ?? $p['IpAddress'] ?? '')), 0, 45);
                if ($name === '' || $ip === '') {
                    continue;
                }
                $sanitizedPlugs[] = [
                    'id' => substr(trim((string) ($p['id'] ?? $p['Id'] ?? Str::uuid()->getHex())), 0, 64),
                    'name' => $name,
                    'ip' => $ip,
                    'mac' => substr(trim((string) ($p['mac'] ?? $p['MacAddress'] ?? '')), 0, 48),
                    'model' => substr(trim((string) ($p['model'] ?? $p['Model'] ?? 'Tapo P100')), 0, 80),
                    'firmware' => substr(trim((string) ($p['firmware'] ?? $p['Firmware'] ?? '')), 0, 80),
                    'room' => substr(trim((string) ($p['room'] ?? $p['Room'] ?? 'Heimnetz')), 0, 80),
                ];
            }
            if (!empty($sanitizedPlugs)) {
                $cfg['plugs'] = $sanitizedPlugs;
            }
        }

        $camsInput = $request->input('cameras');
        if (is_string($camsInput)) {
            $camsInput = json_decode($camsInput, true);
        }
        if (is_array($camsInput)) {
            $sanitizedCams = [];
            foreach ($camsInput as $idx => $c) {
                if (!is_array($c)) {
                    continue;
                }
                $name = substr(trim((string) ($c['name'] ?? $c['Name'] ?? '')), 0, 80);
                if ($name === '') {
                    continue;
                }
                // Never store RTSP credentials in DB: extract clean IP & stream path only
                $ip = substr(trim((string) ($c['ip'] ?? $c['IpAddress'] ?? '')), 0, 45);
                $streamPath = substr(trim((string) ($c['stream_path'] ?? '/stream2')), 0, 64);
                if ($ip === '' && !empty($c['url'])) {
                    $rawUrl = (string) $c['url'];
                    if (preg_match('/@([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $rawUrl, $m)) {
                        $ip = $m[1];
                    } elseif (is_string(parse_url($rawUrl, PHP_URL_HOST))) {
                        $ip = (string) parse_url($rawUrl, PHP_URL_HOST);
                    }
                    $parsedPath = parse_url($rawUrl, PHP_URL_PATH);
                    if (is_string($parsedPath) && $parsedPath !== '') {
                        $streamPath = $parsedPath;
                    }
                }
                $sanitizedCams[] = [
                    'id' => substr(trim((string) ($c['id'] ?? $c['Id'] ?? Str::uuid()->getHex())), 0, 64),
                    'name' => $name,
                    'ip' => $ip,
                    'stream_path' => $streamPath,
                    'service_url' => substr(trim((string) ($c['service_url'] ?? $c['ServiceUrl'] ?? ($ip !== '' ? "http://{$ip}/onvif/device_service" : ''))), 0, 160),
                    'device_model' => substr(trim((string) ($c['device_model'] ?? $c['DeviceModel'] ?? 'IP-Kamera')), 0, 80),
                    'description' => substr(trim((string) ($c['description'] ?? $c['Description'] ?? '')), 0, 200),
                    'motion_record' => (bool) ($c['motion_record'] ?? $c['MotionRecord'] ?? false),
                    'motion_sensitivity' => max(1, min(100, (int) ($c['motion_sensitivity'] ?? $c['MotionSensitivity'] ?? 50))),
                    'position' => (int) ($c['position'] ?? $c['Position'] ?? $idx),
                    'invert_pan' => (bool) ($c['invert_pan'] ?? $c['InvertPan'] ?? true),
                    'invert_tilt' => (bool) ($c['invert_tilt'] ?? $c['InvertTilt'] ?? false),
                ];
            }
            if (!empty($sanitizedCams)) {
                $cfg['cameras'] = $sanitizedCams;
            }
        }

        if (is_array($request->input('label_printer'))) {
            $lp = $request->input('label_printer');
            $cfg['label_printer'] = array_merge($cfg['label_printer'] ?? [], [
                'name' => substr(trim((string) ($lp['name'] ?? $lp['Name'] ?? 'MUNBYN ITPP941AP')), 0, 80),
                'ip' => substr(trim((string) ($lp['ip'] ?? $lp['IpAddress'] ?? '192.168.188.149')), 0, 45),
                'auto_print_shop_jobs' => (bool) ($lp['auto_print_shop_jobs'] ?? $lp['AutoPrintShopJobs'] ?? true),
                'preferred_protocol' => substr(trim((string) ($lp['preferred_protocol'] ?? $lp['PreferredProtocol'] ?? 'IPP')), 0, 16),
            ]);
        }

        $cfg['updated_at'] = Carbon::now()->toIso8601String();

        SystemSetting::updateOrCreate(
            ['key' => 'funki_smarthome_config'],
            ['value' => json_encode($cfg, JSON_UNESCAPED_UNICODE)]
        );

        // Keep funki_alarm_sync_package.smart_plugs synchronized automatically
        $rawAlarm = SystemSetting::where('key', 'funki_alarm_sync_package')->value('value');
        $existingAlarm = is_string($rawAlarm) ? json_decode($rawAlarm, true) : null;
        $alarmPkg = is_array($existingAlarm) ? array_merge($defaultAlarmPackage(), $existingAlarm) : $defaultAlarmPackage();
        $alarmPkg['smart_plugs'] = $cfg['plugs'];
        SystemSetting::updateOrCreate(
            ['key' => 'funki_alarm_sync_package'],
            ['value' => json_encode($alarmPkg, JSON_UNESCAPED_UNICODE)]
        );

        return response()->json([
            'success' => true,
            'data' => $cfg,
        ]);
    });

    Route::get('/alarm-sync', function () use ($defaultAlarmPackage) {
        $raw = SystemSetting::where('key', 'funki_alarm_sync_package')->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $pkg = is_array($decoded) ? array_merge($defaultAlarmPackage(), $decoded) : $defaultAlarmPackage();
        $pkg['funkira_voice'] = 'Aoede';
        $pkg['gemini_tts_key'] = (string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: env('GEMINI_API_KEY', ''));

        return response()->json([
            'success' => true,
            'data' => $pkg,
        ]);
    });

    Route::match(['get', 'post'], '/morning-briefing-tts', function (Request $request) {
        $text = trim((string) $request->input('text', ''));
        $voice = trim((string) $request->input('voice', 'Aoede')) ?: 'Aoede';
        if ($text === '') {
            $timeStr = Carbon::now('Europe/Berlin')->format('H:i');
            $text = "Guten Morgen Alina! Es ist {$timeStr} Uhr. Dein Seelenfunke Fokus-Start Wecker ist aktiv. Starte entspannt und erfolgreich in deinen Tag!";
        }

        $wavBase64 = \App\Services\AI\GeminiTtsService::synthesizeWav($text, $voice);

        return response()->json([
            'success' => is_string($wavBase64) && $wavBase64 !== '',
            'voice' => $voice,
            'text' => $text,
            'audio_base64' => $wavBase64,
        ]);
    });

    Route::post('/alarm-sync', function (Request $request) use ($defaultAlarmPackage) {
        $raw = SystemSetting::where('key', 'funki_alarm_sync_package')->value('value');
        $existing = is_string($raw) ? json_decode($raw, true) : null;
        $pkg = is_array($existing) ? array_merge($defaultAlarmPackage(), $existing) : $defaultAlarmPackage();

        $alarmsInput = $request->input('alarms');
        if (is_string($alarmsInput)) {
            $alarmsInput = json_decode($alarmsInput, true);
        } elseif ($request->filled('alarms_json')) {
            $alarmsInput = json_decode((string) $request->input('alarms_json'), true);
        }
        if (is_array($alarmsInput)) {
            $pkg['alarms'] = array_values($alarmsInput);
        }

        $timersInput = $request->input('timers');
        if (is_string($timersInput)) {
            $timersInput = json_decode($timersInput, true);
        } elseif ($request->filled('timers_json')) {
            $timersInput = json_decode((string) $request->input('timers_json'), true);
        }
        if (is_array($timersInput)) {
            $pkg['timers'] = array_values($timersInput);
        }

        $plugsInput = $request->input('smart_plugs');
        if (is_string($plugsInput)) {
            $plugsInput = json_decode($plugsInput, true);
        } elseif ($request->filled('smart_plugs_json')) {
            $plugsInput = json_decode((string) $request->input('smart_plugs_json'), true);
        }
        if (is_array($plugsInput)) {
            $pkg['smart_plugs'] = array_values($plugsInput);
        }

        $logsInput = $request->input('wake_up_logs');
        if (is_string($logsInput)) {
            $logsInput = json_decode($logsInput, true);
        } elseif ($request->filled('wake_up_logs_json')) {
            $logsInput = json_decode((string) $request->input('wake_up_logs_json'), true);
        }
        $existingLogs = is_array($pkg['wake_up_logs'] ?? null) ? $pkg['wake_up_logs'] : [];
        if (is_array($logsInput)) {
            foreach ($logsInput as $key => $entry) {
                if (is_array($entry) && !empty($entry['date'])) {
                    $existingLogs[$entry['date']] = $entry;
                } elseif (is_string($key) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) && is_array($entry)) {
                    $existingLogs[$key] = array_merge(['date' => $key], $entry);
                }
            }
        }

        // Optional direct single wake-up log push from Android alarm dismiss/ring
        if ($request->filled('wake_up_date') && $request->filled('actual_wake_time')) {
            $wDate = (string) $request->input('wake_up_date');
            $existingLogs[$wDate] = [
                'date' => $wDate,
                'scheduled_time' => (string) $request->input('scheduled_time', $pkg['active_alarm_time'] ?? '08:30'),
                'actual_wake_time' => (string) $request->input('actual_wake_time'),
                'snooze_minutes' => (int) $request->input('snooze_minutes', 0),
                'plugs_triggered' => (bool) $request->input('plugs_triggered', false),
                'ai_briefing_spoken' => (bool) $request->input('ai_briefing_spoken', false),
                'synced_at' => Carbon::now()->toIso8601String(),
            ];
        }

        ksort($existingLogs);
        if (count($existingLogs) > 120) {
            $existingLogs = array_slice($existingLogs, -120, null, true);
        }
        $pkg['wake_up_logs'] = $existingLogs;

        // Recompute active alarm time from enabled routine-synced alarms
        $enabledAlarms = collect($pkg['alarms'] ?? [])
            ->filter(fn($a) => !empty($a['is_enabled']) && ($a['sync_with_routine'] ?? true))
            ->sortBy(fn($a) => sprintf('%02d:%02d', (int) ($a['hour'] ?? 0), (int) ($a['minute'] ?? 0)))
            ->values();

        if ($request->filled('active_alarm_time')) {
            $pkg['active_alarm_time'] = substr((string) $request->input('active_alarm_time'), 0, 5);
        } elseif ($enabledAlarms->isNotEmpty()) {
            $first = $enabledAlarms->first();
            $pkg['active_alarm_time'] = sprintf('%02d:%02d', (int) ($first['hour'] ?? 8), (int) ($first['minute'] ?? 30));
            $pkg['active_alarm_label'] = (string) ($first['schedule_text'] ?? $first['label'] ?? 'Jeden Tag');
        }

        if ($request->filled('device_name')) {
            $pkg['device_name'] = (string) $request->input('device_name');
        }
        $pkg['last_synced_at'] = Carbon::now()->toIso8601String();

        SystemSetting::updateOrCreate(
            ['key' => 'funki_alarm_sync_package'],
            ['value' => json_encode($pkg, JSON_UNESCAPED_UNICODE)]
        );

        $pkg['funkira_voice'] = 'Aoede';
        $pkg['gemini_tts_key'] = (string) (config('services.gemini.key') ?: config('services.gemini.api_key') ?: env('GEMINI_API_KEY', ''));

        return response()->json([
            'success' => true,
            'data' => $pkg,
        ]);
    });

    // 1. Get all routines with steps and today's log status
    Route::get('/', function (Request $request) {
        $date = $request->query('date', Carbon::now()->format('Y-m-d'));
        $routines = ManagementDayRoutine::with([
            'steps' => function($q) {
                $q->orderBy('position', 'asc');
            },
            'logs' => function($q) use ($date) {
                $q->whereDate('date', $date);
            }
        ])
        ->where('is_active', true)
        ->orderBy('start_time', 'asc')
        ->get();
        return response()->json(['success' => true, 'date' => $date, 'data' => $routines]);
    });

    // 2. Create routine
    Route::post('/', function (Request $request) {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'start_time' => 'required|date_format:H:i:s',
            'icon' => 'nullable|string|max:100'
        ]);

        $routine = ManagementDayRoutine::create(array_merge($data, [
            'id' => Str::uuid(),
            'is_active' => true
        ]));

        return response()->json(['success' => true, 'data' => $routine->load('steps')]);
    });

    // 3. Update routine
    Route::put('/{id}', function (Request $request, $id) {
        $routine = ManagementDayRoutine::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'duration_minutes' => 'sometimes|required|integer|min:1|max:1440',
            'start_time' => 'sometimes|required|date_format:H:i:s',
            'icon' => 'nullable|string|max:100',
            'is_active' => 'boolean'
        ]);

        $routine->update($data);
        return response()->json(['success' => true, 'data' => $routine->load('steps')]);
    });

    // 4. Delete routine
    Route::delete('/{id}', function ($id) {
        $routine = ManagementDayRoutine::findOrFail($id);
        $routine->steps()->delete();
        $routine->delete();
        return response()->json(['success' => true]);
    });

    // 5. Create step for routine
    Route::post('/{routineId}/steps', function (Request $request, $routineId) {
        $routine = ManagementDayRoutine::findOrFail($routineId);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1|max:1440'
        ]);

        $step = ManagementDayRoutineStep::create([
            'id' => Str::uuid(),
            'day_routine_id' => $routine->id,
            'title' => $data['title'],
            'duration_minutes' => $data['duration_minutes'],
            'position' => $routine->steps()->max('position') + 1
        ]);

        return response()->json(['success' => true, 'data' => $step]);
    });

    // 6. Delete step
    Route::delete('/steps/{stepId}', function ($stepId) {
        ManagementDayRoutineStep::destroy($stepId);
        return response()->json(['success' => true]);
    });

    // 7. Toggle daily routine completion log
    Route::post('/{routineId}/toggle-log', function (Request $request, $routineId) {
        $routine = ManagementDayRoutine::with('steps')->findOrFail($routineId);
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));

        $log = ManagementDayRoutineLog::where('day_routine_id', $routine->id)
            ->whereDate('date', $date)
            ->first();

        if ($log && $log->is_completed) {
            $log->update([
                'is_completed' => false,
                'completed_steps' => [],
                'completed_at' => null,
            ]);
        } else {
            $log = ManagementDayRoutineLog::updateOrCreate(
                ['day_routine_id' => $routine->id, 'date' => $date],
                [
                    'is_completed' => true,
                    'completed_steps' => $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray(),
                    'completed_at' => Carbon::now(),
                ]
            );
        }

        return response()->json(['success' => true, 'data' => $log]);
    });

    // 8. Toggle sick day for a date
    Route::post('/toggle-sick-day', function (Request $request) {
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $routines = ManagementDayRoutine::where('is_active', true)->get();

        $alreadySick = ManagementDayRoutineLog::whereDate('date', $date)
            ->where('is_sick', true)
            ->exists();

        if ($alreadySick) {
            ManagementDayRoutineLog::whereDate('date', $date)->update(['is_sick' => false]);
        } else {
            foreach ($routines as $routine) {
                ManagementDayRoutineLog::updateOrCreate(
                    ['day_routine_id' => $routine->id, 'date' => $date],
                    ['is_sick' => true]
                );
            }
        }

        return response()->json([
            'success' => true,
            'date' => $date,
            'is_sick' => !$alreadySick,
        ]);
    });

});
