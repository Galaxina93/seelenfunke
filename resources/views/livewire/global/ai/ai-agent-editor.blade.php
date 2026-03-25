<div>
    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center gap-4">
            <button wire:click="cancel" class="text-gray-400 hover:text-white transition-colors bg-gray-900 border border-gray-800 rounded-full h-10 w-10 flex items-center justify-center shadow-[0_0_15px_rgba(0,0,0,0.5)]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </button>
            <div>
                <h2 class="text-3xl font-black text-white mb-1 uppercase tracking-wider font-mono">{{ $agentId === 'new' ? 'Neuen Agenten erschaffen' : 'Agent ' . $name . ' konfigurieren' }}</h2>
                <p class="text-gray-400 font-mono text-sm">Verwalte die Identität, das Gehirn und die Fähigkeiten (Werkzeuge) dieses Agents.</p>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="bg-emerald-500/20 border border-emerald-500/50 text-emerald-300 px-4 py-3 rounded-lg mb-6 font-mono text-sm shadow-[0_0_15px_rgba(16,185,129,0.2)] flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                {{ session('message') }}
            </div>
        @endif

        <form wire:submit.prevent="save" class="space-y-10">

            <!-- Identität -->
            <section class="bg-black/40 border {{ $agentId === 1 ? 'border-primary shadow-[0_0_20px_rgba(197,160,89,0.15)]' : 'border-gray-800/60 shadow-[0_0_20px_rgba(0,0,0,0.3)]' }} rounded-3xl p-6 sm:p-8 backdrop-blur-md relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-gray-900/50 to-transparent pointer-events-none"></div>
                
                <div class="relative z-10 flex items-center justify-between border-b border-gray-800/80 pb-4 mb-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-3 font-mono uppercase tracking-widest">
                            <div class="p-2 rounded-lg bg-primary/20 text-primary border border-primary/30 shadow-[0_0_10px_rgba(197,160,89,0.2)]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z" clip-rule="evenodd" /></svg>
                            </div>
                            1. Identität & Rolle
                        </h3>
                        <p class="text-xs text-gray-500">Wer ist dieser Agent und was ist sein Hauptzweck (System Prompt)?</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-black tracking-widest uppercase transition-colors duration-300 drop-shadow-md {{ $is_active ? 'text-emerald-500' : 'text-red-500' }}">
                            {{ $is_active ? 'Aktiv' : 'Inaktiv' }}
                        </span>
                        <label class="relative inline-flex items-center cursor-pointer" title="Agent aktivieren/deaktivieren">
                            <input type="checkbox" wire:model.live="is_active" class="sr-only peer">
                            <div class="w-11 h-6 bg-red-500/80 border border-red-500 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-red-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500/80 peer-checked:border-emerald-500 peer-checked:shadow-[0_0_15px_rgba(16,185,129,0.4)] shadow-[0_0_15px_rgba(239,68,68,0.4)]"></div>
                        </label>
                    </div>
                </div>

                <div class="relative z-10 space-y-8">
                    
                    <!-- Avatar Upload -->
                    <div class="flex flex-col sm:flex-row gap-6 items-start">
                        <div class="shrink-0">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Profilbild</label>
                            <div class="relative group cursor-pointer w-32 h-32 rounded-2xl border-2 border-dashed {{ $profile_picture || $existing_profile_picture ? 'border-primary/50' : 'border-gray-700' }} hover:border-primary transition-colors bg-gray-900/50 flex items-center justify-center overflow-hidden shadow-[0_0_20px_rgba(0,0,0,0.5)]">
                                
                                @if ($profile_picture)
                                    <img src="{{ $profile_picture->temporaryUrl() }}" class="w-full h-full object-cover">
                                @elseif ($existing_profile_picture)
                                    <img src="{{ Storage::url($existing_profile_picture) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="text-center text-gray-500 group-hover:text-primary transition-colors flex flex-col items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 mb-2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>
                                        <span class="text-[10px] font-mono uppercase tracking-wider">Upload</span>
                                    </div>
                                @endif

                                <input type="file" wire:model="profile_picture" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept="image/png, image/jpeg, image/webp">

                                <!-- Loading Overlay -->
                                <div wire:loading wire:target="profile_picture" class="absolute inset-0 bg-black/80 flex items-center justify-center">
                                    <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </div>
                            </div>
                            @if($existing_profile_picture || $profile_picture)
                                <button type="button" wire:click="deleteProfilePicture" class="mt-2 text-[10px] text-red-500 hover:text-red-400 font-mono uppercase tracking-widest w-full text-center hover:underline">Bild entfernen</button>
                            @endif
                            @error('profile_picture') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Name & Desc -->
                        <div class="flex-1 space-y-6 w-full">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 flex items-center gap-2">Name des Agenten <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model.defer="name" required placeholder="z.B. IT Administrator" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-3 font-mono transition-all">
                                    @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 flex items-center gap-2">Aktivierungswort <span class="text-gray-600">(Wake Word)</span></label>
                                    <input type="text" wire:model.defer="wake_word" placeholder="z.B. Computer oder den echten Namen" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-3 font-mono transition-all">
                                    <p class="text-[10px] text-gray-500 mt-1 font-mono">Bleibt dieses Feld leer, wird automatisch der <span class="text-primary font-bold">Name des Agenten</span> gewählt.</p>
                                    @error('wake_word') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 w-full">
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 flex items-center gap-2">Aufgaben-Rolle</label>
                                    <div class="relative">
                                        <select wire:model.live="ai_role_id" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-3 pr-10 font-mono transition-all appearance-none cursor-pointer">
                                            <option value="">-- Keine spezifische Rolle --</option>
                                            @foreach($aiRoles as $role)
                                                <option value="{{ $role->id }}" class="bg-gray-900 text-gray-300">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </div>
                                    </div>
                                    @error('ai_role_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Rollen-Kurzbeschreibung (für UI)</label>
                                    <input type="text" wire:model.defer="role_description" placeholder="Wird automatisch befüllt..." class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-white sm:text-sm p-3 font-mono transition-all">
                                </div>
                            </div>
                            <!-- Personality Presets -->
                            <div class="mt-4 mb-6 pt-4">
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-indigo-400"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6.711 11.23a.75.75 0 011.026-.201c.712.485 1.54.721 2.378.721 1.401 0 2.721-.63 3.559-1.63a.75.75 0 111.166.96c-1.09 1.302-2.8 2.17-4.725 2.17-1.077 0-2.146-.307-3.078-.94a.75.75 0 01-.326-1.08z" /></svg>
                                    Persönlichkeits-Voreinstellungen (Presets)
                                </label>
                                <p class="text-[10px] text-gray-500 mb-4 font-mono">Ein Klick überschreibt den System-Prompt und die Temperatur passend zum jeweiligen Modus.</p>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <!-- CEO Mode -->
                                    <button type="button" wire:click="applyPreset('ceo')" class="group relative bg-black/40 border hover:border-cyan-500/50 hover:bg-cyan-900/10 rounded-xl p-4 text-left transition-all duration-300 shadow-[0_0_10px_rgba(0,0,0,0.5)] {{ $activePreset === 'ceo' ? 'border-cyan-500 bg-cyan-900/20 shadow-[0_0_15px_rgba(6,182,212,0.3)]' : 'border-gray-700/50' }}">
                                        @if($activePreset === 'ceo')
                                            <div class="absolute inset-0 border-2 border-cyan-400 rounded-xl rounded-xl opacity-50 pointer-events-none"></div>
                                        @endif
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="text-xl relative z-10">🚀</div>
                                            <h5 class="text-sm font-bold uppercase tracking-widest transition-colors relative z-10 {{ $activePreset === 'ceo' ? 'text-cyan-400' : 'text-gray-200 group-hover:text-cyan-400' }}">CEO-Modus</h5>
                                        </div>
                                        <p class="text-[10px] font-mono leading-relaxed transition-colors relative z-10 {{ $activePreset === 'ceo' ? 'text-cyan-100' : 'text-gray-400 group-hover:text-gray-300' }}">Absolut effizient. Fokus auf Skalierung und maximalen Geschäftserfolg. Kein Smalltalk, harte Fakten.</p>
                                    </button>
                                    
                                    <!-- Colleague Mode -->
                                    <button type="button" wire:click="applyPreset('colleague')" class="group relative bg-black/40 border hover:border-emerald-500/50 hover:bg-emerald-900/10 rounded-xl p-4 text-left transition-all duration-300 shadow-[0_0_10px_rgba(0,0,0,0.5)] {{ $activePreset === 'colleague' ? 'border-emerald-500 bg-emerald-900/20 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'border-gray-700/50' }}">
                                        @if($activePreset === 'colleague')
                                            <div class="absolute inset-0 border-2 border-emerald-400 rounded-xl opacity-50 pointer-events-none"></div>
                                        @endif
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="text-xl relative z-10">⚖️</div>
                                            <h5 class="text-sm font-bold uppercase tracking-widest transition-colors relative z-10 {{ $activePreset === 'colleague' ? 'text-emerald-400' : 'text-gray-200 group-hover:text-emerald-400' }}">Kollege</h5>
                                        </div>
                                        <p class="text-[10px] font-mono leading-relaxed transition-colors relative z-10 {{ $activePreset === 'colleague' ? 'text-emerald-100' : 'text-gray-400 group-hover:text-gray-300' }}">Professioneller Erfolg mit menschlicher Note. Zielorientiert, aber charmant und gelegentlich humorvoll.</p>
                                    </button>
                                    
                                    <!-- Chill Mode -->
                                    <button type="button" wire:click="applyPreset('chill')" class="group relative bg-black/40 border hover:border-amber-500/50 hover:bg-amber-900/10 rounded-xl p-4 text-left transition-all duration-300 shadow-[0_0_10px_rgba(0,0,0,0.5)] {{ $activePreset === 'chill' ? 'border-amber-500 bg-amber-900/20 shadow-[0_0_15px_rgba(245,158,11,0.3)]' : 'border-gray-700/50' }}">
                                        @if($activePreset === 'chill')
                                            <div class="absolute inset-0 border-2 border-amber-400 rounded-xl opacity-50 pointer-events-none"></div>
                                        @endif
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="text-xl relative z-10">🛋️</div>
                                            <h5 class="text-sm font-bold uppercase tracking-widest transition-colors relative z-10 {{ $activePreset === 'chill' ? 'text-amber-400' : 'text-gray-200 group-hover:text-amber-400' }}">Feierabend</h5>
                                        </div>
                                        <p class="text-[10px] font-mono leading-relaxed transition-colors relative z-10 {{ $activePreset === 'chill' ? 'text-amber-100' : 'text-gray-400 group-hover:text-gray-300' }}">Empathischer Begleiter. Fokus auf Alltag, Familie und unbeschwertes Brainstorming. Absolut stressfrei.</p>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5">Master System-Prompt</label>
                                <textarea wire:model.defer="system_prompt" rows="8" placeholder="Du bist der IT-Admin Bot. Analysiere Fehler präzise und effizient..." class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-primary focus:ring focus:ring-primary/20 text-emerald-400/90 p-4 font-mono text-sm leading-relaxed transition-all resize-y custom-scrollbar"></textarea>
                                <p class="text-[10px] text-gray-500 mt-2 font-mono"><span class="text-primary font-bold">INFO:</span> Dieser Text bildet die Kern-Grundanweisung (Persona), die bei jeder Anfrage als System-Message mitgeschickt wird.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Appearance (Colors & Icons) matrix style -->
                    <div class="border border-gray-800/80 bg-black/30 p-6 rounded-2xl shadow-inner relative {{ $inheritedDept ? 'opacity-80' : '' }}">
                        @if($inheritedDept)
                            <div class="absolute inset-0 bg-black/60 z-20 rounded-2xl flex flex-col items-center justify-center p-6 backdrop-blur-sm">
                                <div class="bg-gray-950 border border-{{ $inheritedDept['color'] }}/50 shadow-[0_0_30px_currentColor] text-{{ $inheritedDept['color'] }} px-8 py-6 rounded-2xl text-center flex flex-col items-center max-w-md ring-1 ring-white/5">
                                    <x-dynamic-component :component="'heroicon-o-' . $inheritedDept['icon']" class="w-10 h-10 mb-3 animate-pulse-slow" />
                                    <h4 class="font-bold text-sm tracking-widest uppercase mb-2">Visuelle Identität Geerbt</h4>
                                    <p class="text-xs font-mono text-gray-300 leading-relaxed">Dieser Agent arbeitet in der Abteilung <strong class="text-white">"{{ $inheritedDept['name'] }}"</strong>.<br>Farbe und Vektor-Symbol werden systemweit zwingend von der Mutter-Abteilung vererbt und können für diesen Agenten nicht individuell überschrieben werden.</p>
                                </div>
                            </div>
                        @endif

                        <h4 class="text-xs font-bold text-gray-300 uppercase tracking-widest mb-6 flex items-center gap-2 border-b border-gray-800/50 pb-2 relative z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-primary"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.5 10a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd" /></svg>
                            Optische Identität (UI Präsentation)
                        </h4>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                            <!-- Color Palette -->
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Aura Farbe wählen</label>
                                <!-- Tailwind Safelist Fix for Dynamic Colors in loops -->
                                <div class="hidden bg-cyan-500 text-cyan-500 border-cyan-500/50 bg-emerald-500 text-emerald-500 border-emerald-500/50 bg-blue-500 text-blue-500 border-blue-500/50 bg-indigo-500 text-indigo-500 border-indigo-500/50 bg-purple-500 text-purple-500 border-purple-500/50 bg-pink-500 text-pink-500 border-pink-500/50 bg-rose-500 text-rose-500 border-rose-500/50 bg-red-500 text-red-500 border-red-500/50 bg-orange-500 text-orange-500 border-orange-500/50 bg-amber-500 text-amber-500 border-amber-500/50 bg-yellow-500 text-yellow-500 border-yellow-500/50 bg-green-500 text-green-500 border-green-500/50 bg-sky-500 text-sky-500 border-sky-500/50"></div>
                                <div class="grid grid-cols-8 gap-2">
                                    @foreach($availableColors as $col)
                                        <button type="button" wire:click="$set('color', '{{ $col }}')" 
                                            class="w-8 h-8 rounded-full border-2 transition-all flex items-center justify-center relative group
                                            {{ $color === $col ? 'border-white scale-110 shadow-[0_0_15px_currentColor] z-10' : 'border-transparent hover:scale-110 hover:border-gray-500' }}
                                            bg-{{ $col }} text-{{ $col }}"
                                            title="{{ $col }}">
                                            @if($color === $col)
                                                <div class="w-3 h-3 bg-white rounded-full"></div>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                                @error('color') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Heroicons Selection -->
                            <div>
                                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Vektor Symbol</label>
                                <div class="grid grid-cols-8 gap-2">
                                    @foreach($availableIcons as $ico)
                                        <button type="button" wire:click="$set('icon', '{{ $ico }}')" 
                                            class="w-10 h-10 rounded-xl border transition-all flex items-center justify-center
                                            {{ $icon === $ico ? 'border-primary bg-primary/20 text-primary shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'border-gray-800 bg-gray-900/50 text-gray-500 hover:border-gray-600 hover:text-gray-300' }}"
                                            title="{{ $ico }}">
                                            <!-- Dynamically render heroicon out of standard set -->
                                            <x-dynamic-component :component="'heroicon-o-' . $ico" class="w-6 h-6" />
                                        </button>
                                    @endforeach
                                </div>
                                <p class="text-[10px] text-gray-500 mt-3 font-mono">Wähle ein technisches Icon für Logs, Gitter und Buttons.</p>
                                @error('icon') <span class="text-red-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gehirn (LLM Settings) -->
            <section class="bg-black/40 border border-gray-800/60 rounded-3xl p-6 sm:p-8 backdrop-blur-md relative overflow-hidden shadow-[0_0_20px_rgba(0,0,0,0.3)]">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/10 to-transparent pointer-events-none"></div>

                <div class="relative z-10 flex items-center justify-between border-b border-gray-800/80 pb-4 mb-8">
                    <div>
                        <h3 class="text-xl font-bold text-white mb-2 flex items-center gap-3 font-mono uppercase tracking-widest">
                            <div class="p-2 rounded-lg bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 shadow-[0_0_10px_rgba(99,102,241,0.2)]">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M11.47 3.841a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.061l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 101.061 1.06l8.69-8.689z" /><path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.432z" /></svg>
                            </div>
                            2. Neuronales Netz (Modell)
                        </h3>
                        <p class="text-xs text-gray-500">Welches LLM-Gehirn steuert diesen Agenten?</p>
                    </div>
                </div>

                <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Modell Select -->
                    <div x-data="{ selectedModel: $wire.entangle('model'), details: @js($modelDetails) }">
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center justify-between">
                            Künstliches Intelligenz Modell <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select x-model="selectedModel" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 text-white sm:text-sm p-3 font-mono transition-all appearance-none cursor-pointer">
                                @foreach($availableModels as $key => $label)
                                    <option value="{{ $key }}" class="bg-gray-900 text-gray-300">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                        
                        <!-- Model Details Alpine Render -->
                        <div x-show="details[selectedModel]" x-transition class="mt-4 p-4 bg-indigo-900/10 border border-indigo-500/20 rounded-xl shadow-[0_0_15px_rgba(99,102,241,0.05)] text-xs font-mono" style="display: none;">
                            <div class="grid grid-cols-1 gap-3">
                                <div><span class="text-indigo-500/50 uppercase tracking-widest text-[9px] block mb-1">Typische Anwendungsbeispiele</span> 
                                    <ul class="text-indigo-200 list-disc list-inside space-y-1">
                                        <template x-for="(usecase, index) in details[selectedModel]?.use_cases" :key="index">
                                            <li x-text="usecase"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="grid grid-cols-2 gap-3 border-t border-indigo-500/20 pt-3 mt-1">
                                    <div><span class="text-indigo-500/50 uppercase tracking-widest text-[9px] block mb-0.5">Kontextgröße</span> <span x-text="details[selectedModel]?.context" class="text-indigo-200"></span></div>
                                    <div><span class="text-indigo-500/50 uppercase tracking-widest text-[9px] block mb-0.5">Lizenz</span> <span x-text="details[selectedModel]?.license" class="text-indigo-200"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Voice Select -->
                    <div x-data="{ 
                        provider: $wire.entangle('tts_provider'), 
                        voice: $wire.entangle('tts_voice'),
                        voicesMap: @js($ttsVoices) 
                    }">
                        <div class="flex items-center justify-between mb-4">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest">
                                Sprachmodell (TTS) Konfiguration
                            </label>
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-black tracking-widest uppercase transition-colors duration-300 {{ $tts_enabled ? 'text-indigo-400' : 'text-gray-500' }}">
                                    {{ $tts_enabled ? 'Aktiviert' : 'Deaktiviert' }}
                                </span>
                                <label class="relative inline-flex items-center cursor-pointer" title="TTS aktivieren/deaktivieren">
                                    <input type="checkbox" wire:model.live="tts_enabled" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-700/80 border border-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-400 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500/80 peer-checked:border-indigo-500 peer-checked:shadow-[0_0_15px_rgba(99,102,241,0.4)] shadow-inner transition-colors"></div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-4 transition-all duration-300" x-show="$wire.tts_enabled" x-collapse x-cloak>
                            <!-- Provider -->
                            <div class="relative">
                                <select x-model="provider" @change="voice = Object.keys(voicesMap[provider] || {})[0] || ''" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 text-white sm:text-sm p-3 font-mono transition-all appearance-none cursor-pointer">
                                    @foreach($ttsProviders as $key => $label)
                                        <option value="{{ $key }}" class="bg-gray-900 text-gray-300 {{ $key === 'elevenlabs' ? 'text-red-400' : '' }}">{{ $label }}{{ $key === 'elevenlabs' ? ' - OFFLINE (Quota)' : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>

                            <!-- Voice (Select for predefined, Text for Toni) -->
                            <div class="relative" x-show="provider !== 'none'">
                                <template x-if="provider !== 'toni_xttsv2'">
                                    <select x-model="voice" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 text-white sm:text-sm p-3 pl-10 font-mono transition-all appearance-none cursor-pointer">
                                        <option value="" disabled>-- Stimme wählen --</option>
                                        <template x-if="voicesMap[provider]">
                                            <template x-for="(vLabel, vKey) in voicesMap[provider]" :key="vKey">
                                                <option :value="vKey" x-text="vLabel" class="bg-gray-900 text-gray-300"></option>
                                            </template>
                                        </template>
                                    </select>
                                </template>
                                
                                <template x-if="provider === 'toni_xttsv2'">
                                    <input type="text" x-model="voice" placeholder="Voice Key (z.B. voice_bab36a97)" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 text-white sm:text-sm p-3 pl-10 font-mono transition-all">
                                </template>

                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 001.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06zM18.584 5.106a.75.75 0 011.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 11-1.06-1.06 8.25 8.25 0 000-11.668.75.75 0 010-1.06z" /><path d="M15.932 7.757a.75.75 0 011.061 0 6 6 0 010 8.486.75.75 0 01-1.06-1.061 4.5 4.5 0 000-6.364.75.75 0 010-1.06z" /></svg>
                                </div>
                                
                                <template x-if="provider !== 'toni_xttsv2'">
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- Custom URL and Speed (Only for Local/XTTS) -->
                            <div x-show="provider === 'toni_xttsv2'" class="space-y-4 pt-2 border-t border-gray-800/80 mt-2" x-cloak>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-1">API Endpoint URL (Optional)</label>
                                    <input type="url" wire:model.defer="tts_api_url" placeholder="http://192.168.188.32:8000" class="w-full bg-black/40 border border-gray-700/50 rounded-xl shadow-inner focus:border-indigo-500 focus:ring focus:ring-indigo-500/20 text-white sm:text-sm p-3 font-mono transition-all">
                                    <p class="text-[9px] text-gray-500 mt-1 font-mono">Das System ergänzt '/api/tts' automatisch. Leer lassen für Fallback aus .env (TONI_AI_URL).</p>
                                    @error('tts_api_url') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 flex items-center justify-between">
                                        <span>Sprechgeschwindigkeit (Speed)</span>
                                        <span class="text-indigo-400" x-text="(parseFloat($wire.tts_speed)).toFixed(1) + 'x'"></span>
                                    </label>
                                    <input type="range" wire:model.live="tts_speed" min="0.1" max="3.0" step="0.1" class="w-full accent-indigo-500 bg-gray-800 rounded-lg appearance-none cursor-pointer h-2">
                                    <div class="flex justify-between text-[9px] text-gray-500 font-mono mt-1">
                                        <span>0.1 (Sehr langsam)</span>
                                        <span>1.0 (Normal)</span>
                                        <span>3.0 (Sehr schnell)</span>
                                    </div>
                                    @error('tts_speed') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <!-- Offline Hint entfernt -->
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex justify-between pt-6 border-t border-gray-800/80 items-center">
                <button type="button" wire:click="cancel" class="text-gray-400 hover:text-white transition-colors font-mono text-sm uppercase tracking-widest px-4 py-2 rounded-lg hover:bg-gray-900">Abbrechen</button>
                <button type="submit" class="bg-primary hover:bg-primary/80 text-gray-900 font-bold py-3.5 px-10 rounded-xl shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:shadow-[0_0_30px_rgba(197,160,89,0.5)] transition-all font-mono uppercase tracking-widest flex items-center gap-2">
                    <svg wire:loading.remove wire:target="save" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" /></svg>
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-5 w-5 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Agent Speichern
                </button>
            </div>
        </form>
    </div>
</div>
