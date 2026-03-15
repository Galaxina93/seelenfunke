<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-3xl font-black text-white mb-2 uppercase tracking-wider font-mono">KI Agenten Management</h2>
                <p class="text-gray-400 font-mono text-sm max-w-2xl">Verwalte hier das Multi-Agenten-System. Jeder Agent (Bot) hat eine eigene Identität und spezifische Fähigkeiten.</p>
            </div>
            <button wire:click="createAgent" class="bg-primary hover:bg-primary/80 text-gray-900 font-bold py-3 px-8 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:shadow-[0_0_30px_rgba(197,160,89,0.5)] transition-all flex items-center gap-3 font-mono uppercase tracking-widest shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M12 3.75a.75.75 0 01.75.75v6.75h6.75a.75.75 0 010 1.5h-6.75v6.75a.75.75 0 01-1.5 0v-6.75H4.5a.75.75 0 010-1.5h6.75V4.5a.75.75 0 01.75-.75z" clip-rule="evenodd" /></svg>
                Agent Erschaffen
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($agents as $agent)
                @php
                    $agentColor = $agent->color ?? 'cyan-500';
                    $agentIcon = $agent->icon ?? 'sparkles';
                    if(str_starts_with($agentIcon, 'bi-')) $agentIcon = 'sparkles';
                @endphp
                <div wire:click="editAgent('{{ $agent->id }}')" class="bg-black/40 backdrop-blur-md border {{ $agent->name === 'Funkira' ? 'border-primary/50 shadow-[0_0_20px_rgba(197,160,89,0.15)]' : 'border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)]' }} hover:border-{{ $agentColor }} rounded-3xl p-6 transition-all cursor-pointer group relative overflow-hidden">
                    
                    <div class="absolute inset-0 bg-gradient-to-br from-{{ $agentColor }}/5 to-transparent pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"></div>

                    <div class="relative z-10 flex items-start justify-between mb-5">
                        <div class="flex items-center gap-4">
                            @if($agent->profile_picture)
                                <div class="h-14 w-14 rounded-2xl overflow-hidden border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] text-{{ $agentColor }} bg-gray-900 group-hover:scale-110 transition-transform">
                                    <img src="{{ Storage::url($agent->profile_picture) }}" class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="h-14 w-14 rounded-2xl flex items-center justify-center bg-{{ $agentColor }}/20 text-{{ $agentColor }} border border-{{ $agentColor }}/30 shadow-[0_0_15px_currentColor] group-hover:scale-110 transition-transform">
                                    <x-dynamic-component :component="'heroicon-o-' . $agentIcon" class="w-7 h-7" />
                                </div>
                            @endif
                            
                            <div>
                                <h3 class="text-xl font-bold text-white mb-0.5 group-hover:text-{{ $agentColor }} transition-colors font-mono">{{ $agent->name }}</h3>
                                @if($agent->is_active)
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 uppercase tracking-widest inline-block">Online</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-red-500/20 text-red-500 border border-red-500/30 uppercase tracking-widest inline-block">Offline</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <p class="relative z-10 text-xs text-gray-400 line-clamp-2 h-8 font-mono mb-4">{{ $agent->role_description ?? 'Spezialisierung nicht definiert.' }}</p>

                    <div class="relative z-10 pt-4 border-t border-gray-800/80 flex items-center justify-between text-[11px] font-mono uppercase tracking-widest">
                        <span class="flex items-center gap-1.5 text-gray-500 group-hover:text-gray-300 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 2zM10 15a.75.75 0 01.75.75v1.5a.75.75 0 01-1.5 0v-1.5A.75.75 0 0110 15zM10 7a3 3 0 100 6 3 3 0 000-6zM15.657 5.404a.75.75 0 10-1.06-1.06l-1.061 1.06a.75.75 0 001.06 1.06l1.06-1.06zM6.464 14.596a.75.75 0 10-1.06-1.06l-1.06 1.06a.75.75 0 001.06 1.06l1.06-1.06zM18 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 0118 10zM5 10a.75.75 0 01-.75.75h-1.5a.75.75 0 010-1.5h1.5A.75.75 0 015 10zM14.596 15.657a.75.75 0 001.06-1.06l-1.06-1.061a.75.75 0 10-1.06 1.06l1.06 1.06zM5.404 6.464a.75.75 0 001.06-1.06l-1.06-1.06a.75.75 0 10-1.061 1.06l1.06 1.06z" clip-rule="evenodd" /></svg>
                            {{ $agent->tools()->count() }} Skills
                        </span>
                        <span class="flex items-center gap-1.5 text-indigo-500/70 group-hover:text-indigo-400 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5"><path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V14a1.5 1.5 0 001.5 1.5h15A1.5 1.5 0 0019 14V5.5A1.5 1.5 0 0017.5 4h-15zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                            {{ explode(' ', $agent->model ?? 'Standard')[0] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

