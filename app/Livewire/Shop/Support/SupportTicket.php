<?php

namespace App\Livewire\Shop\Support;

use Livewire\Attributes\Layout;

use App\Events\TicketMessageSent;
use App\Models\Customer\CustomerGamification;
use App\Models\Support\SupportTicket as SupportTicketModel;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

#[Layout('components.layouts.backend_layout')]
class SupportTicket extends Component
{
    use WithPagination, WithFileUploads, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    public $viewMode = 'split'; // 'split' or 'table'
    public $filterStatus = 'open';
    public $search = '';
    #[Locked]
    public $activeTicketId = null;
    public $replyMessage = '';
    public $replyAttachments = [];

    // Admin-Initiated Ticket ("Spieß umdrehen" / Direktnachricht an Kunden)
    public bool $isCreatingTicket = false;
    public string $newTicketCustomerSearch = '';
    public string $newTicketCustomerId = '';
    public string $newTicketOrderId = '';
    public string $newTicketCategory = 'support';
    public string $newTicketSubject = '';
    public string $newTicketMessage = '';
    public $newTicketAttachments = [];

    // KPIs
    public $kpiAvgRating = 0;
    public $kpiOpenCount = 0;
    public $kpiAvgResolutionHrs = 0;

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->calculateKPIs();

