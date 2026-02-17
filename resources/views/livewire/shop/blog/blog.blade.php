<div>
    <div class="p-6 bg-gray-50 min-h-screen">

        {{-- ========================================== --}}
        {{-- LIST VIEW --}}
        {{-- ========================================== --}}
        @if($viewMode === 'list')
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-3xl font-serif font-bold text-gray-900">Magazin & Blog</h1>
                        <p class="text-sm text-gray-500 mt-1">Inhalte verwalten, inspirieren und informieren.</p>
                    </div>
                    <button wire:click="create" class="bg-primary text-white px-5 py-2.5 rounded-lg shadow-md hover:bg-primary-dark transition flex items-center gap-2 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                        Neuer Beitrag
                    </button>
                </div>

                {{-- Flash Messages --}}
                @if(session()->has('success'))
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 text-green-700 shadow-sm rounded-r flex justify-between items-center animate-fade-in">
                        <span>{{ session('success') }}</span>
                        <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                @endif

                {{-- Search & Table --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    {{-- Search Header --}}
                    <div class="p-4 border-b border-gray-100 flex gap-4 bg-white sticky top-0 z-10">
                        <div class="relative flex-1">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Beitrag suchen..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary text-sm shadow-sm transition-all">
                            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Responsive Table Wrapper --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            {{-- Kopfzeile: Nur auf Desktop sichtbar --}}
                            <thead class="hidden md:table-header-group bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4 border-b border-gray-100">Titel</th>
                                <th class="px-6 py-4 border-b border-gray-100">Status</th>
                                <th class="px-6 py-4 border-b border-gray-100">Sichtbar ab</th>
                                <th class="px-6 py-4 border-b border-gray-100">Kategorie / Autor</th>
                                <th class="px-6 py-4 border-b border-gray-100 text-right">Aktionen</th>
                            </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 block md:table-row-group">
                            @forelse($posts as $post)
                                {{-- Row: Auf Mobile ein Block (Karte), auf Desktop eine Tabellenzeile --}}
                                <tr class="hover:bg-gray-50/50 transition group block md:table-row bg-white relative">

                                    {{-- 1. TITEL --}}
                                    <td class="px-4 py-3 md:px-6 md:py-4 block md:table-cell">
                                        {{-- Mobile Label nicht nötig, Titel steht für sich --}}
                                        <div class="flex items-start justify-between md:block">
                                            <div>
                                                <div class="font-bold text-gray-900 text-base md:text-sm">{{ $post->title }}</div>
                                                <div class="text-xs text-gray-400 mt-0.5 font-mono break-all md:break-normal">/{{ $post->slug }}</div>
                                                @if($post->is_advertisement)
                                                    <span class="inline-flex mt-1 items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                            Anzeige
                                        </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 2. STATUS --}}
                                    <td class="px-4 py-2 md:px-6 md:py-4 block md:table-cell">
                                        <div class="flex items-center justify-between md:block">
                                            <span class="md:hidden text-xs font-bold text-gray-400 uppercase tracking-wider">Status</span>
                                            @php
                                                $badges = [
                                                    'published' => 'bg-green-100 text-green-800 border-green-200',
                                                    'draft' => 'bg-gray-100 text-gray-800 border-gray-200',
                                                    'scheduled' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                ];
                                                $labels = [
                                                    'published' => 'Veröffentlicht',
                                                    'draft' => 'Entwurf',
                                                    'scheduled' => 'Geplant',
                                                ];
                                            @endphp
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badges[$post->status] }}">
                                    {{ $labels[$post->status] }}
                                </span>
                                        </div>
                                    </td>

                                    {{-- 3. DATUM --}}
                                    <td class="px-4 py-2 md:px-6 md:py-4 block md:table-cell text-sm text-gray-600">
                                        <div class="flex items-center justify-between md:block">
                                            <span class="md:hidden text-xs font-bold text-gray-400 uppercase tracking-wider">Datum</span>
                                            <span>{{ $post->published_at ? $post->published_at->format('d.m.Y H:i') : '-' }}</span>
                                        </div>
                                    </td>

                                    {{-- 4. KATEGORIE / AUTOR --}}
                                    <td class="px-4 py-2 md:px-6 md:py-4 block md:table-cell text-sm text-gray-600">
                                        <div class="flex items-center justify-between md:block">
                                            <span class="md:hidden text-xs font-bold text-gray-400 uppercase tracking-wider">Details</span>
                                            <div class="text-right md:text-left">
                                                <div class="font-medium">{{ $post->category->name ?? '-' }}</div>
                                                <div class="text-xs text-gray-400">{{ $post->author->name ?? 'Unbekannt' }}</div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- 5. AKTIONEN --}}
                                    <td class="px-4 py-4 md:px-6 md:py-4 block md:table-cell md:text-right border-t md:border-0 border-gray-50 mt-2 md:mt-0">
                                        <div class="flex justify-end gap-3 md:gap-4 w-full">
                                            <button wire:click="edit('{{ $post->id }}')"
                                                    class="text-primary hover:text-primary-dark font-bold text-xs uppercase tracking-wider bg-primary/5 hover:bg-primary/10 px-3 py-2 rounded-lg md:bg-transparent md:p-0 transition">
                                                Bearbeiten
                                            </button>
                                            <button wire:click="delete('{{ $post->id }}')"
                                                    wire:confirm="Möchtest du den Beitrag '{{ $post->title }}' wirklich löschen?"
                                                    class="text-gray-400 hover:text-red-600 font-bold text-xs uppercase tracking-wider bg-gray-100 hover:bg-red-50 px-3 py-2 rounded-lg md:bg-transparent md:p-0 transition">
                                                Löschen
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic block md:table-cell">
                                        Keine Beiträge gefunden.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Footer --}}
                    <div class="p-4 border-t border-gray-100 bg-gray-50">
                        {{ $posts->links() }}
                    </div>
                </div>
            </div>

            {{-- ========================================== --}}
            {{-- CREATE / EDIT VIEW --}}
            {{-- ========================================== --}}
        @else
            <div class="max-w-6xl mx-auto animate-fade-in-up">

                {{-- Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-serif font-bold text-gray-900">
                            {{ $viewMode === 'create' ? 'Neuen Beitrag erstellen' : 'Beitrag bearbeiten' }}
                        </h1>
                        <p class="text-sm text-gray-500">
                            {{ $viewMode === 'create' ? 'Fülle die Felder aus, um einen neuen inspirierenden Beitrag zu erstellen.' : 'Bearbeite Inhalte, SEO und Einstellungen.' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button wire:click="cancel" class="px-4 py-2 text-gray-600 font-bold hover:bg-gray-100 rounded-lg transition text-sm">Abbrechen</button>
                        <button wire:click="save" class="bg-primary text-white px-6 py-2.5 rounded-lg shadow-lg shadow-primary/30 hover:bg-primary-dark transition flex items-center gap-2 font-bold text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            {{ $viewMode === 'create' ? 'Veröffentlichen' : 'Speichern' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    {{-- MAIN CONTENT --}}
                    <div class="lg:col-span-2 space-y-8">

                        {{-- Karte: Inhalt --}}
                        <div class="bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-sm">
                            <div class="mb-6">
                                <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Titel des Beitrags <span class="text-red-500">*</span></label>
                                <input wire:model.live.debounce.500ms="title" type="text" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-lg font-serif font-bold text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all duration-200" placeholder="z.B. Die Magie handgefertigter Unikate">
                                @error('title') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-6">
                                <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Permalink (Slug) <span class="text-red-500">*</span></label>
                                <div class="flex rounded-lg shadow-sm">
                                    <span class="inline-flex items-center px-4 rounded-l-lg border border-r-0 border-gray-200 bg-gray-100 text-gray-500 text-sm">.../magazin/</span>
                                    <input wire:model="slug" type="text" class="flex-1 min-w-0 block w-full px-4 py-3 rounded-none rounded-r-lg border border-gray-200 bg-gray-50 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all duration-200">
                                </div>
                                @error('slug') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            {{-- CKEDITOR WRAPPER --}}
                            <div class="mb-6" wire:ignore>
                                <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Inhalt <span class="text-red-500">*</span></label>

                                {{-- 1. CSS Hack: Erzwingt das Ausblenden der Warnmeldung --}}
                                <style>
                                    .cke_notification_warning { display: none !important; }
                                    .cke_notifications_area { display: none !important; }
                                </style>

                                {{-- Container für die Logik --}}
                                <div x-data="{
                                    init() {
                                        // Aufräumen
                                        if (CKEDITOR.instances['blogEditor']) {
                                            CKEDITOR.instances['blogEditor'].destroy(true);
                                        }

                                        // Initialisieren
                                        const editor = CKEDITOR.replace('blogEditor', {
                                            height: 400,
                                            versionCheck: false, // <--- 2. JS Config: Schaltet den Versions-Check ab
                                            resize_enabled: false, // Optional: Verhindert, dass das Layout zerschossen wird
                                            removePlugins: 'elementspath', // Macht den Editor unten sauberer
                                            // Einfache Toolbar Konfiguration für Blog (optional)
                                            toolbarGroups: [
                                                { name: 'styles', groups: [ 'styles' ] },
                                                { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                                                { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align' ] },
                                                { name: 'links', groups: [ 'links' ] },
                                                { name: 'insert', groups: [ 'insert' ] },
                                                '/',
                                                { name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
                                                { name: 'tools', groups: [ 'tools' ] }
                                            ]
                                        });

                                        // Daten-Sync
                                        editor.on('change', function() {
                                            @this.set('content', editor.getData());
                                        });

                                        // Initialen Wert setzen
                                        editor.setData(@this.get('content'));
                                    }
                                }" x-init="init()">
                                    <textarea id="blogEditor" class="w-full border border-gray-200 rounded-lg"></textarea>
                                </div>
                            </div>
                            @error('content') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                            <div>
                                <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Kurzbeschreibung (Excerpt)</label>
                                <textarea wire:model="excerpt" rows="3" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all duration-200" placeholder="Eine kurze Zusammenfassung für die Übersicht..."></textarea>
                                <p class="text-right text-xs text-gray-400 mt-1">Wird in der Blog-Übersicht und in Suchergebnissen angezeigt.</p>
                            </div>
                        </div>

                        {{-- Karte: SEO --}}
                        <div class="bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-sm">
                            <h3 class="font-serif font-bold text-lg text-gray-900 border-b border-gray-100 pb-3 mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Suchmaschinenoptimierung (SEO)
                            </h3>
                            <div class="space-y-6">
                                <div>
                                    <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Meta Titel</label>
                                    <input wire:model="meta_title" type="text" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all" placeholder="{{ $title ?: 'Titel des Beitrags' }}">
                                    <p class="text-xs text-gray-400 mt-1">Standard: Titel des Beitrags. Max. 60 Zeichen empfohlen.</p>
                                </div>
                                <div>
                                    <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Meta Beschreibung</label>
                                    <textarea wire:model="meta_description" rows="2" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all" placeholder="Beschreibe den Inhalt für Suchmaschinen..."></textarea>
                                    <p class="text-xs text-gray-400 mt-1">Max. 160 Zeichen empfohlen.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SIDEBAR --}}
                    <div class="space-y-6">

                        {{-- STATUS & KATEGORIE --}}
                        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <h3 class="font-serif font-bold text-lg text-gray-900 border-b border-gray-100 pb-3 mb-4">Einstellungen</h3>

                            <div class="mb-5">
                                <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Status</label>
                                <div class="relative">
                                    <select wire:model.live="status" class="w-full appearance-none border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all cursor-pointer">
                                        <option value="draft">Draft (Entwurf)</option>
                                        <option value="published">Published (Online)</option>
                                        <option value="scheduled">Scheduled (Geplant)</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-5">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide">Kategorie</label>
                                    {{-- TRIGGER FÜR MODAL --}}
                                    <button wire:click="openCategoryManager" class="text-xs font-bold text-primary hover:text-primary-dark underline cursor-pointer">
                                        Verwalten
                                    </button>
                                </div>
                                <div class="relative">
                                    <select wire:model="blog_category_id" class="w-full appearance-none border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all cursor-pointer">
                                        <option value="">Keine Kategorie</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                                @error('blog_category_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            @if($status === 'scheduled' || $status === 'published')
                                <div>
                                    <label class="block font-bold text-gray-700 text-xs uppercase tracking-wide mb-2">Veröffentlichungsdatum</label>
                                    <input wire:model="published_at" type="datetime-local" class="w-full border border-gray-200 bg-gray-50 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white transition-all">
                                </div>
                            @endif
                        </div>

                        {{-- BILD --}}
                        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <h3 class="font-serif font-bold text-lg text-gray-900 border-b border-gray-100 pb-3 mb-4">Beitragsbild</h3>
                            <div class="mb-4">
                                @if ($image)
                                    <div class="relative group">
                                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-48 object-cover rounded-lg shadow-sm">
                                        <div class="absolute inset-0 bg-black/20 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-xs font-bold bg-black/50 px-2 py-1 rounded">Vorschau</span>
                                        </div>
                                    </div>
                                @elseif($existingImage)
                                    <div class="relative group">
                                        <img src="{{ asset('storage/'.$existingImage) }}" class="w-full h-48 object-cover rounded-lg shadow-sm">
                                        <div class="absolute inset-0 bg-black/20 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-xs font-bold bg-black/50 px-2 py-1 rounded">Aktuell</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="w-full h-48 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200 flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-xs">Kein Bild ausgewählt</span>
                                    </div>
                                @endif
                            </div>
                            <label class="block w-full">
                                <span class="sr-only">Bild wählen</span>
                                <input type="file" wire:model="image" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer"/>
                            </label>
                            @error('image') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- RECHTLICHES --}}
                        <div class="bg-amber-50 p-6 rounded-xl border border-amber-200 shadow-sm">
                            <h3 class="font-serif font-bold text-amber-900 border-b border-amber-200/60 pb-3 mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Rechtliches (UWG)
                            </h3>
                            <div class="space-y-4">
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <div class="relative flex items-center h-5">
                                        <input type="checkbox" wire:model="is_advertisement" class="w-4 h-4 text-primary border-amber-300 rounded focus:ring-primary focus:ring-offset-amber-50">
                                    </div>
                                    <div class="text-sm">
                                        <span class="font-bold text-amber-900 group-hover:text-amber-700 transition">Werbung / Anzeige</span>
                                        <p class="text-xs text-amber-800/70 mt-0.5">Beitrag enthält bezahlte Inhalte oder Kooperationen.</p>
                                    </div>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <div class="relative flex items-center h-5">
                                        <input type="checkbox" wire:model="contains_affiliate_links" class="w-4 h-4 text-primary border-amber-300 rounded focus:ring-primary focus:ring-offset-amber-50">
                                    </div>
                                    <div class="text-sm">
                                        <span class="font-bold text-amber-900 group-hover:text-amber-700 transition">Affiliate Links</span>
                                        <p class="text-xs text-amber-800/70 mt-0.5">Beitrag enthält Provisions-Links (DSGVO-Hinweis aktiv).</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL: KATEGORIE MANAGEMENT --}}
        @if($showCategoryModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm animate-fade-in">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all animate-fade-in-up border border-gray-100">
                    <div class="bg-primary/5 px-6 py-4 border-b border-primary/10 flex justify-between items-center">
                        <h3 class="text-lg font-serif font-bold text-gray-900">Kategorien verwalten</h3>
                        <button wire:click="closeCategoryManager" class="text-gray-400 hover:text-gray-600 transition">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6">
                        {{-- Create Form --}}
                        <div class="flex gap-2 mb-6">
                            <input type="text" wire:model="newCategoryName" placeholder="Neue Kategorie..."
                                   class="flex-1 border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white"
                                   wire:keydown.enter="createCategory">
                            <button wire:click="createCategory" class="bg-primary text-white px-4 py-2 rounded-lg font-bold text-sm hover:bg-primary-dark transition shadow-sm">
                                Hinzufügen
                            </button>
                        </div>
                        @error('newCategoryName') <span class="text-red-500 text-xs block mb-4 -mt-4">{{ $message }}</span> @enderror

                        {{-- List --}}
                        <div class="space-y-2 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                            @foreach($categories as $cat)
                                <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg border border-gray-100 group hover:border-gray-300 transition">
                                    <span class="text-sm font-medium text-gray-700">{{ $cat->name }}</span>
                                    <button wire:click="deleteCategory('{{ $cat->id }}')"
                                            wire:confirm="Kategorie '{{ $cat->name }}' wirklich löschen?"
                                            class="text-gray-400 hover:text-red-500 transition p-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            @endforeach
                            @if($categories->isEmpty())
                                <p class="text-center text-xs text-gray-400 py-4">Noch keine Kategorien erstellt.</p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-right">
                        <button wire:click="closeCategoryManager" class="text-sm font-bold text-gray-500 hover:text-gray-700">Schließen</button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
