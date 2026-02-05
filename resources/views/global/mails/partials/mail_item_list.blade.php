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
                $imgPath = $conf['product_image_path'] ?? null;
            @endphp
            <tr>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
                    <strong style="font-size: 14px; color: #222; display: block; margin-bottom: 5px;">{{ $item['name'] }}</strong>

                    {{-- VISUELLE VORSCHAU DER KONFIGURATION --}}
                    @if(!empty($imgPath))
                        <div class="preview-wrapper" style="margin-top: 10px; margin-bottom: 10px; display: block;">
                            <div class="preview-container" style="position: relative; width: 100px; height: 100px; display: block; border: 1px solid #e5e5e5; border-radius: 4px; background-color: #f9f9f9; overflow: hidden;">
                                <img src="{{ asset($imgPath) }}" style="width: 100%; height: 100%; object-fit: contain; display: block;">

                                @if(isset($conf['text_x']))
                                    <div class="marker" style="position: absolute; width: 6px; height: 6px; border-radius: 50%; background-color: #007bff; border: 1px solid white; left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%; transform: translate(-50%, -50%); z-index: 10;"></div>
                                @endif

                                @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path']))
                                    <div class="marker" style="position: absolute; width: 6px; height: 6px; border-radius: 50%; background-color: #28a745; border: 1px solid white; left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%; transform: translate(-50%, -50%); z-index: 10;"></div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- DETAILS & KONFIGURATIONSDATEN --}}
                    <div style="font-size: 11px; color: #666; line-height: 1.5;">
                        @if(!empty($conf['text']))
                            <div style="margin-bottom: 2px;"><strong style="color: #444;">Gravur:</strong> "{{ $conf['text'] }}"</div>
                            <div style="margin-bottom: 2px;"><strong style="color: #444;">Schrift:</strong> {{ $conf['font'] ?? 'Standard' }}</div>
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
