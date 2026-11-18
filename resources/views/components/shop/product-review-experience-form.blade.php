@props([
    'isEditing' => false,
    'showCancel' => false,
    'productName' => null,
    'darkAware' => false,
    'submitAction' => 'submitReview',
    'cancelAction' => 'cancelEdit',
    'existingMedia' => null,
    'accumulatedMedia' => null,
])

@php
    $resolvedExistingMedia = is_array($existingMedia)
        ? $existingMedia
        : ((isset($this) && is_array($this->existingMedia ?? null)) ? $this->existingMedia : []);

    $resolvedAccumulatedMedia = is_array($accumulatedMedia)
        ? $accumulatedMedia
        : ((isset($this) && is_array($this->accumulatedMedia ?? null)) ? $this->accumulatedMedia : []);

    $initialContentLength = (isset($this) && isset($this->content))
        ? mb_strlen((string) $this->content)
        : 0;

    $wrapperClasses = $darkAware
        ? 'bg-amber-50/50 dark:bg-gray-900/80 p-6 sm:p-8 rounded-2xl border border-amber-100 dark:border-gray-800 w-full'
        : 'mb-12 bg-amber-50/50 p-6 sm:p-8 rounded-2xl border border-amber-100 w-full';

    $headingClass = $darkAware ? 'text-gray-900 dark:text-white' : 'text-gray-900';
    $labelClass = $darkAware ? 'text-gray-700 dark:text-gray-200' : 'text-gray-700';

    $inputClass = $darkAware
        ? 'block w-full rounded-xl bg-white dark:bg-gray-950 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4'
        : 'block w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm py-3 px-4';

    $dropzoneIdleClass = $darkAware
        ? 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950/80 hover:bg-gray-50 dark:hover:bg-gray-900'
        : 'border-gray-300 bg-white hover:bg-gray-50';

    $dropzoneActiveClass = $darkAware
        ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/10 scale-[1.02]'
        : 'border-amber-500 bg-amber-50 scale-[1.02]';

    $optionBoxBaseClass = $darkAware
        ? 'w-full bg-white/90 dark:bg-gray-950/70 rounded-xl p-4 shadow-sm transition-colors hover:border-amber-300 dark:hover:border-[#C5A059]/50'
        : 'w-full bg-white/90 rounded-xl p-4 shadow-sm transition-colors hover:border-amber-300';

    $optionBorderNormalClass = $darkAware
        ? 'border border-amber-200/80 dark:border-gray-800'
        : 'border border-amber-200/80';

    $cancelBtnClass = $darkAware
        ? 'w-full sm:w-auto inline-flex justify-center py-3.5 px-8 border border-gray-300 dark:border-gray-700 rounded-xl shadow-sm text-sm font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none transition-colors uppercase tracking-widest'
        : 'w-full sm:w-auto inline-flex justify-center py-3.5 px-8 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-colors uppercase tracking-widest';
@endphp

