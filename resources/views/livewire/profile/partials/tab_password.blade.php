<div x-show="activeProfileTab === 'password'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div x-init="@this.on('password-updated', () => { showPasswordSuccess = true; })" class="relative">

        {{-- Success Overlay --}}
        <div x-show="showPasswordSuccess" style="display: none;" x-transition class="absolute inset-0 z-[2005] bg-gray-900/95 backdrop-blur-xl flex flex-col items-center justify-center rounded-[2.5rem] border border-emerald-500/30">
            <svg class="w-24 h-24 text-emerald-400 mb-6 drop-shadow-[0_0_20px_rgba(16,185,129,0.5)] animate-[bounce_2s_infinite]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <h3 class="text-4xl font-serif font-bold text-white mb-8 tracking-tight">Passwort aktualisiert!</h3>
            <button @click="showPasswordSuccess = false" type="button" class="bg-emerald-500 text-white px-10 py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:scale-105 transition-all shadow-[0_0_20px_rgba(16,185,129,0.4)]">Meldung schließen</button>
        </div>

        <div class="mb-10 border-b border-gray-800 pb-6">
            <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">Passwort aktualisieren</h3>
            <p class="text-gray-400 text-sm">Stelle sicher, dass dein Konto ein langes, zufälliges Passwort verwendet, um sicher zu bleiben.</p>
        </div>

        <form wire:submit.prevent="updatePassword" class="max-w-2xl space-y-6">
            <div x-data="{ show: false }" class="relative">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Aktuelles Passwort</label>
                <input :type="show ? 'text' : 'password'" wire:model="currentPassword" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-5 py-3 text-white focus:ring-primary focus:border-primary transition-all shadow-inner pr-12">
                <button type="button" @click="show = !show" class="absolute right-4 top-[38px] focus:outline-none flex items-center justify-center group/eye">
                    <x-heroicon-o-eye-slash x-show="!show" class="w-5 h-5 text-gray-600 hover:text-primary transition-colors" />
                    <div x-show="show" style="display: none;" class="relative">
                        <div class="absolute inset-0 bg-primary/40 blur-md rounded-full scale-150 animate-pulse"></div>
                        <x-heroicon-s-eye class="relative z-10 w-6 h-6 text-primary drop-shadow-[0_0_15px_rgba(197,160,89,1)] hover:scale-110 transition-all" />
                    </div>
                </button>
                @error('currentPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div x-data="{ show: false }" class="relative">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Neues Passwort</label>
                <input :type="show ? 'text' : 'password'" wire:model="newPassword" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-5 py-3 text-white focus:ring-primary focus:border-primary transition-all shadow-inner pr-12">
                <button type="button" @click="show = !show" class="absolute right-4 top-[38px] focus:outline-none flex items-center justify-center group/eye">
                    <x-heroicon-o-eye-slash x-show="!show" class="w-5 h-5 text-gray-600 hover:text-primary transition-colors" />
                    <div x-show="show" style="display: none;" class="relative">
                        <div class="absolute inset-0 bg-primary/40 blur-md rounded-full scale-150 animate-pulse"></div>
                        <x-heroicon-s-eye class="relative z-10 w-6 h-6 text-primary drop-shadow-[0_0_15px_rgba(197,160,89,1)] hover:scale-110 transition-all" />
                    </div>
                </button>
                @error('newPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div x-data="{ show: false }" class="relative">
                <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 mb-2">Neues Passwort Wiederholen</label>
                <input :type="show ? 'text' : 'password'" wire:model="repeatNewPassword" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-5 py-3 text-white focus:ring-primary focus:border-primary transition-all shadow-inner pr-12">
                <button type="button" @click="show = !show" class="absolute right-4 top-[38px] focus:outline-none flex items-center justify-center group/eye">
                    <x-heroicon-o-eye-slash x-show="!show" class="w-5 h-5 text-gray-600 hover:text-primary transition-colors" />
                    <div x-show="show" style="display: none;" class="relative">
                        <div class="absolute inset-0 bg-primary/40 blur-md rounded-full scale-150 animate-pulse"></div>
                        <x-heroicon-s-eye class="relative z-10 w-6 h-6 text-primary drop-shadow-[0_0_15px_rgba(197,160,89,1)] hover:scale-110 transition-all" />
                    </div>
                </button>
                @error('repeatNewPassword') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center justify-end pt-6 mt-8 gap-4 border-t border-gray-800">
                <button type="submit" wire:loading.attr="disabled" class="bg-gray-800 text-white border border-gray-700 px-10 py-4 rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-gray-700 hover:text-white transition-all shadow-xl disabled:opacity-50">
                    <span wire:loading.remove wire:target="updatePassword">Speichern</span>
                    <span wire:loading wire:target="updatePassword">Speichere...</span>
                </button>
            </div>
        </form>
    </div>
</div>
