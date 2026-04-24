@props(['availableAgents'])

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
         class="gap-2 transition-transform md:hover:scale-105" x-cloak>
         
         <select wire:model.live="agentId" class="px-2 py-1.5 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md bg-gray-900/50 text-gray-400 border-gray-700 hover:text-gray-300 focus:outline-none focus:border-emerald-500 appearance-none shadow-[0_0_15px_rgba(0,0,0,0.5)] w-full md:w-auto mb-2 md:mb-0">
             <option value="">(Agent wählen)</option>
             @foreach($availableAgents as $agentOpt)
                 <option value="{{ $agentOpt->id }}">{{ $agentOpt->name }}</option>
             @endforeach
         </select>
         
         <button @click="showTasks = !showTasks; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-cyan-300 border-cyan-500/50 bg-cyan-900/30 hover:bg-cyan-800 shadow-[0_0_15px_rgba(6,182,212,0.3)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M4 3a2 2 0 100 4h12a2 2 0 100-4H4z" /><path fill-rule="evenodd" d="M3 8h14v7a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm5 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" clip-rule="evenodd" /></svg> 
             Aufgaben
         </button>

         <button @click="showDebugLog = !showDebugLog; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-emerald-300 border-emerald-500/50 bg-emerald-900/30 hover:bg-emerald-800 shadow-[0_0_15px_rgba(16,185,129,0.3)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm3.293 1.293a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414-1.414L7.586 10 5.293 7.707a1 1 0 010-1.414zM11 12a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd" /></svg>
             Log
         </button>
         
         <button @click="showFiles = !showFiles; if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-indigo-300 border-indigo-500/50 bg-indigo-900/30 hover:bg-indigo-800 shadow-[0_0_15px_rgba(99,102,241,0.3)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
             Dateien
         </button>
         
         <button @click="$wire.pauseAllTasks(); if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-yellow-300 border-yellow-500/50 bg-yellow-900/30 hover:bg-yellow-800 shadow-[0_0_15px_rgba(234,179,8,0.3)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
             Pause
         </button>

         <button @click="stopSpeech(); if(window.innerWidth < 768) mobileMenuOpen = false;" class="px-3 py-2 border rounded-lg text-[10px] font-black uppercase tracking-widest transition-all backdrop-blur-md flex items-center gap-2 w-full md:w-auto justify-start md:justify-center"
             :class="(isOutputActive()) ? 'text-red-300 border-red-500/50 bg-red-900/30 hover:bg-red-800 shadow-[0_0_15px_rgba(239,68,68,0.5)]' : 'text-gray-500 border-gray-700 bg-gray-900/50 hover:text-gray-300 hover:bg-gray-800'">
             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM6.75 6.75a.75.75 0 01.75-.75h5a.75.75 0 01.75.75v5a.75.75 0 01-.75.75h-5a.75.75 0 01-.75-.75v-5z" clip-rule="evenodd" /></svg>
             Stop
         </button>
    </div>
</div>
