<div x-data="{
        showProfileModal: false,
        activeProfileTab: 'profile',
        isMusicPlaying: false,
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

    <button @click="showProfileModal = true; activeProfileTab = 'password'" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-amber-500/5 border border-transparent hover:border-amber-500/30 text-gray-500 hover:bg-amber-500/10 hover:text-amber-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(245,158,11,0.3)] hover:scale-105 group shrink-0 snap-start" title="Sicherheit" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-amber-400 transition-colors">Schutz</span>
        <x-heroicon-s-key class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    <button @click="showProfileModal = true; activeProfileTab = '2fa'" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-emerald-500/5 border border-transparent hover:border-emerald-500/30 text-gray-500 hover:bg-emerald-500/10 hover:text-emerald-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(16,185,129,0.3)] hover:scale-105 group shrink-0 snap-start" title="2-Faktor-Auth" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-emerald-400 transition-colors">2-FA</span>
        <x-heroicon-s-shield-check class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    <button @click="showProfileModal = true; activeProfileTab = 'sessions'" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-purple-500/5 border border-transparent hover:border-purple-500/30 text-gray-500 hover:bg-purple-500/10 hover:text-purple-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(168,85,247,0.3)] hover:scale-105 group shrink-0 snap-start" title="Geräte & Sitzungen" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-purple-400 transition-colors">Geräte</span>
        <x-heroicon-s-computer-desktop class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    <div class="w-px h-8 bg-gray-700 mx-1 shrink-0 snap-start"></div>

    <button @click="isMusicPlaying = !isMusicPlaying; const audio = document.getElementById('funki-bgm'); if(audio) { isMusicPlaying ? audio.play() : audio.pause(); }" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl border border-transparent transition-all shadow-none hover:scale-105 group shrink-0 snap-start" :class="isMusicPlaying ? 'bg-primary/10 hover:border-primary/30 text-primary hover:shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-gray-800/50 hover:border-gray-500/30 text-gray-500 hover:text-white hover:shadow-lg'" title="Musik On/Off" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 transition-colors" x-text="isMusicPlaying ? 'Musik An' : 'Musik Aus'"></span>
        <x-heroicon-s-musical-note x-show="isMusicPlaying" class="w-6 h-6 animate-[bounce_2s_infinite]" />
        <x-heroicon-s-speaker-x-mark x-show="!isMusicPlaying" style="display: none;" class="w-6 h-6 group-hover:scale-110 transition-transform duration-300" />
    </button>

    <button wire:click="logout" class="flex flex-col items-center justify-center w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-xl bg-red-500/5 border border-transparent hover:border-red-500/30 text-gray-500 hover:bg-red-500/10 hover:text-red-400 transition-all shadow-none hover:shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:scale-105 group shrink-0 snap-start" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[9px] md:text-[10px] font-black uppercase tracking-widest mb-1 opacity-70 group-hover:opacity-100 group-hover:text-red-400 transition-colors">Logout</span>
        <x-heroicon-s-power class="w-6 h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    {{-- AUDIO PLAYER --}}
    <audio id="funki-bgm" src="{{ asset('storage/funki/audio/bgm.mp3') }}" loop preload="auto" class="hidden"></audio>

    {{-- GLOBALES MODAL (Teleportiert in den Body, um Z-Index Probleme zu lösen) --}}
    <template x-teleport="body">
        <div x-show="showProfileModal" style="display: none;" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-10 text-left">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/95 backdrop-blur-3xl" @click="showProfileModal = false" x-transition.opacity></div>

            <div class="relative w-full max-w-5xl h-full max-h-[90vh] flex flex-col bg-gray-900 rounded-[2.5rem] shadow-[0_0_100px_rgba(0,0,0,0.8)] border border-gray-800 overflow-hidden"
                 x-transition:enter="transition ease-out duration-300 delay-100"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                {{-- Close Button --}}
                <button @click="showProfileModal = false" class="absolute top-6 right-6 z-[2010] p-3 bg-gray-800 border border-gray-700 rounded-full text-gray-400 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-lg hover:scale-110 focus:outline-none">
                    <x-heroicon-m-x-mark class="w-6 h-6" />
                </button>

                {{-- Header & Tabs --}}
                <div class="px-8 pt-8 pb-4 border-b border-gray-800 bg-gray-900/50 shrink-0">
                    <h2 class="text-3xl font-serif font-bold text-white mb-6 tracking-tight">Konto & Sicherheit</h2>
                    <div class="flex gap-2 overflow-x-auto no-scrollbar">
                        <button @click="activeProfileTab = 'profile'" :class="activeProfileTab === 'profile' ? 'bg-primary text-gray-900 shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">Profil</button>
                        <button @click="activeProfileTab = 'password'" :class="activeProfileTab === 'password' ? 'bg-amber-500 text-gray-900 shadow-[0_0_15px_rgba(245,158,11,0.3)]' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">Passwort</button>
                        <button @click="activeProfileTab = '2fa'" :class="activeProfileTab === '2fa' ? 'bg-emerald-500 text-gray-900 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">2-FA Schutz</button>
                        <button @click="activeProfileTab = 'sessions'" :class="activeProfileTab === 'sessions' ? 'bg-purple-500 text-gray-900 shadow-[0_0_15px_rgba(168,85,247,0.3)]' : 'bg-gray-800 text-gray-400 hover:bg-gray-700'" class="px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">Geräte</button>
                    </div>
                </div>

                {{-- Tab Content Area --}}
                <div class="flex-1 overflow-y-auto p-8 custom-scrollbar relative bg-gray-900">

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
