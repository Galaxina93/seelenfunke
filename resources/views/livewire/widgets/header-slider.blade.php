<div class="relative">
    <div x-data="{
        swiper: null,
        initSwiper() {
            this.swiper = new Swiper('.swiper', window.sliderConfig);
        }
    }" x-init="initSwiper" class="relative">

        <div
            class="swiper overflow-hidden rounded-xl shadow-xl border border-gray-200 bg-white"
            style="width: 100%; height: 600px;">

            <div class="swiper-wrapper">
                @foreach ($slides as $slide)
                    <div class="swiper-slide relative">
                        <img src="{{ $slide['image'] }}"
                             alt="{{ $slide['title'] }}"
                             class="w-full h-full object-cover rounded-xl" />

                        @if ($config['image_title_active'] || $config['image_description_active'])
                            <div class="absolute bottom-0 left-0 right-0 bg-black/60 p-4 text-white rounded-b-xl">
                                @if ($config['image_title_active'])
                                    <h3 class="text-lg font-semibold">{{ $slide['title'] }}</h3>
                                @endif
                                @if ($config['image_description_active'])
                                    <p class="text-sm">{{ $slide['description'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($config['pagination_active'])
                <div class="swiper-pagination !bottom-4"></div>
            @endif

            @if ($config['navigation_active'])
                <div class="swiper-button-prev text-white z-10"></div>
                <div class="swiper-button-next text-white z-10"></div>
            @endif

            @if ($config['scrollbar_active'])
                <div class="swiper-scrollbar"></div>
            @endif
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.sliderConfig = @json($sliderConfig);
            });
        </script>
    </div>
</div>