        $preselectCustomer = trim((string) request()->query('createForCustomer', ''));
        if ($preselectCustomer !== '') {
            $this->openCreateTicketModal($preselectCustomer);
        }
    }

    public function calculateKPIs()
    {
        $this->kpiOpenCount = SupportTicketModel::where('status', 'open')->count();
        $this->kpiAvgRating = round((float) SupportTicketModel::whereNotNull('rating')->avg('rating'), 1);

        $closedTickets = SupportTicketModel::where('status', 'closed')
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $totalHours = 0;
        foreach($closedTickets as $ticket) {
            $totalHours += $ticket->created_at->diffInHours($ticket->updated_at);
        }

        $this->kpiAvgResolutionHrs = $closedTickets->count() > 0 ? round($totalHours / $closedTickets->count(), 1) : 0;
    }
    #[On('echo-private:admin.tickets,.TicketMessageSent')]
    public function receiveMessage($event)
    {
        # \Illuminate\Support\Facades\Log::info('WS Triggered: Admin receiveMessage', ['payload' => $event, 'activeTicketId' => $this->activeTicketId]);

        if (isset($event['message']['support_ticket_id'])) {
            $ticketId = $event['message']['support_ticket_id'];
            if ((string) $this->activeTicketId === (string) $ticketId) {
                $this->filterStatus = 'open';
                $this->markAsReadAdmin($ticketId);
                $this->dispatch('ticket-message-received');
                $this->dispatch('clear-admin-ticket-badge'); // Entfernt den roten Punkt sofort
            }
        }
        $this->dispatch('$refresh');
    }

    public function selectTicket($id)
    {
        $ticket = SupportTicketModel::find($id);
        if (!$ticket) {
            return;
        }
        $this->isCreatingTicket = false;
        $this->activeTicketId = $ticket->id;
        $this->reset(['replyMessage', 'replyAttachments']);
        $this->markAsReadAdmin($ticket->id);
        $this->dispatch('ticket-message-received');
        $this->dispatch('clear-admin-ticket-badge'); // Entfernt den roten Punkt sofort beim Öffnen
        $this->dispatch('admin-ticket-badge-update', hasUnread: false);
    }

    public function openCreateTicketModal(?string $customerId = null): void
    {
        $this->resetErrorBag();
        $this->isCreatingTicket = true;
        $this->viewMode = 'split';
        $this->activeTicketId = null;
        $this->newTicketCustomerSearch = '';
        $this->newTicketCustomerId = '';
        $this->newTicketOrderId = '';
        $this->newTicketCategory = 'support';
        $this->newTicketSubject = '';
        $this->newTicketMessage = '';
        $this->newTicketAttachments = [];

        if (!empty($customerId)) {
            $this->selectCustomerForNewTicket($customerId);
        }
    }

    public function closeCreateTicketModal(): void
    {
        $this->isCreatingTicket = false;
        $this->resetErrorBag();
    }

    public function selectCustomerForNewTicket(string $customerId): void
    {
        $customer = \App\Models\Customer\Customer::withTrashed()->find($customerId);
        if (!$customer) {
            return;
        }

        $this->newTicketCustomerId = (string) $customer->id;
        $this->newTicketOrderId = '';
        $this->newTicketCustomerSearch = trim($customer->first_name . ' ' . $customer->last_name) . ' (' . $customer->email . ')';
    }

    public function clearSelectedCustomerForNewTicket(): void
    {
        $this->newTicketCustomerId = '';
        $this->newTicketOrderId = '';
        $this->newTicketCustomerSearch = '';
    }

    public function removeNewTicketAttachment($index): void
    {
        array_splice($this->newTicketAttachments, (int) $index, 1);
    }

    public function createAdminTicket(): void
    {
        $this->validate([
            'newTicketCustomerId' => 'required|exists:customers,id',
            'newTicketOrderId' => 'nullable|string',
            'newTicketCategory' => 'required|in:support,return,bug,general',
            'newTicketSubject' => 'required|string|min:2|max:255',
            'newTicketMessage' => 'required|string|min:1|max:10000',
            'newTicketAttachments' => 'nullable|array|max:10',
            'newTicketAttachments.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf,txt,docx|max:10240',
        ]);

        $customer = \App\Models\Customer\Customer::withTrashed()->findOrFail($this->newTicketCustomerId);
        $validOrderId = null;
        if (!empty($this->newTicketOrderId)) {
            $order = \App\Models\Order\OrderOrder::where('customer_id', $customer->id)
                ->find($this->newTicketOrderId);
            $validOrderId = $order?->id;
        }

        $allowedMimeMap = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        $attachmentPaths = [];
        foreach ((array) $this->newTicketAttachments as $photo) {
            if (!is_object($photo) || !method_exists($photo, 'getClientOriginalExtension') || !method_exists($photo, 'getRealPath')) {
                continue;
            }
            $ext = strtolower((string) $photo->getClientOriginalExtension());
            if (!isset($allowedMimeMap[$ext])) {
                continue;
            }
            $realPath = $photo->getRealPath();
            $detectedMime = ($realPath && is_file($realPath) && function_exists('finfo_open'))
                ? strtolower((string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath))
                : strtolower((string) $photo->getMimeType());
            if (in_array($detectedMime, ['application/x-empty', 'inode/x-empty'], true) && method_exists($photo, 'getMimeType')) {
                $detectedMime = strtolower((string) $photo->getMimeType());
            }
            if (!in_array($detectedMime, $allowedMimeMap[$ext], true)) {
                continue;
            }
            $safeFilename = \Illuminate\Support\Str::uuid()->toString() . '.' . $ext;
            $attachmentPaths[] = $photo->storeAs('support/tickets/attachments', $safeFilename, 'public');
        }

        $ticketNumber = 'MSF-' . date('y') . '-' . strtoupper(\Illuminate\Support\Str::random(5));

        $ticket = SupportTicketModel::create([
            'ticket_number' => $ticketNumber,
            'customer_id' => $customer->id,
            'order_id' => $validOrderId,
            'subject' => trim($this->newTicketSubject),
            'category' => $this->newTicketCategory,
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => trim($this->newTicketMessage),
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        try {
            broadcast(new TicketMessageSent($message, $ticket->id, $ticket->customer_id));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Websocket Server Error (Reverb/Pusher offline?): ' . $e->getMessage());
        }

        $isOnline = Cache::has('is_online' . $ticket->customer_id);
        if (!$isOnline) {
            $gamification = CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                try {
                    Mail::to($customer->email)->send(new \App\Mail\SupportTicketUpdateMailToCustomer($customer, $ticket));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Ticket Update Mail Error: ' . $e->getMessage());
                }
            }
        }

        $this->isCreatingTicket = false;
        $this->filterStatus = 'open';
        $this->activeTicketId = $ticket->id;
        $this->reset(['newTicketCustomerSearch', 'newTicketCustomerId', 'newTicketOrderId', 'newTicketSubject', 'newTicketMessage', 'newTicketAttachments']);
        $this->calculateKPIs();
        $this->dispatch('notify', message: 'Direktnachricht an Kunden gesendet.', type: 'success');
        $this->dispatch('ticket-message-received');
    }

    public function getCustomerSearchResultsProperty(): array
    {
        $rawQuery = trim((string) $this->newTicketCustomerSearch);
        if (!empty($this->newTicketCustomerId) || $rawQuery === '') {
            return [
                'items' => [],
                'total' => 0,
                'has_more' => false,
                'query' => $rawQuery,
            ];
        }

        $qLower = mb_strtolower($rawQuery);
        $tokens = array_values(array_filter(preg_split('/\s+/u', $qLower) ?: [], fn ($t) => $t !== ''));
        if (empty($tokens)) {
            $tokens = [$qLower];
        }

        $stems = [];
        foreach ($tokens as $token) {
            foreach ($this->buildSearchStems($token) as $stem) {
                $stems[$stem] = $stem;
            }
        }
        $stems = array_values($stems);

        $candidates = \App\Models\Customer\Customer::with('profile')
            ->select(['id', 'first_name', 'last_name', 'email', 'created_at'])
            ->where(function ($sub) use ($rawQuery, $stems) {
                $escapedRaw = addcslashes($rawQuery, '%_\\');
                $sub->where('first_name', 'like', '%' . $escapedRaw . '%')
                    ->orWhere('last_name', 'like', '%' . $escapedRaw . '%')
                    ->orWhere('email', 'like', '%' . $escapedRaw . '%')
                    ->orWhere('id', 'like', '%' . $escapedRaw . '%')
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%' . $escapedRaw . '%'])
                    ->orWhereRaw("CONCAT(COALESCE(last_name, ''), ' ', COALESCE(first_name, '')) LIKE ?", ['%' . $escapedRaw . '%']);

                foreach ($stems as $stem) {
                    $escapedStem = addcslashes($stem, '%_\\');
                    $sub->orWhere('first_name', 'like', '%' . $escapedStem . '%')
                        ->orWhere('last_name', 'like', '%' . $escapedStem . '%')
                        ->orWhere('email', 'like', '%' . $escapedStem . '%');
                }
            })
            ->limit(150)
            ->get();

        $phoneticQuery = $this->normalizePhoneticName($qLower);
        $phoneticTokens = array_map(fn ($t) => $this->normalizePhoneticName($t), $tokens);

        $ranked = [];
        foreach ($candidates as $customer) {
            $firstName = trim((string) $customer->first_name);
            $lastName = trim((string) $customer->last_name);
            $fullName = trim($firstName . ' ' . $lastName);
            $reverseName = trim($lastName . ' ' . $firstName);
            $email = trim((string) $customer->email);
            $city = trim((string) ($customer->profile?->city ?? ''));

            $firstLower = mb_strtolower($firstName);
            $lastLower = mb_strtolower($lastName);
            $fullLower = mb_strtolower($fullName);
            $reverseLower = mb_strtolower($reverseName);
            $emailLower = mb_strtolower($email);
            $idLower = mb_strtolower((string) $customer->id);

            $score = 0;
            $isSimilar = false;

            // 1. Direkte Treffer (Exakt, Präfix, Teilstring, Vollname)
            if ($fullLower === $qLower || $reverseLower === $qLower || $emailLower === $qLower || $firstLower === $qLower || $lastLower === $qLower) {
                $score = 1000;
            } elseif (str_starts_with($firstLower, $qLower) || str_starts_with($lastLower, $qLower) || str_starts_with($fullLower, $qLower) || str_starts_with($reverseLower, $qLower)) {
                $score = 920;
            } else {
                $allTokensDirect = true;
                $anyTokenPrefix = false;
                foreach ($tokens as $tok) {
                    $inCustomer = str_contains($fullLower, $tok) || str_contains($emailLower, $tok) || str_contains($idLower, $tok);
                    if (!$inCustomer) {
                        $allTokensDirect = false;
                    }
                    if (str_starts_with($firstLower, $tok) || str_starts_with($lastLower, $tok)) {
                        $anyTokenPrefix = true;
                    }
                }

                if ($allTokensDirect) {
                    $score = $anyTokenPrefix ? 850 : 780;
                } elseif (str_contains($fullLower, $qLower) || str_contains($reverseLower, $qLower) || str_contains($emailLower, $qLower) || str_contains($idLower, $qLower)) {
                    $score = 720;
                }
            }

            // 2. Ähnlichkeits- & Phonetik-Treffer (z. B. Klara <-> Clara, Mayer <-> Meier, Tippfehler)
            if ($score === 0 && mb_strlen($qLower) >= 2) {
                $phoneticFirst = $this->normalizePhoneticName($firstLower);
                $phoneticLast = $this->normalizePhoneticName($lastLower);
                $phoneticFull = trim($phoneticFirst . ' ' . $phoneticLast);

                if ($phoneticQuery !== '' && ($phoneticFirst === $phoneticQuery || $phoneticLast === $phoneticQuery || $phoneticFull === $phoneticQuery)) {
                    $score = 650;
                    $isSimilar = true;
                } elseif ($phoneticQuery !== '' && (str_starts_with($phoneticFirst, $phoneticQuery) || str_starts_with($phoneticLast, $phoneticQuery) || str_contains($phoneticFull, $phoneticQuery))) {
                    $score = 610;
                    $isSimilar = true;
                } else {
                    $matchedSimilarTokens = 0;
                    $bestTokenScore = 0;

                    foreach ($tokens as $idx => $tok) {
                        $tokLen = mb_strlen($tok);
                        if ($tokLen < 2) {
                            continue;
                        }

                        if (str_contains($fullLower, $tok) || str_contains($emailLower, $tok)) {
                            $matchedSimilarTokens++;
                            $bestTokenScore = max($bestTokenScore, 590);
                            continue;
                        }

                        $pTok = $phoneticTokens[$idx] ?? '';
                        if ($pTok !== '' && (str_contains($phoneticFirst, $pTok) || str_contains($phoneticLast, $pTok))) {
                            $matchedSimilarTokens++;
                            $bestTokenScore = max($bestTokenScore, 580);
                            continue;
                        }

                        if ($tokLen >= 3) {
                            $firstPrefix = mb_substr($firstLower, 0, min(mb_strlen($firstLower), $tokLen + 1));
                            $lastPrefix = mb_substr($lastLower, 0, min(mb_strlen($lastLower), $tokLen + 1));

                            $distFirst = levenshtein($tok, $firstLower);
                            $distLast = levenshtein($tok, $lastLower);
                            $distFirstPrefix = $firstPrefix !== '' ? levenshtein($tok, $firstPrefix) : 99;
                            $distLastPrefix = $lastPrefix !== '' ? levenshtein($tok, $lastPrefix) : 99;
                            $minDist = min($distFirst, $distLast, $distFirstPrefix, $distLastPrefix);

                            $maxAllowedDist = $tokLen >= 5 ? 2 : 1;
                            if ($minDist <= $maxAllowedDist) {
                                $matchedSimilarTokens++;
                                $bestTokenScore = max($bestTokenScore, 560 - ($minDist * 30));
                                continue;
                            }

                            $pctFirst = 0.0;
                            $pctLast = 0.0;
                            similar_text($tok, $firstLower, $pctFirst);
                            similar_text($tok, $lastLower, $pctLast);
                            $maxPct = max($pctFirst, $pctLast);
                            if ($maxPct >= 72.0) {
                                $matchedSimilarTokens++;
                                $bestTokenScore = max($bestTokenScore, (int) round(400 + $maxPct));
                            }
                        }
                    }

                    if ($matchedSimilarTokens >= count($tokens) && $bestTokenScore > 0) {
                        $score = $bestTokenScore;
                        $isSimilar = true;
                    }
                }
            }

            if ($score > 0) {
                $initials = mb_strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
                $ranked[] = [
                    'id' => (string) $customer->id,
                    'short_id' => 'KDN-' . strtoupper(substr(str_replace('-', '', (string) $customer->id), 0, 6)),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'full_name' => $fullName !== '' ? $fullName : $email,
                    'email' => $email,
                    'city' => $city,
                    'initials' => $initials !== '' ? $initials : 'KD',
                    'is_similar' => $isSimilar,
                    'score' => $score,
                    'model' => $customer,
                ];
            }
        }

        usort($ranked, function (array $a, array $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return strcasecmp($a['full_name'], $b['full_name']);
        });

        $totalMatches = count($ranked);

        return [
            'items' => array_slice($ranked, 0, 15),
            'total' => $totalMatches,
            'has_more' => $totalMatches > 15,
            'query' => $rawQuery,
        ];
    }

    public function getSearchedCustomersProperty()
    {
        return collect($this->customerSearchResults['items'])->pluck('model');
    }

    private function normalizePhoneticName(string $value): string
    {
        $v = mb_strtolower(trim($value));
        if ($v === '') {
            return '';
        }

        $v = strtr($v, [
            'ä' => 'e',
            'ö' => 'o',
            'ü' => 'u',
            'ß' => 's',
            'ae' => 'e',
            'oe' => 'o',
            'ue' => 'u',
            'á' => 'a',
            'à' => 'a',
            'â' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ì' => 'i',
            'î' => 'i',
            'ó' => 'o',
            'ò' => 'o',
            'ô' => 'o',
            'ú' => 'u',
            'ù' => 'u',
            'û' => 'u',
            'ph' => 'f',
            'th' => 't',
            'dt' => 't',
            'tz' => 'z',
            'ck' => 'k',
            'ch' => 'k',
            'qu' => 'kw',
            'ai' => 'ei',
            'ay' => 'ei',
            'ey' => 'ei',
            'v' => 'f',
            'c' => 'k',
            'y' => 'i',
        ]);

        return (string) preg_replace('/(.)\1+/u', '$1', $v);
    }

    private function buildSearchStems(string $token): array
    {
        $t = mb_strtolower(trim($token));
        if ($t === '') {
            return [];
        }

        $variants = [$t];

        $swaps = [
            'c' => 'k',
            'k' => 'c',
            'ph' => 'f',
            'f' => 'ph',
            'ai' => 'ei',
            'ay' => 'ei',
            'ey' => 'ei',
            'ei' => 'ai',
            'ae' => 'ä',
            'oe' => 'ö',
            'ue' => 'ü',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ss' => 'ß',
            'ß' => 'ss',
        ];

        foreach ($swaps as $from => $to) {
            if (str_contains($t, $from)) {
                $variants[] = str_replace($from, $to, $t);
            }
        }

        $collapsed = (string) preg_replace('/(.)\1+/u', '$1', $t);
        if ($collapsed !== '' && $collapsed !== $t) {
            $variants[] = $collapsed;
        }

        $len = mb_strlen($t);
        if ($len >= 3) {
            $variants[] = mb_substr($t, 0, 3);
            $variants[] = mb_substr($t, -3);
            if ($len >= 5) {
                $variants[] = mb_substr($t, 1, 3);
            }
        } elseif ($len === 2) {
            $variants[] = $t;
        }

        return array_values(array_unique(array_filter($variants, fn ($s) => mb_strlen($s) >= 2)));
    }

    public function getSelectedNewTicketCustomerProperty(): ?\App\Models\Customer\Customer
    {
        if (empty($this->newTicketCustomerId)) {
            return null;
        }
        return \App\Models\Customer\Customer::withTrashed()->find($this->newTicketCustomerId);
    }

    public function getSelectedCustomerOrdersProperty()
    {
        if (empty($this->newTicketCustomerId)) {
            return collect();
        }
        return \App\Models\Order\OrderOrder::where('customer_id', $this->newTicketCustomerId)
            ->latest()
            ->limit(25)
            ->get();
    }

    private function markAsReadAdmin($id)
    {
        SupportTicketMessage::where('support_ticket_id', $id)
            ->where('sender_type', 'customer')
            ->update(['is_read_by_admin' => true]);
    }

    public function sendReply()
    {
        $this->validate([
            'replyMessage' => 'required|string|min:1|max:10000',
            'replyAttachments' => 'nullable|array|max:10',
            'replyAttachments.*' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,pdf,txt,docx|max:10240',
        ]);

        $ticket = SupportTicketModel::with('customer')->findOrFail($this->activeTicketId);

        $allowedMimeMap = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        ];
        $attachmentPaths = [];
        foreach ($this->replyAttachments as $photo) {
            if (!is_object($photo) || !method_exists($photo, 'getClientOriginalExtension') || !method_exists($photo, 'getRealPath')) {
                continue;
            }
            $ext = strtolower((string) $photo->getClientOriginalExtension());
            if (!isset($allowedMimeMap[$ext])) {
                continue;
            }
            $realPath = $photo->getRealPath();
            $detectedMime = ($realPath && is_file($realPath) && function_exists('finfo_open'))
                ? strtolower((string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $realPath))
                : strtolower((string) $photo->getMimeType());
            if (in_array($detectedMime, ['application/x-empty', 'inode/x-empty'], true) && method_exists($photo, 'getMimeType')) {
                $detectedMime = strtolower((string) $photo->getMimeType());
            }
            if (!in_array($detectedMime, $allowedMimeMap[$ext], true)) {
                continue;
            }
            $safeFilename = \Illuminate\Support\Str::uuid()->toString() . '.' . $ext;
            $attachmentPaths[] = $photo->storeAs('support/tickets/attachments', $safeFilename, 'public');
        }

        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => $this->replyMessage,
            'attachments' => !empty($attachmentPaths) ? $attachmentPaths : null,
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update(['status' => 'answered']);
        $this->filterStatus = 'answered';

        try {
            // Versuchen das Event über Websockets zu schießen
            broadcast(new TicketMessageSent($message, $ticket->id, $ticket->customer_id));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Websocket Server Error (Reverb/Pusher offline?): ' . $e->getMessage());
        }

        // Präzise Prüfung: Ist der Nutzer aktuell wirklich online? (1-Minuten-Zeitfenster durch UserLastActivity Middleware)
        $isOnline = \Illuminate\Support\Facades\Cache::has('is_online' . $ticket->customer_id);

        if (!$isOnline && $ticket->customer) {
            $gamification = \App\Models\Customer\CustomerGamification::where('customer_id', $ticket->customer_id)->first();
            if (!$gamification || $gamification->ticket_emails_enabled) {
                try {
                    \Illuminate\Support\Facades\Mail::to($ticket->customer->email)->send(new \App\Mail\SupportTicketUpdateMailToCustomer($ticket->customer, $ticket));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Ticket Update Mail Error: ' . $e->getMessage());
                }
            }
        }

        $this->reset(['replyMessage', 'replyAttachments']);
        $this->calculateKPIs();
        $this->dispatch('ticket-message-received');
    }

    public function closeTicket()
    {
        $ticket = SupportTicketModel::findOrFail($this->activeTicketId);
        $ticket->update(['status' => 'closed']);
        $this->filterStatus = 'closed';
        $this->calculateKPIs();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'SupportTicket geschlossen.']);
    }

    public function removeAttachment($index)
    {
        array_splice($this->replyAttachments, $index, 1);
    }

    public function render()
    {
        $query = SupportTicketModel::with('customer', 'messages', 'order');

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        if (!empty($this->search)) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('ticket_number', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('subject', 'like', '%' . $escapedSearch . '%')
                    ->orWhereHas('customer', function ($subQ) use ($escapedSearch) {
                        $subQ->where('email', 'like', '%' . $escapedSearch . '%')
                            ->orWhere('first_name', 'like', '%' . $escapedSearch . '%')
                            ->orWhere('last_name', 'like', '%' . $escapedSearch . '%');
                    });
            });
        }

        $tickets = $query->orderBy('updated_at', 'desc')->paginate(15);
        $activeSupportTicket = $this->activeTicketId ? SupportTicketModel::with('messages', 'customer', 'order')->find($this->activeTicketId) : null;

        return view('livewire.shop.support.support-ticket', [
            'tickets' => $tickets,
            'activeTicket' => $activeSupportTicket
        ]);
    }
}