<div {{ $attributes->merge(['class' => $wrapperClasses]) }}
     data-product-review-experience-form="true"
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
             if (r === 0 || r === null || r > 5) r = 5;
             return this.funkiImages[r];
         }
     }">
    <div class="mb-6">
        @if(!empty($productName))
            <div class="mb-2.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-widest {{ $darkAware ? 'bg-white/90 dark:bg-gray-800 border border-[#C5A059]/40 text-[#8a6820] dark:text-[#C5A059]' : 'bg-white/90 border border-[#C5A059]/40 text-[#8a6820]' }}">
                    <span aria-hidden="true">✦</span>
                    <span class="truncate max-w-[260px] sm:max-w-md">{{ $productName }}</span>
                </span>
            </div>
        @endif

        <h3 class="text-lg font-bold {{ $headingClass }}">{{ $isEditing ? 'Deine Bewertung bearbeiten' : 'Deine Erfahrung teilen' }}</h3>
    </div>

    <form wire:submit.prevent="{{ $submitAction }}" class="space-y-6 w-full">
        <div class="w-full">
            <label class="block text-sm font-bold {{ $labelClass }} mb-4">Sterne-Bewertung</label>

            <div class="flex flex-col sm:flex-row flex-wrap sm:flex-nowrap items-center sm:items-end gap-6 sm:gap-10 w-full">
                <div class="flex items-center space-x-2 shrink-0" @mouseleave="hoverRating = 0">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button"
                                @click="selectedRating = {{ $i }}"
                                @mouseenter="hoverRating = {{ $i }}"
                                aria-label="{{ $i }} {{ $i === 1 ? 'Stern' : 'Sterne' }}"
                                class="focus:outline-none focus:ring-2 focus:ring-amber-500 rounded-full transition-transform hover:scale-110">
                            <svg class="h-10 w-10 sm:h-12 sm:w-12 transition-colors"
                                 :class="(hoverRating >= {{ $i }} || (hoverRating === 0 && selectedRating >= {{ $i }})) ? 'text-amber-400' : '{{ $darkAware ? 'text-gray-300 dark:text-gray-700' : 'text-gray-300' }}'"
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
                <span class="block text-sm font-bold {{ $labelClass }} mb-2">Zusammenfassung (optional)</span>
                <input type="text" wire:model="title" maxlength="255" class="{{ $inputClass }}">
                @error('title') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
            </label>

            <label class="w-full block" x-data="{ count: {{ $initialContentLength }} }">
                <div class="flex justify-between items-end mb-2">
                    <span class="block text-sm font-bold {{ $labelClass }}">Dein Feedback</span>
                    <span class="text-[10px] uppercase font-bold tracking-widest transition-colors" :class="count > 950 ? 'text-red-500' : '{{ $darkAware ? 'text-gray-400 dark:text-gray-500' : 'text-gray-400' }}'">
                        <span x-text="count"></span>/1000 Zeichen
                    </span>
                </div>
                <textarea wire:model.live="content" x-on:input="count = $el.value.length" maxlength="1000" rows="5" class="{{ $inputClass }} resize-none" placeholder="Was hat dir besonders gut gefallen?"></textarea>
                @error('content') <span class="text-red-500 text-xs mt-1 block font-medium">{{ $message }}</span> @enderror
            </label>

            <div class="w-full">
                <span class="block text-sm font-bold {{ $labelClass }} mb-2">Bilder &amp; Video hinzufügen (Max. 3 Dateien, davon max. 1 Video)</span>

                <label class="w-full flex flex-col items-center justify-center px-6 py-8 border-2 border-dashed rounded-2xl transition-colors cursor-pointer focus-within:ring-2 focus-within:ring-amber-500"
                       x-data="{ isDropping: false }"
                       @dragover.prevent="isDropping = true"
                       @dragleave.prevent="isDropping = false"
                       @drop.prevent="isDropping = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'));"
                       :class="isDropping ? '{{ $dropzoneActiveClass }}' : '{{ $dropzoneIdleClass }}'">

                    <i class="bi bi-cloud-arrow-up text-3xl {{ $darkAware ? 'text-gray-400 dark:text-gray-500' : 'text-gray-400' }} mb-2 pointer-events-none"></i>
                    <div class="flex text-sm {{ $darkAware ? 'text-gray-600 dark:text-gray-300' : 'text-gray-600' }} justify-center text-center pointer-events-none">
                        <span class="font-medium {{ $darkAware ? 'text-amber-600 dark:text-amber-400 hover:text-amber-500' : 'text-amber-600 hover:text-amber-500' }}">Lade eine Datei hoch</span>
                        <p class="pl-1 hidden sm:block">oder Drag &amp; Drop</p>
                    </div>
                    <p class="text-xs {{ $darkAware ? 'text-gray-500 dark:text-gray-400' : 'text-gray-500' }} mt-1 pointer-events-none text-center">Bilder (JPG, PNG, WEBP bis 5 MB • automatische WebP-Optimierung) • max. 1 Video (MP4, MOV bis 8 MB)</p>
                    <input type="file" x-ref="fileInput" wire:model.live="newMedia" class="sr-only" multiple accept="image/png, image/jpeg, image/webp, video/mp4, video/quicktime">
                </label>

                <div wire:loading wire:target="newMedia" class="text-sm {{ $darkAware ? 'text-amber-600 dark:text-amber-400' : 'text-amber-600' }} mt-3 flex items-center font-bold">
                    <i class="bi bi-arrow-repeat animate-spin mr-2"></i> Dateien werden verarbeitet...
                </div>

                @error('newMedia') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror
                @error('newMedia.*') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror
                @error('accumulatedMedia') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror
                @error('accumulatedMedia.*') <span class="text-red-500 text-xs mt-2 block font-bold">{{ $message }}</span> @enderror

                @if(count($resolvedExistingMedia) > 0 || count($resolvedAccumulatedMedia) > 0)
                    <div class="flex flex-wrap gap-4 mt-6 w-full relative z-10">
                        @foreach($resolvedExistingMedia as $index => $path)
                            @php
                                $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                                $isVideo = in_array($ext, ['mp4', 'mov', 'webm'], true);
                            @endphp
                            <div class="relative w-24 h-24 rounded-xl overflow-hidden border {{ $darkAware ? 'border-gray-200 dark:border-gray-700' : 'border-gray-200' }} shadow-sm group">
                                @if($isVideo)
                                    <div class="w-full h-full bg-gray-900 flex items-center justify-center relative">
                                        <video src="{{ asset('storage/' . ltrim((string) $path, '/')) }}" class="absolute inset-0 w-full h-full object-cover opacity-60"></video>
                                        <i class="bi bi-play-circle text-white text-2xl relative z-10"></i>
                                    </div>
                                @else
                                    <img src="{{ asset('storage/' . ltrim((string) $path, '/')) }}" alt="Bewertungsbild" class="w-full h-full object-cover">
                                @endif
                                <button type="button"
                                        wire:click="removeExistingMedia({{ $index }})"
                                        aria-label="Datei entfernen"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm">
                                    <i class="bi bi-x text-lg"></i>
                                </button>
                            </div>
                        @endforeach

                        @foreach($resolvedAccumulatedMedia as $index => $file)
                            @php
                                $mime = is_object($file) && method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : '';
                                $isVideo = str_contains($mime, 'video');
                            @endphp
                            <div class="relative w-24 h-24 rounded-xl overflow-hidden border border-amber-300 shadow-sm group">
                                @if($isVideo)
                                    <div class="w-full h-full {{ $darkAware ? 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300' : 'bg-gray-100 text-gray-500' }} flex flex-col items-center justify-center">
                                        <i class="bi bi-film text-2xl mb-1"></i>
                                        <span class="text-[10px] uppercase font-bold text-gray-400">Video</span>
                                    </div>
                                @elseif(is_object($file) && method_exists($file, 'isPreviewable') && $file->isPreviewable())
                                    <img src="{{ $file->temporaryUrl() }}" alt="Vorschau" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full {{ $darkAware ? 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-300' : 'bg-gray-100 text-gray-500' }} flex flex-col items-center justify-center">
                                        <span class="text-[10px] uppercase font-bold text-gray-400">Datei</span>
                                    </div>
                                @endif
                                <button type="button"
                                        wire:click="removeAccumulatedMedia({{ $index }})"
                                        aria-label="Neue Datei entfernen"
                                        class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm">
                                    <i class="bi bi-x text-lg"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Anonym bewerten Checkbox --}}
            <div class="{{ $optionBoxBaseClass }} {{ $optionBorderNormalClass }}">
                <label class="flex items-start gap-3.5 cursor-pointer select-none">
                    <div class="pt-0.5 shrink-0">
                        <input type="checkbox" wire:model.live="isAnonymous" class="h-5 w-5 rounded {{ $darkAware ? 'border-gray-300 dark:border-gray-700 dark:bg-gray-900' : 'border-gray-300' }} text-amber-600 focus:ring-amber-500 cursor-pointer transition">
                    </div>
                    <div class="text-xs sm:text-sm leading-relaxed">
                        <span class="font-bold {{ $headingClass }} flex items-center gap-1.5">
                            <i class="bi bi-incognito {{ $darkAware ? 'text-amber-600 dark:text-amber-400' : 'text-amber-600' }}"></i> Anonym bewerten
                        </span>
                        <p class="text-xs {{ $darkAware ? 'text-gray-500 dark:text-gray-400' : 'text-gray-500' }} mt-0.5 leading-normal">
                            Wenn du diesen Haken setzt, werden dein Name, deine Initialen und dein Profilbild nicht öffentlich angezeigt. Deine Rezension erscheint stattdessen geschützt als <strong class="{{ $darkAware ? 'text-gray-700 dark:text-gray-200' : 'text-gray-700' }}">„Anonymer Kunde“</strong>.
                        </p>
                    </div>
                </label>
            </div>

            {{-- Rechtliche Einwilligung (Veröffentlichung & Bildrechte) --}}
            <div class="{{ $optionBoxBaseClass }} @error('acceptReviewTerms') border border-red-400 ring-1 ring-red-400/30 @else {{ $optionBorderNormalClass }} @enderror">
                <label class="flex items-start gap-3.5 cursor-pointer select-none">
                    <div class="pt-0.5 shrink-0">
                        <input type="checkbox" wire:model.live="acceptReviewTerms" class="h-5 w-5 rounded {{ $darkAware ? 'border-gray-300 dark:border-gray-700 dark:bg-gray-900' : 'border-gray-300' }} text-amber-600 focus:ring-amber-500 cursor-pointer transition">
                    </div>
                    <div class="text-xs {{ $darkAware ? 'text-gray-600 dark:text-gray-300' : 'text-gray-600' }} leading-relaxed">
                        Ich bestätige, dass meine Bewertung auf eigener Erfahrung mit dem gekauften Produkt beruht. Sofern ich Fotos oder Videos hochlade, besitze ich sämtliche Bildrechte (keine unbefugte Abbildung Dritter gem. § 22 KUG) und bin mit der öffentlichen Anzeige im Shop sowie auf der Startseite („Was unsere Kunden sagen“) gemäß den <a href="{{ route('agb') }}" target="_blank" class="{{ $darkAware ? 'text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300' : 'text-amber-700 hover:text-amber-900' }} underline font-semibold">AGB (§ 8a)</a> und der <a href="{{ route('datenschutz') }}" target="_blank" class="{{ $darkAware ? 'text-amber-700 dark:text-amber-400 hover:text-amber-900 dark:hover:text-amber-300' : 'text-amber-700 hover:text-amber-900' }} underline font-semibold">Datenschutzerklärung</a> einverstanden. <span class="text-red-500 font-bold">*</span>
                    </div>
                </label>
                @error('acceptReviewTerms') <span class="text-red-500 text-xs mt-1.5 block font-bold">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="pt-6 mt-4 border-t {{ $darkAware ? 'border-amber-200/50 dark:border-gray-800' : 'border-amber-200/50' }} w-full">
            <div class="flex flex-col sm:flex-row items-center gap-4 w-full">
                <button type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto inline-flex justify-center py-3.5 px-10 rounded-xl shadow-md text-sm font-bold text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 transition-all transform hover:-translate-y-0.5 uppercase tracking-widest">
                    <span wire:loading.remove wire:target="{{ $submitAction }}">{{ $isEditing ? 'Änderungen speichern' : 'Bewertung absenden' }}</span>
                    <span wire:loading wire:target="{{ $submitAction }}">Speichert...</span>
                </button>
                @if($showCancel)
                    <button type="button" wire:click="{{ $cancelAction }}" class="{{ $cancelBtnClass }}">
                        Abbrechen
                    </button>
                @endif
            </div>

            <p class="mt-3.5 flex items-center justify-center sm:justify-start gap-2 text-xs sm:text-sm {{ $darkAware ? 'text-gray-600 dark:text-gray-300' : 'text-gray-600' }} font-medium">
                <span class="text-amber-500 text-base leading-none select-none" aria-hidden="true">✨</span>
                <span>
                    Deine ehrliche Bewertung wird mit <strong class="font-bold {{ $darkAware ? 'text-amber-700 dark:text-amber-400' : 'text-amber-700' }}">+{{ \App\Services\Gamification\GameConfig::REVIEW_SPARK_REWARD }} Seelenfunken</strong> belohnt <span class="{{ $darkAware ? 'text-gray-400 dark:text-gray-500' : 'text-gray-400' }} font-normal">(unabhängig von der Sternezahl)</span>
                </span>
            </p>
        </div>
    </form>
</div>
