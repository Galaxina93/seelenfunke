<div x-show="activeProfileTab === 'profile'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div x-init="@this.on('saved', () => { showProfileSuccess = true; })" class="relative">
        <div x-show="showProfileSuccess" style="display: none;" x-transition class="absolute inset-0 z-[2005] bg-gray-900/95 backdrop-blur-xl flex flex-col items-center justify-center rounded-[2.5rem] border border-emerald-500/30">
            <svg class="w-20 h-20 text-emerald-400 mb-4 drop-shadow-[0_0_20px_rgba(16,185,129,0.5)] animate-[bounce_2s_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <h3 class="text-3xl font-serif font-bold text-white mb-6 tracking-tight">Erfolgreich gespeichert!</h3>
            <button @click="showProfileSuccess = false" type="button" class="bg-emerald-500 text-white px-8 py-3 rounded-2xl font-black uppercase tracking-widest text-xs hover:scale-105 transition-all shadow-[0_0_20px_rgba(16,185,129,0.4)]">Schließen</button>
        </div>
        <div class="flex justify-between items-end mb-6 border-b border-gray-800 pb-4">
            <div>
                <h3 class="text-xl font-serif font-bold text-white mb-1">Persönliche Daten</h3>
                <p class="text-gray-400 text-xs">Aktualisiere dein Profil für eine reibungslose Abwicklung.</p>
                @if($guard === 'customer')
                    <div class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-full border {{$isBusiness ? 'bg-amber-500/10 border-amber-500/30 text-amber-400' : 'bg-blue-500/10 border-blue-500/30 text-blue-400'}} text-[10px] font-black uppercase tracking-widest shadow-sm">
                        @if($isBusiness)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg> Gewerblicher Kunde
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Privater Kunde
                        @endif
                    </div>
                @endif
            </div>
            <div x-data="{photoName: null, photoPreview: null}" class="flex items-center gap-4">
                <input type="file" class="hidden" wire:model.live="photo" x-ref="photo" x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />
                <div class="flex flex-col items-end gap-1">
                    <div class="flex gap-2">
                        <button type="button" x-on:click.prevent="$refs.photo.click()" class="px-3 py-1.5 bg-gray-800 text-white rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-gray-700 transition-colors border border-gray-700">Bild ändern</button>
                        @if($user && $user->profile && $user->profile->photo_path)
                            <button type="button" wire:click="deletePhoto" class="px-3 py-1.5 bg-red-500/10 text-red-500 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-colors border border-red-500/20">Entfernen</button>
                        @endif
                    </div>
                </div>
                <div class="relative w-12 h-12 rounded-full bg-gray-950 border border-gray-700 overflow-hidden shadow-inner flex items-center justify-center shrink-0 cursor-pointer hover:border-primary transition-colors" @click="$refs.photo.click()">
                    <div x-show="!photoPreview" class="w-full h-full">
                        @if($user && $user->profile && $user->profile->photo_path)
                            <img src="{{Storage::url($user->profile->photo_path)}}" class="w-full h-full object-cover">
                        @else
                            <span class="text-gray-500 font-bold text-sm flex items-center justify-center h-full">{{substr($firstName ?? 'U', 0, 1)}}</span>
                        @endif
                    </div>
                    <div x-show="photoPreview" class="w-full h-full" style="display: none;">
                        <span class="block w-full h-full bg-cover bg-center bg-no-repeat" x-bind:style="'background-image: url(\'' + photoPreview + '\');'"></span>
                    </div>
                </div>
            </div>
        </div>
        <form wire:submit.prevent="saveProfile" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                <div class="space-y-4">
                    @if($guard === 'customer')
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Kundentyp *</label>
                                <select wire:model="isBusiness" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner cursor-pointer">
                                    <option value="0" class="text-gray-900 bg-white">Privatkunde</option>
                                    <option value="1" class="text-gray-900 bg-white">Gewerblich</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Geburtstag *</label>
                                <input type="date" wire:model="birthday" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner [color-scheme:dark]">
                            </div>
                        </div>
                    @endif
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Vorname *</label>
                            <input type="text" wire:model="firstName" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Nachname *</label>
                            <input type="text" wire:model="lastName" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">E-Mail Adresse *</label>
                        <input type="email" wire:model="email" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Telefon</label>
                        <input type="text" wire:model="phoneNumber" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex gap-4">
                        <div class="flex-[3]">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Straße *</label>
                            <input type="text" wire:model="street" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                        </div>
                        <div class="flex-1">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Nr. *</label>
                            <input type="text" wire:model="houseNumber" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner text-center">
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">PLZ *</label>
                            <input type="text" wire:model="postal" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner text-center">
                        </div>
                        <div class="flex-[2]">
                            <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Ort *</label>
                            <input type="text" wire:model="city" required class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Land *</label>
                        <select wire:model="country" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner cursor-pointer appearance-none">
                            @foreach($activeCountries as $code => $name)
                                <option value="{{$code}}" class="text-gray-900 bg-white">{{$name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-[0.2em] text-gray-500 mb-1">Über mich</label>
                        <textarea wire:model="about" rows="1" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2 text-sm text-white focus:ring-primary focus:border-primary transition-all shadow-inner resize-none"></textarea>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-between pt-4 border-t border-gray-800 gap-4 mt-4">
                @if($guard === 'customer')
                    <button type="button" wire:click="optOut" wire:confirm="Möchtest du das magische Dashboard wirklich deaktivieren? Dein Fortschritt bleibt gespeichert." class="bg-red-500/10 border border-red-500/30 text-red-500 px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)]">Spielmodus deaktivieren</button>
                @else
                    <div></div>
                @endif
                <button type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto bg-primary text-gray-900 px-8 py-3 rounded-xl font-black uppercase tracking-widest text-xs hover:scale-105 transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)] disabled:opacity-50">
                    <span wire:loading.remove wire:target="saveProfile">Änderungen Speichern</span>
                    <span wire:loading wire:target="saveProfile">Wird gespeichert...</span>
                </button>
            </div>
        </form>
    </div>
</div>
