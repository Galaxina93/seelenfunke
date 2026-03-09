<script>
    // 1. Audio Engine
    @include('livewire.customer.partials.games-component.scripts.audio')

    // 2. 3D Game Engine (Three.js Logik)
    @include('livewire.customer.partials.games-component.scripts.engine')
    @include('livewire.customer.partials.games-component.scripts.funkenflug-engine')

    // 3. Alpine.js UI State Manager
    @include('livewire.customer.partials.games-component.scripts.alpine')
    @include('livewire.customer.partials.games-component.scripts.funkenflug-alpine')
</script>
