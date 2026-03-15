<div>
    @if($profile)
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 rounded-3xl p-6 shadow-2xl relative overflow-hidden group">
            <!-- Decorative Backgrounds -->
            <div class="absolute top-0 right-0 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl group-hover:bg-indigo-500/20 transition-all duration-700 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-purple-500/10 rounded-full blur-3xl group-hover:bg-purple-500/20 transition-all duration-700 pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 mb-6">
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        @if($profile->avatar_path)
                            <img src="{{ Storage::url($profile->avatar_path) }}" alt="{{ $profile->full_name }}" class="w-24 h-24 rounded-full object-cover border-[3px] border-gray-800 shadow-[0_0_20px_rgba(99,102,241,0.2)]">
                        @else
                            <div class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500/30 to-purple-500/30 border-2 border-indigo-500/40 flex items-center justify-center text-indigo-300 font-bold text-4xl shadow-[0_0_20px_rgba(99,102,241,0.2)]">
                                {{ substr($profile->first_name, 0, 1) }}{{ $profile->last_name ? substr($profile->last_name, 0, 1) : '' }}
                            </div>
                        @endif
                        @if($profile->relation_type)
                            <div class="absolute -bottom-2 -translate-x-1/2 left-1/2 whitespace-nowrap px-3 py-1 rounded-full bg-gray-950 border border-gray-800 text-indigo-400 font-bold text-[10px] uppercase tracking-widest shadow-lg">
                                {{ $profile->relation_type }}
                            </div>
                        @endif
                    </div>
                    
                    <!-- Name & Aliases -->
                    <div class="flex-1">
                        <h3 class="text-2xl font-serif font-bold text-white tracking-tight leading-tight">
                            {{ $profile->full_name }}
                        </h3>
                        @if($profile->nickname)
                            <div class="text-indigo-400/80 italic text-sm mt-1">
                                alias "{{ $profile->nickname }}"
                            </div>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Contact Column -->
                    <div class="space-y-3">
                        @if($profile->phone)
                            <a href="tel:{{ $profile->phone }}" class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:bg-indigo-500/10 hover:border-indigo-500/30 transition-all group/link">
                                <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-gray-400 group-hover/link:text-indigo-400 group-hover/link:border-indigo-500/30 transition-colors">
                                    <x-heroicon-o-phone class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 group-hover/link:text-indigo-500 transition-colors">Telefon</div>
                                    <div class="text-sm text-gray-300">{{ $profile->phone }}</div>
                                </div>
                            </a>
                        @endif

                        @if($profile->email)
                            <a href="mailto:{{ $profile->email }}" class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80 hover:bg-purple-500/10 hover:border-purple-500/30 transition-all group/link">
                                <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-gray-400 group-hover/link:text-purple-400 group-hover/link:border-purple-500/30 transition-colors">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                </div>
                                <div class="overflow-hidden">
                                    <div class="text-[9px] uppercase tracking-widest font-bold text-gray-500 group-hover/link:text-purple-500 transition-colors">E-Mail</div>
                                    <div class="text-sm text-gray-300 truncate">{{ $profile->email }}</div>
                                </div>
                            </a>
                        @endif

                        @if($profile->birthday)
                            <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80">
                                <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-emerald-400">
                                    <x-heroicon-o-gift class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="text-[9px] uppercase tracking-widest font-bold text-emerald-500/70">Geburtstag</div>
                                    <div class="text-sm text-gray-300">{{ $profile->birthday->format('d.m.Y') }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Address & Links Column -->
                    <div class="space-y-3">
                        @if($profile->street || $profile->city)
                            <div class="flex items-start gap-3 p-3 rounded-2xl bg-gray-950/50 border border-gray-800/80">
                                <div class="w-8 h-8 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-amber-400 shrink-0 mt-0.5">
                                    <x-heroicon-o-map-pin class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="text-[9px] uppercase tracking-widest font-bold text-amber-500/70 mb-1">Wohnort</div>
                                    <div class="text-sm text-gray-300 leading-tight">
                                        @if($profile->street){{ $profile->street }}<br>@endif
                                        @if($profile->postal_code){{ $profile->postal_code }} @endif{{ $profile->city }}
                                        @if($profile->country)<span class="text-gray-500">, {{ $profile->country }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(!empty($profile->links) && count($profile->links) > 0)
                            <div class="pt-2">
                                <div class="text-[9px] uppercase tracking-widest font-bold text-pink-500/70 mb-2 px-1">Verlinkungen</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($profile->links as $link)
                                        @if(!empty($link['name']) && !empty($link['url']))
                                            <a href="{{ $link['url'] }}" target="_blank" class="px-3 py-1.5 bg-pink-500/10 border border-pink-500/20 text-pink-400 hover:bg-pink-500/20 hover:border-pink-500/40 hover:text-pink-300 transition-all rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm group/btn">
                                                <x-heroicon-o-link class="w-3.5 h-3.5 group-hover/btn:rotate-12 transition-transform"/> {{ $link['name'] }}
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="p-4 bg-red-500/10 border border-red-500/30 rounded-2xl text-red-500 text-sm flex items-center gap-3 italic">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
            Vorschau nicht möglich: Profil nicht gefunden.
        </div>
    @endif
</div>
