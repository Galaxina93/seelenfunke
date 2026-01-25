<div>
    <div class="contact-item">

        <section id="contact" class="bg-gray-100 py-20 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-10">

                    <div class="relative overflow-hidden py-10 px-4 sm:px-8 md:px-10 lg:px-12 xl:p-12 bg-primary text-indigo-50 rounded-2xl">
                        <h3 class="text-xl sm:text-2xl font-semibold mb-4">Kontakt Informationen</h3>
                        <p class="text-sm sm:text-base mb-6 leading-relaxed">
                            Haben Sie noch Fragen oder Anregungen?<br>
                            Nehmen Sie gerne Kontakt mit uns auf. Wir beraten Sie persönlich und individuell.
                        </p>

                        <dl class="space-y-6 text-sm sm:text-base">
                            <div class="flex items-start sm:items-center">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-200 mr-3 mt-1 sm:mt-0" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <a href="mailto:kontakt@mein-seelenfunke.de" class="hover:text-indigo-300 break-all">kontakt@mein-seelenfunke.de</a>
                            </div>
                        </dl>

                        <div class="mt-10">
                            <h4 class="text-lg sm:text-xl font-semibold mb-3">Folgen Sie uns auf Social Media</h4>
                            <div class="flex space-x-4">
                                <a href="https://www.instagram.com/Mein_Seelenfunke/" target="_blank" rel="noopener noreferrer"
                                   class="flex items-center space-x-2 hover:text-indigo-300 transition-colors">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-200" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M7.75 2h8.5A5.75 5.75 0 0122 7.75v8.5A5.75 5.75 0 0116.25 22h-8.5A5.75 5.75 0 012 16.25v-8.5A5.75 5.75 0 017.75 2zm0 1.5A4.25 4.25 0 003.5 7.75v8.5A4.25 4.25 0 007.75 20.5h8.5a4.25 4.25 0 004.25-4.25v-8.5A4.25 4.25 0 0016.25 3.5h-8.5zm4.25 3.75a4.5 4.5 0 110 9 4.5 4.5 0 010-9zm0 1.5a3 3 0 100 6 3 3 0 000-6zm4.75-.88a1.12 1.12 0 110 2.25 1.12 1.12 0 010-2.25z"/>
                                    </svg>
                                    <span>Instagram</span>
                                </a>

                                <a href="https://www.tiktok.com/@mein_seelenfunke" target="_blank" rel="noopener noreferrer"
                                   class="flex items-center space-x-2 hover:text-indigo-300 transition-colors">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-200" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                                    </svg>
                                    <span>TikTok</span>
                                </a>
                            </div>
                        </div>
                    </div>


                    <div class="bg-white rounded-2xl shadow-md p-8">
                        <h4 class="text-3xl font-bold text-gray-900 text-center mb-8">Kontaktformular</h4>
                        <form wire:submit.prevent="sending" class="space-y-8">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-900">Vorname</label>
                                    <input wire:model="first_name" type="text" id="first_name" autocomplete="given-name" required
                                           class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 shadow-sm focus:border-secondary-500 focus:ring-secondary-500 focus:bg-white transition">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-900">Nachname</label>
                                    <input wire:model="last_name" type="text" id="last_name" autocomplete="family-name" required
                                           class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 shadow-sm focus:border-secondary-500 focus:ring-secondary-500 focus:bg-white transition">
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-900">E-Mail</label>
                                    <input wire:model="email" type="email" id="email" required
                                           class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 shadow-sm focus:border-secondary-500 focus:ring-secondary-500 focus:bg-white transition">
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-900">Telefon</label>
                                    <input wire:model="phone" type="text" id="phone"
                                           class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 shadow-sm focus:border-secondary-500 focus:ring-secondary-500 focus:bg-white transition">
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="message" class="block text-sm font-medium text-gray-900">Nachricht</label>
                                    <span class="text-sm text-gray-500">Max. {{ $message_length }}/500</span>
                                </div>
                                <textarea wire:model="message" id="message" rows="5" maxlength="500" required
                                          class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 text-gray-900 shadow-sm focus:border-secondary-500 focus:ring-secondary-500 focus:bg-white transition"></textarea>
                            </div>

                            <div class="flex items-start gap-3 text-sm">
                                <input wire:model="data_protection" type="checkbox" id="checkbox-2" required
                                       class="mt-1 border-gray-300 text-secondary-600 rounded focus:ring-secondary-500">
                                <label for="checkbox-2" class="text-gray-700 leading-5">
                                    Ich habe die <a href="/datenschutz" target="_blank" class="text-secondary-600 hover:underline font-semibold">Datenschutzbestimmungen</a> gelesen und erkenne diese ausdrücklich an.
                                </label>
                            </div>

                            <div class="pt-4 flex justify-end">
                                @if($data_protection != 1)
                                    <x-forms.button title="Nachricht senden" category="secondary" type="submit"/>
                                @else
                                    <x-forms.button title="Nachricht senden" category="primary" type="submit"/>
                                @endif
                            </div>

                            @if (session()->has('message'))
                                <div class="mt-4 text-center text-green-500 font-medium">
                                    {{ session('message') }}
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </section>


    </div>
</div>
