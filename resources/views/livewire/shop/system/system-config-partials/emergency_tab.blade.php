<div x-show="activeTab === 'emergency'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
    <div class="bg-[#0a0a0a] border border-red-900/30 rounded-2xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
        
        <!-- Background Element -->
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-red-900/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex items-start justify-between mb-8">
            <div>
                <h2 class="text-xl sm:text-2xl font-serif font-bold text-red-400">Notfall-Handbuch & Nachlass</h2>
                <p class="text-xs sm:text-sm text-gray-400 mt-2 max-w-2xl">
                    Hinterlege hier die wichtigsten Zugangsdaten und Ansprechpartner für den Ernstfall. 
                    Diese Informationen fließen zusammen mit allen aktuell laufenden Abos und Verträgen aus der Buchhaltung in ein zentrales Notfall-PDF.
                </p>
            </div>
            
            <button wire:click="generateEmergencyPdf" class="flex-shrink-0 bg-red-500/10 border border-red-500/30 hover:bg-red-500 hover:text-white text-red-400 font-bold py-3 px-6 rounded-xl text-sm transition-all shadow-[0_0_15px_rgba(239,68,68,0.15)] flex items-center gap-2 group">
                <svg class="w-5 h-5 group-hover:animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                PDF GENERIEREN
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            
            <div class="space-y-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500 border-b border-gray-800 pb-2">Zugänge & Passwörter</h3>
                
                <div>
                    <label class="{{ $labelClass }}">Ort der KeePass-Datenbank</label>
                    <input type="text" wire:model.defer="settings.emergency_keepass_location" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_keepass_location'] }}</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Ort des Master-Passworts</label>
                    <input type="text" wire:model.defer="settings.emergency_master_password_location" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_master_password_location'] }}</p>
                </div>
                
                <div>
                    <label class="{{ $labelClass }}">Hardware PINs (Handy/PC)</label>
                    <input type="text" wire:model.defer="settings.emergency_hardware_pins" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_hardware_pins'] }}</p>
                </div>
            </div>

            <div class="space-y-6">
                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500 border-b border-gray-800 pb-2">Notfall-Kontakte</h3>
                
                <div>
                    <label class="{{ $labelClass }}">Notar / Nachlassverwalter</label>
                    <input type="text" wire:model.defer="settings.emergency_contact_notary" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_contact_notary'] }}</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Steuerberater</label>
                    <input type="text" wire:model.defer="settings.emergency_contact_tax_advisor" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_contact_tax_advisor'] }}</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Wichtigster privater Kontakt</label>
                    <input type="text" wire:model.defer="settings.emergency_contact_family" class="{{ $inputClass }} border-gray-800 focus:border-red-500/50 focus:ring-red-500/30">
                    <p class="text-[10px] text-gray-500 mt-1">{{ $this->infoTexts['emergency_contact_family'] }}</p>
                </div>
            </div>
            
        </div>
        
        <!-- Master Password Block -->
        <div class="mt-8 pt-8 border-t border-red-900/30">
            <h3 class="text-sm font-black uppercase tracking-widest text-amber-500 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Digitaler Notfall-Zugang
            </h3>
            
            <div class="p-6 bg-amber-500/5 border border-amber-500/30 rounded-xl shadow-[0_0_20px_rgba(245,158,11,0.1)] relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-r from-amber-500/0 via-amber-500/10 to-amber-500/0 opacity-0 group-hover:opacity-100 transition-opacity duration-1000 -translate-x-full group-hover:translate-x-full"></div>
                
                <div class="max-w-2xl">
                    <label class="block text-sm font-medium text-amber-500 mb-2">Master-Passwort für /notfall</label>
                    <input type="password" wire:model.defer="settings.emergency_master_password" placeholder="Neues Master-Passwort eingeben (wird verschlüsselt gespeichert)" 
                           class="w-full bg-black/50 border border-amber-500/50 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-all placeholder-gray-600">
                    <p class="text-[11px] text-amber-500/70 mt-2">
                        {{ $this->infoTexts['emergency_master_password'] }}<br>
                        Mit diesem Passwort erhalten deine Notfallkontakte exklusiven Zugriff auf den digitalen Trauerfall-Assistenten. Das Passwort wird beim Speichern sicher gehasht.
                    </p>

                    <div class="mt-6">
                        <label class="block text-xs font-medium text-amber-500/70 mb-1">Zugangs-Link</label>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="{{ url('/notfall') }}" id="emergency-url"
                                   class="w-full bg-black/30 border border-amber-500/30 text-amber-500/80 rounded-lg px-3 py-2 text-sm focus:outline-none cursor-text select-all">
                            
                            <!-- Copy Button -->
                            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('emergency-url').value); const el=this.querySelector('svg'); el.classList.add('text-emerald-500'); setTimeout(()=>el.classList.remove('text-emerald-500'), 1500);" 
                                    class="p-2.5 bg-black/50 border border-amber-500/30 hover:border-amber-500 hover:bg-amber-500/10 text-amber-500 rounded-lg transition-all" title="Link kopieren">
                                <svg class="w-4 h-4 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                            
                            <!-- Open Button -->
                            <a href="{{ url('/notfall') }}" target="_blank" 
                               class="p-2.5 bg-black/50 border border-amber-500/30 hover:border-amber-500 hover:bg-amber-500/10 text-amber-500 rounded-lg transition-all" title="Link in neuem Tab öffnen">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>
