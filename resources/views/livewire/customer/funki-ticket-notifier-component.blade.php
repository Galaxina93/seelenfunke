<div x-data="{ show: false, message: '' }"
     x-init="
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Echo !== 'undefined') {
                console.log('Echo ist bereit. Lausche auf Kanal: customer.{{ auth()->guard('customer')->id() }}');

                Echo.private('customer.{{ auth()->guard('customer')->id() }}')
                    .listen('.ticket.message.sent', (e) => {
                        console.log('WS EVENT EMPFANGEN!', e);

                        if (e.message && e.message.sender_type === 'admin') {
                            message = 'Der Support hat auf dein Ticket geantwortet.';
                            show = true;

                            try {
                                let audio = new Audio('/sounds/pop.mp3');
                                audio.volume = 0.3;
                                audio.play();
                            } catch(err) {}

                            setTimeout(() => show = false, 5000);
                        }
                    });
            } else {
                console.error('Echo ist nicht definiert! Bitte stelle sicher, dass app.js geladen wurde.');
            }
        });
     "
     class="fixed bottom-6 right-6 z-[100] transition-all duration-500 ease-out"
     :class="show ? 'translate-y-0 opacity-100' : 'translate-y-10 opacity-0 pointer-events-none'">

    <div class="bg-gray-900/95 backdrop-blur-md border border-gray-700 shadow-2xl rounded-2xl p-4 flex items-center gap-4 max-w-sm">
        <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center shrink-0 border border-primary/30">
            <span class="text-primary text-xl">💌</span>
        </div>
        <div>
            <h4 class="text-white text-sm font-bold tracking-wide">Support Desk</h4>
            <p class="text-gray-400 text-xs mt-0.5" x-text="message"></p>
        </div>
    </div>
</div>
