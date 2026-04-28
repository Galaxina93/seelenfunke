<div>
    <x-sections.page-container>
        
        @if(view()->exists('frontend.pages.landingpages.' . $landingPage->slug))
            @include('frontend.pages.landingpages.' . $landingPage->slug)
        @else
        <section id="landing-hero"
                 class="relative pt-16 overflow-hidden text-white bg-gray-950 min-h-screen flex items-center"
                 x-data
                 x-init="setTimeout(() => { if(window.startUniverseEngine) window.startUniverseEngine($el, 200) }, 100)">

            {{-- INTERAKTIVES UNIVERSUM CANVAS --}}
            <canvas class="absolute inset-0 z-0 w-full h-full pointer-events-none" wire:ignore></canvas>

            {{-- Zartes Overlay für bessere Lesbarkeit des Textes --}}
            <div class="absolute inset-0 bg-gradient-to-b from-gray-950/60 via-gray-950/80 to-gray-950 z-0 pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 w-full">
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    
                    {{-- Text Content Left --}}
                    <div class="text-center lg:text-left">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/20 text-primary text-sm font-semibold mb-6 floating-animation" style="animation-duration: 4s;">
                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                            Exklusives Angebot
                        </div>

                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-serif font-bold mb-6 leading-tight">
                            <span class="text-primary">{{ $landingPage->title }}</span><br>
                            {{ $landingPage->headline }}
                        </h1>

                        <div class="text-lg md:text-xl mb-10 opacity-90 font-light prose prose-invert prose-p:leading-relaxed max-w-2xl mx-auto lg:mx-0">
                            {!! nl2br($landingPage->sales_copy) !!}
                        </div>

                        {{-- Buttons --}}
                        <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center mb-10">
                            @if($landingPage->product)
                                <a href="{{ route('product.show', $landingPage->product->slug) }}"
                                   class="bg-primary text-white px-8 py-4 rounded-full font-semibold text-lg hover:bg-white hover:text-primary-dark transition-all transform hover:scale-105 shadow-[0_0_20px_rgba(var(--color-primary-rgb),0.4)] pulse-button flex items-center justify-center gap-2 w-full sm:w-auto">
                                    {{ $landingPage->cta_text }}
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                </a>
                            @endif
                        </div>

                        {{-- Positive Punkte (USP) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mt-8 w-full max-w-3xl mx-auto lg:mx-0">
                            {{-- Point 1 --}}
                            <div class="flex flex-col items-center lg:items-start text-center lg:text-left bg-white/5 backdrop-blur-sm rounded-2xl p-5 border border-white/10 hover:bg-white/10 hover:border-primary/30 transition-all duration-300">
                                <span class="text-3xl mb-3 block transform hover:scale-110 transition-transform">🛡️</span>
                                <span class="block text-white font-bold text-sm mb-1">Premium Qualität</span>
                                <span class="block text-gray-400 text-xs leading-relaxed">Manuelle Endkontrolle in der Manufaktur</span>
                            </div>

                            {{-- Point 2 --}}
                            <div class="flex flex-col items-center lg:items-start text-center lg:text-left bg-white/5 backdrop-blur-sm rounded-2xl p-5 border border-white/10 hover:bg-white/10 hover:border-primary/30 transition-all duration-300">
                                <span class="text-3xl mb-3 block transform hover:scale-110 transition-transform">✨</span>
                                <span class="block text-white font-bold text-sm mb-1">Handveredelt</span>
                                <span class="block text-gray-400 text-xs leading-relaxed">Persönlich für dich gelasert</span>
                            </div>

                            {{-- Point 3 --}}
                            <div class="flex flex-col items-center lg:items-start text-center lg:text-left bg-white/5 backdrop-blur-sm rounded-2xl p-5 border border-white/10 hover:bg-white/10 hover:border-primary/30 transition-all duration-300">
                                <span class="text-3xl mb-3 block transform hover:scale-110 transition-transform">📦</span>
                                <span class="block text-white font-bold text-sm mb-1">Sorgfältig verpackt</span>
                                <span class="block text-gray-400 text-xs leading-relaxed">Liebevoll & sicher gepolstert</span>
                            </div>
                        </div>
                    </div>

                    {{-- Product Image Right --}}
                    @if($landingPage->product && count($landingPage->product->media_gallery) > 0)
                        <div class="relative w-full max-w-lg mx-auto lg:mt-0 mt-12 perspective-1000">
                            {{-- Decorative Background Ring --}}
                            <div class="absolute inset-0 bg-primary/20 rounded-full blur-3xl transform scale-110"></div>
                            
                            {{-- Main Product Card --}}
                            <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-white/10 bg-gray-900/50 backdrop-blur-sm transform transition hover:scale-105 duration-500 flex flex-col group">
                                <div class="aspect-square w-full overflow-hidden relative">
                                    <img src="{{ asset('storage/' . $landingPage->product->media_gallery[0]['path']) }}" 
                                         alt="{{ $landingPage->product->name }}" 
                                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                                    
                                    {{-- Price Tag Badge Overlay --}}
                                    <div class="absolute bottom-4 right-4 bg-gray-950/90 backdrop-blur-md border border-primary/50 text-white px-4 py-3 rounded-xl shadow-[0_0_15px_rgba(0,0,0,0.5)] text-center">
                                        <span class="block text-2xl font-bold whitespace-nowrap">{{ $landingPage->product->formatted_price }}</span>
                                        <span class="block text-[10px] text-gray-300 font-normal mt-1 uppercase tracking-wider leading-tight">
                                            inkl. MwSt.<br>
                                            <a href="{{ route('versand') }}" target="_blank" class="underline hover:text-white transition-colors">zzgl. Versand</a>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Floating Decorative Elements --}}
                            <div class="absolute -top-6 -right-6 w-24 h-24 bg-primary/30 rounded-full blur-xl floating-animation"></div>
                            <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-primary-light/20 rounded-full blur-xl floating-animation" style="animation-delay: 2s;"></div>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Dekorative Glow-Effekte (Bottom/Top Corners) --}}
            <div class="absolute top-20 left-10 w-40 h-40 bg-primary opacity-20 blur-[100px] rounded-full pointer-events-none z-0"></div>
            <div class="absolute bottom-10 right-10 w-64 h-64 bg-primary text-primary opacity-20 blur-[120px] rounded-full pointer-events-none z-0"></div>
        </section>
        @endif

    </x-sections.page-container>
</div>
