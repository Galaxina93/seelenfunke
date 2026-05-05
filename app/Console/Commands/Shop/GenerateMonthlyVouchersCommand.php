<?php

namespace App\Console\Commands\Shop;

use Illuminate\Console\Command;

class GenerateMonthlyVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:generate-monthly-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatische Gutschein-Generierung für das neue Jahr';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating monthly vouchers...');
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'MonthlyVoucherSeeder']);
        $this->info('Monthly vouchers generated successfully.');
    }
}
