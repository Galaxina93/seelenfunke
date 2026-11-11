# Debugging Plan: Map-Kontrolle UI Status

## Problem
Die `map_toggle_mapfocus` Funktion meldet Erfolg, aber das UI-Element (Button in der `ai-widget-navigation`) wird visuell nicht aktiviert.

## Analyseschritte
1. **Frontend-Event Prüfung:** Protokollieren, ob das Event `toggle-mapfocus` mit `detail: {active: true}` im Browser ankommt.
2. **UI-Komponenten Prüfung:**
    - Pfad: `resources/js/components/AiWidgetNavigation.vue` (oder ähnlicher Pfad) prüfen.
    - Status-Variablen und Event-Listener in der Blade/Vue Komponente auf korrekte Reaktion prüfen.
3. **Log-Analyse:**
    - Falls vorhanden, `global-logs` nach JavaScript- oder Vue-Fehlern prüfen.

## Maßnahme
Da ich selbst keinen Frontend-Code debuggen oder loggen kann, schicke ich die gesammelten Informationen an Antigravity.

**Ergänzung:**
Um das Problem isoliert zu testen, schlage ich vor, temporär eine Logdatei direkt im Projekthauptverzeichnis zu erstellen, die getriggert wird, sobald das `toggle-mapfocus` Event im System verarbeitet wird. Das hilft uns zu verstehen, ob der Befehl überhaupt die Server-Logik korrekt durchläuft.