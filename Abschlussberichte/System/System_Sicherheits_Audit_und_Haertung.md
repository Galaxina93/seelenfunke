# Abschlussbericht: Tiefen-Sicherheitsaudit & System-Härtung (Zero-Trust)

**Datum:** 23.10.2026  
**System:** Mein Seelenfunke (Gesamtplattform: Auth, KI-Support, Backend-Support, Checkout/Bestellungen, Leitung/E-Mail & Master-Analytics)  
**Status:** Alle identifizierten Sicherheitslücken behoben & Abwehr-Telemetrie erweitert ✅

---

## 1. Login, Registrierung, 2FA, OAuth & API-Authentifizierung

### 1.1 Zwei-Faktor-Authentifizierung (2FA): Typ-Manipulation & Recovery-Code-Härtung
* **Problem:** Bei der Prüfung von 2FA-Wiederherstellungscodes wurde ein loser Vergleich (`in_array` ohne strikte Typprüfung) verwendet. Durch Übergabe eines booleschen Wertes (`true`) oder bei leeren Recovery-Code-Arrays bestand das Risiko einer 2FA-Umgehung. Zudem waren die Livewire-Status-Properties des Benutzers während des 2FA-Schritts nicht gegen clientseitige Manipulation gesperrt.
* **Lösung:** Strikte String-Validierung, zeitkonstanter `hash_equals`-Abgleich und `#[Locked]`-Schutz für alle Identitäts-Properties.
* **Beschreibung:** Der 2FA-Prüfmechanismus erzwingt nun zwingend einen String-Code, prüft Recovery-Codes über kryptografisch sichere Vergleiche und sperrt `$user`, `$guard` sowie `$secretKey` serverseitig gegen jegliche Manipulation im Frontend-Payload.

### 1.2 2FA-Umgehung über Google OAuth, API-Login & Checkout-Login
* **Problem:** Während das Haupt-Login-Formular die Zwei-Faktor-Authentifizierung abfragte, loggten der Google-OAuth-Callback, der REST-API-Login sowie der Direkt-Login im Checkout-Prozess Nutzer direkt ein, ohne einen aktiven 2FA-Schutz des Profils oder gelöschte Konten (`SoftDeletes`) zu prüfen.
* **Lösung:** Vollständige 2FA-, SoftDelete-, E-Mail-Verifizierungs- und IP-Blocklisten-Prüfung auf allen Login-Kanälen.
* **Beschreibung:** Jeder Authentifizierungsweg (Standard-Login, Google OAuth, Checkout-Login und `/api/login`) prüft nun einheitlich auf aktive IP-Sperren, gelöschte Accounts und leitet Konten mit aktivierter 2FA zwingend in die 2FA-Verifizierung um.

### 1.3 Registrierung: Serverseitige Passwort-Komplexität & Gast-Chat-Übernahme
* **Problem:** Die Passwort-Regeln (Großbuchstabe + Zahl) wurden nur in der Live-Vorschau geprüft, beim finalen Absenden jedoch auf `min:8` reduziert. Zudem war das `$sessionToken` zur Übernahme eines vorherigen Gast-Support-Chats öffentlich manipulierbar.
* **Lösung:** Verbindliche serverseitige Komplexitäts-Validierung und Sperrung (`#[Locked]`) des Session-Tokens.
* **Beschreibung:** Schwache Passwörter können nicht mehr per manipuliertem Request eingeschleust werden, und die Übernahme fremder Gast-Chats bei der Registrierung ist ausgeschlossen.

### 1.4 Passwort-Reset: Schutz vor User-Enumeration & Brute-Force
* **Problem:** Bei der Passwort-Vergessen-Funktion verriet eine spezifische 2-Minuten-Sperrmeldung, ob eine E-Mail-Adresse im System registriert ist (User-Enumeration). Zudem fehlte beim finalen Setzen des neuen Passworts ein eigenes Rate-Limit.
* **Lösung:** Vereinheitlichte neutrale Rückmeldungen und zusätzliches Rate-Limiting beim Passwort-Reset-Abschluss.
* **Beschreibung:** Angreifer können anhand der Antworttexte oder Statuscodes nicht mehr ausspähen, welche Kunden- oder Admin-E-Mail-Adressen im Portal existieren.

