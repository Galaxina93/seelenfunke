<?php

namespace App\Services\Laser;

use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Services\Export\FileDownloadService;
use App\Services\System\FunkenwerkPrintService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class XtoolLaserCockpitService
{
    public const DEFAULT_LASER_IP = '192.168.188.150';
    public const DEFAULT_V2_PORT = 28900;
    public const DEFAULT_PARITY_TOKEN = '2F18DD055BEAF251DF165868202036E8';
    public const DEFAULT_USER_ID = 'mk-guest';
    public const DEFAULT_USER_KEY = 'bWFrZWJsb2NrLXh0b29s';
    public const DEVICE_EXT_ID = 'GS009-CLASS-4';
    public const DEVICE_EXT_NAME = 'F2 Ultra UV';
    public const DEVICE_ID_KEY = 'GS009-CLASS-4-1';
    public const DEVICE_SN_CODE = 'MXFK007003260530H0194A6';

    public const BED_WIDTH_MM = 200.0;
    public const BED_HEIGHT_MM = 200.0;
    public const CAMERA_PX_PER_MM = 10.0;

    public const MIN_DOT_DURATION_US = 1;
    public const MAX_DOT_DURATION_US = 3000;
    public const Z_AXIS_FOCUS_OFFSET_MM = 6.0;

    private const QUEUE_FILE = 'system/xtool_queue/jobs.json';
    private const LOCK_FILE = 'system/xtool_queue/jobs.lock';
    private const MAX_HISTORY = 25;

    private ?string $relayRootOverride = null;
    private bool $allowRelayInTests = false;
    private bool $allowV2SocketInTests = false;
    private float $relayCameraWaitSeconds = 9.0;
    private float $relayJobWaitSeconds = 10.0;

    public function setRelayTestingOptions(
        ?string $relayRootOverride = null,
        bool $allowRelayInTests = false,
        float $cameraWaitSeconds = 9.0,
        float $jobWaitSeconds = 10.0
    ): void {
        $this->relayRootOverride = $relayRootOverride;
        $this->allowRelayInTests = $allowRelayInTests;
        $this->relayCameraWaitSeconds = max(0.05, $cameraWaitSeconds);
        $this->relayJobWaitSeconds = max(0.05, $jobWaitSeconds);
    }

    public function setV2SocketTestingMode(bool $allow): void
    {
        $this->allowV2SocketInTests = $allow;
    }

    /**
     * Atomares, root/www-data-sicheres Dateischreiben im geteilten xTool-Arbeitsverzeichnis.
     */
    protected function writeFileAtomicallyShared(string $targetPath, string $bytes): bool
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

        $tmp = $targetPath . '.tmp.' . uniqid('', true);
        if (@file_put_contents($tmp, $bytes) === false) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0666);

        if (!@rename($tmp, $targetPath)) {
            @unlink($targetPath);
            if (!@rename($tmp, $targetPath)) {
                @unlink($tmp);
                return false;
            }
        }

        @chmod($targetPath, 0666);
        clearstatcache(true, $targetPath);

        return true;
    }

    /**
     * Prüft, ob vor dem Gravur-Job automatisch die motorisierte Z-Achse (GS009-CLASS-4) angefahren werden darf:
     * Strikt nur bei Gravur-Aufträgen ($jobType !== 'framing'), gültiger Fokus-Höhe (> 0 mm) und
     * Flachbett / Gebogener Oberfläche (NIEMALS bei RA3 Walze, Förderband oder 3D-Innengravur).
     */
    public static function shouldAutoControlZAxis(string $jobType, ?float $focusMm, string $equipmentKey = 'flatbed_standard'): bool
    {
        if ($jobType === 'framing' || $focusMm === null || $focusMm <= 0.0) {
            return false;
        }

        $normalizedEquip = self::normalizeEquipmentKey($equipmentKey);

        return in_array($normalizedEquip, ['flatbed_standard', 'curved_surface'], true);
    }

    /**
     * Baut den verifizierten xTool Studio v1.10.15 Z-Achsen-Steuerbefehl (POST /v1/laser-head/focus/control)
     * für den xTool F2 Ultra UV (z = focus_mm + 6.0 mm Offset auf GS009-CLASS-4).
     *
     * @return array{method: string, url: string, params: array<string, mixed>, data: array{action: string, stopFirst: int, z: float}}|null
     */
    public static function buildZAxisFocusControlCommand(
        ?float $focusMm,
        string $jobType = 'engrave',
        string $equipmentKey = 'flatbed_standard'
    ): ?array {
        if (!self::shouldAutoControlZAxis($jobType, $focusMm, $equipmentKey)) {
            return null;
        }

        return [
            'method' => 'POST',
            'url'    => '/v1/laser-head/focus/control',
            'params' => [],
            'data'   => [
                'action'    => 'goTo',
                'stopFirst' => 1,
                'z'         => round((float) $focusMm + self::Z_AXIS_FOCUS_OFFSET_MM, 2),
            ],
        ];
    }

    /**
     * Normalisiert den Kamera-Beleuchtungsmodus für blendfreie Aufnahmen auf spiegelnden Metall-/Glas-Rohlingen:
     * - 'anti_glare': Arbeits-LEDs während des Snapshots komplett aus (front = 0, back = 0)
     * - 'dim':        Indirekt gedimmt ~10 % (front = 0, back = 20)
     * - 'normal':     Arbeitslicht unverändert lassen
     */
    public static function normalizeCameraLightMode(?string $lightMode, array $configuration = []): string
    {
        $raw = ($lightMode !== null && trim($lightMode) !== '')
            ? $lightMode
            : (string) ($configuration['laser_camera_light_mode'] ?? 'anti_glare');
        $k = mb_strtolower(trim($raw));

        return match ($k) {
            'dim', 'dimmed', 'low', '10%'             => 'dim',
            'normal', 'on', 'standard', 'unchanged'   => 'normal',
            default                                   => 'anti_glare',
        };
    }

    /**
     * Ziel-PWM-Werte (0..255) für fill_light während der 1,5 s Kameraaufnahme bzw. null bei 'normal'.
     *
     * @return array{front: int, back: int}|null
     */
    public static function resolveFillLightTargetsForMode(string $lightMode): ?array
    {
        $norm = self::normalizeCameraLightMode($lightMode);
        if ($norm === 'normal') {
            return null;
        }
        if ($norm === 'dim') {
            return ['front' => 0, 'back' => 20];
        }

        return ['front' => 0, 'back' => 0];
    }

    /**
     * Übersetzt die prozentuale Helligkeit aus /v1/device/configs (fillLightBrightFront / fillLightBrightBack)
     * in den Hardware-Helligkeitswert (0% => 0, 1..100% => round(($pct - 1) * 235 / 99 + 20)).
     */
    public static function fillLightPercentToBri($rawPercent, int $fallback = 255): int
    {
        if ($rawPercent === null || $rawPercent === '' || !is_numeric($rawPercent)) {
            return max(0, min(255, $fallback));
        }
        $val = (float) $rawPercent;
        if ($val <= 0.0) {
            return 0;
        }
        if ($val > 100.0) {
            return max(20, min(255, (int) round($val)));
        }
        $pct = max(1.0, min(100.0, $val));

        return max(20, min(255, (int) round(((($pct - 1.0) * 235.0) / 99.0) + 20.0)));
    }

    /**
     * Synchronisiert das entzerrte 2000x2000 px (200x200 mm) Kamerabild (background.jpg)
     * aus dem lokalen xTool Studio AppData-Verzeichnis, direkt vom Laser oder über das Mini-PC Stage-Relay.
     *
     * @return array{camera_path: string, camera_url: string, updated_at: string, source: string, laser_online: bool, laser_ip: string, relay_error: ?string, light_mode: string}
     */
    public function syncCameraFromHostOrLaser(
        ?float $focusMm = null,
        bool $forceCheck = false,
        string $laserIp = self::DEFAULT_LASER_IP,
        ?string $lightMode = null
    ): array {
        $resolvedLightMode = self::normalizeCameraLightMode($lightMode);
        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($laserIp, self::DEFAULT_LASER_IP);
        $publicDisk = Storage::disk('public');
        $relativeBgPath = 'bestellungen/xtool/background.jpg';
        $targetFullPath = $publicDisk->path($relativeBgPath);

        if (!is_dir(dirname($targetFullPath))) {
            @mkdir(dirname($targetFullPath), 0777, true);
        }
        @chmod(dirname($targetFullPath), 0777);

        $source = 'cached';
        $hasLocalFile = @is_file($targetFullPath) && (int) @filesize($targetFullPath) > 10000;
        $laserOnline = false;
        $relayError = null;
        $isUnitTestRestricted = app()->runningUnitTests() && !$this->allowRelayInTests && !$this->allowV2SocketInTests;

        if ((!$hasLocalFile || $forceCheck) && !$isUnitTestRestricted) {
            $isCloud = $this->isCloudEnvironment();

            // 1. Direkter Live-Kamera-Abruf vom xTool F2 Ultra UV über das native V2-WebSocket-Protokoll (TLS Port 28900)
            if ($forceCheck && !$isCloud && (!app()->runningUnitTests() || $this->allowV2SocketInTests)) {
                if ($this->captureAndCalibrateLiveCameraFromLaserV2($safeIp, $targetFullPath, true, $resolvedLightMode)) {
                    $source = 'laser_live_v2_ws';
                    $hasLocalFile = true;
                    $laserOnline = true;
                }
            }

            if (!$laserOnline && !$isCloud && (!app()->runningUnitTests() || $this->allowV2SocketInTests)) {
                $laserOnline = $this->probeDirectLaserSocket($safeIp, false);
            }

            // 2. Fallback: HTTP-Kamera-Snapshot versuchen (ältere Firmware / Bridge im lokalen LAN)
            if ($forceCheck && $laserOnline && !$isCloud && !app()->runningUnitTests() && $source !== 'laser_live_v2_ws') {
                foreach ([
                    "http://{$safeIp}:8080/camera/snapshot",
                    "http://{$safeIp}:8080/snapshot.jpg",
                    "http://{$safeIp}:8329/camera/snapshot",
                ] as $snapEndpoint) {
                    $ctx = stream_context_create([
                        'http' => [
                            'method'  => 'GET',
                            'timeout' => 0.60,
                        ],
                    ]);
                    $liveBytes = @file_get_contents($snapEndpoint, false, $ctx);
                    if (is_string($liveBytes) && strlen($liveBytes) > 10000 && function_exists('imagecreatefromstring')) {
                        $testImg = @imagecreatefromstring($liveBytes);
                        if ($testImg) {
                            imagedestroy($testImg);
                            if ($this->writeFileAtomicallyShared($targetFullPath, $liveBytes)) {
                                $source = 'laser_live_http';
                                $hasLocalFile = true;
                                break;
                            }
                        }
                    }
                }
            }

            // 2b. Stage <-> Werkstatt Mini-PC (192.168.188.151) Echtzeit-LAN-Relay bzw. ehrliche lokale Statusmeldung
            if ($forceCheck && !in_array($source, ['laser_live_v2_ws', 'laser_live_http'], true)) {
                $heartbeat = $this->getMiniPcRelayHeartbeat();
                if ($heartbeat !== null && $heartbeat['age_seconds'] <= 20.0) {
                    $relayJob = $this->enqueueRelayJob('capture_camera', [
                        'laser_ip'   => $safeIp,
                        'light_mode' => $resolvedLightMode,
                    ]);
                    $relayResult = $this->waitForRelayJobCompletion($relayJob['job_id'], $this->relayCameraWaitSeconds);
                    clearstatcache(true, $targetFullPath);

                    if (
                        is_array($relayResult)
                        && ($relayResult['ok'] ?? false) === true
                        && @is_file($targetFullPath)
                        && (int) @filesize($targetFullPath) > 5120
                    ) {
                        $source = 'minipc_lan_relay';
                        $hasLocalFile = true;
                        $laserOnline = true;
                        $relayError = null;
                    } else {
                        $laserOnline = (bool) ($relayResult['laser_online'] ?? false);
                        $relayError = (string) ($relayResult['error'] ?? 'Laser antwortet im Werkstatt-LAN nicht.');
                    }
                } elseif ($isCloud || (app()->runningUnitTests() && $this->allowRelayInTests && !$this->allowV2SocketInTests)) {
                    $relayError = 'Werkstatt-Brücke (Mini-PC 192.168.188.151) nicht verbunden oder Laser im lokalen Netzwerk nicht erreichbar.';
                } else {
                    $relayError = $laserOnline
                        ? "xTool F2 Ultra UV ({$safeIp}:" . self::DEFAULT_V2_PORT . ') war erreichbar, aber die Live-Kamera-Übertragung wurde unterbrochen.'
                        : "xTool F2 Ultra UV ({$safeIp}:" . self::DEFAULT_V2_PORT . ') ist im lokalen Netzwerk aktuell nicht erreichbar (ausgeschaltet oder im Standby).';
                }
            }

            if (!$laserOnline) {
                $laserOnline = $this->probeLaserReachability($safeIp);
            }

            // 3. Fallback: Host xTool Studio AppData-Verzeichnis & Docker-Bridge-Spiegel prüfen (falls Laser im Standby)
            if (!in_array($source, ['laser_live_v2_ws', 'laser_live_http', 'minipc_lan_relay'], true)) {
                $rawCaptureCandidate = $publicDisk->path('bestellungen/xtool/host_appdata/capture.jpg');
                if ($forceCheck && @is_file($rawCaptureCandidate) && (int) @filesize($rawCaptureCandidate) > 15000) {
                    $rawCaptureBytes = @file_get_contents($rawCaptureCandidate);
                    if (is_string($rawCaptureBytes) && $this->warpRawCameraImageWithCalib($rawCaptureBytes, $targetFullPath)) {
                        $source = 'xtool_calib_warped';
                        $hasLocalFile = true;
                    }
                }

                if ($source !== 'xtool_calib_warped') {
                    $hostCandidates = [
                        $publicDisk->path('bestellungen/xtool/host_appdata/background.jpg'),
                        '/mnt/c/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/background.jpg',
                        'C:/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/background.jpg',
                    ];

                    foreach ($hostCandidates as $candidate) {
                        if (@is_file($candidate) && @filesize($candidate) > 10000) {
                            $candMtime = (int) @filemtime($candidate);
                            $localMtime = $hasLocalFile ? (int) @filemtime($targetFullPath) : 0;
                            if (!$hasLocalFile || $candMtime >= $localMtime || $forceCheck) {
                                $bytes = @file_get_contents($candidate);
                                if (is_string($bytes) && strlen($bytes) > 10000 && $this->writeFileAtomicallyShared($targetFullPath, $bytes)) {
                                    $source = 'xtool_studio_appdata';
                                    $hasLocalFile = true;
                                }
                            }
                            break;
                        }
                    }
                }
            }

            // 4. Bei explizitem Refresh ($forceCheck) Datei-Zeitstempel aktualisieren, damit Browser & Livewire garantiert neu laden
            if ($forceCheck && @is_file($targetFullPath) && !in_array($source, ['laser_live_v2_ws', 'minipc_lan_relay', 'xtool_calib_warped'], true)) {
                $existingBytes = @file_get_contents($targetFullPath);
                if (is_string($existingBytes) && strlen($existingBytes) > 1024) {
                    $this->writeFileAtomicallyShared($targetFullPath, $existingBytes);
                }
            }
        } elseif (!$isUnitTestRestricted) {
            $laserOnline = $this->probeLaserReachability($safeIp);
        }

        if (!@is_file($targetFullPath) || (int) @filesize($targetFullPath) < 1024) {
            $this->createSyntheticSlatBedImage($targetFullPath);
            $source = 'synthetic_slat_bed';
        } elseif ($forceCheck && $isUnitTestRestricted) {
            $existingBytes = @file_get_contents($targetFullPath);
            if (is_string($existingBytes) && strlen($existingBytes) > 1024) {
                $this->writeFileAtomicallyShared($targetFullPath, $existingBytes);
            }
        }

        clearstatcache(true, $targetFullPath);
        $mtime = @is_file($targetFullPath) ? (int) @filemtime($targetFullPath) : time();
        if ($forceCheck) {
            $mtime = max($mtime, time());
        }

        return [
            'camera_path'  => $targetFullPath,
            'camera_url'   => asset('storage/' . $relativeBgPath) . '?v=' . $mtime,
            'updated_at'   => date('d.m.Y H:i:s', $mtime),
            'source'       => $source,
            'laser_online' => $laserOnline,
            'laser_ip'     => $safeIp,
            'relay_error'  => $relayError,
            'light_mode'   => $resolvedLightMode,
        ];
    }

    /**
     * Nimmt ein neues Kamerabild (JPEG) über die REST-Bridge entgegen und legt es unter storage/app/public/bestellungen/xtool/background.jpg ab.
     */
    public function updateCameraImageFromBytes(string $jpegBytes): array
    {
        if (strlen($jpegBytes) < 1024 || !function_exists('imagecreatefromstring')) {
            throw new \InvalidArgumentException('Ungültige Kamerabild-Daten.');
        }

        $img = @imagecreatefromstring($jpegBytes);
        if (!$img) {
            throw new \InvalidArgumentException('Bilddaten konnten nicht dekodiert werden.');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w !== 2000 || $h !== 2000) {
            $resized = imagecreatetruecolor(2000, 2000);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, 2000, 2000, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        $targetFullPath = Storage::disk('public')->path('bestellungen/xtool/background.jpg');
        ob_start();
        imagejpeg($img, null, 92);
        $outBytes = (string) ob_get_clean();
        imagedestroy($img);

        if ($outBytes === '' || !$this->writeFileAtomicallyShared($targetFullPath, $outBytes)) {
            throw new \RuntimeException('Kamerabild konnte nicht gespeichert werden.');
        }

        return $this->syncCameraFromHostOrLaser(null, false);
    }

    public function isCloudEnvironment(): bool
    {
        if (env('XTOOL_FORCE_LAN_PROBE', false)) {
            return false;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return app()->environment('staging', 'production')
            || ($appHost !== '' && str_ends_with($appHost, 'mein-seelenfunke.de'));
    }

    protected function probeDirectLaserSocket(string $ip = self::DEFAULT_LASER_IP, bool $force = false): bool
    {
        if ((app()->runningUnitTests() && !$this->allowV2SocketInTests) || $this->isCloudEnvironment()) {
            return false;
        }

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($ip, self::DEFAULT_LASER_IP);
        $cacheKey = 'xtool_reach_' . md5($safeIp);

        if (!$force) {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_bool($cached)) {
                return $cached;
            }
        }

        $reachable = false;
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($safeIp, self::DEFAULT_V2_PORT, $errno, $errstr, 0.22);
        if (is_resource($fp)) {
            fclose($fp);
            $reachable = true;
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, $reachable, 15);

        return $reachable;
    }

    /**
     * Prüft per schnellem Socket-Connect (max. 220 ms) oder über den Live-Heartbeat der Werkstatt-Brücke (Mini-PC),
     * ob der xTool F2 Ultra UV im lokalen WLAN erreichbar ist.
     */
    public function probeLaserReachability(string $ip = self::DEFAULT_LASER_IP, bool $force = false): bool
    {
        if (app()->runningUnitTests() && !$this->allowRelayInTests) {
            return false;
        }

        if ($this->probeDirectLaserSocket($ip, $force)) {
            return true;
        }

        $heartbeat = $this->getMiniPcRelayHeartbeat();
        if ($heartbeat !== null && $heartbeat['age_seconds'] < 15.0 && $heartbeat['laser_online'] === true) {
            return true;
        }

        return false;
    }

    /**
     * Löst direkt auf dem xTool F2 Ultra UV (TLS/WSS Port 28900, V2 Binary Frame Protocol 0xBABE)
     * einen physischen Kamera-Snapshot (/v1/camera/snap -> 4000x3000 JPEG) aus, lädt ihn über den
     * file_stream-Kanal herunter und entzerrt ihn anhand des 21x21 Werkskalibrierungs-Rasters (global_calib.json)
     * auf das exakte 2000x2000 px (200x200 mm, 10 px = 1 mm) Arbeitsflächenbild.
     * Unterstützt blendfreie Aufnahme über fill_light ('anti_glare' | 'dim' | 'normal') mit garantiertem Restore im finally-Pfad.
     */
    public function captureAndCalibrateLiveCameraFromLaserV2(
        string $ip = self::DEFAULT_LASER_IP,
        ?string $targetBackgroundPath = null,
        bool $directSocketOnly = false,
        ?string $lightMode = null
    ): bool {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $resolvedLightMode = self::normalizeCameraLightMode($lightMode);
        $fillLightTargets = self::resolveFillLightTargetsForMode($resolvedLightMode);

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($ip, self::DEFAULT_LASER_IP);
        $canConnectDirectly = $directSocketOnly
            ? $this->probeDirectLaserSocket($safeIp, true)
            : $this->probeDirectLaserSocket($safeIp, true);
        if (!$canConnectDirectly) {
            return false;
        }
        $publicDisk = Storage::disk('public');
        $targetBackgroundPath = $targetBackgroundPath ?: $publicDisk->path('bestellungen/xtool/background.jpg');
        $hostAppdataDir = $publicDisk->path('bestellungen/xtool/host_appdata');
        if (!is_dir($hostAppdataDir)) {
            @mkdir($hostAppdataDir, 0777, true);
        }
        @chmod($hostAppdataDir, 0777);

        $uuid = (string) Str::uuid();
        $mainWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=instruction");
        if (!is_resource($mainWs)) {
            return false;
        }

        $fileWs = null;
        $txId = 0;
        $sessionToken = '';
        $fillLightChanged = false;
        $peripheralsDimmed = false;
        $restoreFrontBri = 255;
        $restoreBackBri = 255;

        try {
            $parityToken = (string) env('XTOOL_PARITY_TOKEN', self::DEFAULT_PARITY_TOKEN);
            $parity = $this->v2SendJsonCmd($mainWs, $txId, '', 'GET', '/v1/user/parity', [], [
                'userID'   => self::DEFAULT_USER_ID,
                'userKey'  => self::DEFAULT_USER_KEY,
                'timezone' => 'Europe/Berlin',
                'token'    => $parityToken,
            ]);

            $sessionToken = (string) ($parity['data']['sessionToken'] ?? '');
            if ($sessionToken === '') {
                return false;
            }

            $fileWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=file_stream");
            if (!is_resource($fileWs)) {
                return false;
            }

            // Falls global_calib.json noch nicht lokal gecacht ist, direkt vom Laser herunterladen (filetype = 3)
            $calibPath = $this->resolveOrDownloadGlobalCalibPath($mainWs, $fileWs, $txId, $sessionToken, $hostAppdataDir);

            // 0. Vor dem Abdunkeln aktuelle Arbeits-LED-Rohwerte (0 bzw. 20..255) aus GS009-CLASS-4 /v1/device/configs sichern
            if ($fillLightTargets !== null) {
                try {
                    $devCfg = $this->v2SendJsonCmd(
                        $mainWs,
                        $txId,
                        $sessionToken,
                        'GET',
                        '/v1/device/configs',
                        [],
                        [
                            'alias' => 'config',
                            'type'  => 'user',
                            'kv'    => ['fillLightBrightFront', 'fillLightBrightBack'],
                        ]
                    );
                    $cfgData = is_array($devCfg['data'] ?? null) ? $devCfg['data'] : [];
                    if (isset($cfgData['configs']) && is_array($cfgData['configs'])) {
                        $cfgData = $cfgData['configs'];
                    }

                    $hasFront = array_key_exists('fillLightBrightFront', $cfgData) && is_numeric($cfgData['fillLightBrightFront']);
                    $hasBack = array_key_exists('fillLightBrightBack', $cfgData) && is_numeric($cfgData['fillLightBrightBack']);
                    if ($hasFront) {
                        $restoreFrontBri = max(0, min(255, (int) round((float) $cfgData['fillLightBrightFront'])));
                    }
                    if ($hasBack) {
                        $restoreBackBri = max(0, min(255, (int) round((float) $cfgData['fillLightBrightBack'])));
                    }
                    if ((!$hasFront && !$hasBack) || ($restoreFrontBri === 0 && $restoreBackBri === 0)) {
                        $restoreFrontBri = 255;
                        $restoreBackBri = 255;
                    }
                } catch (\Throwable) {
                }
            }

            // 1. IR-LED & roten/blauen Positionierlaser kurz ausschalten, damit kein Überstrahlen auf Kristall/Edelstahl entsteht
            $peripheralsDimmed = true;
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/peripheral/param', ['type' => 'ir_led'], ['action' => 'off', 'index' => 0]);
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/laser-head/parameter', [], ['action' => 'power', 'ctrl' => 'set', 'value' => 0]);

            // 2. Arbeits-LEDs (fill_light) temporär ausschalten / dimmen
            if ($fillLightTargets !== null) {
                try {
                    $flResp = $this->v2SendJsonCmd(
                        $mainWs,
                        $txId,
                        $sessionToken,
                        'PUT',
                        '/v1/peripheral/param',
                        ['type' => 'fill_light'],
                        [
                            'action' => 'set_bri',
                            'front'  => $fillLightTargets['front'],
                            'back'   => $fillLightTargets['back'],
                        ]
                    );
                    $fillLightChanged = true;
                    if (!is_array($flResp)) {
                        $this->sendLegacyHttpFillLightFallback($safeIp, $fillLightTargets['front'], $fillLightTargets['back']);
                    }
                } catch (\Throwable) {
                }
            }

            // 3. Kamera-Belichtung initialisieren (exp=0, stream=0)
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'POST', '/v1/camera', ['exp' => 0, 'stream' => '0'], []);

            // 4. V4L2-Ringpuffer (~400 ms) flushen & Auto-Belichtung ohne LED-Blendung 650 ms (150 ms + Re-Assert + 500 ms) einpegeln
            if ($fillLightTargets !== null) {
                $this->waitForCameraExposureSettleUs(150000);
                try {
                    $this->v2SendJsonCmd(
                        $mainWs,
                        $txId,
                        $sessionToken,
                        'PUT',
                        '/v1/peripheral/param',
                        ['type' => 'fill_light'],
                        [
                            'action' => 'set_bri',
                            'front'  => $fillLightTargets['front'],
                            'back'   => $fillLightTargets['back'],
                        ]
                    );
                    $fillLightChanged = true;
                } catch (\Throwable) {
                }
                $this->waitForCameraExposureSettleUs(500000);
            }

            // 5. Live-Foto auf dem Laser auslösen
            $snap = $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'GET', '/v1/camera/snap', ['name' => 'main', 'timeOut' => 30000], []);
            $remoteFile = (string) ($snap['data']['filename'] ?? $snap['data']['params']['filename'] ?? '');

            $rawJpegBytes = null;
            if ($remoteFile !== '') {
                // 6. 4000x3000 Raw-JPEG über Kanal 9 herunterladen (filetype = 5)
                $rawJpegBytes = $this->v2DownloadFileFromLaser($mainWs, $fileWs, $txId, $sessionToken, $remoteFile, 5, 9);
            }

            if (!is_string($rawJpegBytes) || strlen($rawJpegBytes) < 15000) {
                return false;
            }

            // Raw-Capture (4000x3000) atomar & shared-writable sichern
            $this->writeFileAtomicallyShared($hostAppdataDir . '/capture.jpg', $rawJpegBytes);

            // 7. Mit 21x21 Werkskalibrierung (global_calib.json) in das 2000x2000 px Arbeitsflächenbild (10 px = 1 mm) entzerren
            $warpedOk = $this->warpRawCameraImageWithCalib($rawJpegBytes, $targetBackgroundPath, $calibPath);
            if ($warpedOk && @is_file($targetBackgroundPath)) {
                $warpedBytes = @file_get_contents($targetBackgroundPath);
                if (is_string($warpedBytes) && $warpedBytes !== '') {
                    $this->writeFileAtomicallyShared($hostAppdataDir . '/background.jpg', $warpedBytes);
                }
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        } finally {
            if (is_resource($mainWs) && $sessionToken !== '') {
                if ($fillLightChanged) {
                    $restResp = null;
                    try {
                        $restResp = $this->v2SendJsonCmd(
                            $mainWs,
                            $txId,
                            $sessionToken,
                            'PUT',
                            '/v1/peripheral/param',
                            ['type' => 'fill_light'],
                            [
                                'action' => 'set_bri',
                                'front'  => $restoreFrontBri,
                                'back'   => $restoreBackBri,
                            ]
                        );
                    } catch (\Throwable) {
                    }
                    if (!is_array($restResp)) {
                        $this->sendLegacyHttpFillLightFallback($safeIp, $restoreFrontBri, $restoreBackBri);
                    }
                }

                if ($peripheralsDimmed) {
                    try {
                        $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/peripheral/param', ['type' => 'ir_led'], ['action' => 'on', 'index' => 0]);
                        $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/laser-head/parameter', [], ['action' => 'power', 'ctrl' => 'set', 'value' => 128]);
                    } catch (\Throwable) {
                    }
                }
            }

            if (is_resource($fileWs)) {
                @fwrite($fileWs, chr(0x88) . chr(0x80) . random_bytes(4));
                @fclose($fileWs);
            }
            if (is_resource($mainWs)) {
                @fwrite($mainWs, chr(0x88) . chr(0x80) . random_bytes(4));
                @fclose($mainWs);
            }
        }
    }

    /**
     * Wartet (Standard 650 ms), damit der V4L2-Kamerapuffer alte Frames verwirft und sich die Belichtung ohne LED-Blendfleck einpegelt.
     */
    protected function waitForCameraExposureSettleUs(int $microseconds = 650000): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    /**
     * Nicht-fataler Legacy-HTTP-Fallback (Port 8080) für fill_light, falls die Firmware den WS-Befehl ablehnt.
     */
    protected function sendLegacyHttpFillLightFallback(string $ip, int $front, int $back): void
    {
        if (app()->runningUnitTests() && !$this->allowV2SocketInTests) {
            return;
        }
        try {
            $body = (string) json_encode([
                'action' => 'set_bri',
                'front'  => max(0, min(255, $front)),
                'back'   => max(0, min(255, $back)),
            ], JSON_UNESCAPED_SLASHES);
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n",
                    'content'       => $body,
                    'timeout'       => 0.35,
                    'ignore_errors' => true,
                ],
            ]);
            @file_get_contents("http://{$ip}:8080/peripheral/fill_light", false, $ctx);
        } catch (\Throwable) {
        }
    }

    /**
     * Entzerrt das rohe 4000x3000 px Kamerabild des xTool F2 Ultra UV anhand des 21x21 Stützpunktrasters (data0)
     * aus global_calib.json auf das exakt maßstabsgetreue 2000x2000 px (200x200 mm, 10 px = 1 mm) Arbeitsflächenbild.
     */
    public function warpRawCameraImageWithCalib(string $rawJpegBytes, string $targetBackgroundPath, ?string $calibJsonPath = null): bool
    {
        $src = @imagecreatefromstring($rawJpegBytes);
        if (!$src) {
            return false;
        }

        $pts = $this->parseGlobalCalibGrid21x21($calibJsonPath);
        $dst = imagecreatetruecolor(2000, 2000);

        if ($pts !== null) {
            // 20x20 Hauptzellen à 100x100 px mit 2x2 Sub-Interpolation (40x40 Patches à 50x50 px => < 0.25 px Abweichung zu GS009CorrectImageWithZSync)
            $sub = 2;
            for ($r = 0; $r < 20; $r++) {
                for ($c = 0; $c < 20; $c++) {
                    $p00 = $pts[$r][$c];
                    $p10 = $pts[$r][$c + 1];
                    $p01 = $pts[$r + 1][$c];
                    $p11 = $pts[$r + 1][$c + 1];

                    for ($sr = 0; $sr < $sub; $sr++) {
                        $v0 = $sr / $sub;
                        $v1 = ($sr + 1) / $sub;
                        for ($sc = 0; $sc < $sub; $sc++) {
                            $u0 = $sc / $sub;
                            $u1 = ($sc + 1) / $sub;

                            $sx0 = (int) round((1 - $u0) * (1 - $v0) * $p00[0] + $u0 * (1 - $v0) * $p10[0] + (1 - $u0) * $v0 * $p01[0] + $u0 * $v0 * $p11[0]);
                            $sy0 = (int) round((1 - $u0) * (1 - $v0) * $p00[1] + $u0 * (1 - $v0) * $p10[1] + (1 - $u0) * $v0 * $p01[1] + $u0 * $v0 * $p11[1]);
                            $sx1 = (int) round((1 - $u1) * (1 - $v1) * $p00[0] + $u1 * (1 - $v1) * $p10[0] + (1 - $u1) * $v1 * $p01[0] + $u1 * $v1 * $p11[0]);
                            $sy1 = (int) round((1 - $u1) * (1 - $v1) * $p00[1] + $u1 * (1 - $v1) * $p10[1] + (1 - $u1) * $v1 * $p01[1] + $u1 * $v1 * $p11[1]);

                            $dx = ($c * 100) + ($sc * 50);
                            $dy = ($r * 100) + ($sr * 50);
                            imagecopyresampled(
                                $dst,
                                $src,
                                $dx,
                                $dy,
                                $sx0,
                                $sy0,
                                50,
                                50,
                                max(1, $sx1 - $sx0),
                                max(1, $sy1 - $sy0)
                            );
                        }
                    }
                }
            }
        } else {
            // Fallback falls keine global_calib.json vorliegt: Zentraler 2360x2360 Ausschnitt aus 4000x3000
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $cropW = (int) round(min($srcW, $srcH) * 0.78);
            $cropX = (int) round(($srcW - $cropW) / 2);
            $cropY = (int) round(($srcH - $cropW) / 2);
            imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, 2000, 2000, max(1, $cropW), max(1, $cropW));
        }

        ob_start();
        imagejpeg($dst, null, 92);
        $jpgBytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        if ($jpgBytes === '') {
            return false;
        }

        return $this->writeFileAtomicallyShared($targetBackgroundPath, $jpgBytes);
    }

    /**
     * Liest das 21x21 Kalibrierungsgitter (data0) aus global_calib.json.
     *
     * @return array<int, array<int, array{0: int, 1: int}>>|null
     */
    private function parseGlobalCalibGrid21x21(?string $preferredPath = null): ?array
    {
        $publicDisk = Storage::disk('public');
        $candidates = array_filter([
            $preferredPath,
            $publicDisk->path('bestellungen/xtool/host_appdata/global_calib.json'),
            $publicDisk->path('bestellungen/xtool/global_calib.json'),
            '/mnt/c/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/global_calib.json',
            'C:/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/global_calib.json',
        ]);

        foreach ($candidates as $file) {
            if (!@is_file($file)) {
                continue;
            }
            $json = json_decode((string) @file_get_contents($file), true);
            $data0 = (string) ($json['data0'] ?? '');
            if ($data0 === '') {
                continue;
            }
            if (preg_match_all('/\[(\d+),(\d+)\]/', $data0, $m, PREG_SET_ORDER) && count($m) >= 441) {
                $pts = [];
                for ($r = 0; $r < 21; $r++) {
                    for ($c = 0; $c < 21; $c++) {
                        $idx = ($r * 21) + $c;
                        $pts[$r][$c] = [(int) $m[$idx][1], (int) $m[$idx][2]];
                    }
                }
                return $pts;
            }
        }

        return null;
    }

    protected function resolveOrDownloadGlobalCalibPath($mainWs, $fileWs, int &$txId, string $sessionToken, string $hostAppdataDir): ?string
    {
        $localCalib = $hostAppdataDir . '/global_calib.json';
        if (@is_file($localCalib) && (int) @filesize($localCalib) > 1000) {
            return $localCalib;
        }

        $hostMountCalib = '/mnt/c/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/global_calib.json';
        if (@is_file($hostMountCalib) && (int) @filesize($hostMountCalib) > 1000) {
            $mountBytes = @file_get_contents($hostMountCalib);
            if (is_string($mountBytes) && strlen($mountBytes) > 1000) {
                $this->writeFileAtomicallyShared($localCalib, $mountBytes);
                return $localCalib;
            }
        }

        $calibBytes = $this->v2DownloadFileFromLaser($mainWs, $fileWs, $txId, $sessionToken, 'global_calib.json', 3, 2);
        if (is_string($calibBytes) && strlen($calibBytes) > 1000 && str_contains($calibBytes, 'data0')) {
            $this->writeFileAtomicallyShared($localCalib, $calibBytes);
            return $localCalib;
        }

        return null;
    }

    protected function v2DownloadFileFromLaser($mainWs, $fileWs, int &$txId, string $sessionToken, string $remoteFilename, int $fileType, int $channel): ?string
    {
        $dlInit = $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/filetransfer/download', [], [
            'channel'    => $channel,
            'digesttype' => 1,
            'filename'   => $remoteFilename,
            'filetype'   => $fileType,
            'packetsize' => 1048576,
        ]);

        $fileSize = (int) ($dlInit['data']['filesize'] ?? 0);
        if ($fileSize <= 0 || $fileSize > 25 * 1024 * 1024) {
            return null;
        }

        // FILE_REQUEST (cmd = 1, 1-Byte channel, 5-Byte BE offset = 0, 3-Byte BE windowSize = $fileSize)
        $reqPkt = chr(1) . chr($channel)
            . "\x00\x00\x00\x00\x00"
            . chr(($fileSize >> 16) & 0xFF) . chr(($fileSize >> 8) & 0xFF) . chr($fileSize & 0xFF);
        $this->v2WsSendBinary($fileWs, $this->v2EncodeFrame($reqPkt, 33, false));

        $buf = str_repeat("\x00", $fileSize);
        $received = 0;
        $rawStream = '';
        $deadline = microtime(true) + 8.0;

        while ($received < $fileSize && microtime(true) < $deadline) {
            $wsPayload = $this->v2WsReadFrame($fileWs);
            if ($wsPayload === null) {
                break;
            }
            $rawStream .= $wsPayload;
            while (strlen($rawStream) >= 10) {
                if (ord($rawStream[0]) !== 0xBA || ord($rawStream[1]) !== 0xBE) {
                    $rawStream = substr($rawStream, 1);
                    continue;
                }
                $pktLen = (ord($rawStream[2]) << 16) | (ord($rawStream[3]) << 8) | ord($rawStream[4]);
                if (strlen($rawStream) < 10 + $pktLen) {
                    break;
                }
                $body = substr($rawStream, 10, $pktLen);
                $rawStream = substr($rawStream, 10 + $pktLen);

                // FILE_DATA: cmd = 129 (0x81), 1-Byte channel, 5-Byte BE offset, chunk bytes
                if (strlen($body) > 7 && ord($body[0]) === 129 && ord($body[1]) === $channel) {
                    $offset = (ord($body[2]) << 32) | (ord($body[3]) << 24) | (ord($body[4]) << 16) | (ord($body[5]) << 8) | ord($body[6]);
                    $chunk = substr($body, 7);
                    $cLen = strlen($chunk);
                    if ($offset >= 0 && $offset < $fileSize && $cLen > 0) {
                        $writeLen = min($cLen, $fileSize - $offset);
                        $buf = substr_replace($buf, substr($chunk, 0, $writeLen), $offset, $writeLen);
                        $received += $writeLen;
                    }
                }
            }
        }

        $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/filetransfer/finish', [], [
            'channel' => $channel,
            'code'    => 0,
            'message' => 'file transfer finish',
        ]);

        return $received >= $fileSize ? $buf : null;
    }

    /**
     * Überträgt einen Gravur-Auftrag (tmp.xf -> autoStart: 0 / WORK_PREPARED) oder eine Rahmen-Vorschau
     * (tmpFrame.gcode -> autoStart: 1 / loopPrint: 1) direkt per V2-TLS-WebSocket (Port 28900) an den xTool F2 Ultra UV.
     *
     * @return array{ok: bool, task_id: ?string, target_z_mm?: ?float, error: ?string}
     */
    public function sendJobToLaserV2(
        string $jobType,
        string $payloadBytes,
        string $ip = self::DEFAULT_LASER_IP,
        ?float $focusMm = null,
        string $equipmentKey = 'flatbed_standard'
    ): array {
        if (app()->runningUnitTests() && !$this->allowV2SocketInTests) {
            return ['ok' => false, 'task_id' => null, 'target_z_mm' => null, 'error' => 'Unit-Test-Modus aktiv.'];
        }

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($ip, self::DEFAULT_LASER_IP);
        if (!$this->probeLaserReachability($safeIp, true)) {
            return [
                'ok'          => false,
                'task_id'     => null,
                'target_z_mm' => null,
                'error'       => "xTool F2 Ultra UV unter {$safeIp}:" . self::DEFAULT_V2_PORT . ' aktuell nicht im WLAN erreichbar (Standby oder ausgeschaltet).',
            ];
        }

        $uuid = (string) Str::uuid();
        $mainWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=instruction");
        if (!is_resource($mainWs)) {
            return [
                'ok'          => false,
                'task_id'     => null,
                'target_z_mm' => null,
                'error'       => "V2-Steuerkanal zu {$safeIp}:" . self::DEFAULT_V2_PORT . ' konnte nicht geöffnet werden.',
            ];
        }

        $fileWs = null;
        try {
            $txId = 0;
            $parityToken = (string) env('XTOOL_PARITY_TOKEN', self::DEFAULT_PARITY_TOKEN);
            $parity = $this->v2SendJsonCmd($mainWs, $txId, '', 'GET', '/v1/user/parity', [], [
                'userID'   => self::DEFAULT_USER_ID,
                'userKey'  => self::DEFAULT_USER_KEY,
                'timezone' => 'Europe/Berlin',
                'token'    => $parityToken,
            ]);

            $sessionToken = (string) ($parity['data']['sessionToken'] ?? '');
            if ($sessionToken === '') {
                return [
                    'ok'          => false,
                    'task_id'     => null,
                    'target_z_mm' => null,
                    'error'       => 'Authentifizierung am xTool F2 Ultra UV fehlgeschlagen (kein Session-Token).',
                ];
            }

            $fileWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=file_stream");
            if (!is_resource($fileWs)) {
                return [
                    'ok'          => false,
                    'task_id'     => null,
                    'target_z_mm' => null,
                    'error'       => 'V2-Dateikanal (file_stream) konnte nicht geöffnet werden.',
                ];
            }

            $isFraming = ($jobType === 'framing');
            $targetZMm = null;
            $focusControlCmd = self::buildZAxisFocusControlCommand($focusMm, $jobType, $equipmentKey);
            if ($focusControlCmd !== null) {
                $this->v2SendJsonCmd(
                    $mainWs,
                    $txId,
                    $sessionToken,
                    $focusControlCmd['method'],
                    $focusControlCmd['url'],
                    $focusControlCmd['params'],
                    $focusControlCmd['data']
                );
                $targetZMm = (float) $focusControlCmd['data']['z'];
            }

            $remoteFilename = $isFraming ? 'tmpFrame.gcode' : 'tmp.xf';
            $channel = $isFraming ? 61 : 62;

            $uploaded = $this->v2UploadFileToLaser(
                $mainWs,
                $fileWs,
                $txId,
                $sessionToken,
                $remoteFilename,
                $payloadBytes,
                1,
                $channel
            );

            if (!$uploaded) {
                return [
                    'ok'          => false,
                    'task_id'     => null,
                    'target_z_mm' => $targetZMm,
                    'error'       => "Dateiübertragung ({$remoteFilename}) an den Laser wurde nicht bestätigt.",
                ];
            }

            $taskId = (string) Str::uuid();
            if ($isFraming) {
                $cfgResp = $this->v2SendJsonCmd(
                    $mainWs,
                    $txId,
                    $sessionToken,
                    'PUT',
                    '/v1/processing/upload/config',
                    ['loopPrint' => 1, 'gcodeType' => 'frame'],
                    ['fileType' => 'txt', 'autoStart' => 1, 'loopPrint' => 1, 'gcodeType' => 'frame']
                );
            } else {
                // Sicherheitsregel: autoStart bleibt strikt 0 (WORK_PREPARED -> Start erst per physischem Knopf am Laser)
                $cfgResp = $this->v2SendJsonCmd(
                    $mainWs,
                    $txId,
                    $sessionToken,
                    'PUT',
                    '/v1/processing/upload/config',
                    ['autoStart' => 0, 'taskId' => $taskId],
                    ['autoStart' => 0, 'fileType' => 'xf', 'taskId' => $taskId]
                );
            }

            $cfgCode = isset($cfgResp['code']) ? (int) $cfgResp['code'] : 0;
            if ($cfgResp === null || $cfgCode !== 0) {
                return [
                    'ok'          => false,
                    'task_id'     => $taskId,
                    'target_z_mm' => $targetZMm,
                    'error'       => 'Laser hat die Auftrags-Aktivierung (/v1/processing/upload/config) abgelehnt.',
                ];
            }

            return [
                'ok'          => true,
                'task_id'     => $taskId,
                'target_z_mm' => $targetZMm,
                'error'       => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'          => false,
                'task_id'     => null,
                'target_z_mm' => null,
                'error'       => $e->getMessage(),
            ];
        } finally {
            if (is_resource($fileWs)) {
                @fwrite($fileWs, chr(0x88) . chr(0x80) . random_bytes(4));
                @fclose($fileWs);
            }
            if (is_resource($mainWs)) {
                @fwrite($mainWs, chr(0x88) . chr(0x80) . random_bytes(4));
                @fclose($mainWs);
            }
        }
    }

    /**
     * Lädt eine Datei (tmp.xf oder tmpFrame.gcode) über das native V2-Fensterprotokoll (0xBABE, Kanal 61/62)
     * auf den xTool F2 Ultra UV hoch und wartet auf die JSON-Erfolgsquittung ({channel, code: 0}).
     */
    protected function v2UploadFileToLaser(
        $mainWs,
        $fileWs,
        int &$txId,
        string $sessionToken,
        string $remoteFilename,
        string $fileBytes,
        int $fileType = 1,
        int $channel = 62
    ): bool {
        $fileSize = strlen($fileBytes);
        if ($fileSize <= 0) {
            return false;
        }

        $upInit = $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/filetransfer/upload', [], [
            'channel'    => $channel,
            'digestdata' => md5($fileBytes),
            'digesttype' => 1,
            'filename'   => $remoteFilename,
            'filesize'   => $fileSize,
            'filetype'   => $fileType,
            'packetsize' => 1048576,
        ]);

        if (!is_array($upInit) || ((int) ($upInit['code'] ?? -1)) !== 0) {
            return false;
        }

        $negotiatedMax = max(16384, min(131072, (int) ($upInit['data']['packetsize'] ?? 131072)));
        $rawStream = '';
        $sentTotal = 0;
        $deadline = microtime(true) + 12.0;

        while (microtime(true) < $deadline) {
            $wsPayload = $this->v2WsReadFrame($fileWs);
            if ($wsPayload === null) {
                break;
            }
            $rawStream .= $wsPayload;

            while (strlen($rawStream) >= 10) {
                if (ord($rawStream[0]) !== 0xBA || ord($rawStream[1]) !== 0xBE) {
                    $rawStream = substr($rawStream, 1);
                    continue;
                }
                $pktLen = (ord($rawStream[2]) << 16) | (ord($rawStream[3]) << 8) | ord($rawStream[4]);
                if (strlen($rawStream) < 10 + $pktLen) {
                    break;
                }

                $proto = ord($rawStream[5]) & 0x7F;
                $body = substr($rawStream, 10, $pktLen);
                $rawStream = substr($rawStream, 10 + $pktLen);

                // 1) Binary FILE_REQUEST vom Laser: cmd = 1, 1-Byte channel, 5-Byte BE offset, 3-Byte BE windowSize
                if ($proto === 33 && strlen($body) >= 10 && ord($body[0]) === 1 && ord($body[1]) === $channel) {
                    $offset = (ord($body[2]) << 32) | (ord($body[3]) << 24) | (ord($body[4]) << 16) | (ord($body[5]) << 8) | ord($body[6]);
                    $windowSize = (ord($body[7]) << 16) | (ord($body[8]) << 8) | ord($body[9]);
                    if ($windowSize <= 0 || $offset >= $fileSize) {
                        continue;
                    }

                    $endOffset = min($fileSize, $offset + $windowSize);
                    $cursor = $offset;
                    while ($cursor < $endOffset) {
                        $chunkLen = min($negotiatedMax, 65536, $endOffset - $cursor);
                        $chunk = substr($fileBytes, $cursor, $chunkLen);
                        // FILE_DATA: cmd = 129 (0x81), 1-Byte channel, 5-Byte BE offset, chunk bytes
                        $dataPkt = chr(129)
                            . chr($channel)
                            . chr(($cursor >> 32) & 0xFF)
                            . chr(($cursor >> 24) & 0xFF)
                            . chr(($cursor >> 16) & 0xFF)
                            . chr(($cursor >> 8) & 0xFF)
                            . chr($cursor & 0xFF)
                            . $chunk;
                        $this->v2WsSendBinary($fileWs, $this->v2EncodeFrame($dataPkt, 33, false));
                        $cursor += $chunkLen;
                        $sentTotal += $chunkLen;
                    }
                } elseif ($proto === 4) {
                    // 2) JSON-Abschlussquittung auf file_stream: {"channel": 62, "code": 0, "message": "success"}
                    $msg = json_decode($body, true);
                    if (is_array($msg) && (int) ($msg['channel'] ?? -1) === $channel) {
                        return ((int) ($msg['code'] ?? -1)) === 0;
                    }
                }
            }
        }

        return $sentTotal >= $fileSize;
    }

    private function v2Crc16Arc(string $data): int
    {
        $crc = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 1) ? ((($crc >> 1) ^ 0xA001) & 0xFFFF) : (($crc >> 1) & 0xFFFF);
            }
        }
        return $crc;
    }

    private function v2EncodeFrame(string $payload, int $protocolType = 4, bool $calcCrc = true): string
    {
        $len = strlen($payload);
        $bodyCrc = $calcCrc ? $this->v2Crc16Arc($payload) : 0;
        $hdr8 = chr(0xBA) . chr(0xBE)
            . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF)
            . chr(($protocolType & 0x7F) | ($calcCrc ? 0 : 0x80))
            . chr(($bodyCrc >> 8) & 0xFF) . chr($bodyCrc & 0xFF);
        $hdrCrc = $this->v2Crc16Arc($hdr8);

        return $hdr8 . chr(($hdrCrc >> 8) & 0xFF) . chr($hdrCrc & 0xFF) . $payload;
    }

    /**
     * @return resource|null
     */
    protected function v2WsConnect(string $ip, int $port, string $path)
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("tls://{$ip}:{$port}", $errno, $errstr, 0.90, STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($fp)) {
            return null;
        }

        stream_set_timeout($fp, 8);
        $key = base64_encode(random_bytes(16));
        $req = "GET {$path} HTTP/1.1\r\n"
            . "Host: {$ip}:{$port}\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";
        @fwrite($fp, $req);

        $resp = '';
        while (($line = @fgets($fp)) !== false) {
            $resp .= $line;
            if ($line === "\r\n") {
                break;
            }
        }
        if (!str_contains($resp, '101')) {
            @fclose($fp);
            return null;
        }

        return $fp;
    }

    private function v2WsSendBinary($fp, string $data): void
    {
        $len = strlen($data);
        $head = chr(0x82);
        if ($len <= 125) {
            $head .= chr(0x80 | $len);
        } elseif ($len <= 65535) {
            $head .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $head .= chr(0x80 | 127) . pack('J', $len);
        }
        $mask = random_bytes(4);
        $head .= $mask;
        $masked = $len > 0 ? substr($data ^ str_repeat($mask, (int) ceil($len / 4)), 0, $len) : '';
        @fwrite($fp, $head . $masked);
    }

    private function v2WsReadFrame($fp): ?string
    {
        $b12 = '';
        while (strlen($b12) < 2) {
            $c = @fread($fp, 2 - strlen($b12));
            if ($c === false || $c === '') {
                return null;
            }
            $b12 .= $c;
        }
        $opcode = ord($b12[0]) & 0x0F;
        $masked = (ord($b12[1]) & 0x80) !== 0;
        $len = ord($b12[1]) & 0x7F;
        if ($len === 126) {
            $ext = '';
            while (strlen($ext) < 2) {
                $c = @fread($fp, 2 - strlen($ext));
                if ($c === false || $c === '') {
                    return null;
                }
                $ext .= $c;
            }
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = '';
            while (strlen($ext) < 8) {
                $c = @fread($fp, 8 - strlen($ext));
                if ($c === false || $c === '') {
                    return null;
                }
                $ext .= $c;
            }
            $len = unpack('J', $ext)[1];
        }
        $mask = '';
        if ($masked) {
            while (strlen($mask) < 4) {
                $c = @fread($fp, 4 - strlen($mask));
                if ($c === false || $c === '') {
                    return null;
                }
                $mask .= $c;
            }
        }
        $payload = '';
        while (strlen($payload) < $len) {
            $chunk = @fread($fp, $len - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }
        if ($masked && $len > 0) {
            $payload = substr($payload ^ str_repeat($mask, (int) ceil($len / 4)), 0, $len);
        }
        if ($opcode === 0x08) {
            return null;
        }

        return $payload;
    }

    protected function v2SendJsonCmd($ws, int &$txId, string $sessionToken, string $method, string $url, array $params = [], array $data = []): ?array
    {
        $txId++;
        $cmd = [
            'type'          => 'request',
            'method'        => $method,
            'url'           => $url,
            'params'        => empty($params) ? (object) [] : $params,
            'data'          => empty($data) ? (object) [] : $data,
            'timestamp'     => (int) round(microtime(true) * 1000),
            'transactionId' => $txId,
        ];
        if ($sessionToken !== '') {
            $cmd['sessionToken'] = $sessionToken;
        }

        $jsonBytes = (string) json_encode($cmd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->v2WsSendBinary($ws, $this->v2EncodeFrame($jsonBytes, 4, true));

        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $frame = $this->v2WsReadFrame($ws);
            if ($frame === null) {
                return null;
            }
            if (strlen($frame) <= 10 || ord($frame[0]) !== 0xBA || ord($frame[1]) !== 0xBE) {
                continue;
            }
            $proto = ord($frame[5]) & 0x7F;
            if ($proto !== 4) {
                continue;
            }
            $decoded = json_decode(substr($frame, 10), true);
            if (is_array($decoded) && ($decoded['type'] ?? '') === 'response' && (int) ($decoded['transactionId'] ?? 0) === $txId) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Erkennt vollautomatisch Position (X_mm, Y_mm) und Drehwinkel (angle_deg) des eingelegten Rohlings
     * auf dem 200x200 mm Lamellenrost mittels 2D-Integralbild (Slat-Occlusion + Horizontal-Edge + Momenten-Analyse).
     * Läuft lokal in < 25 ms ohne Cloud-API.
     *
     * @return array{
     *   center_x_mm: float,
     *   center_y_mm: float,
     *   angle_deg: float,
     *   blank_width_mm: float,
     *   blank_height_mm: float,
     *   confidence: int,
     *   detected: bool,
     *   camera_url: string,
     *   camera_updated_at: string,
     *   laser_online: bool
     * }
     */
    public function detectBlankPositionOnBed(
        ?Product $product = null,
        array $itemConfig = [],
        string $side = 'front',
        bool $forceCameraSync = false,
        ?string $lightMode = null
    ): array {
        $resolvedLightMode = self::normalizeCameraLightMode($lightMode, $itemConfig);
        $camInfo = $this->syncCameraFromHostOrLaser(null, $forceCameraSync, self::DEFAULT_LASER_IP, $resolvedLightMode);
        [$blankWidthMm, $blankHeightMm] = $this->resolvePhysicalBlankDimensionsMm($product, $itemConfig);

        $productConfig = $product
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
            : [];
        $workingAreaCal = app(FileDownloadService::class)->resolveEngravingWorkingAreaCalibration(
            $product,
            $productConfig,
            $itemConfig,
            false
        );
        $engraveWidthMm = round(max(3.0, min(195.0, (float) ($workingAreaCal['engraveWidthMm'] ?? $blankWidthMm))), 2);
        $engraveHeightMm = round(max(3.0, min(195.0, (float) ($workingAreaCal['engraveHeightMm'] ?? $blankHeightMm))), 2);
        $offsetXMm = ($side === 'back')
            ? -(float) ($workingAreaCal['centerOffsetXMm'] ?? 0.0)
            : (float) ($workingAreaCal['centerOffsetXMm'] ?? 0.0);
        $offsetYMm = (float) ($workingAreaCal['centerOffsetYMm'] ?? 0.0);

        $defaultResult = [
            'center_x_mm'       => round(max(5.0, min(195.0, 100.0 + $offsetXMm)), 1),
            'center_y_mm'       => round(max(5.0, min(195.0, 100.0 + $offsetYMm)), 1),
            'angle_deg'         => 0.0,
            'blank_width_mm'    => round($blankWidthMm, 2),
            'blank_height_mm'   => round($blankHeightMm, 2),
            'engrave_width_mm'  => $engraveWidthMm,
            'engrave_height_mm' => $engraveHeightMm,
            'confidence'        => 50,
            'detected'          => false,
            'camera_url'        => $camInfo['camera_url'],
            'camera_updated_at' => $camInfo['updated_at'],
            'source'            => $camInfo['source'],
            'laser_online'      => $camInfo['laser_online'],
            'laser_ip'          => $camInfo['laser_ip'],
            'relay_error'       => $camInfo['relay_error'] ?? null,
            'light_mode'        => $resolvedLightMode,
        ];

        $bgPath = $camInfo['camera_path'];
        if (!@is_file($bgPath) || !function_exists('imagecreatefromjpeg')) {
            return $defaultResult;
        }

        $src = @imagecreatefromjpeg($bgPath);
        if (!$src) {
            $raw = @file_get_contents($bgPath);
            $src = $raw ? @imagecreatefromstring($raw) : false;
        }
        if (!$src) {
            return $defaultResult;
        }

        // 400 x 400 Raster => 1 Zelle = 0.5 mm (200 mm / 400 = 0.5 mm)
        $gridW = 400;
        $gridH = 400;
        $small = imagecreatetruecolor($gridW, $gridH);
        imagecopyresampled($small, $src, 0, 0, 0, 0, $gridW, $gridH, imagesx($src), imagesy($src));
        imagedestroy($src);

        $lum = [];
        for ($y = 0; $y < $gridH; $y++) {
            $row = [];
            for ($x = 0; $x < $gridW; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $row[$x] = (int) (($r * 77 + $g * 150 + $b * 29) >> 8);
            }
            $lum[$y] = $row;
        }
        imagedestroy($small);

        // 1. Unteren silbernen Gehäuserand des xTool F2 Ultra UV automatisch ausblenden ($maxBedY)
        // Auf unbedeckten Aluminium-Lamellen am linken (x=6..35) und rechten (x=364..393) Rand liegen immer
        // dunkle Lamellen-Spalte (lMin/rMin < 75), während der durchgängige Metallrahmen unten (ab ca. y = 336)
        // durchgehend hell ist (lMin > 95 UND rMin > 95 bzw. Mittelwert > 175).
        $maxBedY = 384;
        for ($y = 280; $y < 392; $y++) {
            $lMin = 255;
            $rMin = 255;
            $mSum = 0;
            for ($x = 6; $x < 36; $x++) {
                $v = $lum[$y][$x];
                $mSum += $v;
                if ($v < $lMin) {
                    $lMin = $v;
                }
            }
            for ($x = 364; $x < 394; $x++) {
                $v = $lum[$y][$x];
                $mSum += $v;
                if ($v < $rMin) {
                    $rMin = $v;
                }
            }
            if (($lMin > 95 && $rMin > 95) || ($mSum / 60.0) > 175.0) {
                $maxBedY = $y - 2;
                break;
            }
        }

        // 2. Exaktes Spalten-Lamellenprofil aus dem oberen unbedeckten Rand (y = 4..20, Y = 2..10 mm) bestimmen
        $slatProfile = array_fill(0, $gridW, 55.0);
        for ($x = 0; $x < $gridW; $x++) {
            $vals = [];
            for ($y = 4; $y <= 20; $y++) {
                $vals[] = $lum[$y][$x];
            }
            sort($vals);
            $slatProfile[$x] = (float) $vals[8];
        }

        // 3. Gesättigte Objekt-Maske (0.0 .. 1.0) über lokalen Referenz-Kontrast zu $slatProfile[$x]
        // Unabhängig von der Kamera-Belichtung stimmt auf unbedeckten Lamellen UND Spalten lum[$y][$x] mit
        // dem oberen Referenzprofil $slatProfile[$x] überein (maxDiffLocal < 28). Sobald ein Rohling (Glas,
        // Edelstahl, Schiefer, Acryl) aufliegt, weicht mindestens der überdeckte Nachbar-Spalt oder Lamellensteg stark ab.
        $diffMap = array_fill(0, $gridH, array_fill(0, $gridW, 0.0));
        for ($y = 1; $y < $gridH; $y++) {
            for ($x = 0; $x < $gridW; $x++) {
                $v = (float) $lum[$y][$x];
                $d0 = abs($v - $slatProfile[$x]);
                $dL = $x > 0 ? abs($v - $slatProfile[$x - 1]) : $d0;
                $dR = ($x + 1) < $gridW ? abs($v - $slatProfile[$x + 1]) : $d0;
                $diffMap[$y][$x] = min($d0, $dL, $dR);
            }
        }

        $intScore = array_fill(0, $gridH + 1, array_fill(0, $gridW + 1, 0.0));
        $cellMap = array_fill(0, $gridH, array_fill(0, $gridW, 0.0));

        for ($y = 1; $y < $gridH; $y++) {
            $rowSum = 0.0;
            $prevIntRow = $intScore[$y];
            $currIntRow = [0 => 0.0];
            $inBedY = ($y >= 16 && $y <= $maxBedY);

            for ($x = 0; $x < $gridW; $x++) {
                $score = 0.0;
                if ($inBedY && $x >= 14 && $x < $gridW - 14) {
                    $diff = $diffMap[$y][$x];
                    $edgeH = abs((float) $lum[$y][$x] - (float) $lum[$y - 1][$x]);

                    $maxDiffLocal = $diff;
                    for ($dx = -12; $dx <= 12; $dx += 2) {
                        $d = $diffMap[$y][$x + $dx];
                        if ($d > $maxDiffLocal) {
                            $maxDiffLocal = $d;
                        }
                    }

                    if ($diff > 32.0) {
                        $score = 1.0;
                    } elseif ($maxDiffLocal > 44.0 && ($diff > 18.0 || $edgeH > 12.0 || (float) $lum[$y][$x] > 185.0)) {
                        $score = 0.85;
                    } elseif ($diff > 22.0 && $edgeH > 14.0) {
                        $score = min(1.0, ($diff + $edgeH) / 48.0);
                    }
                }
                $cellMap[$y][$x] = $score;
                $rowSum += $score;
                $currIntRow[$x + 1] = $prevIntRow[$x + 1] + $rowSum;
            }
            $intScore[$y + 1] = $currIntRow;
        }

        // 4. Rohling-Fenster (in 0.5-mm-Zellen) für die Grob-Lokalisierung über den Arbeitsbereich schieben
        $winW = max(16, min(328, (int) round($blankWidthMm * 1.80)));
        $winH = max(16, min($maxBedY - 24, (int) round($blankHeightMm * 1.80)));
        $areaCells = (float) max(1, $winW * $winH);

        $bestRatio = -INF;
        $bestCxCell = 200;
        $bestCyCell = (int) round($maxBedY * 0.52);
        $padCells = 10;

        $minSearchX = max((int) round($winW / 2) + 8, 24);
        $maxSearchX = min($gridW - (int) round($winW / 2) - 8, 376);
        $minSearchY = max((int) round($winH / 2) + 12, 24);
        $maxSearchY = min($maxBedY - (int) round($winH / 2) - 4, 360);
        if ($minSearchX > $maxSearchX) {
            $minSearchX = 160;
            $maxSearchX = 240;
        }
        if ($minSearchY > $maxSearchY) {
            $minSearchY = 140;
            $maxSearchY = 210;
        }

        for ($cy = (int) $minSearchY; $cy <= (int) $maxSearchY; $cy += 2) {
            $y1 = max(1, (int) round($cy - ($winH / 2.0)));
            $y2 = min($maxBedY, $y1 + $winH);
            $oy1 = max(1, $y1 - $padCells);
            $oy2 = min($maxBedY, $y2 + $padCells);

            for ($cx = (int) $minSearchX; $cx <= (int) $maxSearchX; $cx += 2) {
                $x1 = max(1, (int) round($cx - ($winW / 2.0)));
                $x2 = min($gridW - 1, $x1 + $winW);
                $ox1 = max(1, $x1 - $padCells);
                $ox2 = min($gridW - 1, $x2 + $padCells);

                $innerSum = $intScore[$y2][$x2] - $intScore[$y1][$x2] - $intScore[$y2][$x1] + $intScore[$y1][$x1];
                $outerTotal = $intScore[$oy2][$ox2] - $intScore[$oy1][$ox2] - $intScore[$oy2][$ox1] + $intScore[$oy1][$ox1];
                $outerArea = max(1.0, (($ox2 - $ox1) * ($oy2 - $oy1)) - $areaCells);

                $innerMean = $innerSum / $areaCells;
                $ringMean = ($outerTotal - $innerSum) / $outerArea;

                $distCenterMm = hypot(($cx * 0.5) - 100.0, ($cy * 0.5) - 96.0);
                $centerPenalty = $distCenterMm * 0.002;

                $contrastScore = ($innerMean - ($ringMean * 0.72)) - $centerPenalty;
                if ($contrastScore > $bestRatio) {
                    $bestRatio = $contrastScore;
                    $bestCxCell = $cx;
                    $bestCyCell = $cy;
                }
            }
        }

        // 5. Exakten geometrischen Mittelpunkt über die realen Außenkanten im zentralen Horizontal-/Vertikalband bestimmen
        // Wichtig: Statt roher Helligkeit (> 105, was bei Auto-Belichtung auf blanken Aluminium-Lamellen auslöst)
        // nutzen wir den Lamellen-Referenzkontrast |lum - slatProfile| > 34 bzw. gesicherte Spalt-Abdeckung ($cellMap >= 0.65).
        $rx1 = max(20, (int) round($bestCxCell - ($winW * 0.62)));
        $rx2 = min($gridW - 22, (int) round($bestCxCell + ($winW * 0.62)));
        $ry1 = max(16, (int) round($bestCyCell - ($winH * 0.62)));
        $ry2 = min($maxBedY, (int) round($bestCyCell + ($winH * 0.62)));

        $edgeContrastAt = static function (int $x, int $y) use (&$diffMap, &$lum): float {
            if ($diffMap[$y][$x] > 30.0) {
                return 1.0;
            }
            $edgeV = abs((float) $lum[$y][$x] - (float) $lum[max(0, $y - 1)][$x]);
            $edgeH = abs((float) $lum[$y][$x] - (float) $lum[$y][max(0, $x - 1)]);
            return ($diffMap[$y][$x] > 18.0 && max($edgeV, $edgeH) > 18.0) ? 1.0 : 0.0;
        };

        $bandHalfH = max(12, (int) round(($ry2 - $ry1) * 0.18));
        $bandY1 = max($ry1, (int) round($bestCyCell - $bandHalfH));
        $bandY2 = min($ry2, (int) round($bestCyCell + $bandHalfH));

        $colBand = array_fill($rx1, max(1, $rx2 - $rx1 + 1), 0.0);
        for ($y = $bandY1; $y <= $bandY2; $y++) {
            for ($x = $rx1; $x <= $rx2; $x++) {
                $colBand[$x] += $edgeContrastAt($x, $y);
            }
        }

        $peakCol = !empty($colBand) ? max($colBand) : 0.0;
        $bx1 = $rx1;
        $bx2 = $rx2;
        if ($peakCol > 2.0) {
            $colThr = $peakCol * 0.32;
            for ($x = $rx1; $x <= $rx2 - 2; $x++) {
                if ($colBand[$x] >= $colThr && $colBand[$x + 1] >= $colThr && $colBand[$x + 2] >= $colThr) {
                    $bx1 = $x;
                    break;
                }
            }
            for ($x = $rx2; $x >= $rx1 + 2; $x--) {
                if ($colBand[$x] >= $colThr && $colBand[$x - 1] >= $colThr && $colBand[$x - 2] >= $colThr) {
                    $bx2 = $x;
                    break;
                }
            }
        }

        $finalCxCell = ($peakCol > 2.0) ? (($bx1 + $bx2) * 0.5) : (float) $bestCxCell;

        // Vertikalband mindestens +/- 20 Zellen (20 mm Breite), damit garantiert mehrere Lamellen-Spalte erfasst werden
        $bandHalfW = max(20, (int) round(($bx2 - $bx1) * 0.14));
        $bandX1 = max($rx1, (int) round($finalCxCell - $bandHalfW));
        $bandX2 = min($rx2, (int) round($finalCxCell + $bandHalfW));

        $rowBand = array_fill($ry1, max(1, $ry2 - $ry1 + 1), 0.0);
        for ($y = $ry1; $y <= $ry2; $y++) {
            for ($x = $bandX1; $x <= $bandX2; $x++) {
                $rowBand[$y] += $edgeContrastAt($x, $y);
            }
        }

        $peakRow = !empty($rowBand) ? max($rowBand) : 0.0;
        $by1 = $ry1;
        $by2 = $ry2;
        if ($peakRow > 2.0) {
            $rowThr = $peakRow * 0.32;
            for ($y = $ry1; $y <= $ry2 - 2; $y++) {
                if ($rowBand[$y] >= $rowThr && $rowBand[$y + 1] >= $rowThr && $rowBand[$y + 2] >= $rowThr) {
                    $by1 = $y;
                    break;
                }
            }
            for ($y = $ry2; $y >= $ry1 + 2; $y--) {
                if ($rowBand[$y] >= $rowThr && $rowBand[$y - 1] >= $rowThr && $rowBand[$y - 2] >= $rowThr) {
                    $by2 = $y;
                    break;
                }
            }
        }

        $spanXCells = $bx2 - $bx1;
        if ($peakRow > 2.0) {
            // Bei großen kreisrunden Trophäen (wie "Der Seelen Kristall"), deren untere Kreiskante in den unteren
            // Kamerarand ($maxBedY) ragt, ergibt sich die exakte vertikale Kreismitte aus Oberkante ($by1) + Radius ($spanXCells / 2)
            if (abs($blankWidthMm - $blankHeightMm) < 5.0 && ($by1 + $spanXCells) >= ($maxBedY - 8) && $spanXCells > 160) {
                $finalCyCell = $by1 + ($spanXCells * 0.5);
            } else {
                $finalCyCell = ($by1 + $by2) * 0.5;
            }
        } else {
            $finalCyCell = (float) $bestCyCell;
        }

        $mu20 = 0.0;
        $mu02 = 0.0;
        $mu11 = 0.0;
        $wSum = 0.0;
        for ($y = $by1; $y <= $by2; $y++) {
            $dy = $y - $finalCyCell;
            for ($x = $bx1; $x <= $bx2; $x++) {
                $w = $cellMap[$y][$x];
                if ($w >= 0.5) {
                    $dx = $x - $finalCxCell;
                    $wSum += $w;
                    $mu20 += $w * $dx * $dx;
                    $mu02 += $w * $dy * $dy;
                    $mu11 += $w * $dx * $dy;
                }
            }
        }

        $angleDeg = 0.0;
        // Bei länglichen Rohlingen (Seitenverhältnis >= 1.2) liefert der Trägheitstensor den exakten Drehwinkel
        $aspect = max($blankWidthMm, $blankHeightMm) / max(1.0, min($blankWidthMm, $blankHeightMm));
        if ($aspect >= 1.18 && (abs($mu20 - $mu02) > 1.0 || abs($mu11) > 1.0)) {
            $rawTheta = 0.5 * rad2deg(atan2(2.0 * $mu11, $mu20 - $mu02));
            if ($blankHeightMm > $blankWidthMm) {
                $rawTheta -= ($rawTheta > 0 ? 90.0 : -90.0);
            }
            if (abs($rawTheta) <= 28.0) {
                $angleDeg = round($rawTheta, 1);
            }
        }

        $radOffset = deg2rad($angleDeg);
        $rotOffX = ($offsetXMm * cos($radOffset)) - ($offsetYMm * sin($radOffset));
        $rotOffY = ($offsetXMm * sin($radOffset)) + ($offsetYMm * cos($radOffset));

        $centerXMm = round(max(15.0, min(185.0, ($finalCxCell * 0.5) + $rotOffX)), 1);
        $centerYMm = round(max(15.0, min(185.0, ($finalCyCell * 0.5) + $rotOffY)), 1);
        $confidence = (int) round(max(55.0, min(99.0, 74.0 + ($bestRatio * 28.0))));

        return [
            'center_x_mm'       => $centerXMm,
            'center_y_mm'       => $centerYMm,
            'angle_deg'         => $angleDeg,
            'blank_width_mm'    => round($blankWidthMm, 2),
            'blank_height_mm'   => round($blankHeightMm, 2),
            'engrave_width_mm'  => $engraveWidthMm,
            'engrave_height_mm' => $engraveHeightMm,
            'confidence'        => $confidence,
            'detected'          => $bestRatio > 0.12,
            'camera_url'        => $camInfo['camera_url'],
            'camera_updated_at' => $camInfo['updated_at'],
            'source'            => $camInfo['source'],
            'laser_online'      => $camInfo['laser_online'],
            'laser_ip'          => $camInfo['laser_ip'],
            'relay_error'       => $camInfo['relay_error'] ?? null,
            'light_mode'        => $resolvedLightMode,
        ];
    }

    /**
     * Löst die effektiven Gravurmaße (wMm x hMm) unter Berücksichtigung manueller Cockpit-Skalierung auf.
     *
     * @return array{0: float, 1: float}
     */
    protected function resolveEffectiveJobDimensionsMm(
        $item,
        string $side,
        float $defaultWidthMm,
        float $defaultHeightMm,
        ?float $overrideWidthMm = null,
        ?float $overrideHeightMm = null
    ): array {
        $savedSide = [];
        if (is_object($item) && isset($item->configuration) && is_array($item->configuration)) {
            $savedSide = $item->configuration['laser_cockpit_coordinates'][$side] ?? [];
            if (!is_array($savedSide)) {
                $savedSide = [];
            }
        }

        $w = ($overrideWidthMm !== null && $overrideWidthMm > 0.5)
            ? $overrideWidthMm
            : (isset($savedSide['w_mm']) && is_numeric($savedSide['w_mm']) && (float) $savedSide['w_mm'] > 0.5
                ? (float) $savedSide['w_mm']
                : $defaultWidthMm);

        $h = ($overrideHeightMm !== null && $overrideHeightMm > 0.5)
            ? $overrideHeightMm
            : (isset($savedSide['h_mm']) && is_numeric($savedSide['h_mm']) && (float) $savedSide['h_mm'] > 0.5
                ? (float) $savedSide['h_mm']
                : $defaultHeightMm);

        return [
            round(max(3.0, min(195.0, $w)), 3),
            round(max(3.0, min(195.0, $h)), 3),
        ];
    }

    /**
     * Ermittelt die physischen Außenmaße (Breite x Höhe in mm) des eingelegten Rohlings.
     *
     * @return array{0: float, 1: float}
     */
    public function resolvePhysicalBlankDimensionsMm(?Product $product = null, array $itemConfig = []): array
    {
        $productConfig = $product
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
            : [];

        $calibration = app(FileDownloadService::class)->resolvePhysicalBlankCalibration(
            $product,
            $productConfig,
            $itemConfig,
            false
        );

        $widthMm = (float) ($calibration['widthMm'] ?? 45.0);
        $heightMm = (float) ($calibration['heightMm'] ?? 45.0);

        return [
            round(max(3.0, min(195.0, $widthMm)), 3),
            round(max(3.0, min(195.0, $heightMm)), 3),
        ];
    }

    /**
     * Katalog der im Schritt-1-Cockpit auswählbaren Equipment- & Prozess-Modi
     * (synchron zu Product::getLaserEquipmentCatalog() und resolveXtoolActiveMode()).
     *
     * @return array<string, array{key: string, label: string, title: string, badge: string, process_mode: string}>
     */
    public static function getSupportedEquipmentModes(): array
    {
        return [
            'flatbed_standard' => [
                'key'          => 'flatbed_standard',
                'label'        => 'Laserbett (Flachgravur)',
                'title'        => 'Flachbett Standard',
                'badge'        => 'Standard-Linse · Lamellenrost',
                'process_mode' => 'Prozess auf flacher Oberfläche',
            ],
            'conveyor_batch' => [
                'key'          => 'conveyor_batch',
                'label'        => 'Laufband (Conveyor)',
                'title'        => 'Laufband Serie',
                'badge'        => 'Anleitung 2 · Förderband',
                'process_mode' => 'Gleiches Design - Stapelverarbeitung',
            ],
            'rotary_ra3' => [
                'key'          => 'rotary_ra3',
                'label'        => 'RA3 Rotation',
                'title'        => '360° Rotation (xTool RA3)',
                'badge'        => 'Anleitung 1 · RA3 Aufsatz',
                'process_mode' => 'Walze - Rotationsaufsatz',
            ],
            'curved_surface' => [
                'key'          => 'curved_surface',
                'label'        => 'Gebogene Oberfläche',
                'title'        => 'Gebogene Oberfläche',
                'badge'        => '3D-Oberflächenprojektion',
                'process_mode' => 'Gebogene Oberfläche',
            ],
            'inner_3d' => [
                'key'          => 'inner_3d',
                'label'        => 'Glas-Innengravur',
                'title'        => '3D-Innengravur',
                'badge'        => 'Anleitung 3 · Gold-Linse + Riser',
                'process_mode' => 'Innengravur - 3D-Miniatur',
            ],
        ];
    }

    /**
     * Vektor-Parameter (Text & Standard-Symbole) eines Handbuch-Presets.
     *
     * @return array{focus_mm: float, power_percent: int, speed_mms: int, frequency_khz: int, lpc: int, dpi: int, passes: int, engrave_mode: string, note: string}
     */
    private static function hbVector(float $focusMm, int $power, int $speed, int $freqKhz, int $lpc, int $passes = 1, string $note = ''): array
    {
        return [
            'focus_mm'      => $focusMm,
            'power_percent' => $power,
            'speed_mms'     => $speed,
            'frequency_khz' => $freqKhz,
            'lpc'           => $lpc,
            'dpi'           => (int) round($lpc * 2.54),
            'passes'        => $passes,
            'engrave_mode'  => 'Bidirektional',
            'note'          => $note,
        ];
    }

    /**
     * Bitmap-Parameter (Kundenfotos / Uploads) eines Handbuch-Presets.
     *
     * @return array{focus_mm: float, power_percent: int, dot_duration_us: int, frequency_khz: int, dpi: int, passes: int, bitmap_mode: string, engrave_mode: string, note: string}
     */
    private static function hbBitmap(float $focusMm, int $power, int $dotUs, int $freqKhz, int $dpi, int $passes = 1, string $bitmapMode = 'Jarvis', string $note = ''): array
    {
        return [
            'focus_mm'        => $focusMm,
            'power_percent'   => $power,
            'dot_duration_us' => $dotUs,
            'frequency_khz'   => $freqKhz,
            'dpi'             => $dpi,
            'passes'          => $passes,
            'bitmap_mode'     => $bitmapMode,
            'engrave_mode'    => 'Bidirektional',
            'note'            => $note,
        ];
    }

    /**
     * Validierter Preset-Katalog des xTool F2 Ultra UV (5 W · 355 nm) aus beiden Werkstatthandbüchern
     * (PDF 1: Laserwerte- & Schwarzgravur-Handbuch Pos. 01-16, PDF 2: Laserwerte-Datenbank Stand 11/2026),
     * getrennt nach Vektor (Text & Standard-Symbole) und Bitmap (Kundenfotos) pro Produkt. Der jeweils erste
     * Preset-Eintrag ist der Schritt-1-Standard und entspricht 1:1 den Werten in ProductSeeder & Live-Sync-Migration.
     *
     * @return array<string, array{label: string, blank: string, polarity: string, equipment_key: string, material: string, setup_note: string, presets: array<string, array>}>
     */
    public static function getHandbookPresetCatalog(): array
    {
        $v = static fn (...$a) => self::hbVector(...$a);
        $b = static fn (...$a) => self::hbBitmap(...$a);

        return [
            'seelen-pfote-hundemarke' => [
                'label'         => 'Die Seelen-Pfote',
                'blank'         => '40 × 22 × 1,8 mm · 14K IP-Edelstahl PVD',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'Edelstahl 14K IP vergoldet (PVD)',
                'setup_note'    => 'Schwarzes Lamellenrost, vor und nach dem Lasern mit Isopropanol reinigen.',
                'presets'       => [
                    'lamellenrost_schwarz_100khz' => [
                        'label'  => 'Schwarz (Anlass-Gravur 100 kHz · Lamellenrost)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(21.5, 95, 25, 100, 1000, 1),
                        'bitmap' => $b(21.5, 95, 1100, 100, 1270, 1, 'Schwellenwert'),
                    ],
                    'pdf2_sweetspot' => [
                        'label'  => 'Cockpit-Sweetspot (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.4,
                        'vector' => $v(3.2, 42, 110, 65, 330, 1, 'opt. 2x 90° Kreuzschraffur'),
                        'bitmap' => $b(3.2, 45, 210, 65, 800, 1, 'Jarvis', 'Pixel-Logo / Silhouette: Schwellenwert'),
                    ],
                    'pdf1_tiefschwarz_100khz' => [
                        'label'  => 'Tiefschwarz Anlass-Oxidation 100 kHz (PDF 1 Pos. 01/02)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(3.8, 95, 25, 100, 1000, 1),
                        'bitmap' => $b(3.6, 95, 1100, 100, 1270, 1, 'Jarvis', 'Offset +1,8 mm'),
                    ],
                    'silber_kontrast' => [
                        'label'  => 'Silber-Kontrastgravur',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(1.8, 28, 550, 40, 300, 1),
                        'bitmap' => null,
                    ],
                    'gold_eloxal_alu' => [
                        'label'  => 'Gold-Eloxal Alu Hundemarke',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.4,
                        'vector' => $v(1.8, 55, 450, 50, 300, 1),
                        'bitmap' => $b(1.8, 55, 160, 50, 600, 1),
                    ],
                ],
            ],
            'goldenes-seelen-herz' => [
                'label'         => 'Das Goldene Seelen-Herz',
                'blank'         => '25 × 22 × 4,0 mm · STA1274 14K IP-Edelstahl massiv',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'Edelstahl 14K IP vergoldet (massiv)',
                'setup_note'    => 'Kette abnehmen, Herz flach auf das schwarze Lamellenrost legen.',
                'presets'       => [
                    'lamellenrost_schwarz_100khz' => [
                        'label'  => 'Schwarz (Anlass-Gravur 100 kHz · Lamellenrost)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(21.5, 95, 25, 100, 1000, 1),
                        'bitmap' => $b(21.5, 95, 1100, 100, 1270, 1, 'Schwellenwert'),
                    ],
                    'pdf2_sweetspot' => [
                        'label'  => 'Schritt-1 Katalog (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.4,
                        'vector' => $v(5.4, 46, 100, 65, 350, 1, '1-2 Pässe'),
                        'bitmap' => $b(5.4, 48, 220, 65, 800, 1, 'Jarvis', 'Alternativ Graustufen'),
                    ],
                    'pdf1_massiv_tiefschwarz_100khz' => [
                        'label'  => 'Massiv-Tiefschwarz 100 kHz (PDF 1 Pos. 03)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(6.0, 98, 20, 100, 1000, 1, '1-2 Pässe'),
                        'bitmap' => $b(6.0, 95, 1100, 100, 1270, 1),
                    ],
                ],
            ],
            'seelen-talisman' => [
                'label'         => 'Der Seelen-Talisman',
                'blank'         => '50 × 28 × 2,2 mm · Gold-Finish Edelstahl Dog-Tag',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'Edelstahl Gold-Finish',
                'setup_note'    => 'Schlüsselring frei über die Lamellenkante hängen lassen.',
                'presets'       => [
                    'lamellenrost_schwarz_100khz' => [
                        'label'  => 'Schwarz (Anlass-Gravur 100 kHz · Lamellenrost)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(21.5, 95, 25, 100, 1000, 1),
                        'bitmap' => $b(21.5, 95, 1100, 100, 1270, 1, 'Schwellenwert'),
                    ],
                    'pdf2_sweetspot' => [
                        'label'  => 'Schritt-1 Katalog (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.4,
                        'vector' => $v(3.6, 44, 105, 65, 330, 1),
                        'bitmap' => $b(3.5, 46, 215, 65, 800, 1),
                    ],
                    'pdf1_tiefschwarz_100khz' => [
                        'label'  => 'Tiefschwarz 100 kHz (PDF 1 Pos. 04)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 2.0,
                        'vector' => $v(4.2, 95, 25, 100, 1000, 1),
                        'bitmap' => $b(4.2, 95, 1100, 100, 1270, 1),
                    ],
                ],
            ],
            'seelen-anhaenger' => [
                'label'         => 'Der Seelenanhänger',
                'blank'         => '45 × 40 × 1,8..2,3 mm · Schwarzes Herz PVD / Eloxal',
                'polarity'      => 'white_on_dark',
                'equipment_key' => 'flatbed_standard',
                'material'      => '0,2 mm Aluminiumlegierung',
                'setup_note'    => 'Jig-Fokus 19,9 mm (Flachbett ohne Jig 1,8 mm). Foto invertiert mit Dot-Gain-Gamma.',
                'presets'       => [
                    'pdf2_silber_kontrast' => [
                        'label'  => 'Heller Silber-Kontrast (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(19.9, 70, 450, 40, 300, 1),
                        'bitmap' => $b(19.9, 85, 280, 40, 600, 1),
                    ],
                    'pdf1_feintext' => [
                        'label'  => 'Fein-Text & Alt-Foto (PDF 1)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(19.9, 55, 700, 40, 450, 1),
                        'bitmap' => $b(19.9, 90, 300, 40, 500, 1),
                    ],
                    'flachbett_ohne_jig' => [
                        'label'  => 'Flachbett ohne Jig (1,8 mm)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(1.8, 70, 450, 40, 300, 1),
                        'bitmap' => $b(1.8, 85, 280, 40, 600, 1),
                    ],
                ],
            ],
            'id-armband-silber-herz' => [
                'label'         => 'Echtsilber Seelen-Armband (925er Sterlingsilber)',
                'blank'         => '24 × 5,5 × 1,2 mm · FBM 925er Sterlingsilber',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => '925er Sterlingsilber',
                'setup_note'    => 'Auf der schwarzen Schaumstoffmatte fixieren.',
                'presets'       => [
                    'pdf2_sweetspot' => [
                        'label'  => 'Schritt-1 Katalog (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.0,
                        'vector' => $v(2.2, 75, 180, 50, 400, 2),
                        'bitmap' => $b(2.2, 75, 240, 50, 1000, 2),
                    ],
                    'pdf1_kreuzschraffur' => [
                        'label'  => 'Kreuzschraffur (PDF 1 Pos. 06)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.8,
                        'vector' => $v(2.0, 85, 40, 80, 1000, 2, 'Kreuzschraffur 90°'),
                        'bitmap' => null,
                    ],
                ],
            ],
            'id-armband-gold-herz' => [
                'label'         => 'Gold-Vermeil Seelen-Armband (925er Gold-Vermeil)',
                'blank'         => '24 × 5,5 × 1,2 mm · FBM 925er Gold-Vermeil',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => '925er Gold-Vermeil',
                'setup_note'    => 'Auf der schwarzen Schaumstoffmatte fixieren (Schon-Oxidation).',
                'presets'       => [
                    'pdf2_sweetspot' => [
                        'label'  => 'Schritt-1 Katalog (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.3,
                        'vector' => $v(2.5, 34, 130, 70, 400, 1),
                        'bitmap' => $b(2.5, 34, 180, 70, 1000, 1),
                    ],
                    'pdf1_pos07' => [
                        'label'  => 'Schon-Oxidation (PDF 1 Pos. 07)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 1.2,
                        'vector' => $v(2.4, 55, 60, 90, 1000, 1),
                        'bitmap' => null,
                    ],
                ],
            ],
            'echtgold-seelen-herz' => [
                'label'         => '585er Echtgold Seelen-Herz',
                'blank'         => '13 × 14 × 0,8 mm · 585er Gold',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => '585er Gold',
                'setup_note'    => 'Für Dunkelton Offset +0,8 mm (1,6 mm).',
                'presets'       => [
                    'pdf1_pos08' => [
                        'label'  => 'Echtgold (PDF 1 Pos. 08)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.8, 70, 80, 60, 800, 2),
                        'bitmap' => null,
                    ],
                    'pdf1_pos08_dunkelton' => [
                        'label'  => 'Echtgold Dunkelton (+0,8 mm)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.8,
                        'vector' => $v(1.6, 70, 80, 60, 800, 2),
                        'bitmap' => null,
                    ],
                ],
            ],
            'seelen-kristall' => [
                'label'         => 'Der Seelen Kristall',
                'blank'         => 'Ø 161,5 × 40 mm · K9-Kristall MSF-KRISTALL-3D-001',
                'polarity'      => 'white_on_dark',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'K9 Kristalltrophäe',
                'setup_note'    => 'Schwarzes Lamellenrost nutzen, Standfuß frei über Vorderkante schweben lassen. Shop-SVG nicht invertieren.',
                'presets'       => [
                    'pdf2_seidenmatt_frost' => [
                        'label'  => 'Außen Seidenmatt-Frost (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(33.1, 60, 80, 40, 300, 1),
                        'bitmap' => $b(33.1, 100, 450, 40, 500, 1),
                    ],
                    'pdf1_frost_600dpi' => [
                        'label'  => 'Frost 600 DPI (PDF 1)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(33.1, 60, 80, 40, 300, 1),
                        'bitmap' => $b(33.1, 100, 500, 40, 600, 1),
                    ],
                    'master_referenz_32_8' => [
                        'label'  => 'Master-Referenz 32,8 mm (Brennpunkt-Doku)',
                        'source' => 'Brennpunkt_Masse.md',
                        'focus_offset_mm' => -0.3,
                        'vector' => $v(32.8, 60, 80, 40, 300, 1),
                        'bitmap' => $b(32.8, 85, 500, 40, 600, 1),
                    ],
                    'inner_3d_subsurface' => [
                        'label'         => 'Innengravur 3D/2D Subsurface (PDF 1 Pos. 09)',
                        'source'        => 'PDF 1',
                        'equipment_key' => 'inner_3d',
                        'focus_offset_mm' => -17.5,
                        'vector' => null,
                        'bitmap' => $b(15.6, 80, 220, 40, 600, 1, 'Jarvis', 'Offset -15 bis -20 mm ins Glas, niemals Graustufen'),
                    ],
                ],
            ],
            'weizenglas-personalisiert' => [
                'label'         => 'Feierabend-Funke Kristall-Weizenbierglas',
                'blank'         => '0,5 l · Ø 74 × 218 mm · MSF-GLAS-WEIZEN-001',
                'polarity'      => 'white_on_dark',
                'equipment_key' => 'rotary_ra3',
                'material'      => 'Klares Glas (Kristall-Weizenbierglas)',
                'setup_note'    => 'RA3 MagSwap, Wasserwaage Pflicht, 30 mm zum Trinkrand, 40 mm zum Fuß.',
                'presets'       => [
                    'pdf2_ra3_frost' => [
                        'label'  => 'RA3 Frost (PDF 2)',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(74.0, 55, 380, 40, 300, 1, 'Ø 74,0 mm Scheitel (0,0 mm)'),
                        'bitmap' => $b(74.0, 55, 260, 40, 400, 1),
                    ],
                    'pdf1_kraeftig_frost' => [
                        'label'  => 'Kräftig-Frost (PDF 1 Pos. 11)',
                        'source' => 'PDF 1',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(74.0, 80, 125, 40, 300, 1),
                        'bitmap' => $b(74.0, 92, 650, 40, 500, 1),
                    ],
                ],
            ],
            'geschenkgutschein' => [
                'label'         => 'Geschenkgutschein-Karte (Alu Schwarz & Gold)',
                'blank'         => '86 × 54 × 0,2..0,8 mm · Aluminium',
                'polarity'      => 'white_on_dark',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'Eloxiertes Aluminium (Visitenkarte)',
                'setup_note'    => 'Schwarze Karte 0,8 mm (Jig 19,9 mm). Gold-Karte +0,4 mm.',
                'presets'       => [
                    'karte_schwarz' => [
                        'label'  => 'Gutscheinkarte Schwarz',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.8, 45, 850, 55, 450, 1, '800-900 mm/s · 50-60 kHz'),
                        'bitmap' => $b(0.8, 45, 220, 55, 500, 1, 'Schwellenwert'),
                    ],
                    'karte_gold' => [
                        'label'    => 'Gutscheinkarte Gold',
                        'source'   => 'PDF 2',
                        'polarity' => 'dark_on_light',
                        'focus_offset_mm' => 0.4,
                        'vector' => $v(1.2, 52, 450, 50, 300, 1),
                        'bitmap' => $b(1.2, 52, 220, 50, 800, 1),
                    ],
                ],
            ],
            '_materials' => [
                'label'         => 'Erweiterte Werkstoff-Presets',
                'blank'         => 'Werkstoff-Bibliothek',
                'polarity'      => 'dark_on_light',
                'equipment_key' => 'flatbed_standard',
                'material'      => 'Edelstahl',
                'setup_note'    => '',
                'presets'       => [
                    'edelstahl_v2a_anlass_schwarz' => [
                        'label'  => 'Edelstahl V2A Anlass-Schwarz',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 1.5,
                        'vector' => $v(1.5, 55, 95, 55, 400, 1, 'Fokus = Materialstärke + 1,5 mm'),
                        'bitmap' => $b(1.5, 55, 260, 55, 1000, 1),
                    ],
                    'edelstahl_v2a_silber_frost' => [
                        'label'  => 'Edelstahl V2A Silber-Frost',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.0, 85, 600, 40, 300, 1, 'Fokus = Materialstärke'),
                        'bitmap' => $b(0.0, 85, 200, 40, 500, 1),
                    ],
                    'naturschiefer' => [
                        'label'    => 'Naturschiefer',
                        'source'   => 'PDF 2',
                        'polarity' => 'white_on_dark',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.0, 80, 425, 40, 300, 1, '75-85 % · 350-500 mm/s'),
                        'bitmap' => $b(0.0, 90, 350, 40, 400, 1),
                    ],
                    'alu_flaschenoeffner_conveyor' => [
                        'label'         => 'Alu-Flaschenöffner (Conveyor)',
                        'source'        => 'PDF 2',
                        'polarity'      => 'white_on_dark',
                        'equipment_key' => 'conveyor_batch',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.0, 55, 750, 45, 450, 1),
                        'bitmap' => null,
                    ],
                    'massivholz_bambus' => [
                        'label'  => 'Massivholz & Bambus',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.0, 50, 450, 45, 300, 1, '45-55 % (Bambus 35 %) · 250-650 mm/s · 40-50 kHz'),
                        'bitmap' => $b(0.0, 50, 220, 45, 400, 1, 'Jarvis', '160-280 µs · 300-450 DPI'),
                    ],
                    'messing_poliert' => [
                        'label'  => 'Messing poliert',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.9,
                        'vector' => $v(0.9, 82, 170, 57, 400, 1, '+0,8..+1,0 mm · 80-85 % · 120-220 mm/s · 45-70 kHz · 1-2x'),
                        'bitmap' => $b(0.9, 82, 280, 57, 800, 1),
                    ],
                    'acryl_echtleder' => [
                        'label'  => 'Acrylglas & Echtleder',
                        'source' => 'PDF 2',
                        'focus_offset_mm' => 0.0,
                        'vector' => $v(0.0, 38, 650, 60, 300, 1),
                        'bitmap' => $b(0.0, 38, 140, 60, 300, 1),
                    ],
                ],
            ],
        ];
    }

    /**
     * Handbuch-Profil eines Produkts (per Slug oder template_package) inkl. aller wählbaren Schritt-1-Presets.
     */
    public static function getHandbookProductProfile(?string $slug, ?string $templatePackage = null): ?array
    {
        $catalog = self::getHandbookPresetCatalog();
        foreach ([trim((string) $templatePackage), trim((string) $slug)] as $key) {
            if ($key === '' || $key === '_materials') {
                continue;
            }
            if (isset($catalog[$key])) {
                return $catalog[$key] + ['slug' => $key];
            }
            if ($key === 'feierabend-funke' && isset($catalog['weizenglas-personalisiert'])) {
                return $catalog['weizenglas-personalisiert'] + ['slug' => 'weizenglas-personalisiert'];
            }
        }

        return null;
    }

    /**
     * Baut aus einem Handbuch-Preset die kompatiblen configurator_settings-Schlüssel (Top-Level-Bitmap-Werte,
     * getrennte xtool_vector_* / xtool_bitmap_* Werte, Polarität und Vektor-Notiz) für Seeder, Migration und Cockpit.
     *
     * @return array<string, mixed>
     */
    public static function buildProductLaserSettingsFromHandbook(string $slug, ?string $presetKey = null): array
    {
        $profile = self::getHandbookProductProfile($slug);
        if ($profile === null || empty($profile['presets'])) {
            return [];
        }

        $presetKey = ($presetKey !== null && isset($profile['presets'][$presetKey]))
            ? $presetKey
            : (string) array_key_first($profile['presets']);
        $preset = $profile['presets'][$presetKey];
        $vector = $preset['vector'] ?? null;
        $bitmap = $preset['bitmap'] ?? null;
        $primary = $bitmap ?? $vector;
        $equipmentKey = (string) ($preset['equipment_key'] ?? $profile['equipment_key']);
        $modes = self::getSupportedEquipmentModes();

        $settings = [
            'xtool_handbook_preset'          => $presetKey,
            'xtool_photo_substrate_polarity' => (string) ($preset['polarity'] ?? $profile['polarity']),
            'xtool_equipment_key'            => $equipmentKey,
            'xtool_process_mode'             => $modes[$equipmentKey]['process_mode'] ?? 'Prozess auf flacher Oberfläche',
            'xtool_material'                 => (string) $profile['material'],
            'xtool_focus_mm'                 => (float) $primary['focus_mm'],
            'xtool_element_type'             => $bitmap !== null ? 'Bild' : 'Vektor',
            'xtool_engrave_tab'              => 'Gravieren',
            'xtool_power_percent'            => (int) $primary['power_percent'],
            'xtool_frequency_khz'            => (int) $primary['frequency_khz'],
            'xtool_passes'                   => (int) $primary['passes'],
            'xtool_engrave_mode'             => 'Bidirektional',
        ];

        if ($bitmap !== null) {
            $settings += [
                'xtool_dot_duration_us'        => (int) $bitmap['dot_duration_us'],
                'xtool_dpi'                    => (int) $bitmap['dpi'],
                'xtool_bitmap_mode'            => (string) $bitmap['bitmap_mode'],
                'xtool_bitmap_focus_mm'        => (float) $bitmap['focus_mm'],
                'xtool_bitmap_power_percent'   => (int) $bitmap['power_percent'],
                'xtool_bitmap_dot_duration_us' => (int) $bitmap['dot_duration_us'],
                'xtool_bitmap_frequency_khz'   => (int) $bitmap['frequency_khz'],
                'xtool_bitmap_dpi'             => (int) $bitmap['dpi'],
                'xtool_bitmap_passes'          => (int) $bitmap['passes'],
            ];
        } else {
            $settings += [
                'xtool_dpi'         => (int) $vector['dpi'],
                'xtool_bitmap_mode' => 'Schwellenwert',
            ];
        }

        if ($vector !== null) {
            $settings += [
                'xtool_speed_mms'            => (int) $vector['speed_mms'],
                'xtool_lpc'                  => (int) $vector['lpc'],
                'xtool_vector_note'          => self::formatHandbookVectorNote($vector),
                'xtool_vector_focus_mm'      => (float) $vector['focus_mm'],
                'xtool_vector_power_percent' => (int) $vector['power_percent'],
                'xtool_vector_speed_mms'     => (int) $vector['speed_mms'],
                'xtool_vector_frequency_khz' => (int) $vector['frequency_khz'],
                'xtool_vector_lpc'           => (int) $vector['lpc'],
                'xtool_vector_dpi'           => (int) $vector['dpi'],
                'xtool_vector_passes'        => (int) $vector['passes'],
            ];
        }

        return $settings;
    }

    public static function formatHandbookVectorNote(array $vector): string
    {
        $parts = [
            'Gravieren',
            ((int) $vector['power_percent']) . ' %',
            ((int) $vector['speed_mms']) . ' mm/s',
            ((int) $vector['lpc']) . ' Linien/cm',
            ((int) $vector['frequency_khz']) . ' kHz',
        ];
        if (((int) ($vector['passes'] ?? 1)) > 1) {
            $parts[] = ((int) $vector['passes']) . 'x';
        }

        return implode(' · ', $parts);
    }

    /**
     * Schritt-1-Presetliste (flach) für ein Produkt inkl. erweiterter Werkstoff-Presets.
     *
     * @return array<int, array{key: string, label: string, source: string, group: string, polarity: string, equipment_key: string, vector: ?array, bitmap: ?array}>
     */
    public static function getStepOnePresetOptions(?Product $product): array
    {
        $catalog = self::getHandbookPresetCatalog();
        $settings = $product
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : ($product->configurator_settings ?? []))
            : [];
        $profile = $product ? self::getHandbookProductProfile($product->slug, (string) ($settings['template_package'] ?? '')) : null;

        $groups = [];
        if ($profile !== null) {
            $groups[] = [$profile, 'Produkt'];
        }
        $groups[] = [$catalog['_materials'], 'Werkstoff'];

        $out = [];
        foreach ($groups as [$grp, $groupLabel]) {
            foreach ($grp['presets'] as $key => $preset) {
                $out[] = [
                    'key'           => $key,
                    'label'         => (string) $preset['label'],
                    'source'        => (string) ($preset['source'] ?? ''),
                    'group'         => $groupLabel,
                    'polarity'      => (string) ($preset['polarity'] ?? $grp['polarity']),
                    'equipment_key' => (string) ($preset['equipment_key'] ?? $grp['equipment_key']),
                    'vector'        => $preset['vector'] ?? null,
                    'bitmap'        => $preset['bitmap'] ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * Zerlegt eine Vektor-Notiz ("Gravieren · 70 % · 450 mm/s · 300 Linien/cm · 40 kHz") in Einzelwerte.
     *
     * @return array{power_percent: ?int, speed_mms: ?int, lpc: ?int, frequency_khz: ?int, passes: ?int}
     */
    public static function parseVectorNoteValues(string $note): array
    {
        $out = ['power_percent' => null, 'speed_mms' => null, 'lpc' => null, 'frequency_khz' => null, 'passes' => null];
        if (trim($note) === '') {
            return $out;
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $note, $m)) {
            $out['power_percent'] = max(1, min(100, (int) round((float) str_replace(',', '.', $m[1]))));
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mm\s*\/\s*s/iu', $note, $m)) {
            $out['speed_mms'] = max(1, min(4000, (int) round((float) str_replace(',', '.', $m[1]))));
        }
        if (preg_match('/(\d+)\s*(?:L|Linien)\s*\/\s*cm/iu', $note, $m)) {
            $out['lpc'] = max(10, min(2000, (int) $m[1]));
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kHz/iu', $note, $m)) {
            $out['frequency_khz'] = max(1, min(200, (int) round((float) str_replace(',', '.', $m[1]))));
        }
        if (preg_match('/(?:^|\s|·)(\d{1,2})\s*x(?:\s|$|·)/iu', $note, $m)) {
            $out['passes'] = max(1, min(20, (int) $m[1]));
        }

        return $out;
    }

    /**
     * Getrennte Laserparameter für den Foto-Bitmap-Layer und den Text-/Vektor-Layer einer Bestellposition.
     * Bitmap = effektive Schritt-1-Werte (Produkt + Overrides). Vektor = Produkt-Vektorwerte (xtool_vector_*)
     * bzw. bei Overrides die effektive Vektor-Notiz, jeweils mit Fallback auf die Bitmap-Werte.
     *
     * @return array{bitmap: array<string, mixed>, vector: array<string, mixed>, has_product_vector_values: bool, polarity: string}
     */
    public function resolveLayerLaserParameters($item, string $side = 'front', ?array $effective = null): array
    {
        $effective = $effective ?? $this->resolveEffectiveLaserParameters($item, $side);
        $product = (is_object($item) && isset($item->product)) ? $item->product : null;
        $settings = $product
            ? (is_string($product->configurator_settings) ? (json_decode($product->configurator_settings, true) ?: []) : (is_array($product->configurator_settings) ? $product->configurator_settings : []))
            : [];

        $hasProductVector = isset($settings['xtool_vector_power_percent']) && is_numeric($settings['xtool_vector_power_percent']);
        $hasOverrides = (bool) ($effective['has_overrides'] ?? false);
        $noteValues = self::parseVectorNoteValues((string) ($effective['vector_note'] ?? ''));

        $pick = static function (?int $fromNote, $fromProduct, int $fallback, bool $preferNote): int {
            if ($preferNote && $fromNote !== null) {
                return $fromNote;
            }
            if ($fromProduct !== null && is_numeric($fromProduct)) {
                return (int) $fromProduct;
            }
            return $fromNote ?? $fallback;
        };

        $vectorLpc = $pick($noteValues['lpc'], $settings['xtool_vector_lpc'] ?? ($settings['xtool_lpc'] ?? null), 300, $hasOverrides);
        $vector = [
            'focus_mm'      => $hasOverrides
                ? (float) $effective['focus_mm']
                : (float) (is_numeric($settings['xtool_vector_focus_mm'] ?? null) ? $settings['xtool_vector_focus_mm'] : $effective['focus_mm']),
            'power_percent' => $pick($noteValues['power_percent'], $settings['xtool_vector_power_percent'] ?? null, (int) $effective['power_percent'], $hasOverrides),
            'speed_mms'     => $pick($noteValues['speed_mms'], $settings['xtool_vector_speed_mms'] ?? null, (int) $effective['speed_mms'], $hasOverrides),
            'frequency_khz' => $pick($noteValues['frequency_khz'], $settings['xtool_vector_frequency_khz'] ?? null, (int) $effective['frequency_khz'], $hasOverrides),
            'lpc'           => $vectorLpc,
            'dpi'           => max(50, min(2540, (int) round($vectorLpc * 2.54))),
            'passes'        => $pick($noteValues['passes'], $settings['xtool_vector_passes'] ?? null, (int) $effective['passes'], $hasOverrides),
            'engrave_mode'  => (string) $effective['engrave_mode'],
            'bitmap_mode'   => 'Schwellenwert',
        ];

        $bitmap = [
            'focus_mm'        => (float) $effective['focus_mm'],
            'power_percent'   => (int) $effective['power_percent'],
            'dot_duration_us' => (int) $effective['dot_duration_us'],
            'frequency_khz'   => (int) $effective['frequency_khz'],
            'dpi'             => (int) $effective['dpi'],
            'passes'          => (int) $effective['passes'],
            'bitmap_mode'     => (string) $effective['bitmap_mode'],
            'engrave_mode'    => (string) $effective['engrave_mode'],
            'speed_mms'       => (int) $effective['speed_mms'],
        ];

        $isDark = app(FileDownloadService::class)->isFrostedLightEngravingSurface($product, $settings);

        return [
            'bitmap'                    => $bitmap,
            'vector'                    => $vector,
            'has_product_vector_values' => $hasProductVector,
            'polarity'                  => LaserPhotoPreprocessor::normalizePolarity((string) ($settings['xtool_photo_substrate_polarity'] ?? ''), $isDark),
        ];
    }

    public static function normalizeEquipmentKey(string $rawKey, string $rawProcessMode = ''): string
    {
        $k = mb_strtolower(trim($rawKey));
        $direct = match ($k) {
            'flatbed_standard', 'flatbed', 'laser_plane'                   => 'flatbed_standard',
            'conveyor_batch', 'conveyor', 'laser_conveyor_feeder'          => 'conveyor_batch',
            'rotary_ra3', 'rotary', 'laser_cylinder'                       => 'rotary_ra3',
            'curved_surface', 'curved', 'curved_process'                   => 'curved_surface',
            'inner_3d', 'inner_engraving', 'inner_laser', 'innengravur'    => 'inner_3d',
            default                                                        => null,
        };
        if ($direct !== null) {
            return $direct;
        }

        $haystack = mb_strtolower(trim($rawKey . ' ' . $rawProcessMode));
        if (str_contains($haystack, 'ra3') || str_contains($haystack, 'rotation') || str_contains($haystack, 'walze') || str_contains($haystack, 'spannfutter')) {
            return 'rotary_ra3';
        }
        if (str_contains($haystack, 'laufband') || str_contains($haystack, 'conveyor') || str_contains($haystack, 'stapel') || str_contains($haystack, 'förderband')) {
            return 'conveyor_batch';
        }
        if (str_contains($haystack, 'innengravur') || str_contains($haystack, '3d-miniatur') || str_contains($haystack, 'inner')) {
            return 'inner_3d';
        }
        if (str_contains($haystack, 'gebogen') || str_contains($haystack, 'curved')) {
            return 'curved_surface';
        }

        return 'flatbed_standard';
    }

    public static function normalizeBitmapModeLabel(string $rawBitmapMode, string $fallback = 'Graustufen'): string
    {
        $b = mb_strtolower(trim($rawBitmapMode));
        if ($b === '') {
            return $fallback;
        }
        if (str_contains($b, 'jarvis') || str_contains($b, 'raster')) {
            return 'Jarvis';
        }
        if (str_contains($b, 'stucki')) {
            return 'Stucki';
        }
        if (str_contains($b, 'schwellenwert') || str_contains($b, 'threshold')) {
            return 'Schwellenwert';
        }
        if (str_contains($b, 'floyd')) {
            return 'Floyd';
        }
        if (str_contains($b, 'atkinson')) {
            return 'Atkinson';
        }
        if (str_contains($b, 'konturgravur') || str_contains($b, 'emboss') || str_contains($b, 'relief')) {
            return 'Konturgravur (3D)';
        }
        if (str_contains($b, 'flächenfüllung') || str_contains($b, 'flaechenfuellung') || str_contains($b, 'fill')) {
            return 'Flächenfüllung (Vektor)';
        }
        if (str_contains($b, 'graustufen') || str_contains($b, 'greyscale') || str_contains($b, 'grayscale')) {
            return 'Graustufen';
        }

        return $fallback;
    }

    /**
     * Übersetzt einen Dither-Modus aus dem Laser-Foto-Studio in das deutsche xTool Bitmap-Modus-Label.
     */
    public static function ditherModeToBitmapModeLabel(string $ditherMode): string
    {
        $norm = mb_strtolower(trim($ditherMode));

        return match ($norm) {
            'jarvis'                               => 'Jarvis',
            'stucki'                               => 'Stucki',
            'grayscale', 'greyscale', 'graustufen' => 'Graustufen',
            'threshold', 'schwellenwert'           => 'Schwellenwert',
            default                                => 'Jarvis',
        };
    }

    /**
     * Übersetzt ein deutsches xTool Bitmap-Modus-Label zurück in den Dither-Modus des Laser-Foto-Studios.
     */
    public static function bitmapModeLabelToDitherMode(string $bitmapMode, string $fallback = 'jarvis'): string
    {
        $cleanFallback = mb_strtolower(trim($fallback));
        if ($cleanFallback === 'greyscale') {
            $cleanFallback = 'grayscale';
        }
        if (!in_array($cleanFallback, ['jarvis', 'stucki', 'grayscale', 'threshold'], true)) {
            $cleanFallback = 'jarvis';
        }

        $b = mb_strtolower(trim($bitmapMode));
        if ($b === '') {
            return $cleanFallback;
        }
        if (str_contains($b, 'jarvis') || str_contains($b, 'raster')) {
            return 'jarvis';
        }
        if (str_contains($b, 'stucki')) {
            return 'stucki';
        }
        if (str_contains($b, 'graustufen') || str_contains($b, 'greyscale') || str_contains($b, 'grayscale')) {
            return 'grayscale';
        }
        if (str_contains($b, 'schwellenwert') || str_contains($b, 'threshold')) {
            return 'threshold';
        }

        return $cleanFallback;
    }

    /**
     * Validiert und reduziert Foto-Studio-Parameter auf die exakt 9 am Produkt-Laserprofil gespeicherten Schlüssel (ohne enabled).
     *
     * @param  array<string, mixed>  $studioParams
     * @return array{grayscale: bool, invert: bool, contrast: int, brightness: int, gamma: float, highlight_cap: int, sharpness: int, dither_mode: string, threshold: int}
     */
    public static function sanitizeProfilePhotoStudioParams(array $studioParams): array
    {
        $sanitized = LaserPhotoPreprocessor::sanitizeStudioParams($studioParams);

        return [
            'grayscale'     => (bool) $sanitized['grayscale'],
            'invert'        => (bool) $sanitized['invert'],
            'contrast'      => (int) $sanitized['contrast'],
            'brightness'    => (int) $sanitized['brightness'],
            'gamma'         => (float) $sanitized['gamma'],
            'highlight_cap' => (int) $sanitized['highlight_cap'],
            'sharpness'     => (int) $sanitized['sharpness'],
            'dither_mode'   => (string) $sanitized['dither_mode'],
            'threshold'     => (int) $sanitized['threshold'],
        ];
    }

    public static function normalizeEngraveModeLabel(string $rawEngraveMode, string $fallback = 'Bidirektional'): string
    {
        $e = mb_strtolower(trim($rawEngraveMode));
        if ($e === '') {
            return $fallback;
        }
        if ($e === 'eindirektional') {
            return 'Eindirektional';
        }
        if (str_contains($e, 'uni') || str_contains($e, 'eindirekt') || str_contains($e, 'single')) {
            return 'Unidirektional';
        }
        if ($e === 'zweidirektional') {
            return 'Zweidirektional';
        }

        return 'Bidirektional';
    }

    public static function normalizeElementTypeLabel(string $rawElementType, string $fallback = 'Bild'): string
    {
        $t = mb_strtolower(trim($rawElementType));
        if ($t === '') {
            return $fallback;
        }
        if (str_contains($t, 'vektor') || str_contains($t, 'vector') || str_contains($t, 'pfad')) {
            return 'Vektor';
        }
        if (str_contains($t, '3d') || str_contains($t, 'modell') || str_contains($t, 'miniatur')) {
            return '3D-Modell';
        }

        return 'Bild';
    }

    public static function normalizeEngraveTabLabel(string $rawEngraveTab, string $fallback = 'Gravieren'): string
    {
        $t = mb_strtolower(trim($rawEngraveTab));
        if ($t === '') {
            return $fallback;
        }
        if (str_contains($t, 'farb') || str_contains($t, 'color')) {
            return 'Farbgravur';
        }
        if (str_contains($t, 'kontur') || str_contains($t, 'relief') || str_contains($t, 'emboss')) {
            return 'Konturgravur';
        }

        return 'Gravieren';
    }

    public static function normalizePresetModeLabel(string $rawPresetMode, string $fallback = 'Benutzerdefiniert'): string
    {
        $p = mb_strtolower(trim($rawPresetMode));
        if ($p === '') {
            return $fallback;
        }
        if (str_contains($p, 'referenz') || str_contains($p, 'official') || str_contains($p, 'standard')) {
            return 'Referenz';
        }

        return 'Benutzerdefiniert';
    }

    public static function resolveXtoolProcessingType(string $elementType, string $engraveTab = 'Gravieren'): string
    {
        $el = mb_strtolower(trim($elementType));
        $tab = mb_strtolower(trim($engraveTab));

        if (str_contains($tab, 'farb')) {
            return 'COLOR_ENGRAVE';
        }
        if (str_contains($tab, 'kontur')) {
            return 'RELIEF';
        }
        if (str_contains($el, 'vektor')) {
            return 'FILL_VECTOR_ENGRAVING';
        }

        return 'BITMAP_ENGRAVING';
    }

    public static function parseSpeedMmsFromVectorNote(string $vectorNote, int $fallback = 200): int
    {
        if ($vectorNote !== '' && preg_match('/(\d+(?:[.,]\d+)?)\s*mm\s*\/\s*s/iu', $vectorNote, $m)) {
            $parsed = (int) round((float) str_replace(',', '.', $m[1]));
            if ($parsed >= 1) {
                return max(1, min(4000, $parsed));
            }
        }

        return max(1, min(4000, $fallback));
    }

    /**
     * Löst die effektiven Laserparameter (alle 15 xTool-Felder aus Schritt 4.2 + speed_mms) für eine Bestellposition und Seite auf:
     * Produkt-Standardwerte aus getLaserInstructionSummary() überlagert mit configuration['laser_cockpit_parameters'][$side]
     * bzw. configuration['laser_parameter_overrides'][$side] (Fallback auf 'front').
     */
    public function resolveEffectiveLaserParameters($item, string $side = 'front'): array
    {
        if (!is_object($item) && $item !== null && $item !== '') {
            $item = OrderOrderItem::with('product')->find($item)
                ?? OrderQuoteRequestItem::with('product')->find($item);
        }

        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $product = (is_object($item) && isset($item->product)) ? $item->product : null;
        $productSettings = $product
            ? (is_string($product->configurator_settings)
                ? (json_decode($product->configurator_settings, true) ?: [])
                : (is_array($product->configurator_settings) ? $product->configurator_settings : []))
            : [];
        $baseSummary = $product
            ? $product->getLaserInstructionSummary()
            : Product::evaluateLaserInstructionSettings([], false);

        $modesCatalog = self::getSupportedEquipmentModes();
        $productCatalog = Product::getLaserEquipmentCatalog();

        $defaultEquipKey = self::normalizeEquipmentKey(
            (string) ($baseSummary['equipment_key'] ?? ''),
            (string) ($baseSummary['process_mode'] ?? '')
        );
        $defaultModeMeta = $modesCatalog[$defaultEquipKey] ?? $modesCatalog['flatbed_standard'];
        $defaultProcessMode = trim((string) ($baseSummary['process_mode'] ?? '')) !== ''
            ? trim((string) $baseSummary['process_mode'])
            : $defaultModeMeta['process_mode'];
        $defaultMaterial = trim((string) ($baseSummary['material'] ?? '')) !== ''
            ? trim((string) $baseSummary['material'])
            : 'Edelstahl';
        $defaultFocusMm = ((float) ($baseSummary['focus_mm'] ?? 0)) > 0
            ? round(max(0.1, min(150.0, (float) $baseSummary['focus_mm'])), 2)
            : 3.0;
        $defaultElementType = self::normalizeElementTypeLabel((string) ($baseSummary['element_type'] ?? 'Bild'), 'Bild');
        $defaultEngraveTab = self::normalizeEngraveTabLabel((string) ($baseSummary['engrave_tab'] ?? 'Gravieren'), 'Gravieren');
        $defaultPresetMode = self::normalizePresetModeLabel((string) ($baseSummary['preset_mode'] ?? 'Benutzerdefiniert'), 'Benutzerdefiniert');
        $defaultPower = ((int) ($baseSummary['power_percent'] ?? 0)) > 0
            ? max(1, min(100, (int) $baseSummary['power_percent']))
            : 70;
        $defaultSpeedFallback = isset($productSettings['xtool_speed_mms']) && is_numeric($productSettings['xtool_speed_mms'])
            ? max(1, min(4000, (int) $productSettings['xtool_speed_mms']))
            : 200;
        $defaultVectorNote = trim((string) ($baseSummary['vector_note'] ?? ''));
        $defaultSpeedMms = self::parseSpeedMmsFromVectorNote($defaultVectorNote, $defaultSpeedFallback);
        $defaultDpi = ((int) ($baseSummary['dpi'] ?? 0)) > 0
            ? max(50, min(2540, (int) $baseSummary['dpi']))
            : 500;
        $defaultDotUs = ((int) ($baseSummary['dot_duration_us'] ?? 0)) > 0
            ? max(self::MIN_DOT_DURATION_US, min(self::MAX_DOT_DURATION_US, (int) $baseSummary['dot_duration_us']))
            : 120;
        $defaultFreqKhz = ((int) ($baseSummary['frequency_khz'] ?? 0)) > 0
            ? max(1, min(200, (int) $baseSummary['frequency_khz']))
            : 60;
        $defaultPasses = ((int) ($baseSummary['passes'] ?? 0)) > 0
            ? max(1, min(20, (int) $baseSummary['passes']))
            : 1;
        $defaultBitmapMode = self::normalizeBitmapModeLabel((string) ($baseSummary['bitmap_mode'] ?? ''), 'Graustufen');
        $defaultEngraveMode = self::normalizeEngraveModeLabel((string) ($baseSummary['engrave_mode'] ?? ''), 'Bidirektional');

        $defaults = [
            'material'        => $defaultMaterial,
            'equipment_key'   => $defaultEquipKey,
            'process_mode'    => $defaultProcessMode,
            'focus_mm'        => $defaultFocusMm,
            'element_type'    => $defaultElementType,
            'engrave_tab'     => $defaultEngraveTab,
            'preset_mode'     => $defaultPresetMode,
            'power_percent'   => $defaultPower,
            'speed_mms'       => $defaultSpeedMms,
            'dpi'             => $defaultDpi,
            'dot_duration_us' => $defaultDotUs,
            'frequency_khz'   => $defaultFreqKhz,
            'passes'          => $defaultPasses,
            'bitmap_mode'     => $defaultBitmapMode,
            'engrave_mode'    => $defaultEngraveMode,
            'vector_note'     => $defaultVectorNote,
        ];

        $conf = (is_object($item) && is_array($item->configuration ?? null)) ? $item->configuration : [];
        $cockpitRoot = is_array($conf['laser_cockpit_parameters'] ?? null) ? $conf['laser_cockpit_parameters'] : [];
        $overridesRoot = is_array($conf['laser_parameter_overrides'] ?? null) ? $conf['laser_parameter_overrides'] : [];

        $rawOverrides = [];
        if (!empty($cockpitRoot[$effectiveSide]) && is_array($cockpitRoot[$effectiveSide])) {
            $rawOverrides = $cockpitRoot[$effectiveSide];
        } elseif (!empty($overridesRoot[$effectiveSide]) && is_array($overridesRoot[$effectiveSide])) {
            $rawOverrides = $overridesRoot[$effectiveSide];
        } elseif ($effectiveSide === 'back' && !empty($cockpitRoot['front']) && is_array($cockpitRoot['front'])) {
            $rawOverrides = $cockpitRoot['front'];
        } elseif ($effectiveSide === 'back' && !empty($overridesRoot['front']) && is_array($overridesRoot['front'])) {
            $rawOverrides = $overridesRoot['front'];
        } elseif (!empty($overridesRoot['default']) && is_array($overridesRoot['default'])) {
            $rawOverrides = $overridesRoot['default'];
        }

        $hasOverrides = !empty($rawOverrides);
        $sanitized = $hasOverrides
            ? $this->sanitizeLaserParameterInput($rawOverrides, $defaults)
            : $defaults;

        // Schmuck-Preset- & Foto-Studio-Fix:
        // Wenn dither_mode in laser_photo_adjustments aktiv gewählt wurde oder auf Schmuck-Marken
        // (seelen-pfote*, goldenes-seelen-herz*, seelen-talisman*) ein echtes Halbton-Kundenfoto liegt,
        // nutze 'Jarvis' (bzw. den gewählten dither_mode) statt hart 'Schwellenwert'.
        $hasExplicitBitmapOverride = $hasOverrides && array_key_exists('bitmap_mode', $rawOverrides) && trim((string) $rawOverrides['bitmap_mode']) !== '';
        $sanitized['bitmap_mode'] = $this->resolvePhotoAwareBitmapModeLabel(
            $item,
            $effectiveSide,
            $conf,
            $hasExplicitBitmapOverride,
            (string) $sanitized['bitmap_mode']
        );

        $equipKey = $sanitized['equipment_key'];
        $modeMeta = $modesCatalog[$equipKey] ?? $modesCatalog['flatbed_standard'];
        $prodEquipMeta = $productCatalog[$equipKey] ?? null;
        $pdfFile = $prodEquipMeta['pdf_file'] ?? null;
        $pdfUrl = !empty($pdfFile) ? asset($pdfFile) : null;
        $focusFormatted = number_format((float) $sanitized['focus_mm'], 1, ',', '.') . ' mm';

        $explicitVectorProvided = $hasOverrides && array_key_exists('vector_note', $rawOverrides);
        if ($explicitVectorProvided) {
            $vectorNote = $sanitized['vector_note'];
        } elseif ($defaultVectorNote !== '' && preg_match('/\d+(?:[.,]\d+)?\s*mm\s*\/\s*s/iu', $defaultVectorNote)) {
            $vectorNote = (string) preg_replace(
                '/\d+(?:[.,]\d+)?\s*mm\s*\/\s*s/iu',
                $sanitized['speed_mms'] . ' mm/s',
                $defaultVectorNote,
                1
            );
        } elseif ($hasOverrides && (int) $sanitized['speed_mms'] !== $defaultSpeedMms) {
            $vectorNote = "Gravieren · {$sanitized['power_percent']} % · {$sanitized['speed_mms']} mm/s · {$sanitized['frequency_khz']} kHz";
        } else {
            $vectorNote = $sanitized['vector_note'];
        }

        $elementType = $sanitized['element_type'];
        $engraveTab = $sanitized['engrave_tab'];
        $presetMode = ($hasOverrides && !array_key_exists('preset_mode', $rawOverrides))
            ? 'Benutzerdefiniert'
            : $sanitized['preset_mode'];
        $setupNote = (string) ($prodEquipMeta['setup_note'] ?? ($baseSummary['setup_note'] ?? ''));

        $copyLines = [];
        $copyLines[] = 'Equipment: ' . ($prodEquipMeta['title'] ?? $modeMeta['title']) . ' (' . ($prodEquipMeta['badge'] ?? $modeMeta['badge']) . ')';
        if ($setupNote !== '') {
            $copyLines[] = 'Hinweis: ' . $setupNote;
        }
        $copyLines[] = 'Modus: ' . $sanitized['process_mode'];
        $copyLines[] = 'Material: ' . $sanitized['material'];
        $copyLines[] = 'Fokus: ' . $focusFormatted;
        $copyLines[] = "{$elementType} ({$engraveTab} / {$presetMode}): {$sanitized['dot_duration_us']} µs · {$sanitized['power_percent']} % · {$sanitized['speed_mms']} mm/s · {$sanitized['dpi']} DPI · {$sanitized['passes']}x · {$sanitized['frequency_khz']} kHz · {$sanitized['bitmap_mode']} · {$sanitized['engrave_mode']}";
        if ($vectorNote !== '') {
            $copyLines[] = 'Vektor: ' . $vectorNote;
        }

        return [
            'is_complete'     => (bool) (($baseSummary['is_complete'] ?? false) || $hasOverrides),
            'has_geometry'    => (bool) ($baseSummary['has_geometry'] ?? true),
            'missing_labels'  => $hasOverrides ? [] : ($baseSummary['missing_labels'] ?? []),
            'has_overrides'   => $hasOverrides,
            'is_customized'   => $hasOverrides,
            'equipment_key'   => $equipKey,
            'equipment_label' => $modeMeta['label'],
            'equipment_title' => $prodEquipMeta['title'] ?? $modeMeta['title'],
            'equipment_badge' => $prodEquipMeta['badge'] ?? $modeMeta['badge'],
            'pdf_file'        => $pdfFile,
            'pdf_filename'    => $prodEquipMeta['pdf_filename'] ?? null,
            'pdf_url'         => $pdfUrl,
            'pdf_label'       => $prodEquipMeta['pdf_label'] ?? null,
            'setup_note'      => $setupNote,
            'process_mode'    => $sanitized['process_mode'],
            'material'        => $sanitized['material'],
            'focus_mm'        => (float) $sanitized['focus_mm'],
            'focus_formatted' => $focusFormatted,
            'element_type'    => $elementType,
            'engrave_tab'     => $engraveTab,
            'preset_mode'     => $presetMode,
            'power_percent'   => (int) $sanitized['power_percent'],
            'speed_mms'       => (int) $sanitized['speed_mms'],
            'speed_mm_s'      => (int) $sanitized['speed_mms'],
            'dpi'             => (int) $sanitized['dpi'],
            'dot_duration_us' => (int) $sanitized['dot_duration_us'],
            'frequency_khz'   => (int) $sanitized['frequency_khz'],
            'passes'          => (int) $sanitized['passes'],
            'bitmap_mode'     => $sanitized['bitmap_mode'],
            'engrave_mode'    => $sanitized['engrave_mode'],
            'vector_note'     => $vectorNote,
            'copy_summary'    => implode("\n", $copyLines),
            'defaults'        => $defaults,
        ];
    }

    /**
     * Ermittelt den effektiven Bitmap-Modus unter Berücksichtigung aktiver laser_photo_adjustments
     * sowie echter Halbton-Kundenfotos auf Schmuck-Presets mit Standardwert 'Schwellenwert'.
     */
    protected function resolvePhotoAwareBitmapModeLabel(
        $item,
        string $effectiveSide,
        array $conf,
        bool $hasExplicitBitmapOverride,
        string $currentBitmapMode
    ): string {
        $logos = $effectiveSide === 'back'
            ? ($conf['logos_back'] ?? $conf['back']['logos'] ?? [])
            : ($conf['logos'] ?? $conf['front']['logos'] ?? []);

        if (empty($logos) && $effectiveSide === 'front' && !empty($conf['logo_storage_path'])) {
            $logos = [[
                'url'   => '/storage/' . ltrim((string) $conf['logo_storage_path'], '/'),
                'value' => ltrim((string) $conf['logo_storage_path'], '/'),
            ]];
        }

        // 1. Aktiver Studio-Dither-Modus aus laser_photo_adjustments (hat Vorrang, sofern kein manuelles Cockpit-Override gesetzt ist)
        if (!$hasExplicitBitmapOverride && !empty($conf['laser_photo_adjustments']) && is_array($conf['laser_photo_adjustments'])) {
            if (is_array($logos)) {
                foreach ($logos as $logo) {
                    if (!is_array($logo)) {
                        continue;
                    }
                    $activeAdj = LaserPhotoPreprocessor::findActivePhotoAdjustment($logo, $conf);
                    if ($activeAdj !== null && !empty($activeAdj['dither_mode'])) {
                        return self::normalizeBitmapModeLabel((string) $activeAdj['dither_mode'], 'Jarvis');
                    }
                }
            }
            foreach ($conf['laser_photo_adjustments'] as $adjEntry) {
                if (is_array($adjEntry) && !empty($adjEntry['enabled']) && !empty($adjEntry['dither_mode'])) {
                    return self::normalizeBitmapModeLabel((string) $adjEntry['dither_mode'], 'Jarvis');
                }
            }
        }

        // 2. Schmuck-Preset-Fix: Wenn Produkt-Default auf 'Schwellenwert' steht, aber ein echtes Halbton-Kundenfoto aufliegt -> 'Jarvis'
        if (!$hasExplicitBitmapOverride && $currentBitmapMode === 'Schwellenwert' && is_array($logos) && !empty($logos)) {
            if ($this->logosContainHalftoneCustomerPhoto($logos, $conf)) {
                return 'Jarvis';
            }
        }

        return $currentBitmapMode;
    }

    /**
     * Prüft schnell und gecacht, ob unter den übergebenen Logo-Einträgen mindestens ein echtes Halbton-Rasterfoto liegt.
     */
    protected function logosContainHalftoneCustomerPhoto(array $logos, array $conf = []): bool
    {
        static $halftoneCache = [];

        foreach ($logos as $logo) {
            if (!is_array($logo)) {
                continue;
            }
            $url = trim((string) ($logo['url'] ?? $logo['value'] ?? $logo['src'] ?? ''));
            $val = trim((string) ($logo['value'] ?? ''));
            if ($url === '' && $val === '') {
                continue;
            }
            if (
                str_starts_with(strtolower($url), 'data:image/svg')
                || str_ends_with(strtolower((string) (parse_url($url, PHP_URL_PATH) ?? $url)), '.svg')
                || str_ends_with(strtolower($val), '.svg')
            ) {
                continue;
            }

            $cacheKey = md5(($val !== '' ? $val : substr($url, 0, 256)) . '|' . strlen($url));
            if (array_key_exists($cacheKey, $halftoneCache)) {
                if ($halftoneCache[$cacheKey] === true) {
                    return true;
                }
                continue;
            }

            $rawBytes = null;
            if (str_starts_with($url, 'data:image/')) {
                if (preg_match('/^data:image\/[a-zA-Z0-9\+\-]+;base64,(.+)$/s', $url, $m)) {
                    $decoded = base64_decode($m[1], true);
                    if (is_string($decoded) && $decoded !== '') {
                        $rawBytes = $decoded;
                    }
                }
            } else {
                $candKey = $val !== '' ? $val : $url;
                $resolvedRel = LaserPhotoPreprocessor::resolveOriginalPhotoRelPath($candKey, $conf);
                $normRel = LaserPhotoPreprocessor::normalizePublicRelPath($resolvedRel);
                if ($normRel !== '' && Storage::disk('public')->exists($normRel)) {
                    $abs = Storage::disk('public')->path($normRel);
                    if (@is_file($abs)) {
                        $rawBytes = @file_get_contents($abs) ?: null;
                    }
                }
            }

            $isHalftone = false;
            if (is_string($rawBytes) && $rawBytes !== '' && function_exists('imagecreatefromstring')) {
                $gd = @imagecreatefromstring($rawBytes);
                if ($gd) {
                    $sw = min(160, max(1, imagesx($gd)));
                    $sh = min(160, max(1, imagesy($gd)));
                    $sample = $gd;
                    if (imagesx($gd) > 160 || imagesy($gd) > 160) {
                        $resized = imagecreatetruecolor($sw, $sh);
                        if ($resized) {
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
                            imagecopyresampled($resized, $gd, 0, 0, 0, 0, $sw, $sh, imagesx($gd), imagesy($gd));
                            imagedestroy($gd);
                            $sample = $resized;
                        }
                    }
                    LaserPhotoPreprocessor::decodeLegacyAlphaMaskGdInPlace($sample, false);
                    $hist = array_fill(0, 256, 0);
                    $vis = 0;
                    $w = imagesx($sample);
                    $h = imagesy($sample);
                    for ($y = 0; $y < $h; $y++) {
                        for ($x = 0; $x < $w; $x++) {
                            $c = imagecolorat($sample, $x, $y);
                            if ((($c >> 24) & 0x7F) >= 120) {
                                continue;
                            }
                            $r = ($c >> 16) & 0xFF;
                            $g = ($c >> 8) & 0xFF;
                            $b = $c & 0xFF;
                            $lum = max(0, min(255, (int) round((0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b))));
                            $hist[$lum]++;
                            $vis++;
                        }
                    }
                    imagedestroy($sample);
                    $isHalftone = LaserPhotoPreprocessor::classifyHistogram($hist, $vis) === 'halftone';
                }
            }

            $halftoneCache[$cacheKey] = $isHalftone;
            if ($isHalftone) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validiert und clampt übergebene Laserparameter (alle 15 xTool-Felder + speed_mms) gegen die Grenzwerte des xTool F2 Ultra UV.
     *
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function sanitizeLaserParameterInput(array $raw, array $defaults): array
    {
        $modesCatalog = self::getSupportedEquipmentModes();

        $material = array_key_exists('material', $raw)
            ? trim(strip_tags((string) $raw['material']))
            : (string) $defaults['material'];
        $material = $material !== '' ? mb_substr($material, 0, 120) : (string) $defaults['material'];

        $hasExplicitEquip = array_key_exists('equipment_key', $raw) && trim((string) $raw['equipment_key']) !== '';
        $hasExplicitProc = array_key_exists('process_mode', $raw) && trim((string) $raw['process_mode']) !== '';

        if ($hasExplicitEquip || $hasExplicitProc) {
            $equipKey = self::normalizeEquipmentKey(
                $hasExplicitEquip ? (string) $raw['equipment_key'] : '',
                $hasExplicitProc ? (string) $raw['process_mode'] : ''
            );
            $modeMeta = $modesCatalog[$equipKey] ?? $modesCatalog['flatbed_standard'];
            if ($hasExplicitProc && !$hasExplicitEquip) {
                $rawProcClean = mb_substr(trim(strip_tags((string) $raw['process_mode'])), 0, 120);
                $processMode = isset($modesCatalog[mb_strtolower($rawProcClean)])
                    ? $modeMeta['process_mode']
                    : ($rawProcClean !== '' ? $rawProcClean : $modeMeta['process_mode']);
            } elseif ($equipKey !== (string) $defaults['equipment_key'] && !$hasExplicitProc) {
                $processMode = $modeMeta['process_mode'];
            } else {
                $rawProcClean = $hasExplicitProc
                    ? mb_substr(trim(strip_tags((string) $raw['process_mode'])), 0, 120)
                    : '';
                $processMode = $rawProcClean !== '' ? $rawProcClean : (string) $defaults['process_mode'];
            }
        } else {
            $equipKey = (string) $defaults['equipment_key'];
            $processMode = (string) $defaults['process_mode'];
        }

        $parseFloat = static function ($val, float $fallback): float {
            if ($val === null || $val === '') {
                return $fallback;
            }
            $norm = str_replace(',', '.', trim((string) $val));
            return is_numeric($norm) ? (float) $norm : $fallback;
        };

        $parseInt = static function ($val, int $fallback): int {
            if ($val === null || $val === '') {
                return $fallback;
            }
            $norm = str_replace(',', '.', trim((string) $val));
            return is_numeric($norm) ? (int) round((float) $norm) : $fallback;
        };

        $rawSpeedVal = $raw['speed_mms'] ?? ($raw['speed_mm_s'] ?? null);
        $vectorNoteRaw = array_key_exists('vector_note', $raw)
            ? mb_substr(trim(strip_tags((string) $raw['vector_note'])), 0, 250)
            : (string) ($defaults['vector_note'] ?? '');

        if (($rawSpeedVal === null || $rawSpeedVal === '') && array_key_exists('vector_note', $raw) && $vectorNoteRaw !== '') {
            $speedFromNote = self::parseSpeedMmsFromVectorNote($vectorNoteRaw, -1);
            if ($speedFromNote >= 1) {
                $rawSpeedVal = $speedFromNote;
            }
        }

        $focusMm = round(max(0.1, min(150.0, $parseFloat($raw['focus_mm'] ?? null, (float) $defaults['focus_mm']))), 2);
        $powerPercent = max(1, min(100, $parseInt($raw['power_percent'] ?? null, (int) $defaults['power_percent'])));
        $speedMms = max(1, min(4000, $parseInt($rawSpeedVal, (int) ($defaults['speed_mms'] ?? 200))));
        $dpi = max(50, min(2540, $parseInt($raw['dpi'] ?? null, (int) $defaults['dpi'])));
        $dotDurationUs = max(self::MIN_DOT_DURATION_US, min(self::MAX_DOT_DURATION_US, $parseInt($raw['dot_duration_us'] ?? null, (int) $defaults['dot_duration_us'])));
        $frequencyKhz = max(1, min(200, $parseInt($raw['frequency_khz'] ?? null, (int) $defaults['frequency_khz'])));
        $passes = max(1, min(20, $parseInt($raw['passes'] ?? null, (int) $defaults['passes'])));

        $elementType = array_key_exists('element_type', $raw)
            ? self::normalizeElementTypeLabel((string) $raw['element_type'], (string) ($defaults['element_type'] ?? 'Bild'))
            : (string) ($defaults['element_type'] ?? 'Bild');

        $engraveTab = array_key_exists('engrave_tab', $raw)
            ? self::normalizeEngraveTabLabel((string) $raw['engrave_tab'], (string) ($defaults['engrave_tab'] ?? 'Gravieren'))
            : (string) ($defaults['engrave_tab'] ?? 'Gravieren');

        $presetMode = array_key_exists('preset_mode', $raw)
            ? self::normalizePresetModeLabel((string) $raw['preset_mode'], (string) ($defaults['preset_mode'] ?? 'Benutzerdefiniert'))
            : (string) ($defaults['preset_mode'] ?? 'Benutzerdefiniert');

        $bitmapMode = array_key_exists('bitmap_mode', $raw)
            ? self::normalizeBitmapModeLabel((string) $raw['bitmap_mode'], (string) $defaults['bitmap_mode'])
            : (string) $defaults['bitmap_mode'];

        $engraveMode = array_key_exists('engrave_mode', $raw)
            ? self::normalizeEngraveModeLabel((string) $raw['engrave_mode'], (string) $defaults['engrave_mode'])
            : (string) $defaults['engrave_mode'];

        return [
            'material'        => $material,
            'equipment_key'   => $equipKey,
            'process_mode'    => $processMode,
            'focus_mm'        => $focusMm,
            'element_type'    => $elementType,
            'engrave_tab'     => $engraveTab,
            'preset_mode'     => $presetMode,
            'power_percent'   => $powerPercent,
            'speed_mms'       => $speedMms,
            'dpi'             => $dpi,
            'dot_duration_us' => $dotDurationUs,
            'frequency_khz'   => $frequencyKhz,
            'passes'          => $passes,
            'bitmap_mode'     => $bitmapMode,
            'engrave_mode'    => $engraveMode,
            'vector_note'     => $vectorNoteRaw,
        ];
    }

    /**
     * Speichert und normalisiert alle xTool-Laserparameter direkt auf der Bestellposition.
     */
    public function persistItemLaserParameters(OrderOrderItem $item, string $side, array $rawParams): array
    {
        return $this->saveOrderItemLaserParameterOverrides($item, $side, $rawParams, false);
    }

    /**
     * Speichert benutzerdefinierte Laserparameter-Overrides direkt auf der Bestellposition
     * (synchron in configuration['laser_cockpit_parameters'][$side] und configuration['laser_parameter_overrides'][$side]).
     */
    public function saveOrderItemLaserParameterOverrides(
        OrderOrderItem $item,
        string $side,
        array $rawParams,
        bool $onlyIfChangedFromCurrent = false
    ): array {
        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $item->loadMissing('product');

        $currentEffective = $this->resolveEffectiveLaserParameters($item, $effectiveSide);
        $baseline = [
            'material'        => $currentEffective['material'],
            'equipment_key'   => $currentEffective['equipment_key'],
            'process_mode'    => $currentEffective['process_mode'],
            'focus_mm'        => $currentEffective['focus_mm'],
            'element_type'    => $currentEffective['element_type'],
            'engrave_tab'     => $currentEffective['engrave_tab'],
            'preset_mode'     => $currentEffective['preset_mode'],
            'power_percent'   => $currentEffective['power_percent'],
            'speed_mms'       => $currentEffective['speed_mms'],
            'dpi'             => $currentEffective['dpi'],
            'dot_duration_us' => $currentEffective['dot_duration_us'],
            'frequency_khz'   => $currentEffective['frequency_khz'],
            'passes'          => $currentEffective['passes'],
            'bitmap_mode'     => $currentEffective['bitmap_mode'],
            'engrave_mode'    => $currentEffective['engrave_mode'],
            'vector_note'     => $currentEffective['vector_note'],
        ];

        $sanitized = $this->sanitizeLaserParameterInput($rawParams, $baseline);

        if ($onlyIfChangedFromCurrent && !$currentEffective['has_overrides']) {
            $defaults = $currentEffective['defaults'] ?? $baseline;
            $differsFromDefaults = false;
            foreach ($defaults as $k => $defVal) {
                if (!array_key_exists($k, $sanitized)) {
                    continue;
                }
                if ($k === 'focus_mm') {
                    if (abs((float) $sanitized[$k] - (float) $defVal) >= 0.05) {
                        $differsFromDefaults = true;
                        break;
                    }
                } elseif ((string) $sanitized[$k] !== (string) $defVal) {
                    $differsFromDefaults = true;
                    break;
                }
            }
            if (!$differsFromDefaults) {
                return $currentEffective;
            }
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        if (!isset($conf['laser_cockpit_parameters']) || !is_array($conf['laser_cockpit_parameters'])) {
            $conf['laser_cockpit_parameters'] = [];
        }
        if (!isset($conf['laser_parameter_overrides']) || !is_array($conf['laser_parameter_overrides'])) {
            $conf['laser_parameter_overrides'] = [];
        }

        $storedPayload = array_merge($sanitized, [
            'updated_at' => now()->toIso8601String(),
        ]);

        $conf['laser_cockpit_parameters'][$effectiveSide] = $storedPayload;
        $conf['laser_parameter_overrides'][$effectiveSide] = $storedPayload;

        $item->configuration = $conf;
        $item->saveQuietly();

        return $this->resolveEffectiveLaserParameters($item, $effectiveSide);
    }

    /**
     * Setzt die Laserparameter-Overrides einer Bestellposition wieder auf die Produkt-Standardwerte zurück.
     */
    public function resetItemLaserParameters(OrderOrderItem $item, string $side = 'front'): array
    {
        return $this->resetOrderItemLaserParameterOverrides($item, $side);
    }

    /**
     * Setzt die Laserparameter-Overrides einer Bestellposition für die gewählte Seite wieder auf die Produkt-Standardwerte zurück.
     */
    public function resetOrderItemLaserParameterOverrides(OrderOrderItem $item, string $side = 'front'): array
    {
        $effectiveSide = ($side === 'back') ? 'back' : 'front';
        $item->loadMissing('product');

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $changed = false;

        foreach (['laser_cockpit_parameters', 'laser_parameter_overrides'] as $rootKey) {
            if (isset($conf[$rootKey]) && is_array($conf[$rootKey])) {
                unset($conf[$rootKey][$effectiveSide], $conf[$rootKey]['default']);
                if (empty($conf[$rootKey])) {
                    unset($conf[$rootKey]);
                }
                $changed = true;
            }
        }

        if ($changed) {
            $item->configuration = $conf;
            $item->saveQuietly();
        }

        return $this->resolveEffectiveLaserParameters($item, $effectiveSide);
    }

    /**
     * Liefert eine vollständige, editierbare Laser-Baseline für ein Produkt (aus configurator_settings,
     * Katalog-Fallback und sicheren xTool F2 Ultra UV Standardwerten).
     *
     * @return array<string, mixed>
     */
    public function getEditableProductLaserBaseline(Product $product): array
    {
        $currentSummary = $product->getLaserInstructionSummary();
        $productSettings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $catalog = self::getDefaultProductLaserProfilesCatalog();
        $slug = (string) ($product->slug ?? '');
        $tplKey = trim((string) ($productSettings['template_package'] ?? ''));
        $catalogList = $catalog[$slug] ?? ($tplKey !== '' && isset($catalog[$tplKey]) ? $catalog[$tplKey] : []);
        $catalogDefault = collect($catalogList)->firstWhere('is_default', true) ?? ($catalogList[0] ?? []);

        $rawMat = trim((string) ($currentSummary['material'] ?? ''))
            ?: (trim((string) ($catalogDefault['material'] ?? '')) ?: 'Edelstahl');
        $rawProc = trim((string) ($currentSummary['process_mode'] ?? ''))
            ?: (trim((string) ($catalogDefault['process_mode'] ?? '')) ?: 'Prozess auf flacher Oberfläche');
        $equipKey = self::normalizeEquipmentKey(
            (string) ($currentSummary['equipment_key'] ?? ($catalogDefault['equipment_key'] ?? '')),
            $rawProc
        );

        $focusMm = ((float) ($currentSummary['focus_mm'] ?? 0)) > 0
            ? (float) $currentSummary['focus_mm']
            : (((float) ($catalogDefault['focus_mm'] ?? 0)) > 0 ? (float) $catalogDefault['focus_mm'] : 19.5);
        $powerPercent = ((int) ($currentSummary['power_percent'] ?? 0)) > 0
            ? (int) $currentSummary['power_percent']
            : (((int) ($catalogDefault['power_percent'] ?? 0)) > 0 ? (int) $catalogDefault['power_percent'] : 70);
        $vectorNote = trim((string) ($currentSummary['vector_note'] ?? ($catalogDefault['vector_note'] ?? '')));

        if (isset($productSettings['xtool_speed_mms']) && is_numeric($productSettings['xtool_speed_mms']) && (int) $productSettings['xtool_speed_mms'] > 0) {
            $speedMms = (int) $productSettings['xtool_speed_mms'];
        } elseif (((int) ($catalogDefault['speed_mms'] ?? 0)) > 0) {
            $speedMms = (int) $catalogDefault['speed_mms'];
        } else {
            $speedMms = self::parseSpeedMmsFromVectorNote($vectorNote, 200);
        }

        $dpi = ((int) ($currentSummary['dpi'] ?? 0)) > 0
            ? (int) $currentSummary['dpi']
            : (((int) ($catalogDefault['dpi'] ?? 0)) > 0 ? (int) $catalogDefault['dpi'] : 600);
        $dotDurationUs = ((int) ($currentSummary['dot_duration_us'] ?? 0)) > 0
            ? (int) $currentSummary['dot_duration_us']
            : (((int) ($catalogDefault['dot_duration_us'] ?? 0)) > 0 ? (int) $catalogDefault['dot_duration_us'] : 210);
        $frequencyKhz = ((int) ($currentSummary['frequency_khz'] ?? 0)) > 0
            ? (int) $currentSummary['frequency_khz']
            : (((int) ($catalogDefault['frequency_khz'] ?? 0)) > 0 ? (int) $catalogDefault['frequency_khz'] : 65);
        $passes = ((int) ($currentSummary['passes'] ?? 0)) > 0
            ? (int) $currentSummary['passes']
            : (((int) ($catalogDefault['passes'] ?? 0)) > 0 ? (int) $catalogDefault['passes'] : 1);

        return [
            'material'        => $rawMat,
            'equipment_key'   => $equipKey,
            'process_mode'    => $rawProc,
            'focus_mm'        => $focusMm,
            'element_type'    => self::normalizeElementTypeLabel((string) ($currentSummary['element_type'] ?? ($catalogDefault['element_type'] ?? 'Bild')), 'Bild'),
            'engrave_tab'     => self::normalizeEngraveTabLabel((string) ($currentSummary['engrave_tab'] ?? ($catalogDefault['engrave_tab'] ?? 'Gravieren')), 'Gravieren'),
            'preset_mode'     => self::normalizePresetModeLabel((string) ($currentSummary['preset_mode'] ?? ($catalogDefault['preset_mode'] ?? 'Benutzerdefiniert')), 'Benutzerdefiniert'),
            'power_percent'   => $powerPercent,
            'speed_mms'       => $speedMms,
            'dpi'             => $dpi,
            'dot_duration_us' => $dotDurationUs,
            'frequency_khz'   => $frequencyKhz,
            'passes'          => $passes,
            'bitmap_mode'     => self::normalizeBitmapModeLabel((string) ($currentSummary['bitmap_mode'] ?? ($catalogDefault['bitmap_mode'] ?? '')), 'Jarvis'),
            'engrave_mode'    => self::normalizeEngraveModeLabel((string) ($currentSummary['engrave_mode'] ?? ($catalogDefault['engrave_mode'] ?? '')), 'Bidirektional'),
            'vector_note'     => $vectorNote,
        ];
    }

    /**
     * Stellt sicher, dass photo_studio-Arrays auf den Profilen in $syncedSettings['xtool_profiles']
     * nach Product::syncLaserProfilesAndPrimarySettings() vollständig (9 Keys) erhalten bleiben.
     *
     * @param  array<int, array<string, mixed>>  $sourceProfiles
     * @param  array<string, mixed>  $syncedSettings
     * @return array<string, mixed>
     */
    protected static function restorePhotoStudioIntoSyncedSettings(array $sourceProfiles, array $syncedSettings): array
    {
        if (empty($syncedSettings['xtool_profiles']) || !is_array($syncedSettings['xtool_profiles'])) {
            return $syncedSettings;
        }

        $studioById = [];
        $studioByTitle = [];
        $studioByIndex = [];
        foreach (array_values($sourceProfiles) as $idx => $src) {
            if (!is_array($src) || empty($src['photo_studio']) || !is_array($src['photo_studio'])) {
                continue;
            }
            $cleanStudio = self::sanitizeProfilePhotoStudioParams($src['photo_studio']);
            $srcId = trim((string) ($src['id'] ?? ''));
            $srcTitle = mb_strtolower(trim((string) ($src['title'] ?? '')));
            if ($srcId !== '') {
                $studioById[$srcId] = $cleanStudio;
            }
            if ($srcTitle !== '') {
                $studioByTitle[$srcTitle] = $cleanStudio;
            }
            $studioByIndex[$idx] = $cleanStudio;
        }

        foreach ($syncedSettings['xtool_profiles'] as $idx => &$prof) {
            if (!is_array($prof)) {
                continue;
            }
            if (!empty($prof['photo_studio']) && is_array($prof['photo_studio'])) {
                $prof['photo_studio'] = self::sanitizeProfilePhotoStudioParams($prof['photo_studio']);
                continue;
            }
            $pid = trim((string) ($prof['id'] ?? ''));
            $ptitle = mb_strtolower(trim((string) ($prof['title'] ?? '')));
            if ($pid !== '' && isset($studioById[$pid])) {
                $prof['photo_studio'] = $studioById[$pid];
            } elseif ($ptitle !== '' && isset($studioByTitle[$ptitle])) {
                $prof['photo_studio'] = $studioByTitle[$ptitle];
            } elseif (isset($studioByIndex[$idx])) {
                $prof['photo_studio'] = $studioByIndex[$idx];
            }
        }
        unset($prof);

        return $syncedSettings;
    }

    /**
     * Lädt die normalisierten Laserprofile eines Produkts unter garantierter Beibehaltung aller photo_studio-Werte.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadProductProfilesPreservingPhotoStudio(Product $product): array
    {
        $rawSettings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $rawProfiles = isset($rawSettings['xtool_profiles']) && is_array($rawSettings['xtool_profiles'])
            ? array_values(array_filter($rawSettings['xtool_profiles'], 'is_array'))
            : [];

        $synced = Product::syncLaserProfilesAndPrimarySettings($rawSettings, false);
        $synced = self::restorePhotoStudioIntoSyncedSettings($rawProfiles, $synced);

        return isset($synced['xtool_profiles']) && is_array($synced['xtool_profiles'])
            ? array_values($synced['xtool_profiles'])
            : [];
    }

    /**
     * Übernimmt getestete Laserparameter aus der Bestellbearbeitung dauerhaft als neuen Produkt-Standard (Schritt 4.2).
     */
    public function saveLaserParametersToProduct(Product $product, array $rawParams): array
    {
        $currentSummary = $product->getLaserInstructionSummary();
        $productSettings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);
        $rawProfilesBefore = isset($productSettings['xtool_profiles']) && is_array($productSettings['xtool_profiles'])
            ? array_values(array_filter($productSettings['xtool_profiles'], 'is_array'))
            : [];

        $baseline = [
            'material'        => trim((string) ($currentSummary['material'] ?? '')) ?: 'Edelstahl',
            'equipment_key'   => self::normalizeEquipmentKey((string) ($currentSummary['equipment_key'] ?? ''), (string) ($currentSummary['process_mode'] ?? '')),
            'process_mode'    => trim((string) ($currentSummary['process_mode'] ?? '')) ?: 'Prozess auf flacher Oberfläche',
            'focus_mm'        => ((float) ($currentSummary['focus_mm'] ?? 0)) > 0 ? (float) $currentSummary['focus_mm'] : 3.0,
            'element_type'    => self::normalizeElementTypeLabel((string) ($currentSummary['element_type'] ?? 'Bild'), 'Bild'),
            'engrave_tab'     => self::normalizeEngraveTabLabel((string) ($currentSummary['engrave_tab'] ?? 'Gravieren'), 'Gravieren'),
            'preset_mode'     => self::normalizePresetModeLabel((string) ($currentSummary['preset_mode'] ?? 'Benutzerdefiniert'), 'Benutzerdefiniert'),
            'power_percent'   => ((int) ($currentSummary['power_percent'] ?? 0)) > 0 ? (int) $currentSummary['power_percent'] : 70,
            'speed_mms'       => isset($productSettings['xtool_speed_mms']) && is_numeric($productSettings['xtool_speed_mms'])
                ? (int) $productSettings['xtool_speed_mms']
                : self::parseSpeedMmsFromVectorNote((string) ($currentSummary['vector_note'] ?? ''), 200),
            'dpi'             => ((int) ($currentSummary['dpi'] ?? 0)) > 0 ? (int) $currentSummary['dpi'] : 500,
            'dot_duration_us' => ((int) ($currentSummary['dot_duration_us'] ?? 0)) > 0 ? (int) $currentSummary['dot_duration_us'] : 120,
            'frequency_khz'   => ((int) ($currentSummary['frequency_khz'] ?? 0)) > 0 ? (int) $currentSummary['frequency_khz'] : 60,
            'passes'          => ((int) ($currentSummary['passes'] ?? 0)) > 0 ? (int) $currentSummary['passes'] : 1,
            'bitmap_mode'     => self::normalizeBitmapModeLabel((string) ($currentSummary['bitmap_mode'] ?? ''), 'Graustufen'),
            'engrave_mode'    => self::normalizeEngraveModeLabel((string) ($currentSummary['engrave_mode'] ?? ''), 'Bidirektional'),
            'vector_note'     => trim((string) ($currentSummary['vector_note'] ?? '')),
        ];

        $sanitized = $this->sanitizeLaserParameterInput($rawParams, $baseline);

        $productSettings['xtool_equipment_key']   = $sanitized['equipment_key'];
        $productSettings['xtool_process_mode']    = $sanitized['process_mode'];
        $productSettings['xtool_material']        = $sanitized['material'];
        $productSettings['xtool_focus_mm']        = $sanitized['focus_mm'];
        $productSettings['xtool_element_type']    = $sanitized['element_type'];
        $productSettings['xtool_engrave_tab']     = $sanitized['engrave_tab'];
        $productSettings['xtool_preset_mode']     = $sanitized['preset_mode'];
        $productSettings['xtool_dot_duration_us'] = $sanitized['dot_duration_us'];
        $productSettings['xtool_power_percent']   = $sanitized['power_percent'];
        $productSettings['xtool_speed_mms']       = $sanitized['speed_mms'];
        $productSettings['xtool_dpi']             = $sanitized['dpi'];
        $productSettings['xtool_passes']          = $sanitized['passes'];
        $productSettings['xtool_frequency_khz']   = $sanitized['frequency_khz'];
        $productSettings['xtool_bitmap_mode']     = $sanitized['bitmap_mode'];
        $productSettings['xtool_engrave_mode']    = $sanitized['engrave_mode'];
        $productSettings['xtool_vector_note']     = $sanitized['vector_note'];

        $productSettings = Product::syncLaserProfilesAndPrimarySettings($productSettings, true);
        $productSettings = self::restorePhotoStudioIntoSyncedSettings($rawProfilesBefore, $productSettings);
        $product->configurator_settings = $productSettings;
        $product->saveQuietly();

        return $product->fresh()->getLaserInstructionSummary();
    }

    /**
     * Speichert aktuelle Laserparameter als neues (oder gleichnamig aktualisiertes) benanntes Produkt-Profil
     * in configurator_settings['xtool_profiles'] und synchronisiert bei $makeDefault = true auch die primären Felder.
     *
     * @param  array<string, mixed>  $rawParams
     * @return array{profile: array<string, mixed>, profiles: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function saveLaserProfileToProduct(
        Product $product,
        string $title,
        array $rawParams,
        bool $makeDefault = false
    ): array {
        $existingProfiles = $this->loadProductProfilesPreservingPhotoStudio($product);
        $cleanTitle = trim(str_replace(" \u{2013} ", ' · ', strip_tags($title)));
        if ($cleanTitle === '') {
            $cleanTitle = 'Eigenes Profil ' . (count($existingProfiles) + 1);
        }
        $cleanTitle = mb_substr($cleanTitle, 0, 120);

        $productSettings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $baseline = $this->getEditableProductLaserBaseline($product);
        $sanitized = $this->sanitizeLaserParameterInput($rawParams, $baseline);

        $matchedIndex = null;
        foreach ($existingProfiles as $idx => $ep) {
            if (mb_strtolower(trim((string) ($ep['title'] ?? ''))) === mb_strtolower($cleanTitle)) {
                $matchedIndex = $idx;
                break;
            }
        }

        $slugId = Str::slug($cleanTitle, '_');
        $profileId = $matchedIndex !== null
            ? (string) ($existingProfiles[$matchedIndex]['id'] ?? ('prof_' . $slugId))
            : ('prof_' . ($slugId !== '' ? substr($slugId, 0, 32) : 'custom') . '_' . substr(md5($cleanTitle . microtime(true)), 0, 6));

        $resolvedPhotoStudio = null;
        if (isset($rawParams['photo_studio']) && is_array($rawParams['photo_studio']) && !empty($rawParams['photo_studio'])) {
            $resolvedPhotoStudio = self::sanitizeProfilePhotoStudioParams($rawParams['photo_studio']);
        } elseif ($matchedIndex !== null && !empty($existingProfiles[$matchedIndex]['photo_studio']) && is_array($existingProfiles[$matchedIndex]['photo_studio'])) {
            $resolvedPhotoStudio = self::sanitizeProfilePhotoStudioParams($existingProfiles[$matchedIndex]['photo_studio']);
        }

        $newProfile = Product::normalizeLaserProfileItem([
            'id'              => $profileId,
            'title'           => $cleanTitle,
            'is_default'      => $makeDefault || ($matchedIndex !== null && !empty($existingProfiles[$matchedIndex]['is_default'])),
            'focus_mm'        => $sanitized['focus_mm'],
            'element_type'    => $sanitized['element_type'],
            'engrave_tab'     => $sanitized['engrave_tab'],
            'preset_mode'     => $sanitized['preset_mode'],
            'dot_duration_us' => $sanitized['dot_duration_us'],
            'power_percent'   => $sanitized['power_percent'],
            'dpi'             => $sanitized['dpi'],
            'frequency_khz'   => $sanitized['frequency_khz'],
            'speed_mms'       => $sanitized['speed_mms'],
            'passes'          => $sanitized['passes'],
            'bitmap_mode'     => $sanitized['bitmap_mode'],
            'engrave_mode'    => $sanitized['engrave_mode'],
            'vector_note'     => $sanitized['vector_note'],
            'equipment_key'   => $sanitized['equipment_key'],
            'process_mode'    => $sanitized['process_mode'],
            'material'        => $sanitized['material'],
        ], $matchedIndex ?? count($existingProfiles), $productSettings);

        if ($resolvedPhotoStudio !== null) {
            $newProfile['photo_studio'] = $resolvedPhotoStudio;
        }

        if ($makeDefault) {
            foreach ($existingProfiles as &$ep) {
                $ep['is_default'] = false;
            }
            unset($ep);
            $newProfile['is_default'] = true;
            $productSettings['xtool_equipment_key'] = $sanitized['equipment_key'];
            $productSettings['xtool_process_mode']  = $sanitized['process_mode'];
            $productSettings['xtool_material']      = $sanitized['material'];
        }

        if ($matchedIndex !== null) {
            $existingProfiles[$matchedIndex] = $newProfile;
        } else {
            $existingProfiles[] = $newProfile;
        }

        $profilesSnapshot = array_values($existingProfiles);
        $productSettings['xtool_profiles'] = $profilesSnapshot;
        $productSettings = Product::syncLaserProfilesAndPrimarySettings($productSettings, false);
        $productSettings = self::restorePhotoStudioIntoSyncedSettings($profilesSnapshot, $productSettings);

        $product->configurator_settings = $productSettings;
        $product->saveQuietly();

        $freshProduct = $product->fresh() ?? $product;
        $freshProfiles = $this->loadProductProfilesPreservingPhotoStudio($freshProduct);
        $savedProfile = collect($freshProfiles)->firstWhere('id', $profileId) ?? end($freshProfiles) ?: $newProfile;

        return [
            'profile'  => $savedProfile,
            'profiles' => $freshProfiles,
            'summary'  => $freshProduct->getLaserInstructionSummary(),
        ];
    }

    /**
     * Speichert die 9 Foto-Studio-Parameter dauerhaft auf einem bestehenden Produkt-Laserprofil
     * und synchronisiert dessen bitmap_mode passend zum gewählten dither_mode.
     *
     * @param  array<string, mixed>  $studioParams
     * @return array{profiles: array<int, array<string, mixed>>, updated_profile: array<string, mixed>}
     */
    public function updateProductLaserProfilePhotoStudio(
        Product $product,
        string $profileId,
        array $studioParams
    ): array {
        $cleanStudio = self::sanitizeProfilePhotoStudioParams($studioParams);
        $settings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $profiles = $this->loadProductProfilesPreservingPhotoStudio($product);
        if (empty($profiles)) {
            $catalog = self::getDefaultProductLaserProfilesCatalog();
            $slug = (string) ($product->slug ?? '');
            $tplKey = trim((string) ($settings['template_package'] ?? ''));
            $catalogProfiles = $catalog[$slug] ?? ($tplKey !== '' && isset($catalog[$tplKey]) ? $catalog[$tplKey] : []);
            if (!empty($catalogProfiles)) {
                $profiles = array_values($catalogProfiles);
            } else {
                $baseline = $this->getEditableProductLaserBaseline($product);
                $profiles = [
                    Product::normalizeLaserProfileItem(array_merge($baseline, [
                        'id'         => 'prof_standard',
                        'title'      => 'Standard',
                        'is_default' => true,
                    ]), 0, $settings),
                ];
            }
        }

        $cleanProfileId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);
        $targetIndex = null;
        if ($cleanProfileId !== '') {
            foreach ($profiles as $idx => $p) {
                if ((string) ($p['id'] ?? '') === $cleanProfileId || (string) ($p['title'] ?? '') === $cleanProfileId) {
                    $targetIndex = $idx;
                    break;
                }
            }
        }
        if ($targetIndex === null) {
            foreach ($profiles as $idx => $p) {
                if (!empty($p['is_default'])) {
                    $targetIndex = $idx;
                    break;
                }
            }
        }
        if ($targetIndex === null) {
            $targetIndex = 0;
        }

        $profiles[$targetIndex]['photo_studio'] = $cleanStudio;
        $profiles[$targetIndex]['bitmap_mode'] = self::ditherModeToBitmapModeLabel($cleanStudio['dither_mode']);
        $targetId = (string) ($profiles[$targetIndex]['id'] ?? 'prof_standard');

        $profilesSnapshot = array_values($profiles);
        $settings['xtool_profiles'] = $profilesSnapshot;
        $settings = Product::syncLaserProfilesAndPrimarySettings($settings, false);
        $settings = self::restorePhotoStudioIntoSyncedSettings($profilesSnapshot, $settings);

        $product->configurator_settings = $settings;
        $product->save();

        $freshProduct = $product->fresh() ?? $product;
        $savedProfiles = $this->loadProductProfilesPreservingPhotoStudio($freshProduct);
        $updatedProfile = collect($savedProfiles)->firstWhere('id', $targetId)
            ?? ($savedProfiles[$targetIndex] ?? ($savedProfiles[0] ?? $profilesSnapshot[$targetIndex]));

        return [
            'profiles'        => $savedProfiles,
            'updated_profile' => $updatedProfile,
        ];
    }

    /**
     * Erzeugt aus dem Laser-Foto-Studio heraus ein neues benanntes Produkt-Laserprofil (oder aktualisiert ein gleichnamiges),
     * indem die Maschinenwerte von $baseProfileId (Fallback Default-Profil / Produkt-Baseline) geklont und mit photo_studio verknüpft werden.
     *
     * @param  array<string, mixed>  $studioParams
     * @return array{profiles: array<int, array<string, mixed>>, created_profile: array<string, mixed>}
     */
    public function createProductLaserProfileFromPhotoStudio(
        Product $product,
        string $title,
        ?string $baseProfileId,
        array $studioParams,
        bool $makeDefault = false
    ): array {
        $cleanTitle = trim(str_replace(" \u{2013} ", ' · ', strip_tags($title)));
        if ($cleanTitle === '') {
            throw new \InvalidArgumentException('Bitte gib einen Namen für das neue Laser-Profil ein.');
        }
        $cleanTitle = mb_substr($cleanTitle, 0, 120);

        $cleanStudio = self::sanitizeProfilePhotoStudioParams($studioParams);
        $settings = is_string($product->configurator_settings)
            ? (json_decode($product->configurator_settings, true) ?: [])
            : (is_array($product->configurator_settings) ? $product->configurator_settings : []);

        $baseline = $this->getEditableProductLaserBaseline($product);
        $profiles = $this->loadProductProfilesPreservingPhotoStudio($product);
        if (empty($profiles)) {
            $catalog = self::getDefaultProductLaserProfilesCatalog();
            $slug = (string) ($product->slug ?? '');
            $tplKey = trim((string) ($settings['template_package'] ?? ''));
            $catalogProfiles = $catalog[$slug] ?? ($tplKey !== '' && isset($catalog[$tplKey]) ? $catalog[$tplKey] : []);
            if (!empty($catalogProfiles)) {
                $profiles = array_values($catalogProfiles);
            } else {
                $profiles = [
                    Product::normalizeLaserProfileItem(array_merge($baseline, [
                        'id'         => 'prof_standard',
                        'title'      => 'Standard',
                        'is_default' => true,
                    ]), 0, $settings),
                ];
            }
        }

        $cleanBaseId = $baseProfileId !== null
            ? (preg_replace('/^profile:/i', '', trim($baseProfileId)) ?? trim($baseProfileId))
            : '';

        $baseSource = null;
        if ($cleanBaseId !== '') {
            foreach ($profiles as $p) {
                if ((string) ($p['id'] ?? '') === $cleanBaseId || (string) ($p['title'] ?? '') === $cleanBaseId) {
                    $baseSource = $p;
                    break;
                }
            }
        }
        if ($baseSource === null) {
            foreach ($profiles as $p) {
                if (!empty($p['is_default'])) {
                    $baseSource = $p;
                    break;
                }
            }
        }
        if ($baseSource === null && !empty($profiles)) {
            $baseSource = $profiles[0];
        }
        if ($baseSource === null) {
            $baseSource = $baseline;
        }

        $clonedRaw = [
            'focus_mm'        => ((float) ($baseSource['focus_mm'] ?? 0)) > 0 ? (float) $baseSource['focus_mm'] : $baseline['focus_mm'],
            'power_percent'   => ((int) ($baseSource['power_percent'] ?? 0)) > 0 ? (int) $baseSource['power_percent'] : $baseline['power_percent'],
            'dot_duration_us' => ((int) ($baseSource['dot_duration_us'] ?? 0)) > 0 ? (int) $baseSource['dot_duration_us'] : $baseline['dot_duration_us'],
            'speed_mms'       => ((int) ($baseSource['speed_mms'] ?? 0)) > 0 ? (int) $baseSource['speed_mms'] : $baseline['speed_mms'],
            'frequency_khz'   => ((int) ($baseSource['frequency_khz'] ?? 0)) > 0 ? (int) $baseSource['frequency_khz'] : $baseline['frequency_khz'],
            'dpi'             => ((int) ($baseSource['dpi'] ?? 0)) > 0 ? (int) $baseSource['dpi'] : $baseline['dpi'],
            'passes'          => ((int) ($baseSource['passes'] ?? 0)) > 0 ? (int) $baseSource['passes'] : $baseline['passes'],
            'element_type'    => $baseSource['element_type'] ?? $baseline['element_type'],
            'engrave_tab'     => $baseSource['engrave_tab'] ?? $baseline['engrave_tab'],
            'preset_mode'     => $baseSource['preset_mode'] ?? $baseline['preset_mode'],
            'engrave_mode'    => $baseSource['engrave_mode'] ?? $baseline['engrave_mode'],
            'vector_note'     => $baseSource['vector_note'] ?? $baseline['vector_note'],
            'bitmap_mode'     => self::ditherModeToBitmapModeLabel($cleanStudio['dither_mode']),
        ];
        foreach (['equipment_key', 'process_mode', 'material'] as $optKey) {
            if (!empty($baseSource[$optKey])) {
                $clonedRaw[$optKey] = $baseSource[$optKey];
            }
        }

        $sanitizedClone = $this->sanitizeLaserParameterInput($clonedRaw, $baseline);
        $sanitizedClone['bitmap_mode'] = self::ditherModeToBitmapModeLabel($cleanStudio['dither_mode']);

        $matchedIndex = null;
        $existingIds = [];
        foreach ($profiles as $idx => $ep) {
            $epId = trim((string) ($ep['id'] ?? ''));
            if ($epId !== '') {
                $existingIds[$epId] = true;
            }
            if (mb_strtolower(trim((string) ($ep['title'] ?? ''))) === mb_strtolower($cleanTitle)) {
                $matchedIndex = $idx;
            }
        }

        if ($matchedIndex !== null) {
            $targetId = (string) ($profiles[$matchedIndex]['id'] ?? ('prof_' . Str::lower(Str::random(10))));
        } else {
            do {
                $targetId = 'prof_' . Str::lower(Str::random(10));
            } while (isset($existingIds[$targetId]));
        }

        $targetItemData = [
            'id'              => $targetId,
            'title'           => $cleanTitle,
            'is_default'      => $makeDefault || ($matchedIndex !== null && !empty($profiles[$matchedIndex]['is_default'])),
            'focus_mm'        => $sanitizedClone['focus_mm'],
            'element_type'    => $sanitizedClone['element_type'],
            'engrave_tab'     => $sanitizedClone['engrave_tab'],
            'preset_mode'     => $sanitizedClone['preset_mode'],
            'dot_duration_us' => $sanitizedClone['dot_duration_us'],
            'power_percent'   => $sanitizedClone['power_percent'],
            'dpi'             => $sanitizedClone['dpi'],
            'frequency_khz'   => $sanitizedClone['frequency_khz'],
            'speed_mms'       => $sanitizedClone['speed_mms'],
            'passes'          => $sanitizedClone['passes'],
            'bitmap_mode'     => $sanitizedClone['bitmap_mode'],
            'engrave_mode'    => $sanitizedClone['engrave_mode'],
            'vector_note'     => $sanitizedClone['vector_note'],
        ];
        foreach (['equipment_key', 'process_mode', 'material'] as $optKey) {
            if (!empty($clonedRaw[$optKey])) {
                $targetItemData[$optKey] = $sanitizedClone[$optKey];
            }
        }

        $targetProfile = Product::normalizeLaserProfileItem(
            $targetItemData,
            $matchedIndex ?? count($profiles),
            $settings
        );
        $targetProfile['bitmap_mode'] = self::ditherModeToBitmapModeLabel($cleanStudio['dither_mode']);
        $targetProfile['photo_studio'] = $cleanStudio;

        if ($makeDefault) {
            foreach ($profiles as &$ep) {
                $ep['is_default'] = false;
            }
            unset($ep);
            $targetProfile['is_default'] = true;
            if (!empty($clonedRaw['equipment_key'])) {
                $settings['xtool_equipment_key'] = $sanitizedClone['equipment_key'];
            }
            if (!empty($clonedRaw['process_mode'])) {
                $settings['xtool_process_mode'] = $sanitizedClone['process_mode'];
            }
            if (!empty($clonedRaw['material'])) {
                $settings['xtool_material'] = $sanitizedClone['material'];
            }
        }

        if ($matchedIndex !== null) {
            $profiles[$matchedIndex] = $targetProfile;
        } else {
            $profiles[] = $targetProfile;
        }

        $profilesSnapshot = array_values($profiles);
        $settings['xtool_profiles'] = $profilesSnapshot;
        $settings = Product::syncLaserProfilesAndPrimarySettings($settings, false);
        $settings = self::restorePhotoStudioIntoSyncedSettings($profilesSnapshot, $settings);

        $product->configurator_settings = $settings;
        $product->save();

        $freshProduct = $product->fresh() ?? $product;
        $savedProfiles = $this->loadProductProfilesPreservingPhotoStudio($freshProduct);
        $createdProfile = collect($savedProfiles)->firstWhere('id', $targetId)
            ?? (end($savedProfiles) ?: $targetProfile);

        return [
            'profiles'        => $savedProfiles,
            'created_profile' => $createdProfile,
        ];
    }

    /**
     * Lädt ein am Produkt hinterlegtes Laserprofil aktiv auf eine Bestellposition.
     */
    public function applyProductLaserProfileToOrderItem(OrderOrderItem $item, string $side, string $profileId): ?array
    {
        $item->loadMissing('product');
        if (!$item->product) {
            return null;
        }

        $profiles = $item->product->getLaserProfiles();
        $cleanProfileId = preg_replace('/^profile:/i', '', trim($profileId)) ?? trim($profileId);

        $found = null;
        foreach ($profiles as $prof) {
            if ((string) ($prof['id'] ?? '') === $cleanProfileId || (string) ($prof['title'] ?? '') === $cleanProfileId) {
                $found = $prof;
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        $payload = [
            'focus_mm'        => $found['focus_mm'],
            'element_type'    => $found['element_type'] ?? 'Bild',
            'engrave_tab'     => $found['engrave_tab'] ?? 'Gravieren',
            'preset_mode'     => $found['preset_mode'] ?? 'Benutzerdefiniert',
            'dot_duration_us' => $found['dot_duration_us'],
            'power_percent'   => $found['power_percent'],
            'speed_mms'       => $found['speed_mms'] ?? 200,
            'dpi'             => $found['dpi'],
            'passes'          => $found['passes'] ?? 1,
            'frequency_khz'   => $found['frequency_khz'],
            'bitmap_mode'     => $found['bitmap_mode'] ?? 'Jarvis',
            'engrave_mode'    => $found['engrave_mode'] ?? 'Bidirektional',
            'vector_note'     => $found['vector_note'] ?? '',
        ];

        foreach (['equipment_key', 'process_mode', 'material'] as $optKey) {
            if (!empty($found[$optKey])) {
                $payload[$optKey] = $found[$optKey];
            }
        }

        return $this->saveOrderItemLaserParameterOverrides($item, $side, $payload, false);
    }

    /**
     * Kanonischer Multi-Profil-Katalog für alle Seelenfunke Laser-Produkte inkl. echter Lamellenrost-Höhen
     * (19,5 mm Oberfläche auf schwarzem Lamellenrost, +2,0 mm Defokus = 21,5 mm für 100 kHz Schwarz-Anlassgravur;
     * unantastbare 33,1 mm / 32,8 mm Master-Referenz für den Seelen Kristall).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getDefaultProductLaserProfilesCatalog(): array
    {
        $goldSteelProfiles = [
            [
                'id'              => 'prof_schwarz_100khz',
                'title'           => 'Schwarz (Anlass-Gravur 100 kHz)',
                'is_default'      => true,
                'focus_mm'        => 21.5,
                'element_type'    => 'Bild',
                'engrave_tab'     => 'Gravieren',
                'preset_mode'     => 'Benutzerdefiniert',
                'dot_duration_us' => 1100,
                'power_percent'   => 95,
                'dpi'             => 1270,
                'frequency_khz'   => 100,
                'speed_mms'       => 25,
                'passes'          => 1,
                'bitmap_mode'     => 'Schwellenwert',
                'engrave_mode'    => 'Bidirektional',
                'vector_note'     => 'Gravieren · 95 % · 25 mm/s · 1000 Linien/cm · 100 kHz',
            ],
            [
                'id'              => 'prof_cockpit_sweetspot_65khz',
                'title'           => 'Cockpit-Sweetspot 65 kHz',
                'is_default'      => false,
                'focus_mm'        => 20.9,
                'element_type'    => 'Bild',
                'engrave_tab'     => 'Gravieren',
                'preset_mode'     => 'Benutzerdefiniert',
                'dot_duration_us' => 210,
                'power_percent'   => 45,
                'dpi'             => 800,
                'frequency_khz'   => 65,
                'speed_mms'       => 110,
                'passes'          => 1,
                'bitmap_mode'     => 'Jarvis',
                'engrave_mode'    => 'Bidirektional',
                'vector_note'     => 'Gravieren · 42 % · 110 mm/s · 330 Linien/cm · 65 kHz',
            ],
            [
                'id'              => 'prof_weiss_silber_kontrast',
                'title'           => 'Weiß / Silber-Kontrast',
                'is_default'      => false,
                'focus_mm'        => 19.5,
                'element_type'    => 'Bild',
                'engrave_tab'     => 'Gravieren',
                'preset_mode'     => 'Benutzerdefiniert',
                'dot_duration_us' => 210,
                'power_percent'   => 45,
                'dpi'             => 600,
                'frequency_khz'   => 40,
                'speed_mms'       => 550,
                'passes'          => 1,
                'bitmap_mode'     => 'Jarvis',
                'engrave_mode'    => 'Bidirektional',
                'vector_note'     => 'Gravieren · 28 % · 550 mm/s · 300 Linien/cm · 40 kHz',
            ],
        ];

        $pawProfiles = array_merge($goldSteelProfiles, [[
            'id'              => 'prof_schwarz_bodenplatte_3_8',
            'title'           => 'Schwarz · Bodenplatte ohne Lamellenrost (3,8 mm)',
            'is_default'      => false,
            'focus_mm'        => 3.8,
            'element_type'    => 'Bild',
            'engrave_tab'     => 'Gravieren',
            'preset_mode'     => 'Benutzerdefiniert',
            'dot_duration_us' => 1100,
            'power_percent'   => 95,
            'dpi'             => 1270,
            'frequency_khz'   => 100,
            'speed_mms'       => 25,
            'passes'          => 1,
            'bitmap_mode'     => 'Schwellenwert',
            'engrave_mode'    => 'Bidirektional',
            'vector_note'     => 'Gravieren · 95 % · 25 mm/s · 1000 Linien/cm · 100 kHz',
        ]]);

        $heartProfiles = array_merge($goldSteelProfiles, [[
            'id'              => 'prof_massiv_4mm_lamellenrost',
            'title'           => 'Schwarz · Massiv 4 mm Lamellenrost (+2,0 mm Defokus)',
            'is_default'      => false,
            'focus_mm'        => 23.7,
            'element_type'    => 'Bild',
            'engrave_tab'     => 'Gravieren',
            'preset_mode'     => 'Benutzerdefiniert',
            'dot_duration_us' => 1100,
            'power_percent'   => 95,
            'dpi'             => 1270,
            'frequency_khz'   => 100,
            'speed_mms'       => 20,
            'passes'          => 1,
            'bitmap_mode'     => 'Schwellenwert',
            'engrave_mode'    => 'Bidirektional',
            'vector_note'     => 'Gravieren · 98 % · 20 mm/s · 1000 Linien/cm · 100 kHz',
        ]]);

        $talismanProfiles = array_merge($goldSteelProfiles, [[
            'id'              => 'prof_schwarz_bodenplatte_4_2',
            'title'           => 'Schwarz · Bodenplatte ohne Lamellenrost (4,2 mm)',
            'is_default'      => false,
            'focus_mm'        => 4.2,
            'element_type'    => 'Bild',
            'engrave_tab'     => 'Gravieren',
            'preset_mode'     => 'Benutzerdefiniert',
            'dot_duration_us' => 1100,
            'power_percent'   => 95,
            'dpi'             => 1270,
            'frequency_khz'   => 100,
            'speed_mms'       => 25,
            'passes'          => 1,
            'bitmap_mode'     => 'Schwellenwert',
            'engrave_mode'    => 'Bidirektional',
            'vector_note'     => 'Gravieren · 95 % · 25 mm/s · 1000 Linien/cm · 100 kHz',
        ]]);

        return [
            'seelen-pfote-hundemarke' => $pawProfiles,
            'goldenes-seelen-herz'    => $heartProfiles,
            'seelen-talisman'         => $talismanProfiles,
            'seelen-anhaenger'        => [
                [
                    'id'              => 'prof_silber_kontrast_lamellenrost',
                    'title'           => 'Weiß / Silber-Kontrast · Lamellenrost',
                    'is_default'      => true,
                    'focus_mm'        => 19.9,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Referenz',
                    'dot_duration_us' => 280,
                    'power_percent'   => 85,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 450,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Zweidirektional',
                    'vector_note'     => 'Gravieren · 70 % · 450 mm/s · 300 Linien/cm · 40 kHz',
                    'photo_studio'    => [
                        'grayscale'     => true,
                        'invert'        => true,
                        'contrast'      => 20,
                        'brightness'    => 0,
                        'gamma'         => 1.00,
                        'highlight_cap' => 58,
                        'sharpness'     => 85,
                        'dither_mode'   => 'jarvis',
                        'threshold'     => 128,
                    ],
                ],
                [
                    'id'              => 'prof_rueckseite_widmung_feintext',
                    'title'           => 'Rückseite Widmung & Feintext · Lamellenrost',
                    'is_default'      => false,
                    'focus_mm'        => 19.9,
                    'element_type'    => 'Vektor',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 300,
                    'power_percent'   => 55,
                    'dpi'             => 500,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 700,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Schwellenwert',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 55 % · 700 mm/s · 450 Linien/cm · 40 kHz',
                ],
                [
                    'id'              => 'prof_flachbett_ohne_rost_1_8',
                    'title'           => 'Weiß / Silber-Kontrast · Bodenplatte (1,8 mm)',
                    'is_default'      => false,
                    'focus_mm'        => 1.8,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 280,
                    'power_percent'   => 85,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 450,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 70 % · 450 mm/s · 300 Linien/cm · 40 kHz',
                    'photo_studio'    => [
                        'grayscale'     => true,
                        'invert'        => true,
                        'contrast'      => 20,
                        'brightness'    => 0,
                        'gamma'         => 1.00,
                        'highlight_cap' => 58,
                        'sharpness'     => 85,
                        'dither_mode'   => 'jarvis',
                        'threshold'     => 128,
                    ],
                ],
            ],
            'seelen-kristall' => [
                [
                    'id'              => 'prof_kristall_seidenmatt_33_1',
                    'title'           => 'Außen Seidenmatt-Frost (33,1 mm)',
                    'is_default'      => true,
                    'focus_mm'        => 33.1,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 450,
                    'power_percent'   => 100,
                    'dpi'             => 500,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 80,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Zweidirektional',
                    'vector_note'     => 'Gravieren · 60 % · 80 mm/s · 300 Linien/cm · 40 kHz',
                ],
                [
                    'id'              => 'prof_kristall_master_32_8',
                    'title'           => 'Master-Referenz Brennpunkt-Doku (32,8 mm)',
                    'is_default'      => false,
                    'focus_mm'        => 32.8,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 500,
                    'power_percent'   => 85,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 80,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Zweidirektional',
                    'vector_note'     => 'Gravieren · 60 % · 80 mm/s · 300 Linien/cm · 40 kHz',
                ],
                [
                    'id'              => 'prof_kristall_innengravur_3d',
                    'title'           => 'Innengravur 3D Subsurface (15,6 mm)',
                    'is_default'      => false,
                    'equipment_key'   => 'inner_3d',
                    'process_mode'    => 'Innengravur - 3D-Miniatur',
                    'focus_mm'        => 15.6,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 220,
                    'power_percent'   => 80,
                    'dpi'             => 600,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 80,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Innengravur · 80 % · 220 µs · 600 DPI · 40 kHz',
                ],
            ],
            'id-armband-silber-herz' => [
                [
                    'id'              => 'prof_silber_schaumstoffmatte',
                    'title'           => '925er Silber · Schaumstoffmatte (2,2 mm)',
                    'is_default'      => true,
                    'focus_mm'        => 2.2,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 240,
                    'power_percent'   => 75,
                    'dpi'             => 1000,
                    'frequency_khz'   => 50,
                    'speed_mms'       => 180,
                    'passes'          => 2,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 75 % · 180 mm/s · 400 Linien/cm · 50 kHz · 2x',
                ],
                [
                    'id'              => 'prof_silber_lamellenrost',
                    'title'           => '925er Silber · Lamellenrost (+1,0 mm Defokus)',
                    'is_default'      => false,
                    'focus_mm'        => 20.5,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 240,
                    'power_percent'   => 75,
                    'dpi'             => 1000,
                    'frequency_khz'   => 50,
                    'speed_mms'       => 180,
                    'passes'          => 2,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 75 % · 180 mm/s · 400 Linien/cm · 50 kHz · 2x',
                ],
                [
                    'id'              => 'prof_silber_kreuzschraffur',
                    'title'           => '925er Silber · Tiefkontrast 80 kHz',
                    'is_default'      => false,
                    'focus_mm'        => 2.0,
                    'element_type'    => 'Vektor',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 240,
                    'power_percent'   => 85,
                    'dpi'             => 1000,
                    'frequency_khz'   => 80,
                    'speed_mms'       => 40,
                    'passes'          => 2,
                    'bitmap_mode'     => 'Schwellenwert',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 85 % · 40 mm/s · 1000 Linien/cm · 80 kHz · 2x',
                ],
            ],
            'id-armband-gold-herz' => [
                [
                    'id'              => 'prof_vermeil_schaumstoffmatte',
                    'title'           => 'Gold-Vermeil · Schon-Oxidation (2,5 mm)',
                    'is_default'      => true,
                    'focus_mm'        => 2.5,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 180,
                    'power_percent'   => 34,
                    'dpi'             => 1000,
                    'frequency_khz'   => 70,
                    'speed_mms'       => 130,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 34 % · 130 mm/s · 400 Linien/cm · 70 kHz',
                ],
                [
                    'id'              => 'prof_vermeil_lamellenrost',
                    'title'           => 'Gold-Vermeil · Lamellenrost (+1,3 mm Defokus)',
                    'is_default'      => false,
                    'focus_mm'        => 20.8,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 180,
                    'power_percent'   => 34,
                    'dpi'             => 1000,
                    'frequency_khz'   => 70,
                    'speed_mms'       => 130,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 34 % · 130 mm/s · 400 Linien/cm · 70 kHz',
                ],
                [
                    'id'              => 'prof_vermeil_kontrast_90khz',
                    'title'           => 'Gold-Vermeil · Dunkelkontrast 90 kHz',
                    'is_default'      => false,
                    'focus_mm'        => 2.4,
                    'element_type'    => 'Vektor',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 180,
                    'power_percent'   => 55,
                    'dpi'             => 1000,
                    'frequency_khz'   => 90,
                    'speed_mms'       => 60,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Schwellenwert',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 55 % · 60 mm/s · 1000 Linien/cm · 90 kHz',
                ],
            ],
            'weizenglas-personalisiert' => [
                [
                    'id'              => 'prof_ra3_seidenmatt',
                    'title'           => 'RA3 Walze · Seidenmatt-Frost (Ø 74 mm)',
                    'is_default'      => true,
                    'equipment_key'   => 'rotary_ra3',
                    'process_mode'    => 'Walze - Rotationsaufsatz',
                    'focus_mm'        => 74.0,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 260,
                    'power_percent'   => 55,
                    'dpi'             => 400,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 380,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 55 % · 380 mm/s · 300 Linien/cm · 40 kHz',
                ],
                [
                    'id'              => 'prof_ra3_kraeftig_frost',
                    'title'           => 'RA3 Walze · Kräftig-Frost',
                    'is_default'      => false,
                    'equipment_key'   => 'rotary_ra3',
                    'process_mode'    => 'Walze - Rotationsaufsatz',
                    'focus_mm'        => 74.0,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 650,
                    'power_percent'   => 92,
                    'dpi'             => 500,
                    'frequency_khz'   => 40,
                    'speed_mms'       => 125,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 80 % · 125 mm/s · 300 Linien/cm · 40 kHz',
                ],
            ],
            'geschenkgutschein' => [
                [
                    'id'              => 'prof_gutschein_schwarz_rost',
                    'title'           => 'Gutscheinkarte Schwarz · Lamellenrost (19,9 mm)',
                    'is_default'      => true,
                    'focus_mm'        => 19.9,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 220,
                    'power_percent'   => 45,
                    'dpi'             => 500,
                    'frequency_khz'   => 55,
                    'speed_mms'       => 850,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Schwellenwert',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 45 % · 850 mm/s · 450 Linien/cm · 55 kHz',
                ],
                [
                    'id'              => 'prof_gutschein_gold_rost',
                    'title'           => 'Gutscheinkarte Gold · Lamellenrost (20,3 mm)',
                    'is_default'      => false,
                    'focus_mm'        => 20.3,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 220,
                    'power_percent'   => 52,
                    'dpi'             => 800,
                    'frequency_khz'   => 50,
                    'speed_mms'       => 450,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Jarvis',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 52 % · 450 mm/s · 300 Linien/cm · 50 kHz',
                ],
                [
                    'id'              => 'prof_gutschein_schwarz_bodenplatte',
                    'title'           => 'Gutscheinkarte Schwarz · Bodenplatte (0,8 mm)',
                    'is_default'      => false,
                    'focus_mm'        => 0.8,
                    'element_type'    => 'Bild',
                    'engrave_tab'     => 'Gravieren',
                    'preset_mode'     => 'Benutzerdefiniert',
                    'dot_duration_us' => 220,
                    'power_percent'   => 45,
                    'dpi'             => 500,
                    'frequency_khz'   => 55,
                    'speed_mms'       => 850,
                    'passes'          => 1,
                    'bitmap_mode'     => 'Schwellenwert',
                    'engrave_mode'    => 'Bidirektional',
                    'vector_note'     => 'Gravieren · 45 % · 850 mm/s · 450 Linien/cm · 55 kHz',
                ],
            ],
        ];
    }

    /**
     * Synchronisiert die kanonischen Multi-Laserprofile für ein Produkt-Settings-Array (für Seeder & Migration)
     * und aktualisiert automatisch die primären xtool_*-Felder anhand des Standard-Profils.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function applySeededMultiLaserProfilesToSettings(string $slug, array $settings): array
    {
        $catalog = self::getDefaultProductLaserProfilesCatalog();
        $tplKey = trim((string) ($settings['template_package'] ?? ''));
        $profiles = $catalog[$slug] ?? ($tplKey !== '' && isset($catalog[$tplKey]) ? $catalog[$tplKey] : null);
        $existingProfiles = isset($settings['xtool_profiles']) && is_array($settings['xtool_profiles'])
            ? array_values(array_filter($settings['xtool_profiles'], 'is_array'))
            : [];

        if ($profiles !== null) {
            $existingById = [];
            $existingByTitle = [];
            foreach ($existingProfiles as $ep) {
                $epId = trim((string) ($ep['id'] ?? ''));
                $epTitle = mb_strtolower(trim((string) ($ep['title'] ?? '')));
                if ($epId !== '') {
                    $existingById[$epId] = $ep;
                }
                if ($epTitle !== '') {
                    $existingByTitle[$epTitle] = $ep;
                }
            }

            $catalogIds = [];
            $catalogTitles = [];
            $mergedProfiles = [];

            foreach ($profiles as $catProf) {
                $cId = trim((string) ($catProf['id'] ?? ''));
                $cTitle = mb_strtolower(trim((string) ($catProf['title'] ?? '')));
                if ($cId !== '') {
                    $catalogIds[$cId] = true;
                }
                if ($cTitle !== '') {
                    $catalogTitles[$cTitle] = true;
                }

                $matchedExisting = ($cId !== '' && isset($existingById[$cId]))
                    ? $existingById[$cId]
                    : (($cTitle !== '' && isset($existingByTitle[$cTitle])) ? $existingByTitle[$cTitle] : null);

                if ($matchedExisting !== null && !empty($matchedExisting['photo_studio']) && is_array($matchedExisting['photo_studio'])) {
                    $catProf['photo_studio'] = self::sanitizeProfilePhotoStudioParams($matchedExisting['photo_studio']);
                    $catProf['bitmap_mode'] = self::ditherModeToBitmapModeLabel($catProf['photo_studio']['dither_mode']);
                } elseif (!empty($catProf['photo_studio']) && is_array($catProf['photo_studio'])) {
                    $catProf['photo_studio'] = self::sanitizeProfilePhotoStudioParams($catProf['photo_studio']);
                }

                $mergedProfiles[] = $catProf;
            }

            // Vom Admin selbst angelegte Zusatzprofile (eigene id und eigener title) inkl. photo_studio bewahren
            foreach ($existingProfiles as $ep) {
                $epId = trim((string) ($ep['id'] ?? ''));
                $epTitle = mb_strtolower(trim((string) ($ep['title'] ?? '')));
                $isCatalogEntry = ($epId !== '' && isset($catalogIds[$epId]))
                    || ($epTitle !== '' && isset($catalogTitles[$epTitle]));
                if ($isCatalogEntry) {
                    continue;
                }

                if (!empty($ep['photo_studio']) && is_array($ep['photo_studio'])) {
                    $ep['photo_studio'] = self::sanitizeProfilePhotoStudioParams($ep['photo_studio']);
                }
                if (!empty($ep['is_default'])) {
                    foreach ($mergedProfiles as &$mp) {
                        $mp['is_default'] = false;
                    }
                    unset($mp);
                }

                $mergedProfiles[] = $ep;
            }

            $settings['xtool_profiles'] = $mergedProfiles;
            $settings = Product::syncLaserProfilesAndPrimarySettings($settings, false);
            $settings = self::restorePhotoStudioIntoSyncedSettings($mergedProfiles, $settings);

            // Vektor-Begleitfelder bei den 3 Gold-Edelstahl-Marken synchron zum 100 kHz Standard-Profil halten
            if (in_array($slug, ['seelen-pfote-hundemarke', 'goldenes-seelen-herz', 'seelen-talisman'], true)) {
                $settings['xtool_handbook_preset']      = 'lamellenrost_schwarz_100khz';
                $settings['xtool_vector_focus_mm']      = 21.5;
                $settings['xtool_vector_power_percent'] = 95;
                $settings['xtool_vector_speed_mms']     = 25;
                $settings['xtool_vector_frequency_khz'] = 100;
                $settings['xtool_vector_lpc']           = 1000;
                $settings['xtool_vector_dpi']           = 2540;
                $settings['xtool_vector_passes']        = 1;
                $settings['xtool_lpc']                  = 1000;
            }
        } else {
            $settings = Product::syncLaserProfilesAndPrimarySettings($settings, false);
            $settings = self::restorePhotoStudioIntoSyncedSettings($existingProfiles, $settings);
        }

        return $settings;
    }

    /**
     * Übersetzt die Seelenfunke Materialbezeichnung in die offizielle xTool Studio Material-ID (F2 Ultra UV).
     */
    public static function resolveXtoolMaterialId(string $materialLabel): int
    {
        $m = mb_strtolower(trim($materialLabel));
        if (str_contains($m, 'glas') || str_contains($m, 'kristall') || str_contains($m, 'k9')) {
            return 4064; // Klares Glas / K9 Kristallglas
        }
        if (str_contains($m, 'alu') || str_contains($m, 'visitenkarte')) {
            return 985; // Metall-Visitenkarte / Eloxiertes Aluminium
        }
        if (str_contains($m, 'schiefer') || str_contains($m, 'stein') || str_contains($m, 'keramik') || str_contains($m, 'untersetzer')) {
            return 2514; // Stein-Untersetzer / Naturschiefer
        }
        return 1098; // Edelstahl / Messing / Holz / Metall / Leder / Standard-UV-Oberfläche
    }

    /**
     * Übersetzt das Equipment / den Prozessmodus in den xTool Studio activeMode-Key.
     */
    public static function resolveXtoolActiveMode(string $equipmentKey, string $processMode = ''): string
    {
        $normalizedKey = self::normalizeEquipmentKey($equipmentKey, $processMode);

        return match ($normalizedKey) {
            'rotary_ra3'     => 'LASER_CYLINDER',
            'conveyor_batch' => 'LASER_CONVEYOR_FEEDER',
            'inner_3d'       => 'INNER_LASER',
            'curved_surface' => 'CURVED_PROCESS',
            default          => 'LASER_PLANE',
        };
    }

    /**
     * Übersetzt den deutschen Bitmap-Modus aus Schritt 4.2 in den internen xTool Studio Wert.
     */
    public static function resolveXtoolBitmapMode(string $bitmapModeLabel): string
    {
        $b = mb_strtolower(trim($bitmapModeLabel));
        if (str_contains($b, 'jarvis') || str_contains($b, 'raster')) {
            return 'jarvis';
        }
        if (str_contains($b, 'stucki')) {
            return 'stucki';
        }
        if (str_contains($b, 'floyd')) {
            return 'floyd';
        }
        if (str_contains($b, 'atkinson')) {
            return 'atkinson';
        }
        if (str_contains($b, 'schwellenwert') || str_contains($b, 'threshold')) {
            return 'threshold';
        }
        if (str_contains($b, 'konturgravur') || str_contains($b, 'emboss') || str_contains($b, 'relief')) {
            return 'emboss';
        }
        return 'greyscale';
    }

    /**
     * Übersetzt die Gravur-Richtung in den xTool Studio Scan-Modus ('zMode' = Bidirektional, 'singleMode' = Unidirektional).
     */
    public static function resolveXtoolScanMode(string $engraveModeLabel): string
    {
        $em = mb_strtolower(trim($engraveModeLabel));
        if (str_contains($em, 'uni') || str_contains($em, 'eindirekt') || str_contains($em, 'single')) {
            return 'singleMode';
        }

        return 'zMode';
    }

    /**
     * Erzeugt den rotierbaren 4-Punkt-Rahmen-GCode (border.gcode) für die blaue Laser-Rahmen-Vorschau (Framing) am xTool F2 Ultra UV.
     */
    public function generateBorderGcode(float $centerXMm, float $centerYMm, float $widthMm, float $heightMm, float $angleDeg = 0.0): string
    {
        $halfW = max(1.0, $widthMm / 2.0);
        $halfH = max(1.0, $heightMm / 2.0);
        $rad = deg2rad($angleDeg);
        $cos = cos($rad);
        $sin = sin($rad);

        $localCorners = [
            [-$halfW, -$halfH],
            [$halfW, -$halfH],
            [$halfW, $halfH],
            [-$halfW, $halfH],
        ];

        $pts = [];
        foreach ($localCorners as [$lx, $ly]) {
            $rx = $centerXMm + ($lx * $cos) - ($ly * $sin);
            $ry = $centerYMm + ($lx * $sin) + ($ly * $cos);
            $pts[] = [
                'x' => number_format(max(0.0, min(self::BED_WIDTH_MM, $rx)), 3, '.', ''),
                'y' => number_format(max(0.0, min(self::BED_HEIGHT_MM, $ry)), 3, '.', ''),
            ];
        }

        $lines = [
            '# GS009-4 HEAD',
            'G0 F180000',
            'M4 S0',
            'G1 F180000',
            'M114 S1',
            '',
            '# GS009-4 VECTOR HEAD',
            '# motion_start',
            'G21',
            'G90',
            '# blockConfig={"powerFactor":0.133,"isVector":true,"crossDot":false}',
            "G0X{$pts[0]['x']}Y{$pts[0]['y']}F1440000",
            "G1X{$pts[1]['x']}Y{$pts[1]['y']}S133F1440000",
            "G1X{$pts[2]['x']}Y{$pts[2]['y']}",
            "G1X{$pts[3]['x']}Y{$pts[3]['y']}",
            "G1X{$pts[0]['x']}Y{$pts[0]['y']}",
            '# GS009-4 VECTOR TAIL',
            '# motion_end',
            '# END',
            '# GS009-4 TAIL',
            'G90',
            'G0 S0',
            'G0 F180000',
            'G1 F180000',
            'M536 U0',
            'M6 P1',
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * Generiert ein sofort in xTool Studio v1.10.15 (F2 Ultra UV / GS009-CLASS-4) lauffähiges
     * .xcs Projektarchiv (xcs-workspace-v2 ZIP) inkl. aller Produkt-Laserwerte, Fokus-Höhe,
     * hochauflösender Gravur-Bitmap, Stapelverarbeitungs-Metadaten und cyanfarbener 1:1 Geister-Kontur (Ignorieren).
     *
     * @return array{bytes: string, filename: string, center_x_mm: float, center_y_mm: float, angle_deg: float, width_mm: float, height_mm: float}
     */
    public function generateXcsProjectBytes(
        $itemId,
        string $side = 'front',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        ?float $widthMm = null,
        ?float $heightMm = null
    ): array {
        $exportService = app(FileDownloadService::class);
        $payload = $exportService->buildLaserDesignPayload($itemId, $side, true);

        $item = $payload['item'];
        $product = $payload['product'];
        $laserSummary = $this->resolveEffectiveLaserParameters($item, $side);

        $savedCoords = (is_object($item) && is_array($item->configuration ?? null))
            ? ($item->configuration['laser_cockpit_coordinates'][$side] ?? [])
            : [];
        if ($centerXMm === null && isset($savedCoords['x_mm']) && is_numeric($savedCoords['x_mm'])) {
            $centerXMm = (float) $savedCoords['x_mm'];
        }
        if ($centerYMm === null && isset($savedCoords['y_mm']) && is_numeric($savedCoords['y_mm'])) {
            $centerYMm = (float) $savedCoords['y_mm'];
        }
        if (abs($angleDeg) < 0.01 && isset($savedCoords['angle_deg']) && is_numeric($savedCoords['angle_deg'])) {
            $angleDeg = (float) $savedCoords['angle_deg'];
        }

        if ($centerXMm === null || $centerYMm === null) {
            $detected = $this->detectBlankPositionOnBed($product, $item->configuration ?? [], $side);
            $centerXMm = $centerXMm ?? $detected['center_x_mm'];
            $centerYMm = $centerYMm ?? $detected['center_y_mm'];
            if (abs($angleDeg) < 0.01) {
                $angleDeg = $detected['angle_deg'];
            }
        }

        [$widthMm, $heightMm] = $this->resolveEffectiveJobDimensionsMm(
            $item,
            $side,
            (float) $payload['widthMm'],
            (float) $payload['heightMm'],
            $widthMm,
            $heightMm
        );
        $topLeftX = round($centerXMm - ($widthMm / 2.0), 4);
        $topLeftY = round($centerYMm - ($heightMm / 2.0), 4);

        // Sauberes Gravur-PNG mit echt transparentem Hintergrund (Alpha = 0 außerhalb des Motivs) erzeugen,
        // damit xTool Studio auf dem Live-Kamerabild keinen schwarzen oder weißen Kasten über den Rohling legt.
        // Multi-Layer: Kundenfoto (Foto-Bitmap-Layer) und Texte/Symbole (Vektor-Layer) werden getrennt eingebettet.
        $photoLayerSource = (string) ($payload['photoLayerPngBytes'] ?? '');
        $vectorLayerSource = (string) ($payload['vectorLayerPngBytes'] ?? '');
        $isMultiLayer = $photoLayerSource !== '' && $vectorLayerSource !== '';
        $engravePngBytes = $this->buildCleanTransparentXcsBitmapPng($isMultiLayer ? $photoLayerSource : (string) $payload['stageEngravePngBytes']);
        $opaqueThumbSourceBytes = $this->buildOpaqueWhiteBackedGrayscalePng((string) $payload['stageEngravePngBytes']);
        $thumbnailPngBytes = $this->buildXcsProjectThumbnailPng($opaqueThumbSourceBytes);

        $engraveSha256 = hash('sha256', $engravePngBytes);
        $resourceFileName = $engraveSha256 . '.png';
        $resourceRelPath = 'resources/' . $resourceFileName;
        $base64DataUrl = 'data:image/png;base64,' . base64_encode($engravePngBytes);

        $projectId = (string) Str::uuid();
        $canvasId = (string) Str::uuid();
        $engraveDisplayId = (string) Str::uuid();
        $ghostContourDisplayId = (string) Str::uuid();
        $bitmapSourceToken = (string) Str::uuid();

        $activeMode = self::resolveXtoolActiveMode(
            (string) ($laserSummary['equipment_key'] ?? 'flatbed_standard'),
            (string) ($laserSummary['process_mode'] ?? '')
        );
        $materialId = self::resolveXtoolMaterialId((string) ($laserSummary['material'] ?? 'Edelstahl'));
        $focusMm = max(0.1, min(150.0, (float) ($laserSummary['focus_mm'] ?: 3.0)));
        $elementType = (string) ($laserSummary['element_type'] ?? 'Bild');
        $engraveTab = (string) ($laserSummary['engrave_tab'] ?? 'Gravieren');
        $presetMode = (string) ($laserSummary['preset_mode'] ?? 'Benutzerdefiniert');
        $vectorNote = (string) ($laserSummary['vector_note'] ?? '');
        $processingType = self::resolveXtoolProcessingType($elementType, $engraveTab);
        $dotDurationUs = max(self::MIN_DOT_DURATION_US, min(self::MAX_DOT_DURATION_US, (int) ($laserSummary['dot_duration_us'] ?: 120)));
        $powerPercent = max(1, min(100, (int) ($laserSummary['power_percent'] ?: 70)));
        $speedMms = max(1, min(4000, (int) ($laserSummary['speed_mms'] ?: 200)));
        $dpi = max(50, min(2540, (int) ($laserSummary['dpi'] ?: 500)));
        $passes = max(1, min(20, (int) ($laserSummary['passes'] ?: 1)));
        $frequencyKhz = max(1, min(200, (int) ($laserSummary['frequency_khz'] ?: 60)));
        $bitmapMode = self::resolveXtoolBitmapMode((string) ($laserSummary['bitmap_mode'] ?? 'Graustufen'));
        $scanMode = self::resolveXtoolScanMode((string) ($laserSummary['engrave_mode'] ?? 'Bidirektional'));

        $orderNumber = (string) ($item->order->order_number ?? 'Auftrag');
        $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $orderNumber) ?: 'Auftrag';
        $sideLabel = $side === 'back' ? 'Rueckseite' : 'Vorderseite';
        $projectName = "SF-{$safeOrderNumber}-{$sideLabel}";

        // Getrennte Layer-Laserwerte (Foto-Bitmap vs. Text/Vektor) aus Produkt-Handbuchwerten & Schritt-1-Overrides
        $layerParams = $this->resolveLayerLaserParameters($item, $side, $laserSummary);
        $photoLayerInfo = is_array($payload['photoLayerInfo'] ?? null) ? $payload['photoLayerInfo'] : [];
        $hasOverrides = (bool) ($laserSummary['has_overrides'] ?? false);
        $isPhotoOnly = $photoLayerSource !== '' && $vectorLayerSource === '';
        $isVectorOnly = $photoLayerSource === '' && $vectorLayerSource !== '';
        if (($isMultiLayer || $isPhotoOnly) && !empty($photoLayerInfo['studio_adjusted']) && !empty($photoLayerInfo['studio_dither_mode'])) {
            if (!$hasOverrides) {
                $bitmapMode = self::resolveXtoolBitmapMode((string) $photoLayerInfo['studio_dither_mode']);
            }
        } elseif (($isMultiLayer || $isPhotoOnly) && !$hasOverrides && ($photoLayerInfo['class'] ?? '') === 'silhouette') {
            // Reines 1-Bit-Logo / Silhouette: randscharf per Schwellenwert statt Fehlerdiffusion
            $bitmapMode = 'threshold';
        } elseif (($isMultiLayer || $isPhotoOnly) && !$hasOverrides && ($photoLayerInfo['class'] ?? '') === 'halftone' && $bitmapMode === 'threshold') {
            $bitmapMode = 'jarvis';
        }
        if ($isVectorOnly && !$hasOverrides && $layerParams['has_product_vector_values']) {
            // Reine Text-/Symbol-Gravur: exakt die Vektor-Handbuchwerte des Produkts
            $vec = $layerParams['vector'];
            $powerPercent = max(1, min(100, (int) $vec['power_percent']));
            $speedMms = max(1, min(4000, (int) $vec['speed_mms']));
            $frequencyKhz = max(1, min(200, (int) $vec['frequency_khz']));
            $dpi = max(50, min(2540, (int) $vec['dpi']));
            $passes = max(1, min(20, (int) $vec['passes']));
            $dotDurationUs = self::equivalentDotDurationUs($dpi, $speedMms);
            $bitmapMode = 'threshold';
            $processingType = 'FILL_VECTOR_ENGRAVING';
        }

        // 1. Ghost-Contour SVG dPath im lokalen Koordinatensystem (0..widthMm, 0..heightMm)
        $ghostDPath = $payload['ghostContourDPath'];

        // 2. displays-0.json (mit Pflichtfeld canvasId für XcsSnapshotDeserializer sowie frei auswähl- & löschbaren Elementen)
        $displaysJson = [
            'canvasId' => $canvasId,
            'displays' => [
                [
                    'id'                            => $engraveDisplayId,
                    'name'                          => 'Seelenfunke Gravur (' . ($item->product_name ?? 'Unikat') . ')',
                    'type'                          => 'BITMAP',
                    'visible'                       => true,
                    'layerColor'                    => '#000000',
                    'x'                             => $topLeftX,
                    'y'                             => $topLeftY,
                    'angle'                         => round($angleDeg, 2),
                    'scale'                         => [
                        'x' => round($widthMm / max(1, $payload['stagePixelWidth']), 6),
                        'y' => round($heightMm / max(1, $payload['stagePixelHeight']), 6),
                    ],
                    'skew'                          => ['x' => 0, 'y' => 0],
                    'pivot'                         => ['x' => 0, 'y' => 0],
                    'localSkew'                     => ['x' => 0, 'y' => 0],
                    'offsetX'                       => $topLeftX,
                    'offsetY'                       => $topLeftY,
                    'lockRatio'                     => true,
                    'enableTransform'               => true,
                    'isClosePath'                   => false,
                    'zOrder'                        => 1,
                    'sourceId'                      => $engraveDisplayId,
                    'groupTag'                      => '',
                    'groupTags'                     => [],
                    'layerTag'                      => '#000000',
                    'fillColor'                     => '#FFFFFF',
                    'visibleState'                  => true,
                    'lockState'                     => false,
                    'isLock'                        => false,
                    'resourceOrigin'                => '',
                    'customData'                    => new \stdClass(),
                    'rootComponentId'               => '',
                    'minCanvasVersion'              => '0.0.0',
                    'width'                         => $widthMm,
                    'height'                        => $heightMm,
                    'base64'                        => $base64DataUrl,
                    'originWidth'                   => $payload['stagePixelWidth'],
                    'originHeight'                  => $payload['stagePixelHeight'],
                    'DPI'                           => $dpi,
                    'isFill'                        => true,
                    'filterList'                    => [],
                    'grayValue'                     => [0, 250],
                    'sharpness'                     => 50,
                    'brightness'                    => 0,
                    'contrast'                      => 0,
                    'saturation'                    => 0,
                    'temperature'                   => 0,
                    'tone'                          => 0,
                    'colorInverted'                 => false,
                    'colorInvertedFillTransparent'  => false,
                    'colorInvertedTransparentColor' => 'black',
                    'isGray'                        => true,
                    'opacity'                       => 1,
                    'bitmapSourceToken'             => $bitmapSourceToken,
                    'lineColor'                     => '#FF0000',
                    'stroke'                        => [
                        'color'     => 16711680,
                        'width'     => 0.1,
                        'alpha'     => 0,
                        'visible'   => false,
                        'cap'       => 'butt',
                        'join'      => 'miter',
                        'miterLimit'=> 4,
                        'alignment' => 0.5,
                    ],
                    'fill'                          => [
                        'color'   => 16777215,
                        'alpha'   => 0,
                        'visible' => false,
                    ],
                    'totalFrame'                    => 1,
                    'color'                         => [
                        'bright'     => 0,
                        'contrast'   => 0,
                        'saturation' => 0,
                        'whiteClip'  => 250,
                        'blackClip'  => 0,
                    ],
                    'isGrayscale'                   => true,
                    'isInvert'                      => false,
                    'resourcePath'                  => $resourceRelPath,
                ],
                [
                    'id'              => $ghostContourDisplayId,
                    'name'            => '1:1 Rohling Geister-Kontur (Ausrichthilfe / Nicht lasern)',
                    'type'            => 'PATH',
                    'visible'         => true,
                    'layerColor'      => '#00bcd4',
                    'x'               => $topLeftX,
                    'y'               => $topLeftY,
                    'angle'           => round($angleDeg, 2),
                    'scale'           => [
                        'x' => round($widthMm / max(0.001, (float) $payload['widthMm']), 6),
                        'y' => round($heightMm / max(0.001, (float) $payload['heightMm']), 6),
                    ],
                    'skew'            => ['x' => 0, 'y' => 0],
                    'pivot'           => ['x' => 0, 'y' => 0],
                    'localSkew'       => ['x' => 0, 'y' => 0],
                    'offsetX'         => $topLeftX,
                    'offsetY'         => $topLeftY,
                    'lockRatio'       => true,
                    'enableTransform' => true,
                    'isClosePath'     => true,
                    'zOrder'          => 2,
                    'sourceId'        => $ghostContourDisplayId,
                    'groupTag'        => '',
                    'groupTags'       => [],
                    'layerTag'        => '#00bcd4',
                    'fillColor'       => '#00bcd4',
                    'visibleState'    => true,
                    'lockState'       => false,
                    'isLock'          => false,
                    'resourceOrigin'  => '',
                    'customData'      => new \stdClass(),
                    'rootComponentId' => '',
                    'minCanvasVersion'=> '0.0.0',
                    'width'           => $widthMm,
                    'height'          => $heightMm,
                    'dPath'           => $ghostDPath,
                    'fillRule'        => 'nonzero',
                    'graphicX'        => 0,
                    'graphicY'        => 0,
                    'isCompoundPath'  => false,
                    'points'          => [],
                    'isFill'          => false,
                    'lineColor'       => '#00bcd4',
                ],
            ],
        ];

        // 2b. Multi-Layer: Text-/Vektor-Layer als eigenes, deckungsgleiches Element (identische x/y/angle/scale)
        $vectorDisplayId = null;
        $vectorPngBytes = '';
        $vectorSha256 = '';
        $vectorResourceRelPath = '';
        $vectorResourceFileName = '';
        if ($isMultiLayer) {
            $displaysJson['displays'][0]['name'] = 'Foto-Bitmap (' . ($item->product_name ?? 'Unikat') . ')';

            $vectorPngBytes = $this->buildCleanTransparentXcsBitmapPng($vectorLayerSource);
            $vectorSha256 = hash('sha256', $vectorPngBytes);
            $vectorResourceFileName = $vectorSha256 . '.png';
            $vectorResourceRelPath = 'resources/' . $vectorResourceFileName;
            $vectorDisplayId = (string) Str::uuid();

            $vectorDisplay = $displaysJson['displays'][0];
            $vectorDisplay['id'] = $vectorDisplayId;
            $vectorDisplay['sourceId'] = $vectorDisplayId;
            $vectorDisplay['name'] = 'Text & Symbole (Vektor-Layer)';
            $vectorDisplay['layerColor'] = '#1E40AF';
            $vectorDisplay['layerTag'] = '#1E40AF';
            $vectorDisplay['zOrder'] = 2;
            $vectorDisplay['base64'] = 'data:image/png;base64,' . base64_encode($vectorPngBytes);
            $vectorDisplay['DPI'] = (int) $layerParams['vector']['dpi'];
            $vectorDisplay['bitmapSourceToken'] = (string) Str::uuid();
            $vectorDisplay['resourcePath'] = $vectorResourceRelPath;

            $ghostDisplay = $displaysJson['displays'][1];
            $ghostDisplay['zOrder'] = 3;
            $displaysJson['displays'] = [$displaysJson['displays'][0], $vectorDisplay, $ghostDisplay];
        }

        // 3. canvases/<canvasId>.json (ohne BATCH_TEMPLATE-Sperre und ohne Zwangs-Gruppierung, inkl. chunkIndexes: [0])
        $canvasMetaJson = [
            'id'              => $canvasId,
            'title'           => $projectName,
            'layerData'       => [
                '#000000' => [
                    'name'    => 'Seelenfunke Lasergravur',
                    'order'   => 1,
                    'visible' => true,
                ],
                '#00bcd4' => [
                    'name'    => 'Geister-Kontur (Ignorieren)',
                    'order'   => 2,
                    'visible' => true,
                ],
            ],
            'groupData'       => new \stdClass(),
            'SHOW_RULER'      => true,
            'VISIBLE_GRID'    => true,
            'LOCK_CONVERT'    => false,
            'PARK_POSITION'   => 'topLeft',
            'SET_FOCUS_LIGHT' => false,
            'BYPASS_SHIELD'   => false,
            'rememberTyped'   => true,
            'isFill'          => false,
            'lineColor'       => 16730698,
            'fillColor'       => 16730698,
            'transformMode'   => false,
            'magnetism'       => true,
            'scale'           => 2.5,
            'gridOptions'     => ['size' => 'standard', 'unit' => 'mm'],
            'textPluginData'  => [],
            'auxiliaryLines'  => [],
            'chunkLayout'     => [
                'chunkIndexes' => [0],
                'file'         => 'displays-0.json',
                'count'        => 2,
            ],
        ];
        if ($isMultiLayer) {
            $canvasMetaJson['layerData'] = [
                '#000000' => [
                    'name'    => 'Foto-Bitmap (Kundenfoto)',
                    'order'   => 1,
                    'visible' => true,
                ],
                '#1E40AF' => [
                    'name'    => 'Text & Symbole (Vektor)',
                    'order'   => 2,
                    'visible' => true,
                ],
                '#00bcd4' => [
                    'name'    => 'Geister-Kontur (Ignorieren)',
                    'order'   => 3,
                    'visible' => true,
                ],
            ];
            $canvasMetaJson['chunkLayout']['count'] = 3;
        }

        // 4. devices/device-GS009-CLASS-4-1.json (100 % V2DeviceConfig-konform: id auf Canvas, profiles/patches/bindings als Listen)
        $profileKey = implode('::', [$activeMode, $materialId, 'BITMAP', $processingType, 'uv', 0]);
        $profileId = 'profile_' . substr(md5($profileKey), 0, 8);
        $bindingId = 'binding_' . substr(md5($engraveDisplayId . '::' . $profileId), 0, 8);
        $profileSource = $presetMode === 'Referenz' ? 'official' : 'custom';
        $profileValues = [
            'materialType'          => $materialId > 0 ? 'official' : 'customize',
            'materialId'            => $materialId,
            'ProcessingLightSource' => 'uv',
            'laserType'             => 'uv',
            'dotDuration'           => $dotDurationUs,
            'power'                 => $powerPercent,
            'speed'                 => $speedMms,
            'dpi'                   => $dpi,
            'repeat'                => $passes,
            'frequency'             => $frequencyKhz,
            'bitmapMode'            => $bitmapMode,
            'bitmapScanMode'        => $scanMode,
            'elementType'           => $elementType,
            'engraveTab'            => $engraveTab,
            'presetMode'            => $presetMode,
            'vectorNote'            => $vectorNote,
            'crossAngle'            => false,
            'enableKerf'            => false,
            'kerfDistance'          => 0,
        ];

        $deviceJson = [
            'id'         => self::DEVICE_ID_KEY,
            'deviceCode' => self::DEVICE_EXT_ID,
            'extId'      => self::DEVICE_EXT_ID,
            'extName'    => self::DEVICE_EXT_NAME,
            'snCode'     => self::DEVICE_SN_CODE,
            'power'      => 5,
            'processing' => [
                $canvasId => [
                    'id'         => $canvasId,
                    'activeMode' => $activeMode,
                    'modes'      => [
                        $activeMode => [
                            'data'              => [
                                'material'          => $materialId,
                                'lightSourceMode'   => 'uv',
                                'isProcessByLayer'  => false,
                                'pathPlanning'      => 'auto',
                                'fillPlanning'      => 'separate',
                                'scanDirection'     => 'topToBottom',
                                'enableOddEvenKerf' => false,
                                'perimeter'         => round($widthMm, 2),
                                'diameter'          => round($widthMm / M_PI, 2),
                                'thickness'         => $focusMm,
                                'isCheckFloorExist' => false,
                            ],
                            'profiles'          => [
                                [
                                    'id'                    => $profileId,
                                    'key'                   => $profileKey,
                                    'processingType'        => $processingType,
                                    'ProcessingLightSource' => 'uv',
                                    'values'                => $profileValues,
                                    'base'                  => [
                                        'source' => $profileSource,
                                        'values' => $profileValues,
                                    ],
                                ],
                            ],
                            'patches'           => [],
                            'bindings'          => [
                                [
                                    'bindingId'     => $bindingId,
                                    'baseProfileId' => $profileId,
                                    'displayIds'    => [$engraveDisplayId],
                                ],
                            ],
                            'ignoredDisplayIds' => [
                                $ghostContourDisplayId,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 4b. Multi-Layer: eigenes Vektor-Profil (Text & Symbole) mit den Vektor-Handbuchwerten + eigene Bindung
        $vectorProfileValues = null;
        if ($isMultiLayer && $vectorDisplayId !== null) {
            $modeRef = &$deviceJson['processing'][$canvasId]['modes'][$activeMode];
            $modeRef['profiles'][0]['values']['seelenfunkeLayer'] = 'photo_bitmap';
            $modeRef['profiles'][0]['base']['values']['seelenfunkeLayer'] = 'photo_bitmap';

            $vec = $layerParams['vector'];
            $vecDpi = max(50, min(2540, (int) $vec['dpi']));
            $vecSpeed = max(1, min(4000, (int) $vec['speed_mms']));
            $vectorProcessingType = 'FILL_VECTOR_ENGRAVING';
            $vectorProfileKey = implode('::', [$activeMode, $materialId, 'BITMAP', $vectorProcessingType, 'uv', 1]);
            $vectorProfileId = 'profile_' . substr(md5($vectorProfileKey), 0, 8);
            $vectorProfileValues = [
                'materialType'          => $materialId > 0 ? 'official' : 'customize',
                'materialId'            => $materialId,
                'ProcessingLightSource' => 'uv',
                'laserType'             => 'uv',
                'dotDuration'           => self::equivalentDotDurationUs($vecDpi, $vecSpeed),
                'power'                 => max(1, min(100, (int) $vec['power_percent'])),
                'speed'                 => $vecSpeed,
                'dpi'                   => $vecDpi,
                'density'               => (int) $vec['lpc'],
                'linesPerCm'            => (int) $vec['lpc'],
                'repeat'                => max(1, min(20, (int) $vec['passes'])),
                'frequency'             => max(1, min(200, (int) $vec['frequency_khz'])),
                'bitmapMode'            => 'threshold',
                'bitmapScanMode'        => self::resolveXtoolScanMode((string) $vec['engrave_mode']),
                'elementType'           => 'Vektor',
                'engraveTab'            => 'Gravieren',
                'presetMode'            => $presetMode,
                'vectorNote'            => $vectorNote,
                'focusHeight'           => round((float) $vec['focus_mm'], 2),
                'seelenfunkeLayer'      => 'text_vector',
                'crossAngle'            => false,
                'enableKerf'            => false,
                'kerfDistance'          => 0,
            ];
            $modeRef['profiles'][] = [
                'id'                    => $vectorProfileId,
                'key'                   => $vectorProfileKey,
                'processingType'        => $vectorProcessingType,
                'ProcessingLightSource' => 'uv',
                'values'                => $vectorProfileValues,
                'base'                  => [
                    'source' => $profileSource,
                    'values' => $vectorProfileValues,
                ],
            ];
            $modeRef['bindings'][] = [
                'bindingId'     => 'binding_' . substr(md5($vectorDisplayId . '::' . $vectorProfileId), 0, 8),
                'baseProfileId' => $vectorProfileId,
                'displayIds'    => [$vectorDisplayId],
            ];
            unset($modeRef);
        }

        // 5. project.json + resources/<sha256>.png.meta.json für OPFS-Rehydrierung in xTool Studio v1.10.15
        $nowMs = (int) round(microtime(true) * 1000);
        $resourceMetaJson = [
            'ref'      => $resourceRelPath,
            'metadata' => [
                'kind'      => 'binary',
                'size'      => strlen($engravePngBytes),
                'mimeType'  => 'image/png',
                'checksum'  => $engraveSha256,
                'canvasId'  => $canvasId,
                'displayId' => $engraveDisplayId,
                'source'    => [
                    'type'  => 'workspace',
                    'value' => $resourceFileName,
                ],
            ],
        ];

        $projectJson = [
            '__v2__'              => true,
            'projectId'           => $projectId,
            'projectName'         => $projectName,
            'version'             => '2.0.0',
            'appMinVersion'       => '1.3.0',
            'created'             => $nowMs,
            'modified'            => $nowMs,
            'activeCanvasId'      => $canvasId,
            'activeDeviceId'      => self::DEVICE_ID_KEY,
            'extId'               => self::DEVICE_EXT_ID,
            'extName'             => self::DEVICE_EXT_NAME,
            'deviceVersion'       => '1.4.25',
            'minimumPluginVersion'=> '0.0.0',
            'recommendList'       => [],
            'customData'          => [
                'seelenfunke_order_number' => $orderNumber,
                'seelenfunke_item_id'      => (string) $item->id,
                'seelenfunke_side'         => $side,
                'seelenfunke_focus_mm'     => $focusMm,
                'seelenfunke_speed_mms'    => $speedMms,
                'seelenfunke_material'     => (string) $laserSummary['material'],
                'seelenfunke_element_type' => $elementType,
                'seelenfunke_engrave_tab'  => $engraveTab,
                'seelenfunke_preset_mode'  => $presetMode,
                'seelenfunke_vector_note'  => $vectorNote,
            ],
            'meta'                => [],
            'modules'             => [
                'canvases' => [$canvasId],
                'devices'  => [self::DEVICE_ID_KEY],
            ],
            'resources'           => [
                $resourceRelPath => [
                    'sha256'    => $engraveSha256,
                    'size'      => strlen($engravePngBytes),
                    'mime'      => 'image/png',
                    'extension' => 'png',
                    'createdAt' => $nowMs,
                    'displays'  => [
                        $canvasId => [$engraveDisplayId],
                    ],
                ],
            ],
            'materialList'        => [],
        ];

        $photoJarvisPngBytes = null;
        if ($isMultiLayer && $vectorDisplayId !== null) {
            $projectJson['resources'][$vectorResourceRelPath] = [
                'sha256'    => $vectorSha256,
                'size'      => strlen($vectorPngBytes),
                'mime'      => 'image/png',
                'extension' => 'png',
                'createdAt' => $nowMs,
                'displays'  => [
                    $canvasId => [$vectorDisplayId],
                ],
            ];
            $projectJson['customData']['seelenfunke_layers'] = 'photo_bitmap+text_vector';
            $projectJson['customData']['seelenfunke_photo_polarity'] = (string) ($photoLayerInfo['polarity'] ?? $layerParams['polarity']);
        }
        if ($photoLayerSource !== '' && ($photoLayerInfo['class'] ?? '') === 'halftone' && in_array($bitmapMode, ['jarvis', 'stucki'], true)) {
            $photoJarvisPngBytes = $this->buildDitheredPreviewPng($photoLayerSource, $widthMm, $heightMm, $dpi, $bitmapMode);
        }

        $persistenceMetaJson = [
            'schemaVersion' => '2.0.0',
            'protocol'      => 'xcs-workspace-v2',
        ];

        $baseTmp = tempnam(sys_get_temp_dir(), 'sf_xcs_');
        if ($baseTmp === false) {
            throw new \RuntimeException('Temporäre Arbeitsdatei für xTool .xcs Projektarchiv konnte nicht angelegt werden.');
        }
        $tmpZipPath = $baseTmp . '.xcs';

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmpZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('xTool .xcs Projektarchiv konnte nicht angelegt werden.');
            }

            $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $zip->addFromString('meta/persistence-meta.json', json_encode($persistenceMetaJson, $jsonFlags));
            $zip->addFromString('.format', 'v2');
            $zip->addFromString('project.json', json_encode($projectJson, $jsonFlags));
            $zip->addFromString("canvases/{$canvasId}.json", json_encode($canvasMetaJson, $jsonFlags));
            $zip->addFromString("canvases/{$canvasId}/displays-0.json", json_encode($displaysJson, $jsonFlags));
            $zip->addFromString('devices/device-' . self::DEVICE_ID_KEY . '.json', json_encode($deviceJson, $jsonFlags));
            $zip->addFromString($resourceRelPath, $engravePngBytes);
            $zip->addFromString($resourceRelPath . '.meta.json', json_encode($resourceMetaJson, $jsonFlags));
            if ($isMultiLayer && $vectorDisplayId !== null) {
                $vectorResourceMeta = $resourceMetaJson;
                $vectorResourceMeta['ref'] = $vectorResourceRelPath;
                $vectorResourceMeta['metadata']['size'] = strlen($vectorPngBytes);
                $vectorResourceMeta['metadata']['checksum'] = $vectorSha256;
                $vectorResourceMeta['metadata']['displayId'] = $vectorDisplayId;
                $vectorResourceMeta['metadata']['source']['value'] = $vectorResourceFileName;
                $zip->addFromString($vectorResourceRelPath, $vectorPngBytes);
                $zip->addFromString($vectorResourceRelPath . '.meta.json', json_encode($vectorResourceMeta, $jsonFlags));
            }
            if ($photoJarvisPngBytes !== null) {
                $zip->addFromString('seelenfunke/Laser_Foto_Jarvis_Vorschau.png', $photoJarvisPngBytes);
            }
            $zip->addFromString('thumbnail.png', $thumbnailPngBytes);
            $zip->close();

            $zipBytes = (string) file_get_contents($tmpZipPath);
        } finally {
            @unlink($tmpZipPath);
            @unlink($baseTmp);
        }

        $filename = "xTool-Projekt-{$safeOrderNumber}-Pos-" . substr((string) $item->id, 0, 8) . "-{$sideLabel}.xcs";

        return [
            'bytes'            => $zipBytes,
            'filename'         => $filename,
            'center_x_mm'      => $centerXMm,
            'center_y_mm'      => $centerYMm,
            'angle_deg'        => $angleDeg,
            'width_mm'         => $widthMm,
            'height_mm'        => $heightMm,
            'is_multi_layer'   => $isMultiLayer,
            'photo_layer_info' => $photoLayerInfo,
            'photo_jarvis_png' => $photoJarvisPngBytes,
        ];
    }

    /**
     * Physikalisch äquivalente Punktdauer (µs) eines gerasterten Vektor-Layers: Punktabstand / Geschwindigkeit.
     */
    public static function equivalentDotDurationUs(int $dpi, int $speedMms): int
    {
        $pitchMm = 25.4 / max(50, $dpi);

        return max(self::MIN_DOT_DURATION_US, min(self::MAX_DOT_DURATION_US, (int) round(($pitchMm / max(1, $speedMms)) * 1000000.0)));
    }

    /**
     * Erzeugt die Jarvis-Judice-Ninke Vorschau (Laser_Foto_Jarvis_Vorschau.png) des Foto-Layers in echter Ziel-DPI.
     */
    public function buildJarvisPreviewPng(string $photoLayerPngBytes, float $widthMm, float $heightMm, int $dpi): ?string
    {
        return $this->buildDitheredPreviewPng($photoLayerPngBytes, $widthMm, $heightMm, $dpi, 'jarvis');
    }

    /**
     * Erzeugt das auf Ziel-DPI abgetastete 1-Bit Error-Diffusion-Punktraster ('jarvis' | 'stucki') des Foto-Layers.
     */
    public function buildDitheredPreviewPng(
        string $photoLayerPngBytes,
        float $widthMm,
        float $heightMm,
        int $dpi,
        string $mode = 'jarvis'
    ): ?string {
        $src = @imagecreatefromstring($photoLayerPngBytes);
        if (!$src) {
            return null;
        }
        $effectiveDpi = max(100, min(1000, $dpi));
        // Identisches Abtastraster wie buildLaserMotionStream() (1:1 Punkt-Übernahme in motion.gcode)
        $sampleW = max(64, min(800, (int) round(($widthMm / 25.4) * $effectiveDpi)));
        $sampleH = max(64, min(800, (int) round(($heightMm / 25.4) * $effectiveDpi)));
        $small = imagecreatetruecolor($sampleW, $sampleH);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 255, 255, 255, 127));
        imagecopyresampled($small, $src, 0, 0, 0, 0, $sampleW, $sampleH, imagesx($src), imagesy($src));
        imagedestroy($src);

        $preproc = app(LaserPhotoPreprocessor::class);
        $dithered = strtolower($mode) === 'stucki'
            ? $preproc->stuckiDither($small)
            : $preproc->jarvisDither($small);
        imagedestroy($small);

        ob_start();
        imagepng($dithered, null, 9);
        $bytes = (string) ob_get_clean();
        imagedestroy($dithered);

        return $bytes !== '' ? $bytes : null;
    }

    /**
     * Erzeugt das direkte xTool F2 Ultra UV Druckpaket (.xf POSIX TAR-Archiv mit preview.jpg,
     * border.gcode, motion.gcode und description.json gemäß GS009-CLASS-4 Manifest).
     *
     * @return array{bytes: string, filename: string, border_gcode: string, motion_gcode: string, description: array, center_x_mm: float, center_y_mm: float, angle_deg: float}
     */
    public function generateXfPackageBytes(
        $itemId,
        string $side = 'front',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        ?float $widthMm = null,
        ?float $heightMm = null
    ): array {
        $exportService = app(FileDownloadService::class);
        $payload = $exportService->buildLaserDesignPayload($itemId, $side, true);

        $item = $payload['item'];
        $product = $payload['product'];
        $laserSummary = $this->resolveEffectiveLaserParameters($item, $side);

        $savedCoords = (is_object($item) && is_array($item->configuration ?? null))
            ? ($item->configuration['laser_cockpit_coordinates'][$side] ?? [])
            : [];
        if ($centerXMm === null && isset($savedCoords['x_mm']) && is_numeric($savedCoords['x_mm'])) {
            $centerXMm = (float) $savedCoords['x_mm'];
        }
        if ($centerYMm === null && isset($savedCoords['y_mm']) && is_numeric($savedCoords['y_mm'])) {
            $centerYMm = (float) $savedCoords['y_mm'];
        }
        if (abs($angleDeg) < 0.01 && isset($savedCoords['angle_deg']) && is_numeric($savedCoords['angle_deg'])) {
            $angleDeg = (float) $savedCoords['angle_deg'];
        }

        if ($centerXMm === null || $centerYMm === null) {
            $detected = $this->detectBlankPositionOnBed($product, $item->configuration ?? [], $side);
            $centerXMm = $centerXMm ?? $detected['center_x_mm'];
            $centerYMm = $centerYMm ?? $detected['center_y_mm'];
            if (abs($angleDeg) < 0.01) {
                $angleDeg = $detected['angle_deg'];
            }
        }

        [$widthMm, $heightMm] = $this->resolveEffectiveJobDimensionsMm(
            $item,
            $side,
            (float) $payload['widthMm'],
            (float) $payload['heightMm'],
            $widthMm,
            $heightMm
        );
        $borderGcode = $this->generateBorderGcode($centerXMm, $centerYMm, $widthMm, $heightMm, $angleDeg);

        $activeMode = self::resolveXtoolActiveMode(
            (string) ($laserSummary['equipment_key'] ?? 'flatbed_standard'),
            (string) ($laserSummary['process_mode'] ?? '')
        );
        $materialId = self::resolveXtoolMaterialId((string) ($laserSummary['material'] ?? 'Edelstahl'));
        $focusMm = max(0.1, min(150.0, (float) ($laserSummary['focus_mm'] ?: 3.0)));
        $elementType = (string) ($laserSummary['element_type'] ?? 'Bild');
        $engraveTab = (string) ($laserSummary['engrave_tab'] ?? 'Gravieren');
        $presetMode = (string) ($laserSummary['preset_mode'] ?? 'Benutzerdefiniert');
        $vectorNote = (string) ($laserSummary['vector_note'] ?? '');
        $processingType = self::resolveXtoolProcessingType($elementType, $engraveTab);
        $dotDurationUs = max(self::MIN_DOT_DURATION_US, min(self::MAX_DOT_DURATION_US, (int) ($laserSummary['dot_duration_us'] ?: 120)));
        $powerPercent = max(1, min(100, (int) ($laserSummary['power_percent'] ?: 70)));
        $speedMms = max(1, min(4000, (int) ($laserSummary['speed_mms'] ?: 200)));
        $dpi = max(50, min(2540, (int) ($laserSummary['dpi'] ?: 500)));
        $passes = max(1, min(20, (int) ($laserSummary['passes'] ?: 1)));
        $frequencyKhz = max(1, min(200, (int) ($laserSummary['frequency_khz'] ?: 60)));
        $bitmapMode = self::resolveXtoolBitmapMode((string) ($laserSummary['bitmap_mode'] ?? 'Graustufen'));
        $scanMode = self::resolveXtoolScanMode((string) ($laserSummary['engrave_mode'] ?? 'Bidirektional'));

        $previewJpgBytes = $this->buildPreviewJpeg200($payload['stageEngravePngBytes']);

        // Multi-Layer (.xf): Foto-Bitmap-Block (Jarvis-Punktraster mit Bitmap-Werten) + Text-/Vektor-Block (Vektor-Werte)
        $photoLayerSource = (string) ($payload['photoLayerPngBytes'] ?? '');
        $vectorLayerSource = (string) ($payload['vectorLayerPngBytes'] ?? '');
        $isMultiLayer = $photoLayerSource !== '' && $vectorLayerSource !== '';
        $isPhotoOnly = $photoLayerSource !== '' && $vectorLayerSource === '';
        $photoLayerInfo = is_array($payload['photoLayerInfo'] ?? null) ? $payload['photoLayerInfo'] : [];
        $layerParams = $this->resolveLayerLaserParameters($item, $side, $laserSummary);
        $hasOverrides = (bool) ($laserSummary['has_overrides'] ?? false);
        $vectorProcessingParams = null;

        if ($photoLayerSource === '' && $vectorLayerSource !== '' && !$hasOverrides && $layerParams['has_product_vector_values']) {
            $vec = $layerParams['vector'];
            $powerPercent = max(1, min(100, (int) $vec['power_percent']));
            $speedMms = max(1, min(4000, (int) $vec['speed_mms']));
            $frequencyKhz = max(1, min(200, (int) $vec['frequency_khz']));
            $dpi = max(50, min(2540, (int) $vec['dpi']));
            $passes = max(1, min(20, (int) $vec['passes']));
            $dotDurationUs = self::equivalentDotDurationUs($dpi, $speedMms);
            $bitmapMode = 'threshold';
            $processingType = 'FILL_VECTOR_ENGRAVING';
        }

        if ($isMultiLayer || $isPhotoOnly) {
            if (!empty($photoLayerInfo['studio_adjusted']) && !empty($photoLayerInfo['studio_dither_mode']) && !$hasOverrides) {
                $bitmapMode = self::resolveXtoolBitmapMode((string) $photoLayerInfo['studio_dither_mode']);
            } elseif (!$hasOverrides && ($photoLayerInfo['class'] ?? '') === 'silhouette') {
                $bitmapMode = 'threshold';
            } elseif (!$hasOverrides && ($photoLayerInfo['class'] ?? '') === 'halftone' && $bitmapMode === 'threshold') {
                $bitmapMode = 'jarvis';
            }
        }

        if ($isMultiLayer || $isPhotoOnly) {
            $isHalftonePhoto = ($photoLayerInfo['class'] ?? '') === 'halftone';
            $photoBlockMode = match ($bitmapMode) {
                'stucki'    => 'stucki',
                'jarvis'    => 'jarvis',
                'threshold' => 'threshold',
                'greyscale' => $isHalftonePhoto && !$hasOverrides && empty($photoLayerInfo['studio_adjusted']) ? 'jarvis' : 'grayscale',
                default     => $isHalftonePhoto ? 'jarvis' : 'grayscale',
            };
            if ($photoBlockMode === 'jarvis' && $bitmapMode === 'greyscale' && !$hasOverrides && empty($photoLayerInfo['studio_adjusted'])) {
                $bitmapMode = 'jarvis';
            }

            $photoMotionSource = in_array($photoBlockMode, ['jarvis', 'stucki'], true)
                ? ($this->buildDitheredPreviewPng($photoLayerSource, $widthMm, $heightMm, $dpi, $photoBlockMode) ?? $photoLayerSource)
                : $photoLayerSource;

            $motionGcodeBytes = $this->buildLaserMotionStream(
                $photoMotionSource,
                $centerXMm,
                $centerYMm,
                $widthMm,
                $heightMm,
                $angleDeg,
                $powerPercent,
                $dotDurationUs,
                $frequencyKhz,
                $passes,
                $dpi,
                $scanMode,
                $speedMms,
                $photoBlockMode
            );

            if ($isMultiLayer) {
                $vec = $layerParams['vector'];
                $vecDpi = max(50, min(2540, (int) $vec['dpi']));
                $vecSpeed = max(1, min(4000, (int) $vec['speed_mms']));
                $vecScan = self::resolveXtoolScanMode((string) $vec['engrave_mode']);
                $vectorMotion = $this->buildLaserMotionStream(
                    $vectorLayerSource,
                    $centerXMm,
                    $centerYMm,
                    $widthMm,
                    $heightMm,
                    $angleDeg,
                    max(1, min(100, (int) $vec['power_percent'])),
                    self::equivalentDotDurationUs($vecDpi, $vecSpeed),
                    max(1, min(200, (int) $vec['frequency_khz'])),
                    max(1, min(20, (int) $vec['passes'])),
                    $vecDpi,
                    $vecScan,
                    $vecSpeed,
                    'threshold'
                );
                $motionGcodeBytes = self::mergeMotionBlocks($motionGcodeBytes, $vectorMotion);

                $vectorProcessingParams = [
                    'processingType' => 'FILL_VECTOR_ENGRAVING',
                    'elementType'    => 'Vektor',
                    'engraveTab'     => 'Gravieren',
                    'presetMode'     => $presetMode,
                    'lightSource'    => 'uv',
                    'bitmapMode'     => 'threshold',
                    'bitmapScanMode' => $vecScan,
                    'dotDuration'    => self::equivalentDotDurationUs($vecDpi, $vecSpeed),
                    'dpi'            => $vecDpi,
                    'linesPerCm'     => (int) $vec['lpc'],
                    'power'          => max(1, min(100, (int) $vec['power_percent'])),
                    'speed'          => $vecSpeed,
                    'frequency'      => max(1, min(200, (int) $vec['frequency_khz'])),
                    'repeat'         => max(1, min(20, (int) $vec['passes'])),
                    'vectorNote'     => $vectorNote,
                    'layer'          => 'text_vector',
                ];
            }
        } else {
            $fallbackBlockMode = $bitmapMode === 'threshold' ? 'threshold' : 'grayscale';
            $motionGcodeBytes = $this->buildLaserMotionStream(
                $payload['stageEngravePngBytes'],
                $centerXMm,
                $centerYMm,
                $widthMm,
                $heightMm,
                $angleDeg,
                $powerPercent,
                $dotDurationUs,
                $frequencyKhz,
                $passes,
                $dpi,
                $scanMode,
                $speedMms,
                $fallbackBlockMode
            );
        }

        $speedTimeScale = max(0.2, min(10.0, $speedMms / 200.0));
        $description = [
            'mode'                   => $activeMode,
            'processMode'            => (string) $laserSummary['process_mode'],
            'light'                  => 'uv',
            'create_time'            => (int) round(microtime(true) * 1000),
            'total_Time'             => max(5, (int) round(($widthMm * $heightMm * $passes) / (18.0 * $speedTimeScale))),
            'Compatible Machine Type'=> [self::DEVICE_EXT_ID],
            'focus'                  => $focusMm,
            'thickness'              => $focusMm,
            'processingParams'       => [[
                'processingType' => $processingType,
                'elementType'    => $elementType,
                'engraveTab'     => $engraveTab,
                'presetMode'     => $presetMode,
                'lightSource'    => 'uv',
                'bitmapMode'     => $bitmapMode,
                'bitmapScanMode' => $scanMode,
                'dotDuration'    => $dotDurationUs,
                'dpi'            => $dpi,
                'power'          => $powerPercent,
                'speed'          => $speedMms,
                'frequency'      => $frequencyKhz,
                'repeat'         => $passes,
                'vectorNote'     => $vectorNote,
            ]],
            'material'               => (string) $materialId,
            'materialLabel'          => (string) $laserSummary['material'],
            'isProcessByLayer'       => false,
            'pathPlanning'           => 'auto',
            'fillPlanning'           => 'separate',
            'scanDirection'          => 'topToBottom',
            'deviceInfo'             => [
                'extId'       => self::DEVICE_EXT_ID,
                'connectType' => 'Wi-Fi',
                'snCode'      => self::DEVICE_SN_CODE,
                'ip'          => self::DEFAULT_LASER_IP,
            ],
        ];
        if ($vectorProcessingParams !== null) {
            $description['processingParams'][0]['layer'] = 'photo_bitmap';
            $description['processingParams'][] = $vectorProcessingParams;
            $description['isProcessByLayer'] = true;
        }

        $descriptionJsonBytes = (string) json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Wichtig: Auf GS009-CLASS-4 heißt ASCII-GCode im .xf TAR-Archiv exakt "motion.gcode"
        $tarBytes = $this->packPosixTarArchive([
            'preview.jpg'      => $previewJpgBytes,
            'border.gcode'     => $borderGcode,
            'motion.gcode'     => $motionGcodeBytes,
            'description.json' => $descriptionJsonBytes,
        ]);

        $orderNumber = (string) ($item->order->order_number ?? 'Auftrag');
        $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $orderNumber) ?: 'Auftrag';
        $sideLabel = $side === 'back' ? 'Rueckseite' : 'Vorderseite';
        $filename = "xTool-Direktauftrag-{$safeOrderNumber}-Pos-" . substr((string) $item->id, 0, 8) . "-{$sideLabel}.xf";

        return [
            'bytes'        => $tarBytes,
            'filename'     => $filename,
            'border_gcode' => $borderGcode,
            'motion_gcode' => $motionGcodeBytes,
            'description'  => $description,
            'center_x_mm'  => $centerXMm,
            'center_y_mm'  => $centerYMm,
            'angle_deg'    => $angleDeg,
            'width_mm'     => $widthMm,
            'height_mm'    => $heightMm,
            'is_multi_layer' => $isMultiLayer,
        ];
    }

    /**
     * Fügt den Bitmap-Block (BITMAP HEAD .. motion_end) eines zweiten motion.gcode-Stroms direkt nach dem
     * ersten Block ein (gemeinsamer GS009-4 Kopf & Fuß, zwei Layer mit eigenen blockConfig-Laserwerten).
     */
    public static function mergeMotionBlocks(string $primaryGcode, string $secondaryGcode): string
    {
        $startMarker = '# GS009-4 BITMAP HEAD';
        $endMarker = "# motion_end\n";
        $secStart = strpos($secondaryGcode, $startMarker);
        $secEnd = strpos($secondaryGcode, $endMarker, $secStart === false ? 0 : $secStart);
        $priEnd = strpos($primaryGcode, $endMarker);
        if ($secStart === false || $secEnd === false || $priEnd === false) {
            return $primaryGcode;
        }
        $block = substr($secondaryGcode, $secStart, ($secEnd + strlen($endMarker)) - $secStart);
        $insertAt = $priEnd + strlen($endMarker);

        return substr($primaryGcode, 0, $insertAt) . $block . substr($primaryGcode, $insertAt);
    }

    /**
     * Übergibt einen Rahmen-Vorschau- ("framing") oder Gravur-Auftrag ("engrave") direkt per V2-WebSocket
     * an den xTool F2 Ultra UV bzw. reiht ihn bei Nichterreichbarkeit in die verschlüsselte Seelenfunke Laser-Queue ein.
     */
    public function enqueueOrSendLaserJob(
        $itemId,
        string $side = 'front',
        string $jobType = 'engrave',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        string $laserIp = self::DEFAULT_LASER_IP,
        ?float $widthMm = null,
        ?float $heightMm = null
    ): array {
        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($laserIp, self::DEFAULT_LASER_IP);
        $effectiveType = in_array($jobType, ['framing', 'engrave'], true) ? $jobType : 'engrave';
        $xfData = $this->generateXfPackageBytes($itemId, $side, $centerXMm, $centerYMm, $angleDeg, $widthMm, $heightMm);
        $xcsData = $this->generateXcsProjectBytes(
            $itemId,
            $side,
            $xfData['center_x_mm'],
            $xfData['center_y_mm'],
            $xfData['angle_deg'],
            $xfData['width_mm'],
            $xfData['height_mm']
        );

        $publicDisk = Storage::disk('public');
        $this->writeFileAtomicallyShared($publicDisk->path('bestellungen/xtool/latest_job.xf'), $xfData['bytes']);
        $this->writeFileAtomicallyShared($publicDisk->path('bestellungen/xtool/latest_project.xcs'), $xcsData['bytes']);
        $this->writeFileAtomicallyShared($publicDisk->path('bestellungen/xtool/latest_border.gcode'), $xfData['border_gcode']);
        if (!empty($xcsData['photo_jarvis_png'])) {
            // Produktions-Ablage: echtes Jarvis-Punktraster des Foto-Layers zur Sichtkontrolle in der Werkstatt
            $this->writeFileAtomicallyShared($publicDisk->path('bestellungen/xtool/Laser_Foto_Jarvis_Vorschau.png'), (string) $xcsData['photo_jarvis_png']);
        }

        // Zusätzlich falls auf dem Windows-Host das xTool Studio Arbeitsverzeichnis gemountet ist,
        // legen wir das fertig ausgerichtete .xcs Projekt direkt im "Seelenfunke-AutoStart"-Ordner ab.
        $hostAutoDir = '/mnt/c/Users/konta/AppData/Roaming/xTool Studio/Seelenfunke-AutoStart';
        if (@is_dir('/mnt/c/Users/konta/AppData/Roaming/xTool Studio')) {
            if (!@is_dir($hostAutoDir)) {
                @mkdir($hostAutoDir, 0775, true);
            }
            @file_put_contents($hostAutoDir . '/' . $xcsData['filename'], $xcsData['bytes']);
            @file_put_contents($hostAutoDir . '/latest_seelenfunke.xcs', $xcsData['bytes']);
            @file_put_contents($hostAutoDir . '/latest_job.xf', $xfData['bytes']);
        }

        $exportService = app(FileDownloadService::class);
        $payload = $exportService->buildLaserDesignPayload($itemId, $side, true);
        $item = $payload['item'];
        $effectiveLaserParams = $this->resolveEffectiveLaserParameters($item, $side);
        $focusMmForZ = isset($effectiveLaserParams['focus_mm']) && is_numeric($effectiveLaserParams['focus_mm'])
            ? round((float) $effectiveLaserParams['focus_mm'], 2)
            : null;
        $equipKeyForZ = (string) ($effectiveLaserParams['equipment_key'] ?? 'flatbed_standard');
        $focusCtrlCmd = self::buildZAxisFocusControlCommand($focusMmForZ, $effectiveType, $equipKeyForZ);
        $targetZMm = $focusCtrlCmd !== null ? (float) $focusCtrlCmd['data']['z'] : null;

        // Direkte V2-TLS-WebSocket-Übertragung an den xTool F2 Ultra UV bzw. Stage <-> Mini-PC Echtzeit-Relay
        $sendResult = ['ok' => false, 'task_id' => null, 'target_z_mm' => $targetZMm, 'error' => null];
        $isUnitTestRestricted = app()->runningUnitTests() && !$this->allowRelayInTests;

        if (!$isUnitTestRestricted) {
            if (!$this->isCloudEnvironment() && (!app()->runningUnitTests() || $this->allowV2SocketInTests)) {
                $sendPayload = ($effectiveType === 'framing') ? $xfData['border_gcode'] : $xfData['bytes'];
                $sendResult = $this->sendJobToLaserV2($effectiveType, $sendPayload, $safeIp, $focusMmForZ, $equipKeyForZ);
            }

            if (!($sendResult['ok'] ?? false)) {
                $heartbeat = $this->getMiniPcRelayHeartbeat();
                if ($heartbeat !== null && $heartbeat['age_seconds'] <= 20.0) {
                    $relayAction = ($effectiveType === 'framing') ? 'frame_border' : 'send_xf';
                    $relayJob = $this->enqueueRelayJob($relayAction, [
                        'laser_ip'      => $safeIp,
                        'xf_base64'     => ($effectiveType === 'engrave') ? base64_encode($xfData['bytes']) : null,
                        'gcode'         => $xfData['border_gcode'],
                        'center_x_mm'   => (float) $xfData['center_x_mm'],
                        'center_y_mm'   => (float) $xfData['center_y_mm'],
                        'width_mm'      => (float) $xcsData['width_mm'],
                        'height_mm'     => (float) $xcsData['height_mm'],
                        'angle_deg'     => (float) $xfData['angle_deg'],
                        'equipment_key' => $equipKeyForZ,
                        'focus_mm'      => $focusMmForZ,
                        'target_z_mm'   => $targetZMm,
                        'focus_control' => $focusCtrlCmd !== null ? $focusCtrlCmd['data'] : null,
                    ]);

                    $relayRes = $this->waitForRelayJobCompletion($relayJob['job_id'], $this->relayJobWaitSeconds);
                    if (is_array($relayRes) && ($relayRes['ok'] ?? false) === true) {
                        $sendResult = [
                            'ok'      => true,
                            'task_id' => $relayJob['job_id'],
                            'error'   => null,
                        ];
                    } else {
                        $sendResult = [
                            'ok'      => false,
                            'task_id' => null,
                            'error'   => (string) ($relayRes['error'] ?? 'Übertragung über Werkstatt-Brücke fehlgeschlagen.'),
                        ];
                    }
                } elseif ($sendResult['error'] === null) {
                    $sendResult['error'] = 'Werkstatt-Brücke (Mini-PC 192.168.188.151) nicht verbunden oder Laser im lokalen Netzwerk nicht erreichbar.';
                }
            }
        }

        $sentDirect = (bool) ($sendResult['ok'] ?? false);

        $job = [
            'id'               => (string) ($sendResult['task_id'] ?: Str::uuid()),
            'type'             => $effectiveType,
            'status'           => $sentDirect ? 'sent_to_laser' : 'pending',
            'sent_direct'      => $sentDirect,
            'delivery_error'   => $sentDirect ? null : ($sendResult['error'] ?? null),
            'laser_ip'         => $safeIp,
            'device_sn'        => self::DEVICE_SN_CODE,
            'order_id'         => (string) ($item->order_id ?? ''),
            'order_number'     => (string) ($item->order->order_number ?? 'Auftrag'),
            'item_id'          => (string) $item->id,
            'product_name'     => (string) ($item->product_name ?? 'Unikat'),
            'side'             => $side,
            'center_x_mm'      => $xfData['center_x_mm'],
            'center_y_mm'      => $xfData['center_y_mm'],
            'angle_deg'        => $xfData['angle_deg'],
            'width_mm'         => $xcsData['width_mm'],
            'height_mm'        => $xcsData['height_mm'],
            'xcs_filename'     => $xcsData['filename'],
            'xf_filename'      => $xfData['filename'],
            'border_gcode'     => $xfData['border_gcode'],
            'description'      => $xfData['description'],
            'focus_mm'         => $focusMmForZ,
            'target_z_mm'      => $targetZMm,
            'focus_control'    => $focusCtrlCmd !== null ? $focusCtrlCmd['data'] : null,
            'laser_parameters' => [
                'material'        => $effectiveLaserParams['material'],
                'equipment_key'   => $effectiveLaserParams['equipment_key'],
                'process_mode'    => $effectiveLaserParams['process_mode'],
                'focus_mm'        => $effectiveLaserParams['focus_mm'],
                'element_type'    => $effectiveLaserParams['element_type'],
                'engrave_tab'     => $effectiveLaserParams['engrave_tab'],
                'preset_mode'     => $effectiveLaserParams['preset_mode'],
                'power_percent'   => $effectiveLaserParams['power_percent'],
                'speed_mms'       => $effectiveLaserParams['speed_mms'],
                'dpi'             => $effectiveLaserParams['dpi'],
                'dot_duration_us' => $effectiveLaserParams['dot_duration_us'],
                'frequency_khz'   => $effectiveLaserParams['frequency_khz'],
                'passes'          => $effectiveLaserParams['passes'],
                'bitmap_mode'     => $effectiveLaserParams['bitmap_mode'],
                'engrave_mode'    => $effectiveLaserParams['engrave_mode'],
                'vector_note'     => $effectiveLaserParams['vector_note'],
                'has_overrides'   => $effectiveLaserParams['has_overrides'],
            ],
            'created_at'       => now()->toIso8601String(),
            'processed_at'     => $sentDirect ? now()->toIso8601String() : null,
            'error_message'    => $sentDirect ? null : ($sendResult['error'] ?? null),
        ];

        $this->appendJob($job);

        return $job;
    }

    /**
     * Liefert den aktuellen Status der Laser-Warteschlange für die REST-API / Funkenwerk-Bridge.
     */
    public function getQueueState(bool $includeFilesBase64 = false): array
    {
        $jobs = $this->readQueue();
        $publicDisk = Storage::disk('public');

        $out = [];
        foreach ($jobs as $job) {
            if ($includeFilesBase64 && ($job['status'] ?? '') === 'pending') {
                if ($publicDisk->exists('bestellungen/xtool/latest_job.xf')) {
                    $job['xf_base64'] = base64_encode((string) $publicDisk->get('bestellungen/xtool/latest_job.xf'));
                }
                if ($publicDisk->exists('bestellungen/xtool/latest_project.xcs')) {
                    $job['xcs_base64'] = base64_encode((string) $publicDisk->get('bestellungen/xtool/latest_project.xcs'));
                }
            }
            $out[] = $job;
        }

        $pending = array_values(array_filter($out, fn (array $j) => ($j['status'] ?? '') === 'pending'));

        return [
            'laser_ip'      => self::DEFAULT_LASER_IP,
            'device_sn'     => self::DEVICE_SN_CODE,
            'pending_count' => count($pending),
            'pending_jobs'  => $pending,
            'recent_jobs'   => array_slice($out, 0, 15),
        ];
    }

    /**
     * Bestätigt die Verarbeitung eines Laser-Jobs durch die Funkenwerk-/xTool-Bridge.
     */
    public function acknowledgeJob(string $jobId, string $status, ?string $errorMessage = null): ?array
    {
        $updated = null;
        $this->withQueueLock(function () use ($jobId, $status, $errorMessage, &$updated) {
            $jobs = $this->readQueueUnlocked();
            foreach ($jobs as &$job) {
                if (($job['id'] ?? '') === $jobId) {
                    $job['status'] = $status;
                    $job['processed_at'] = now()->toIso8601String();
                    $job['error_message'] = $errorMessage ? mb_substr($errorMessage, 0, 500) : null;
                    $updated = $job;
                    break;
                }
            }
            unset($job);
            if ($updated !== null) {
                $this->writeQueueUnlocked($jobs);
            }
        });

        return $updated;
    }

    /**
     * Bereitet die Stage-Gravur-Bitmap für .xcs als sauberes, hochauflösendes dunkles/Graustufen-Motiv
     * auf echt transparentem Hintergrund (PNG Alpha = 0 / GD Alpha = 127 außerhalb des Motivs) auf.
     * Verhindert sowohl weiße Abdeckkästen über dem Live-Kamerabild als auch 100 % schwarze Vollflächen-Rechtecke.
     */
    public function buildCleanTransparentXcsBitmapPng(string $pngBytes): string
    {
        $src = @imagecreatefromstring($pngBytes);
        if (!$src) {
            $fallback = imagecreatetruecolor(64, 64);
            imagealphablending($fallback, false);
            imagesavealpha($fallback, true);
            $trans = imagecolorallocatealpha($fallback, 255, 255, 255, 127);
            imagefill($fallback, 0, 0, $trans);
            ob_start();
            imagepng($fallback, null, 6);
            $outBytes = (string) ob_get_clean();
            imagedestroy($fallback);

            return $outBytes;
        }

        $w = max(1, imagesx($src));
        $h = max(1, imagesy($src));
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefill($dst, 0, 0, $trans);

        $opaqueGrayLut = [];
        $alphaBlackLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $opaqueGrayLut[$g] = imagecolorallocatealpha($dst, $g, $g, $g, 0);
        }
        for ($a = 0; $a <= 127; $a++) {
            $alphaBlackLut[$a] = imagecolorallocatealpha($dst, 0, 0, 0, $a);
        }

        $opaqueDarkPixels = 0;
        $totalPixels = $w * $h;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($src, $x, $y);
                $gdAlpha = ($c >> 24) & 0x7F;
                if ($gdAlpha >= 126) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $srcGray = (int) ((($r * 77) + ($g * 150) + ($b * 29)) >> 8);

                // Reinweiße Hintergrundpixel (>= 252) echt transparent freistellen
                if ($srcGray >= 252) {
                    continue;
                }

                if ($gdAlpha > 0) {
                    if ($srcGray <= 12) {
                        imagesetpixel($dst, $x, $y, $alphaBlackLut[$gdAlpha]);
                    } else {
                        $col = imagecolorallocatealpha($dst, $srcGray, $srcGray, $srcGray, $gdAlpha);
                        imagesetpixel($dst, $x, $y, $col);
                    }
                } else {
                    imagesetpixel($dst, $x, $y, $opaqueGrayLut[$srcGray]);
                    if ($srcGray < 15) {
                        $opaqueDarkPixels++;
                    }
                }
            }
        }

        // Schutz gegen vollflächige schwarze Test-/Fehl-Bitmaps (>= 98 % tiefschwarzes Rechteck ohne Transparenz):
        // Äußeren 1-px-Saum transparent halten, sodass niemals ein 100 % schwarzer Kasten eingebettet wird.
        if ($totalPixels > 16 && $opaqueDarkPixels >= (int) round($totalPixels * 0.98)) {
            for ($x = 0; $x < $w; $x++) {
                imagesetpixel($dst, $x, 0, $trans);
                imagesetpixel($dst, $x, $h - 1, $trans);
            }
            for ($y = 0; $y < $h; $y++) {
                imagesetpixel($dst, 0, $y, $trans);
                imagesetpixel($dst, $w - 1, $y, $trans);
            }
        }

        imagedestroy($src);
        ob_start();
        imagepng($dst, null, 6);
        $outBytes = (string) ob_get_clean();
        imagedestroy($dst);

        return $outBytes;
    }

    /**
     * Wandelt die transparente Stage-Gravur-Bitmap in ein 100 % opakes Graustufen-PNG auf reinweißem Grund (#FFFFFF) um
     * (z. B. als Quelle für das helle 400x400 thumbnail.png Vorschaubild).
     */
    public function buildOpaqueWhiteBackedGrayscalePng(string $pngBytes): string
    {
        $src = @imagecreatefromstring($pngBytes);
        if (!$src) {
            $fallback = imagecreatetruecolor(64, 64);
            imagealphablending($fallback, false);
            imagesavealpha($fallback, false);
            $white = imagecolorallocate($fallback, 255, 255, 255);
            imagefill($fallback, 0, 0, $white);
            ob_start();
            imagepng($fallback, null, 6);
            $outBytes = (string) ob_get_clean();
            imagedestroy($fallback);

            return $outBytes;
        }

        $w = max(1, imagesx($src));
        $h = max(1, imagesy($src));
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, false);

        $grayLut = [];
        for ($g = 0; $g <= 255; $g++) {
            $grayLut[$g] = imagecolorallocate($dst, $g, $g, $g);
        }
        imagefill($dst, 0, 0, $grayLut[255]);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($src, $x, $y);
                $gdAlpha = ($c >> 24) & 0x7F;
                if ($gdAlpha >= 126) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $srcGray = (int) ((($r * 77) + ($g * 150) + ($b * 29)) >> 8);
                if ($gdAlpha <= 0) {
                    $compGray = $srcGray;
                } else {
                    $opacity = (127 - $gdAlpha) / 127.0;
                    $compGray = max(0, min(255, (int) round(($srcGray * $opacity) + (255.0 * (1.0 - $opacity)))));
                }
                if ($compGray < 255) {
                    imagesetpixel($dst, $x, $y, $grayLut[$compGray]);
                }
            }
        }

        imagedestroy($src);
        ob_start();
        imagepng($dst, null, 6);
        $outBytes = (string) ob_get_clean();
        imagedestroy($dst);

        return $outBytes;
    }

    /**
     * Erzeugt ein kontrastreiches, freundliches 400x400 px Projekt-Vorschaubild (thumbnail.png auf #FAF8F5)
     * für die xTool Studio Projektübersicht.
     */
    public function buildXcsProjectThumbnailPng(string $opaqueWhitePngBytes): string
    {
        $thumbSize = 400;
        $thumb = imagecreatetruecolor($thumbSize, $thumbSize);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, false);

        $bgLight = imagecolorallocate($thumb, 250, 248, 245); // #FAF8F5
        $cardWhite = imagecolorallocate($thumb, 255, 255, 255);
        $borderCol = imagecolorallocate($thumb, 222, 216, 206);
        $accentGold = imagecolorallocate($thumb, 197, 160, 89); // #C5A059
        imagefill($thumb, 0, 0, $bgLight);

        imagefilledrectangle($thumb, 18, 18, 381, 381, $cardWhite);
        imagerectangle($thumb, 18, 18, 381, 381, $borderCol);
        imagerectangle($thumb, 19, 19, 380, 380, $accentGold);

        $src = @imagecreatefromstring($opaqueWhitePngBytes);
        if ($src) {
            $sw = max(1, imagesx($src));
            $sh = max(1, imagesy($src));
            $maxBox = 332.0;
            $scale = min($maxBox / $sw, $maxBox / $sh);
            $dw = max(1, (int) round($sw * $scale));
            $dh = max(1, (int) round($sh * $scale));
            $dx = (int) round(($thumbSize - $dw) / 2.0);
            $dy = (int) round(($thumbSize - $dh) / 2.0);

            imagecopyresampled($thumb, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
            imagedestroy($src);
        }

        ob_start();
        imagepng($thumb, null, 6);
        $thumbBytes = (string) ob_get_clean();
        imagedestroy($thumb);

        return $thumbBytes;
    }

    /**
     * Erstellt ein 200x200 px Vorschaubild (preview.jpg) auf weißem Hintergrund für das .xf Druckpaket.
     */
    private function buildPreviewJpeg200(string $pngBytes): string
    {
        $preview = imagecreatetruecolor(200, 200);
        $white = imagecolorallocate($preview, 255, 255, 255);
        imagefill($preview, 0, 0, $white);

        $src = @imagecreatefromstring($pngBytes);
        if ($src) {
            imagealphablending($preview, true);
            imagecopyresampled($preview, $src, 10, 10, 0, 0, 180, 180, imagesx($src), imagesy($src));
            imagedestroy($src);
        }

        ob_start();
        imagejpeg($preview, null, 88);
        $jpg = (string) ob_get_clean();
        imagedestroy($preview);

        return $jpg;
    }

    /**
     * Erzeugt den nativen GS009-4 UV-Galvo Bewegungsstrom (motion.gcode) für die direkte Gravur am xTool F2 Ultra UV.
     */
    private function buildLaserMotionStream(
        string $pngBytes,
        float $centerXMm,
        float $centerYMm,
        float $widthMm,
        float $heightMm,
        float $angleDeg,
        int $powerPercent,
        int $dotDurationUs,
        int $frequencyKhz,
        int $passes,
        int $dpi = 500,
        string $scanMode = 'zMode',
        int $speedMms = 200,
        string $blockBitmapMode = 'grayscale'
    ): string {
        $effectiveDpi = max(100, min(1000, $dpi));
        $sampleW = max(64, min(800, (int) round(($widthMm / 25.4) * $effectiveDpi)));
        $sampleH = max(64, min(800, (int) round(($heightMm / 25.4) * $effectiveDpi)));
        $densityLinesPerMm = round($sampleH / max(1.0, $heightMm), 4);
        $pixelPitchMm = $widthMm / max(1, $sampleW);
        $speedScale = max(0.05, min(20.0, max(1, $speedMms) / 200.0));
        $dotFeedMmPerMin = (($pixelPitchMm * 1000000.0) / max(10, $dotDurationUs)) * 60.0;
        $feedMmPerMin = max(
            600,
            min(240000, (int) round($dotFeedMmPerMin * $speedScale))
        );

        $powerFactor = round(max(0.01, min(1.0, $powerPercent / 100.0)), 3);
        $sMax = max(10, min(1000, $powerPercent * 10));
        $freqClamped = max(20, min(200, $frequencyKhz));

        $blockConfigJson = json_encode([
            'powerFactor' => $powerFactor,
            'speed'       => max(1, min(4000, $speedMms)),
            'overDist'    => 0,
            'accel'       => 5000,
            'density'     => $densityLinesPerMm,
            'power'       => [0, $sMax],
            'bitmapMode'  => $blockBitmapMode,
            'crossDot'    => false,
            'startX'      => 0,
            'isVector'    => false,
        ], JSON_UNESCAPED_SLASHES);

        $lines = [
            '# date=' . date('Y_m_d_H_i_s'),
            '# version=1.10.15',
            '# algorithmVersion=',
            '# gc={"size":{"w":200,"h":200}}',
            '# gc={"offset":{"x":0,"y":0}}',
            '# gc={"start":{"x":0,"y":0.0000}}',
            '# gc={"keys":["x","y"],"rm":1,"is3DMode":false}',
            '# timeConfig=',
            'G90',
            'G0 F3000',
            'G4P5',
            'G198 P85 "M9401 D2 V500"',
            'M9064 B3',
            'G198 P70 "M9064 B3"',
            'G198 P78 "M9064 B3"',
            'M9039 C3',
            'G198 P69 "M9039 C3"',
            'G198 P76 "M9039 V40"',
            'G198 P76 "M9043 H1 I1 J1 K1 L1 M1"',
            'G4P5',
            '# GS009-4 HEAD',
            'G0 F180000',
            'M4 S0',
            'G1 F180000',
            'G0 X0 Y0',
            '',
            '# GS009-4 BITMAP HEAD',
            '# motion_start',
            'G4P1',
            'G22',
            "G0Q{$freqClamped}",
            'G4P1',
            'M523P40',
            '# blockConfig=' . $blockConfigJson,
            "G1F{$feedMmPerMin}S0",
        ];

        if ($passes > 1) {
            $lines[] = '# MUST';
            $lines[] = 'G104 P' . $passes;
        }

        $src = @imagecreatefromstring($pngBytes);
        if ($src) {
            $small = imagecreatetruecolor($sampleW, $sampleH);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            $trans = imagecolorallocatealpha($small, 255, 255, 255, 127);
            imagefill($small, 0, 0, $trans);
            if (imagesx($src) === $sampleW && imagesy($src) === $sampleH) {
                imagecopy($small, $src, 0, 0, 0, 0, $sampleW, $sampleH);
            } else {
                imagecopyresampled($small, $src, 0, 0, 0, 0, $sampleW, $sampleH, imagesx($src), imagesy($src));
            }
            imagedestroy($src);

            $rad = deg2rad($angleDeg);
            $cos = cos($rad);
            $sin = sin($rad);
            $halfW = $widthMm / 2.0;
            $halfH = $heightMm / 2.0;
            $denomW = (float) max(1, $sampleW - 1);
            $denomH = (float) max(1, $sampleH - 1);
            $isDotPulseMode = in_array(strtolower($blockBitmapMode), ['jarvis', 'stucki'], true);
            $pulseSpanMm = max(0.008, min(0.032, $pixelPitchMm * 0.52));

            for ($y = 0; $y < $sampleH; $y++) {
                $ly = -$halfH + (($y / $denomH) * $heightMm);
                $reverse = ($scanMode === 'zMode') && (($y & 1) === 1);
                $dirSign = $reverse ? -1.0 : 1.0;

                $inRun = false;
                $runPower = 0;
                $runStartX = 0.0;
                $runStartY = 0.0;
                $runEndX = 0.0;
                $runEndY = 0.0;

                for ($step = 0; $step < $sampleW; $step++) {
                    $x = $reverse ? ($sampleW - 1 - $step) : $step;
                    $c = imagecolorat($small, $x, $y);
                    $alpha = ($c >> 24) & 0x7F;
                    $r = ($c >> 16) & 0xFF;
                    $g = ($c >> 8) & 0xFF;
                    $b = $c & 0xFF;
                    $gray = (int) (($r * 77 + $g * 150 + $b * 29) >> 8);

                    $pixelS = 0;
                    if ($isDotPulseMode) {
                        if ($alpha < 112 && $gray < 128) {
                            $pixelS = $sMax;
                        }
                    } elseif ($alpha < 112 && $gray < 242) {
                        $opacityFactor = (127 - $alpha) / 127.0;
                        $darkFactor = (255 - $gray) / 255.0;
                        $intensity = min(1.0, max(0.12, $darkFactor * $opacityFactor));
                        // Quantisierung in 20 Stufen hält die GCode-Datei kompakt und flüssig
                        $quantized = max(1, (int) round($intensity * 20.0)) / 20.0;
                        $pixelS = max(15, min($sMax, (int) round($sMax * $quantized)));
                    }

                    if ($pixelS > 0) {
                        $lx = -$halfW + (($x / $denomW) * $widthMm);
                        $gx = max(0.0, min(self::BED_WIDTH_MM, $centerXMm + ($lx * $cos) - ($ly * $sin)));
                        $gy = max(0.0, min(self::BED_HEIGHT_MM, $centerYMm + ($lx * $sin) + ($ly * $cos)));

                        if ($isDotPulseMode) {
                            // Diskreter Punkt-Puls ohne Verschmelzen benachbarter Halbton-Dither-Punkte zu 100-%-Brennstrichen
                            $halfPulse = $pulseSpanMm * 0.5 * $dirSign;
                            $p0x = max(0.0, min(self::BED_WIDTH_MM, $gx - ($halfPulse * $cos)));
                            $p0y = max(0.0, min(self::BED_HEIGHT_MM, $gy - ($halfPulse * $sin)));
                            $p1x = max(0.0, min(self::BED_WIDTH_MM, $gx + ($halfPulse * $cos)));
                            $p1y = max(0.0, min(self::BED_HEIGHT_MM, $gy + ($halfPulse * $sin)));
                            $lines[] = sprintf('G0X%.3fY%.3f', $p0x, $p0y);
                            $lines[] = sprintf('G1X%.3fY%.3fS%d', $p1x, $p1y, $pixelS);
                        } elseif (!$inRun) {
                            $lines[] = sprintf('G0X%.3fY%.3f', $gx, $gy);
                            $inRun = true;
                            $runPower = $pixelS;
                            $runStartX = $gx;
                            $runStartY = $gy;
                            $runEndX = $gx;
                            $runEndY = $gy;
                        } elseif ($pixelS === $runPower) {
                            $runEndX = $gx;
                            $runEndY = $gy;
                        } else {
                            if (hypot($runEndX - $runStartX, $runEndY - $runStartY) < 0.002) {
                                $runEndX = max(0.0, min(self::BED_WIDTH_MM, $runStartX + ($pulseSpanMm * $dirSign * $cos)));
                                $runEndY = max(0.0, min(self::BED_HEIGHT_MM, $runStartY + ($pulseSpanMm * $dirSign * $sin)));
                            }
                            $lines[] = sprintf('G1X%.3fY%.3fS%d', $runEndX, $runEndY, $runPower);
                            $lines[] = sprintf('G0X%.3fY%.3f', $gx, $gy);
                            $runPower = $pixelS;
                            $runStartX = $gx;
                            $runStartY = $gy;
                            $runEndX = $gx;
                            $runEndY = $gy;
                        }
                    } elseif ($inRun) {
                        if (hypot($runEndX - $runStartX, $runEndY - $runStartY) < 0.002) {
                            $runEndX = max(0.0, min(self::BED_WIDTH_MM, $runStartX + ($pulseSpanMm * $dirSign * $cos)));
                            $runEndY = max(0.0, min(self::BED_HEIGHT_MM, $runStartY + ($pulseSpanMm * $dirSign * $sin)));
                        }
                        $lines[] = sprintf('G1X%.3fY%.3fS%d', $runEndX, $runEndY, $runPower);
                        $inRun = false;
                        $runPower = 0;
                    }
                }

                if ($inRun) {
                    if (hypot($runEndX - $runStartX, $runEndY - $runStartY) < 0.002) {
                        $runEndX = max(0.0, min(self::BED_WIDTH_MM, $runStartX + ($pulseSpanMm * $dirSign * $cos)));
                        $runEndY = max(0.0, min(self::BED_HEIGHT_MM, $runStartY + ($pulseSpanMm * $dirSign * $sin)));
                    }
                    $lines[] = sprintf('G1X%.3fY%.3fS%d', $runEndX, $runEndY, $runPower);
                }
            }
            imagedestroy($small);
        }

        if ($passes > 1) {
            $lines[] = '# MUST';
            $lines[] = 'G105';
        }

        $lines[] = '# GS009-4 BITMAP TAIL';
        $lines[] = '# motion_end';
        $lines[] = '# END';
        $lines[] = '# GS009-4 TAIL';
        $lines[] = 'G90';
        $lines[] = 'G0 S0';
        $lines[] = 'G0 F180000';
        $lines[] = 'G1 F180000';
        $lines[] = 'M536 U0';
        $lines[] = 'M6 P1';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Packt ein unkomprimiertes POSIX ustar TAR-Archiv im Arbeitsspeicher (exakt wie tmp.xf von xTool Studio).
     *
     * @param array<string, string> $files
     */
    private function packPosixTarArchive(array $files): string
    {
        $tar = '';
        $mtimeOct = sprintf('%011o', time());

        foreach ($files as $name => $content) {
            $size = strlen($content);
            $sizeOct = sprintf('%011o', $size);

            $header = str_pad(substr($name, 0, 100), 100, "\0");
            $header .= str_pad('0000644', 7, '0', STR_PAD_LEFT) . "\0";
            $header .= str_pad('0000000', 7, '0', STR_PAD_LEFT) . "\0";
            $header .= str_pad('0000000', 7, '0', STR_PAD_LEFT) . "\0";
            $header .= $sizeOct . "\0";
            $header .= $mtimeOct . "\0";
            $header .= '        '; // 8 Leerzeichen als Platzhalter für Checksumme
            $header .= '0'; // Normal file
            $header .= str_repeat("\0", 100);
            $header .= "ustar\0" . '00';
            $header = str_pad($header, 512, "\0");

            $checksum = 0;
            for ($i = 0; $i < 512; $i++) {
                $checksum += ord($header[$i]);
            }
            $checksumField = sprintf('%06o', $checksum) . "\0 ";
            $header = substr_replace($header, $checksumField, 148, 8);

            $tar .= $header . $content;
            $remainder = $size % 512;
            if ($remainder > 0) {
                $tar .= str_repeat("\0", 512 - $remainder);
            }
        }

        // Zwei leere 512-Byte-Blöcke markieren das Ende des TAR-Archivs
        $tar .= str_repeat("\0", 1024);

        return $tar;
    }

    /**
     * Erzeugt ein realistisches 2000x2000 px Lamellenrost-Kamerabild mit eingelegtem Rohling als Fallback für Testumgebungen.
     */
    private function createSyntheticSlatBedImage(string $targetFullPath): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $img = imagecreatetruecolor(2000, 2000);
        $darkSlat = imagecolorallocate($img, 24, 26, 30);
        $gapColor = imagecolorallocate($img, 12, 13, 16);
        $blankFill = imagecolorallocate($img, 210, 214, 220);
        $blankBorder = imagecolorallocate($img, 160, 165, 172);

        imagefill($img, 0, 0, $darkSlat);
        for ($x = 40; $x < 2000; $x += 120) {
            imagefilledrectangle($img, $x, 0, $x + 18, 1999, $gapColor);
        }

        // Beispiel-Rohling mittig bei (96.0 mm, 99.0 mm) = (960 px, 990 px)
        imagefilledrectangle($img, 760, 840, 1160, 1140, $blankFill);
        imagerectangle($img, 760, 840, 1160, 1140, $blankBorder);

        ob_start();
        imagejpeg($img, null, 86);
        $jpgBytes = (string) ob_get_clean();
        imagedestroy($img);

        if ($jpgBytes !== '') {
            $this->writeFileAtomicallyShared($targetFullPath, $jpgBytes);
        }
    }

    private function appendJob(array $job): void
    {
        $this->withQueueLock(function () use ($job) {
            $jobs = $this->readQueueUnlocked();
            array_unshift($jobs, $job);
            if (count($jobs) > self::MAX_HISTORY) {
                $jobs = array_slice($jobs, 0, self::MAX_HISTORY);
            }
            $this->writeQueueUnlocked($jobs);
        });
    }

    private function readQueue(): array
    {
        $jobs = [];
        $this->withQueueLock(function () use (&$jobs) {
            $jobs = $this->readQueueUnlocked();
        });
        return $jobs;
    }

    private function readQueueUnlocked(): array
    {
        $disk = Storage::disk('local');
        if (!$disk->exists(self::QUEUE_FILE)) {
            return [];
        }

        $raw = (string) $disk->get(self::QUEUE_FILE);
        if (trim($raw) === '') {
            return [];
        }

        if (str_starts_with($raw, 'ENC:v1:')) {
            try {
                $raw = Crypt::decryptString(substr($raw, 7));
            } catch (\Throwable) {
                return [];
            }
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeQueueUnlocked(array $jobs): void
    {
        $disk = Storage::disk('local');
        $json = (string) json_encode(array_values($jobs), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encrypted = 'ENC:v1:' . Crypt::encryptString($json);
        $disk->put(self::QUEUE_FILE, $encrypted);
    }

    private function withQueueLock(callable $callback): void
    {
        $disk = Storage::disk('local');
        $lockPath = $disk->path(self::LOCK_FILE);
        if (!is_dir(dirname($lockPath))) {
            @mkdir(dirname($lockPath), 0775, true);
        }

        $fp = @fopen($lockPath, 'c+');
        if (!is_resource($fp)) {
            $callback();
            return;
        }

        @flock($fp, LOCK_EX);
        try {
            $callback();
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    // =========================================================================
    // STAGE <-> WERKSTATT MINI-PC (192.168.188.151) ECHTZEIT-LASER-RELAY
    // =========================================================================

    public function relayDirectory(): string
    {
        $dir = $this->relayRootOverride ?: storage_path('app/private/xtool_stage_relay');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return rtrim($dir, '/\\');
    }

    public function relayJobsDirectory(): string
    {
        $dir = $this->relayDirectory() . DIRECTORY_SEPARATOR . 'jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        return $dir;
    }

    public function relayHeartbeatPath(): string
    {
        return $this->relayDirectory() . DIRECTORY_SEPARATOR . 'minipc_heartbeat.json';
    }

    private function relayLockPath(): string
    {
        return $this->relayDirectory() . DIRECTORY_SEPARATOR . 'relay.lock';
    }

    public static function isValidRelayJobId(string $jobId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{6,80}$/', $jobId);
    }

    public function recordMiniPcRelayHeartbeat(?bool $laserOnline = null, ?string $laserIp = null): array
    {
        $hbPath = $this->relayHeartbeatPath();
        $resolvedOnline = $laserOnline;

        if ($resolvedOnline === null && @is_file($hbPath)) {
            $existing = json_decode((string) @file_get_contents($hbPath), true);
            if (is_array($existing) && isset($existing['laser_online'])) {
                $resolvedOnline = (bool) $existing['laser_online'];
            }
        }

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($laserIp ?? self::DEFAULT_LASER_IP, self::DEFAULT_LASER_IP);
        $nowEpoch = microtime(true);
        $nowIso = now('Europe/Berlin')->toIso8601String();

        $data = [
            'last_seen_at'    => $nowIso,
            'last_seen_epoch' => $nowEpoch,
            'laser_online'    => (bool) ($resolvedOnline ?? false),
            'laser_ip'        => $safeIp,
        ];

        $this->atomicWriteJsonFile($hbPath, $data);

        return $data;
    }

    /**
     * @return array{age_seconds: float, laser_online: bool, last_seen_at: string, laser_ip: string}|null
     */
    public function getMiniPcRelayHeartbeat(): ?array
    {
        $hbPath = $this->relayHeartbeatPath();
        if (is_link($hbPath) || !@is_file($hbPath)) {
            return null;
        }

        $raw = @file_get_contents($hbPath);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $epoch = null;
        if (isset($decoded['last_seen_epoch']) && is_numeric($decoded['last_seen_epoch'])) {
            $epoch = (float) $decoded['last_seen_epoch'];
        } elseif (isset($decoded['last_seen_at'])) {
            if (is_numeric($decoded['last_seen_at'])) {
                $epoch = (float) $decoded['last_seen_at'];
            } elseif (is_string($decoded['last_seen_at'])) {
                $parsed = strtotime($decoded['last_seen_at']);
                if ($parsed !== false) {
                    $epoch = (float) $parsed;
                }
            }
        }

        if ($epoch === null || $epoch <= 0.0) {
            return null;
        }

        return [
            'age_seconds'  => max(0.0, microtime(true) - $epoch),
            'laser_online' => (bool) ($decoded['laser_online'] ?? false),
            'last_seen_at' => (string) ($decoded['last_seen_at'] ?? ''),
            'laser_ip'     => (string) ($decoded['laser_ip'] ?? self::DEFAULT_LASER_IP),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function enqueueRelayJob(string $action, array $params = []): array
    {
        if (!in_array($action, ['capture_camera', 'send_xf', 'frame_border'], true)) {
            throw new \InvalidArgumentException('Ungültige Relay-Aktion: ' . $action);
        }

        $jobId = 'lrel_' . now('Europe/Berlin')->format('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp(
            (string) ($params['laser_ip'] ?? self::DEFAULT_LASER_IP),
            self::DEFAULT_LASER_IP
        );

        $rawFocus = isset($params['focus_mm']) && is_numeric($params['focus_mm']) && (float) $params['focus_mm'] > 0
            ? round((float) $params['focus_mm'], 2)
            : null;
        $equipKey = isset($params['equipment_key']) && is_string($params['equipment_key']) && trim($params['equipment_key']) !== ''
            ? self::normalizeEquipmentKey($params['equipment_key'])
            : 'flatbed_standard';
        $jobTypeForZ = ($action === 'send_xf') ? 'engrave' : 'framing';
        $autoFocusCmd = self::buildZAxisFocusControlCommand($rawFocus, $jobTypeForZ, $equipKey);
        $resolvedTargetZ = isset($params['target_z_mm']) && is_numeric($params['target_z_mm']) && (float) $params['target_z_mm'] > 0
            ? round((float) $params['target_z_mm'], 2)
            : ($autoFocusCmd !== null ? (float) $autoFocusCmd['data']['z'] : null);
        $resolvedFocusControl = isset($params['focus_control']) && is_array($params['focus_control'])
            ? $params['focus_control']
            : ($autoFocusCmd !== null ? $autoFocusCmd['data'] : null);

        $resolvedLightMode = self::normalizeCameraLightMode(
            isset($params['light_mode']) && is_string($params['light_mode']) ? $params['light_mode'] : null
        );

        $jobRecord = [
            'job_id'        => $jobId,
            'status'        => 'pending',
            'created_epoch' => microtime(true),
            'created_at'    => now('Europe/Berlin')->toIso8601String(),
            'claimed_at'    => null,
            'completed_at'  => null,
            'action'        => $action,
            'laser_ip'      => $safeIp,
            'light_mode'    => $resolvedLightMode,
            'xf_base64'     => isset($params['xf_base64']) && is_string($params['xf_base64']) ? $params['xf_base64'] : null,
            'gcode'         => isset($params['gcode']) && is_string($params['gcode']) ? $params['gcode'] : null,
            'center_x_mm'   => isset($params['center_x_mm']) ? round((float) $params['center_x_mm'], 2) : 100.0,
            'center_y_mm'   => isset($params['center_y_mm']) ? round((float) $params['center_y_mm'], 2) : 100.0,
            'width_mm'      => isset($params['width_mm']) ? round((float) $params['width_mm'], 2) : 80.0,
            'height_mm'     => isset($params['height_mm']) ? round((float) $params['height_mm'], 2) : 80.0,
            'angle_deg'     => isset($params['angle_deg']) ? round((float) $params['angle_deg'], 2) : 0.0,
            'equipment_key' => $equipKey,
            'focus_mm'      => $rawFocus,
            'target_z_mm'   => $resolvedTargetZ,
            'focus_control' => $resolvedFocusControl,
            'result'        => null,
        ];

        $this->withRelayLock(function () use ($jobRecord): void {
            $this->purgeExpiredRelayJobsUnlocked(60.0);
            $jobPath = $this->relayJobsDirectory() . DIRECTORY_SEPARATOR . $jobRecord['job_id'] . '.json';
            $this->atomicWriteJsonFile($jobPath, $jobRecord);
        });

        return $jobRecord;
    }

    /**
     * Vom Mini-PC gepollt: aktualisiert den Heartbeat, räumt Alt-Jobs (> 60s) auf
     * und reserviert atomar den ältesten pending-Job (max. 25s alt).
     *
     * @return array<string, mixed>|null
     */
    public function claimNextPendingRelayJob(?bool $laserOnline = null, ?string $laserIp = null): ?array
    {
        $this->recordMiniPcRelayHeartbeat($laserOnline, $laserIp);

        $claimedWireJob = null;

        $this->withRelayLock(function () use (&$claimedWireJob): void {
            $this->purgeExpiredRelayJobsUnlocked(60.0);

            $jobsDir = $this->relayJobsDirectory();
            $files = glob($jobsDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
            $now = microtime(true);
            $candidates = [];

            foreach ($files as $file) {
                if (is_link($file) || !@is_file($file)) {
                    continue;
                }
                $raw = @file_get_contents($file);
                if (!is_string($raw) || $raw === '') {
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'pending') {
                    continue;
                }
                $jobId = (string) ($decoded['job_id'] ?? '');
                if (!self::isValidRelayJobId($jobId)) {
                    continue;
                }
                $createdEpoch = isset($decoded['created_epoch']) && is_numeric($decoded['created_epoch'])
                    ? (float) $decoded['created_epoch']
                    : (float) (@filemtime($file) ?: 0);
                $ageSec = max(0.0, $now - $createdEpoch);
                if ($ageSec > 25.0) {
                    continue;
                }

                $candidates[] = [
                    'epoch' => $createdEpoch,
                    'path'  => $file,
                    'data'  => $decoded,
                ];
            }

            if ($candidates === []) {
                return;
            }

            usort($candidates, static fn (array $a, array $b): int => $a['epoch'] <=> $b['epoch']);
            $chosen = $candidates[0];
            $record = $chosen['data'];
            $record['status'] = 'claimed';
            $record['claimed_at'] = now('Europe/Berlin')->toIso8601String();

            $this->atomicWriteJsonFile($chosen['path'], $record);

            $claimedWireJob = [
                'job_id'        => (string) $record['job_id'],
                'action'        => (string) $record['action'],
                'laser_ip'      => (string) ($record['laser_ip'] ?? self::DEFAULT_LASER_IP),
                'light_mode'    => self::normalizeCameraLightMode(isset($record['light_mode']) ? (string) $record['light_mode'] : null),
                'xf_base64'     => $record['xf_base64'] ?? null,
                'gcode'         => $record['gcode'] ?? null,
                'center_x_mm'   => (float) ($record['center_x_mm'] ?? 100.0),
                'center_y_mm'   => (float) ($record['center_y_mm'] ?? 100.0),
                'width_mm'      => (float) ($record['width_mm'] ?? 80.0),
                'height_mm'     => (float) ($record['height_mm'] ?? 80.0),
                'angle_deg'     => (float) ($record['angle_deg'] ?? 0.0),
                'equipment_key' => $record['equipment_key'] ?? 'flatbed_standard',
                'focus_mm'      => isset($record['focus_mm']) && is_numeric($record['focus_mm']) ? (float) $record['focus_mm'] : null,
                'target_z_mm'   => isset($record['target_z_mm']) && is_numeric($record['target_z_mm']) ? (float) $record['target_z_mm'] : null,
                'focus_control' => $record['focus_control'] ?? null,
            ];
        });

        return $claimedWireJob;
    }

    /**
     * Nimmt die Ausführungs-Quittung (inkl. optionalem Live-Kamerabild) vom Werkstatt Mini-PC entgegen.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function completeRelayJob(string $jobId, array $payload): ?array
    {
        if (!self::isValidRelayJobId($jobId)) {
            return null;
        }

        if (array_key_exists('laser_online', $payload) && $payload['laser_online'] !== null) {
            $this->recordMiniPcRelayHeartbeat((bool) $payload['laser_online']);
        }

        $targetFullPath = Storage::disk('public')->path('bestellungen/xtool/background.jpg');

        $cameraSaved = false;
        if (!empty($payload['warped_jpeg_base64']) && is_string($payload['warped_jpeg_base64'])) {
            $warpedBytes = base64_decode(preg_replace('/\s+/', '', $payload['warped_jpeg_base64']) ?? '', true);
            if (is_string($warpedBytes) && strlen($warpedBytes) > 5120) {
                $imgInfo = @getimagesizefromstring($warpedBytes);
                if (is_array($imgInfo) && ($imgInfo[2] ?? 0) === IMAGETYPE_JPEG) {
                    $cameraSaved = $this->writeFileAtomicallyShared($targetFullPath, $warpedBytes);
                }
            }
        } elseif (!empty($payload['raw_jpeg_base64']) && is_string($payload['raw_jpeg_base64'])) {
            $rawBytes = base64_decode(preg_replace('/\s+/', '', $payload['raw_jpeg_base64']) ?? '', true);
            if (is_string($rawBytes) && strlen($rawBytes) > 5120) {
                $cameraSaved = $this->warpRawCameraImageWithCalib($rawBytes, $targetFullPath);
            }
        }

        $completedRecord = null;

        $this->withRelayLock(function () use ($jobId, $payload, $cameraSaved, &$completedRecord): void {
            $jobPath = $this->relayJobsDirectory() . DIRECTORY_SEPARATOR . $jobId . '.json';
            if (is_link($jobPath) || !@is_file($jobPath)) {
                return;
            }

            $decoded = json_decode((string) @file_get_contents($jobPath), true);
            if (!is_array($decoded)) {
                return;
            }

            $decoded['status'] = 'completed';
            $decoded['completed_at'] = now('Europe/Berlin')->toIso8601String();
            // Große Nutzlast nach Ausführung freigeben
            $decoded['xf_base64'] = null;
            $decoded['result'] = [
                'ok'           => (bool) ($payload['ok'] ?? false),
                'laser_online' => (bool) ($payload['laser_online'] ?? false),
                'state'        => isset($payload['state']) && $payload['state'] !== null
                    ? mb_substr((string) $payload['state'], 0, 80)
                    : null,
                'error'        => isset($payload['error']) && $payload['error'] !== null && trim((string) $payload['error']) !== ''
                    ? mb_substr(trim((string) $payload['error']), 0, 500)
                    : null,
                'camera_saved' => $cameraSaved,
            ];

            $this->atomicWriteJsonFile($jobPath, $decoded);
            $completedRecord = $decoded;
        });

        return $completedRecord;
    }

    /**
     * Wartet synchron (in 150-ms-Schritten) auf die Fertigstellung eines Relay-Jobs durch den Mini-PC.
     *
     * @return array<string, mixed>
     */
    public function waitForRelayJobCompletion(string $jobId, float $timeoutSeconds = 9.0): array
    {
        if (!self::isValidRelayJobId($jobId)) {
            return [
                'ok'           => false,
                'laser_online' => false,
                'state'        => 'INVALID_JOB_ID',
                'error'        => 'Ungültige Relay-Job-ID.',
            ];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $jobPath = $this->relayJobsDirectory() . DIRECTORY_SEPARATOR . $jobId . '.json';
        $deadline = microtime(true) + max(0.05, $timeoutSeconds);

        while (microtime(true) < $deadline) {
            clearstatcache(true, $jobPath);
            if (@is_file($jobPath) && !is_link($jobPath)) {
                $raw = @file_get_contents($jobPath);
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && ($decoded['status'] ?? '') === 'completed') {
                        @unlink($jobPath);
                        return is_array($decoded['result'] ?? null)
                            ? $decoded['result']
                            : ['ok' => true, 'laser_online' => true, 'state' => 'COMPLETED', 'error' => null];
                    }
                }
            }

            usleep(150000);
        }

        @unlink($jobPath);

        return [
            'ok'           => false,
            'laser_online' => false,
            'state'        => 'RELAY_TIMEOUT',
            'error'        => 'Zeitüberschreitung der Werkstatt-Brücke (Mini-PC hat den Laser-Befehl nicht rechtzeitig quittiert).',
        ];
    }

    private function purgeExpiredRelayJobsUnlocked(float $maxAgeSeconds = 60.0): void
    {
        $jobsDir = $this->relayJobsDirectory();
        $now = microtime(true);

        foreach (glob($jobsDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (is_link($file) || !@is_file($file)) {
                continue;
            }
            $mtime = (int) (@filemtime($file) ?: 0);
            if ($mtime > 0 && ($now - $mtime) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function atomicWriteJsonFile(string $targetPath, array $data): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $json = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $tmpPath = $targetPath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmpPath, $json, LOCK_EX) === false) {
            @unlink($tmpPath);
            return;
        }
        @chmod($tmpPath, 0600);
        if (!@rename($tmpPath, $targetPath)) {
            @unlink($tmpPath);
        }
    }

    private function withRelayLock(callable $callback): void
    {
        $lockPath = $this->relayLockPath();
        $fp = @fopen($lockPath, 'c+');
        if (!is_resource($fp)) {
            $callback();
            return;
        }
        @chmod($lockPath, 0600);

        @flock($fp, LOCK_EX);
        try {
            $callback();
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}
