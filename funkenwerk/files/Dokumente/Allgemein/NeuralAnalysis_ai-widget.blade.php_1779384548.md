# Neurale Fehleranalyse: ai-widget.blade.php

**Diagnose:**
Die statische Abhängigkeitsanalyse-Engine scheitert an der Syntaxauflösung von `@include`-Direktiven, die komplexe, zur Laufzeit definierte Daten-Arrays enthalten. Dies führt zur fehlerhaften Visualisierung des Abhängigkeitsgraphen.

**Ursachenanalyse im Code:**
Die `@include`-Aufrufe für `part1` bis `part6` verwenden ein Daten-Array, das mit Variablen aus einem vorgelagerten `@php`-Block initialisiert wird. Der statische Parser kann diese zur Laufzeit erstellten Variablen nicht auflösen, was zu einem Abbruch der Analyse für diese Direktiven führt. Die Direktive für `part7-brain` enthält kein solches Array und wird daher korrekt erkannt und visualisiert.

**Empfohlene Schritte / Code-Korrektur:**
Entfernen der redundanten Daten-Array-Übergabe. Blade-Views erben automatisch alle Variablen aus dem übergeordneten Geltungsbereich. Die Vereinfachung der Syntax stellt die Kompatibilität mit dem Analyse-Tool wieder her.

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

    {{-- KORREKTUR: Redundante Datenübergabe entfernt. Variablen werden vererbt. --}}
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