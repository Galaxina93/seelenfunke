# Konzept & Umsetzungsbericht: Das Funkenreich als eigenes Spiel-Projekt & Saubere Shop-Trennung (Baustein 0)

> **Datum:** 18.11.2026  
> **Verantwortlich:** Spieli (`spieli` · Lead Game Director) unter Chef-Dirigent Falcone  
> **Ziel-Domain:** `https://game.mein-seelenfunke.de`  
> **Eigenstaendiger Projektpfad:** `/home/ubuntuxina/meine-projekte/funkenreich`

---

## 1. Unternehmerische & Technische Ausgangslage

Mit dem Wachstum der 3D-Halle der Helden, der 10-stufigen Magma-Welt, des Haendlersystems und des Multiplayer-Chats im Hauptportal `Mein-Seelenfunke` entstand eine klare architektonische Anforderung:
1. **Reiner, blitzschneller Manufaktur-Shop (`mein-seelenfunke.de`):** Das E-Commerce-Portal fuer handveredelte Laser-Unikate bleibt schlank, wartungsarm und frei von zehntausenden Zeilen 3D-Spielcode.
2. **Eigenstaendiges 60-FPS MMORPG-Studio (`game.mein-seelenfunke.de`):** „Das Funkenreich“ erhaelt ein eigenes, hochperformantes PHP-8.4- und ES-Module-Projekt ohne Framework-Ballast, mit **0 % Spielcode-Verlust** und einer modularen Baukasten-Struktur fuer die Ausbaustufen Baustein 1 bis 6.

---

## 2. Architektur der Sauberen Trennung (Baustein 0)

### 2.1 Eigenstaendige PHP 8.4 + SQLite-WAL Engine (`app/` & `public/index.php`)
- **Zero Framework-Bloat:** Direkter Front-Controller (`public/index.php`) mit eigenem PSR-4 Autoloader (`Funkenreich\*` in `app/Bootstrap.php`), gehaerteten Sessions (`HttpOnly`, `SameSite=Lax`, Session-Rotation) und striktem CSRF-Schutz.
- **Atomare SQLite-WAL Persistenz (`app/Services/PlayerStoreService.php`):** Speichert Spieler-Profile, Funken-Salden, 6-Herzen-Escrow, Magma-Fortschritt, 6-Slot-Rucksack, aktive Distrikt-Peers, Chat-Ringpuffer und verbrauchte SSO-Nonces lokal unter `storage/database/funkenreich.sqlite` mittels `BEGIN IMMEDIATE TRANSACTION` (`< 1 ms` Antwortzeit, 0 externe Datenbank-Abhaengigkeit).
- **Suchmaschinen- & Closed-Alpha-Schutz:** Jede Antwort traegt `X-Robots-Tag: noindex, nofollow, noarchive`. Unautorisierte Besucher erhalten auf `GET /` **HTTP `403`** mit der edlen Schwarz-Gold Vorschau-Seite (`app/Views/gate_locked.php`).

### 2.2 Kryptographischer SSO-Handschlag & Live-Widerruf (`app/Security/SsoGateGuard.php`)
- **Einmal-Token (`GET /sso/callback?token=<base64url>.<hex_hmac_sha256>`):** Prueft die HMAC-SHA256 Signatur zeitkonstant via `hash_equals`, validiert `iss === "mein-seelenfunke"`, `exp >= time()` und `mmorpg_access === true`.
- **Atomarer Replay-Schutz:** Jedes `nonce` wird einmalig in `used_sso_nonces` verbucht; jeder Wiederholungsversuch wird sofort mit HTTP `403` blockiert.
- **Sauberer Browser-Einstieg:** Nach erfolgreicher Verifikation rotiert `SsoGateGuard` die Session-ID und leitet per `302` auf `/` weiter, sodass niemals ein Token in der Browser-Chronik oder Adresszeile verbleibt.
- **30-Sekunden Live-Revocation:** Aktive Kunden-Sessions werden alle 30 Sekunden signiert (`X-Funkenreich-Timestamp` + `X-Funkenreich-Signature`) gegen `SHOP_INTERNAL_VERIFY_URL` geprueft. Bei Entzug der Berechtigung im Shop endet die Spiel-Session sofort.

### 2.3 Verlustfreie 3D-Engine & Neue Baukasten-Struktur (`public/js/funkenreich/`)
- **100 % Autarke Assets (0 externe CDNs):** Lokale Einbindung von Three.js (`three.min.js`, `three.module.min.js`, `GLTFLoader.js`), Alpine.js (`alpine.min.js`), allen 10 Funki-`.glb`-Modellen, Showroom-Texturen, `.webp`-Fähigkeits-Icons und 2-Spur-BGM/SFX.
- **Modularer Baukasten fuer das Spieli-Team:**
  - `core/GameConfig.js` & `core/AudioEngine.js`
  - `character/MovementSpec.js` (Physik, Sprung, `Shift`-Sprint & 24-Bone-Rig fuer Baustein 1 & 2)
  - `combat/AbilityCatalog.js` (Faehigkeiten 1..5 & 4er-Kombo fuer Baustein 1 & `spieli2`)
  - `world/MagmaGymSpec.js` & `world/MerchantCatalog.js` (S-Kurven-Anker Raum 1..10, Haendler & 13-Slot-Paperdoll-Vorbereitung fuer Baustein 3)
  - `ui/GameHudController.js` (WeakMap-isolierte HUD-, Distrikt-, Chat-, Haendler- & Inventar-Steuerung)
  - `engine/LegacyFullWorld3D.js` (Kompletter 18.698-Zeilen 3D-Spielkern 1:1 verlustfrei eingebunden inkl. Goldklinge in der rechten Hand, 6 Herzen, Mana, Magma Raum 1..10 & Multiplayer)

---

## 3. Qualitaetssicherung & Testergebnis

Die automatisierte Test-Suite `tests/run_tests.php` prueft bei jedem Lauf PHP-Syntax, ES-Module-Syntax via `node --input-type=module --check`, lokale Asset-Vollstaendigkeit, `0` KI-Gedankenstriche, SSO-Replay-Abwehr, Live-Widerruf, Multiplayer-Distrikt-Sharding (`Soft-Cap 16` / `Hard-Cap 20`), Magma-6-Herzen-Escrow, Level-Abstufung bei `0/6` Herzen, Haendler-Kaeufe sowie echte HTTP-End-to-End-Aufrufe gegen `public/index.php` (**100 % Gruen**).
