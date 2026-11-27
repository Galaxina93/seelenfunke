<?php

namespace App\Services\System;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

class ResilientPusherBroadcaster extends PusherBroadcaster
{
    protected array $endpointConfig;

    protected int $circuitBreakerCooldownSeconds = 15;

    /**
     * @var array<string, int>
     */
    protected static array $noticeLogTimestamps = [];

    public function __construct(Pusher $pusher, bool $jsonp = false, array $endpointConfig = [])
    {
        parent::__construct($pusher, $jsonp);
        $this->endpointConfig = $endpointConfig;
    }

    /**
     * Broadcast the given event without letting an offline WebSocket/Reverb server crash callers.
     *
     * @param  array  $channels
     * @param  string  $event
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $eventName = trim((string) $event);
        if (empty($channels) || $eventName === '') {
            return;
        }

        if ($this->isCircuitOpen()) {
            return;
        }

        try {
            parent::broadcast($channels, $eventName, $payload);
            $this->resetCircuit();
        } catch (\Throwable $e) {
            if (self::isTransientTransportFailure($e)) {
                $this->tripCircuit();
                $this->logThrottled(
                    'notice',
                    'WebSocket/Reverb-Broadcaster nicht erreichbar (' . $this->endpointSummary() . ') für Event ' . $eventName . ': ' . $this->safeErrorMessage($e)
                );

                return;
            }

            $this->logThrottled(
                'warning',
                'WebSocket/Reverb-Broadcast fehlgeschlagen (' . $this->endpointSummary() . ') für Event ' . $eventName . ': ' . $this->safeErrorMessage($e)
            );
        }
    }

    /**
     * Erkennt transiente Netzwerk-, Socket-, DNS- oder Verbindungsfehler zu Reverb/Pusher (z. B. cURL error 7 auf Port 6001).
     */
    public static function isTransientTransportFailure(\Throwable $e): bool
    {
        $cursor = $e;
        $depth = 0;

        while ($cursor !== null && $depth < 4) {
            $msg = strtolower((string) $cursor->getMessage());
            $needles = [
                'curl error 6',
                'curl error 7',
                'curl error 28',
                'curl error 35',
                'curl error 52',
                'curl error 56',
                'could not connect to server',
                'failed to connect to',
                'could not resolve host',
                'connection refused',
                'connection timed out',
                'operation timed out',
                'network is unreachable',
                'empty reply from server',
            ];

            foreach ($needles as $needle) {
                if (str_contains($msg, $needle)) {
                    return true;
                }
            }

            $cursor = $cursor->getPrevious();
            $depth++;
        }

        return false;
    }

    public static function resetProcessState(): void
    {
        self::$noticeLogTimestamps = [];
    }

    protected function endpointSummary(): string
    {
        $options = is_array($this->endpointConfig['options'] ?? null) ? $this->endpointConfig['options'] : [];
        $scheme = (string) ($options['scheme'] ?? 'http');
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $port = (string) ($options['port'] ?? '6001');

        return $scheme . '://' . $host . ':' . $port;
    }

    protected function getCircuitFilePath(): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . '/sf_ws_cb_'
            . md5($this->endpointSummary())
            . '.state';
    }

    protected function isCircuitOpen(): bool
    {
        $cbFile = $this->getCircuitFilePath();
        if ($cbFile === null || !is_file($cbFile)) {
            return false;
        }

        $age = time() - (int) @filemtime($cbFile);
        if ($age < 0 || $age >= $this->circuitBreakerCooldownSeconds) {
            return false;
        }

        return trim((string) @file_get_contents($cbFile)) === '0';
    }

    protected function tripCircuit(): void
    {
        $cbFile = $this->getCircuitFilePath();
        if ($cbFile !== null) {
            @file_put_contents($cbFile, '0', LOCK_EX);
        }
    }

    protected function resetCircuit(): void
    {
        $cbFile = $this->getCircuitFilePath();
        if ($cbFile !== null && is_file($cbFile)) {
            @unlink($cbFile);
        }
    }

    protected function safeErrorMessage(\Throwable $e): string
    {
        $raw = (string) $e->getMessage();
        if (class_exists(SystemErrorLogger::class)) {
            return SystemErrorLogger::sanitizeSensitiveText($raw);
        }

        return $raw;
    }

    protected function logThrottled(string $level, string $message): void
    {
        $key = $level . '|' . $this->endpointSummary();
        $now = time();
        $last = self::$noticeLogTimestamps[$key] ?? 0;

        if (($now - $last) < 300) {
            return;
        }

        self::$noticeLogTimestamps[$key] = $now;

        if ($level === 'warning') {
            Log::warning($message);
        } else {
            Log::notice($message);
        }
    }
}
