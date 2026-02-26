<div x-data="{
        showProfileModal: false,
        activeProfileTab: 'profile',
        isMusicPlaying: false,
        volume: 0.2, // Standard-Lautstärke (20%)
        openAvatar: false,
        showProfileSuccess: false,
        showPasswordSuccess: false
    }"
     @open-profile-modal.window="showProfileModal = true; activeProfileTab = $event.detail.tab"
     class="flex flex-nowrap items-center justify-start xl:justify-end gap-2 md:gap-3 bg-gray-900/80 backdrop-blur-xl p-2 rounded-2xl border border-gray-800 shadow-inner overflow-x-auto no-scrollbar w-full xl:w-auto shrink-0 z-[999] snap-x snap-mandatory touch-pan-x"
     style="-webkit-overflow-scrolling: touch;">

    {{-- DESKTOP ICON DOCK (Mit Beschriftung) --}}
    <a href="/" target="_blank" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-blue-500/5 border border-transparent hover:border-blue-500/30 text-gray-500 hover:bg-blue-500/10 hover:text-blue-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(59,130,246,0.3)] hover:scale-105 group shrink-0 snap-start" title="Zur Webseite" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-blue-400 transition-colors">Webseite</span>
        <x-heroicon-s-globe-alt class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </a>

    <button @click="showProfileModal = true; activeProfileTab = 'profile'" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-cyan-500/5 border border-transparent hover:border-cyan-500/30 text-gray-500 hover:bg-cyan-500/10 hover:text-cyan-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:scale-105 group shrink-0 snap-start" title="Profil" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-cyan-400 transition-colors">Profil</span>
        <x-heroicon-s-user class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    <div class="w-px h-8 bg-gray-800 mx-1 shrink-0 snap-start"></div>

    {{-- MUSIK TOGGLE BUTTON --}}
    <button @click="isMusicPlaying = !isMusicPlaying; isMusicPlaying ? $refs.funkiAudio.play() : $refs.funkiAudio.pause();" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl border border-transparent transition-all shadow-none hover:scale-105 group shrink-0 snap-start" :class="isMusicPlaying ? 'bg-primary/10 hover:border-primary/30 text-primary hover:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-gray-800/50 hover:border-gray-500/30 text-gray-500 hover:text-white hover:shadow-lg'" title="Musik On/Off" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 transition-colors" x-text="isMusicPlaying ? 'Musik An' : 'Musik Aus'"></span>
        <x-heroicon-s-musical-note x-show="isMusicPlaying" class="w-6 h-6 animate-[bounce_2s_infinite]" />
        <x-heroicon-s-speaker-x-mark x-show="!isMusicPlaying" style="display: none;" class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" />
    </button>

    {{-- LAUTSTÄRKE REGLER --}}
    <div class="flex flex-col items-center justify-center w-20 md:w-24 h-16 md:h-[4.5rem] rounded-xl bg-gray-950/50 border border-gray-800 hover:border-gray-700 transition-all shadow-inner px-3 shrink-0 snap-start group" title="Lautstärke">
        <div class="flex items-center justify-between w-full mb-1.5 px-0.5">
            <x-heroicon-s-speaker-wave class="w-3.5 h-3.5 text-gray-500 group-hover:text-primary transition-colors" />
            <span class="text-[9px] font-black font-mono text-gray-500 group-hover:text-white transition-colors" x-text="Math.round(volume * 100) + '%'"></span>
        </div>
        <input type="range" min="0" max="1" step="0.01" x-model="volume"
               class="w-full h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer outline-none shadow-inner
                      [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3.5 [&::-webkit-slider-thumb]:h-3.5
                      [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:shadow-[0_0_10px_rgba(197,160,89,0.8)]
                      [&::-moz-range-thumb]:w-3.5 [&::-moz-range-thumb]:h-3.5 [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:rounded-full">
    </div>

    <div class="w-px h-8 bg-gray-800 mx-1 shrink-0 snap-start"></div>

    <button wire:click="logout" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-red-500/5 border border-transparent hover:border-red-500/30 text-gray-500 hover:bg-red-500/10 hover:text-red-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:scale-105 group shrink-0 snap-start" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-red-400 transition-colors">Logout</span>
        <x-heroicon-s-power class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    {{-- AUDIO PLAYER (mit Alpine Verknüpfung) --}}
    <audio x-ref="funkiAudio" src="{{ asset('storage/funki/audio/bgm.mp3') }}" loop preload="auto" class="hidden" x-init="$el.volume = volume; $watch('volume', val => $el.volume = val)"></audio>

    {{-- GLOBALES MODAL (Teleportiert in den Body, um Z-Index Probleme zu lösen) --}}
    <template x-teleport="body">
        <div x-show="showProfileModal" style="display: none;" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-10 text-left">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="showProfileModal = false" x-transition.opacity></div>

            <div class="relative w-full max-w-5xl h-full max-h-[90vh] flex flex-col bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-800 overflow-hidden"
                 x-transition:enter="transition ease-out duration-300 delay-100"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                {{-- Close Button --}}
                <button @click="showProfileModal = false" class="absolute top-6 right-6 z-[2010] p-3 bg-gray-950 border border-gray-800 rounded-xl text-gray-500 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-inner hover:scale-110 focus:outline-none">
                    <x-heroicon-m-x-mark class="w-6 h-6" />
                </button>

                {{-- Header & Tabs --}}
                <div class="px-8 pt-8 pb-4 border-b border-gray-800 bg-gray-950/50 shrink-0 shadow-inner">
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-white mb-6 tracking-tight">Konto & Sicherheit</h2>
                    <div class="flex gap-2 sm:gap-3 overflow-x-auto no-scrollbar pb-1">
                        <button @click="activeProfileTab = 'profile'" :class="activeProfileTab === 'profile' ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-gray-900 border border-gray-800 text-gray-400 hover:bg-gray-800 hover:text-white shadow-inner'" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap">Profil</button>
                        <button @click="activeProfileTab = 'password'" :class="activeProfileTab === 'password' ? 'bg-amber-500 text-gray-900 shadow-[0_0_15px_rgba(245,158,11,0.3)]' : 'bg-gray-900 border border-gray-800 text-gray-400 hover:bg-gray-800 hover:text-white shadow-inner'" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap">Passwort</button>
                        <button @click="activeProfileTab = '2fa'" :class="activeProfileTab === '2fa' ? 'bg-emerald-500 text-gray-900 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'bg-gray-900 border border-gray-800 text-gray-400 hover:bg-gray-800 hover:text-white shadow-inner'" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap">2-FA Schutz</button>
                        <button @click="activeProfileTab = 'sessions'" :class="activeProfileTab === 'sessions' ? 'bg-purple-500 text-gray-900 shadow-[0_0_15px_rgba(168,85,247,0.3)]' : 'bg-gray-900 border border-gray-800 text-gray-400 hover:bg-gray-800 hover:text-white shadow-inner'" class="px-6 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap">Geräte</button>
                    </div>
                </div>

                {{-- Tab Content Area --}}
                <div class="flex-1 overflow-y-auto p-6 sm:p-8 custom-scrollbar relative bg-transparent">

                    {{-- TAB: PROFIL --}}
                    @include('livewire.profile.partials.tab_profile')

                    {{-- TAB: PASSWORT --}}
                    @include('livewire.profile.partials.tab_password')

                    {{-- TAB: 2-FA SCHUTZ --}}
                    @include('livewire.profile.partials.tab_2fa')

                    {{-- TAB: GERÄTE & SITZUNGEN (Inklusive Löschen) --}}
                    @include('livewire.profile.partials.tab_devices')

                </div>
            </div>
        </div>
    </template>
</div>