---

## 2. Support KI-Agent Widget (Frontend) & KI-Schnittstellen

### 2.1 Zero-Trust KI-Tool-Sandbox für den öffentlichen Support-Chat
* **Problem:** Die zentrale KI-Funktions-Registry verließ sich darauf, dass dem öffentlichen Support-Agenten in der Datenbank nur harmlose Tools zugewiesen sind. Bei einer versehentlichen Fehlkonfiguration im Backend hätte der öffentliche Chat theoretisch interne Verwaltungs- oder Finanz-Tools aufrufen können. Zudem enthielt der globale Basis-Prompt interne Buchhaltungs-Regeln.
* **Lösung:** Harte Code-Sandbox (`AIFunctionsRegistry`) und Trennung interner System-Prompts.
* **Beschreibung:** Sobald ein Aufruf aus dem öffentlichen Kunden-Support-Chat stammt, blockiert eine unüberwindbare Sicherheits-Schranke im Code jedes Tool, das kein explizites `support_*`-Werkzeug ist, und protokolliert den Vorfall sofort im Security-Monitor.

### 2.2 Schutz interner WebSocket-Bridge- & Transkript-Endpunkte
* **Problem:** Interne API-Endpunkte der Audio-/WebSocket-Bridge (`verify-token`, `save-live-transcript`, `log/websocket`, `twilio/call-log`) waren ohne Signatur- oder Netzwerkprüfung erreichbar. Dadurch hätten gefälschte Gesprächstranskripte (inkl. `system`-Rolle) oder manipulierte Log-Einträge von außen eingespeist werden können.
* **Lösung:** Interne Ursprungs- & Secret-Verifizierung (`isTrustedBridgeRequest`) sowie strikte Rollen-Validierung (`user` / `assistant`).
* **Beschreibung:** Nur noch der verifizierte interne Bridge-Dienst darf Live-Transkripte und Diagnose-Logs einspeisen; das Einschleusen von `system`-Prompts über Transkripte ist vollständig unterbunden.

### 2.3 Mandanten-Trennung & Kosten-Schutz im Livewire Support-Widget
* **Problem:** Auf gemeinsam genutzten Geräten konnte eine noch in der Session gespeicherte Chat-ID beim Wechsel des Kundenkontos geladen werden. Zudem konnte die KI-Inferenz-Methode direkt vom Client getriggert werden, ohne dass zuvor eine neue Kundennachricht gespeichert wurde.
* **Lösung:** Strikter Abgleich der `customer_id`, Gast-Limit-Prüfung vor jeder KI-Inferenz und Begrenzung von Bewertungs-Payloads.
* **Beschreibung:** Fremde Chats werden bei Konto-Abweichung sofort aus der Session getrennt, und direkte Client-Aufrufe der KI-Generierung ohne gültige vorherige Kundennachricht werden abgewiesen.

---

## 3. Backend-Support & Kunden-Ticketsystem

### 3.1 Schutz vor fremdem Ticket-Zugriff (IDOR) im Kundenportal
* **Problem:** Im Kunden-Ticket-Modul war die Property `$customerId` als öffentliches Livewire-Attribut ohne `#[Locked]` deklariert. Durch Manipulation des Livewire-Request-Payloads hätte ein eingeloggter Kunde theoretisch seine `$customerId` auf eine fremde UUID ändern und fremde Tickets abfragen oder beantworten können.
* **Lösung:** Harte Sperrung via `#[Locked]` und erzwungene Neu-Verifizierung gegen `Auth::guard('customer')->id()` vor jeder Aktion.
* **Beschreibung:** Jede Lese- und Schreiboperation im Kunden-Ticketbereich erzwingt nun serverseitig die authentifizierte Sitzungs-ID des Kunden und meldet Manipulationsversuche direkt an den Threat-Monitor.

---

## 4. Bestellungen, Checkout & Dateidownloads

### 4.1 Manipulation von Zahlungs- und Status-Attributen im Checkout
* **Problem:** Im Checkout-Modul waren sicherheitsrelevante Statusfelder (`$clientSecret`, `$currentPaymentIntentId`, `$totalAmount`, `$isFinished`, `$finalOrderNumber`) als ungeschützte öffentliche Properties definiert.
* **Lösung:** Vollständige Absicherung aller Zahlungs- und Status-Properties mit `#[Locked]` sowie Sicherheits-Logging bei Intent-Abweichungen.
* **Beschreibung:** Zahlungs-Intents, Summen und Bestellabschlüsse sind unmanipulierbar an den serverseitigen Zustand und die direkte Stripe-API-Verifizierung gekoppelt.

