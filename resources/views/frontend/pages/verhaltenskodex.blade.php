<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">
            <header class="mb-12 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-gray-900">Verhaltenskodex</h1>
            </header>

            <div id="verhaltenskodex" class="scroll-mt-28">
                <h2 class="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-3">
                    <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">III</span>
                    Verhaltenskodex
                </h2>

                <div class="space-y-6 text-gray-700 leading-relaxed">

                    <p>
                        Bei <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> legen wir großen Wert auf ein respektvolles und faires Miteinander.
                        Dieser Verhaltenskodex bildet die Grundlage für den Umgang zwischen Kund:innen, Geschäftspartner:innen
                        sowie unserem gesamten Team unter der Leitung von {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}.
                    </p>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">1. Respektvoller Umgang</h3>
                        <p>
                            Wir begegnen allen Kund:innen, Partner:innen und Mitarbeitenden mit Wertschätzung und Respekt.
                            Gleiches erwarten wir auch im Umgang mit uns.
                            Beleidigendes, herabwürdigendes oder diskriminierendes Verhalten wird nicht akzeptiert.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">2. Offene und transparente Kommunikation</h3>
                        <p>
                            Informationen zu unseren Produkten, Leistungen und Richtlinien kommunizieren wir klar,
                            verständlich und nachvollziehbar.
                            Anfragen bearbeiten wir ehrlich, freundlich und stets lösungsorientiert.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">3. Verantwortungsbewusstsein</h3>
                        <p>
                            Wir erwarten einen verantwortungsvollen und sachlichen Umgang mit unseren Produkten
                            und Dienstleistungen.
                            Rückmeldungen, Kritik oder Beschwerden sollten respektvoll und konstruktiv formuliert werden.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">4. Datenschutz und Vertraulichkeit</h3>
                        <p>
                            Der Schutz personenbezogener Daten hat für uns höchste Priorität.
                            Sämtliche Daten werden vertraulich behandelt und nur dann an Dritte weitergegeben,
                            wenn dies zur Abwicklung einer Bestellung erforderlich ist. Unsere Prozesse in {{ shop_setting('owner_city', '38518 Gifhorn') }} folgen strengen Sorgfaltsmaßstäben.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">5. Nachhaltigkeit und soziale Verantwortung</h3>
                        <p>
                            Wir bemühen uns um möglichst umweltschonende Prozesse und legen auch bei unseren Partner:innen
                            Wert auf nachhaltiges Handeln.
                            Unsere Produkte werden unter fairen Bedingungen hergestellt und vertrieben.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">6. Maßnahmen bei Verstößen</h3>
                        <p>
                            Verstöße gegen diesen Verhaltenskodex können Einschränkungen beim Einkauf,
                            die Sperrung eines Kundenkontos oder weitere geeignete Maßnahmen nach sich ziehen.
                            Respektlose oder beleidigende Kommunikation kann zudem zur Ablehnung von Anfragen führen.
                        </p>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-6">
                        <p class="font-medium text-gray-800">
                            💙 Gemeinsam schaffen wir eine respektvolle und vertrauensvolle Community.
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            Vielen Dank für dein Vertrauen – schön, dass du Teil der <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}-Community</strong> bist.
                        </p>
                        <p class="mt-2 text-sm">
                            📩 Kontakt: <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary hover:underline">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a>
                        </p>
                    </div>

                </div>
            </div>


        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
