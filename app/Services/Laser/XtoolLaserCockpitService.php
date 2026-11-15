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

    private const QUEUE_FILE = 'system/xtool_queue/jobs.json';
    private const LOCK_FILE = 'system/xtool_queue/jobs.lock';
    private const MAX_HISTORY = 25;

    /**
     * Synchronisiert das entzerrte 2000x2000 px (200x200 mm) Kamerabild (background.jpg)
     * aus dem lokalen xTool Studio AppData-Verzeichnis oder direkt vom Laser.
     *
     * @return array{camera_path: string, camera_url: string, updated_at: string, source: string, laser_online: bool, laser_ip: string}
     */
    public function syncCameraFromHostOrLaser(?float $focusMm = null, bool $forceCheck = false, string $laserIp = self::DEFAULT_LASER_IP): array
    {
        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($laserIp, self::DEFAULT_LASER_IP);
        $publicDisk = Storage::disk('public');
        $relativeBgPath = 'bestellungen/xtool/background.jpg';
        $targetFullPath = $publicDisk->path($relativeBgPath);

        if (!is_dir(dirname($targetFullPath))) {
            @mkdir(dirname($targetFullPath), 0775, true);
        }

        $source = 'cached';
        $hasLocalFile = @is_file($targetFullPath) && (int) @filesize($targetFullPath) > 10000;
        $laserOnline = false;

        if ((!$hasLocalFile || $forceCheck) && !app()->runningUnitTests()) {
            // 1. Direkter Live-Kamera-Abruf vom xTool F2 Ultra UV über das native V2-WebSocket-Protokoll (TLS Port 28900)
            if ($forceCheck) {
                if ($this->captureAndCalibrateLiveCameraFromLaserV2($safeIp, $targetFullPath)) {
                    $source = 'laser_live_v2_ws';
                    $hasLocalFile = true;
                    $laserOnline = true;
                }
            }

            if (!$laserOnline) {
                $laserOnline = $this->probeLaserReachability($safeIp);
            }

            // 2. Fallback: HTTP-Kamera-Snapshot versuchen (ältere Firmware / Bridge)
            if ($forceCheck && $laserOnline && $source !== 'laser_live_v2_ws') {
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
                            @file_put_contents($targetFullPath, $liveBytes);
                            $source = 'laser_live_http';
                            $hasLocalFile = true;
                            break;
                        }
                    }
                }
            }

            // 3. Fallback: Host xTool Studio AppData-Verzeichnis & Docker-Bridge-Spiegel prüfen (falls Laser im Standby)
            if (!in_array($source, ['laser_live_v2_ws', 'laser_live_http'], true)) {
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
                                if (is_string($bytes) && strlen($bytes) > 10000) {
                                    @file_put_contents($targetFullPath, $bytes);
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
            if ($forceCheck && @is_file($targetFullPath) && !in_array($source, ['laser_live_v2_ws', 'xtool_calib_warped'], true)) {
                $existingBytes = @file_get_contents($targetFullPath);
                if (is_string($existingBytes) && strlen($existingBytes) > 1024) {
                    @file_put_contents($targetFullPath, $existingBytes);
                }
            }
        } elseif (!app()->runningUnitTests()) {
            $laserOnline = $this->probeLaserReachability($safeIp);
        }

        if (!@is_file($targetFullPath) || (int) @filesize($targetFullPath) < 1024) {
            $this->createSyntheticSlatBedImage($targetFullPath);
            $source = 'synthetic_slat_bed';
        } elseif ($forceCheck && app()->runningUnitTests()) {
            $existingBytes = @file_get_contents($targetFullPath);
            if (is_string($existingBytes) && strlen($existingBytes) > 1024) {
                @file_put_contents($targetFullPath, $existingBytes);
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
        if (!is_dir(dirname($targetFullPath))) {
            @mkdir(dirname($targetFullPath), 0775, true);
        }

        imagejpeg($img, $targetFullPath, 92);
        imagedestroy($img);

        return $this->syncCameraFromHostOrLaser(null, false);
    }

    /**
     * Prüft per schnellem Socket-Connect (max. 250 ms), ob der xTool F2 Ultra UV im lokalen WLAN erreichbar ist.
     * Port 28900 ist der native V2-TLS-WebSocket-Port des xTool F2 Ultra UV (GS009-CLASS-4).
     */
    public function probeLaserReachability(string $ip = self::DEFAULT_LASER_IP): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($ip, self::DEFAULT_LASER_IP);
        foreach ([self::DEFAULT_V2_PORT, 8080, 8329] as $port) {
            $errno = 0;
            $errstr = '';
            $fp = @fsockopen($safeIp, $port, $errno, $errstr, 0.25);
            if (is_resource($fp)) {
                fclose($fp);
                return true;
            }
        }

        return false;
    }

    /**
     * Löst direkt auf dem xTool F2 Ultra UV (TLS/WSS Port 28900, V2 Binary Frame Protocol 0xBABE)
     * einen physischen Kamera-Snapshot (/v1/camera/snap -> 4000x3000 JPEG) aus, lädt ihn über den
     * file_stream-Kanal herunter und entzerrt ihn anhand des 21x21 Werkskalibrierungs-Rasters (global_calib.json)
     * auf das exakte 2000x2000 px (200x200 mm, 10 px = 1 mm) Arbeitsflächenbild.
     * Funktioniert komplett autonom ohne geöffnetes xTool Studio!
     */
    public function captureAndCalibrateLiveCameraFromLaserV2(
        string $ip = self::DEFAULT_LASER_IP,
        ?string $targetBackgroundPath = null
    ): bool {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($ip, self::DEFAULT_LASER_IP);
        $publicDisk = Storage::disk('public');
        $targetBackgroundPath = $targetBackgroundPath ?: $publicDisk->path('bestellungen/xtool/background.jpg');
        $hostAppdataDir = $publicDisk->path('bestellungen/xtool/host_appdata');
        if (!is_dir($hostAppdataDir)) {
            @mkdir($hostAppdataDir, 0775, true);
        }

        $uuid = (string) Str::uuid();
        $mainWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=instruction");
        if (!is_resource($mainWs)) {
            return false;
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
                return false;
            }

            $fileWs = $this->v2WsConnect($safeIp, self::DEFAULT_V2_PORT, "/websocket?id={$uuid}&function=file_stream");
            if (!is_resource($fileWs)) {
                return false;
            }

            // Falls global_calib.json noch nicht lokal gecacht ist, direkt vom Laser herunterladen (filetype = 3)
            $calibPath = $this->resolveOrDownloadGlobalCalibPath($mainWs, $fileWs, $txId, $sessionToken, $hostAppdataDir);

            // 1. IR-LED & roten/blauen Positionierlaser kurz ausschalten, damit kein Überstrahlen auf Kristall/Edelstahl entsteht
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/peripheral/param', ['type' => 'ir_led'], ['action' => 'off', 'index' => 0]);
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/laser-head/parameter', [], ['action' => 'power', 'ctrl' => 'set', 'value' => 0]);

            // 2. Kamera initialisieren & Live-Foto auf dem Laser auslösen
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'POST', '/v1/camera', ['exp' => 0, 'stream' => '0'], []);
            $snap = $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'GET', '/v1/camera/snap', ['name' => 'main', 'timeOut' => 30000], []);
            $remoteFile = (string) ($snap['data']['filename'] ?? $snap['data']['params']['filename'] ?? '');

            $rawJpegBytes = null;
            if ($remoteFile !== '') {
                // 3. 4000x3000 Raw-JPEG über Kanal 9 herunterladen (filetype = 5)
                $rawJpegBytes = $this->v2DownloadFileFromLaser($mainWs, $fileWs, $txId, $sessionToken, $remoteFile, 5, 9);
            }

            // 4. IR-LED & Positionierlaser wieder einschalten
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/peripheral/param', ['type' => 'ir_led'], ['action' => 'on', 'index' => 0]);
            $this->v2SendJsonCmd($mainWs, $txId, $sessionToken, 'PUT', '/v1/laser-head/parameter', [], ['action' => 'power', 'ctrl' => 'set', 'value' => 128]);

            if (!is_string($rawJpegBytes) || strlen($rawJpegBytes) < 15000) {
                return false;
            }

            // Raw-Capture (4000x3000) sichern
            @file_put_contents($hostAppdataDir . '/capture.jpg', $rawJpegBytes);

            // 5. Mit 21x21 Werkskalibrierung (global_calib.json) in das 2000x2000 px Arbeitsflächenbild (10 px = 1 mm) entzerren
            $warpedOk = $this->warpRawCameraImageWithCalib($rawJpegBytes, $targetBackgroundPath, $calibPath);
            if ($warpedOk && @is_file($targetBackgroundPath)) {
                @copy($targetBackgroundPath, $hostAppdataDir . '/background.jpg');
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
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
     * Entzerrt das rohe 4000x3000 px Kamerabild des xTool F2 Ultra UV anhand des 21x21 Stützpunktrasters (data0)
     * aus global_calib.json auf das exakt maßstabsgetreue 2000x2000 px (200x200 mm, 10 px = 1 mm) Arbeitsflächenbild.
     */
    public function warpRawCameraImageWithCalib(string $rawJpegBytes, string $targetBackgroundPath, ?string $calibJsonPath = null): bool
    {
        $src = @imagecreatefromstring($rawJpegBytes);
        if (!$src) {
            return false;
        }

        if (!is_dir(dirname($targetBackgroundPath))) {
            @mkdir(dirname($targetBackgroundPath), 0775, true);
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

        $saved = @imagejpeg($dst, $targetBackgroundPath, 92);
        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $saved;
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

    private function resolveOrDownloadGlobalCalibPath($mainWs, $fileWs, int &$txId, string $sessionToken, string $hostAppdataDir): ?string
    {
        $localCalib = $hostAppdataDir . '/global_calib.json';
        if (@is_file($localCalib) && (int) @filesize($localCalib) > 1000) {
            return $localCalib;
        }

        $hostMountCalib = '/mnt/c/Users/konta/AppData/Roaming/xTool Studio/atomm/' . self::DEVICE_EXT_ID . '/' . self::DEVICE_SN_CODE . '/global_calib.json';
        if (@is_file($hostMountCalib) && (int) @filesize($hostMountCalib) > 1000) {
            @copy($hostMountCalib, $localCalib);
            return $localCalib;
        }

        $calibBytes = $this->v2DownloadFileFromLaser($mainWs, $fileWs, $txId, $sessionToken, 'global_calib.json', 3, 2);
        if (is_string($calibBytes) && strlen($calibBytes) > 1000 && str_contains($calibBytes, 'data0')) {
            @file_put_contents($localCalib, $calibBytes);
            return $localCalib;
        }

        return null;
    }

    private function v2DownloadFileFromLaser($mainWs, $fileWs, int &$txId, string $sessionToken, string $remoteFilename, int $fileType, int $channel): ?string
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
    private function v2WsConnect(string $ip, int $port, string $path)
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

    private function v2SendJsonCmd($ws, int &$txId, string $sessionToken, string $method, string $url, array $params = [], array $data = []): ?array
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
    public function detectBlankPositionOnBed(?Product $product = null, array $itemConfig = [], string $side = 'front', bool $forceCameraSync = false): array
    {
        $camInfo = $this->syncCameraFromHostOrLaser(null, $forceCameraSync);
        [$blankWidthMm, $blankHeightMm] = $this->resolvePhysicalBlankDimensionsMm($product, $itemConfig);

        $defaultResult = [
            'center_x_mm'       => 100.0,
            'center_y_mm'       => 100.0,
            'angle_deg'         => 0.0,
            'blank_width_mm'    => round($blankWidthMm, 2),
            'blank_height_mm'   => round($blankHeightMm, 2),
            'confidence'        => 50,
            'detected'          => false,
            'camera_url'        => $camInfo['camera_url'],
            'camera_updated_at' => $camInfo['updated_at'],
            'laser_online'      => $camInfo['laser_online'],
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
        // Am linken (x=6..22) und rechten (x=378..394) Außenrand hat das schwarze Lamellenrost Luminanz < 85,
        // während die helle Metallkante unten (ab ca. Y = 164 mm / y = 328) auf > 105 springt.
        $maxBedY = 384;
        for ($y = 280; $y < 392; $y++) {
            $mSum = 0;
            for ($x = 6; $x < 22; $x++) {
                $mSum += $lum[$y][$x];
            }
            for ($x = 378; $x < 394; $x++) {
                $mSum += $lum[$y][$x];
            }
            if (($mSum / 32.0) > 100.0) {
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

        // 3. Gesättigte Objekt-Maske (0.0 .. 1.0, unempfindlich gegen punktuelle LED-Spiegelungen) & 2D-Integralbild
        // Auf unbedeckten Lamellen liegt in jedem +/- 6,5 mm Fenster (dx = -13..+13) mindestens ein tiefschwarzer
        // Lamellen-Spalt (minLocal < 32). Sobald ein Rohling aufliegt, wird dieser Spalt verdeckt (minLocal > 36).
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
                    $val = (float) $lum[$y][$x];
                    $diff = abs($val - $slatProfile[$x]);
                    $edgeH = abs($val - (float) $lum[$y - 1][$x]);

                    $minLocal = 255;
                    for ($dx = -13; $dx <= 13; $dx += 2) {
                        $v = $lum[$y][$x + $dx];
                        if ($v < $minLocal) {
                            $minLocal = $v;
                        }
                    }

                    if ($minLocal > 36 && ($diff > 15.0 || $val > 90.0)) {
                        $score = 1.0;
                    } elseif ($diff > 38.0 || ($diff > 20.0 && $edgeH > 12.0)) {
                        $score = min(1.0, ($diff + ($edgeH * 1.5)) / 42.0);
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
        // Wichtig: Ohne die +/- 6,5 mm Lamellen-Erosion aus Schritt 3 messen, damit dunkle Lamellenspalte links neben dem Glas
        // die linke Rohlingskante nicht nach innen verschieben (1:1 Kanten-Deckung auf 0,1 mm genau).
        $rx1 = max(20, (int) round($bestCxCell - ($winW * 0.62)));
        $rx2 = min($gridW - 22, (int) round($bestCxCell + ($winW * 0.62)));
        $ry1 = max(16, (int) round($bestCyCell - ($winH * 0.62)));
        $ry2 = min($maxBedY, (int) round($bestCyCell + ($winH * 0.62)));

        $rimPresenceAt = static function (int $x, int $y) use (&$lum, &$cellMap): float {
            if ((float) $lum[$y][$x] > 105.0) {
                return 1.0;
            }
            return (float) $cellMap[$y][$x];
        };

        $bandHalfH = max(12, (int) round(($ry2 - $ry1) * 0.18));
        $bandY1 = max($ry1, (int) round($bestCyCell - $bandHalfH));
        $bandY2 = min($ry2, (int) round($bestCyCell + $bandHalfH));

        $colBand = array_fill($rx1, max(1, $rx2 - $rx1 + 1), 0.0);
        for ($y = $bandY1; $y <= $bandY2; $y++) {
            for ($x = $rx1; $x <= $rx2; $x++) {
                $colBand[$x] += $rimPresenceAt($x, $y);
            }
        }

        $peakCol = !empty($colBand) ? max($colBand) : 0.0;
        $bx1 = $rx1;
        $bx2 = $rx2;
        if ($peakCol > 2.0) {
            for ($x = $rx1; $x <= $rx2; $x++) {
                if ($colBand[$x] >= $peakCol * 0.32) {
                    $bx1 = $x;
                    break;
                }
            }
            for ($x = $rx2; $x >= $rx1; $x--) {
                if ($colBand[$x] >= $peakCol * 0.32) {
                    $bx2 = $x;
                    break;
                }
            }
        }

        $finalCxCell = ($peakCol > 2.0) ? (($bx1 + $bx2) * 0.5) : (float) $bestCxCell;

        $bandHalfW = max(6, (int) round(($bx2 - $bx1) * 0.08));
        $bandX1 = max($rx1, (int) round($finalCxCell - $bandHalfW));
        $bandX2 = min($rx2, (int) round($finalCxCell + $bandHalfW));

        $rowBand = array_fill($ry1, max(1, $ry2 - $ry1 + 1), 0.0);
        for ($y = $ry1; $y <= $ry2; $y++) {
            for ($x = $bandX1; $x <= $bandX2; $x++) {
                $rowBand[$y] += $cellMap[$y][$x];
            }
        }

        $peakRow = !empty($rowBand) ? max($rowBand) : 0.0;
        $by1 = $ry1;
        $by2 = $ry2;
        if ($peakRow > 2.0) {
            for ($y = $ry1; $y <= $ry2; $y++) {
                if ($rowBand[$y] >= $peakRow * 0.32) {
                    $by1 = $y;
                    break;
                }
            }
            for ($y = $ry2; $y >= $ry1; $y--) {
                if ($rowBand[$y] >= $peakRow * 0.32) {
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

        $centerXMm = round(max(15.0, min(185.0, $finalCxCell * 0.5)), 1);
        $centerYMm = round(max(15.0, min(185.0, $finalCyCell * 0.5)), 1);
        $confidence = (int) round(max(55.0, min(99.0, 74.0 + ($bestRatio * 28.0))));

        return [
            'center_x_mm'       => $centerXMm,
            'center_y_mm'       => $centerYMm,
            'angle_deg'         => $angleDeg,
            'blank_width_mm'    => round($blankWidthMm, 2),
            'blank_height_mm'   => round($blankHeightMm, 2),
            'confidence'        => $confidence,
            'detected'          => $bestRatio > 0.12,
            'camera_url'        => $camInfo['camera_url'],
            'camera_updated_at' => $camInfo['updated_at'],
            'laser_online'      => $camInfo['laser_online'],
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
        return 1098; // Edelstahl / Metall / Leder / Standard-UV-Oberfläche
    }

    /**
     * Übersetzt das Equipment / den Prozessmodus in den xTool Studio activeMode-Key.
     */
    public static function resolveXtoolActiveMode(string $equipmentKey, string $processMode = ''): string
    {
        return match ($equipmentKey) {
            'rotary_ra3'     => 'LASER_CYLINDER',
            'conveyor_batch' => 'LASER_CONVEYOR_FEEDER',
            'inner_3d'       => 'INNER_LASER',
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
        return 'greyscale';
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
            'M105 S1',
            'M108 S1',
            'M17 S1',
            'M207 S0',
            'M106 S0',
            '# GS009-4 HEAD_END',
            "G0X{$pts[0]['x']}Y{$pts[0]['y']}F1440000",
            "G1X{$pts[1]['x']}Y{$pts[1]['y']}S133F1440000",
            "G1X{$pts[2]['x']}Y{$pts[2]['y']}",
            "G1X{$pts[3]['x']}Y{$pts[3]['y']}",
            "G1X{$pts[0]['x']}Y{$pts[0]['y']}",
            '# GS009-4 TAIL',
            'G18',
            'M18',
            'M105 S0',
            '# GS009-4 TAIL_END',
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
        float $angleDeg = 0.0
    ): array {
        $exportService = app(FileDownloadService::class);
        $payload = $exportService->buildLaserDesignPayload($itemId, $side, true);

        $item = $payload['item'];
        $product = $payload['product'];
        $laserSummary = $product ? $product->getLaserInstructionSummary() : Product::evaluateLaserInstructionSettings([], false);

        if ($centerXMm === null || $centerYMm === null) {
            $detected = $this->detectBlankPositionOnBed($product, $item->configuration ?? [], $side);
            $centerXMm = $centerXMm ?? $detected['center_x_mm'];
            $centerYMm = $centerYMm ?? $detected['center_y_mm'];
            if (abs($angleDeg) < 0.01) {
                $angleDeg = $detected['angle_deg'];
            }
        }

        $widthMm = (float) $payload['widthMm'];
        $heightMm = (float) $payload['heightMm'];
        $topLeftX = round($centerXMm - ($widthMm / 2.0), 4);
        $topLeftY = round($centerYMm - ($heightMm / 2.0), 4);

        $engravePngBytes = $payload['stageEngravePngBytes'];
        $engraveSha256 = hash('sha256', $engravePngBytes);
        $resourceRelPath = 'resources/' . $engraveSha256 . '.png';
        $base64DataUrl = 'data:image/png;base64,' . base64_encode($engravePngBytes);

        $projectId = (string) Str::uuid();
        $canvasId = (string) Str::uuid();
        $engraveDisplayId = (string) Str::uuid();
        $ghostContourDisplayId = (string) Str::uuid();
        $groupTag = 'G-' . Str::uuid();

        $activeMode = self::resolveXtoolActiveMode(
            (string) ($laserSummary['equipment_key'] ?? 'flatbed_standard'),
            (string) ($laserSummary['process_mode'] ?? '')
        );
        $materialId = self::resolveXtoolMaterialId((string) ($laserSummary['material'] ?? 'Edelstahl'));
        $focusMm = max(0.5, (float) ($laserSummary['focus_mm'] ?: 3.0));
        $dotDurationUs = max(10, (int) ($laserSummary['dot_duration_us'] ?: 120));
        $powerPercent = max(1, min(100, (int) ($laserSummary['power_percent'] ?: 70)));
        $dpi = max(100, (int) ($laserSummary['dpi'] ?: 500));
        $passes = max(1, (int) ($laserSummary['passes'] ?: 1));
        $frequencyKhz = max(20, (int) ($laserSummary['frequency_khz'] ?: 60));
        $bitmapMode = self::resolveXtoolBitmapMode((string) ($laserSummary['bitmap_mode'] ?? 'Graustufen'));
        $scanMode = str_contains(mb_strtolower((string) ($laserSummary['engrave_mode'] ?? 'bi')), 'uni') ? 'singleMode' : 'zMode';

        $orderNumber = (string) ($item->order->order_number ?? 'Auftrag');
        $safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $orderNumber) ?: 'Auftrag';
        $sideLabel = $side === 'back' ? 'Rueckseite' : 'Vorderseite';
        $projectName = "SF-{$safeOrderNumber}-{$sideLabel}";

        // 1. Ghost-Contour SVG dPath im lokalen Koordinatensystem (0..widthMm, 0..heightMm)
        $ghostDPath = $payload['ghostContourDPath'];

        // 2. displays-0.json (Gravur-Bitmap auf Ebene #000000 + 1:1 Geister-Kontur auf Ebene #00bcd4)
        $displaysJson = [
            'displays' => [
                [
                    'id'              => $engraveDisplayId,
                    'name'            => 'Seelenfunke Gravur (' . ($item->product_name ?? 'Unikat') . ')',
                    'type'            => 'BITMAP',
                    'visible'         => true,
                    'layerColor'      => '#000000',
                    'x'               => $topLeftX,
                    'y'               => $topLeftY,
                    'angle'           => round($angleDeg, 2),
                    'scale'           => [
                        'x' => round($widthMm / max(1, $payload['stagePixelWidth']), 6),
                        'y' => round($heightMm / max(1, $payload['stagePixelHeight']), 6),
                    ],
                    'skew'            => ['x' => 0, 'y' => 0],
                    'pivot'           => ['x' => 0, 'y' => 0],
                    'localSkew'       => ['x' => 0, 'y' => 0],
                    'offsetX'         => $topLeftX,
                    'offsetY'         => $topLeftY,
                    'lockRatio'       => true,
                    'isClosePath'     => true,
                    'zOrder'          => 1,
                    'sourceId'        => $engraveDisplayId,
                    'groupTag'        => $groupTag,
                    'layerTag'        => '#000000',
                    'fillColor'       => '#000000',
                    'visibleState'    => true,
                    'lockState'       => false,
                    'resourceOrigin'  => '',
                    'customData'      => new \stdClass(),
                    'rootComponentId' => '',
                    'minCanvasVersion'=> '0.0.0',
                    'width'           => $widthMm,
                    'height'          => $heightMm,
                    'base64'          => $base64DataUrl,
                    'originWidth'     => $payload['stagePixelWidth'],
                    'originHeight'    => $payload['stagePixelHeight'],
                    'DPI'             => $dpi,
                    'isFill'          => false,
                    'lineColor'       => '#FF0000',
                    'stroke'          => [
                        'color'     => 16711680,
                        'width'     => 0.1,
                        'alpha'     => 0,
                        'cap'       => 'butt',
                        'join'      => 'miter',
                        'miterLimit'=> 4,
                        'alignment' => 0.5,
                    ],
                    'fill'            => [
                        'color'   => 0,
                        'alpha'   => 1,
                        'visible' => false,
                    ],
                    'totalFrame'      => 1,
                    'Enhance'         => [
                        'sharpness'  => 0,
                        'embossment' => 0,
                        'blur'       => 0,
                        'denoise'    => 0,
                    ],
                    'color'           => [
                        'bright'     => 0,
                        'contrast'   => 0,
                        'saturation' => 0,
                        'whiteClip'  => 255,
                        'blackClip'  => 0,
                    ],
                    'isGrayscale'     => true,
                    'isInvert'        => false,
                    'resourcePath'    => $resourceRelPath,
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
                    'scale'           => ['x' => 1, 'y' => 1],
                    'skew'            => ['x' => 0, 'y' => 0],
                    'pivot'           => ['x' => 0, 'y' => 0],
                    'localSkew'       => ['x' => 0, 'y' => 0],
                    'offsetX'         => $topLeftX,
                    'offsetY'         => $topLeftY,
                    'lockRatio'       => true,
                    'isClosePath'     => true,
                    'zOrder'          => 2,
                    'sourceId'        => $ghostContourDisplayId,
                    'groupTag'        => $groupTag,
                    'layerTag'        => '#00bcd4',
                    'fillColor'       => '#00bcd4',
                    'visibleState'    => true,
                    'lockState'       => false,
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

        // 3. canvases/<canvasId>.json (inkl. BATCH_TEMPLATE für Serien- und Laufband-Gravur)
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
            'groupData'       => [
                $groupTag => [
                    'groupName' => 'Gravur + Ausricht-Kontur',
                    'groupTag'  => $groupTag,
                    'contain'   => [$engraveDisplayId, $ghostContourDisplayId],
                ],
            ],
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
            'batchData'       => [
                'entryType' => 'BATCH_TEMPLATE',
                'bindings'  => [],
            ],
            'chunkLayout'     => [
                [
                    'file'  => 'displays-0.json',
                    'count' => 2,
                ],
            ],
        ];

        // 4. devices/device-GS009-CLASS-4-1.json (inkl. ignoredDisplayIds für die Geister-Kontur!)
        $profileKey = implode('::', [$activeMode, $materialId, 'BITMAP', 'BITMAP_ENGRAVING', 'uv', 0]);
        $patchId = $engraveDisplayId . '::' . $profileKey;

        $deviceJson = [
            'id'         => self::DEVICE_ID_KEY,
            'extId'      => self::DEVICE_EXT_ID,
            'extName'    => self::DEVICE_EXT_NAME,
            'processing' => [
                $canvasId => [
                    'activeMode' => $activeMode,
                    'modes'      => [
                        $activeMode => [
                            'data'              => [
                                'material'              => $materialId,
                                'lightSourceMode'       => 'uv',
                                'isProcessByLayer'      => false,
                                'pathPlanning'          => 'auto',
                                'fillPlanning'          => 'separate',
                                'scanDirection'         => 'topToBottom',
                                'enableOddEvenKerf'     => false,
                                'perimeter'             => 100,
                                'diameter'              => round($widthMm / M_PI, 2),
                                'thickness'             => $focusMm,
                                'Drehachsenversatz'     => 0,
                                'Drehrichtung'          => 'clockwise',
                                'isCheckFloorExist'     => false,
                            ],
                            'profiles'          => [
                                $profileKey => [
                                    'key'              => $profileKey,
                                    'processingType'   => 'BITMAP_ENGRAVING',
                                    'ProcessingLightSource' => 'uv',
                                    'base'             => [
                                        'source' => 'custom',
                                        'values' => [
                                            'dotDuration'    => $dotDurationUs,
                                            'power'          => $powerPercent,
                                            'dpi'            => $dpi,
                                            'repeat'         => $passes,
                                            'frequency'      => $frequencyKhz,
                                            'bitmapMode'     => $bitmapMode,
                                            'bitmapScanMode' => $scanMode,
                                            'crossAngle'     => false,
                                            'enableKerf'     => false,
                                            'kerfDistance'   => 0,
                                        ],
                                    ],
                                ],
                            ],
                            'patches'           => [
                                $patchId => [
                                    'id'      => $patchId,
                                    'baseKey' => $profileKey,
                                    'delta'   => new \stdClass(),
                                ],
                            ],
                            'bindings'          => [
                                $engraveDisplayId => $patchId,
                            ],
                            'ignoredDisplayIds' => [
                                $ghostContourDisplayId,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 5. project.json
        $nowMs = (int) round(microtime(true) * 1000);
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

        $persistenceMetaJson = [
            'schemaVersion' => '2.0.0',
            'protocol'      => 'xcs-workspace-v2',
        ];

        $tmpZipPath = tempnam(sys_get_temp_dir(), 'sf_xcs_') . '.xcs';
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
        $zip->addFromString('thumbnail.png', $engravePngBytes);
        $zip->close();

        $zipBytes = (string) file_get_contents($tmpZipPath);
        @unlink($tmpZipPath);

        $filename = "xTool-Projekt-{$safeOrderNumber}-Pos-" . substr((string) $item->id, 0, 8) . "-{$sideLabel}.xcs";

        return [
            'bytes'       => $zipBytes,
            'filename'    => $filename,
            'center_x_mm' => $centerXMm,
            'center_y_mm' => $centerYMm,
            'angle_deg'   => $angleDeg,
            'width_mm'    => $widthMm,
            'height_mm'   => $heightMm,
        ];
    }

    /**
     * Erzeugt das direkte xTool F2 Ultra UV Druckpaket (.xf POSIX TAR-Archiv mit preview.jpg,
     * border.gcode, motion.bgcode und description.json).
     *
     * @return array{bytes: string, filename: string, border_gcode: string, description: array, center_x_mm: float, center_y_mm: float, angle_deg: float}
     */
    public function generateXfPackageBytes(
        $itemId,
        string $side = 'front',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0
    ): array {
        $exportService = app(FileDownloadService::class);
        $payload = $exportService->buildLaserDesignPayload($itemId, $side, true);

        $item = $payload['item'];
        $product = $payload['product'];
        $laserSummary = $product ? $product->getLaserInstructionSummary() : Product::evaluateLaserInstructionSettings([], false);

        if ($centerXMm === null || $centerYMm === null) {
            $detected = $this->detectBlankPositionOnBed($product, $item->configuration ?? [], $side);
            $centerXMm = $centerXMm ?? $detected['center_x_mm'];
            $centerYMm = $centerYMm ?? $detected['center_y_mm'];
            if (abs($angleDeg) < 0.01) {
                $angleDeg = $detected['angle_deg'];
            }
        }

        $widthMm = (float) $payload['widthMm'];
        $heightMm = (float) $payload['heightMm'];
        $borderGcode = $this->generateBorderGcode($centerXMm, $centerYMm, $widthMm, $heightMm, $angleDeg);

        $activeMode = self::resolveXtoolActiveMode(
            (string) ($laserSummary['equipment_key'] ?? 'flatbed_standard'),
            (string) ($laserSummary['process_mode'] ?? '')
        );
        $materialId = self::resolveXtoolMaterialId((string) ($laserSummary['material'] ?? 'Edelstahl'));
        $focusMm = max(0.5, (float) ($laserSummary['focus_mm'] ?: 3.0));
        $dotDurationUs = max(10, (int) ($laserSummary['dot_duration_us'] ?: 120));
        $powerPercent = max(1, min(100, (int) ($laserSummary['power_percent'] ?: 70)));
        $dpi = max(100, (int) ($laserSummary['dpi'] ?: 500));
        $passes = max(1, (int) ($laserSummary['passes'] ?: 1));
        $frequencyKhz = max(20, (int) ($laserSummary['frequency_khz'] ?: 60));
        $bitmapMode = self::resolveXtoolBitmapMode((string) ($laserSummary['bitmap_mode'] ?? 'Graustufen'));
        $scanMode = str_contains(mb_strtolower((string) ($laserSummary['engrave_mode'] ?? 'bi')), 'uni') ? 'singleMode' : 'zMode';

        $previewJpgBytes = $this->buildPreviewJpeg200($payload['stageEngravePngBytes']);
        $motionBgcodeBytes = $this->buildLaserMotionStream(
            $payload['stageEngravePngBytes'],
            $centerXMm,
            $centerYMm,
            $widthMm,
            $heightMm,
            $angleDeg,
            $powerPercent,
            $dotDurationUs,
            $frequencyKhz,
            $passes
        );

        $description = [
            'mode'                   => $activeMode,
            'light'                  => 'uv',
            'create_time'            => (int) round(microtime(true) * 1000),
            'total_Time'             => max(5, (int) round(($widthMm * $heightMm * $passes) / 18.0)),
            'Compatible Machine Type'=> [self::DEVICE_EXT_ID],
            'focus'                  => $focusMm,
            'thickness'              => $focusMm,
            'processingParams'       => [[
                'processingType' => 'BITMAP_ENGRAVING',
                'lightSource'    => 'uv',
                'bitmapMode'     => $bitmapMode,
                'bitmapScanMode' => $scanMode,
                'dotDuration'    => $dotDurationUs,
                'dpi'            => $dpi,
                'power'          => $powerPercent,
                'frequency'      => $frequencyKhz,
                'repeat'         => $passes,
            ]],
            'material'               => (string) $materialId,
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

        $descriptionJsonBytes = (string) json_encode($description, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $tarBytes = $this->packPosixTarArchive([
            'preview.jpg'      => $previewJpgBytes,
            'border.gcode'     => $borderGcode,
            'motion.bgcode'    => $motionBgcodeBytes,
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
            'description'  => $description,
            'center_x_mm'  => $centerXMm,
            'center_y_mm'  => $centerYMm,
            'angle_deg'    => $angleDeg,
        ];
    }

    /**
     * Übergibt einen Rahmen-Vorschau- ("framing") oder Gravur-Auftrag ("engrave") direkt an den Laser
     * bzw. reiht ihn in die verschlüsselte Seelenfunke Laser-Queue (für Funkenwerk / lokale Bridge) ein.
     */
    public function enqueueOrSendLaserJob(
        $itemId,
        string $side = 'front',
        string $jobType = 'engrave',
        ?float $centerXMm = null,
        ?float $centerYMm = null,
        float $angleDeg = 0.0,
        string $laserIp = self::DEFAULT_LASER_IP
    ): array {
        $safeIp = FunkenwerkPrintService::sanitizePrivateLanIp($laserIp, self::DEFAULT_LASER_IP);
        $xfData = $this->generateXfPackageBytes($itemId, $side, $centerXMm, $centerYMm, $angleDeg);
        $xcsData = $this->generateXcsProjectBytes($itemId, $side, $xfData['center_x_mm'], $xfData['center_y_mm'], $xfData['angle_deg']);

        $publicDisk = Storage::disk('public');
        $publicDisk->put('bestellungen/xtool/latest_job.xf', $xfData['bytes']);
        $publicDisk->put('bestellungen/xtool/latest_project.xcs', $xcsData['bytes']);
        $publicDisk->put('bestellungen/xtool/latest_border.gcode', $xfData['border_gcode']);

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

        $job = [
            'id'           => (string) Str::uuid(),
            'type'         => in_array($jobType, ['framing', 'engrave'], true) ? $jobType : 'engrave',
            'status'       => 'pending',
            'laser_ip'     => $safeIp,
            'device_sn'    => self::DEVICE_SN_CODE,
            'order_id'     => (string) ($item->order_id ?? ''),
            'order_number' => (string) ($item->order->order_number ?? 'Auftrag'),
            'item_id'      => (string) $item->id,
            'product_name' => (string) ($item->product_name ?? 'Unikat'),
            'side'         => $side,
            'center_x_mm'  => $xfData['center_x_mm'],
            'center_y_mm'  => $xfData['center_y_mm'],
            'angle_deg'    => $xfData['angle_deg'],
            'width_mm'     => $xcsData['width_mm'],
            'height_mm'    => $xcsData['height_mm'],
            'xcs_filename' => $xcsData['filename'],
            'xf_filename'  => $xfData['filename'],
            'border_gcode' => $xfData['border_gcode'],
            'description'  => $xfData['description'],
            'created_at'   => now()->toIso8601String(),
            'processed_at' => null,
            'error_message'=> null,
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
     * Erzeugt den Scanline-Laser-Bewegungsstrom (motion.bgcode) für die direkte UV-Gravur.
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
        int $passes
    ): string {
        $lines = [
            '# GS009-4 HEAD',
            'M105 S0',
            'M108 S1',
            'M17 S1',
            'M207 S1',
            "M208 P{$powerPercent} D{$dotDurationUs} F{$frequencyKhz} R{$passes}",
            '# GS009-4 HEAD_END',
        ];

        $src = @imagecreatefromstring($pngBytes);
        if ($src) {
            $sampleW = 64;
            $sampleH = 64;
            $small = imagecreatetruecolor($sampleW, $sampleH);
            imagealphablending($small, false);
            imagesavealpha($small, true);
            $trans = imagecolorallocatealpha($small, 255, 255, 255, 127);
            imagefill($small, 0, 0, $trans);
            imagecopyresampled($small, $src, 0, 0, 0, 0, $sampleW, $sampleH, imagesx($src), imagesy($src));
            imagedestroy($src);

            $rad = deg2rad($angleDeg);
            $cos = cos($rad);
            $sin = sin($rad);
            $halfW = $widthMm / 2.0;
            $halfH = $heightMm / 2.0;
            $sPower = max(10, min(1000, $powerPercent * 10));

            for ($y = 0; $y < $sampleH; $y++) {
                $ly = -$halfH + (($y / max(1, $sampleH - 1)) * $heightMm);
                $inRun = false;
                $runStartX = 0.0;
                $runStartY = 0.0;
                $runEndX = 0.0;
                $runEndY = 0.0;

                for ($x = 0; $x < $sampleW; $x++) {
                    $c = imagecolorat($small, $x, $y);
                    $alpha = ($c >> 24) & 0x7F;
                    $gray = $c & 0xFF;
                    $isDark = ($alpha < 110) && ($gray < 235);

                    if ($isDark) {
                        $lx = -$halfW + (($x / max(1, $sampleW - 1)) * $widthMm);
                        $gx = $centerXMm + ($lx * $cos) - ($ly * $sin);
                        $gy = $centerYMm + ($lx * $sin) + ($ly * $cos);
                        if (!$inRun) {
                            $inRun = true;
                            $runStartX = $gx;
                            $runStartY = $gy;
                        }
                        $runEndX = $gx;
                        $runEndY = $gy;
                    } elseif ($inRun) {
                        $lines[] = sprintf('G0X%.3fY%.3fF1440000', $runStartX, $runStartY);
                        $lines[] = sprintf('G1X%.3fY%.3fS%dF60000', $runEndX, $runEndY, $sPower);
                        $inRun = false;
                    }
                }
                if ($inRun) {
                    $lines[] = sprintf('G0X%.3fY%.3fF1440000', $runStartX, $runStartY);
                    $lines[] = sprintf('G1X%.3fY%.3fS%dF60000', $runEndX, $runEndY, $sPower);
                }
            }
            imagedestroy($small);
        }

        $lines[] = '# GS009-4 TAIL';
        $lines[] = 'G18';
        $lines[] = 'M18';
        $lines[] = 'M105 S0';
        $lines[] = '# GS009-4 TAIL_END';
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

        @imagejpeg($img, $targetFullPath, 86);
        imagedestroy($img);
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
}