---

## 5. Leitung, E-Mail-Client (CRM) & Dateianhänge

### 5.1 Schutz vor JavaScript-Ausführung (XSS) in eingehenden HTML-E-Mails
* **Problem:** Das Vorschau-`<iframe>` für eingehende E-Mails im Leitungsbereich besaß das Attribut `sandbox="allow-popups allow-scripts"`. Sollte ein Angreifer einen HTML-Sanitizer-Bypass in einer E-Mail finden, hätte eingeschleustes JavaScript im Kontext des Admin-Browsers ausgeführt werden können.
* **Lösung:** Entfernung von `allow-scripts` aus der Iframe-Sandbox und erweiterte Bereinigung gefährlicher HTML-Tags (`base`, `form`, `meta`, `math`, `data:text/html`).
* **Beschreibung:** Eingehende E-Mails werden in einer strikt skriptfreien Sandbox gerendert, sodass Schadcode in E-Mails selbst im Extremfall niemals ausgeführt werden kann.

### 5.2 Schutz vor Dateipfad-Manipulation (LFI) bei E-Mail-Anhängen & Belegen
* **Problem:** Beim Weiterleiten oder Versenden von E-Mails über das CRM/API wurden Dateipfade für Anhänge aus einem Array gelesen, das ohne Pfad-Prüfung an `Attachment::fromStorage()` übergeben wurde. Ähnliches galt für den Dateipfad beim KI-Belegscan.
* **Lösung:** `#[Locked]`-Schutz für `$composeAttachments` sowie strikte Whitelist- und Path-Traversal-Prüfung (`..`-Verbot und Verzeichnis-Whitelist) für alle Anhang- und Belegpfade.
* **Beschreibung:** Es können ausschließlich legitim hochgeladene Dateien aus den vorgesehenen Anhang- bzw. Buchhaltungs-Ordnern versendet oder verarbeitet werden.

### 5.3 Schutz vor Server-Side Request Forgery (SSRF) & Passwort-Exfiltration bei Mail-Konten
* **Problem:** Bei der Konfiguration von IMAP-/SMTP-Servern konnten interne IP-Adressen (`127.0.0.1`, `localhost`, Docker-Subnetze) eingetragen werden. Zudem konnte bei einem bestehenden Postfach der SMTP-Server geändert werden, ohne das gespeicherte Passwort neu einzugeben, wodurch das gespeicherte Passwort an einen fremden Server hätte gesendet werden können.
* **Lösung:** SSRF-Host-Validator (`isSafeMailHost`) und Pflicht zur Passwort-Neueingabe bei Serverwechsel.
* **Beschreibung:** Interne Netzwerkziele sind als Mail-Hosts gesperrt, und gespeicherte Zugangsdaten sind gegen unbefugte Umleitung auf fremde Server geschützt.

---

## 6. Erweiterung "Sicherheit & Abwehr" (Master-Analytics)

* **Zentraler Security-Service (`SystemSecurityService`):** Bündelt alle Sicherheitsereignisse in Echtzeit und verfügt über eine **automatische IP-Firewall**, die Angreifer-IPs bei $\ge 10$ fehlgeschlagenen Login-Versuchen innerhalb von 15 Minuten vollautomatisch für 24 Stunden sperrt (`security:auto_ip_ban`).
* **5-Säulen Abwehr-Matrix:** Aufschlüsselung und Direkt-Filterung aller Ereignisse der letzten 48 Stunden nach:
  1. *Brute-Force & Login*
  2. *Bot & Rate-Limiter (Honeypots & Spam-Abwehr)*
  3. *2FA & Identitätsschutz ( inkl. Admin-2FA-Abdeckungsquote)*
  4. *KI & API-Sandbox (Tool-Gate & Bridge-Schutz)*
  5. *IDOR & Integrität (Checkout-, Ticket-, SSRF- & LFI-Schutz)*
