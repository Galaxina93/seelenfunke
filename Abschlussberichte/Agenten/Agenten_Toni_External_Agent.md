# Abschlussbericht: Entfernung des externen TTS-Agenten (Toni / XTTSv2)

## Hintergrund & Zielsetzung
In der ursprünglichen Architektur der Seelenfunke-Applikation war ein externer KI-Sprachagent ("Toni") integriert, der auf Basis von Coqui XTTSv2 über einen lokalen Zweitrechner (IP: `192.168.188.32:8000`) bereitgestellt wurde. Das Ziel dieser Implementierung war es, eine lokale, authentische Text-to-Speech (TTS) Synthese zur Verfügung zu stellen. 

Obwohl diese Lösung funktional und charmant war, hat sich im Produktiv- und Entwicklungsbetrieb herausgestellt, dass die Latenzen für eine flüssige, synchrone Sprach-KI-Erfahrung zu hoch sind. Aus Performance-Gründen wurde daher beschlossen, "Toni" sowie die gesamte dazugehörige externe Schnittstellen-Logik auszubauen und das System exklusiv auf die native, weitaus schnellere Google Gemini TTS-Infrastruktur umzustellen.

## Bestandteile der entfernten Architektur

Folgende wesentliche Komponenten bildeten das externe System und wurden im Rahmen der Bereinigung restlos aus der Codebasis entfernt:

### 1. `app/Livewire/Shop/Ai/ExternalAgentManager.php`
- Diente als Dashboard/Übersicht für externe Agenten.
- Beinhaltete Polling/Ping-Logik (`fetchStatus()`), um die Erreichbarkeit des lokalen Python-TTS-Servers über Port `8000` zu verifizieren.

### 2. `app/Livewire/Shop/Ai/ExternalAgentEditor.php`
- Zuständig für die Konfiguration des TTS-Systems von "Toni" via API-Schnittstelle.
- Ermöglichte die ferngesteuerte Anpassung von System-Prompts, LLM-Hoster, Temperatur-Werten und die Auswahl verschiedener Stimm-Profile ("ceo", "kollege", "feierabend").
- Die Konfiguration wurde mittels Patch-Requests direkt an den lokalen Server gesendet.

### 3. Anpassungen im `AIController.php` (TTS-Streaming)
- Der Controller besaß einen großen Auswertungs-Block für `$ttsProvider === 'toni_xttsv2'`.
- Dort wurde der reine Text aus der KI-Antwort extrahiert, von Markdown befreit (bzw. auf `<speak>` Tags reduziert) und per POST-Request (`/api/toni/tts`) an den externen Rechner übergeben.
- Der bytestream/WAV-Rückgabewert der API wurde anschließend als Base64 encodiert an das Frontend zurückgespielt. Sollte das Stimmprofil abgelehnt werden, gab es zudem einen internen Fallback-Retry-Mechanismus in PHP.

### 4. Konfiguration in `AiAgentEditor.php`
- Die Auswahlmöglichkeit `toni_xttsv2` (Toni - Coqui XTTSv2) als TTS-Provider im Livewire-Backend der KI-Agenten-Verwaltung.

### 5. Routen und UI-Views
- Die zugehörige Blade-Logik und die Registrierung in den `routes/partials/admin_routes.php` (`/admin/externe-agenten/{id}`).

## Gewonnene Erkenntnisse & Fazit
Die Anbindung eines lokalen Coqui XTTS-Servers via API zeigte, dass maßgeschneiderte, lokale Sprachmodelle problemlos in die Laravel-Architektur integrierbar sind. 
Der Performance-Faktor bei Echtzeit-Anwendungen mit Voice-Interface (VAD / WebSockets) ist jedoch ausschlaggebend. Lokale Inferenzen erfordern dedizierte, hochperformante GPU-Ressourcen im selben Netzwerk. Durch den Wechsel auf Google Gemini (Native TTS) wird die Latenz signifikant gesenkt, was zu einer weitaus besseren, unterbrechungsfreien User-Experience führt. Die Systemarchitektur der Applikation wurde durch das Entfernen der externen APIs zudem spürbar verschlankt.
