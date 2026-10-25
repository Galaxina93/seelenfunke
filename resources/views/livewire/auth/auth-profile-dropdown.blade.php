<div x-data="{
        showProfileModal: false,
        activeProfileTab: 'profile',
        openAvatar: false,
        showProfileSuccess: false,
        showPasswordSuccess: false,
        isDark: ('theme' in localStorage) ? (localStorage.theme === 'dark') : false,
        toggleTheme() {
            this.isDark = !this.isDark;
            if (this.isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
                localStorage.setItem('theme', 'light');
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { isDark: this.isDark, theme: this.isDark ? 'dark' : 'light' } }));
        }
    }"
     @theme-changed.window="isDark = $event.detail.isDark"
     x-on:livewire:navigated.window="isDark = ('theme' in localStorage) ? (localStorage.theme === 'dark') : false"
     @open-profile-modal.window="showProfileModal = true; activeProfileTab = $event.detail.tab"
     @wheel.passive="$el.scrollLeft += $event.deltaY"
     class="flex flex-nowrap items-center justify-start gap-1.5 sm:gap-2 lg:gap-1.5 xl:gap-2.5 2xl:gap-3 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl p-1.5 sm:p-2 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-sm dark:shadow-inner overflow-x-auto no-scrollbar w-fit max-w-full z-[999] snap-x snap-mandatory touch-pan-x ml-auto"
     style="-webkit-overflow-scrolling: touch;">

    {{-- TAILWIND SUN / MOON THEME SWITCH (Links neben Webseite) --}}
    <button type="button"
            @click="toggleTheme(); if (window.spawnSparks) window.spawnSparks($event)"
            class="flex flex-col items-center justify-center w-14 h-14 sm:w-16 sm:h-16 lg:w-14 lg:h-14 xl:w-16 xl:h-16 2xl:w-[4.5rem] 2xl:h-[4.5rem] rounded-xl transition-all shadow-none hover:scale-105 group shrink-0 snap-start relative overflow-hidden"
            :class="isDark ? 'bg-amber-500/5 border border-transparent hover:border-amber-500/30 text-gray-500 hover:bg-amber-500/10 hover:text-amber-400 hover:shadow-[0_0_15px_rgba(245,158,11,0.3)]' : 'bg-amber-500/10 border border-amber-300/40 text-amber-600 hover:bg-amber-500/20 hover:border-amber-400 hover:text-amber-600'"
            :title="isDark ? 'Hellen Modus aktivieren' : 'Dunklen Modus aktivieren'"
            @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[8px] xl:text-[9px] 2xl:text-[10px] font-black uppercase tracking-widest mb-0.5 xl:mb-1 opacity-70 group-hover:opacity-100 transition-colors"
              :class="isDark ? 'group-hover:text-amber-400' : 'group-hover:text-amber-600'"
              x-text="isDark ? 'Dunkel' : 'Hell'"></span>
        <div class="relative w-5 h-5 xl:w-6 xl:h-6 flex items-center justify-center">
            <x-heroicon-s-moon x-cloak x-show="isDark" class="w-5 h-5 xl:w-6 xl:h-6 group-hover:-rotate-12 group-active:scale-90 transition-transform duration-300 text-amber-400" />
            <x-heroicon-s-sun x-cloak x-show="!isDark" class="w-5 h-5 xl:w-6 xl:h-6 group-hover:rotate-45 group-active:scale-90 transition-transform duration-300 text-amber-500" />
        </div>
    </button>

    {{-- DESKTOP ICON DOCK (Mit Beschriftung) --}}
    <a href="{{ route('shop') }}" target="_blank" class="flex flex-col items-center justify-center w-14 h-14 sm:w-16 sm:h-16 lg:w-14 lg:h-14 xl:w-16 xl:h-16 2xl:w-[4.5rem] 2xl:h-[4.5rem] rounded-xl bg-blue-500/5 border border-transparent hover:border-blue-500/30 text-gray-500 hover:bg-blue-500/10 hover:text-blue-500 dark:hover:text-blue-400 transition-all shadow-none dark:hover:shadow-[0_0_15px_rgba(59,130,246,0.3)] hover:scale-105 group shrink-0 snap-start" title="Zum Shop" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[8px] xl:text-[9px] 2xl:text-[10px] font-black uppercase tracking-widest mb-0.5 xl:mb-1 opacity-70 group-hover:opacity-100 group-hover:text-blue-500 dark:group-hover:text-blue-400 transition-colors">Shop</span>
        <x-heroicon-s-shopping-bag class="w-5 h-5 xl:w-6 xl:h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </a>

    <button @click="showProfileModal = true; activeProfileTab = 'profile'" class="flex flex-col items-center justify-center w-14 h-14 sm:w-16 sm:h-16 lg:w-14 lg:h-14 xl:w-16 xl:h-16 2xl:w-[4.5rem] 2xl:h-[4.5rem] rounded-xl bg-cyan-500/5 border border-transparent hover:border-cyan-500/30 text-gray-500 hover:bg-cyan-500/10 hover:text-cyan-500 dark:hover:text-cyan-400 transition-all shadow-none dark:hover:shadow-[0_0_15px_rgba(6,182,212,0.3)] hover:scale-105 group shrink-0 snap-start" title="Profil" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[8px] xl:text-[9px] 2xl:text-[10px] font-black uppercase tracking-widest mb-0.5 xl:mb-1 opacity-70 group-hover:opacity-100 group-hover:text-cyan-500 dark:group-hover:text-cyan-400 transition-colors">Profil</span>
        <x-heroicon-s-user class="w-5 h-5 xl:w-6 xl:h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    <div class="w-px h-6 lg:h-7 xl:h-8 bg-gray-200 dark:bg-gray-800 mx-0.5 sm:mx-1 shrink-0 snap-start"></div>

    <button wire:click="logout" class="flex flex-col items-center justify-center w-14 h-14 sm:w-16 sm:h-16 lg:w-14 lg:h-14 xl:w-16 xl:h-16 2xl:w-[4.5rem] 2xl:h-[4.5rem] rounded-xl bg-red-500/5 border border-transparent hover:border-red-500/30 text-gray-500 hover:bg-red-500/10 hover:text-red-500 dark:hover:text-red-400 transition-all shadow-none dark:hover:shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:scale-105 group shrink-0 snap-start" @mouseenter="window.spawnSparks ? window.spawnSparks($event) : null">
        <span class="text-[8px] xl:text-[9px] 2xl:text-[10px] font-black uppercase tracking-widest mb-0.5 xl:mb-1 opacity-70 group-hover:opacity-100 group-hover:text-red-500 dark:group-hover:text-red-400 transition-colors">Logout</span>
        <x-heroicon-s-power class="w-5 h-5 xl:w-6 xl:h-6 group-hover:rotate-12 group-active:scale-90 transition-transform duration-300" />
    </button>

    {{-- GLOBALES MODAL (Teleportiert in den Body, um Z-Index Probleme zu lösen) --}}
    <template x-teleport="body">
        <div x-show="showProfileModal" style="display: none;" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-10 text-left">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 dark:bg-black/90 backdrop-blur-sm dark:backdrop-blur-md" @click="showProfileModal = false" x-transition.opacity></div>

            <div class="relative w-full max-w-5xl h-full max-h-[90vh] flex flex-col bg-white/95 dark:bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] shadow-2xl dark:shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-200 dark:border-gray-800 overflow-hidden"
                 x-transition:enter="transition ease-out duration-300 delay-100"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-10"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-10">

                {{-- Close Button --}}
                <button @click="showProfileModal = false" class="absolute top-6 right-6 z-[2010] p-3 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl text-gray-500 hover:text-white hover:bg-red-500 hover:border-red-500 transition-all shadow-sm hover:scale-110 focus:outline-none">
                    <x-heroicon-m-x-mark class="w-6 h-6" />
                </button>

                {{-- Header & Tabs --}}
                <div class="px-8 pt-8 pb-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-950/50 shrink-0 shadow-sm dark:shadow-inner">
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mb-6 tracking-tight">Konto & Sicherheit</h2>
                    <div class="flex gap-2 sm:gap-3 overflow-x-auto no-scrollbar pb-1">
                        <button @click="activeProfileTab = 'profile'" :class="activeProfileTab === 'profile' ? 'bg-gradient-to-r from-primary-light to-primary text-gray-900 shadow-md font-black' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white shadow-sm'" class="px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all whitespace-nowrap">Profil</button>
                        <button @click="activeProfileTab = 'password'" :class="activeProfileTab === 'password' ? 'bg-amber-500 text-gray-900 shadow-md font-black' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white shadow-sm'" class="px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all whitespace-nowrap">Passwort</button>
                        <button @click="activeProfileTab = '2fa'" :class="activeProfileTab === '2fa' ? 'bg-emerald-500 text-white shadow-md font-black' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white shadow-sm'" class="px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all whitespace-nowrap">2-FA Schutz</button>
                        <button @click="activeProfileTab = 'sessions'" :class="activeProfileTab === 'sessions' ? 'bg-purple-500 text-white shadow-md font-black' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white shadow-sm'" class="px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all whitespace-nowrap">Geräte</button>
                        <button @click="activeProfileTab = 'delete_account'" :class="activeProfileTab === 'delete_account' ? 'bg-red-500 text-white shadow-md font-black' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white shadow-sm'" class="px-6 py-2.5 rounded-xl text-[10px] uppercase tracking-widest transition-all whitespace-nowrap">Konto löschen</button>
                    </div>
                </div>

                {{-- Tab Content Area --}}
                <div class="flex-1 overflow-y-auto p-6 sm:p-8 custom-scrollbar relative bg-transparent">

                    {{-- TAB: PROFIL --}}
                    @include('livewire.auth.partials.tab_profile')

                    {{-- TAB: PASSWORT --}}
                    @include('livewire.auth.partials.tab_password')

                    {{-- TAB: 2-FA SCHUTZ --}}
                    @include('livewire.auth.partials.tab_2fa')

                    {{-- TAB: GERÄTE & SITZUNGEN --}}
                    @include('livewire.auth.partials.tab_devices')

                    {{-- TAB: KONTO LÖSCHEN --}}
                    @include('livewire.auth.partials.tab_delete_account')

                </div>
            </div>
        </div>
    </template>
</div>
