<div class="bg-gray-900/50 border border-gray-800 rounded-2xl overflow-hidden shadow-lg" x-data="{ open: true }">
    <button @click="open = !open" class="w-full flex items-center justify-between p-5 bg-gray-950/50 hover:bg-gray-900 transition-colors border-b border-gray-800">
        <div class="flex items-center gap-3">
            <span class="w-6 h-6 rounded-lg bg-gray-800 text-blue-400 flex items-center justify-center text-[10px] font-black shadow-inner">3</span>
            <h4 class="text-xs font-black text-white uppercase tracking-widest drop-shadow-[0_0_5px_rgba(59,130,246,0.5)]">Overlay (Gravur)</h4>
        </div>
        <div class="flex items-center gap-4">
            <span @click.stop="resetOverlay()" class="text-[9px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-800 hover:border-gray-600">Alle Reset</span>
            <svg class="w-5 h-5 text-gray-500 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        </div>
    </button>

    <div x-show="open" x-collapse class="p-6 space-y-8">

        <div x-show="configSettings.overlay_type === 'cylinder'" class="bg-blue-900/10 border border-blue-500/20 rounded-xl p-4 flex gap-4 items-start shadow-inner">
            <div class="bg-blue-500/20 p-1.5 rounded-lg text-blue-400 shrink-0"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></div>
            <div>
                <h5 class="text-[10px] font-black text-blue-300 uppercase tracking-widest mb-1 drop-shadow-[0_0_5px_currentColor]">Auto-Fit Aktiviert</h5>
                <p class="text-[9px] text-gray-400 leading-relaxed">Das Modell wird via Raycasting in Echtzeit abgescannt. Das Overlay passt sich exakt der Form an.</p>
            </div>
        </div>

        {{-- ================= VORDERSEITE ================= --}}
        <div x-show="activeSide === 'front'">
            <div class="flex items-center gap-3 mb-6">
                <span class="px-3 py-1 bg-primary/10 text-primary border border-primary/30 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-[0_0_8px_rgba(197,160,89,0.3)]">Bearbeitung: Vorderseite</span>
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Größe %</label>
                    <input type="range" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" min="10" max="200" step="0.1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_scale" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_scale=100; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="w-full h-px bg-gray-800 my-2"></div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Pos X</label>
                    <input type="range" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_pos_x" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_pos_x=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Pos Y</label>
                    <input type="range" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_pos_y" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_pos_y=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Pos Z</label>
                    <input type="range" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_pos_z" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_pos_z=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="w-full h-px bg-gray-800 my-2"></div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Rot X°</label>
                    <input type="range" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_rot_x" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_rot_x=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Rot Y°</label>
                    <input type="range" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_rot_y" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_rot_y=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-blue-400">Rot Z°</label>
                    <input type="range" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-blue-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.engraving_rot_z" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-blue-500 outline-none shadow-inner">
                    <button @click="configSettings.engraving_rot_z=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>
            </div>
        </div>

        {{-- ================= RÜCKSEITE (Die exakt gleichen Regler, aber für die Variablen "back_engraving_...") ================= --}}
        <div x-show="activeSide === 'back'" style="display: none;">
            <div class="flex items-center gap-3 mb-6">
                <span class="px-3 py-1 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded-lg text-[9px] font-black uppercase tracking-widest shadow-[0_0_8px_rgba(168,85,247,0.3)]">Bearbeitung: Rückseite</span>
            </div>

            <div class="space-y-5">
                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Größe %</label>
                    <input type="range" x-model.number="configSettings.back_engraving_scale" @input="applyModelTransforms()" min="10" max="200" step="0.1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_scale" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_scale=100; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="w-full h-px bg-gray-800 my-2"></div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Pos X</label>
                    <input type="range" x-model.number="configSettings.back_engraving_pos_x" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_pos_x" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_pos_x=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Pos Y</label>
                    <input type="range" x-model.number="configSettings.back_engraving_pos_y" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_pos_y" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_pos_y=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Pos Z</label>
                    <input type="range" x-model.number="configSettings.back_engraving_pos_z" @input="applyModelTransforms()" min="-100" max="100" step="0.1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_pos_z" @input="applyModelTransforms()" step="0.1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_pos_z=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="w-full h-px bg-gray-800 my-2"></div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Rot X°</label>
                    <input type="range" x-model.number="configSettings.back_engraving_rot_x" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_rot_x" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_rot_x=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Rot Y°</label>
                    <input type="range" x-model.number="configSettings.back_engraving_rot_y" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_rot_y" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_rot_y=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>

                <div class="flex items-center gap-4">
                    <label class="w-20 sm:w-24 text-[10px] font-black uppercase tracking-widest text-purple-400">Rot Z°</label>
                    <input type="range" x-model.number="configSettings.back_engraving_rot_z" @input="applyModelTransforms()" min="-180" max="180" step="1" class="flex-1 accent-purple-500 h-1.5 bg-gray-800 rounded-lg appearance-none cursor-pointer">
                    <input type="number" x-model.number="configSettings.back_engraving_rot_z" @input="applyModelTransforms()" step="1" class="w-16 bg-gray-950 border border-gray-800 rounded-lg px-2 py-1.5 text-xs text-center text-white focus:border-purple-500 outline-none shadow-inner">
                    <button @click="configSettings.back_engraving_rot_z=0; applyModelTransforms()" class="text-gray-500 hover:text-white p-1" title="Reset"><x-heroicon-s-arrow-path class="w-4 h-4"/></button>
                </div>
            </div>
        </div>

    </div>
</div>
