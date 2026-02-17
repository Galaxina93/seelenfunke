<?php

namespace App\Console\Commands;

use App\Models\FunkiVoucher;
use App\Models\FunkiLog;
use Illuminate\Console\Command;

class RunVoucherAutomation extends Command
{
    protected $signature = 'funki:send-vouchers';
    protected $description = 'Prüft den Status der automatischen Gutscheine und loggt Aktivitäten.';

    public function handle()
    {
        $this->info('Starte Funki-Voucher Check...');

        // Wir holen uns Gutscheine, die heute ihre Gültigkeit starten oder deren Limit fast erreicht ist
        $activeAutomations = FunkiVoucher::where('is_active', true)
            ->where('mode', 'auto')
            ->whereDate('valid_from', now())
            ->get();

        if ($activeAutomations->count() > 0) {
            foreach ($activeAutomations as $voucher) {
                FunkiLog::create([
                    'type' => 'system',
                    'action_id' => 'voucher:auto_start',
                    'title' => 'Kampagne gestartet',
                    'message' => "Der automatische Gutschein '{$voucher->title}' ({$voucher->code}) ist ab heute live.",
                    'status' => 'success',
                    'payload' => ['voucher_id' => $voucher->id, 'code' => $voucher->code],
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }
            $this->info($activeAutomations->count() . ' Kampagnen-Log(s) erstellt.');
        } else {
            $this->comment('Keine neuen Kampagnen-Starts für heute.');
        }

        $this->info('Fertig.');
    }
}
