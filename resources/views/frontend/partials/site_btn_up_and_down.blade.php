{{-- To Top & Bottom Buttons + Cookie-Warner --}}
<div class="fixed right-2 bottom-4 z-50 flex flex-col items-end space-y-4">

    {{-- To Top Btn --}}
    <div onclick="topFunction()" id="toTopBtn" title="Nach oben" class="hidden">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="text-white cursor-pointer h-10 w-10 transform hover:scale-105 duration-300 rounded-full bg-primary bg-opacity-60 hover:bg-opacity-90 border border-secondary shadow-md"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
        </svg>
    </div>

    {{-- To Bottom Btn --}}
    <div onclick="scrollToBottom()" id="toBottomBtn" title="Nach unten" class="hidden">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="text-white cursor-pointer h-10 w-10 transform hover:scale-105 duration-300 rounded-full bg-primary bg-opacity-60 hover:bg-opacity-90 border border-secondary shadow-md"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/>
        </svg>
    </div>

    {{-- Cookie-Warner --}}
    <div id="open-cookie-settings" title="Cookie-Einstellungen öffnen"
         class="cursor-pointer h-10 w-10 transform hover:scale-105 duration-300 rounded-full bg-primary bg-opacity-60 hover:bg-opacity-90 border border-secondary flex items-center justify-center shadow-md">
        <img src="{{ URL::to('/images/cookie/cookie.svg') }}" alt="Cookie Icon" class="h-6 w-6 opacity-80 hover:opacity-100">
    </div>

</div>


<script>
    const toTopBtn = document.getElementById("toTopBtn");
    const toBottomBtn = document.getElementById("toBottomBtn");

    window.addEventListener("scroll", function () {
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        const windowHeight = window.innerHeight;
        const fullHeight = document.documentElement.scrollHeight;

        // Show ToTopBtn when scrolled down
        if (scrollTop > 800) {
            toTopBtn.style.display = "block";
        } else {
            toTopBtn.style.display = "none";
        }

        // Show ToBottomBtn when not near the bottom
        if (scrollTop + windowHeight < fullHeight - 800) {
            toBottomBtn.style.display = "block";
        } else {
            toBottomBtn.style.display = "none";
        }
    });

    function topFunction() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    function scrollToBottom() {
        window.scrollTo({
            top: document.body.scrollHeight,
            behavior: 'smooth'
        });
    }


    /*Cookiewarner*/
    document.getElementById('open-cookie-settings').addEventListener('click', function () {
        CookieConsent.showPreferences();
    });


</script>
