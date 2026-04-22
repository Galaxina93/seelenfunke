<?php

namespace App\Listeners;

use App\Models\System\SystemLog;
use Illuminate\Events\Dispatcher;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class BackupEventSubscriber
{
    /**
     * Handle failed backup events.
     */
    public function handleBackupFailed(BackupHasFailed $event): void
    {
        $message = 'Backup ist fehlgeschlagen.';
        if ($event->exception) {
            $message .= ' Fehler: ' . $event->exception->getMessage();
        }

        SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:backup_failed',
            'title' => 'Kritischer Fehler: System-Backup fehlgeschlagen',
            'message' => substr($message, 0, 500),
            'status' => 'error',
            'payload' => [
                'exception' => $event->exception ? $event->exception->getMessage() : null,
                'trace' => $event->exception ? $event->exception->getTraceAsString() : null,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * Handle failed cleanup events.
     */
    public function handleCleanupFailed(CleanupHasFailed $event): void
    {
        $message = 'Backup-Bereinigung ist fehlgeschlagen.';
        if ($event->exception) {
            $message .= ' Fehler: ' . $event->exception->getMessage();
        }

        SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:cleanup_failed',
            'title' => 'Warnung: Backup-Bereinigung fehlgeschlagen',
            'message' => substr($message, 0, 500),
            'status' => 'warning',
            'payload' => [
                'exception' => $event->exception ? $event->exception->getMessage() : null,
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * Handle unhealthy backup events.
     */
    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        $message = 'Das Backup-System hat ungesunde Backups gefunden. Möglicherweise ist der Festplattenspeicher erschöpft oder Backups sind zu alt.';

        SystemLog::create([
            'type' => 'system',
            'action_id' => 'system:backup_unhealthy',
            'title' => 'Warnung: Ungesunde Backups gefunden',
            'message' => $message,
            'status' => 'warning',
            'payload' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            BackupHasFailed::class,
            [BackupEventSubscriber::class, 'handleBackupFailed']
        );

        $events->listen(
            CleanupHasFailed::class,
            [BackupEventSubscriber::class, 'handleCleanupFailed']
        );

        $events->listen(
            UnhealthyBackupWasFound::class,
            [BackupEventSubscriber::class, 'handleUnhealthyBackup']
        );
    }
}
