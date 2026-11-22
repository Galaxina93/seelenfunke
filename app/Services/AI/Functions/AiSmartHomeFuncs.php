<?php

namespace App\Services\AI\Functions;

use App\Models\System\SystemSetting;
use App\Services\AI\AiAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Str;

trait AiSmartHomeFuncs
{
    public const SMARTHOME_SETTING_KEY = 'funki_smarthome_config';
    public const SMARTHOME_QUEUE_KEY = 'funki_smarthome_command_queue';

    public const ALLOWED_SMARTHOME_ACTIONS = [
        'shutter_open', 'shutter_close', 'shutter_stop', 'shutter_my', 'shutter_position',
        'plug_on', 'plug_off', 'plug_toggle',
        'group_open', 'group_close', 'group_stop', 'group_my', 'group_position', 'group_on', 'group_off',
    ];

    public static function getAiSmartHomeFuncsSchema(): array
    {
        return [
            [
                'name' => 'smarthome_get_devices_and_groups',
                'description' => 'Liest alle bekannten Funkenwerk SmartHome Geräte (Connexoon Window Somfy Rollläden/Fenster, Tapo WLAN-Steckdosen) sowie alle angelegten Geräte-Gruppen (z.B. Wohnzimmer + Terrassentür) aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeSmartHomeGetDevicesAndGroups'],
            ],
            [
                'name' => 'smarthome_control',
                'description' => 'Steuert einzelne Funkenwerk SmartHome Geräte (Somfy Connexoon Window Rollläden, Tapo Steckdosen), kombinierte Ziele ("Wohnzimmer und Terrassentür") oder ganze SmartHome-Gruppen mit einem Befehl.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'description' => 'Aktion: shutter_open, shutter_close, shutter_stop, shutter_my, shutter_position, plug_on, plug_off, plug_toggle, group_open, group_close, group_stop, group_my, group_position, group_on, group_off.',
                            'enum' => self::ALLOWED_SMARTHOME_ACTIONS,
                        ],
                        'target' => [
                            'type' => 'string',
                            'description' => 'Exakter Name der Gruppe (z.B. "Wohnbereich"), des Geräts (z.B. "Wohnzimmer", "Terrassentür", "Lichterkette Spiegel"), mehrere Namen mit "und" verbunden ("Wohnzimmer und Terrassentür") oder "alle".',
                        ],
                        'position' => [
                            'type' => 'integer',
                            'description' => 'Optionale Schließposition in Prozent von 0 (komplett offen) bis 100 (komplett geschlossen) bei shutter_position oder group_position.',
                        ],
                    ],
                    'required' => ['action', 'target'],
                ],
                'callable' => [self::class, 'executeSmartHomeControl'],
            ],
        ];
    }

    public static function executeSmartHomeGetDevicesAndGroups(array $args = []): array
    {
        if (AiAuthHelper::check() && !AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: SmartHome-Steuerung ist nur für Administratoren freigegeben.',
            ];
        }

        $cfg = self::loadSanitizedSmartHomeInventory();

        return [
            'status' => 'success',
            'gateway' => $cfg['connexoon_gateway'],
            'shutters' => $cfg['shutters'],
            'plugs' => $cfg['plugs'],
            'groups' => $cfg['groups'],
            'pending_commands_count' => count(self::getPendingRelayCommands()),
        ];
    }

    public static function executeSmartHomeControl(array $args = []): array
    {
        if (AiAuthHelper::check() && !AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: SmartHome-Steuerung ist nur für Administratoren freigegeben.',
            ];
        }

        $action = strtolower(trim((string) ($args['action'] ?? '')));
        $target = trim((string) ($args['target'] ?? 'alle'));
        $position = isset($args['position']) && $args['position'] !== '' && $args['position'] !== null
            ? max(0, min(100, (int) $args['position']))
            : null;

        return self::enqueueOrExecuteSmartHomeCommand($action, $target, $position, 'ai_agent');
    }

    public static function loadSanitizedSmartHomeInventory(): array
    {
        $raw = SystemSetting::where('key', self::SMARTHOME_SETTING_KEY)->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $cfg = is_array($decoded) ? $decoded : [];

        $plugs = [];
        foreach ((array) ($cfg['plugs'] ?? []) as $p) {
            if (!is_array($p) || empty($p['name'])) {
                continue;
            }
            $plugs[] = [
                'name' => (string) $p['name'],
                'ip' => (string) ($p['ip'] ?? ''),
                'room' => (string) ($p['room'] ?? 'Heimnetz'),
                'model' => (string) ($p['model'] ?? 'Tapo P100'),
            ];
        }

        $shutters = [];
        foreach ((array) ($cfg['shutters'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $label = trim((string) ($s['label'] ?? ''));
            if ($label === '' && empty($s['device_url'])) {
                continue;
            }
            $shutters[] = [
                'device_url' => (string) ($s['device_url'] ?? ''),
                'label' => $label !== '' ? $label : 'Rollladen',
                'closure_percent' => (int) ($s['closure_percent'] ?? 0),
                'supports_position' => (bool) ($s['supports_position'] ?? true),
            ];
        }

        $groups = [];
        foreach ((array) ($cfg['groups'] ?? []) as $g) {
            if (!is_array($g) || empty($g['name'])) {
                continue;
            }
            $groups[] = [
                'id' => (string) ($g['id'] ?? ''),
                'name' => (string) $g['name'],
                'shutter_targets' => array_values((array) ($g['shutter_targets'] ?? [])),
                'plug_targets' => array_values((array) ($g['plug_targets'] ?? [])),
            ];
        }

        return [
            'connexoon_gateway' => $cfg['connexoon_gateway'] ?? [
                'model' => 'Connexoon Window',
                'ip' => '192.168.188.29',
                'pin' => '0813-5321-9574',
            ],
            'shutters' => $shutters,
            'plugs' => $plugs,
            'groups' => $groups,
        ];
    }

    public static function normalizeSmartHomeToken(string $value): string
    {
        $s = mb_strtolower(trim($value), 'UTF-8');
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
        // Collapse double 'rr' / 'll' typos like "terassentür" vs "terrassentür" or "rolladen" vs "rollladen"
        $s = str_replace(['terrasse', 'terasse', 'rollladen', 'rolladen'], ['terasse', 'terasse', 'rolladen', 'rolladen'], $s);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? '';
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    public static function resolveSmartHomeTargets(string $rawTarget, array $inventory): array
    {
        $cleanTarget = trim($rawTarget);
        $normFull = self::normalizeSmartHomeToken($cleanTarget);
        if ($normFull === '' || in_array($normFull, ['alle', 'all', 'alles', 'beide'], true)) {
            return [
                'matched_group' => null,
                'shutters' => $inventory['shutters'],
                'plugs' => $inventory['plugs'],
                'raw_target' => 'alle',
            ];
        }

        // 1. Check Groups first (exact or normalized token match)
        foreach ($inventory['groups'] as $group) {
            $normGroup = self::normalizeSmartHomeToken((string) $group['name']);
            if ($normGroup !== '' && ($normGroup === $normFull || str_contains($normFull, $normGroup) || str_contains($normGroup, $normFull))) {
                $matchedShutters = [];
                foreach ($inventory['shutters'] as $sh) {
                    $shNorm = self::normalizeSmartHomeToken($sh['label']);
                    foreach ($group['shutter_targets'] as $st) {
                        $stNorm = self::normalizeSmartHomeToken((string) $st);
                        if ($st === $sh['device_url'] || ($stNorm !== '' && ($shNorm === $stNorm || str_contains($shNorm, $stNorm) || str_contains($stNorm, $shNorm)))) {
                            $matchedShutters[$sh['device_url'] ?: $sh['label']] = $sh;
                        }
                    }
                }
                // Even if CachedShutters hasn't been synced yet, preserve symbolic shutter targets from the group!
                if (empty($matchedShutters) && !empty($group['shutter_targets'])) {
                    foreach ($group['shutter_targets'] as $st) {
                        $matchedShutters[(string) $st] = ['device_url' => '', 'label' => (string) $st];
                    }
                }

                $matchedPlugs = [];
                foreach ($inventory['plugs'] as $pl) {
                    $plNorm = self::normalizeSmartHomeToken($pl['name']);
                    foreach ($group['plug_targets'] as $pt) {
                        $ptNorm = self::normalizeSmartHomeToken((string) $pt);
                        if ($pt === $pl['ip'] || ($ptNorm !== '' && ($plNorm === $ptNorm || str_contains($plNorm, $ptNorm) || str_contains($ptNorm, $plNorm)))) {
                            $matchedPlugs[$pl['ip'] ?: $pl['name']] = $pl;
                        }
                    }
                }

                return [
                    'matched_group' => $group['name'],
                    'shutters' => array_values($matchedShutters),
                    'plugs' => array_values($matchedPlugs),
                    'raw_target' => $group['name'],
                ];
            }
        }

        // 2. Support compound queries like "Wohnzimmer und Terrassentür" or "Wohnzimmer, Terassentür"
        $parts = preg_split('/\s+(?:und|sowie|&|\+)\s+|\s*,\s*/iu', $cleanTarget) ?: [$cleanTarget];
        $matchedShutters = [];
        $matchedPlugs = [];

        foreach ($parts as $part) {
            $pNorm = self::normalizeSmartHomeToken((string) $part);
            if ($pNorm === '') {
                continue;
            }
            foreach ($inventory['shutters'] as $sh) {
                $shNorm = self::normalizeSmartHomeToken($sh['label']);
                if ($shNorm !== '' && ($shNorm === $pNorm || str_contains($shNorm, $pNorm) || str_contains($pNorm, $shNorm))) {
                    $matchedShutters[$sh['device_url'] ?: $sh['label']] = $sh;
                }
            }
            foreach ($inventory['plugs'] as $pl) {
                $plNorm = self::normalizeSmartHomeToken($pl['name']);
                if ($part === $pl['ip'] || ($plNorm !== '' && ($plNorm === $pNorm || str_contains($plNorm, $pNorm) || str_contains($pNorm, $plNorm)))) {
                    $matchedPlugs[$pl['ip'] ?: $pl['name']] = $pl;
                }
            }
        }

        return [
            'matched_group' => null,
            'shutters' => array_values($matchedShutters),
            'plugs' => array_values($matchedPlugs),
            'raw_target' => $cleanTarget,
        ];
    }

    public static function enqueueOrExecuteSmartHomeCommand(
        string $rawAction,
        string $rawTarget,
        ?int $position = null,
        string $source = 'ai'
    ): array {
        $actionMap = [
            'open' => 'shutter_open',
            'auf' => 'shutter_open',
            'hoch' => 'shutter_open',
            'close' => 'shutter_close',
            'zu' => 'shutter_close',
            'runter' => 'shutter_close',
            'stop' => 'shutter_stop',
            'stopp' => 'shutter_stop',
            'my' => 'shutter_my',
            'position' => 'shutter_position',
            'set_position' => 'shutter_position',
            'on' => 'plug_on',
            'turn_on' => 'plug_on',
            'ein' => 'plug_on',
            'off' => 'plug_off',
            'turn_off' => 'plug_off',
            'aus' => 'plug_off',
            'toggle' => 'plug_toggle',
        ];
        $action = $actionMap[$rawAction] ?? $rawAction;

        if (!in_array($action, self::ALLOWED_SMARTHOME_ACTIONS, true)) {
            return [
                'status' => 'error',
                'message' => "Ungültige SmartHome-Aktion '{$rawAction}'.",
            ];
        }

        $safeTarget = \App\Services\AI\ContextDossierBuilder::sanitizeExternalUntrustedText($rawTarget !== '' ? $rawTarget : 'alle', 140);
        $inventory = self::loadSanitizedSmartHomeInventory();
        $resolved = self::resolveSmartHomeTargets($safeTarget, $inventory);

        $cmdId = 'shcmd_' . Str::uuid()->getHex();
        $nowIso = Carbon::now('Europe/Berlin')->toIso8601String();

        $queue = self::loadQueueRaw();
        $cutoff = Carbon::now('Europe/Berlin')->subMinutes(10)->timestamp;
        $queue = array_values(array_filter($queue, static function ($item) use ($cutoff) {
            if (!is_array($item)) {
                return false;
            }
            $ts = isset($item['created_ts']) ? (int) $item['created_ts'] : 0;
            return $ts >= $cutoff;
        }));

        $entry = [
            'id' => $cmdId,
            'action' => $action,
            'target' => $safeTarget,
            'matched_group' => $resolved['matched_group'],
            'position' => $position,
            'source' => substr($source, 0, 32),
            'status' => 'pending',
            'created_at' => $nowIso,
            'created_ts' => Carbon::now('Europe/Berlin')->timestamp,
        ];

        $queue[] = $entry;
        if (count($queue) > 30) {
            $queue = array_slice($queue, -30);
        }

        SystemSetting::updateOrCreate(
            ['key' => self::SMARTHOME_QUEUE_KEY],
            ['value' => json_encode($queue, JSON_UNESCAPED_UNICODE)]
        );

        $shutterLabels = array_values(array_map(static fn($s) => $s['label'], $resolved['shutters']));
        $plugLabels = array_values(array_map(static fn($p) => $p['name'], $resolved['plugs']));
        $targetLabel = $resolved['matched_group']
            ? "Gruppe '{$resolved['matched_group']}'"
            : (!empty($shutterLabels) || !empty($plugLabels)
                ? implode(', ', array_merge($shutterLabels, $plugLabels))
                : $safeTarget);

        $posSuffix = $position !== null ? " ({$position} %)" : '';

        return [
            'status' => 'success',
            'command_id' => $cmdId,
            'action' => $action,
            'target' => $safeTarget,
            'matched_group' => $resolved['matched_group'],
            'resolved_shutters' => $shutterLabels,
            'resolved_plugs' => $plugLabels,
            'position' => $position,
            'queued_for_home_bridge' => true,
            'message' => "SmartHome-Befehl '{$action}{$posSuffix}' für {$targetLabel} an Funkenwerk SmartHome übermittelt.",
        ];
    }

    public static function getPendingRelayCommands(): array
    {
        $cutoff = Carbon::now('Europe/Berlin')->subMinutes(10)->timestamp;
        $queue = self::loadQueueRaw();
        return array_values(array_filter($queue, static function ($item) use ($cutoff) {
            return is_array($item)
                && ($item['status'] ?? '') === 'pending'
                && ((int) ($item['created_ts'] ?? 0)) >= $cutoff;
        }));
    }

    public static function acknowledgeRelayCommand(string $commandId, bool $ok = true, string $summary = ''): bool
    {
        if ($commandId === '') {
            return false;
        }
        $queue = self::loadQueueRaw();
        $found = false;
        foreach ($queue as &$item) {
            if (is_array($item) && ($item['id'] ?? '') === $commandId) {
                $item['status'] = $ok ? 'executed' : 'failed';
                $item['summary'] = $summary;
                $item['acked_at'] = Carbon::now('Europe/Berlin')->toIso8601String();
                $found = true;
                break;
            }
        }
        unset($item);

        if ($found) {
            SystemSetting::updateOrCreate(
                ['key' => self::SMARTHOME_QUEUE_KEY],
                ['value' => json_encode($queue, JSON_UNESCAPED_UNICODE)]
            );
        }
        return $found;
    }

    private static function loadQueueRaw(): array
    {
        $raw = SystemSetting::where('key', self::SMARTHOME_QUEUE_KEY)->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
