# AI Widget Dokumentation & Architektur-Map

Diese Dokumentation beschreibt den Aufbau, die Architektur und die Funktionsweise der zentralseitigen AI Widget-Komponente ("FunkiView") in Seelenfunke. 
Aufgrund der hohen Komplexität (Alpine.js State, Livewire, Three.js 3D-Engine, Mapbox 2D/3D Layer, Web Speech API, WebSockets) wurde die Komponente in mehrere Sub-Dateien aufgeteilt. Dies verhindert Rendering-Fehler im DOM, die durch Konflikte zwischen Alpine.js Teleport (`x-teleport`) und der Three.js Canvas Instanz entstehen können.

## Architekturübersicht

Das AI Widget wird initial über `ai-widget.blade.php` geladen, welches als Hülle für Livewire dient und die Unterkomponenten (`part1` bis `part7`) via Blade `@include` einbindet.
Die UI-Logik ist vollständig in Alpine.js (`funkiView` Data-Objekt) gekapselt, während die 3D-Rendering-Logik in einer dedizierten globalen `window.t3` Struktur gespeichert ist. Die Kommunikation zwischen Backend (Persona / Events) und Frontend erfolgt fast ausschließlich über vom Backend dispatchte `window`-Events.

---

## AI Map: Datei-Struktur & Zuständigkeiten

Die folgenden Dateien bilden das Gesamtsystem des AI Widgets. **Für zukünftige Agenten:** Wenn eine Änderung vorgenommen werden muss, suche anhand dieser Map die zuständige Datei heraus, um Nebenwirkungen in der 3D-Engine oder im State-Management zu vermeiden.

### 1. Einstiegspunkt & Livewire Controller
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget.blade.php`
* **Inhalt:** Das absolute Root-Element. Definiert die Livewire-Komponente `<div wire:ignore.self>`. Includiert die restlichen Parts in der korrekten Reihenfolge. Hier sollte **keine** weitere Logik stehen.
* **Dateipfad Backend:** `app/Livewire/Shop/Ai/AiWidget.php`
* **Inhalt:** Die Livewire Backend-Komponente. Kümmert sich um Initialisierung, Chat-Historien und Speicherung der Widget-Konfigurationen in der Datenbank.

### 2. Frontend-Rendering & UI-Overlays (Part 1)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part1.blade.php`
* **Inhalt:** Das Haupt-Template der UI. 
  * Definiert den Alpine-Scope: `<div x-data="funkiView(...)">`.
  * **Event-Listener:** Beinhaltet alle wichtigen `@...window` Alpine-Listener (z. B. `@ai-show-persona.window`, `@ai-transform-core.window`, `@ai-toggle-secret-workspace.window`).
  * **UI-Elemente:** Beinhaltet das Side-Dock, den Mapbox-Container, den Canvas-Container für Three.js, sowie alle 2D-Overlays: News Widgets, YouTube Pool, Persona Pool (Geheimdienst-Akten) und das "Top Secret" String-Board.

### 3. State-Management & Kern-Logik (Part 2)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part2.blade.php`
* **Inhalt:** Das Gehirn der UI. Definiert das JavaScript Alpine `funkiView` Data-Objekt.
  * **State:** Beinhaltet reaktive Variablen wie `isJarvis`, `isSecretMode`, `intelWidgets`, `activeAgentId`, etc.
  * **Funktionen:** Hier liegen die Methoden für die Interaktion, wie `sendToAI()` (REST Chat API), `toggleLiveMode()`, Audio-Steuerung und `updateJarvisMode()`.

### 4. Speech Recognition & Synthesis (Part 3)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part3.blade.php`
* **Inhalt:** Beinhaltet die Logik für Spracherkennung (`window.SpeechRecognition`) und Sprachsynthese (`window.speechSynthesis`).
  * Kümmert sich um Push-to-Talk, kontinuierliches Zuhören und die Transformation von Audio-Chunks.
  * *Hinweis: Wird perspektivisch von WebSockets im LiveMode (Part 6) abgelöst oder ergänzt.*

