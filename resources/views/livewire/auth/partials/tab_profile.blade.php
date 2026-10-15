<div x-show="activeProfileTab === 'profile'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div x-init="@this.on('saved', () => { showProfileSuccess = true; })" class="relative">
        <div x-show="showProfileSuccess" style="display: none;" x-transition class="absolute inset-0 z-[2005] bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl flex flex-col items-center justify-center rounded-[2.5rem] border border-emerald-500/30">
            <svg class="w-20 h-20 text-emerald-500 mb-4 drop-shadow-[0_0_20px_rgba(16,185,129,0.3)] animate-[bounce_2s_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <h3 class="text-3xl font-serif font-bold text-gray-900 dark:text-white mb-6 tracking-tight">Erfolgreich gespeichert!</h3>
            <button @click="showProfileSuccess = false" type="button" class="bg-emerald-500 hover:bg-emerald-600 text-white px-8 py-3 rounded-2xl font-black uppercase tracking-widest text-xs hover:scale-105 transition-all shadow-md">Schließen</button>
        </div>

        {{-- PROFILBILD & KOPFBEREICH --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 pb-6 border-b border-gray-200 dark:border-gray-800">
            <div>
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1 tracking-tight">Persönliche Daten</h3>
                <p class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Aktualisiere deine Stammdaten und Adressinformationen für eine reibungslose Abwicklung.</p>
                @if($guard === 'customer')
                    <div class="mt-3 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border {{$isBusiness ? 'bg-amber-500/10 border-amber-500/30 text-amber-600 dark:text-amber-400' : 'bg-blue-500/10 border-blue-500/30 text-blue-600 dark:text-blue-400'}} text-[10px] font-black uppercase tracking-widest shadow-sm">
                        @if($isBusiness)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> Gewerblicher Kunde
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Privater Kunde
                        @endif
                    </div>
                @endif
            </div>

            {{-- GROSSES PROFILBILD MIT STYLING --}}
            <div x-data="{photoName: null, photoPreview: null}" class="flex items-center gap-5 shrink-0 bg-gray-50 dark:bg-gray-950/60 p-4 rounded-2xl border border-gray-200 dark:border-gray-800/80 shadow-sm dark:shadow-inner">
                <input type="file" class="hidden" wire:model.live="photo" x-ref="photo" accept="image/png,image/jpeg,image/webp" x-on:change="
                    photoName = $refs.photo.files[0].name;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        photoPreview = e.target.result;
                    };
                    reader.readAsDataURL($refs.photo.files[0]);
                " />

                {{-- Avatar Circle (w-24 h-24 sm:w-28 sm:h-28) --}}
                <div class="relative w-24 h-24 sm:w-28 sm:h-28 rounded-full p-1 bg-gradient-to-tr from-primary/50 via-gray-300 dark:via-gray-800 to-gray-200 dark:to-gray-700 shadow-md shrink-0 cursor-pointer group" @click="$refs.photo.click()" title="Klicken, um Profilbild zu ändern">
                    <div class="relative w-full h-full rounded-full bg-white dark:bg-gray-950 overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-800">
                        <div x-show="!photoPreview" class="w-full h-full">
                            @if($user && $user->profile && $user->profile->photo_path)
                                @php
                                    $pp = $user->profile->photo_path;
                                    $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/')) 
                                           ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                                @endphp
                                <img src="{{ $src }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-900 dark:to-gray-950 flex items-center justify-center">
                                    <span class="text-primary font-serif font-bold text-3xl sm:text-4xl">{{substr($firstName ?? 'U', 0, 1)}}</span>
                                </div>
                            @endif
                        </div>
                        <div x-show="photoPreview" class="w-full h-full" style="display: none;">
                            <span class="block w-full h-full bg-cover bg-center bg-no-repeat" x-bind:style="'background-image: url(\'' + photoPreview + '\');'"></span>
                        </div>

                        {{-- Hover Overlay mit Kamera-Icon --}}
                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center text-white text-[10px] font-bold uppercase tracking-wider gap-1">
                            <svg class="w-6 h-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="text-[9px]">Ändern</span>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons & Info --}}
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-on:click.prevent="$refs.photo.click()" class="px-3.5 py-2 bg-white dark:bg-gray-900 text-gray-700 dark:text-white rounded-xl text-[10px] font-black uppercase tracking-wider hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors border border-gray-300 dark:border-gray-700/80 shadow-sm flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Bild ändern
                        </button>
                        @if($user && $user->profile && $user->profile->photo_path)
                            <button type="button" wire:click="deletePhoto" class="px-3.5 py-2 bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-wider hover:bg-red-500 hover:text-white transition-colors border border-red-500/20 shadow-sm cursor-pointer">Entfernen</button>
                        @endif
                    </div>
                    <p class="text-[10px] text-gray-500">JPG, PNG, WebP · max. 5 MB</p>
                </div>
            </div>
        </div>

        {{-- FORMULAR --}}
        <form wire:submit.prevent="saveProfile" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5 items-start">
                
                {{-- LINKE SPALTE: PERSÖNLICHE INFOS --}}
                <div class="space-y-5">
                    @if($guard === 'customer')
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Kundentyp *</label>
                                <select wire:model="isBusiness" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner cursor-pointer">
                                    <option value="0" class="text-gray-900 bg-white dark:bg-gray-900 dark:text-white">Privatkunde</option>
                                    <option value="1" class="text-gray-900 bg-white dark:bg-gray-900 dark:text-white">Gewerblich</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Geburtstag (optional)</label>
                                <input type="date" wire:model="birthday" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner [color-scheme:light] dark:[color-scheme:dark] cursor-pointer">
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Vorname *</label>
                            <input type="text" wire:model="firstName" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Nachname *</label>
                            <input type="text" wire:model="lastName" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">E-Mail Adresse *</label>
                        <input type="email" wire:model="email" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Telefon</label>
                        <input type="tel" wire:model="phoneNumber" placeholder="+49 ..." class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                    </div>
                </div>

                {{-- RECHTE SPALTE: ADRESSE --}}
                <div class="space-y-5">
                    <div class="flex gap-4">
                        <div class="flex-[3]">
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Straße *</label>
                            <input type="text" wire:model="street" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Nr. *</label>
                            <input type="text" wire:model="houseNumber" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-1/3 sm:w-36 shrink-0">
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">PLZ *</label>
                            <input type="text" wire:model="postal" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Ort *</label>
                            <input type="text" wire:model="city" required class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner text-left cursor-text">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400 mb-1.5">Land *</label>
                        <select wire:model="country" class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 sm:py-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner cursor-pointer">
                            @foreach($activeCountries as $code => $name)
                                <option value="{{$code}}" class="text-gray-900 bg-white dark:bg-gray-900 dark:text-white">{{$name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- ÜBER MICH (VOLLE BREITE, ECHTES TEXTAREA) --}}
            <div class="pt-2">
                <div class="flex justify-between items-center mb-1.5">
                    <label class="block text-[10px] font-black uppercase tracking-[0.18em] text-gray-600 dark:text-gray-400">Über mich</label>
                    <span class="text-[10px] text-gray-500">Optional · Angaben für dein Profil</span>
                </div>
                <textarea wire:model="about" rows="4" placeholder="Erzähle kurz etwas über dich, deine Wünsche oder besondere Angaben..." class="w-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-2xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm dark:shadow-inner resize-y min-h-[110px] leading-relaxed cursor-text"></textarea>
            </div>

            {{-- FOOTER BUTTONS --}}
            <div class="flex flex-col sm:flex-row items-center justify-between pt-5 border-t border-gray-200 dark:border-gray-800 gap-4 mt-6">
                @if($guard === 'customer' && $isGameModeActive)
                    <button type="button" wire:click="optOut" wire:confirm="Möchtest du das magische Dashboard wirklich deaktivieren? Dein Fortschritt bleibt gespeichert." class="bg-red-500/10 border border-red-500/30 text-red-600 dark:text-red-400 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-sm cursor-pointer">Spielmodus deaktivieren</button>
                @else
                    <div></div>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto bg-gradient-to-r from-primary-light to-primary text-gray-900 px-8 py-3.5 rounded-xl font-black uppercase tracking-widest text-xs hover:brightness-105 active:scale-95 transition-all shadow-md disabled:opacity-50 cursor-pointer">
                    <span wire:loading.remove wire:target="saveProfile">Änderungen Speichern</span>
                    <span wire:loading wire:target="saveProfile">Wird gespeichert...</span>
                </button>
            </div>
        </form>
    </div>
</div>
