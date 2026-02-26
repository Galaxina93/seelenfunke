<div class="animate-fade-in-up font-sans antialiased text-gray-300 min-h-screen">
    <div class="pb-20">

        {{-- ========================================== --}}
        {{-- LIST VIEW --}}
        {{-- ========================================== --}}
        @if($viewMode === 'list')
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 mb-8">
                    <div>
                        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-wide">Magazin & Blog</h1>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-2">Inhalte verwalten, inspirieren und informieren.</p>
                    </div>
                    <button wire:click="create" class="w-full sm:w-auto bg-primary border border-primary/50 text-gray-900 px-6 py-3.5 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark hover:scale-[1.02] transition-all flex items-center justify-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                        Neuer Beitrag
                    </button>
                </div>

                {{-- Flash Messages --}}
                @if(session()->has('success'))
                    <div class="bg-emerald-500/10 border-l-4 border-emerald-500 p-5 mb-8 text-emerald-400 shadow-inner rounded-r-2xl flex justify-between items-center animate-fade-in text-[10px] font-black uppercase tracking-widest">
                        <span class="drop-shadow-[0_0_8px_currentColor]">{{ session('success') }}</span>
                        <svg class="w-5 h-5 text-emerald-500 drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                @endif

                {{-- Search & Table --}}
                <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
                    {{-- Search Header --}}
                    <div class="p-6 sm:p-8 border-b border-gray-800 flex gap-4 bg-gray-950/50 shadow-inner">
                        <div class="relative flex-1 group">
                            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Beitrag suchen..."
                                   class="w-full pl-12 pr-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-gray-950 focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-600">
                            <svg class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Responsive Table Wrapper --}}
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            {{-- Kopfzeile --}}
                            <thead class="bg-gray-950/80 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800 shadow-inner">
                            <tr>
                                <th class="px-6 sm:px-8 py-5">Titel</th>
                                <th class="px-6 sm:px-8 py-5">Status</th>
                                <th class="px-6 sm:px-8 py-5">Sichtbar ab</th>
                                <th class="px-6 sm:px-8 py-5">Kategorie / Autor</th>
                                <th class="px-6 sm:px-8 py-5 text-right">Aktionen</th>
                            </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-800/50">
                            @forelse($posts as $post)
                                <tr class="hover:bg-gray-800/30 transition-colors group">

                                    {{-- 1. TITEL --}}
                                    <td class="px-6 sm:px-8 py-6 align-top">
                                        <div>
                                            <div class="font-bold text-white text-base tracking-wide">{{ $post->title }}</div>
                                            <div class="text-[10px] text-gray-500 mt-1.5 font-mono truncate max-w-[250px] bg-gray-950 px-2 py-1 rounded-md border border-gray-800 inline-block shadow-inner">/{{ $post->slug }}</div>
                                            @if($post->is_advertisement)
                                                <span class="inline-flex mt-2 items-center px-3 py-1 rounded-md text-[8px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30 shadow-inner drop-shadow-[0_0_8px_currentColor] ml-2">
                                                    Anzeige
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- 2. STATUS --}}
                                    <td class="px-6 sm:px-8 py-6 align-top">
                                        @php
                                            $badges = [
                                                'published' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 shadow-[0_0_8px_rgba(16,185,129,0.4)]',
                                                'draft' => 'bg-gray-800/50 text-gray-400 border-gray-700 shadow-inner',
                                                'scheduled' => 'bg-blue-500/10 text-blue-400 border-blue-500/30 shadow-[0_0_8px_rgba(59,130,246,0.4)]',
                                            ];
                                            $labels = [
                                                'published' => 'Veröffentlicht',
                                                'draft' => 'Entwurf',
                                                'scheduled' => 'Geplant',
                                            ];
                                        @endphp
                                        <span class="inline-block px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border {{ $badges[$post->status] }}">
                                            {{ $labels[$post->status] }}
                                        </span>
                                    </td>

                                    {{-- 3. DATUM --}}
                                    <td class="px-6 sm:px-8 py-6 align-top text-sm text-gray-400 font-medium">
                                        <span>{{ $post->published_at ? $post->published_at->format('d.m.Y H:i') : '-' }}</span>
                                    </td>

                                    {{-- 4. KATEGORIE / AUTOR --}}
                                    <td class="px-6 sm:px-8 py-6 align-top">
                                        <div>
                                            <div class="font-bold text-gray-300 text-sm tracking-wide">{{ $post->category->name ?? '-' }}</div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-primary mt-1.5 drop-shadow-[0_0_5px_currentColor]">{{ $post->author->name ?? 'Unbekannt' }}</div>
                                        </div>
                                    </td>

                                    {{-- 5. AKTIONEN --}}
                                    <td class="px-6 sm:px-8 py-6 align-top text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="edit('{{ $post->id }}')"
                                                    class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:bg-primary/10 hover:border-primary/30 hover:text-primary rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button wire:click="delete('{{ $post->id }}')"
                                                    wire:confirm="Möchtest du den Beitrag '{{ $post->title }}' wirklich löschen?"
                                                    class="p-2.5 bg-gray-950 border border-gray-800 text-gray-500 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-400 rounded-xl transition-all shadow-inner" title="Löschen">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <div class="w-16 h-16 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center mb-4 shadow-inner">
                                                <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                                            </div>
                                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Keine Beiträge gefunden.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Footer --}}
                    @if($posts->hasPages())
                        <div class="p-6 border-t border-gray-800 bg-gray-950/30 shadow-inner">
                            {{ $posts->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- ========================================== --}}
            {{-- CREATE / EDIT VIEW --}}
            {{-- ========================================== --}}
        @else
            <div class="max-w-6xl mx-auto animate-fade-in-up">

                {{-- Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
                    <div>
                        <h1 class="text-3xl font-serif font-bold text-white tracking-wide">
                            {{ $viewMode === 'create' ? 'Neuen Beitrag erstellen' : 'Beitrag bearbeiten' }}
                        </h1>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-2">
                            {{ $viewMode === 'create' ? 'Fülle die Felder aus, um einen neuen Beitrag zu erstellen.' : 'Bearbeite Inhalte, SEO und Einstellungen.' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <button wire:click="cancel" class="flex-1 md:flex-none px-5 py-3.5 bg-gray-950 border border-gray-800 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors shadow-inner">Abbrechen</button>
                        <button wire:click="save" class="flex-1 md:flex-none bg-primary border border-primary/50 text-gray-900 px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark hover:text-white hover:scale-[1.02] transition-all flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            {{ $viewMode === 'create' ? 'Veröffentlichen' : 'Speichern' }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

                    {{-- MAIN CONTENT --}}
                    <div class="xl:col-span-2 space-y-8">

                        {{-- Karte: Inhalt --}}
                        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] border border-gray-800 shadow-2xl">

                            @php
                                $inputClass = "w-full border border-gray-800 bg-gray-950 text-white rounded-xl px-4 py-3.5 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-black transition-all shadow-inner outline-none placeholder-gray-600";
                                $labelClass = "block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2 ml-1";
                            @endphp

                            <div class="mb-8">
                                <label class="{{ $labelClass }}">Titel des Beitrags <span class="text-primary drop-shadow-[0_0_5px_currentColor]">*</span></label>
                                <input wire:model.live.debounce.500ms="title" type="text" class="{{ $inputClass }} !text-lg !font-serif !font-bold" placeholder="z.B. Die Magie handgefertigter Unikate">
                                @error('title') <span class="text-[10px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-8">
                                <label class="{{ $labelClass }}">Permalink (Slug) <span class="text-primary drop-shadow-[0_0_5px_currentColor]">*</span></label>
                                <div class="flex rounded-xl shadow-inner border border-gray-800 overflow-hidden">
                                    <span class="inline-flex items-center px-4 bg-gray-900 text-gray-500 text-[10px] font-black uppercase tracking-widest border-r border-gray-800">.../magazin/</span>
                                    <input wire:model="slug" type="text" class="flex-1 min-w-0 block w-full px-4 py-3.5 bg-gray-950 text-white text-sm focus:bg-black outline-none font-mono transition-colors">
                                </div>
                                @error('slug') <span class="text-[10px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror
                            </div>

                            {{-- CKEDITOR WRAPPER --}}
                            <div class="mb-8" wire:ignore>
                                <label class="{{ $labelClass }}">Inhalt <span class="text-primary drop-shadow-[0_0_5px_currentColor]">*</span></label>

                                {{-- 1. CSS FÜR DAS ÄUSSERE GEHÄUSE (TOOLBAR & RAHMEN) --}}
                                <style>
                                    .cke_notification_warning { display: none !important; }
                                    .cke_notifications_area { display: none !important; }

                                    /* Rahmen und Basis */
                                    .cke_chrome { border: 1px solid #1f2937 !important; border-radius: 1rem !important; overflow: hidden; box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.5) !important; background: #030712 !important; transition: border-color 0.3s ease; }
                                    .cke_chrome:hover { border-color: #374151 !important; }

                                    /* Toolbar Hintergrund */
                                    .cke_top { background: #111827 !important; border-bottom: 1px solid #1f2937 !important; padding: 12px 10px !important; }
                                    .cke_bottom { background: #111827 !important; border-top: 1px solid #1f2937 !important; }

                                    /* Button-Gruppen & Buttons */
                                    .cke_toolgroup { background: #1f2937 !important; border: 1px solid #374151 !important; box-shadow: inset 0 1px 3px rgba(0,0,0,0.3) !important; border-radius: 8px !important; margin-right: 8px !important; }
                                    .cke_button { transition: all 0.2s; padding: 4px 6px !important; }
                                    .cke_button:hover { background: #374151 !important; border-radius: 6px !important; }

                                    /* Icons invertieren (hell machen) und zum Leuchten bringen */
                                    .cke_button_icon { filter: invert(0.8) hue-rotate(180deg) brightness(1.5) !important; opacity: 0.8; transition: opacity 0.2s; }
                                    .cke_button:hover .cke_button_icon { opacity: 1; filter: invert(1) brightness(2) drop-shadow(0 0 2px rgba(255,255,255,0.5)) !important; }

                                    /* Dropdowns (z.B. Format, Schriftart) */
                                    .cke_combo_text { color: #d1d5db !important; text-shadow: none !important; }
                                    .cke_combo_button { background: #1f2937 !important; border: 1px solid #374151 !important; border-radius: 6px !important; margin-[2px_4px] !important; }
                                    .cke_combo_open { border-left: 1px solid #374151 !important; }
                                    .cke_combo_arrow { filter: invert(1) !important; }

                                    /* iFrame Hintergrund-Wrapper */
                                    .cke_contents { background: #030712 !important; }
                                </style>

                                <div x-data="{
                                                init() {
                                                    if (CKEDITOR.instances['blogEditor']) {
                                                        CKEDITOR.instances['blogEditor'].destroy(true);
                                                    }

                                                    // 2. CSS FÜR DAS INNERE DES iFRAMES (Texteingabefeld)
                                                    CKEDITOR.addCss(`
                                                        body.cke_editable {
                                                            background-color: #030712 !important; /* bg-gray-950 */
                                                            color: #d1d5db !important; /* text-gray-300 */
                                                            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif !important;
                                                            font-size: 14px !important;
                                                            line-height: 1.8 !important;
                                                            padding: 2rem !important;
                                                            margin: 0 !important;
                                                        }
                                                        /* Schicke Text-Auswahlfarbe in Gold */
                                                        ::selection { background: rgba(197, 160, 89, 0.3) !important; color: #ffffff !important; }

                                                        /* Grundlegende Typografie im Darkmode */
                                                        p { margin-bottom: 1.2em !important; }
                                                        a { color: #C5A059 !important; text-decoration: none !important; transition: all 0.2s; }
                                                        a:hover { text-decoration: underline !important; color: #d4af37 !important; }

                                                        /* Überschriften */
                                                        h1, h2, h3, h4, h5, h6 {
                                                            color: #ffffff !important;
                                                            font-family: ui-serif, Georgia, serif !important;
                                                            font-weight: bold !important;
                                                            margin-top: 1.5em !important;
                                                            margin-bottom: 0.5em !important;
                                                            letter-spacing: 0.025em !important;
                                                        }

                                                        /* Listen & Zitate */
                                                        ul, ol { margin-bottom: 1.2em !important; padding-left: 1.5em !important; }
                                                        li { margin-bottom: 0.5em !important; }
                                                        blockquote {
                                                            border-left: 3px solid #C5A059 !important;
                                                            padding-left: 1.5em !important;
                                                            color: #9ca3af !important;
                                                            font-style: italic !important;
                                                            background: rgba(197, 160, 89, 0.05) !important;
                                                            padding-top: 0.5em !important;
                                                            padding-bottom: 0.5em !important;
                                                            border-radius: 0 8px 8px 0 !important;
                                                        }
                                                    `);

                                                    const editor = CKEDITOR.replace('blogEditor', {
                                                        height: 500,
                                                        versionCheck: false,
                                                        resize_enabled: false,
                                                        removePlugins: 'elementspath',
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

                                                    editor.on('change', function() {
                                                        @this.set('content', editor.getData());
                                                    });

                                                    editor.setData(@this.get('content'));
                                                }
                                            }" x-init="init()">
                                    <textarea id="blogEditor" class="w-full"></textarea>
                                </div>
                            </div>
                            @error('content') <span class="text-[10px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror

                            <div>
                                <label class="{{ $labelClass }}">Kurzbeschreibung (Excerpt)</label>
                                <textarea wire:model="excerpt" rows="4" class="{{ $inputClass }} resize-none" placeholder="Eine kurze Zusammenfassung für die Übersicht..."></textarea>
                                <p class="text-right text-[9px] font-bold uppercase tracking-widest text-gray-600 mt-2">Wird in der Blog-Übersicht und in Suchergebnissen angezeigt.</p>
                            </div>
                        </div>

                        {{-- Karte: SEO --}}
                        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-10 rounded-[2.5rem] border border-gray-800 shadow-2xl">
                            <h3 class="font-serif font-bold text-xl text-white border-b border-gray-800 pb-4 mb-8 flex items-center gap-3 tracking-wide">
                                <div class="p-2 bg-gray-950 border border-gray-800 rounded-xl shadow-inner shrink-0 text-gray-500">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                Suchmaschinenoptimierung (SEO)
                            </h3>
                            <div class="space-y-8">
                                <div>
                                    <label class="{{ $labelClass }}">Meta Titel</label>
                                    <input wire:model="meta_title" type="text" class="{{ $inputClass }}" placeholder="{{ $title ?: 'Titel des Beitrags' }}">
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-600 mt-2 ml-1">Standard: Titel des Beitrags. Max. 60 Zeichen empfohlen.</p>
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Meta Beschreibung</label>
                                    <textarea wire:model="meta_description" rows="3" class="{{ $inputClass }} resize-none" placeholder="Beschreibe den Inhalt für Suchmaschinen..."></textarea>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-600 mt-2 ml-1">Max. 160 Zeichen empfohlen.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SIDEBAR --}}
                    <div class="space-y-8">

                        {{-- STATUS & KATEGORIE --}}
                        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl">
                            <h3 class="font-serif font-bold text-xl text-white border-b border-gray-800 pb-4 mb-6 tracking-wide">Einstellungen</h3>

                            <div class="mb-6">
                                <label class="{{ $labelClass }}">Status</label>
                                <div class="relative group">
                                    <select wire:model.live="status" class="{{ $inputClass }} appearance-none cursor-pointer pr-10">
                                        <option value="draft" class="bg-gray-900">Draft (Entwurf)</option>
                                        <option value="published" class="bg-gray-900">Published (Online)</option>
                                        <option value="scheduled" class="bg-gray-900">Scheduled (Geplant)</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 group-focus-within:text-primary transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-2 ml-1">
                                    <label class="{{ $labelClass }} !mb-0 !ml-0">Kategorie</label>
                                    <button wire:click="openCategoryManager" class="text-[9px] font-black uppercase tracking-widest text-primary border-b border-primary/30 hover:text-white hover:border-white transition-colors pb-0.5">
                                        Verwalten
                                    </button>
                                </div>
                                <div class="relative group">
                                    <select wire:model="blog_category_id" class="{{ $inputClass }} appearance-none cursor-pointer pr-10">
                                        <option value="" class="bg-gray-900">Keine Kategorie</option>
                                        @foreach($categories as $cat)
                                            <option value="{{ $cat->id }}" class="bg-gray-900">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500 group-focus-within:text-primary transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                </div>
                                @error('blog_category_id') <span class="text-[10px] font-bold text-red-400 mt-2 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror
                            </div>

                            @if($status === 'scheduled' || $status === 'published')
                                <div class="animate-fade-in">
                                    <label class="{{ $labelClass }}">Veröffentlichungsdatum</label>
                                    <input wire:model="published_at" type="datetime-local" class="{{ $inputClass }} font-mono text-sm [color-scheme:dark]">
                                </div>
                            @endif
                        </div>

                        {{-- BILD --}}
                        <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-800 shadow-2xl">
                            <h3 class="font-serif font-bold text-xl text-white border-b border-gray-800 pb-4 mb-6 tracking-wide">Beitragsbild</h3>
                            <div class="mb-6">
                                @if ($image)
                                    <div class="relative group rounded-2xl overflow-hidden border-2 border-primary shadow-[0_0_15px_rgba(197,160,89,0.2)]">
                                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-48 object-cover">
                                        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-[10px] font-black uppercase tracking-widest bg-gray-950/80 border border-gray-700 px-3 py-1.5 rounded-lg shadow-xl">Vorschau</span>
                                        </div>
                                    </div>
                                @elseif($existingImage)
                                    <div class="relative group rounded-2xl overflow-hidden border border-gray-800 shadow-inner">
                                        <img src="{{ asset('storage/'.$existingImage) }}" class="w-full h-48 object-cover opacity-80 group-hover:opacity-100 transition-opacity">
                                        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                            <span class="text-white text-[10px] font-black uppercase tracking-widest bg-gray-950/80 border border-gray-700 px-3 py-1.5 rounded-lg shadow-xl">Aktuell</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="w-full h-48 bg-gray-950 rounded-2xl border-2 border-dashed border-gray-800 flex flex-col items-center justify-center text-gray-600 shadow-inner transition-colors hover:border-gray-600">
                                        <svg class="w-10 h-10 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="text-[10px] font-black uppercase tracking-widest">Kein Bild ausgewählt</span>
                                    </div>
                                @endif
                            </div>
                            <label class="block w-full">
                                <span class="sr-only">Bild wählen</span>
                                <input type="file" wire:model="image" class="block w-full text-[10px] font-black uppercase tracking-widest text-gray-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:bg-gray-950 file:border-gray-800 file:text-primary hover:file:bg-gray-900 hover:file:text-white cursor-pointer transition-all file:shadow-inner"/>
                            </label>
                            @error('image') <span class="text-[10px] font-bold text-red-400 mt-3 block ml-1 uppercase tracking-widest">{{ $message }}</span> @enderror
                        </div>

                        {{-- RECHTLICHES --}}
                        <div class="bg-amber-500/5 p-6 sm:p-8 rounded-[2.5rem] border border-amber-500/20 shadow-inner">
                            <h3 class="font-serif font-bold text-lg text-amber-400 border-b border-amber-500/20 pb-4 mb-6 flex items-center gap-3 drop-shadow-[0_0_8px_currentColor]">
                                <div class="p-2 rounded-xl bg-amber-500/10 shadow-inner border border-amber-500/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                </div>
                                Rechtliches (UWG)
                            </h3>
                            <div class="space-y-5">
                                <label class="flex items-start gap-4 cursor-pointer group p-3 -m-3 rounded-xl hover:bg-amber-500/10 transition-colors border border-transparent hover:border-amber-500/20">
                                    <div class="relative flex items-center h-5 mt-0.5 shrink-0">
                                        <input type="checkbox" wire:model="is_advertisement" class="peer sr-only">
                                        <div class="w-5 h-5 bg-gray-950 border-2 border-amber-500/50 rounded transition-all peer-checked:bg-amber-500 peer-checked:border-amber-400 shadow-inner"></div>
                                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div>
                                        <span class="font-bold text-white group-hover:text-amber-400 transition-colors text-sm tracking-wide">Werbung / Anzeige</span>
                                        <p class="text-[10px] text-gray-500 font-medium mt-1">Beitrag enthält bezahlte Inhalte oder Kooperationen.</p>
                                    </div>
                                </label>

                                <label class="flex items-start gap-4 cursor-pointer group p-3 -m-3 rounded-xl hover:bg-amber-500/10 transition-colors border border-transparent hover:border-amber-500/20">
                                    <div class="relative flex items-center h-5 mt-0.5 shrink-0">
                                        <input type="checkbox" wire:model="contains_affiliate_links" class="peer sr-only">
                                        <div class="w-5 h-5 bg-gray-950 border-2 border-amber-500/50 rounded transition-all peer-checked:bg-amber-500 peer-checked:border-amber-400 shadow-inner"></div>
                                        <svg class="absolute w-3.5 h-3.5 left-0.5 top-0.5 text-gray-900 opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </div>
                                    <div>
                                        <span class="font-bold text-white group-hover:text-amber-400 transition-colors text-sm tracking-wide">Affiliate Links</span>
                                        <p class="text-[10px] text-gray-500 font-medium mt-1">Beitrag enthält Provisions-Links (DSGVO-Hinweis aktiv).</p>
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
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-10 bg-black/90 backdrop-blur-md animate-fade-in">
                <div class="bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-800 w-full max-w-md overflow-hidden transform transition-all animate-fade-in-up">

                    <div class="bg-gray-950/50 px-8 py-6 border-b border-gray-800 flex justify-between items-center shadow-inner">
                        <h3 class="text-xl font-serif font-bold text-white tracking-wide">Kategorien verwalten</h3>
                        <button wire:click="closeCategoryManager" class="p-2 bg-gray-900 border border-gray-700 text-gray-500 hover:text-white hover:bg-red-500 hover:border-red-500 rounded-xl transition-all shadow-inner hover:scale-110">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-8">
                        {{-- Create Form --}}
                        <div class="flex gap-3 mb-8">
                            <input type="text" wire:model="newCategoryName" placeholder="Neue Kategorie..."
                                   class="flex-1 border border-gray-800 bg-gray-950 rounded-xl px-4 py-3 text-sm text-white focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-black transition-all shadow-inner outline-none placeholder-gray-600"
                                   wire:keydown.enter="createCategory">
                            <button wire:click="createCategory" class="bg-primary border border-primary/50 text-gray-900 px-5 py-3 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-primary-dark hover:scale-[1.05] transition-all shadow-[0_0_15px_rgba(197,160,89,0.2)]">
                                Hinzufügen
                            </button>
                        </div>
                        @error('newCategoryName') <span class="text-[10px] font-bold text-red-400 uppercase tracking-widest block mb-5 -mt-5 ml-1">{{ $message }}</span> @enderror

                        {{-- List --}}
                        <div class="space-y-3 max-h-64 overflow-y-auto pr-2 custom-scrollbar">
                            @foreach($categories as $cat)
                                <div class="flex justify-between items-center bg-gray-950 p-4 rounded-xl border border-gray-800 shadow-inner group hover:border-gray-700 transition-colors">
                                    <span class="text-sm font-bold text-gray-300 tracking-wide">{{ $cat->name }}</span>
                                    <button wire:click="deleteCategory('{{ $cat->id }}')"
                                            wire:confirm="Kategorie '{{ $cat->name }}' wirklich löschen?"
                                            class="p-2 bg-gray-900 border border-gray-800 text-gray-500 hover:text-white hover:bg-red-500/20 hover:border-red-500/50 rounded-lg transition-all shadow-inner opacity-100 sm:opacity-0 sm:group-hover:opacity-100">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            @endforeach
                            @if($categories->isEmpty())
                                <div class="p-8 text-center bg-gray-950 rounded-xl border border-gray-800 border-dashed shadow-inner">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-600">Noch keine Kategorien erstellt.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
