<div>
    @php
        // Dynamically fetch the requested agent, fallback to Funkira, otherwise any active agent.
        if ($agentId) {
            $widgetAgent = \App\Models\Ai\AiAgent::find($agentId);
        } else {
            $widgetAgent = \App\Models\Ai\AiAgent::where('name', 'Funkira')->where('is_active', true)->first() 
                ?? \App\Models\Ai\AiAgent::where('is_active', true)->first();
        }
        $agentName = $widgetAgent ? $widgetAgent->name : 'Funkira';
        $agentIsActive = $widgetAgent ? (bool) $widgetAgent->is_active : false;
        $agentWakeWord = $widgetAgent ? strtolower($widgetAgent->wake_word ?? $agentName) : 'funkira';
        $agentColor = $widgetAgent ? $widgetAgent->color : 'emerald-500';
    @endphp

    <!-- HIDDEN STREAM TARGET FOR FRONTEND EVENTS (Outside wire:ignore) -->
    <div wire:stream="thought_{{ $agentId ?? 'system' }}" style="display:none;"></div>

    @include('livewire.shop.ai.ai-widget-part1', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor, 'availableAgents' => $availableAgents])
    @include('livewire.shop.ai.ai-widget-part2', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part3', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part4', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part5', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part6', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    @include('livewire.shop.ai.ai-widget-part7-brain')
</div>