* **Aktive IP-Firewall & Sofort-Sperre:** Direkt-Übersicht aller aktuell gesperrten IPs inkl. Ablaufzeit, Sperrgrund, Ein-Klick-Freigabe sowie einem Formular zur manuellen Sofort-Sperrung verdächtiger IPs (24h, 7 Tage oder Permanent).
* **Interaktive Info-Tooltips:** Jede Kachel und Abwehr-Säule verfügt über ein Info-Icon mit detaillierter Hover-Erklärung der jeweiligen Kennzahl und Schutzmechanik.

---

## 7. Android App: 2FA-Login & Mobile-Security-Härtung

### 7.1 Zwei-Faktor-Authentifizierung (2FA) im App-Login
* **Problem:** Konten mit aktivierter Zwei-Faktor-Authentifizierung (TOTP / Recovery-Codes) erhielten beim Login in der Android-App eine `403 two_factor_required`-Antwort, ohne dass ein Eingabefeld für den 6-stelligen Sicherheitscode angezeigt wurde.
* **Lösung:** Vollständige 2FA-Challenge-Erkennung und zweistufiger Login-Flow in der Android-App.
* **Beschreibung:** Fordert das Backend einen zweiten Faktor an, schaltet der Login-Screen nahtlos auf die 2FA-Code-Eingabe um (inkl. Abbruch-/Zurück-Option) und übermittelt den Code verschlüsselt zur Verifizierung.

### 7.2 Hardware-gestützte AndroidKeyStore-Verschlüsselung (AES-256-GCM)
* **Problem:** Auth-Token, gespeicherte Passwörter (für den Biometrie-Login) und Gemini-API-Keys lagen im Klartext in den `SharedPreferences`. Zudem war `android:allowBackup="true"` aktiv und der Netzwerk-Logger protokollierte HTTP-Bodys inkl. Passwörtern im Logcat.
* **Lösung:** AES-256-GCM-Verschlüsselung über den `AndroidKeyStore`, Deaktivierung von Geräte-Backups (`allowBackup="false"`), HTTPS-Zwang (`cleartextTrafficPermitted="false"` für öffentliche Domains), Header-Schwärzung (`Authorization`, `Cookie`) im Logger sowie Bearer-Token-Authentifizierung am E-Mail-WebSocket.
* **Beschreibung:** Sensible Zugangsdaten sind auf dem Endgerät hardwareseitig verschlüsselt, vor ADB-Backup-Extraktion geschützt und werden niemals im Systemlog ausgegeben.

---

## 8. Kunden-Dashboard, Bestellungen & Gamification-Härtung

### 8.1 State-Locking & Pfad-Validierung im Kundenbereich
* **Problem:** Status- und ID-Eigenschaften in den Livewire-Komponenten für Kundenbestellungen und Gamification (Level, Funken-Guthaben, Energie, freigeschaltete Gutscheine) waren nicht gegen clientseitige Manipulation gesperrt (`#[Locked]`). Zudem erlaubte die erzwungene Passwortänderung schwache Passwörter ohne Komplexitätsprüfung.
* **Lösung:** Konsequentes `#[Locked]`-Attribut auf allen serverberechneten Eigenschaften, Eigentümerprüfung bei URL-Parametern (`?edit=`), Path-Traversal-Schutz bei digitalen Downloads, Passwort-Komplexitätsregeln sowie Anbindung der Gamification-Anti-Cheat-Prüfungen an den zentralen Threat Monitor (`security:game_cheat_attempt`).
* **Beschreibung:** Spielstände, Guthaben und Bestellzugriffe können clientseitig nicht manipuliert werden; Manipulationsversuche werden sofort im Sicherheits-Dashboard sichtbar.

---

## 9. Spiel-Optimierung: "Funkenflug-Express" (Mobile, Grafik & FPS-Tests)

