<?php

namespace App\Console\Commands\Shop;

use App\Models\System\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateMonthlyVouchersCommand extends Command
{
    protected $signature = 'shop:generate-monthly-vouchers';

    protected $description = 'Automatische Gutschein-Generierung für das neue Jahr';

    public function handle(): int
    {
        $this->info('Generating monthly vouchers...');

        // WICHTIG: --force ist in APP_ENV=production zwingend erforderlich, damit db:seed im Cronjob nicht abbricht!
        $exitCode = Artisan::call('db:seed', [
            '--class' => 'MonthlyVoucherSeeder',
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            $this->error('Monthly voucher generation failed with exit code ' . $exitCode);
            return self::FAILURE;
        }

        if (class_exists(SystemLog::class)) {
            try {
                SystemLog::create([
                    'type' => 'shop',
                    'action_id' => 'shop:generate_monthly_vouchers',
                    'title' => 'Jahres-Gutscheine generiert',
                    'message' => 'MonthlyVoucherSeeder erfolgreich für das aktuelle Jahr ausgeführt.',
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            } catch (\Throwable) {
            }
        }

        $this->info('Monthly vouchers generated successfully.');
        return self::SUCCESS;
    }
}
