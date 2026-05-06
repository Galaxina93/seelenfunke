@props(['availableAgents'])

<!-- LOG: AI WIDGET NAVIGATION COMPONENT RENDERED -->
{{--<script>console.log('AI Widget Navigation component is rendering via Laravel!');</script>--}}
<div x-data="{ mobileMenuOpen: false }" @click.outside="if(window.innerWidth < 768) mobileMenuOpen = false" class="pointer-events-auto relative">

    <!-- Mobile Trigger Button -->
    <div class="md:hidden flex justify-end">
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="w-10 h-10 bg-gray-900/80 border border-gray-700 rounded-xl flex items-center justify-center hover:text-white hover:border-emerald-500 backdrop-blur-md shadow-glow text-gray-400 transition-colors">
            <!-- Hamburger Icon (if closed) -->
            <svg x-show="!mobileMenuOpen" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <!-- Close Icon (if open) -->
            <svg x-show="mobileMenuOpen" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation Bar / Dropdown -->
    <div :class="mobileMenuOpen ? 'flex flex-col absolute right-0 top-12 mt-2 w-48 bg-gray-900/95 border border-gray-700 rounded-xl shadow-2xl backdrop-blur-xl p-2 z-[100]' : 'hidden md:flex md:flex-row md:items-center md:gap-2 md:static md:bg-transparent md:border-0 md:p-0 md:shadow-none'"
         class="gap-2 transition-transform md:hover:scale-105">

         <select x-model="activeAgentId" @change="$wire.set('agentId', activeAgentId)" class="px-2 py-1.5 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md bg-black text-white border-gray-700 hover:text-white focus:outline-none focus:border-emerald-500 appearance-none shadow-[0_0_15px_rgba(0,0,0,0.5)] w-full md:w-auto mb-2 md:mb-0">
             <option value="">(Agent wählen)</option>
             @foreach($availableAgents as $agentOpt)
                 <option value="{{ $agentOpt->id }}" class="bg-black text-white">{{ $agentOpt->name }}</option>
             @endforeach
         </select>

         <select x-model="currentChatSessionId" @change="$wire.set('currentChatSessionId', currentChatSessionId)" class="px-2 py-1.5 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md bg-black text-white border-gray-700 hover:text-white focus:outline-none focus:border-[var(--theme-color)] appearance-none shadow-[0_0_15px_rgba(0,0,0,0.5)] w-full md:w-auto mb-2 md:mb-0 max-w-[150px]">
             @if($this->chatSessions() && $this->chatSessions()->count() > 0)
                 @foreach($this->chatSessions() as $chat)
                     <option value="{{ $chat->id }}" class="bg-black text-white">{{ \Illuminate\Support\Str::limit($chat->title, 20) }}</option>
                 @endforeach
             @else
                 <option value="">(Neuer Chat)</option>
             @endif
         </select>

         <select x-model="activeMapStyle" class="px-2 py-1.5 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md bg-black text-white border-emerald-900/50 hover:text-white focus:outline-none focus:border-[var(--theme-color)] appearance-none shadow-[0_0_15px_rgba(0,0,0,0.5)] w-full md:w-auto mb-2 md:mb-0 max-w-[120px]">
             <template x-for="style in mapStyles" :key="style.id">
                 <option :value="style.id" x-text="style.name" class="bg-black text-white"></option>
             </template>
         </select>

         <button @click="toggleLiveMode(); if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="isLiveMode ? 'text-yellow-400 border-yellow-500/50 bg-yellow-900/30 hover:bg-yellow-800 shadow-[0_0_15px_rgba(250,204,21,0.5)] animate-pulse' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd" /></svg>
             Live Mode
         </button>

         <button x-show="isMapFocus" x-transition @click="isFlightDataActive = !isFlightDataActive; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="isFlightDataActive ? 'text-cyan-400 border-cyan-500/50 bg-cyan-900/30 hover:bg-cyan-800 shadow-[0_0_15px_rgba(0,240,255,0.5)] animate-pulse' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z" /></svg>
             Livedaten
         </button>

         <button @click="isMapFocus = !isMapFocus; if(isMapFocus) isMapMode = true; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="isMapFocus ? 'text-emerald-400 border-emerald-500/50 bg-emerald-900/30 hover:bg-emerald-800 shadow-[0_0_15px_rgba(16,185,129,0.5)] animate-pulse' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M11.54 22.351l.07.04.028.016a.76.76 0 00.723 0l.028-.015.071-.041a16.975 16.975 0 001.144-.742 19.58 19.58 0 002.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 00-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 002.682 2.282 16.975 16.975 0 001.145.742zM12 13.5a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" /></svg>
             Map Kontrolle
         </button>

         <button @click="$dispatch('ai-toggle-secret-workspace', {open: !isSecretMode}); if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="isSecretMode ? 'text-red-400 border-red-500/50 bg-red-900/30 hover:bg-red-800 shadow-[0_0_15px_rgba(220,38,38,0.5)] animate-pulse' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" /></svg>
             Top Secret
         </button>

         <button @click="$dispatch('toggle-voice-interruption'); if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="allowVoiceInterruption ? 'text-emerald-400 border-emerald-500/50 bg-emerald-900/30 hover:bg-emerald-800 shadow-[0_0_15px_rgba(16,185,129,0.5)] animate-pulse' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" /></svg>
             KI Unterbrechen
         </button>

         <button @click="showDebugLog = !showDebugLog; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-emerald-300 border-emerald-500/50 bg-emerald-900/30 hover:bg-emerald-800 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" /></svg>
             Log
         </button>




    </div>
</div>