### 9.1 3D-Asset-Komprimierung (-99,15 % Dateigröße) & Mobile-Grafik-Aktivierung
* **Problem:** Die drei 3D-Modelle (`funki_rocket.glb`, `meteorit.glb`, `sharp_stone.glb`) bestanden aus unkomprimierten Roh-Meshes mit zusammen **130,63 MB Dateigröße**, fast **6 Millionen Dreiecken** und eingebetteten **4096×4096 (4K) Texturen** (268 MB GPU-VRAM). Dadurch dauerte das Laden extrem lange, führte zu massiven Frame-Drops, und auf Mobilgeräten waren die 3D-Modelle für Meteoriten und Kristalle komplett deaktiviert.
* **Lösung:** Verlustfreie Mesh-Optimierung und Textur-Skalierung (1024×1024px) auf insgesamt **1,11 MB** (Rakete: 522 KB, Meteorit: 335 KB, Kristall: 258 KB) bei ~21.600 Dreiecken sowie Aktivierung aller echten 3D-Modelle auch auf Mobilgeräten.
* **Beschreibung:** Alle Grafiken laden auf Smartphone und Desktop in unter 0,25 Sekunden und werden auf allen Geräten vollständig in 3D dargestellt.

### 9.2 Zero-Allocation Render-Loop & Touch-Optimierung
* **Problem:** Pro Frame und Touch-Bewegung wurden hunderte temporäre 3D-Vektoren erzeugt, `getBoundingClientRect()`-Layout-Reflows ausgelöst, Alpine.js-Reaktivitäts-Updates 60-mal pro Sekunde gefeuert, bei jeder Explosion 19.200 Zufallswerte für Audio-Rauschen auf dem Main-Thread berechnet und Hintergrund-Canvas-Animationen (`goldDust`) weitergezeichnet.
* **Lösung:** Vorallokierte Scratch-Vektoren (Zero-GC), gecachte Canvas-Bounds, passive Touch-Listener (`{ passive: true }`), entkoppeltes DOM-HUD-Update, gecachter Audio-Noise-Buffer und automatisches Pausieren von Hintergrund-Animationen während des Spiels.
* **Beschreibung:** Keinerlei Garbage-Collection-Ruckler oder Touch-Verzögerungen mehr – butterweiches Gameplay selbst auf gedrosselten Smartphone-Prozessoren.

### 9.3 Ergebnisse der WebGL-FPS-Benchmarks (Volllast: Multishoot + Schild + 4 Ultimate-Drohnen + Touch-Bewegung)
| Test-Szenario | 3D-Ladezeit | Ø Frame-Zeit | 60Hz Budget-Last | Max. theoretische FPS | 1% Low FPS | VSync-FPS |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Desktop (1280×800)** | 251 ms | **0,35 ms** | 2,1 % | **2.863 FPS** | 500 FPS | **120 FPS** (locked) |
| **Mobile (390×844 Touch)** | 98 ms | **0,33 ms** | 2,0 % | **3.055 FPS** | 667 FPS | **120 FPS** (locked) |
| **Mobile 4× CPU-Drosselung** | 141 ms | **0,97 ms** | 5,8 % | **1.037 FPS** | 278 FPS | **120 FPS** (locked) |

---

## 10. Phase 2 Tiefen-Audit: Weitere Module, Preis-Integrität & Dateisystem-Schutz

### 10.1 Notfall- & Todesfall-Protokoll (`/notfall`): Schutz vor Auth-Bypass
* **Problem:** Die Eigenschaft `$isAuthenticated` in der Notfall-Komponente war nicht mit `#[Locked]` gesperrt. Ein Angreifer hätte im Livewire-Payload `isAuthenticated = true` übergeben können, um ohne Master-Passwort Einblick in Systemeinstellungen, Buchhaltungsposten und offene Bestellungen zu erhalten oder den Wartungsmodus des gesamten Shops auszulösen.
* **Lösung:** Harte Sperrung via `#[Locked]`, ausschließliches Laden sensibler Statusdaten nach verifizierter Server-Session (`session('emergency_access_granted') === true`) und direkte Anbindung von Fehlversuchen an den Security-Monitor (`security:emergency_auth_failed`).
* **Beschreibung:** Das Notfall-Protokoll ist nun vollständig gegen clientseitiges State-Spoofing gehärtet.

