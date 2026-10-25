{{-- resources/views/mails/partials/mail_item_list.blade.php --}}
<table class="table" style="width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px;">
    <thead>
    <tr>
        <th style="text-align: left; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;">Artikel & Konfiguration</th>
        <th style="text-align: right; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;" width="15%">Menge</th>
        <th style="text-align: right; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px;" width="25%">Preis</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['items'] as $item)
        {{-- Sicherheitsfilter: Versandkosten/Express werden hier nicht als Zeile gelistet --}}
        @if(!str_contains(strtolower($item['name']), 'versand') && !str_contains(strtolower($item['name']), 'express'))
            @php
                $conf = $item['config'] ?? [];
                
                // Image Paths extrahieren
                $imgPaths = [];
                if (isset($conf['snapshot_path'])) {
                    if (is_array($conf['snapshot_path'])) {
                        if (isset($conf['snapshot_path']['front'])) $imgPaths['front'] = $conf['snapshot_path']['front'];
                        if (isset($conf['snapshot_path']['back'])) $imgPaths['back'] = $conf['snapshot_path']['back'];
                    } elseif (is_string($conf['snapshot_path'])) {
                        $imgPaths['front'] = $conf['snapshot_path'];
                    }
                }
                
                if (empty($imgPaths) && !empty($item['main_image'])) {
                    $imgPaths['front'] = $item['main_image'];
                }

                $imgUrls = [];
                $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $allowedRoots = array_values(array_filter([
                    realpath(storage_path('app/public')),
                    realpath(public_path()),
                ]));

                $isSafePublicFile = function (string $candidatePath) use ($allowedRoots, $allowedImageMimes): ?array {
                    if (!file_exists($candidatePath) || is_dir($candidatePath)) {
                        return null;
                    }
                    $real = realpath($candidatePath);
                    if ($real === false) {
                        return null;
                    }
                    $inAllowedRoot = false;
                    foreach ($allowedRoots as $root) {
                        if ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                            $inAllowedRoot = true;
                            break;
                        }
                    }
                    if (!$inAllowedRoot) {
                        return null;
                    }
                    $mime = @mime_content_type($real) ?: '';
                    if (!in_array($mime, $allowedImageMimes, true)) {
                        return null;
                    }
                    return ['real' => $real, 'mime' => $mime];
                };

                foreach ($imgPaths as $key => $imgPath) {
                    $imgUrl = null;
                    if (!is_string($imgPath) || $imgPath === '' || str_contains($imgPath, '..') || str_contains($imgPath, "\0") || str_contains($imgPath, '\\')) {
                        continue;
                    }
                    if (preg_match('#^https?://#i', $imgPath)) {
                        if (empty($isPdf)) {
                            $imgUrl = $imgPath;
                        }
                    } else {
                        $cleanRelPath = ltrim(preg_replace('#^/?storage/#', '', $imgPath), '/');
                        // Snapshot-Pfade normalisieren falls das 'system/' Prefix fehlt
                        if (str_starts_with($cleanRelPath, 'snapshots/')) {
                            $cleanRelPath = 'system/' . $cleanRelPath;
                        }

                        if (isset($isPdf) && $isPdf) {
                            $candidates = [
                                storage_path('app/public/' . $cleanRelPath),
                                public_path('storage/' . $cleanRelPath),
                                public_path($cleanRelPath),
                                storage_path('app/public/' . ltrim(preg_replace('#^system/#', '', $cleanRelPath), '/')),
                            ];
                            foreach ($candidates as $cand) {
                                $safeFile = $isSafePublicFile($cand);
                                if ($safeFile) {
                                    $dataInfo = base64_encode(file_get_contents($safeFile['real']));
                                    $imgUrl = 'data:' . $safeFile['mime'] . ';base64,' . $dataInfo;
                                    break;
                                }
                            }
                            if (!$imgUrl && !empty($item['main_image']) && is_string($item['main_image']) && !str_contains($item['main_image'], '..') && !str_contains($item['main_image'], "\0") && !str_contains($item['main_image'], '\\')) {
                                $fallbackRel = ltrim(preg_replace('#^/?storage/#', '', $item['main_image']), '/');
                                foreach ([storage_path('app/public/' . $fallbackRel), public_path('storage/' . $fallbackRel), public_path($fallbackRel)] as $cand) {
                                    $safeFile = $isSafePublicFile($cand);
                                    if ($safeFile) {
                                        $dataInfo = base64_encode(file_get_contents($safeFile['real']));
                                        $imgUrl = 'data:' . $safeFile['mime'] . ';base64,' . $dataInfo;
                                        break;
                                    }
                                }
                            }
                        } else {
                            $safeFallbackImage = (!empty($item['main_image']) && is_string($item['main_image']) && !str_contains($item['main_image'], '..') && !str_contains($item['main_image'], "\0") && !str_contains($item['main_image'], '\\'))
                                ? asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $item['main_image']), '/'))
                                : null;
                            $imgUrl = resolve_snapshot_url($cleanRelPath, $safeFallbackImage);
                        }
                    }
                    if ($imgUrl) {
                        $imgUrls[$key] = $imgUrl;
                    }
                }
            @endphp
            <tr>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
                    <strong style="font-size: 14px; color: #222; display: block; margin-bottom: 5px;">{{ $item['name'] }}</strong>

                    {{-- VISUELLE VORSCHAU DER KONFIGURATION ODER PRODUKTBILD --}}
                    @if(!empty($imgUrls))
                        <table style="margin-top: 10px; margin-bottom: 10px; border-collapse: collapse;">
                            <tr>
                            @foreach($imgUrls as $key => $imgUrl)
                                <td style="padding-right: 10px; vertical-align: top;">
                                    <table cellpadding="0" cellspacing="0" border="0" style="width: 80px; height: 80px; border: 1px solid #e5e7eb; border-radius: 6px; background-color: #ffffff; border-collapse: separate; table-layout: fixed;">
                                        <tr>
                                            <td align="center" valign="middle" style="width: 80px; height: 80px; text-align: center; vertical-align: middle; padding: 2px;">
                                                <img src="{{ $imgUrl }}" style="max-width: 76px; max-height: 76px; width: auto; height: auto; display: block; margin: 0 auto; border: 0;" alt="{{ $key === 'back' ? 'Rückseite' : 'Vorderseite' }}">
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            @endforeach
                            </tr>
                        </table>
                    @endif

                    {{-- DETAILS & KONFIGURATIONSDATEN --}}
                    <div style="font-size: 11px; color: #666; line-height: 1.5;">
                        @if(!empty($conf['texts']) && is_array($conf['texts']))
                            @foreach($conf['texts'] as $idx => $t)
                                @if(!empty($t['text']))
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">{{ count($conf['texts']) > 1 ? 'Text '.($idx+1).' (Vorderseite)' : 'Gravur (Vorderseite)' }}:</strong> "{!! nl2br(e($t['text'])) !!}"</div>
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">Schrift:</strong> {{ $t['font'] ?? 'Standard' }}</div>
                                @endif
                            @endforeach
                        @elseif(!empty($conf['text']))
                            <div style="margin-bottom: 2px;"><strong style="color: #444;">Gravur:</strong> "{!! nl2br(e($conf['text'])) !!}"</div>
                            <div style="margin-bottom: 2px;"><strong style="color: #444;">Schrift:</strong> {{ $conf['font'] ?? 'Standard' }}</div>
                        @endif

                        @if(!empty($conf['texts_back']) && is_array($conf['texts_back']))
                            <div style="margin-top: 5px;"></div>
                            @foreach($conf['texts_back'] as $idx => $t)
                                @if(!empty($t['text']))
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">{{ count($conf['texts_back']) > 1 ? 'Text '.($idx+1).' (Rückseite)' : 'Gravur (Rückseite)' }}:</strong> "{!! nl2br(e($t['text'])) !!}"</div>
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">Schrift:</strong> {{ $t['font'] ?? 'Standard' }}</div>
                                @endif
                            @endforeach
                        @endif

                        @php
                            $uploadedFilesCount = isset($conf['files']) && is_array($conf['files']) ? count($conf['files']) : 0;
                        @endphp
                        @if($uploadedFilesCount > 0)
                            <div style="margin-top: 5px; margin-bottom: 2px;"><strong style="color: #444;">Hinterlegte Bilder:</strong> {{ $uploadedFilesCount }} Datei(en)</div>
                        @endif

                        @if(!empty($conf['logo_storage_path']) && is_string($conf['logo_storage_path']) && !str_contains($conf['logo_storage_path'], '..') && !str_contains($conf['logo_storage_path'], "\0") && !str_contains($conf['logo_storage_path'], '\\'))
                            <div style="margin-top: 4px; margin-bottom: 4px;">
                                <strong style="color: #444;">Logo-Datei:</strong>
                                <a href="{{ asset('storage/' . ltrim($conf['logo_storage_path'], '/')) }}" style="color:#C5A059; text-decoration:underline;">Ansehen / Download</a>
                            </div>
                        @endif

                        {{-- Digitales Siegel für Sicherheit --}}
                        @if(isset($order))
                            @php
                                $orderItem = $order->items->firstWhere('product_name', $item['name']);
                            @endphp
                            @if($orderItem && !empty($orderItem->config_fingerprint))
                                <div style="margin-top: 10px; padding: 6px 10px; background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 4px; display: inline-block;">
                                    <span style="font-size: 8px; font-weight: bold; color: #166534; display: block; text-transform: uppercase; letter-spacing: 0.5px;">Produktions-ID</span>
                                    <span style="font-size: 9px; color: #166534; font-family: monospace;">{{ substr($orderItem->config_fingerprint, 0, 16) }}</span>
                                </div>
                            @endif
                        @endif

                        {{-- GESCHENKGUTSCHEIN DETAILS (Empfänger, Widmung & Versandart) --}}
                        @if(!empty($conf['is_gift_voucher']))
                            @php
                                $voucherDelivery = $conf['delivery_method'] ?? 'email';
                                $voucherSurchargeCents = (int) ($conf['shipping_surcharge'] ?? shop_setting('shipping_cost_voucher', 350));
                            @endphp
                            <div style="margin-top: 4px; margin-bottom: 4px;">
                                @if(!empty($conf['recipient_name']))
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">Für:</strong> {{ $conf['recipient_name'] }}</div>
                                @endif
                                @if(!empty($conf['personal_message']))
                                    <div style="margin-bottom: 2px;"><strong style="color: #444;">Persönliche Nachricht:</strong> „{{ $conf['personal_message'] }}“</div>
                                @endif
                                <div style="margin-top: 4px;">
                                    <strong style="color: #444;">Zustellung:</strong>
                                    @if($voucherDelivery === 'post')
                                        <span style="color: #b45309; font-weight: bold;">
                                            ✉️ Per Post im Umschlag (zzgl. {{ number_format($voucherSurchargeCents / 100, 2, ',', '.') }} € Versand)
                                        </span>
                                    @else
                                        <span style="color: #047857; font-weight: bold;">
                                            📧 Per E-Mail / PDF-Download (Versandkostenfrei)
                                        </span>
                                        @if(!empty($conf['recipient_email']))
                                            <span style="color: #6b7280;">({{ $conf['recipient_email'] }})</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- HINWEIS FÜR STANDARD PRODUKTE (Nicht bei Geschenkgutscheinen anzeigen) --}}
                        @if(empty($conf['is_gift_voucher']) && isset($item['is_personalizable']) && $item['is_personalizable'] === false)
                             <div style="margin-top: 8px; font-style: italic; color: #166534; font-size: 10px;">
                                 ✓ Handgefertigter Standard-Artikel<br>
                                 <span style="font-size: 9px; color: #78716c;">(Keine Personalisierung durch Kunden vorgesehen.)</span>
                             </div>
                        @endif
                    </div>

                    {{-- KUNDEN-ANMERKUNG --}}
                    @if(!empty($conf['notes']))
                        <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 10px; margin-top: 10px; font-size: 11px; border-radius: 4px; line-height: 1.4;">
                            <strong style="display: block; margin-bottom: 2px;">Hinweis für die Manufaktur:</strong> {{ $conf['notes'] }}
                        </div>
                    @endif
                </td>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; text-align: right; color: #222; font-weight: 500;">{{ $item['quantity'] }}x</td>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; text-align: right; font-weight: bold; color: #222;">{{ $item['total_price'] }} €</td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
