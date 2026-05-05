<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="h-auto min-h-[calc(100dvh-4rem)] lg:h-[calc(100vh-6rem)] w-full font-mono text-[var(--theme-color)] flex flex-col pt-4 overflow-hidden relative"
         x-data="{
            activeTab: @entangle('activeTab').live,
            showWorkspaceMobile: false,
            isChatFullScreen: false,
        init() {
            this.scrollToBottom();
            $wire.$watch('messages', () => { setTimeout(() => this.scrollToBottom(), 50) });
            $wire.$watch('typingAgents', () => { setTimeout(() => this.scrollToBottom(), 50) });
        },
        scrollToBottom() {
            let el = document.getElementById('chat-scroll-container');
            if(el) el.scrollTop = el.scrollHeight;
        }
     }">

    <!-- Neon Header -->
    <div x-show="!isChatFullScreen" class="text-center mb-4 lg:mb-6 shrink-0 relative z-10 w-full px-4 lg:px-6">
        <h1 class="text-3xl font-black tracking-widest uppercase shadow-[var(--theme-color-20)] drop-shadow-md text-[var(--theme-color)]">KI-Zentrale</h1>
        <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Multi-Agenten Arbeitsfläche & Kommunikation</p>

    @include('livewire.shop.ai.partials-ai-workspace.sidebar-agents')

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 bg-black/90">
        <!-- Top Navigation Area -->
        <div class="flex-1 flex flex-col overflow-hidden p-2">

            @if($activeWorkspaceView === 'knowledge-base')
                @include('livewire.shop.ai.partials-ai-workspace.view-knowledge-base')
            @elseif($activeWorkspaceView === 'gen-ui')
                @include('livewire.shop.ai.partials-ai-workspace.view-gen-ui')
            @elseif($activeWorkspaceView === 'settings')
                @include('livewire.shop.ai.partials-ai-workspace.view-settings')
            @else
                <div wire:key="workspace-main-view" class="flex-1 flex flex-col gap-4 overflow-hidden h-full w-full">
                    @include('livewire.shop.ai.partials-ai-workspace.navigation-tabs')
                    @include('livewire.shop.ai.partials-ai-workspace.tab-workspace')
                    @include('livewire.shop.ai.partials-ai-workspace.tab-chat')
                    @include('livewire.shop.ai.partials-ai-workspace.tab-files')
                    @include('livewire.shop.ai.partials-ai-workspace.tab-health')
                    @include('livewire.shop.ai.partials-ai-workspace.tab-workflows')
                    <div :class="{'hidden': activeTab !== 'cronjobs'}" class="flex-1 shrink-0 h-full w-full overflow-hidden">
                        @livewire('shop.ai.partials-ai-workspace.tab-cronjobs')
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@include('livewire.shop.ai.partials-ai-workspace.modals-and-scripts')
