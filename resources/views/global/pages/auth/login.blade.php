<x-layouts.guest>
    <section class="dark:bg-gray-900 min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-xl">

            {{-- Logo --}}
            <a href="/" class="flex justify-center mb-8">
                <img class="h-56" src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="mein-seelenfunke">
            </a>

            {{-- Login Card --}}
            <div
                x-data="{
                    tabs: ['customer', 'employee', 'admin'],
                    labels: { customer: 'Kunde', employee: 'Mitarbeiter', admin: 'Admin' },
                    selected: 'customer'
                }"
                class="bg-white dark:bg-gray-800 shadow rounded-lg p-6"
            >

                {{-- Tab Buttons --}}
                <div class="relative flex rounded-lg bg-gray-100 dark:bg-gray-700 overflow-hidden mb-6">
                    <template x-for="tab in tabs" :key="tab">
                        <button
                            @click="selected = tab"
                            type="button"
                            class="w-1/3 py-2 text-sm font-medium transition"
                            :class="{
                                'bg-primary text-white': selected === tab,
                                'text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600': selected !== tab
                            }"
                            x-text="labels[tab]"
                        ></button>
                    </template>
                </div>

                {{-- Tab Content --}}
                <div>
                    <template x-if="selected === 'admin'">
                        <div>
                            @livewire('global.auth.login', ['guard' => 'admin'])
                        </div>
                    </template>

                    <template x-if="selected === 'employee'">
                        <div>
                            @livewire('global.auth.login', ['guard' => 'employee'])
                        </div>
                    </template>

                    <template x-if="selected === 'customer'">
                        <div>
                            @livewire('global.auth.login', ['guard' => 'customer'])
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>
</x-layouts.guest>