### 5. Mapbox 3D Engine & Geolocation (Part 4)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part4.blade.php`
* **Inhalt:** Setup für die Mapbox GL JS Karte.
  * Flugsicherungsdaten (Live Flight Radar), Map-Styles (Dark Cyber, Satellite), Custom Markers, Kamerafahrten (`flyTo`).

### 6. Three.js 3D Kern (Part 5)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part5.blade.php`
* **Inhalt:** Die native WebGL/Three.js Engine.
  * Initialisiert `t3` (Scene, Camera, Renderer).
  * Enthält den extrem komplexen Raymarching Fragment-Shader für die pulsierende 3D-Kugel.
  * **Jarvis Transformation:** Wechselt dynamisch zwischen Shader-Geometrie und der `IcosahedronGeometry` (Drahtgitter-Box) über Alpine-Aufrufe. *Achtung: Änderungen am Canvas oder WebGL-Kontext müssen hier durchgeführt werden.*

### 7. LiveMode WebSockets & Audio Streams (Part 6)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part6.blade.php`
* **Inhalt:** Realtime-Streaming via WebSockets für latenzfreie "Echtzeit"-AI Voice Calls.
  * Verbindet sich über Port 8081 mit dem Node.js Middleware-Server. 
  * Spielt Base64-Audiodaten ab und streamt das Mikrofon live.

### 8. Projekt Gehirn & Neurale Fehleranalyse (Part 7)
* **Dateipfad:** `resources/views/livewire/shop/ai/ai-widget-part7-brain.blade.php`
* **Inhalt:** Die 3D Force-Directed Graph Engine für das Dateisystem der App.
  * **Visualisierung:** Initialisiert und konfiguriert `ForceGraph3D` auf Basis der automatisch erstellten Datei `system-brain-map.json`.
  * **Navigation:** Suchfunktion (`searchBrainMap`) und zentrierte Kamera-Fahrten (`cameraPosition`) zu ausgewählten Knoten.
  * **Fehlerdiagnose:** Empfängt Systemfehler (`ai-neural-scan-trigger`), färbt betroffene Nodes rot ein und stößt bei Interaktion eine Dateianalyse (`system_analyze_neural_error`) via Livewire an.

### 9. Backend Event Dispatcher (Persona Functions)
* **Dateipfad:** `app/Services/AI/Functions/AiPersonaFuncs.php`
* **Inhalt:** Der Backend Service, in dem die Werkzeuge ("Tools") für den AI Agenten definiert sind.
  * Löst Events wie `ai-toggle-secret-workspace` oder `ai-transform-core` über `Livewire::dispatch()` aus. Diese Events werden in **Part 1** empfangen und in **Part 2/5** ausgeführt.

---

## SPA Navigation & Persistenz (wire:navigate)

Um sicherzustellen, dass die künstliche Intelligenz (insb. Live-Sprachchat über WebRTC/WebSockets oder kontinuierliche Sprachaufnahme) nicht bei jedem Seitenwechsel getrennt wird, wurde die SPA-Integration von Livewire optimiert:
1. **Persistenz des Containers:** Das AI-Widget-Template in der Backend-Ansicht ist in eine `@persist('ai-widget-container')`-Direktive gehüllt. Dadurch bleibt der gesamte DOM-Knoten mitsamt den Three.js- und Mapbox-Instanzen sowie der WebSocket-Verbindung bei Seitenwechseln intakt.
2. **Unterbindung von Hard Reloads:** Alle Links in den Navigationsleisten (Desktop/Mobile in `backend_layout.blade.php` und `list-item.blade.php`) wurden mit dem Livewire-Attribut `wire:navigate` versehen. Dies sorgt für eine clientseitige Seitenaktualisierung und erhält den Zustand des Widgets aufrecht.

---

## Z-Index & Overlay-Schichtenarchitektur

Da das Widget aus mehreren überlagerten Fullscreen-Canvas-Elementen und interaktiven Steuerelementen besteht, ist die Reihenfolge der Darstellung (Stacking Context) essenziell für die Sichtbarkeit und Klickbarkeit.

### Die Schichten-Hierarchie (von hinten nach vorne)

