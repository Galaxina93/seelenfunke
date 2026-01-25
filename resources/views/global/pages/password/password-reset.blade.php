<x-layouts.guest>
    <section class="dark:bg-gray-900">
        <section class="dark:bg-gray-900">
            <div class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">
                <a href="/" class="flex items-center mb-6 text-2xl font-semibold text-gray-900 dark:text-white">
                    <img class="mx-auto h-64 w-auto" src="{{ URL::to('/images/fmi/cropped-logo-felix.png') }}" alt="facturio">
                </a>
                <div class="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">

                    @livewire('global.password.password-reset', ['token' => $token])

                </div>
            </div>
        </section>
    </section>
</x-layouts.guest>
