<div x-data="{
        reviewerModal: null,
        openReviewerProfile(data) {
            this.reviewerModal = data;
        },
        mediaLightbox: {
            open: false,
            items: [],
            index: 0,
            author: '',
            rating: 5,
            text: ''
        },
        openMediaLightbox(items, startIndex = 0, meta = {}) {
            if (!items || !items.length) return;
            this.mediaLightbox.items = items;
            this.mediaLightbox.index = startIndex;
            this.mediaLightbox.author = meta.author || 'Verifizierter Kunde';
            this.mediaLightbox.rating = meta.rating || 5;
            this.mediaLightbox.text = meta.text || '';
            this.mediaLightbox.open = true;
        },
        closeMediaLightbox() {
            this.mediaLightbox.open = false;
        },
        nextMediaSlide() {
            if (!this.mediaLightbox.items.length) return;
            this.mediaLightbox.index = (this.mediaLightbox.index + 1) % this.mediaLightbox.items.length;
        },
        prevMediaSlide() {
            if (!this.mediaLightbox.items.length) return;
            this.mediaLightbox.index = (this.mediaLightbox.index - 1 + this.mediaLightbox.items.length) % this.mediaLightbox.items.length;
        }
     }"
     @keydown.escape.window="if (mediaLightbox.open) closeMediaLightbox()"
     @keydown.arrow-right.window="if (mediaLightbox.open) nextMediaSlide()"
     @keydown.arrow-left.window="if (mediaLightbox.open) prevMediaSlide()"
     style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="w-full relative z-10">
    <style>
        @keyframes sfProdMediaSparkleA {
            0% { transform: translate(0, 6px) scale(0.35) rotate(0deg); opacity: 0; }
            35% { opacity: 1; }
            100% { transform: translate(-8px, -16px) scale(1.15) rotate(25deg); opacity: 0; }
        }
        @keyframes sfProdMediaSparkleB {
            0% { transform: translate(0, 6px) scale(0.3) rotate(0deg); opacity: 0; }
            40% { opacity: 1; }
            100% { transform: translate(10px, -18px) scale(1.1) rotate(-22deg); opacity: 0; }
        }
        @keyframes sfProdMediaSparkleC {
            0% { transform: translate(0, 4px) scale(0.4) rotate(0deg); opacity: 0; }
            45% { opacity: 0.95; }
            100% { transform: translate(-4px, -14px) scale(1.05) rotate(18deg); opacity: 0; }
        }
        .group\/media:hover .sf-p-sparkle-1 {
            animation: sfProdMediaSparkleA 1.25s ease-out infinite;
        }
        .group\/media:hover .sf-p-sparkle-2 {
            animation: sfProdMediaSparkleB 1.45s ease-out infinite 0.22s;
        }
        .group\/media:hover .sf-p-sparkle-3 {
            animation: sfProdMediaSparkleC 1.35s ease-out infinite 0.5s;
        }
    </style>
    <div class="mt-2 w-full">

        <div class="border-t border-gray-100 pt-6 w-full">

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
                    <p class="mt-2.5 text-[11px] text-gray-500 leading-relaxed max-w-xs text-center md:text-left">
                        <strong class="text-gray-700">Echtheitsprüfung (§ 5b Abs. 3 UWG):</strong> Bewertungen können ausschließlich von eingeloggten Kunden abgegeben werden, die diesen Artikel nachweislich in unserem Shop erworben haben. Treuepunkte („Seelenfunken“) werden ergebnisoffen und unabhängig von der Sternezahl vergeben.
                    </p>
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
                <div class="mb-8 p-4 bg-green-50 text-green-800 rounded-xl text-sm flex items-center shadow-sm w-full border border-green-200">
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
                                     1: '{{ funki_asset('shop/projekt/funki/reviews/1_funki_1_star.webp') }}',
                                     2: '{{ funki_asset('shop/projekt/funki/reviews/2_funki_2_stars.webp') }}',
                                     3: '{{ funki_asset('shop/projekt/funki/reviews/3_funki_3_stars.webp') }}',
                                     4: '{{ funki_asset('shop/projekt/funki/reviews/4_funki_4_stars.webp') }}',
                                     5: '{{ funki_asset('shop/projekt/funki/reviews/5_funki_5_stars.webp') }}'
                                 },
                                 currentFunki() {
                                     let r = this.hoverRating !== 0 ? this.hoverRating : this.selectedRating;
                                     if(r === 0 || r === null || r > 5) r = 5;
                                     return this.funkiImages[r];
                                 }
                             }">
                            <div class="mb-6">
                                <h3 class="text-lg font-bold text-gray-900">{{ $isEditing ? 'Deine Bewertung bearbeiten' : 'Deine Erfahrung teilen' }}</h3>
                            </div>

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

                                        <div class="h-44 w-44 sm:h-48 sm:w-48 shrink-0 transition-transform duration-300 mt-4 sm:mt-0 -mb-2 sm:-mb-4">
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
                                        <span class="block text-sm font-bold text-gray-700 mb-2">Bilder & Video hinzufügen (Max. 3 Dateien, davon max. 1 Video)</span>

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
                                            <p class="text-xs text-gray-500 mt-1 pointer-events-none text-center">Bilder (JPG, PNG, WEBP bis 5 MB • automatische WebP-Optimierung) • max. 1 Video (MP4, MOV bis 8 MB)</p>
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

                                    {{-- Anonym bewerten Checkbox --}}
                                    <div class="w-full bg-white/90 border border-amber-200/80 rounded-xl p-4 shadow-sm transition-colors hover:border-amber-300">
                                        <label class="flex items-start gap-3.5 cursor-pointer select-none">
                                            <div class="pt-0.5 shrink-0">
                                                <input type="checkbox" wire:model.live="isAnonymous" class="h-5 w-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 cursor-pointer transition">
                                            </div>
                                            <div class="text-xs sm:text-sm leading-relaxed">
                                                <span class="font-bold text-gray-900 flex items-center gap-1.5">
                                                    <i class="bi bi-incognito text-amber-600"></i> Anonym bewerten
                                                </span>
                                                <p class="text-xs text-gray-500 mt-0.5 leading-normal">
                                                    Wenn du diesen Haken setzt, werden dein Name, deine Initialen und dein Profilbild nicht öffentlich angezeigt. Deine Rezension erscheint stattdessen geschützt als <strong class="text-gray-700">„Anonymer Kunde“</strong>.
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    {{-- Rechtliche Einwilligung (Veröffentlichung & Bildrechte) --}}
                                    <div class="w-full bg-white/90 border @error('acceptReviewTerms') border-red-400 ring-1 ring-red-400/30 @else border-amber-200/80 @enderror rounded-xl p-4 shadow-sm transition-colors hover:border-amber-300">
                                        <label class="flex items-start gap-3.5 cursor-pointer select-none">
                                            <div class="pt-0.5 shrink-0">
                                                <input type="checkbox" wire:model.live="acceptReviewTerms" class="h-5 w-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 cursor-pointer transition">
                                            </div>
                                            <div class="text-xs text-gray-600 leading-relaxed">
                                                Ich bestätige, dass meine Bewertung auf eigener Erfahrung mit dem gekauften Produkt beruht. Sofern ich Fotos oder Videos hochlade, besitze ich sämtliche Bildrechte (keine unbefugte Abbildung Dritter gem. § 22 KUG) und bin mit der öffentlichen Anzeige im Shop sowie auf der Startseite („Was unsere Kunden sagen“) gemäß den <a href="{{ route('agb') }}" target="_blank" class="text-amber-700 underline hover:text-amber-900 font-semibold">AGB (§ 8a)</a> und der <a href="{{ route('datenschutz') }}" target="_blank" class="text-amber-700 underline hover:text-amber-900 font-semibold">Datenschutzerklärung</a> einverstanden. <span class="text-red-500 font-bold">*</span>
                                            </div>
                                        </label>
                                        @error('acceptReviewTerms') <span class="text-red-500 text-xs mt-1.5 block font-bold">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div class="pt-6 mt-4 border-t border-amber-200/50 w-full">
                                    <div class="flex flex-col sm:flex-row items-center gap-4 w-full">
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

                                    <p class="mt-3.5 flex items-center justify-center sm:justify-start gap-2 text-xs sm:text-sm text-gray-600 font-medium">
                                        <span class="text-amber-500 text-base leading-none select-none" aria-hidden="true">✨</span>
                                        <span>
                                            Deine ehrliche Bewertung wird mit <strong class="font-bold text-amber-700">+{{ \App\Services\Gamification\GameConfig::REVIEW_SPARK_REWARD }} Seelenfunken</strong> belohnt <span class="text-gray-400 font-normal">(unabhängig von der Sternezahl)</span>
                                        </span>
                                    </p>
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
                                    <button wire:click="deleteReview" onclick="confirm('Willst du diese Bewertung wirklich dauerhaft löschen?') || event.stopImmediatePropagation()" class="w-full sm:w-auto text-xs font-bold uppercase tracking-wider text-red-600 hover:text-red-800 bg-red-100/50 hover:bg-red-100 px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                                        <i class="bi bi-trash mr-2"></i> Löschen
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4 mb-4">
                                @if($userReview->is_anonymous)
                                    <div class="h-14 w-14 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold text-xl shadow-inner border border-white shrink-0">
                                        <i class="bi bi-incognito text-2xl"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-base font-semibold text-gray-900">Anonymer Kunde</p>
                                            <span class="text-[10px] font-bold uppercase tracking-wider bg-gray-200/80 text-gray-700 px-2 py-0.5 rounded-full">Anonym veröffentlicht</span>
                                        </div>
                                        <p class="text-xs text-gray-500 flex items-center mt-1 font-medium">
                                            <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $userReview->created_at->format('d.m.Y') }}
                                        </p>
                                    </div>
                                @else
                                    @php
                                        $uCard = $userReview->customer
                                            ? app(\App\Services\Gamification\GamificationService::class)->getPublicProfileCardData($userReview->customer, $userReview->customer->gamification)
                                            : null;
                                        $canOpenUProfile = $uCard && (isset($uCard['can_show_public_profile'])
                                            ? !empty($uCard['can_show_public_profile'])
                                            : (bool) ($userReview->customer?->gamification?->is_active && $userReview->customer?->gamification?->ranking_opt_in));
                                    @endphp
                                    @if($uCard)
                                        @if($canOpenUProfile)
                                            <button type="button"
                                                    @click="openReviewerProfile(@js($uCard))"
                                                    title="Klicken, um dein öffentliches Bewertungs-Profil anzusehen"
                                                    class="relative h-20 w-20 sm:h-24 sm:w-24 rounded-full shrink-0 group cursor-pointer focus:outline-none">
                                                <div class="absolute inset-[-4px] rounded-full border border-dashed border-[#C5A059]/50 pointer-events-none"></div>
                                                @if(!empty($uCard['custom_photo_url']))
                                                    <img src="{{ $uCard['custom_photo_url'] }}" alt="{{ $uCard['display_name'] }}" class="h-full w-full rounded-full object-cover border-2 border-[#C5A059] shadow-md transition-transform duration-300 group-hover:scale-105">
                                                @else
                                                    <div style="{{ $uCard['circle_css'] }}"
                                                         class="relative h-full w-full rounded-full border-2 border-[#C5A059] shadow-[0_0_20px_rgba(197,160,89,0.35)] overflow-hidden flex items-center justify-center transition-transform duration-300 group-hover:scale-105">
                                                        @if(!empty($uCard['video_url']))
                                                            <video src="{{ $uCard['video_url'] }}"
                                                                   autoplay loop muted playsinline
                                                                   class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                                        @endif
                                                        <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                                             style="background: conic-gradient(from 0deg, transparent 0%, {{ $uCard['accent_hex'] }}55 25%, transparent 50%, {{ $uCard['accent_hex'] }}44 75%, transparent 100%);"></div>
                                                        <img src="{{ $uCard['image_url'] }}" alt="Funki Level {{ $uCard['display_level'] }}" class="relative z-10 w-full h-full object-contain p-2 drop-shadow-[0_6px_12px_rgba(0,0,0,0.55)]">
                                                    </div>
                                                @endif
                                                <span class="absolute -bottom-1 -right-1 z-20 px-1.5 py-0.5 rounded-full bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 border border-white text-[9px] font-black shadow-sm">
                                                    Lvl. {{ $uCard['level'] }}
                                                </span>
                                            </button>
                                        @else
                                            <div class="relative h-14 w-14 rounded-full bg-amber-100 text-[#8a6820] font-serif font-bold text-lg flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-[#C5A059]/25 shrink-0 select-none">
                                                {{ mb_strtoupper(mb_substr((string) ($uCard['display_name'] ?? 'K'), 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                @if($canOpenUProfile)
                                                    <button type="button"
                                                            @click="openReviewerProfile(@js($uCard))"
                                                            class="text-base sm:text-lg font-bold text-gray-900 hover:text-amber-700 transition-colors cursor-pointer">
                                                        {{ $uCard['display_name'] }}
                                                    </button>
                                                    <span class="text-[10px] font-black uppercase tracking-wider bg-amber-500/15 border border-[#C5A059]/40 text-amber-800 px-2.5 py-0.5 rounded-full">
                                                        ✨ Funki Lvl. {{ $uCard['level'] }}
                                                    </span>
                                                    @if(!empty($uCard['rank_name']))
                                                        <span class="text-[10px] font-black uppercase tracking-wider {{ !empty($uCard['is_seelengott']) ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-300' : 'bg-white border-amber-300 text-amber-900' }} border px-2.5 py-0.5 rounded-full">
                                                            {{ !empty($uCard['is_seelengott']) ? '👑' : '🏆' }} {{ $uCard['rank_name'] }}
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-base sm:text-lg font-bold text-gray-900">
                                                        {{ $uCard['display_name'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-500 flex items-center mt-1 font-medium">
                                                <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $userReview->created_at->format('d.m.Y') }}
                                                @if($canOpenUProfile)
                                                    &bull; <button type="button" @click="openReviewerProfile(@js($uCard))" class="ml-1 text-amber-700 hover:underline font-semibold cursor-pointer">Profil ansehen</button>
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                @endif
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
                                @php
                                    $userMediaList = collect($userReview->media)->map(function ($mPath) {
                                        $mExt = strtolower(pathinfo((string) $mPath, PATHINFO_EXTENSION));
                                        return [
                                            'url' => '/storage/' . ltrim((string) $mPath, '/'),
                                            'type' => in_array($mExt, ['mp4', 'mov', 'webm'], true) ? 'video' : 'image',
                                        ];
                                    })->values()->all();
                                    $userAuthorName = $userReview->is_anonymous ? 'Anonymer Kunde' : ($uCard['display_name'] ?? 'Deine Bewertung');
                                @endphp
                                <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-amber-200/50 w-full">
                                    @foreach($userMediaList as $mIdx => $mItem)
                                        <button type="button"
                                                @click="openMediaLightbox(@js($userMediaList), {{ $mIdx }}, {
                                                    author: @js($userAuthorName),
                                                    rating: {{ (int) $userReview->rating }},
                                                    text: @js($userReview->content)
                                                })"
                                                class="w-28 h-28 sm:w-36 sm:h-36 rounded-2xl overflow-hidden border border-[#C5A059]/35 bg-gradient-to-br from-[#fdfbf7] via-[#f9f4e8] to-[#f4ecdc] shadow-sm relative group/media cursor-pointer transition-all duration-300 hover:border-[#C5A059] hover:shadow-[0_8px_22px_rgba(197,160,89,0.3)] focus:outline-none p-1.5 flex items-center justify-center">
                                            @if($mItem['type'] === 'video')
                                                <video src="{{ $mItem['url'] }}" muted preload="metadata" class="relative z-10 max-w-full max-h-full object-contain rounded-xl transition-transform duration-500 group-hover/media:scale-105"></video>
                                                <span class="absolute inset-0 z-20 flex items-center justify-center">
                                                    <span class="w-9 h-9 rounded-full bg-white/95 border border-[#C5A059] text-[#8a6820] flex items-center justify-center shadow-md">
                                                        <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                    </span>
                                                </span>
                                            @else
                                                <img src="{{ $mItem['url'] }}" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover blur-lg opacity-25 scale-110 pointer-events-none">
                                                <img src="{{ $mItem['url'] }}" alt="Bewertungsbild {{ $mIdx + 1 }}" class="relative z-10 max-w-full max-h-full object-contain rounded-xl shadow-xs transition-transform duration-500 group-hover/media:scale-105">
                                            @endif
                                            <div class="absolute inset-0 bg-gradient-to-t from-amber-500/10 via-transparent to-white/20 opacity-0 group-hover/media:opacity-100 transition-opacity duration-300 pointer-events-none z-10"></div>
                                            <div class="absolute inset-0 pointer-events-none overflow-hidden z-20" aria-hidden="true">
                                                <svg class="sf-p-sparkle-1 opacity-0 absolute bottom-3 left-3 w-3.5 h-3.5 text-amber-500 drop-shadow-[0_0_5px_rgba(251,191,36,0.95)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                <svg class="sf-p-sparkle-2 opacity-0 absolute bottom-4 right-3 w-4 h-4 text-[#C5A059] drop-shadow-[0_0_6px_rgba(197,160,89,0.95)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                <svg class="sf-p-sparkle-3 opacity-0 absolute top-3 right-1/3 w-3 h-3 text-amber-400 drop-shadow-[0_0_5px_rgba(251,191,36,0.9)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            @else
                <div class="mb-12 text-center py-10 bg-gray-50 border border-gray-100 rounded-2xl w-full" x-data="{ showLogin: false }">
                    <style>
                        @keyframes sfReviewSparkFloatMain {
                            0%, 100% { transform: translateY(0) scale(1) rotate(0deg); opacity: 0.95; }
                            50% { transform: translateY(-5px) scale(1.12) rotate(12deg); opacity: 1; }
                        }
                        @keyframes sfReviewSparkFloatSubA {
                            0%, 100% { transform: translate(0, 0) scale(0.85) rotate(0deg); opacity: 0.55; }
                            50% { transform: translate(-4px, -6px) scale(1.1) rotate(-18deg); opacity: 1; }
                        }
                        @keyframes sfReviewSparkFloatSubB {
                            0%, 100% { transform: translate(0, 0) scale(0.8) rotate(0deg); opacity: 0.5; }
                            50% { transform: translate(4px, -5px) scale(1.08) rotate(20deg); opacity: 0.95; }
                        }
                    </style>
                    <div x-show="!showLogin" class="animate-fade-in">
                        <div class="mx-auto relative flex items-center justify-center h-14 w-20 mb-3 select-none pointer-events-none" aria-hidden="true">
                            {{-- Haupt-Funke (Gold) --}}
                            <svg class="w-8 h-8 text-[#C5A059] drop-shadow-[0_0_8px_rgba(197,160,89,0.55)]" style="animation: sfReviewSparkFloatMain 3.2s ease-in-out infinite;" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/>
                            </svg>
                            {{-- Kleiner Funke links oben --}}
                            <svg class="w-4 h-4 text-amber-400 absolute top-1 left-2 drop-shadow-[0_0_5px_rgba(251,191,36,0.65)]" style="animation: sfReviewSparkFloatSubA 2.6s ease-in-out infinite 0.4s;" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l2.1 6.4L20.5 10.5l-6.4 2.1L12 19l-2.1-6.4L3.5 10.5l6.4-2.1L12 2z"/>
                            </svg>
                            {{-- Kleiner Funke rechts unten --}}
                            <svg class="w-3.5 h-3.5 text-amber-500 absolute bottom-1.5 right-2.5 drop-shadow-[0_0_5px_rgba(245,158,11,0.6)]" style="animation: sfReviewSparkFloatSubB 2.9s ease-in-out infinite 1.1s;" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l2.1 6.4L20.5 10.5l-6.4 2.1L12 19l-2.1-6.4L3.5 10.5l6.4-2.1L12 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Teile deine Erfahrung</h3>
                        <p class="mt-2 text-gray-500 max-w-lg mx-auto text-sm px-4">Bitte melde dich an, um eine Bewertung für dieses Produkt abzugeben und anderen Kunden bei der Entscheidung zu helfen.</p>
                        <button type="button" @click="showLogin = true" class="mt-6 inline-flex items-center px-8 py-3 rounded-xl shadow-lg text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 transition-colors uppercase tracking-widest transform hover:-translate-y-0.5">
                            Jetzt einloggen
                        </button>
                    </div>

                    <div x-show="showLogin" x-cloak class="max-w-sm mx-auto text-left animate-fade-in-up bg-white p-8 rounded-2xl border border-gray-200 shadow-xl mt-4 relative">
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-6 text-center">Konto verifizieren</h3>

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
                                        <svg x-show="showPw" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        <svg x-show="!showPw" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
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

                                        @if($review->is_anonymous || !$review->customer)
                                            <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-lg border border-white shadow-inner shrink-0">
                                                <i class="bi bi-incognito text-xl"></i>
                                            </div>
                                            <div>
                                                <p class="text-base font-semibold text-gray-900">Anonymer Kunde</p>
                                                <p class="text-xs text-gray-500 flex items-center mt-0.5 font-medium">
                                                    <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $review->created_at->format('d.m.Y') }}
                                                </p>
                                            </div>
                                        @else
                                            @php
                                                $rCard = app(\App\Services\Gamification\GamificationService::class)->getPublicProfileCardData($review->customer, $review->customer->gamification);
                                                $canOpenProfile = isset($rCard['can_show_public_profile'])
                                                    ? !empty($rCard['can_show_public_profile'])
                                                    : (bool) ($review->customer?->gamification?->is_active && $review->customer?->gamification?->ranking_opt_in);
                                            @endphp
                                            @if($canOpenProfile)
                                                <button type="button"
                                                        @click="openReviewerProfile(@js($rCard))"
                                                        title="Profil von {{ $rCard['display_name'] }} ansehen"
                                                        class="relative h-16 w-16 sm:h-20 sm:w-20 rounded-full shrink-0 group cursor-pointer focus:outline-none">
                                                    <div class="absolute inset-[-3px] rounded-full border border-dashed border-[#C5A059]/45 pointer-events-none"></div>
                                                    @if(!empty($rCard['custom_photo_url']))
                                                        <img src="{{ $rCard['custom_photo_url'] }}" alt="{{ $rCard['display_name'] }}" class="h-full w-full rounded-full object-cover border-2 border-[#C5A059] shadow-sm transition-transform duration-300 group-hover:scale-105">
                                                    @else
                                                        <div style="{{ $rCard['circle_css'] }}"
                                                             class="relative h-full w-full rounded-full border-2 border-[#C5A059] shadow-[0_0_16px_rgba(197,160,89,0.3)] overflow-hidden flex items-center justify-center transition-transform duration-300 group-hover:scale-105">
                                                            @if(!empty($rCard['video_url']))
                                                                <video src="{{ $rCard['video_url'] }}"
                                                                       autoplay loop muted playsinline
                                                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                                                            @endif
                                                            <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                                                 style="background: conic-gradient(from 0deg, transparent 0%, {{ $rCard['accent_hex'] }}55 25%, transparent 50%, {{ $rCard['accent_hex'] }}44 75%, transparent 100%);"></div>
                                                            <img src="{{ $rCard['image_url'] }}" alt="Funki Level {{ $rCard['display_level'] }}" class="relative z-10 w-full h-full object-contain p-1.5 drop-shadow-[0_5px_10px_rgba(0,0,0,0.5)]">
                                                        </div>
                                                    @endif
                                                    <span class="absolute -bottom-1 -right-1 z-20 px-1.5 py-0.5 rounded-full bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 border border-white text-[8px] font-black shadow-xs">
                                                        Lvl. {{ $rCard['level'] }}
                                                    </span>
                                                </button>
                                            @else
                                                <div class="h-12 w-12 rounded-full bg-amber-50 text-[#8a6820] font-serif font-bold text-lg flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-[#C5A059]/20 shrink-0 select-none">
                                                    {{ mb_strtoupper(mb_substr((string) ($rCard['display_name'] ?? 'K'), 0, 1)) }}
                                                </div>
                                            @endif

                                            <div>
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    @if($canOpenProfile)
                                                        <button type="button"
                                                                @click="openReviewerProfile(@js($rCard))"
                                                                class="text-base font-bold text-gray-900 hover:text-amber-700 transition-colors cursor-pointer">
                                                            {{ $rCard['display_name'] }}
                                                        </button>
                                                        <span class="text-[10px] font-black uppercase tracking-wider bg-amber-500/15 border border-[#C5A059]/40 text-amber-800 px-2 py-0.5 rounded-full">
                                                            ✨ Funki Lvl. {{ $rCard['level'] }}
                                                        </span>
                                                        @if(!empty($rCard['rank_name']))
                                                            <span class="text-[10px] font-black uppercase tracking-wider {{ !empty($rCard['is_seelengott']) ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-300' : 'bg-gray-100 border-gray-200 text-gray-700' }} border px-2 py-0.5 rounded-full">
                                                                {{ !empty($rCard['is_seelengott']) ? '👑' : '🏆' }} {{ $rCard['rank_name'] }}
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-base font-semibold text-gray-900">
                                                            {{ $rCard['display_name'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 flex items-center mt-0.5 font-medium">
                                                    <i class="bi bi-check-circle-fill text-green-500 mr-1.5"></i> Verifizierter Kauf &bull; {{ $review->created_at->format('d.m.Y') }}
                                                    @if($canOpenProfile)
                                                        &bull; <button type="button" @click="openReviewerProfile(@js($rCard))" class="ml-1 text-amber-700 hover:underline font-semibold cursor-pointer">Profil ansehen</button>
                                                    @endif
                                                </p>
                                            </div>
                                        @endif
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
                                    @php
                                        $revMediaList = collect($review->media)->map(function ($mPath) {
                                            $mExt = strtolower(pathinfo((string) $mPath, PATHINFO_EXTENSION));
                                            return [
                                                'url' => '/storage/' . ltrim((string) $mPath, '/'),
                                                'type' => in_array($mExt, ['mp4', 'mov', 'webm'], true) ? 'video' : 'image',
                                            ];
                                        })->values()->all();
                                        $revAuthorName = ($review->is_anonymous || !$review->customer) ? 'Anonymer Kunde' : ($rCard['display_name'] ?? 'Verifizierter Kunde');
                                    @endphp
                                    <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100 w-full">
                                        @foreach($revMediaList as $mIdx => $mItem)
                                            <button type="button"
                                                    @click="openMediaLightbox(@js($revMediaList), {{ $mIdx }}, {
                                                        author: @js($revAuthorName),
                                                        rating: {{ (int) $review->rating }},
                                                        text: @js($review->content)
                                                    })"
                                                    class="w-28 h-28 sm:w-36 sm:h-36 rounded-2xl overflow-hidden border border-[#C5A059]/35 bg-gradient-to-br from-[#fdfbf7] via-[#f9f4e8] to-[#f4ecdc] shadow-sm relative group/media cursor-pointer transition-all duration-300 hover:border-[#C5A059] hover:shadow-[0_8px_22px_rgba(197,160,89,0.3)] focus:outline-none p-1.5 flex items-center justify-center">
                                                @if($mItem['type'] === 'video')
                                                    <video src="{{ $mItem['url'] }}" muted preload="metadata" class="relative z-10 max-w-full max-h-full object-contain rounded-xl transition-transform duration-500 group-hover/media:scale-105"></video>
                                                    <span class="absolute inset-0 z-20 flex items-center justify-center">
                                                        <span class="w-9 h-9 rounded-full bg-white/95 border border-[#C5A059] text-[#8a6820] flex items-center justify-center shadow-md">
                                                            <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                        </span>
                                                    </span>
                                                @else
                                                    <img src="{{ $mItem['url'] }}" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover blur-lg opacity-25 scale-110 pointer-events-none">
                                                    <img src="{{ $mItem['url'] }}" alt="Kundenbild {{ $mIdx + 1 }}" class="relative z-10 max-w-full max-h-full object-contain rounded-xl shadow-xs transition-transform duration-500 group-hover/media:scale-105">
                                                @endif
                                                <div class="absolute inset-0 bg-gradient-to-t from-amber-500/10 via-transparent to-white/20 opacity-0 group-hover/media:opacity-100 transition-opacity duration-300 pointer-events-none z-10"></div>
                                                <div class="absolute inset-0 pointer-events-none overflow-hidden z-20" aria-hidden="true">
                                                    <svg class="sf-p-sparkle-1 opacity-0 absolute bottom-3 left-3 w-3.5 h-3.5 text-amber-500 drop-shadow-[0_0_5px_rgba(251,191,36,0.95)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                    <svg class="sf-p-sparkle-2 opacity-0 absolute bottom-4 right-3 w-4 h-4 text-[#C5A059] drop-shadow-[0_0_6px_rgba(197,160,89,0.95)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                    <svg class="sf-p-sparkle-3 opacity-0 absolute top-3 right-1/3 w-3 h-3 text-amber-400 drop-shadow-[0_0_5px_rgba(251,191,36,0.9)]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.3 6.9L21 11.2l-6.7 2.3L12 20.5l-2.3-7L3 11.2l6.7-2.3L12 2z"/></svg>
                                                </div>
                                            </button>
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

    {{-- FREUNDLICHES, HELLES LIGHTBOX SLIDER MODAL FÜR BEWERTUNGSBILDER & VIDEOS --}}
    <template x-teleport="body">
        <div x-show="mediaLightbox.open"
             style="display: none;"
             x-transition.opacity.duration.250ms
             class="fixed inset-0 z-[9800] flex items-center justify-center p-3 sm:p-6">

            <div class="fixed inset-0 bg-gray-900/35 backdrop-blur-sm"
                 @click="closeMediaLightbox()"></div>

            <template x-if="mediaLightbox.open && mediaLightbox.items.length > 0">
                <div class="relative z-10 w-full max-w-3xl rounded-[2rem] bg-white border-2 border-[#C5A059]/45 shadow-[0_25px_80px_rgba(138,104,32,0.22)] overflow-hidden flex flex-col max-h-[92vh]"
                     @click.stop>

                    <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-amber-100 bg-gradient-to-r from-[#fdfbf7] via-amber-50/45 to-[#fdfbf7]">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-full bg-amber-50 text-[#8a6820] font-serif font-bold text-base flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-[#C5A059]/25 shrink-0 select-none"
                                 x-text="(mediaLightbox.author || 'K').charAt(0).toUpperCase()"></div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-serif font-bold text-gray-900 text-base sm:text-lg truncate" x-text="mediaLightbox.author"></span>
                                    <div class="flex text-amber-400">
                                        <template x-for="star in 5" :key="star">
                                            <svg class="w-4 h-4" :class="star <= mediaLightbox.rating ? 'text-amber-400' : 'text-gray-200'" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10.868 2.884c-.321-.772-1.415-.772-1.736 0l-1.83 4.401-4.753.381c-.833.067-1.171 1.107-.536 1.651l3.62 3.102-1.106 4.637c-.194.813.691 1.456 1.405 1.02L10 15.591l4.069 2.485c.713.436 1.598-.207 1.404-1.02l-1.106-4.637 3.62-3.102c.635-.544.297-1.584-.536-1.65l-4.752-.382-1.831-4.401z" clip-rule="evenodd" />
                                            </svg>
                                        </template>
                                    </div>
                                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-[10px] font-bold">
                                        <span>✓</span>
                                        <span>Verifizierter Kauf</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <template x-if="mediaLightbox.items.length > 1">
                                <span class="px-3 py-1 rounded-full bg-amber-50 border border-[#C5A059]/40 text-xs font-bold text-[#8a6820]"
                                      x-text="(mediaLightbox.index + 1) + ' / ' + mediaLightbox.items.length"></span>
                            </template>
                            <button type="button"
                                    @click="closeMediaLightbox()"
                                    class="w-9 h-9 rounded-full bg-white hover:bg-amber-50 text-gray-500 hover:text-gray-900 border border-gray-200 hover:border-[#C5A059] shadow-xs flex items-center justify-center transition-all cursor-pointer"
                                    aria-label="Großansicht schließen">
                                ✕
                            </button>
                        </div>
                    </div>

                    <div class="relative flex-1 bg-gradient-to-b from-[#fdfbf7] via-[#f7f2e4] to-[#fdfbf7] flex items-center justify-center min-h-[300px] sm:min-h-[450px] max-h-[65vh] overflow-hidden select-none p-4 sm:p-6">
                        <template x-if="mediaLightbox.items[mediaLightbox.index]">
                            <div class="relative w-full h-full flex items-center justify-center">
                                <template x-if="mediaLightbox.items[mediaLightbox.index].type === 'video'">
                                    <video :key="mediaLightbox.items[mediaLightbox.index].url"
                                           :src="mediaLightbox.items[mediaLightbox.index].url"
                                           controls autoplay playsinline
                                           class="relative z-10 max-h-[58vh] max-w-full rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/30 bg-white"></video>
                                </template>
                                <template x-if="mediaLightbox.items[mediaLightbox.index].type !== 'video'">
                                    <div class="relative w-full h-full flex items-center justify-center">
                                        <img :src="mediaLightbox.items[mediaLightbox.index].url"
                                             alt=""
                                             aria-hidden="true"
                                             class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-20 scale-110 pointer-events-none">
                                        <img :key="mediaLightbox.items[mediaLightbox.index].url"
                                             :src="mediaLightbox.items[mediaLightbox.index].url"
                                             :alt="'Kundenaufnahme von ' + mediaLightbox.author"
                                             class="relative z-10 max-h-[58vh] max-w-full object-contain rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/25 bg-white">
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="mediaLightbox.items.length > 1">
                            <div>
                                <button type="button"
                                        @click="prevMediaSlide()"
                                        class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Vorherige Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button"
                                        @click="nextMediaSlide()"
                                        class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Nächste Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 sm:px-7 py-4 bg-white border-t border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-2.5 flex-1 min-w-0" x-show="mediaLightbox.text">
                            <span class="text-2xl leading-none font-serif text-[#C5A059] select-none" aria-hidden="true">„</span>
                            <p class="text-xs sm:text-sm text-gray-700 italic line-clamp-2"
                               x-text="mediaLightbox.text + '“'"></p>
                        </div>

                        <template x-if="mediaLightbox.items.length > 1">
                            <div class="flex items-center gap-2.5 shrink-0 justify-center sm:justify-end">
                                <template x-for="(item, idx) in mediaLightbox.items" :key="idx">
                                    <button type="button"
                                            @click="mediaLightbox.index = idx"
                                            :class="mediaLightbox.index === idx ? 'ring-2 ring-[#C5A059] border-white opacity-100 scale-105 shadow-sm' : 'opacity-60 hover:opacity-95 border-amber-200/70'"
                                            class="relative w-14 h-14 rounded-xl overflow-hidden border transition-all cursor-pointer bg-[#fdfbf7] shrink-0">
                                        <template x-if="item.type === 'video'">
                                            <div class="w-full h-full relative">
                                                <video :src="item.url" muted preload="metadata" class="w-full h-full object-cover"></video>
                                                <span class="absolute inset-0 flex items-center justify-center bg-black/25 text-white">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </span>
                                            </div>
                                        </template>
                                        <template x-if="item.type !== 'video'">
                                            <img :src="item.url" class="w-full h-full object-cover" alt="">
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- SÜSSES REVIEWER-PROFIL-MODAL (WIE IN DER HALLE DER HELDEN) --}}
    <template x-teleport="body">
        <div x-show="reviewerModal !== null"
             style="display: none;"
             @keydown.escape.window="reviewerModal = null"
             class="fixed inset-0 z-[9500] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-md"
                 @click="reviewerModal = null"
                 x-transition.opacity></div>

            <template x-if="reviewerModal">
                <div class="relative z-10 w-full max-w-md rounded-[2rem] bg-white dark:bg-[#0f131d] border-2 border-[#C5A059]/60 shadow-[0_25px_70px_rgba(0,0,0,0.45)] overflow-hidden p-6 sm:p-8 text-center animate-fade-in-up">
                    <button type="button"
                            @click="reviewerModal = null"
                            class="absolute top-4 right-4 z-20 w-9 h-9 rounded-xl bg-gray-100 hover:bg-red-500 text-gray-500 hover:text-white flex items-center justify-center transition-colors cursor-pointer">
                        ✕
                    </button>

                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-[#C5A059]/40 text-amber-800 dark:text-[#f5dca0] text-[10px] font-black uppercase tracking-widest mb-4">
                        <span>🏛️</span>
                        <span>Seelenfunke • Mitgliedsprofil</span>
                    </span>

                    {{-- Großer animierter Funki- & Hintergrund-Kreis --}}
                    <div class="relative w-36 h-36 sm:w-44 sm:h-44 mx-auto mb-5 flex items-center justify-center select-none">
                        <div class="absolute inset-[-6px] rounded-full border border-dashed border-[#C5A059]/50 pointer-events-none"></div>
                        <div class="absolute inset-[-12px] rounded-full border border-dotted border-[#C5A059]/30 animate-[spin_26s_linear_infinite] pointer-events-none"></div>

                        <div :style="reviewerModal.circle_css"
                             class="relative w-full h-full rounded-full border-2 border-[#C5A059] shadow-[0_0_35px_rgba(197,160,89,0.45)] overflow-hidden flex items-center justify-center bg-gray-950">
                            <template x-if="reviewerModal.video_url">
                                <video :src="reviewerModal.video_url"
                                       autoplay loop muted playsinline
                                       class="absolute inset-0 w-full h-full object-cover rounded-full pointer-events-none z-[1]"></video>
                            </template>
                            <div class="absolute inset-[-18%] rounded-full opacity-55 mix-blend-screen animate-[spin_22s_linear_infinite] pointer-events-none z-[2]"
                                 :style="`background: conic-gradient(from 0deg, transparent 0%, ${reviewerModal.accent_hex}55 25%, transparent 50%, ${reviewerModal.accent_hex}44 75%, transparent 100%);`"></div>
                            <img :src="reviewerModal.custom_photo_url || reviewerModal.image_url"
                                 :alt="reviewerModal.display_name"
                                 :class="reviewerModal.custom_photo_url ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-3.5 drop-shadow-[0_10px_20px_rgba(0,0,0,0.65)]'"
                                 class="relative z-10">
                        </div>

                        <span class="absolute -bottom-1 -right-1 z-30 px-2.5 py-1 rounded-full bg-gradient-to-r from-amber-300 via-[#C5A059] to-amber-500 text-gray-950 border border-white text-xs font-black shadow-md"
                              x-text="'Level ' + reviewerModal.level"></span>
                    </div>

                    {{-- Name & Aktiver Titel --}}
                    <h4 class="text-2xl font-serif font-bold text-gray-900 dark:text-white" x-text="reviewerModal.display_name"></h4>

                    <div class="mt-2 flex flex-wrap items-center justify-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border"
                              :class="reviewerModal.is_seelengott ? 'bg-gradient-to-r from-amber-400 via-[#C5A059] to-amber-500 text-gray-950 border-amber-300 shadow-sm' : 'bg-amber-500/15 border-[#C5A059]/50 text-amber-900 dark:text-[#f5dca0]'">
                            <span x-text="reviewerModal.is_seelengott ? '👑' : '🏆'"></span>
                            <span x-text="reviewerModal.rank_name"></span>
                        </span>
                    </div>

                    {{-- Funki Stufe & Hintergrund Info --}}
                    <div class="mt-4 p-3.5 rounded-2xl bg-amber-50/80 dark:bg-gray-900/80 border border-amber-200/70 dark:border-gray-800 text-xs">
                        <span class="text-[10px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-400 block" x-text="reviewerModal.funki_name"></span>
                        <span class="text-gray-600 dark:text-gray-300 font-medium block mt-0.5" x-text="'Hintergrund: ' + reviewerModal.bg_name"></span>
                    </div>

                    {{-- Kleine Statistik-Kacheln --}}
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Funki-Stufe</span>
                            <span class="text-sm font-mono font-black text-gray-900 dark:text-white" x-text="reviewerModal.level + ' / 10'"></span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Max-Titel</span>
                            <span class="text-sm font-mono font-black text-amber-700 dark:text-[#f5dca0]" x-text="reviewerModal.diamonds_count + ' / ' + reviewerModal.total_titles + ' 💎'"></span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Status</span>
                            <span class="text-sm font-mono font-black text-emerald-600 dark:text-emerald-400">Verifiziert ✓</span>
                        </div>
                    </div>

                    <button type="button"
                            @click="reviewerModal = null"
                            class="mt-5 w-full py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 text-white text-xs font-black uppercase tracking-widest transition cursor-pointer">
                        Schließen
                    </button>
                </div>
            </template>
        </div>
    </template>
</div>
