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
                foreach ($imgPaths as $key => $imgPath) {
                    $imgUrl = null;
                    if (str_starts_with($imgPath, 'http')) {
                        $imgUrl = $imgPath;
                    } else {
                        $pathPrefix = str_starts_with($imgPath, 'storage/') ? '' : 'storage/';
                        if (isset($isPdf) && $isPdf) {
                            $fullLocalPath = public_path($pathPrefix . $imgPath);
                            if (file_exists($fullLocalPath)) {
                                $mime = mime_content_type($fullLocalPath);
                                $dataInfo = base64_encode(file_get_contents($fullLocalPath));
                                $imgUrl = 'data:' . $mime . ';base64,' . $dataInfo;
                            }
                        }
                        
                        if (!$imgUrl) {
                            $imgUrl = asset($pathPrefix . $imgPath);
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
                                    <div style="width: 80px; height: 80px; border: 1px solid #e5e5e5; border-radius: 4px; background-color: #f9f9f9; text-align: center; vertical-align: middle;">
                                        <img src="{{ $imgUrl }}" style="max-width: 78px; max-height: 78px; display: inline-block; margin-top: 1px;" alt="{{ $key === 'back' ? 'Rückseite' : 'Vorderseite' }}">
                                    </div>
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

                        @if(!empty($conf['logo_storage_path']))
                            <div style="margin-top: 4px; margin-bottom: 4px;">
                                <strong style="color: #444;">Logo-Datei:</strong>
                                <a href="{{ asset('storage/'.$conf['logo_storage_path']) }}" style="color:#C5A059; text-decoration:underline;">Ansehen / Download</a>
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

                        {{-- HINWEIS FÜR STANDARD PRODUKTE --}}
                        @if(isset($item['is_personalizable']) && $item['is_personalizable'] === false)
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
