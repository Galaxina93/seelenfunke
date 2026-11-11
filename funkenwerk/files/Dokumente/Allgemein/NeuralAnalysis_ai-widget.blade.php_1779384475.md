# Neurale Fehleranalyse: ai-widget.blade.php

### Diagnose
Fehlende neurale Konnektivität zwischen `ai-widget.blade.php` und den Sub-Knoten `part1` bis `part6`. Ursache: Inkompatibilität der dynamischen Datenübergabe via assoziativem Array im `@include`-Statement mit dem statischen Analyse-Algorithmus des Neural-Scanners.

### Ursachenanalyse im Code
Die explizite Übergabe des Daten-Arrays `['widgetAgent' => $widgetAgent, ...]` an die `@include`-Direktiven für `part1` bis `part6` blockiert die statische Code-Analyse. Der Scanner kann die Abhängigkeit nicht auflösen, da die Variableninhalte zur Analysezeit nicht determiniert sind. Die Direktive `@include('livewire.shop.ai.ai-widget-part7-brain')` wird korrekt erkannt, da sie keine dynamischen Parameter enthält.

Blade `@include` erfordert keine explizite Parameterübergabe; Sub-Views erben den Variablen-Scope des Parent-Views. Die Übergabe ist redundant.

### Empfohlene Schritte / Code-Korrektur
Entfernen der redundanten Daten-Arrays aus den `@include`-Aufrufen.

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

    @include('livewire.shop.ai.ai-widget-part1')
    @include('livewire.shop.ai.ai-widget-part2')
    @include('livewire.shop.ai.ai-widget-part3')
    @include('livewire.shop.ai.ai-widget-part4')
    @include('livewire.shop.ai.ai-widget-part5')
    @include('livewire.shop.ai.ai-widget-part6')
    @include('livewire.shop.ai.ai-widget-part7-brain')
    </div>
</div>
```