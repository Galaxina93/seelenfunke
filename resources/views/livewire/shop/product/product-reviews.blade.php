<div class="w-full relative z-10">
    <div class="mt-16 sm:mt-24 w-full">

        <div class="mt-6 border-t border-gray-200 pt-10 w-full">

            <div class="flex flex-col md:flex-row md:items-start gap-8 mb-10 bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 w-full">
                <div class="flex flex-col items-center md:items-start shrink-0">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Kundenrezensionen</h3>
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-5xl font-bold text-gray-900">{{ number_format($averageRating, 1, ',', '.') }}</span>
                        <span class="text-lg text-gray-500 mt-2">von 5</span>
                    </div>
                    <div class="flex items-center text-amber-400 mb-2">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="h-6 w-6 flex-shrink-0 {{ $i <= round($averageRating) ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                            </svg>
                        @endfor
                    </div>
                    <span class="text-sm text-gray-500">{{ $totalReviews }} verifizierte Bewertungen</span>
                </div>

                <div class="flex-1 w-full border-t md:border-t-0 md:border-l border-gray-200 pt-6 md:pt-0 md:pl-8">
                    @foreach([5, 4, 3, 2, 1] as $star)
                        <button wire:click="filterByRating({{ $star }})" class="flex items-center w-full group mb-3 last:mb-0 transition-opacity {{ $filterRating && $filterRating !== $star ? 'opacity-40' : 'opacity-100' }}">
                            <span class="text-sm font-medium {{ $filterRating === $star ? 'text-amber-600 font-bold' : 'text-gray-600 group-hover:text-amber-600 group-hover:underline' }} w-16 text-left whitespace-nowrap">{{ $star }} Sterne</span>
                            <div class="flex-1 mx-4 h-5 bg-gray-100 rounded-full overflow-hidden border border-gray-200 shadow-inner">
                                <div class="h-full bg-amber-400 rounded-full transition-all duration-500" style="width: {{ $breakdown[$star]['percent'] }}%;"></div>
                            </div>
                            <span class="text-sm {{ $filterRating === $star ? 'text-amber-600 font-bold' : 'text-gray-600 group-hover:text-amber-600' }} w-12 text-right">{{ $breakdown[$star]['percent'] }}%</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if(session()->has('success'))
                <div class="mb-8 p-4 bg-green-50 text-green-800 rounded-xl text-sm flex items-center shadow-sm w-full">
                    <i class="bi bi-check-circle mr-2 text-lg"></i> {{ session('success') }}
                </div>
            @endif

            @if(session()->has('error'))
                <div class="mb-8 p-4 bg-red-50 text-red-800 rounded-xl text-sm flex items-center shadow-sm border border-red-100 w-full">
                    <i class="bi bi-exclamation-triangle mr-2 text-lg"></i> {{ session('error') }}
                </div>
            @endif

            @auth('customer')
                @if(!$canReview && !$hasReviewed)
                    <div class="mb-12 text-center py-10 bg-gray-50 border border-gray-100 rounded-2xl w-full">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-4">
                            <i class="bi bi-bag-x text-amber-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">Nur für Käufer</h3>
                        <p class="mt-2 text-gray-500 max-w-lg mx-auto px-4">Um eine ehrliche und transparente Community zu gewährleisten, können nur Kunden, die dieses Produkt erworben haben, eine Bewertung abgeben.</p>
                    </div>
                @else
                    @if(!$hasReviewed || $isEditing)
                        <div class="mb-12 bg-amber-50/50 p-6 sm:p-8 rounded-2xl border border-amber-100 w-full"
                             x-data="{
                                 hoverRating: 0,
                                 selectedRating: @entangle('rating'),
                                 funkiImages: {
                                     1: '{{ asset('images/projekt/funki/reviews/1_funki_1_star.png') }}',
                                     2: '{{ asset('images/projekt/funki/reviews/2_funki_2_stars.png') }}',
                                     3: '{{ asset('images/projekt/funki/reviews/3_funki_3_stars.png') }}',
                                     4: '{{ asset('images/projekt/funki/reviews/4_funki_4_stars.png') }}',
                                     5: '{{ asset('images/projekt/funki/reviews/5_funki_5_stars.png') }}'
                                 },
                                 currentFunki() {
                                     let r = this.hoverRating !== 0 ? this.hoverRating : this.selectedRating;
                                     if(r === 0 || r === null || r > 5) r = 5;
                                     return this.funkiImages[r];
                                 }
                             }">
                            <h3 class="text-lg font-bold text-gray-900 mb-6">{{ $isEditing ? 'Deine Bewertung bearbeiten' : 'Deine Erfahrung teilen' }}</h3>

                            <form wire:submit.prevent="submitReview" class="space-y-6 w-full">
                                <div class="w-full">
                                    <label class="block text-sm font-bold text-gray-700 mb-4">Sterne-Bewertung</label>

                                    <div class="flex flex-col sm:flex-row flex-wrap sm:flex-nowrap items-center sm:items-end gap-6 sm:gap-10 w-full">
                                        <div class="flex items-center space-x-2 shrink-0" @mouseleave="hoverRating = 0">
                                            @for($i = 1; $i <= 5; $i++)
                                                <button type="button"
                                                        @click="selectedRating = {{ $i }}"
                                                        @mouseenter="hoverRating = {{ $i }}"
                                                        class="focus:outline-none focus:ring-2 focus:ring-amber-500 rounded-full transition-transform hover:scale-110">
                                                    <svg class="h-10 w-10 sm:h-12 sm:w-12 transition-colors"
                                                         :class="(hoverRating >= {{ $i }} || (hoverRating === 0 && selectedRating >= {{ $i }})) ? 'text-amber-400' : 'text-gray-300'"
                                                         viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            @endfor
                                        </div>

                                        <div class="h-32 w-32 sm:h-44 sm:w-44 shrink-0 transition-transform duration-300 mt-4 sm:mt-0 -mb-2 sm:-mb-4">
                                            <img :src="currentFunki()" class="w-full h-full object-contain origin-bottom" alt="Funki Bewertung">
                                        </div>
                                    </div>

                                    @error('rating') <span class="text-red-500 text-xs mt-3 block font-bold">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-1 gap-6 mt-4 w-full">
                                    <label class="w-full block">
                                        <span class="block text-sm font-bold text-gray-700 mb-2">Zusammenfassung (optional)</span>
                                        <input type="text" wire:model="title" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4">
                                        @error('title') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                    </label>

                                    <label class="w-full block" x-data="{ count: {{ strlen($content ?? '') }} }">
                                        <div class="flex justify-between items-end mb-2">
                                            <span class="block text-sm font-bold text-gray-700">Dein Feedback</span>
                                            <span class="text-[10px] uppercase font-bold tracking-widest transition-colors" :class="count > 950 ? 'text-red-500' : 'text-gray-400'">
                                                <span x-text="count"></span>/1000 Zeichen
                                            </span>
                                        </div>
                                        <textarea wire:model.live="content" x-on:input="count = $el.value.length" maxlength="1000" rows="5" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4 resize-none" placeholder="Was hat dir besonders gut gefallen?"></textarea>
                                        @error('content') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="w-full">
                                        <span class="block text-sm font-bold text-gray-700 mb-2">Bilder & Videos hinzufügen (Max. 3 Dateien)</span>

                                        <label class="w-full flex flex-col items-center justify-center px-6 py-8 border-2 border-dashed rounded-2xl transition-colors cursor-pointer focus-within:ring-2 focus-within:ring-amber-500"
                                               x-data="{ isDropping: false }"
                                               @dragover.prevent="isDropping = true"
                                               @dragleave.prevent="isDropping = false"
                                               @drop.prevent="isDropping = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'));"
                                               :class="isDropping ? 'border-amber-500 bg-amber-50 scale-[1.02]' : 'border-gray-300 bg-white hover:bg-gray-50'">

                                            <i class="bi bi-cloud-arrow-up text-3xl text-gray-400 mb-2 pointer-events-none"></i>
                                            <div class="flex text-sm text-gray-600 justify-center text-center pointer-events-none">
                                                <span class="font-medium text-amber-600 hover:text-amber-500">Lade eine Datei hoch</span>
                                                <p class="pl-1 hidden sm:block">oder Drag & Drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1 pointer-events-none">PNG, JPG, MP4 bis max. 10MB</p>
                                            <input type="file" x-ref="fileInput" wire:model.live="newMedia" class="sr-only" multiple accept="image/png, image/jpeg, image/webp, video/mp4, video/quicktime">
                                        </label>

                                        <div wire:loading wire:target="newMedia" class="text-sm text-amber-600 mt-3 flex items-center font-bold">
                                            <i class="bi bi-arrow-repeat animate-spin mr-2"></i> Dateien werden verarbeitet...
                                        </div>

                                        @error('newMedia') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror
                                        @error('newMedia.*') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror

                                        @if(count($existingMedia) > 0 || count($accumulatedMedia) > 0)
                                            <div class="flex flex-wrap gap-4 mt-6 w-full relative z-10">
                                                @foreach($existingMedia as $index => $path)
                                                    @php
                                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                        $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                                    @endphp
                                                    <div class="relative w-24 h-24 rounded-xl overflow-hidden border border-gray-200 shadow-sm group">
                                                        @if($isVideo)
                                                            <div class="w-full h-full bg-gray-900 flex items-center justify-center relative">
                                                                <video src="{{ asset('storage/' . $path) }}" class="absolute inset-0 w-full h-full object-cover opacity-60"></video>
                                                                <i class="bi bi-play-circle text-white text-2xl relative z-10"></i>
                                                            </div>
                                                        @else
                                                            <img src="{{ asset('storage/' . $path) }}" class="w-full h-full object-cover">
                                                        @endif
                                                        <button type="button" wire:click="removeExistingMedia({{ $index }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm">
                                                            <i class="bi bi-x text-lg"></i>
                                                        </button>
                                                    </div>
                                                @endforeach

                                                @foreach($accumulatedMedia as $index => $file)
                                                    @php
                                                        $mime = $file->getMimeType();
                                                        $isVideo = str_contains($mime, 'video');
                                                    @endphp
                                                    <div class="relative w-24 h-24 rounded-xl overflow-hidden border border-amber-300 shadow-sm group">
                                                        @if($isVideo)
                                                            <div class="w-full h-full bg-gray-100 flex flex-col items-center justify-center text-gray-500">
                                                                <i class="bi bi-film text-2xl mb-1"></i>
                                                                <span class="text-[10px] uppercase font-bold text-gray-400">Video</span>
                                                            </div>
                                                        @else
                                                            <img src="{{ $file->temporaryUrl() }}" class="w-full h-full object-cover">
                                                        @endif
                                                        <button type="button" wire:click="removeAccumulatedMedia({{ $index }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm">
                                                            <i class="bi bi-x text-lg"></i>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row items-center gap-4 pt-6 mt-4 border-t border-amber-200/50 w-full">
                                    <button type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto inline-flex justify-center py-3.5 px-10 rounded-xl shadow-md text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all transform hover:-translate-y-0.5 uppercase tracking-widest">
                                        <span wire:loading.remove wire:target="submitReview">{{ $isEditing ? 'Änderungen speichern' : 'Bewertung absenden' }}</span>
                                        <span wire:loading wire:target="submitReview">Speichert...</span>
                                    </button>
                                    @if($isEditing)
                                        <button type="button" wire:click="cancelEdit" class="w-full sm:w-auto inline-flex justify-center py-3.5 px-8 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors uppercase tracking-widest">
                                            Abbrechen
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @elseif($hasReviewed && !$isEditing && $userReview)
                        <div class="mb-12 bg-amber-50 border-2 border-amber-200 p-6 sm:p-8 rounded-2xl shadow-sm relative transition-all w-full">

                            @if($userReview->status === 'pending')
                                <div class="mb-6 bg-blue-100 text-blue-800 p-4 rounded-xl text-sm flex items-start gap-3 shadow-sm border border-blue-200">
                                    <i class="bi bi-info-circle-fill text-blue-600 text-xl shrink-0 mt-0.5"></i>
                                    <div>
                                        <span class="font-bold block mb-1">Deine Bewertung wird geprüft</span>
                                        <p class="leading-relaxed opacity-90">Dein Kommentar wird durch unsere Moderatoren für die Freigabe geprüft. Sobald das erledigt ist, wird sie für alle sichtbar.</p>
                                    </div>
                                </div>
                            @elseif($userReview->status === 'rejected')
                                <div class="mb-6 bg-red-100 text-red-800 p-4 rounded-xl text-sm flex items-start gap-3 shadow-sm border border-red-200">
                                    <i class="bi bi-shield-exclamation text-red-600 text-xl shrink-0 mt-0.5"></i>
                                    <div>
                                        <span class="font-bold block mb-1">Inakzeptabler Inhalt festgestellt</span>
                                        <p class="leading-relaxed opacity-90">Deine Bewertung entspricht leider nicht unseren Richtlinien und wurde blockiert. Bitte passe deinen Kommentar / deine Bilder an oder lösche die Bewertung.</p>
                                    </div>
                                </div>
                            @endif

                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-amber-200/50 pb-4 w-full">
                                <h3 class="text-xs font-black uppercase text-amber-800 tracking-widest flex items-center">
                                    <i class="bi bi-star-fill mr-2 text-amber-500"></i> Deine aktuelle Bewertung
                                </h3>
                                <div class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
                                    <button wire:click="editReview" class="w-full sm:w-auto text-xs font-bold uppercase tracking-wider text-amber-700 hover:text-amber-900 bg-amber-200/50 hover:bg-amber-200 px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="bi bi-pencil-square mr-2"></i> Bearbeiten
                                    </button>
                                    <button wire:click="deleteReview" wire:confirm="Bist du sicher, dass du deine Bewertung dauerhaft löschen willst?" class="w-full sm:w-auto text-xs font-bold uppercase tracking-wider text-red-600 hover:text-red-800 bg-red-100/50 hover:bg-red-100 px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="bi bi-trash mr-2"></i> Löschen
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4 mb-4">
                                <div class="h-14 w-14 rounded-full bg-amber-200 flex items-center justify-center text-amber-900 font-bold text-xl shadow-inner border border-white shrink-0">
                                    {{ substr($userReview->customer->first_name, 0, 1) }}{{ substr($userReview->customer->last_name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-base font-semibold text-gray-900">{{ $userReview->customer->first_name }} {{ substr($userReview->customer->last_name, 0, 1) }}.</p>
                                    <p class="text-xs text-gray-500 flex items-center mt-1 font-medium">
                                        <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $userReview->created_at->format('d.m.Y') }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center mb-4">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-6 w-6 flex-shrink-0 {{ $i <= $userReview->rating ? 'text-amber-400' : 'text-gray-300' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                    </svg>
                                @endfor
                            </div>
                            @if($userReview->title)
                                <h4 class="text-lg font-bold text-gray-900 mb-2">{{ $userReview->title }}</h4>
                            @endif
                            <div class="text-sm text-gray-700 leading-relaxed mb-4 w-full break-words">
                                <p>{!! nl2br(e($userReview->content)) !!}</p>
                            </div>

                            @if(!empty($userReview->media))
                                <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-amber-200/50 w-full">
                                    @foreach($userReview->media as $mediaPath)
                                        @php
                                            $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
                                            $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                        @endphp
                                        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl overflow-hidden border border-amber-200 shadow-sm relative group">
                                            @if($isVideo)
                                                <video src="{{ asset('storage/' . $mediaPath) }}" controls class="w-full h-full object-cover"></video>
                                            @else
                                                <a href="{{ asset('storage/' . $mediaPath) }}" target="_blank" class="block w-full h-full">
                                                    <img src="{{ asset('storage/' . $mediaPath) }}" class="w-full h-full object-cover hover:scale-105 transition-transform cursor-pointer">
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            @else
                <div class="mb-12 text-center py-10 bg-gray-50 border border-gray-100 rounded-2xl w-full" x-data="{ showLogin: false }">
                    <div x-show="!showLogin" class="animate-fade-in">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-amber-100 mb-4">
                            <i class="bi bi-person-lock text-amber-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Teile deine Erfahrung</h3>
                        <p class="mt-2 text-gray-500 max-w-lg mx-auto text-sm px-4">Bitte melde dich an, um eine Bewertung für dieses Produkt abzugeben und anderen Kunden bei der Entscheidung zu helfen.</p>
                        <button type="button" @click="showLogin = true" class="mt-6 inline-flex items-center px-8 py-3 rounded-xl shadow-lg text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 transition-colors uppercase tracking-widest transform hover:-translate-y-0.5">
                            Jetzt einloggen
                        </button>
                    </div>

                    <div x-show="showLogin" x-cloak class="max-w-sm mx-auto text-left animate-fade-in-up bg-white p-8 rounded-2xl border border-gray-200 shadow-xl mt-4 relative">
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-6 text-center">Willkommen zurück</h3>

                        <form wire:submit.prevent="loginUser" class="space-y-5">

                            <label class="block w-full">
                                <span class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">E-Mail</span>
                                <input type="email" wire:model="loginEmail" autocomplete="username" class="block w-full rounded-xl bg-gray-50 border-gray-200 shadow-sm focus:bg-white focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4 transition-colors">
                                @error('loginEmail') <span class="text-red-500 text-xs mt-1 font-bold block">{{ $message }}</span> @enderror
                            </label>

                            <label class="block w-full" x-data="{ showPw: false }">
                                <span class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Passwort</span>
                                <div class="relative">
                                    <input :type="showPw ? 'text' : 'password'" wire:model="loginPassword" autocomplete="current-password" wire:keydown.enter="loginUser" class="block w-full rounded-xl bg-gray-50 border-gray-200 shadow-sm focus:bg-white focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4 transition-colors pr-10">
                                    <button type="button" @click.prevent="showPw = !showPw" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none transition-colors">
                                        <svg x-show="!showPw" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="showPw" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                                    </button>
                                </div>
                                @error('loginPassword') <span class="text-red-500 text-xs mt-1 font-bold block">{{ $message }}</span> @enderror
                            </label>

                            <label class="flex items-center ml-1 cursor-pointer">
                                <input type="checkbox" wire:model="loginRemember" class="h-4 w-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500 transition duration-150 ease-in-out cursor-pointer">
                                <span class="ml-2 block text-xs font-bold text-gray-600 cursor-pointer">Angemeldet bleiben</span>
                            </label>

                            @if($loginError)
                                <p class="text-red-500 text-xs font-bold mt-2 bg-red-50 p-3 rounded-lg border border-red-100 flex items-center gap-2"><i class="bi bi-exclamation-triangle"></i> {{ $loginError }}</p>
                            @endif

                            <div class="flex flex-col gap-3 pt-6 mt-4 border-t border-gray-100">
                                <button type="submit" wire:loading.attr="disabled" class="w-full flex justify-center py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 transition-all uppercase tracking-widest transform hover:-translate-y-0.5">
                                    <span wire:loading.remove wire:target="loginUser">Einloggen</span>
                                    <span wire:loading wire:target="loginUser">Lade...</span>
                                </button>
                                <button type="button" @click="showLogin = false" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-colors uppercase tracking-widest">
                                    Abbrechen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endauth

            <div class="flex items-center justify-between mt-12 mb-8 border-b border-gray-200 pb-4 w-full">
                <h3 class="text-xl font-bold text-gray-900">Alle Bewertungen</h3>
                @if($filterRating)
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-amber-600 bg-amber-50 px-3 py-1 rounded-full border border-amber-200">{{ $filterRating }} Sterne Bewertungen</span>
                        <button wire:click="filterByRating({{ $filterRating }})" class="text-sm text-gray-400 hover:text-red-500 underline transition">Filter löschen</button>
                    </div>
                @endif
            </div>

            <div class="w-full relative z-10">
                @if($reviews->isEmpty())
                    @if(!auth()->guard('customer')->check() || ($hasReviewed && !$isEditing))
                        <div class="text-center py-16 bg-white rounded-2xl border border-gray-100 w-full">
                            <i class="bi bi-chat-square-text text-4xl text-gray-300 mb-3 block"></i>
                            <p class="text-gray-500 text-sm">Es gibt noch keine passenden Bewertungen.</p>
                        </div>
                    @endif
                @else
                    <div class="space-y-8 w-full">
                        @foreach($reviews as $review)
                            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-gray-100 transition-all hover:shadow-md w-full">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 w-full">
                                    <div class="flex items-center space-x-4">

                                        @if($review->customer && $review->customer->profile && $review->customer->profile->photo_path)
                                            <img src="{{ Storage::url($review->customer->profile->photo_path) }}" alt="{{ $review->customer->first_name }}" class="h-12 w-12 rounded-full object-cover border border-white shadow-inner shrink-0">
                                        @else
                                            <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-bold text-lg border border-white shadow-inner shrink-0">
                                                {{ substr($review->customer->first_name, 0, 1) }}{{ substr($review->customer->last_name, 0, 1) }}
                                            </div>
                                        @endif

                                        <div>
                                            <p class="text-base font-semibold text-gray-900">{{ $review->customer->first_name }} {{ substr($review->customer->last_name, 0, 1) }}.</p>
                                            <p class="text-xs text-gray-500 flex items-center mt-0.5 font-medium">
                                                <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $review->created_at->format('d.m.Y') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="h-5 w-5 flex-shrink-0 {{ $i <= $review->rating ? 'text-amber-400' : 'text-gray-200' }}" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                            </svg>
                                        @endfor
                                    </div>
                                </div>
                                @if($review->title)
                                    <h4 class="text-lg font-bold text-gray-900 mt-4 mb-2 w-full break-words">{{ $review->title }}</h4>
                                @endif
                                <div class="text-sm text-gray-700 leading-relaxed mb-4 w-full break-words">
                                    <p>{!! nl2br(e($review->content)) !!}</p>
                                </div>

                                @if(!empty($review->media))
                                    <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100 w-full">
                                        @foreach($review->media as $mediaPath)
                                            @php
                                                $ext = strtolower(pathinfo($mediaPath, PATHINFO_EXTENSION));
                                                $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                            @endphp
                                            <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl overflow-hidden border border-gray-200 shadow-sm relative group">
                                                @if($isVideo)
                                                    <video src="{{ asset('storage/' . $mediaPath) }}" controls class="w-full h-full object-cover"></video>
                                                @else
                                                    <a href="{{ asset('storage/' . $mediaPath) }}" target="_blank" class="block w-full h-full">
                                                        <img src="{{ asset('storage/' . $mediaPath) }}" class="w-full h-full object-cover hover:scale-105 transition-transform cursor-pointer">
                                                    </a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>

                    <div class="mt-10 w-full">
                        {{ $reviews->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
