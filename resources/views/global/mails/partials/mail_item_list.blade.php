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
        {{-- Versandkosten und Express werden hier ignoriert, da sie in die Preisliste gehören --}}
        @if(!str_contains(strtolower($item['name']), 'versand') && !str_contains(strtolower($item['name']), 'express'))
            @php
                $conf = $item['config'] ?? [];
                $imgPath = $conf['product_image_path'] ?? null;
            @endphp
            <tr>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top;">
                    <strong style="font-size: 14px; color: #222;">{{ $item['name'] }}</strong>

                    {{-- VISUELLE VORSCHAU --}}
                    @if(!empty($imgPath))
                        <div class="preview-wrapper" style="margin-top: 10px; display: block;">
                            <div class="preview-container" style="position: relative; width: 100px; height: 100px; display: inline-block; border: 1px solid #e5e5e5; border-radius: 4px; background-color: #f9f9f9; background-image: url('{{ asset($imgPath) }}'); background-repeat: no-repeat; background-position: center center; background-size: contain; overflow: hidden;">
                                @if(isset($conf['text_x']))
                                    <div class="marker" style="position: absolute; width: 8px; height: 8px; border-radius: 50%; margin-left: -4px; margin-top: -4px; border: 1px solid white; box-shadow: 0 0 2px rgba(0,0,0,0.5); z-index: 20; background-color: #007bff; left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%;"></div>
                                @endif

                                @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path']))
                                    <div class="marker" style="position: absolute; width: 8px; height: 8px; border-radius: 50%; margin-left: -4px; margin-top: -4px; border: 1px solid white; box-shadow: 0 0 2px rgba(0,0,0,0.5); z-index: 20; background-color: #28a745; left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%;"></div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- DETAILS --}}
                    <div style="font-size: 11px; color: #666; margin-top: 8px; line-height: 1.4;">
                        @if(!empty($conf['text']))
                            <div><strong style="color: #444;">Gravur:</strong> "{{ $conf['text'] }}"</div>
                            <div><strong style="color: #444;">Schrift:</strong> {{ $conf['font'] ?? 'Standard' }}</div>
                        @endif

                        @if(!empty($conf['logo_storage_path']))
                            <div style="margin-top: 4px;">
                                <strong style="color: #444;">Logo:</strong>
                                <a href="{{ asset('storage/'.$conf['logo_storage_path']) }}" style="color:#C5A059; text-decoration:underline;">Datei ansehen</a>
                            </div>
                        @endif

                        {{-- Fingerprint/Siegel nur anzeigen, wenn $order vorhanden ist --}}
                        @if(isset($order))
                            @php
                                $orderItem = $order->items->firstWhere('product_name', $item['name']);
                            @endphp
                            @if($orderItem && !empty($orderItem->config_fingerprint))
                                <div style="margin-top: 10px; padding: 6px 10px; background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 4px; display: inline-block;">
                                    <span style="font-size: 8px; font-weight: bold; color: #166534; display: block; text-transform: uppercase;">Digitales Siegel</span>
                                    <span style="font-size: 9px; color: #166534; font-family: monospace;">{{ substr($orderItem->config_fingerprint, 0, 16) }}...</span>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- ANMERKUNG --}}
                    @if(!empty($conf['notes']))
                        <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 8px; margin-top: 8px; font-size: 11px; border-radius: 4px;">
                            <strong>Anmerkung:</strong> {{ $conf['notes'] }}
                        </div>
                    @endif
                </td>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; text-align: right;">{{ $item['quantity'] }}x</td>
                <td style="padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; text-align: right; font-weight: bold;">{{ $item['total_price'] }} €</td>
            </tr>
        @endif
    @endforeach
    </tbody>
</table>
