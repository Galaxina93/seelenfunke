<?php

namespace App\Livewire\Customer;


use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem; // Anpassen, falls dein Model anders heißt
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class CustomerOrdersComponent extends Component
{
    use WithPagination;

    public $searchOrder = '';
    #[Locked]
    public $selectedOrderId = null;
    #[Locked]
    public $previewItemId = null;
    #[Locked]
    public $editItemId = null;
    public $showSuccessMessageFor = null;

    public function boot(): void
    {
        $user = Auth::guard('customer')->user();
        if (!$user || !empty($user->needs_password_change)) {
            abort(403);
        }
    }

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (request()->has('order')) {
            $orderParam = trim((string) request()->get('order'));
            $order = OrderOrder::where('customer_id', $user->id)
                ->where(function ($q) use ($orderParam) {
                    $q->where('id', $orderParam)
                      ->orWhere('order_number', $orderParam);
                })
                ->first();
            if ($order) {
                $this->selectedOrderId = $order->id;
            }
        }
        if (request()->has('edit')) {
            $editId = request()->get('edit');
            $belongs = OrderOrderItem::where('id', $editId)->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                  ->where('status', 'pending');
            })->exists();
            if ($belongs) {
                $this->editItemId = $editId;
            }
        }
    }

    // Wenn gesucht wird, springen wir zurück auf Seite 1
    public function updatingSearchOrder()
    {
        $this->resetPage();
    }

    public function showOrder($id)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $exists = OrderOrder::where('id', $id)->where('customer_id', $user->id)->exists();
        if (!$exists) {
            $this->selectedOrderId = null;
            $this->previewItemId = null;
            return;
        }

        $this->selectedOrderId = $id;
        $this->previewItemId = null;
        $this->dispatch('scroll-orders-top');
    }

    public function resetOrderView()
    {
        $this->selectedOrderId = null;
        $this->previewItemId = null;
        $this->dispatch('scroll-orders-top');
    }

    public function openPreview($itemId)
    {
        $this->editItemId = null; // Close edit if open
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $belongs = OrderOrderItem::where('id', $itemId)->whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->exists();

        if (!$belongs) {
            $this->previewItemId = null;
            return;
        }

        if ($this->previewItemId === $itemId) {
            $this->previewItemId = null; // Toggle zum Schließen
        } else {
            $this->previewItemId = $itemId;
        }
    }

    public function openEdit($itemId)
    {
        $this->previewItemId = null; // Close preview if open
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $belongs = OrderOrderItem::where('id', $itemId)->whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id)
              ->where('status', 'pending');
        })->exists();

        if (!$belongs) {
            $this->editItemId = null;
            return;
        }

        if ($this->editItemId === $itemId) {
            $this->editItemId = null; // Toggle zum Schließen
        } else {
            $this->editItemId = $itemId;
        }
    }

    #[On('order-item-updated')]
    public function handleItemUpdated($itemId)
    {
        $this->editItemId = null;
        $this->showSuccessMessageFor = $itemId;
    }

    public function downloadDigitalFile($itemId, ?string $docId = null)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            abort(403);
        }

        $item = OrderOrderItem::with(['order', 'product'])
            ->where('id', $itemId)
            ->whereHas('order', function ($q) use ($user) {
                $q->where('customer_id', $user->id);
            })->first();

        if (!$item) {
            session()->flash('error', 'Datei nicht verfügbar.');
            return;
        }

        $downloadPath = '';
        $rawDownloadName = 'Download';

        if ($docId !== null && trim($docId) !== '') {
            $matchedDoc = collect($item->resolveDigitalDocuments())->firstWhere('id', trim($docId));
            if (!$matchedDoc || empty($matchedDoc['path'])) {
                session()->flash('error', 'Dokument nicht verfügbar.');
                return;
            }
            $downloadPath = ltrim(str_replace('\\', '/', (string) $matchedDoc['path']), '/');
            $ext = strtolower((string) ($matchedDoc['extension'] ?? pathinfo($downloadPath, PATHINFO_EXTENSION) ?: 'pdf'));
            $rawDownloadName = trim((string) ($matchedDoc['title'] ?? 'Dokument')) . '.' . $ext;
        } elseif ($item->product && $item->product->type === 'digital' && !empty($item->product->digital_download_path)) {
            $downloadPath = ltrim(str_replace('\\', '/', (string) $item->product->digital_download_path), '/');
            $rawDownloadName = (string) ($item->product->digital_filename ?: 'Download');
        } else {
            session()->flash('error', 'Datei nicht verfügbar.');
            return;
        }

        if (
            $downloadPath === ''
            || str_contains($downloadPath, '..')
            || str_contains($downloadPath, "\0")
            || (!str_starts_with($downloadPath, 'produkte/products-secure/') && !str_starts_with($downloadPath, 'products-secure/'))
        ) {
            session()->flash('error', 'Ungültiger Dateipfad.');
            return;
        }

        // Downloads stehen erst nach vollständigem Zahlungseingang zur Verfügung und sind bei Storno/Erstattung gesperrt
        if (
            !$item->order
            || $item->order->payment_status !== 'paid'
            || in_array($item->order->status, ['cancelled', 'refunded'], true)
        ) {
            session()->flash('error', 'Der Download steht erst nach vollständigem Zahlungseingang zur Verfügung.');
            return;
        }

        if (
            !\Illuminate\Support\Facades\Storage::disk('local')->exists($downloadPath)
            || is_link(\Illuminate\Support\Facades\Storage::disk('local')->path($downloadPath))
        ) {
            session()->flash('error', 'Datei nicht gefunden.');
            return;
        }

        $safeDownloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($rawDownloadName)) ?: 'Download';

        return \Illuminate\Support\Facades\Storage::disk('local')->download(
            $downloadPath,
            $safeDownloadName,
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]
        );
    }

    public function confirmDelivery($orderId)
    {
        $user = Auth::guard('customer')->user();
        if (!$user) return;

        $order = OrderOrder::where('id', $orderId)
            ->where('customer_id', $user->id)
            ->first();

        if (!$order) {
            session()->flash('error', 'Bestellung nicht gefunden.');
            return;
        }

        if ($order->status !== 'shipped') {
            session()->flash('error', 'Die Bestellung kann nur im Status "Unterwegs" als zugestellt bestätigt werden.');
            return;
        }

        // Alle Sendungen auf delivered setzen
        $order->shipments()->update(['status' => 'delivered']);

        // Bestellung auf completed setzen
        $order->update(['status' => 'completed']);

        session()->flash('delivery_confirmed', 'Vielen Dank! Ihre Bestellung wurde als zugestellt bestätigt. Wir wünschen Ihnen ganz viel Freude mit Ihren Seelenfunke-Unikaten! ✨');
    }

    // Computed Property für die ausgewählte Bestellung (strikt mandantengeschützt)
    public function getSelectedOrderProperty()
    {
        if (!$this->selectedOrderId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrder::with([
                'items.product',
                'invoices' => fn($q) => $q->where('status', '!=', 'draft'),
                'shipments'
            ])
            ->where('customer_id', $user->id)
            ->find($this->selectedOrderId);
    }

    // Computed Property für das Vorschaubild (Manufaktur Details, strikt mandantengeschützt)
    public function getPreviewItemProperty()
    {
        if (!$this->previewItemId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrderItem::whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->with('product')->find($this->previewItemId);
    }

    // Computed Property für die Edit-Funktion (strikt mandantengeschützt & nur für offene Bestellungen)
    public function getEditItemProperty()
    {
        if (!$this->editItemId) return null;
        $user = Auth::guard('customer')->user();
        if (!$user) return null;

        return OrderOrderItem::whereHas('order', function ($q) use ($user) {
            $q->where('customer_id', $user->id)
              ->where('status', 'pending');
        })->with('product')->find($this->editItemId);
    }

    public static function resolveBlueprintSnapshotUrl(?string $rawPath): ?string
    {
        if ($rawPath === null) {
            return null;
        }
        $trimmed = trim($rawPath);
        if ($trimmed === '' || str_contains($trimmed, "\0") || str_contains($trimmed, '..') || str_contains($trimmed, '\\')) {
            return null;
        }

        if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return resolve_snapshot_url($trimmed);
        }

        $clean = ltrim(preg_replace('#^/?storage/#', '', $trimmed) ?? $trimmed, '/');
        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, '\\')) {
            return null;
        }

        $candidates = [];
        if (str_starts_with($clean, 'snapshots/')) {
            $candidates[] = 'system/' . $clean;
            $candidates[] = $clean;
        } elseif (str_starts_with($clean, 'system/snapshots/')) {
            $candidates[] = $clean;
            $candidates[] = substr($clean, 7);
        } else {
            $candidates[] = 'system/snapshots/' . $clean;
            $candidates[] = 'system/' . $clean;
            $candidates[] = $clean;
        }

        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');
        foreach ($candidates as $cand) {
            if ($publicDisk->exists($cand)) {
                return asset('storage/' . $cand);
            }
        }

        return resolve_snapshot_url($clean, asset('storage/' . $clean));
    }

    /**
     * Löst Metadaten einer erfolgten Manufaktur-/Admin-Anpassung an einer Bestellposition auf.
     *
     * @return array{adjusted_by: string, adjusted_at: string, adjusted_formatted: string, version: int, sentence: string}|null
     */
    public static function resolveAdminAdjustmentInfo(?OrderOrderItem $item): ?array
    {
        if (!$item) {
            return null;
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $adj = $conf['last_admin_adjustment'] ?? null;
        if (!is_array($adj) || empty($adj)) {
            return null;
        }

        $adjustedBy = trim(str_replace([" \u{2013} ", " \u{2014} ", 'Mein-Seelenfunke Admin'], [' · ', ' · ', 'Mein Seelenfunke Admin'], (string) ($adj['adjusted_by'] ?? 'Mein Seelenfunke Admin')));
        if ($adjustedBy === '') {
            $adjustedBy = 'Mein Seelenfunke Admin';
        }

        $rawAt = trim((string) ($adj['adjusted_at'] ?? ''));
        $formattedAt = '';
        try {
            if ($rawAt !== '') {
                $formattedAt = \Carbon\Carbon::parse($rawAt)->timezone('Europe/Berlin')->format('d.m.Y \u\m H:i \U\h\r');
            } elseif ($item->updated_at) {
                $formattedAt = $item->updated_at->copy()->timezone('Europe/Berlin')->format('d.m.Y \u\m H:i \U\h\r');
            }
        } catch (\Throwable) {
            $formattedAt = $item->updated_at
                ? $item->updated_at->copy()->timezone('Europe/Berlin')->format('d.m.Y \u\m H:i \U\h\r')
                : '';
        }

        return [
            'adjusted_by'        => $adjustedBy,
            'adjusted_at'        => $rawAt,
            'adjusted_formatted' => $formattedAt,
            'version'            => max(1, (int) ($adj['version'] ?? 2)),
            'sentence'           => $formattedAt !== ''
                ? "{$adjustedBy} · Änderungsmaßnahme am {$formattedAt} durchgeführt"
                : "{$adjustedBy} · Änderungsmaßnahme durchgeführt",
        ];
    }

    /**
     * Baut den chronologischen Blueprint-Änderungsverlauf (configuration_history + aktueller Stand) für den Kundenbereich.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildItemBlueprintTimeline(?OrderOrderItem $item): array
    {
        if (!$item) {
            return [];
        }

        $conf = is_array($item->configuration) ? $item->configuration : [];
        $rawHistory = is_array($conf['configuration_history'] ?? null)
            ? array_values(array_filter($conf['configuration_history'], 'is_array'))
            : [];

        if (empty($rawHistory)) {
            return [];
        }

        usort($rawHistory, static fn (array $a, array $b): int => ((int) ($a['version'] ?? 0)) <=> ((int) ($b['version'] ?? 0)));

        $formatTs = static function (?string $iso, $fallbackDt = null): string {
            try {
                if ($iso !== null && trim($iso) !== '') {
                    return \Carbon\Carbon::parse($iso)->timezone('Europe/Berlin')->format('d.m.Y, H:i \U\h\r');
                }
                if ($fallbackDt instanceof \Carbon\CarbonInterface) {
                    return $fallbackDt->copy()->timezone('Europe/Berlin')->format('d.m.Y, H:i \U\h\r');
                }
            } catch (\Throwable) {
            }

            return '';
        };

        $steps = [];
        foreach ($rawHistory as $idx => $entry) {
            $ver = max(1, (int) ($entry['version'] ?? ($idx + 1)));
            $actorType = strtolower((string) ($entry['actor_type'] ?? 'customer')) === 'admin' ? 'admin' : 'customer';
            $rawLabel = trim(str_replace([" \u{2013} ", " \u{2014} "], ' · ', (string) ($entry['actor_label'] ?? '')));

            if ($actorType === 'customer' && ($rawLabel === '' || $rawLabel === 'Original-Entwurf beim Kauf')) {
                $displayLabel = $ver === 1 ? 'Dein Original-Entwurf' : 'Deine Anpassung';
            } elseif ($rawLabel !== '') {
                $displayLabel = $rawLabel;
            } else {
                $displayLabel = $actorType === 'admin' ? 'Mein-Seelenfunke Admin' : 'Dein Original-Entwurf';
            }

            $snap = $entry['snapshot_path'] ?? null;
            $frontUrl = is_array($snap)
                ? self::resolveBlueprintSnapshotUrl($snap['front'] ?? null)
                : (is_string($snap) ? self::resolveBlueprintSnapshotUrl($snap) : null);
            $backUrl = is_array($snap) ? self::resolveBlueprintSnapshotUrl($snap['back'] ?? null) : null;

            $steps[] = [
                'version'        => $ver,
                'actor_type'     => $actorType,
                'actor_label'    => $displayLabel,
                'saved_at_label' => $formatTs($entry['saved_at'] ?? null, $item->created_at),
                'front_url'      => $frontUrl,
                'back_url'       => $backUrl,
                'has_back'       => $backUrl !== null && $backUrl !== '',
                'is_current'     => false,
            ];
        }

        $maxVer = !empty($steps) ? max(array_column($steps, 'version')) : 0;
        $adjInfo = self::resolveAdminAdjustmentInfo($item);
        $currVer = ($adjInfo !== null && $adjInfo['version'] > $maxVer)
            ? $adjInfo['version']
            : ($maxVer + 1);
        $currActorType = $adjInfo !== null ? 'admin' : 'customer';
        $currActorLabel = $adjInfo !== null ? $adjInfo['adjusted_by'] : 'Dein aktueller Entwurf';

        $currSnap = $conf['snapshot_path'] ?? null;
        $hasBackContent = collect($conf['texts_back'] ?? [])->contains(fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '')
            || !empty($conf['logos_back']);
        $currFrontUrl = is_array($currSnap)
            ? self::resolveBlueprintSnapshotUrl($currSnap['front'] ?? null)
            : (is_string($currSnap) ? self::resolveBlueprintSnapshotUrl($currSnap) : null);
        $currBackUrl = (is_array($currSnap) && $hasBackContent)
            ? self::resolveBlueprintSnapshotUrl($currSnap['back'] ?? null)
            : null;

        $steps[] = [
            'version'        => $currVer,
            'actor_type'     => $currActorType,
            'actor_label'    => $currActorLabel,
            'saved_at_label' => $formatTs($adjInfo['adjusted_at'] ?? null, $item->updated_at),
            'front_url'      => $currFrontUrl,
            'back_url'       => $currBackUrl,
            'has_back'       => $currBackUrl !== null && $currBackUrl !== '',
            'is_current'     => true,
        ];

        return $steps;
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            abort(403);
        }

        $query = OrderOrder::where('customer_id', $user->id)
            ->with(['items.product', 'shipments']); // Eager Loading für Performance

        if (!empty($this->searchOrder)) {
            $escapedSearch = addcslashes((string) $this->searchOrder, '%_\\');
            $query->where('order_number', 'like', '%' . $escapedSearch . '%');
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.customer.customer-orders-component', [
            'orders' => $orders,
            'selectedOrder' => $this->selectedOrder,
        ])->layout('components.layouts.customer_layout');
    }
}
