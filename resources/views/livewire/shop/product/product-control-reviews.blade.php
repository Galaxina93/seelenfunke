<div class="space-y-6 pb-20">
    <div class="bg-white p-6 sm:p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col lg:flex-row justify-between items-center gap-6">
        <div class="text-center lg:text-left">
            <h1 class="text-2xl font-serif font-bold text-gray-900">Review Moderation</h1>
            <p class="text-sm text-gray-500 mt-1">Prüfe User Generated Content bevor er live geht.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto items-center">
            <div class="relative w-full sm:w-64">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen..." class="w-full pl-10 py-2.5 border-gray-200 rounded-xl text-sm focus:ring-primary focus:border-primary shadow-sm bg-gray-50 focus:bg-white transition-colors">
                <i class="bi bi-search absolute left-3.5 top-3 text-gray-400"></i>
            </div>
            <div class="flex bg-gray-100 p-1 rounded-xl w-full sm:w-auto overflow-x-auto">
                <button wire:click="$set('filterStatus', 'pending')" class="px-4 py-2.5 text-sm font-bold rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'pending' ? 'bg-white shadow text-primary' : 'text-gray-500 hover:text-gray-700' }}">Ausstehend</button>
                <button wire:click="$set('filterStatus', 'approved')" class="px-4 py-2.5 text-sm font-bold rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'approved' ? 'bg-white shadow text-green-600' : 'text-gray-500 hover:text-gray-700' }}">Freigegeben</button>
                <button wire:click="$set('filterStatus', 'rejected')" class="px-4 py-2.5 text-sm font-bold rounded-lg transition-all whitespace-nowrap {{ $filterStatus === 'rejected' ? 'bg-white shadow text-red-600' : 'text-gray-500 hover:text-gray-700' }}">Abgelehnt</button>
            </div>
        </div>
    </div>

    @if(session()->has('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm font-bold shadow-sm">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session()->has('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm font-bold shadow-sm">
            <i class="bi bi-shield-exclamation"></i> {{ session('warning') }}
        </div>
    @endif
    @if(session()->has('danger'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2 text-sm font-bold shadow-sm">
            <i class="bi bi-trash"></i> {{ session('danger') }}
        </div>
    @endif

    <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                <tr>
                    <th class="px-6 py-4">Kunde & Produkt</th>
                    <th class="px-6 py-4">Bewertung</th>
                    <th class="px-6 py-4">Medien (UGC)</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-right">Aktion</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                @forelse($reviews as $review)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-6 py-5 align-top">
                            <div class="font-bold text-gray-900 text-sm">{{ $review->customer->first_name }} {{ $review->customer->last_name }}</div>
                            <div class="text-xs text-primary mt-1 font-semibold truncate max-w-[200px]">{{ $review->product->name ?? 'Unbekannt' }}</div>
                            <div class="text-[10px] text-gray-400 mt-2">{{ $review->created_at->format('d.m.Y H:i') }}</div>
                        </td>
                        <td class="px-6 py-5 align-top max-w-xs">
                            <div class="flex text-amber-400 mb-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-4 w-4 {{ $i <= $review->rating ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </div>
                            @if($review->title)
                                <strong class="text-sm block mb-1 text-gray-800">{{ $review->title }}</strong>
                            @endif
                            <p class="text-xs text-gray-600 line-clamp-3">{{ $review->content }}</p>
                        </td>
                        <td class="px-6 py-5 align-top">
                            @if(!empty($review->media))
                                <div class="flex flex-wrap gap-2">
                                    @foreach($review->media as $media)
                                        @php
                                            $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                            $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                        @endphp
                                        <a href="{{ Storage::url($media) }}" target="_blank" class="w-12 h-12 rounded-lg border border-gray-200 overflow-hidden block relative group">
                                            @if($isVideo)
                                                <div class="w-full h-full bg-gray-900 flex items-center justify-center text-white">
                                                    <i class="bi bi-play-fill text-xl"></i>
                                                </div>
                                            @else
                                                <img src="{{ Storage::url($media) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">Keine Medien</span>
                            @endif
                        </td>
                        <td class="px-6 py-5 align-top text-center">
                            @if($review->status === 'approved')
                                <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded-full uppercase">Freigegeben</span>
                            @elseif($review->status === 'rejected')
                                <span class="bg-red-100 text-red-700 text-[10px] font-bold px-2 py-1 rounded-full uppercase">Abgelehnt</span>
                            @else
                                <span class="bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-1 rounded-full uppercase animate-pulse">Prüfung offen</span>
                            @endif
                        </td>
                        <td class="px-6 py-5 align-top text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($review->status !== 'approved')
                                    <button wire:click="approve('{{ $review->id }}')" class="p-2 bg-green-50 text-green-600 hover:bg-green-500 hover:text-white rounded-lg transition shadow-sm" title="Freigeben">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                @endif
                                @if($review->status !== 'rejected')
                                    <button wire:click="reject('{{ $review->id }}')" class="p-2 bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white rounded-lg transition shadow-sm" title="Ablehnen">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                @endif
                                <div class="w-px h-6 bg-gray-200 mx-1"></div>
                                <button wire:click="deleteReview('{{ $review->id }}')" wire:confirm="Review inkl. Medien unwiderruflich löschen?" class="p-2 bg-red-50 text-red-600 hover:bg-red-500 hover:text-white rounded-lg transition shadow-sm" title="Löschen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-12 text-center text-gray-400 italic">Keine Bewertungen in dieser Kategorie gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100 bg-gray-50/50">
            {{ $reviews->links() }}
        </div>
    </div>
</div>
