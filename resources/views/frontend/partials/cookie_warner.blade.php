<!-- Fingerprint-Icon -->
<div id="trigger" class="right-2 bottom-6 fixed z-50">
    <x-heroicon-m-finger-print class="p-1 text-white cursor-pointer h-10 w-10 transform hover:scale-105 duration-300 rounded-full bg-primary border border-secondary"/>
</div>

<!-- Modal -->
<div id="modal" x-data="{ open: false }" {{ $attributes }}>
    <div x-show="open" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-60">
        <div class="relative bg-white bg-opacity-90 w-full h-full p-6 sm:p-8 md:p-10 lg:p-12 xl:w-auto xl:h-auto xl:rounded-lg">
            <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
                <div class="text-sm mb-4 md:mb-0">
                    <h2 class="font-bold">Datenschutzeinstellungen</h2>
                    <p>Auf unserer Webseite werden Cookies verwendet. Einige davon werden zwingend benötigt, während es uns andere ermöglichen, Ihre Nutzererfahrung auf unserer Webseite zu verbessern. Hinweis zur Datenverarbeitung in den USA durch Google: Indem Sie auf "Allen zustimmen" klicken, willigen Sie gem. Art. 6 Abs. 1 S. 1 lit. a) DSGVO ein, dass Ihre Daten in den USA verarbeitet werden. Der Datenschutzstandard in den USA ist nach Ansicht des EuGHs unzureichend und es besteht die Gefahr, dass Ihre Daten durch die US-Behörden zu Kontroll- und Überwachungszwecken, möglicherweise auch ohne Rechtsbehelfsmöglichkeiten, verarbeitet werden. Wenn Sie nur dem Setzen von essenziellen Cookies zustimmen, findet die Übermittlung nicht statt. Eine erteilte Einwilligung kann jederzeit widerrufen werden.</p>
                </div>
            </div>
            <div class="flex flex-col md:flex-row gap-2">
                <button id="accept-all" class="bg-white text-primary py-1 px-3 rounded">Alle akzeptieren</button>
                <button id="save-and-close" class="bg-white text-primary py-1 px-3 rounded">Speichern & schließen</button>
                <button id="accept-essential" class="bg-white text-primary py-1 px-3 rounded">Nur essentielle Cookies akzeptieren</button>
            </div>
        </div>
    </div>
</div>


<script>

    // Open Modal by clicking fingerprint Icon
    const myDiv = document.getElementById('modal');
    const openButton = document.getElementById('trigger');

    openButton.addEventListener('click', () => {
        myDiv.__x.$data.open = true;
    });

    // Cookie Warner Config
    document.addEventListener("DOMContentLoaded", () => {

        // Überprüfen Sie, ob der Benutzer Cookies bereits akzeptiert hat
        if (!document.cookie.includes("cookies_accepted=")) {
            myDiv.__x.$data.open = true;
        }

        const acceptAll = document.getElementById("accept-all");
        const saveAndClose = document.getElementById("save-and-close");
        const acceptEssential = document.getElementById("accept-essential");

        acceptAll.addEventListener("click", () => {
            document.cookie = "cookies_accepted=all; max-age=31536000; path=/";
            myDiv.__x.$data.open = false;
        });

        saveAndClose.addEventListener("click", () => {
            // Hier können Sie die ausgewählten Einstellungen speichern
            myDiv.__x.$data.open = false;
        });

        acceptEssential.addEventListener("click", () => {
            document.cookie = "cookies_accepted=essential; max-age=31536000; path=/";
            myDiv.__x.$data.open = false;
        });

        /*
        *
        * CONTENT BLOCKING
        *
        *
        * */

        // HTML
        /*<!-- Video-Container -->
        <div id="video-container" style="display: none;">
            <!-- Fügen Sie hier Ihren Video-Code ein -->
        </div>*/

        /*// Überprüfen Sie, ob der Benutzer Cookies bereits akzeptiert hat
        if (document.cookie.includes("cookies_accepted=")) {
            loadVideo();
        }

        acceptAll.addEventListener("click", () => {
            document.cookie = "cookies_accepted=all; max-age=31536000; path=/";
            myDiv.__x.$data.open = false;
            loadVideo();
        });

        // Wenn Sie möchten, dass das Video auch bei essentiellen Cookies geladen wird
        acceptEssential.addEventListener("click", () => {
            document.cookie = "cookies_accepted=essential; max-age=31536000; path=/";
            myDiv.__x.$data.open = false;
            loadVideo();
        });*/

    });


</script>