1. **Hintergrund:** Mapbox GL Map (`z-0`)
2. **Shader-Raster & Scanlines:** Visuelles HUD-Overlay (`z-0` / `pointer-events-none`)
3. **Geheimdienst-Modus:** Secret Workspace Canvas (`z-[40]`)
4. **Three.js Canvas (Visualisierungskugel):** Kern-Visualisierung (`z-[50]`)
5. **Jarvis 2D Canvas:** Partikel- & Ring-Animationen (`z-[51]`)
6. **3D Force Graph (Projekt Gehirn):** `style="z-index: 55;"` (Wird absichtlich über dem Three.js Orb gerendert, da die CSS-Skalierung und Transformation der Kugel im Brain-Modus sonst die Ränder des transparenten Gehirn-WebGL-Canvas clippen/abschneiden würde).
7. **Steuerleiste & Navigation (Oben Rechts):** `style="z-index: 90;"` (Muss vor dem Gehirn liegen, damit Klick-Events auf Agenten-Auswahl, Chat-Verlauf und Buttons nicht vom Fullscreen-Gehirn-Canvas blockiert werden).
8. **Unterer Steuerungsbalken (Lautstärke / Beenden):** `style="z-index: 90;"` (Muss vor dem Gehirn liegen, um Klicks zu registrieren).
9. **Knoten-Panel & Suchleiste (Unten Mitte):** `style="z-index: 100;"` (Ganz vorne für maximale Interaktivität mit Sucheingabe und Kacheln).

---

## Interaktionsbeispiel: "Zeige mir den Jarvis Modus"

Um zu verstehen, wie das Widget als System arbeitet, hier der Datenfluss für die Aktivierung des Jarvis-Modus:

1. **User spricht:** "Aktivier den Jarvis Modus." -> Das Audio wird per `sendToAI()` (Part 2) an das Backend gesendet.
2. **Backend (AiPersonaFuncs):** Der Agent entscheidet, das Tool `persona_transform_core` mit `target: 'jarvis'` aufzurufen.
3. **Dispatch:** Das Backend feuert das Event `ai-transform-core` über Livewire an das Frontend.
4. **Listener (Part 1):** `@ai-transform-core.window` fängt das Event ab.
5. **State (Part 1 & 2):** Setzt `isJarvis = true` und ruft `updateJarvisMode()` auf.
6. **Engine (Part 2 -> Part 5):** `updateJarvisMode()` tauscht die `coreMesh.geometry` von der Default Raymarching-Geometrie zur Custom `IcosahedronGeometry` (Jarvis Wireframe). Der 3D Kern verwandelt sich augenblicklich.

---

## Besondere Hinweise für zukünftige Entwicklungen

1. **x-teleport Konflikte vermeiden:** Three.js und Mapbox mögen es gar nicht, wenn ihre Container-DIVs von Alpine durch `x-show` oder DOM-Ersetzungen neu gerendert werden. Verwende CSS `opacity` und `pointer-events`, um die 3D Layer im Hintergrund ein- und auszublenden, anstatt sie aus dem DOM zu entfernen (oder `x-show` auf Wrapper zu nutzen, die DOM-Knoten entfernen).
2. **Alpine State:** Speichere keine Three.js Objekte (`Scene`, `Renderer`) in Alpine (`this`), da Alpine diese proxied und Proxy-Lese-/Schreibkonflikte oder immense Performance-Einbrüche erzeugt. Speichere sie stattdessen im globalen Scope (z. B. in `let t3 = {}`).
3. **Keine neuen willkürlichen Tailwind Z-Index Klassen:** Tailwind-JIT-Kompilierung verarbeitet Klassen wie `z-[55]` oder `z-[90]` nicht dynamisch zur Laufzeit im Livewire-Blade, sofern die CSS-Dateien nicht neu gebaut wurden. **Nutze für kritische Steuerungsschichten immer Inline-Styles (`style="z-index: ...;"`)**, um zu verhindern, dass interaktive UI-Elemente durch unkompilierte Z-Klassen hinter unsichtbaren Fullscreen-Layern gefangen und unklickbar werden.
4. **Backend-Frontend Sync:** Jeder Befehl, den die KI ausführt und der das UI verändern soll, MUSS über ein standardisiertes CustomEvent (z. B. `ai-show-xy`) via Livewire dispatched werden, das im Alpine Root (Part 1) deklariert ist.
