<div x-show="activeProfileTab === 'sessions'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-8" x-transition:enter-end="opacity-100 translate-x-0">
    <div class="mb-10 border-b border-gray-800 pb-6">
        <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">Browser-Sitzungen</h3>
        <p class="text-gray-400 text-sm">Verwalte deine aktiven Sitzungen auf anderen Browsern und Geräten.</p>
    </div>
    <div class="mt-6 space-y-4 mb-10">
        @if(method_exists($this, 'getBrowserSessions'))
            @foreach($this->getBrowserSessions($user) as $session)
                <div class="flex items-center gap-6 bg-gray-950 p-5 rounded-2xl border border-gray-800 hover:border-primary/30 transition-colors">
                    <div class="text-gray-500">
                        @if($session->device_type === 'Desktop')
                            <x-heroicon-o-computer-desktop class="w-8 h-8 text-primary opacity-80" />
                        @elseif($session->device_type === 'Mobile')
                            <x-heroicon-o-device-phone-mobile class="w-8 h-8 text-primary opacity-80" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-bold text-gray-200">{{ $session->user_agent }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $session->ip_address }} <span class="mx-1">•</span>
                            @if($loop->first)
                                <span class="text-emerald-400 font-black uppercase tracking-widest text-[10px]">Dieses Gerät</span>
                            @else
                                Zuletzt aktiv {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
    <form wire:submit.prevent="deleteOtherSessions" class="flex flex-col sm:flex-row items-start sm:items-center gap-6 pt-6 border-t border-gray-800 mb-16">
        <button type="submit" wire:loading.attr="disabled" class="bg-gray-800 text-white border border-gray-700 px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-xs hover:bg-gray-700 transition-all shadow-xl disabled:opacity-50">
            <span wire:loading.remove wire:target="deleteOtherSessions">Andere Sitzungen abmelden</span>
            <span wire:loading wire:target="deleteOtherSessions">Wird abgemeldet...</span>
        </button>
        <span x-data="{ shown: false, timeout: null }" x-init="@this.on('loggedOut', () => { clearTimeout(timeout); shown = true; timeout = setTimeout(() => { shown = false }, 2000); })" x-show="shown" style="display: none;" class="text-sm text-emerald-400 font-bold">Erledigt.</span>
    </form>

</div>