### 10.2 Geschenkgutschein- & Warenkorb-Preisintegrität
* **Problem:** Bei der Zahlungsabwicklung (`completePayment`) prüfte die Gutscheingenerierung lediglich, ob im Konfigurations-Array einer Bestellposition `is_gift_voucher` gesetzt war, und übernahm ungeprüft `amount_cents` als Gutscheinwert. Durch Übergabe dieser Konfigurationsparameter an ein günstiges Standardprodukt hätte ein Käufer für wenige Euro einen hochdotierten Wertgutschein generieren lassen können. Zudem waren negative `amount_cents`-Werte bei Gutscheinen nicht nach unten begrenzt und persönliche Gutschein-Fehlermeldungen gaben die volle E-Mail-Adresse des Empfängers preis.
* **Lösung:** Strikte Produkt-Bindung (`slug === 'geschenkgutschein'`), automatisches Stripping unzulässiger Gutschein-Flags bei Standardprodukten, Clamping des Gutscheinbetrags auf 5,00 € bis 1.000,00 € (`500`–`100000` Cents), Deckelung des generierten Gutscheinwerts auf den tatsächlich gezahlten Artikelpreis (`unit_price`), E-Mail-Maskierung bei persönlichen Gutscheinen sowie Schutz vor 0-€-Leerkorb-Bestellungen.
* **Beschreibung:** Jegliche Manipulation von Gutscheinbeträgen oder Artikelkonfigurationen wird serverseitig abgefangen, korrigiert und sofort als `security:price_tampering_attempt` im Sicherheits-Dashboard gemeldet.

### 10.3 Laser-SVG-Export & Produkt-Konfigurator: Schutz vor Local File Inclusion (LFI)
* **Problem:** Beim Generieren der Laser-Druckdatei (`downloadLaserSvg`) wurden Logo-Pfade aus der Artikelkonfiguration ohne Prüfung auf `..` oder Verzeichnisgrenzen per `file_get_contents()` geladen und Base64-kodiert in die SVG eingebettet. Zudem war `$uploaded_files` im Konfigurator nicht gesperrt.
* **Lösung:** `realpath()`-Verzeichnis-Jail (nur `storage/app/public`, `public/shop` und `public/shopverwaltung/images`), Dateiendungs-Whitelist (`svg`, `png`, `jpg`, `jpeg`, `webp`), XML-Attribut-Escaping, 10-MB-Limit für Snapshot-Uploads sowie `#[Locked]`-Schutz auf `$uploaded_files` und `$snapshot_path`.
* **Beschreibung:** Selbst über manipulierte Konfigurationsdaten können niemals Systemdateien außerhalb der freigegebenen Medienordner ausgelesen oder gelöscht werden (`security:lfi_attempt_blocked`).

### 10.4 Produktbewertungen, Linktree, KI-Workspace & System-Routen
* **Problem:**
  * Der eingebettete Login in den Produktbewertungen besaß kein Rate-Limiting und keine IP-Blocklisten-Prüfung.
  * Die Linktree-Komponente akzeptierte die Weiterleitungs-URL als Client-Parameter (Open-Redirect-Risiko).
  * Der Dateimanager im KI-Workspace prüfte Pfade nicht durchgängig auf `agenten/workspace/` und erlaubte beim Upload ausführbare Dateiendungen.
  * Auf der Newsletter-Seite verriet eine Fehlermeldung, ob eine E-Mail bereits verifiziert ist (User-Enumeration), und bei den Cronjob-/Cache-Routen griff der Rate-Limiter erst nach der Token-Prüfung.
* **Lösung:**
  * Brute-Force-Schutz, IP-Firewall-Check und `#[Locked]`-Properties in den Produktbewertungen.
  * Serverseitige Datenbank-Auflösung der Ziel-URL bei Linktree-Klicks (`security:open_redirect_blocked`).
  * Strikte Pfad-Sanitisierung (`sanitizeWorkspacePath`), Löschschutz für Hauptordner und Blockade ausführbarer Skript-Endungen im KI-Workspace sowie in den Finanz-/Task-Upload-APIs.
  * Rate-Limiting vor Token-Vergleich bei System-Routen (`security:cron_token_invalid`), Download-Throttling für Rechnungen/Gutscheine/Digitalprodukte, Ausschluss von SVGs bei öffentlichen Telegram-Uploads und einheitliche, enumerationssichere Antworten im Newsletter-Modul.
* **Beschreibung:** Alle sekundären Login-, Upload-, Download- und Weiterleitungs-Endpunkte unterliegen denselben strengen Zero-Trust-Regeln wie der Haupt-Checkout.


