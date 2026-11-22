<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-60: {{ $this->themeColorHex }}99; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="h-auto min-h-[calc(100dvh-4rem)] lg:h-[calc(100vh-6rem)] w-full font-mono text-[var(--theme-color)] flex flex-col pt-2 overflow-hidden relative"
     @request-clipboard.window="readClipboard()"
     @write-clipboard.window="writeClipboard($event.detail.text)"
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
        },
        clipboardNeedsPermission: false,
        async readClipboard(isDirectClick = false) {
            try {
                this.clipboardNeedsPermission = false;
                const clipboardItems = await navigator.clipboard.read();
                for (const item of clipboardItems) {
                    if (item.types.some(type => type.startsWith('image/'))) {
                        const imageType = item.types.find(type => type.startsWith('image/'));
                        const blob = await item.getType(imageType);
                        const reader = new FileReader();
                        reader.onloadend = () => {
                            this.$wire.call('submitClipboardImage', reader.result, 'clipboard_image.png', imageType);
                        };
                        reader.readAsDataURL(blob);
                        return;
                    }
                }
                
                // Fallback for text
                const text = await navigator.clipboard.readText();
                if (text && text.trim().length > 0) {
                    this.$wire.call('saveUserLiveMessage', '*(Text aus Zwischenspeicher eingefügt)*\n\n' + text);
                    setTimeout(() => {
                        this.$wire.call('processAutoRouting');
                    }, 200);
                    return;
                }
                
                console.warn('Kein Bild oder Text im Zwischenspeicher gefunden.');
                this.$wire.call('saveUserLiveMessage', '*(System: Der Zwischenspeicher war leer, es konnte nichts ausgelesen werden.)*');
                setTimeout(() => { this.$wire.call('processAutoRouting'); }, 200);
            } catch (error) {
                console.error('Clipboard Zugriff verweigert oder Fehler:', error);
                if (error.name === 'NotAllowedError' || error.message.includes('gesture')) {
                    this.clipboardNeedsPermission = true;
                    this.scrollToBottom();
                    this.$wire.call('saveUserLiveMessage', '*(System: Dem Browser fehlt die User-Interaktion (Sicherheitssperre) für den Zwischenspeicher. Bitte weise den Nutzer an: Klicke bitte auf den blinkenden Button für den Zwischenspeicher, der gerade aufgetaucht ist!)*');
                } else {
                    this.$wire.call('saveUserLiveMessage', '*(System: Der Zugriff auf den Zwischenspeicher wurde vom Browser verweigert oder ist leer.)*');
                }
                setTimeout(() => { this.$wire.call('processAutoRouting'); }, 200);
            }
        },
        async writeClipboard(text) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    return;
                }
            } catch (error) {
                console.warn('Clipboard API Fallback aktiv:', error);
            }
            try {
                const ta = document.createElement('textarea');
                ta.value = text ?? '';
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            } catch (fallbackError) {
                console.error('Clipboard Write Error:', fallbackError);
            }
        }
     }">

    <!-- Main Workspace Container -->
    <div class="flex-1 flex flex-col gap-4 lg:gap-6 px-4 lg:px-6 pb-4 lg:pb-6 overflow-hidden relative"
         x-data="workspaceCanvas()">

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-transparent min-h-0 h-full">
            <!-- Top Navigation Area -->
            <div class="flex-1 flex flex-col overflow-hidden p-2 min-h-0 h-full">

                @if($activeWorkspaceView === 'knowledge-base')
                    @include('livewire.shop.ai.partials-ai-workspace.view-knowledge-base')
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
    @include('livewire.shop.ai.partials-ai-workspace.modal-internal-agents')
    @include('livewire.shop.ai.partials-ai-workspace.modals-and-scripts')
    </div>
</div>
