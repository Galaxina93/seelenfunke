<x-sections.page-container>
    <x-sections.page-section>

        @include('livewire.profile.personal_information')
        <div class="border-t border-gray-300 my-8"></div>
        @include('livewire.profile.password_reset_profile')
        <div class="border-t border-gray-300 my-8"></div>
        @livewire('global.auth.two-factor-auth')
        <div class="border-t border-gray-300 my-8"></div>
        @include('livewire.profile.browser-session')
        <div class="border-t border-gray-300 my-8"></div>
        @include('livewire.profile.delete_account')

    </x-sections.page-section>
</x-sections.page-container>
