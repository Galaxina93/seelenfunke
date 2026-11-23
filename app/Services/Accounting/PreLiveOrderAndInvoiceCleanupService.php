<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Order\OrderOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PreLiveOrderAndInvoiceCleanupService
{
    /**
     * Prüft hart, dass im scharfen Produktionsbetrieb (APP_ENV=production + shop_is_live=true)
     * niemals versehentlich echte GoBD-Bestellungen oder Ausgangsrechnungen gelöscht werden können.
     */
    public function assertPreLiveSafe(): void
    {
        $isProductionEnv = app()->environment('production');
        $shopIsLive = filter_var(shop_setting('shop_is_live', false), FILTER_VALIDATE_BOOLEAN);

        if ($isProductionEnv && $shopIsLive) {
            throw new RuntimeException(
                'GoBD-Schutz aktiv: Der Shop ist bereits im Live-Betrieb (APP_ENV=production & shop_is_live=true). '
                . 'Produktive Bestellungen und Ausgangsrechnungen dürfen nicht gelöscht werden.'
            );
        }
    }

    /**
     * Liefert die aktuelle Anzahl an Vorab-Bestellungen, Ausgangsrechnungen und physischen Archivrechnungs-Dateien.
     *
     * @return array{orders_count: int, invoices_count: int, archive_files_count: int}
     */
    public function inspectCounts(): array
    {
        $ordersCount = Schema::hasTable('order_orders')
            ? OrderOrder::withTrashed()->count()
            : 0;

        $invoicesCount = Schema::hasTable('accounting_invoices')
            ? AccountingInvoice::withTrashed()->count()
            : 0;

        $archiveFilesCount = count($this->listInvoiceArchiveFiles());

        return [
            'orders_count' => $ordersCount,
            'invoices_count' => $invoicesCount,
            'archive_files_count' => $archiveFilesCount,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function listInvoiceArchiveFiles(): array
    {
        $localDisk = Storage::disk('local');
        if (!$localDisk->exists('buchhaltung/invoices')) {
            return [];
        }

        return array_values(array_filter(
            $localDisk->allFiles('buchhaltung/invoices'),
            static fn (string $path): bool => !str_ends_with($path, '/.gitignore') && basename($path) !== '.gitignore'
        ));
    }

    /**
     * Bereinigt vor dem Livegang restlos alle Testbestellungen, Ausgangs-/Archivrechnungen
     * und deren direkte Bestell-Nebenwirkungen (Herzensprojekt-Beiträge, Bestell-Funken, Test-Snapshots),
     * ohne jemals Eingangsbelege (AccountingSpecialIssue / receipts), Verträge (AccountingCostItem / contracts),
     * Lieferanten-E-Rechnungen (incoming_einvoices) oder Steuerexporte anzutasten.
     *
     * @return array<string, mixed>
     */
    public function purge(): array
    {
        $this->assertPreLiveSafe();

        $summary = [
            'deleted_invoices' => 0,
            'deleted_orders' => 0,
            'deleted_order_items' => 0,
            'deleted_shipments' => 0,
            'deleted_revocations' => 0,
            'deleted_quotes' => 0,
            'deleted_carts' => 0,
            'deleted_herzensprojekt_contributions' => 0,
            'deleted_gamification_order_logs' => 0,
            'deleted_referrals' => 0,
            'deleted_archive_files' => 0,
            'deleted_order_files' => 0,
            'preserved_special_issues' => Schema::hasTable('accounting_special_issues') ? DB::table('accounting_special_issues')->count() : 0,
            'preserved_cost_items' => Schema::hasTable('accounting_cost_items') ? DB::table('accounting_cost_items')->count() : 0,
            'next_invoice_number' => null,
        ];

        DB::transaction(function () use (&$summary): void {
            // 1. Ausgangsrechnungen, Stornos & Gutschriften (accounting_invoices)
            if (Schema::hasTable('accounting_invoices')) {
                DB::table('accounting_invoices')->whereNotNull('parent_id')->update(['parent_id' => null]);
                $summary['deleted_invoices'] = DB::table('accounting_invoices')->delete();
            }

            // 2. Direkte Nebenwirkungen aus Testbestellungen bereinigen
            if (Schema::hasTable('marketing_gift_voucher_logs')) {
                DB::table('marketing_gift_voucher_logs')->whereNotNull('order_id')->delete();
            }
            if (Schema::hasTable('marketing_gift_vouchers')) {
                DB::table('marketing_gift_vouchers')->whereNotNull('order_item_id')->delete();
            }

            if (Schema::hasTable('marketing_herzensprojekt_transactions')) {
                $summary['deleted_herzensprojekt_contributions'] = DB::table('marketing_herzensprojekt_transactions')
                    ->where(function ($q): void {
                        $q->where('type', 'order_contribution')
                            ->orWhereNotNull('order_id');
                    })
                    ->delete();
            }

            if (Schema::hasTable('marketing_influencer_commissions')) {
                DB::table('marketing_influencer_commissions')->delete();
            }
            if (Schema::hasTable('marketing_influencers') && Schema::hasColumn('marketing_influencers', 'sample_order_id')) {
                DB::table('marketing_influencers')->whereNotNull('sample_order_id')->update(['sample_order_id' => null]);
            }

            // Aus Testbestellungen automatisch generierte Kunden-Empfehlungscodes bereinigen
            if (Schema::hasTable('marketing_referrals')) {
                $referralVoucherIds = DB::table('marketing_referrals')
                    ->whereNotNull('voucher_id')
                    ->pluck('voucher_id')
                    ->filter()
                    ->all();

                $summary['deleted_referrals'] = DB::table('marketing_referrals')->delete();

                if (!empty($referralVoucherIds) && Schema::hasTable('marketing_vouchers')) {
                    DB::table('marketing_vouchers')->whereIn('id', $referralVoucherIds)->delete();
                }
            }

            if (Schema::hasTable('marketing_vouchers')) {
                DB::table('marketing_vouchers')->where('used_count', '>', 0)->update(['used_count' => 0]);
            }

            // Gamification Bestell-Funken & Kauf-Zähler zurücksetzen
            if (Schema::hasTable('customer_gamification_logs')) {
                $summary['deleted_gamification_order_logs'] = DB::table('customer_gamification_logs')
                    ->where('event_type', 'order_reward')
                    ->delete();
            }
            if (Schema::hasTable('customer_gamifications')) {
                $gamificationRows = DB::table('customer_gamifications')->get();
                foreach ($gamificationRows as $gRow) {
                    $progress = is_string($gRow->titles_progress)
                        ? (json_decode($gRow->titles_progress, true) ?: [])
                        : [];

                    $rewardedAmounts = is_array($progress['_rewarded_order_amounts'] ?? null)
                        ? $progress['_rewarded_order_amounts']
                        : [];
                    $orderSparksSum = array_sum(array_map('intval', $rewardedAmounts));

                    $hadOrderRewards = $orderSparksSum > 0
                        || !empty($progress['_rewarded_order_ids'])
                        || !empty($progress['schatzhueter'])
                        || !empty($progress['massenkaeufer'])
                        || !empty($progress['wiederholungstaeter']);

                    if ($hadOrderRewards) {
                        unset($progress['_rewarded_order_ids'], $progress['_rewarded_order_amounts']);
                        foreach (['schatzhueter', 'massenkaeufer', 'wochenend_shopper', 'wiederholungstaeter', 'nachtschwaermer'] as $purchaseKey) {
                            if (array_key_exists($purchaseKey, $progress)) {
                                $progress[$purchaseKey] = 0;
                            }
                        }

                        $newBalance = max(0, (int) $gRow->funken_balance - $orderSparksSum);
                        $newTotalEarned = max(0, (int) $gRow->funken_total_earned - $orderSparksSum);
                        if (array_key_exists('funkenkoenig', $progress)) {
                            $progress['funkenkoenig'] = $newTotalEarned;
                        }

                        DB::table('customer_gamifications')
                            ->where('id', $gRow->id)
                            ->update([
                                'funken_balance' => $newBalance,
                                'funken_total_earned' => $newTotalEarned,
                                'titles_progress' => json_encode($progress, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                }
            }

            // 3. Bestellungen, Positionen, Sendungen, Widerrufe, Angebote & Warenkörbe
            if (Schema::hasTable('order_shipments')) {
                $summary['deleted_shipments'] = DB::table('order_shipments')->delete();
            }
            if (Schema::hasTable('order_revocations')) {
                $summary['deleted_revocations'] = DB::table('order_revocations')->delete();
            }
            if (Schema::hasTable('order_quote_request_items')) {
                DB::table('order_quote_request_items')->delete();
            }
            if (Schema::hasTable('order_quote_requests')) {
                $summary['deleted_quotes'] = DB::table('order_quote_requests')->delete();
            }
            if (Schema::hasTable('order_order_items')) {
                $summary['deleted_order_items'] = DB::table('order_order_items')->delete();
            }
            if (Schema::hasTable('order_orders')) {
                $summary['deleted_orders'] = DB::table('order_orders')->delete();
            }
            if (Schema::hasTable('cart_items')) {
                DB::table('cart_items')->delete();
            }
            if (Schema::hasTable('carts')) {
                $summary['deleted_carts'] = DB::table('carts')->delete();
            }
        });

        // 4. Physische Archivrechnungen (PDF + E-Rechnungs-XML) im Storage leeren
        $localDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');

        foreach ($this->listInvoiceArchiveFiles() as $invFile) {
            if ($localDisk->delete($invFile)) {
                $summary['deleted_archive_files']++;
            }
        }
        if ($localDisk->exists('buchhaltung/invoices/xml') && empty($localDisk->allFiles('buchhaltung/invoices/xml'))) {
            $localDisk->deleteDirectory('buchhaltung/invoices/xml');
        }
        if ($publicDisk->exists('buchhaltung/invoices')) {
            foreach ($publicDisk->allFiles('buchhaltung/invoices') as $pubInvFile) {
                if ($publicDisk->delete($pubInvFile)) {
                    $summary['deleted_archive_files']++;
                }
            }
            $publicDisk->deleteDirectory('buchhaltung/invoices');
        }

        // 5. Verwaiste Test-Bestelldateien (DHL-Testlabels, Snapshots, Cart-Uploads, Laser-Vorschau-Caches)
        // WICHTIG: xTool-Kalibrierungsdaten (fl_calib_uv.json, global_calib.json, background.jpg, host_appdata/*)
        // sowie .htaccess in cart-uploads bleiben zu 100 % geschützt!
        if ($localDisk->exists('bestellungen/dhl_labels')) {
            foreach ($localDisk->allFiles('bestellungen/dhl_labels') as $dhlFile) {
                if (basename($dhlFile) !== '.gitignore' && basename($dhlFile) !== '.htaccess' && $localDisk->delete($dhlFile)) {
                    $summary['deleted_order_files']++;
                }
            }
        }

        foreach ([$localDisk, $publicDisk] as $disk) {
            if ($disk->exists('system/snapshots')) {
                foreach ($disk->allFiles('system/snapshots') as $snapFile) {
                    if (basename($snapFile) !== '.gitignore' && basename($snapFile) !== '.htaccess' && $disk->delete($snapFile)) {
                        $summary['deleted_order_files']++;
                    }
                }
            }
        }

        foreach (['bestellungen/cart-uploads', 'bestellungen/xtool/previews', 'bestellungen/xtool/photo-edits'] as $pubOrderDir) {
            if ($publicDisk->exists($pubOrderDir)) {
                foreach ($publicDisk->allFiles($pubOrderDir) as $orderAsset) {
                    $base = basename($orderAsset);
                    if ($base === '.htaccess' || $base === '.gitignore') {
                        continue;
                    }
                    if ($publicDisk->delete($orderAsset)) {
                        $summary['deleted_order_files']++;
                    }
                }
            }
        }

        // 6. Kapazitäts-Cache auf 0 % synchronisieren & nächste Rechnungsnummer ermitteln
        Cache::put('shop_capacity_percentage', 0.0);
        try {
            if (array_key_exists('shop:capacity-engine', Artisan::all())) {
                Artisan::call('shop:capacity-engine');
            }
        } catch (\Throwable) {
            // Optional im isolierten Unit-Test
        }

        if (Schema::hasTable('accounting_invoices')) {
            $summary['next_invoice_number'] = app(InvoiceService::class)->getNextInvoiceNumber('RE');
        }

        return $summary;
    }
}
