<script>
    // 1. Audio Engine
    @include('livewire.customer.partials.games-component.scripts.kristall-kollaps-audio')

    // 2. 3D Game Engine (Three.js Logik)
    @include('livewire.customer.partials.games-component.scripts.kristall-kollaps-engine')
    @include('livewire.customer.partials.games-component.scripts.funkenflug-engine')

    // 3. Alpine.js UI State Manager
    @include('livewire.customer.partials.games-component.scripts.kristall-kollaps-alpine')
    @include('livewire.customer.partials.games-component.scripts.funkenflug-alpine')
</script>
