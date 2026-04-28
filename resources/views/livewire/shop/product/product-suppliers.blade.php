<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <!-- Top Action Bar -->
    @if(!$showModal)
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 animate-fade-in-up">
        <div>
            <h2 class="text-xl font-bold text-white tracking-wide">Übersicht deiner Lieferanten</h2>
            <p class="text-gray-400 text-sm mt-1">Hinterlege hier alle Großhändler und Kontaktadressen, um Lieferketten optimal abzubilden.</p>
        </div>
        <button wire:click="create" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color)]-hover text-gray-900 font-bold px-6 py-3 rounded-xl shadow-glow transition-all flex items-center gap-2">
            <x-heroicon-o-plus class="w-5 h-5" />
            Neuen Lieferanten anlegen
        </button>
    </div>

    <!-- Suppliers Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 animate-fade-in-up">
        @forelse($suppliers as $supplier)
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl relative group hover:border-gray-700 transition-colors">
                <!-- Actions Dropdown -->
                <div class="absolute top-4 right-4 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button wire:click="edit('{{ $supplier->id }}')" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-blue-500/20 text-gray-400 hover:text-blue-400 flex items-center justify-center transition-colors">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                    </button>
                    <button wire:click="delete('{{ $supplier->id }}')" wire:confirm="Lieferant {{ $supplier->name }} wirklich löschen?" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-red-500/20 text-gray-400 hover:text-red-400 flex items-center justify-center transition-colors">
                        <x-heroicon-o-trash class="w-4 h-4" />
                    </button>
                </div>

                <!-- Header -->
                <div class="flex items-start gap-4 mb-5">
                    <div class="w-12 h-12 bg-gray-950 rounded-xl border border-gray-800 flex items-center justify-center shrink-0 shadow-inner">
                        <x-heroicon-o-building-office-2 class="w-6 h-6 text-[var(--theme-color)] opacity-80" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white leading-tight break-all pr-12">{{ $supplier->name }}</h3>
                        <p class="text-sm text-gray-400 mt-1 flex items-center gap-1">
                            <x-heroicon-o-user class="w-4 h-4" /> {{ $supplier->contact_person ?: 'Kein Ansprechpartner' }}
                        </p>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/50">
                        <span class="block text-[10px] uppercase font-black tracking-widest text-gray-500 mb-1">Lieferzeit (Lead Time)</span>
                        <div class="text-sm font-bold text-gray-300 flex items-center gap-2">
                            @php
                                $activeDays = match($supplier->shipping_method) {
                                    'air' => $supplier->lead_time_air_days,
                                    'sea' => $supplier->lead_time_sea_days,
                                    'train' => $supplier->lead_time_train_days,
                                    default => $supplier->lead_time_land_days,
                                };
                            @endphp
                            @if($supplier->shipping_method === 'air')
                                <x-heroicon-s-paper-airplane class="w-4 h-4 text-[var(--theme-color)] -rotate-45" title="Luftfracht" />
                            @elseif($supplier->shipping_method === 'sea')
                                <x-heroicon-s-globe-europe-africa class="w-4 h-4 text-[var(--theme-color)]" title="Seefracht" />
                            @elseif($supplier->shipping_method === 'train')
                                <x-heroicon-s-ticket class="w-4 h-4 text-[var(--theme-color)]" title="Zug / Bahn" />
                            @else
                                <x-heroicon-s-truck class="w-4 h-4 text-[var(--theme-color)]" title="Landweg" />
                            @endif
                            {{ $activeDays ? $activeDays . ' Tage' : 'Unbekannt' }}
                        </div>
                    </div>
                    <div class="bg-gray-950/50 p-3 rounded-xl border border-gray-800/50 overflow-hidden">
                        <span class="block text-[10px] uppercase font-black tracking-widest text-gray-500 mb-1">Kontakt</span>
                        <div class="text-sm font-bold text-gray-300 truncate" title="{{ $supplier->email }}">
                            {{ $supplier->email ?: ($supplier->phone ?: 'Unbekannt') }}
                        </div>
                    </div>
                </div>

                <!-- Notizen -->
                @if($supplier->notes)
                    <div class="text-sm text-gray-400 mb-6 bg-gray-800/20 p-4 rounded-xl border border-gray-800 italic">
                        "{{ Str::limit($supplier->notes, 80) }}"
                    </div>
                @endif

                <!-- Dynamische Links -->
                <div class="space-y-2">
                    @if($supplier->website)
                        <a href="{{ $supplier->website }}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-gray-950 border border-gray-800 hover:border-[var(--theme-color-50)] group/link transition-colors">
                            <span class="flex items-center gap-3 text-sm font-bold text-gray-300 group-hover/link:text-[var(--theme-color)] transition-colors">
                                <x-heroicon-o-globe-alt class="w-5 h-5 text-gray-500 group-hover/link:text-[var(--theme-color)] transition-colors" />
                                Hauptwebseite
                            </span>
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-600 group-hover/link:text-[var(--theme-color)] transition-colors" />
                        </a>
                    @endif

                    @if(is_array($supplier->dynamic_links) && count($supplier->dynamic_links) > 0)
                        @foreach($supplier->dynamic_links as $link)
                            @if(!empty($link['url']) && !empty($link['title']))
                                <a href="{{ $link['url'] }}" target="_blank" class="flex items-center justify-between p-3 rounded-xl bg-gray-950 border border-gray-800 hover:border-[var(--theme-color-50)] group/link transition-colors">
                                    <span class="flex items-center gap-3 text-sm font-bold text-gray-300 group-hover/link:text-[var(--theme-color)] transition-colors">
                                        <x-heroicon-o-link class="w-5 h-5 text-gray-500 group-hover/link:text-[var(--theme-color)] transition-colors" />
                                        {{ $link['title'] }}
                                    </span>
                                    <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-600 group-hover/link:text-[var(--theme-color)] transition-colors" />
                                </a>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-1 md:col-span-2 xl:col-span-3">
                <div class="flex flex-col flex-1 items-center justify-center p-12 text-center h-64 border-2 border-dashed border-gray-800 rounded-[2rem] bg-gray-900/30">
                    <div class="w-20 h-20 bg-gray-900 rounded-full flex items-center justify-center mb-6 shadow-inner border border-gray-800">
                        <x-heroicon-o-truck class="w-10 h-10 text-gray-600" />
                    </div>
                    <h4 class="text-lg font-bold text-white mb-2">Noch keine Lieferanten angelegt</h4>
                    <p class="text-sm text-gray-400 max-w-sm mb-6">Erfasse hier deine Großhändler, Produzenten und Marktplätze inkl. aller Kontaktwege wie WhatsApp, WeChat oder Dashboards.</p>
                    <button wire:click="create" class="bg-gray-800 hover:bg-gray-700 text-white font-bold px-6 py-2.5 rounded-xl border border-gray-700 transition-colors text-sm">
                        Ersten Händler anlegen
                    </button>
                </div>
            </div>
        @endforelse
    </div>
    @endif

    <!-- INLINE FORMULAR -->
    @if($showModal)
        <div class="bg-gray-900/80 backdrop-blur-xl rounded-3xl shadow-2xl border border-gray-700 w-full transform transition-all p-6 sm:p-10 animate-fade-in-up">

            <div class="flex items-center justify-between border-b border-gray-800 pb-6 mb-8">
                <h3 class="text-2xl font-serif font-bold text-white tracking-wide">
                    {{ $isEditing ? 'Lieferant bearbeiten' : 'Neuen Lieferanten anlegen' }}
                </h3>
                <button wire:click="resetForm" class="text-gray-500 hover:text-white transition-colors bg-gray-800 w-10 h-10 flex items-center justify-center rounded-xl shadow-inner border border-gray-700 hover:bg-gray-700">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <form wire:submit.prevent="save" class="space-y-10">
                <!-- Sektion 1: Stammdaten -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-building-storefront class="w-4 h-4" /> Stammdaten
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 px-2">
                        <div class="col-span-1 md:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wide">Firmenname / Händler *</label>
                            <input type="text" wire:model="name" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-4 text-base font-bold text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="z.B. Alibaba Merchant XYZ">
                            @error('name') <span class="text-xs text-red-500 mt-1 block font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-span-1 md:col-span-2 lg:col-span-2">
                            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wide">Ansprechpartner</label>
                            <input type="text" wire:model="contact_person" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-4 py-4 text-sm font-medium text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="z.B. Mr. Chen / Mia">
                        </div>

                        <!-- Shipping Methods und Lieferzeiten -->
                        <div class="col-span-1 md:col-span-2 lg:col-span-3 mt-4" x-data="{ method: @entangle('shipping_method') }">
                            <label class="block text-xs font-bold text-gray-400 mb-3 uppercase tracking-wide">Standard-Lieferweg & Laufzeiten</label>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Land -->
                                <div class="bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'land' ? 'border-[var(--theme-color)] bg-emerald-500/5 shadow-[0_0_20px_rgba(16,185,129,0.1)]' : 'border-gray-800 hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="land" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'land' ? 'border-[var(--theme-color)]' : 'border-gray-600 group-hover:border-gray-500'">
                                        <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'land' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-truck class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'land' ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'land' ? 'text-[var(--theme-color)]' : 'text-gray-400'">Landweg</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_land_days" min="0" class="w-full bg-gray-900 border border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-inner placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Air -->
                                <div class="bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'air' ? 'border-[var(--theme-color)] bg-[var(--theme-color)] shadow-[0_0_20px_rgba(14,165,233,0.1)]' : 'border-gray-800 hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="air" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'air' ? 'border-[var(--theme-color)]' : 'border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'air' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-paper-airplane class="w-5 h-5 -rotate-45 transition-colors shrink-0" x-bind:class="method === 'air' ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'air' ? 'text-[var(--theme-color)]' : 'text-gray-400'">Luftfracht</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_air_days" min="0" class="w-full bg-gray-900 border border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-white focus:border-sky-500 focus:ring-1 focus:ring-sky-500 shadow-inner placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Train -->
                                <div class="bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'train' ? 'border-[var(--theme-color)] bg-[var(--theme-color)] shadow-[0_0_20px_rgba(249,115,22,0.1)]' : 'border-gray-800 hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="train" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'train' ? 'border-[var(--theme-color)]' : 'border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'train' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-ticket class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'train' ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'train' ? 'text-[var(--theme-color)]' : 'text-gray-400'">Zug / Bahn</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_train_days" min="0" class="w-full bg-gray-900 border border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 shadow-inner placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>

                                <!-- Sea -->
                                <div class="bg-gray-950/50 border-2 rounded-[1.25rem] p-4 transition-all duration-300" :class="method === 'sea' ? 'border-[var(--theme-color)] bg-[var(--theme-color)] shadow-[0_0_20px_rgba(59,130,246,0.1)]' : 'border-gray-800 hover:border-gray-700'">
                                    <label class="cursor-pointer flex items-center gap-3 mb-4 group w-full">
                                        <input type="radio" wire:model="shipping_method" value="sea" class="sr-only">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors shrink-0" :class="method === 'sea' ? 'border-[var(--theme-color)]' : 'border-gray-600 group-hover:border-gray-500'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] transition-transform duration-200" :class="method === 'sea' ? 'scale-100' : 'scale-0'"></div>
                                        </div>
                                        <x-heroicon-o-globe-europe-africa class="w-5 h-5 transition-colors shrink-0" x-bind:class="method === 'sea' ? 'text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-400'" />
                                        <span class="text-xs font-black uppercase tracking-widest truncate" :class="method === 'sea' ? 'text-[var(--theme-color)]' : 'text-gray-400'">Seefracht</span>
                                    </label>
                                    <div class="relative mt-auto">
                                        <input type="number" wire:model="lead_time_sea_days" min="0" class="w-full bg-gray-900 border border-gray-800 rounded-xl px-3 py-3 text-sm font-bold text-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-inner placeholder-gray-700 transition-colors pr-10" placeholder="Tage">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-gray-600 uppercase">Tage</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sektion 2: Kontakt & System -->
                <div class="border-t border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-6 px-2 flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" /> Direkter Kontakt
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 px-2">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wide">E-Mail Adresse</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                                    <x-heroicon-o-envelope class="w-5 h-5" />
                                </span>
                                <input type="email" wire:model="email" class="w-full bg-gray-950 border border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="z.B. support@supplier.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wide">Telefon / WhatsApp / WeChat-Nr</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                                    <x-heroicon-o-chat-bubble-left-ellipsis class="w-5 h-5" />
                                </span>
                                <input type="text" wire:model="phone" class="w-full bg-gray-950 border border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="+86 ...">
                            </div>
                        </div>
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-xs font-bold text-gray-400 mb-2 uppercase tracking-wide">Haupt-Webseite / AliBaba Shop</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500">
                                    <x-heroicon-o-globe-alt class="w-5 h-5" />
                                </span>
                                <input type="url" wire:model="website" class="w-full bg-gray-950 border border-gray-700 rounded-xl pl-12 pr-4 py-4 text-sm font-medium text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="https://...">
                            </div>
                            @error('website') <span class="text-xs text-red-500 mt-2 block font-bold">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- Sektion: Dynamische Links -->
                <div class="border-t border-gray-800/50 pt-8">
                    <div class="bg-gray-950/40 p-6 sm:p-8 rounded-3xl border border-gray-800">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-2">
                                <x-heroicon-o-link class="w-4 h-4" /> Dynamische Links (Chats, Portale, Docs)
                            </h4>
                            <button type="button" wire:click="addLink" class="text-xs font-bold bg-gray-800 hover:bg-[var(--theme-color-20)] text-white hover:text-[var(--theme-color)] border border-gray-700 hover:border-[var(--theme-color-50)] rounded-lg px-4 py-2 transition-colors flex items-center gap-2">
                                <x-heroicon-o-plus class="w-4 h-4" /> Link hinzufügen
                            </button>
                        </div>

                        <div class="space-y-4">
                            @foreach($dynamic_links as $index => $link)
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-center p-4 bg-gray-900 border border-gray-700 rounded-2xl shadow-inner">
                                    <div class="w-full md:w-1/3">
                                        <label class="block text-[10px] font-black tracking-widest text-gray-500 uppercase mb-2">Titel der Verlinkung</label>
                                        <input type="text" wire:model="dynamic_links.{{ $index }}.title" placeholder="z.B. WeChat Chat" class="w-full bg-black border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)]">
                                        @error('dynamic_links.'.$index.'.title') <span class="text-xs text-red-500 block mt-1 font-bold">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="flex-1 w-full flex items-end gap-3">
                                        <div class="flex-1">
                                            <label class="block text-[10px] font-black tracking-widest text-gray-500 uppercase mb-2">URL / Hyperlink</label>
                                            <div class="flex items-center gap-2">
                                                <input type="url" wire:model.live.debounce.500ms="dynamic_links.{{ $index }}.url" placeholder="https://..." class="w-full bg-black border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)]">
                                                @if(!empty($link['url']))
                                                    <a href="{{ $link['url'] }}" target="_blank" title="Link im neuen Tab öffnen" class="w-12 h-12 shrink-0 bg-gray-800 hover:bg-[var(--theme-color-20)] border border-gray-700 hover:border-[var(--theme-color-50)] text-gray-400 hover:text-[var(--theme-color)] rounded-xl flex items-center justify-center transition-all shadow-inner">
                                                        <x-heroicon-o-arrow-top-right-on-square class="w-5 h-5" />
                                                    </a>
                                                @endif
                                            </div>
                                            @error('dynamic_links.'.$index.'.url') <span class="text-xs text-red-500 block mt-1 font-bold">{{ $message }}</span> @enderror
                                        </div>
                                        <button type="button" wire:click="removeLink({{ $index }})" title="Entfernen" class="w-12 h-12 shrink-0 bg-red-500/10 hover:bg-red-500 border border-red-500/20 hover:border-red-500 text-red-400 hover:text-white rounded-xl flex items-center justify-center transition-all shadow-inner">
                                            <x-heroicon-o-trash class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            @if(count($dynamic_links) === 0)
                                <div class="flex items-center gap-3 p-4 bg-gray-900 border border-dashed border-gray-700 rounded-2xl">
                                    <x-heroicon-o-information-circle class="w-6 h-6 text-gray-500" />
                                    <p class="text-sm text-gray-400 tracking-wide">Hier kannst du z.B. direkte WhatsApp Links, Links zu Google Drive Dokumenten oder zu B2B Plattform-Chats ablegen, für den reibungslosen Workflow.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notizen -->
                <div class="border-t border-gray-800/50 pt-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[var(--theme-color)] mb-4 px-2 flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-4 h-4" /> Zusätzliche Infos
                    </h4>
                    <div class="px-2">
                        <label class="block text-xs font-bold text-gray-400 mb-3 uppercase tracking-wide">Interne Notizen & Adresse</label>
                        <textarea wire:model="notes" rows="4" class="w-full bg-gray-950 border border-gray-700 rounded-xl px-5 py-4 text-base leading-relaxed font-medium text-white focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] shadow-inner placeholder-gray-600" placeholder="Spezielle Rabatte, Mindestabnahmemengen (MOQ), Liefersituationen..."></textarea>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex flex-col-reverse sm:flex-row items-center justify-end gap-4 pt-8 border-t border-gray-800">
                    <button type="button" wire:click="resetForm" class="w-full sm:w-auto text-gray-400 hover:text-white font-bold px-8 py-4 transition-colors">
                        Abbrechen
                    </button>
                    <button type="submit"
                        x-data="{ hasError: false }"
                        @validation-failed.window="hasError = true; setTimeout(() => hasError = false, 3000)"
                        :class="hasError ? 'bg-red-500 hover:bg-red-600 shadow-[0_0_20px_rgba(239,68,68,0.2)] text-white' : 'bg-[var(--theme-color)] hover:bg-[var(--theme-color)]-hover shadow-[0_0_20px_var(--theme-color-20)] hover:shadow-[0_0_25px_var(--theme-color-40)] text-gray-900'"
                        class="w-full sm:w-auto font-black text-sm uppercase tracking-widest px-10 py-4 rounded-xl transition-all flex items-center justify-center gap-3">
                        <span x-show="!hasError" class="flex items-center gap-2"><x-heroicon-o-check class="w-5 h-5" /> Lieferanten speichern</span>
                        <span x-show="hasError" x-cloak class="flex items-center gap-2"><x-heroicon-o-exclamation-triangle class="w-5 h-5" /> Fehlende Angaben</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
