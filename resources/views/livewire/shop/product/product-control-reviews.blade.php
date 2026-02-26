<div class="space-y-6 sm:space-y-8 pb-20 animate-fade-in-up font-sans antialiased text-gray-300">

    {{-- Header Bereich --}}
    <div class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6">
        <div class="text-left w-full xl:w-auto">
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-wide">Review Moderation</h1>
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 mt-2">Prüfe User Generated Content bevor er live geht.</p>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 w-full xl:w-auto items-stretch sm:items-center">
            {{-- Suchfeld --}}
            <div class="relative w-full sm:w-72 group">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen..."
                       class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-xl text-sm text-white focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none placeholder-gray-600 transition-all">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="bi bi-search text-gray-500 group-focus-within:text-primary transition-colors"></i>
                </div>
            </div>

            {{-- Filter Tabs --}}
            <div class="flex bg-gray-950 p-1.5 rounded-xl border border-gray-800 shadow-inner w-full sm:w-auto overflow-x-auto no-scrollbar shrink-0">
                <button wire:click="$set('filterStatus', 'pending')"
                        class="flex-1 sm:flex-none px-5 py-2.5 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'pending' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30 shadow-[0_0_10px_rgba(59,130,246,0.2)]' : 'text-gray-500 hover:text-white border border-transparent' }}">Ausstehend</button>

                <button wire:click="$set('filterStatus', 'approved')"
                        class="flex-1 sm:flex-none px-5 py-2.5 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'approved' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shadow-[0_0_10px_rgba(16,185,129,0.2)]' : 'text-gray-500 hover:text-white border border-transparent' }}">Freigegeben</button>

                <button wire:click="$set('filterStatus', 'rejected')"
                        class="flex-1 sm:flex-none px-5 py-2.5 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'rejected' ? 'bg-red-500/20 text-red-400 border border-red-500/30 shadow-[0_0_10px_rgba(239,68,68,0.2)]' : 'text-gray-500 hover:text-white border border-transparent' }}">Abgelehnt</button>
            </div>
        </div>
    </div>

    {{-- Session Alerts --}}
    @if(session()->has('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-5 py-4 rounded-xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-inner">
            <i class="bi bi-check-circle text-sm drop-shadow-[0_0_8px_currentColor]"></i> {{ session('success') }}
        </div>
    @endif
    @if(session()->has('warning'))
        <div class="bg-amber-500/10 border border-amber-500/30 text-amber-400 px-5 py-4 rounded-xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-inner">
            <i class="bi bi-shield-exclamation text-sm drop-shadow-[0_0_8px_currentColor]"></i> {{ session('warning') }}
        </div>
    @endif
    @if(session()->has('danger'))
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 px-5 py-4 rounded-xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-inner">
            <i class="bi bi-trash text-sm drop-shadow-[0_0_8px_currentColor]"></i> {{ session('danger') }}
        </div>
    @endif

    {{-- Tabelle --}}
    <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse whitespace-nowrap sm:whitespace-normal min-w-[800px]">
                <thead class="bg-gray-950/50 text-[9px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800 shadow-inner">
                <tr>
                    <th class="px-6 sm:px-8 py-5">Kunde & Produkt</th>
                    <th class="px-6 sm:px-8 py-5">Bewertung</th>
                    <th class="px-6 sm:px-8 py-5">Medien (UGC)</th>
                    <th class="px-6 sm:px-8 py-5 text-center">Status</th>
                    <th class="px-6 sm:px-8 py-5 text-right w-36">Aktion</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                @forelse($reviews as $review)
                    <tr class="hover:bg-gray-800/30 transition-colors group">

                        {{-- Kunde & Produkt --}}
                        <td class="px-6 sm:px-8 py-6 align-top">
                            <div class="font-bold text-white text-sm tracking-wide">{{ $review->customer->first_name }} {{ $review->customer->last_name }}</div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-primary mt-1.5 drop-shadow-[0_0_5px_currentColor] truncate max-w-[150px] sm:max-w-[200px]">{{ $review->product->name ?? 'Unbekannt' }}</div>
                            <div class="text-[9px] font-medium text-gray-500 mt-2">{{ $review->created_at->format('d.m.Y H:i') }}</div>
                        </td>

                        {{-- Text & Sterne --}}
                        <td class="px-6 sm:px-8 py-6 align-top max-w-xs min-w-[250px]">
                            <div class="flex text-primary mb-3 drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-4 w-4 {{ $i <= $review->rating ? 'text-primary' : 'text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </div>
                            @if($review->title)
                                <strong class="text-sm block mb-1.5 text-white tracking-wide">{{ $review->title }}</strong>
                            @endif
                            <p class="text-xs text-gray-400 font-medium leading-relaxed line-clamp-3">{{ $review->content }}</p>
                        </td>

                        {{-- Medien UGC --}}
                        <td class="px-6 sm:px-8 py-6 align-top">
                            @if(!empty($review->media))
                                <div class="flex flex-wrap gap-3">
                                    @foreach($review->media as $media)
                                        @php
                                            $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                            $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                        @endphp
                                        <a href="{{ Storage::url($media) }}" target="_blank" class="w-14 h-14 rounded-xl border border-gray-700 overflow-hidden block relative group/media shadow-inner hover:border-primary transition-colors">
                                            @if($isVideo)
                                                <div class="w-full h-full bg-gray-950 flex items-center justify-center text-gray-400 group-hover/media:text-primary transition-colors">
                                                    <i class="bi bi-play-fill text-2xl drop-shadow-[0_0_8px_currentColor]"></i>
                                                </div>
                                            @else
                                                <img src="{{ Storage::url($media) }}" class="w-full h-full object-cover group-hover/media:scale-110 transition-transform duration-500 opacity-80 group-hover/media:opacity-100">
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-[9px] font-black uppercase tracking-widest text-gray-600 block mt-2">Keine Medien</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-6 sm:px-8 py-6 align-top text-center">
                            @if($review->status === 'approved')
                                <span class="inline-block bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-inner drop-shadow-[0_0_8px_currentColor]">Freigegeben</span>
                            @elseif($review->status === 'rejected')
                                <span class="inline-block bg-red-500/10 border border-red-500/30 text-red-400 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-inner drop-shadow-[0_0_8px_currentColor]">Abgelehnt</span>
                            @else
                                <span class="inline-block bg-blue-500/10 border border-blue-500/30 text-blue-400 text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest shadow-[0_0_15px_rgba(59,130,246,0.2)] animate-pulse">Prüfung offen</span>
                            @endif
                        </td>

                        {{-- Aktion (Buttons nun mit Text, untereinander gestapelt) --}}
                        <td class="px-6 sm:px-8 py-6 align-top text-right">
                            <div class="flex flex-col items-end gap-2">
                                @if($review->status !== 'approved')
                                    <button wire:click="approve('{{ $review->id }}')" class="flex items-center justify-start gap-2.5 w-32 px-3 py-2 bg-gray-950 border border-gray-800 text-gray-400 hover:bg-emerald-500/10 hover:border-emerald-500/30 hover:text-emerald-400 rounded-xl transition-all shadow-inner group/btn" title="Freigeben">
                                        <i class="bi bi-check-lg text-sm group-hover/btn:drop-shadow-[0_0_8px_currentColor]"></i>
                                        <span class="text-[9px] font-black uppercase tracking-widest">Freigeben</span>
                                    </button>
                                @endif

                                @if($review->status !== 'rejected')
                                    <button wire:click="reject('{{ $review->id }}')" class="flex items-center justify-start gap-2.5 w-32 px-3 py-2 bg-gray-950 border border-gray-800 text-gray-400 hover:bg-amber-500/10 hover:border-amber-500/30 hover:text-amber-400 rounded-xl transition-all shadow-inner group/btn" title="Ablehnen">
                                        <i class="bi bi-slash-circle text-sm group-hover/btn:drop-shadow-[0_0_8px_currentColor]"></i>
                                        <span class="text-[9px] font-black uppercase tracking-widest">Ablehnen</span>
                                    </button>
                                @endif

                                <button wire:click="deleteReview('{{ $review->id }}')" wire:confirm="Review inkl. Medien unwiderruflich löschen?" class="flex items-center justify-start gap-2.5 w-32 px-3 py-2 mt-1 bg-gray-950 border border-gray-800 text-gray-400 hover:bg-red-500/10 hover:border-red-500/30 hover:text-red-400 rounded-xl transition-all shadow-inner group/btn" title="Löschen">
                                    <i class="bi bi-trash text-sm group-hover/btn:drop-shadow-[0_0_8px_currentColor]"></i>
                                    <span class="text-[9px] font-black uppercase tracking-widest">Löschen</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-16 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-16 h-16 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center mb-4 shadow-inner">
                                    <i class="bi bi-inbox text-2xl text-gray-600"></i>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Keine Bewertungen gefunden</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($reviews->hasPages())
            <div class="p-6 border-t border-gray-800 bg-gray-950/30 shadow-inner">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>

    {{-- Custom Scrollbar Style für die Tabelle --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #374151; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: #4b5563; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</div>
