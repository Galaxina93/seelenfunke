# Konzept & Ideen-Speicher: Funkira 0-Token Kamera-Wächter-Modus (Atelier-Wache)

**Datum:** 14.11.2026  
**System:** Funkenwerk OS Linux Mini-PC (`192.168.188.151`, Logitech HD Pro Webcam C920, `funkira_local_sense.py`, `wwwroot/app.js`, `FunkiraLiveAndMcpEngine.cs`)  
**Status:** Gespeicherte Idee / Konzeptentwurf (noch nicht implementiert, wartet auf explizites „Go“)

---

## 1. Ausgangsidee & Unternehmerischer Mehrwert

Wenn Funkira im **0-Token-Hausmodus** deaktiviert bzw. pausiert ist, verbraucht sie keinerlei Cloud-Tokens. Über die am Werkstatt-Monitor angeschlossene **Logitech C920** soll optional ein zuschaltbarer **„🛡️ Wächter-Modus“** aktiviert werden können, wenn Alina das Atelier verlässt (Pause, Einkauf, Feierabend).

**Mehrwert für die Manufaktur:**
1. **Aktiver Arbeitsplatz- & Datenschutz am Laser-/Geschäfts-PC:** Tritt während Alinas Abwesenheit Besuch, Familie oder ein Handwerker vor den Monitor, sperrt das System sofort alle sensiblen Ansichten (**Zero-PII Besucher-Sandbox**) und Funkira reagiert sichtbar und hörbar.
2. **Garantiert 0 Token-Verbrauch im Ruhezustand:** Solange niemand vor den Monitor tritt, bleibt die Gemini-Live-Verbindung vollständig getrennt (`0 Tokens`, `0,00 €`).

---

## 2. Zweistufiger 0-Token-Lokal-Filter (Schutz vor Fehlalarmen)

Damit Sonnenlichtwechsel, Wolken, Schatten oder die xTool-Abluft keine Fehlalarme und unnötigen Token-Kosten auslösen, arbeitet die Erkennung zweistufig rein lokal im Arbeitsspeicher (`RAM`):

1. **Stufe 1 (Ultraleichte Pixel-Bewegungserkennung, `~2 FPS`, 0 Tokens):**
   * Prüft lokal auf grobe Bildveränderung vor dem Monitor. Reine Helligkeits- oder Schattenwechsel reichen allein noch nicht zum Aufwecken.
2. **Stufe 2 (Lokale Offline-Personen- & Gesichtserkennung via `YuNet + SFace` ONNX, 0 Tokens):**
   * Erst bei erkannter Bewegung prüft `funkira_local_sense.py` (`127.0.0.1:17701`) innerhalb weniger Millisekunden lokal im RAM, ob tatsächlich **ein Mensch / Gesicht** vor der Kamera steht und ob der gespeicherte `128-Float`-Vektor zu **Alina (Inhaberin)** gehört.

---

## 3. Geplante Reaktionslogik (Unbekannte Person vs. Alina)

* **Standard-Zustand:** Der Schalter **„🛡️ Wächter-Modus“** im Header ist standardmäßig **AUS**, damit im normalen Werkstattbetrieb niemand beim Vorbeilaufen gestört wird.
* **Fall A: Unbekannte Person / Besucher wird vor dem Monitor erkannt:**
  1. Sofortige Aktivierung der **Zero-PII Besucher-Sandbox** (keine Kundendaten, Bestellungen, Umsätze oder privaten Dateien sichtbar/abfragbar).
  2. Funkira sprintet im 3D-Haus nach vorne an die Glasscheibe (inkl. kurzem Warnblinken der Helm-Sirene).
  3. Kurze Live-Aktivierung mit freundlich-bestimmter Ansprache im knackigen Stil: *„Hallo? Wer ist da, und was machen Sie am Atelier-Platz? Alina ist gerade nicht da.“*
  4. **Token-Schutzbremse:** Kommt keine Antwort, trennt Funkira die Cloud-Verbindung nach **30 Sekunden** automatisch wieder und kehrt in die 0-Token-Wache zurück (inkl. **3 Minuten Cooldown**, damit Herumstehen keine Endlosschleife auslöst).
* **Fall B: Alina selbst kehrt zurück ins Atelier (`SFace` erkennt Chefin):**
  1. Keine Warnansprache und **0 Cloud-Tokens** Verbrauch.
  2. Der Wächter-Modus entschärft sich lautlos von selbst (optional kurzes lokales Status-Badge *„Willkommen zurück, Alina“*), sodass Alina erst dann per Wake-Word *„Funkira“* ein Gespräch startet, wenn sie es selbst möchte.

---

## 4. Datenschutz & Systemsicherheit (DSGVO-Konformität)

* **0 Foto-/Video-Speicherung:** Alle Kamerabilder der Wächter-Prüfung existieren ausschließlich für wenige Millisekunden im flüchtigen Arbeitsspeicher (`RAM`) und werden niemals auf Festplatte, NAS oder in eine Cloud gespeichert.
* **Strikte Rollentrennung:** Solange bei einem Wächter-Alarm nicht zweifelsfrei Alina verifiziert wurde, bleibt die Sitzung hart in der Zero-PII Besucher-Sandbox gesperrt.
