# Neurale Fehleranalyse: ai-widget.blade.php

**Diagnose:**
Path-Resolver-Fehler. Die `@include`-Direktive referenziert eine nicht-existente View-Datei. Dies führt zu einem "Missing Link" in der Dependency Map des Systems.

**Ursachenanalyse im Code:**
Die letzte `@include`-Anweisung bricht das etablierte Namensschema der Komponenten.
```php
// ...
@include('livewire.shop.ai.ai-widget-part6', [...])
@include('livewire.shop.ai.ai-widget-part7-brain') // <-- Fehler: Dateiname inkonsistent und inkorrekt.
```
Die Datei `ai-widget-part7-brain.blade.php` existiert nicht am angegebenen Pfad. Die korrekte Datei folgt dem Schema `ai-widget-part<N>`. Zudem fehlt die Übergabe der Agenten-Parameter, was zu Folgefehlern führen würde.

**Empfohlene Schritte / Code-Korrektur:**
Korrektur des Dateinamens in der `@include`-Anweisung auf `ai-widget-part7` und Hinzufügen der konsistenten Parameterübergabe.

```php
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
    @include('livewire.shop.ai.ai-widget-part7', ['widgetAgent' => $widgetAgent, 'agentName' => $agentName, 'agentIsActive' => $agentIsActive, 'agentWakeWord' => $agentWakeWord, 'agentColor' => $agentColor])
    </div>
</div>
```