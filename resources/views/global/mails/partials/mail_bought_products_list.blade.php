<table class="table">
    <thead>
    <tr>
        <th width="60%">Artikel & Konfiguration</th>
        <th width="15%" class="text-right">Menge</th>
        <th width="25%" class="text-right">Preis</th>
    </tr>
    </thead>
    <tbody>
    @foreach($data['items'] as $item)
        @php
            $conf = $item['config'] ?? [];
            $imgPath = $conf['product_image_path'] ?? null;
            $hasImage = !empty($imgPath);
        @endphp
        <tr>
            <td>
                <strong style="font-size: 14px; color: #222;">{{ $item['name'] }}</strong>

                {{-- VISUELLE VORSCHAU --}}
                @if($hasImage)
                    <div class="preview-wrapper">
                        <div class="preview-container" style="background-image: url('{{ asset($imgPath) }}');">
                            @if(isset($conf['text_x']))
                                <div class="marker marker-text" style="left: {{ $conf['text_x'] }}%; top: {{ $conf['text_y'] }}%;"></div>
                            @endif

                            @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path']))
                                <div class="marker marker-logo" style="left: {{ $conf['logo_x'] }}%; top: {{ $conf['logo_y'] }}%;"></div>
                            @endif
                        </div>
                        <div style="font-size: 9px; color: #999; margin-top: 2px;">
                            @if(isset($conf['text_x'])) <span style="color:#007bff;">●</span> Text @endif
                            @if(isset($conf['logo_x']) && !empty($conf['logo_storage_path'])) <span style="color:#28a745; margin-left:5px;">●</span> Logo @endif
                        </div>
                    </div>
                @endif

                {{-- DETAILS --}}
                <div class="detail-info">
                    @if(!empty($conf['text']))
                        <div><span class="detail-label">Gravur:</span> "{{ $conf['text'] }}"</div>
                        <div><span class="detail-label">Schrift:</span> {{ $conf['font'] ?? 'Standard' }}</div>
                    @endif

                    @if(!empty($conf['logo_storage_path']))
                        <div style="margin-top: 4px;">
                            <span class="detail-label">Logo:</span>
                            <a href="{{ asset('storage/'.$conf['logo_storage_path']) }}" style="color:#C5A059; text-decoration:underline;">Datei ansehen</a>
                        </div>
                    @endif
                </div>

                {{-- HINWEIS --}}
                @if(!empty($conf['notes']))
                    <div class="note-box">
                        <strong>Deine Anmerkung:</strong><br>
                        {{ $conf['notes'] }}
                    </div>
                @endif
            </td>
            <td class="text-right">{{ $item['quantity'] }}x</td>
            <td class="text-right">{{ $item['total_price'] }} €</td>
        </tr>
    @endforeach
    </tbody>
</table>
