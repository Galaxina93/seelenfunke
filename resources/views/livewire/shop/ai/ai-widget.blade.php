<div>
    @php
        $widgetAgent = $widgetAgent ?? null;
        if (!$widgetAgent) {
            $widgetAgent = $agentId
                ? ($availableAgents->firstWhere('id', $agentId) ?? \App\Models\Ai\AiAgent::find($agentId))
                : ($availableAgents->firstWhere('name', 'Funkira') ?? $availableAgents->first());
        }
        $agentName = $widgetAgent ? $widgetAgent->name : 'Funkira';
        $agentIsActive = $widgetAgent ? (bool) $widgetAgent->is_active : false;
        $agentWakeWord = $widgetAgent ? strtolower($widgetAgent->wake_word ?? $agentName) : 'funkira';
        $agentColor = $widgetAgent ? $widgetAgent->color : 'emerald-500';
        $agentProfilePicture = $widgetAgent ? $widgetAgent->profile_picture_url : '';
        $isAdminUser = $isAdminUser ?? \App\Services\AI\AiAuthHelper::isAdmin();
        $chatSessions = $chatSessions ?? $this->chatSessions();
    @endphp

    <!-- HIDDEN STREAM TARGET FOR FRONTEND EVENTS (Outside wire:ignore) -->
    <div wire:stream="thought_{{ $agentId ?? 'system' }}" style="display:none;"></div>

    @include('livewire.shop.ai.ai-widget-part1', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor, 'agentProfilePicture' => $agentProfilePicture, 'availableAgents' => $availableAgents, 'chatSessions' => $chatSessions, 'isAdminUser' => $isAdminUser])
    @include('livewire.shop.ai.ai-widget-part2', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor, 'agentProfilePicture' => $agentProfilePicture, 'availableAgents' => $availableAgents, 'chatSessions' => $chatSessions, 'isAdminUser' => $isAdminUser])
    @include('livewire.shop.ai.ai-widget-part3', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part4', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part5', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part6', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part7-brain')
</div>
