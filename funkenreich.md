# Das Funkenreich · Architektur-Regelwerk & Datei-Landkarte (`funkenreich.md`)

> Verbindliche Leitarchitektur fuer das eigenstaendige 3D-MMORPG **„Das Funkenreich“** (`https://game.mein-seelenfunke.de`).
> **Oberste Direktive:** Jeglicher Spielcode, 3D-Weltenbau, Multiplayer-Sync, Charakter-Editor, Kampf und Parkour findet **ausschliesslich** in diesem eigenstaendigen Projekt statt. Das Hauptportal (`seelenfunke`) bleibt dauerhaft frei von Spiel-Ballast!

---

## 1. Umgebungen, Pfade & Sicherheits-Leitplanken

- **Projekt-Pfad (WSL):** `/home/ubuntuxina/meine-projekte/funkenreich`
- **Projekt-Pfad (Windows UNC fuer Dateitools):** `\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\funkenreich`
- **Live- & Stage-Domain:** `https://game.mein-seelenfunke.de` (`DocumentRoot: /public`)
- **Hauptportal (Shop):** `https://mein-seelenfunke.de` (`/home/ubuntuxina/meine-projekte/seelenfunke`)
- **Suchmaschinen-Abschirmung:** Strikter Header `X-Robots-Tag: noindex, nofollow, noarchive` auf allen Routen sowie `Disallow: /` in `public/robots.txt`.
- **Closed-Alpha-Waechter:** Unbefugte Aufrufer erhalten auf `GET /` immer **HTTP `403 Forbidden`** mit der edlen Schwarz-Gold Vorschau-Seite (`app/Views/gate_locked.php`) und auf allen API-Endpunkten **HTTP `403`** JSON (`closed_alpha_locked`).
- **Pfad- & Text-Integritaet:** In Markdown-Links fuer WSL exakt zwei Slashes (`file://wsl.localhost/Ubuntu/...`) nutzen. Niemals KI-Gedankenstriche verwenden; als Trenner dient ` · ` oder ein normaler Bindestrich `-`.

---

## 2. Vollstaendige Baukasten-Ordnerstruktur (Baustein 0 bis 6)

```text
/home/ubuntuxina/meine-projekte/funkenreich/
├── .env.example                            # Vorlage fuer SSO-Secret, Shop-URLs & SQLite-Pfad
├── .gitignore                              # Schliesst .env, *.sqlite, Nonces & Logs strikt aus
├── funkenreich.md                          # DIESE ARCHITEKTUR-REGEL (immer synchron halten!)
├── _preserved_snapshot/                    # Unverfaelschtes Referenz-Archiv des urspruenglichen Spielcodes
├── app/
│   ├── Bootstrap.php                       # PSR-4 Autoloader (Funkenreich\*), Env-Loader, Session-Hardening, CSRF & Security-Headers
│   ├── Security/
│   │   └── SsoGateGuard.php                # HMAC-SHA256 Token-Pruefung, Single-Use Nonce-Schutz & 30s Live-Revocation-Check
│   ├── Services/
│   │   ├── PlayerStoreService.php          # Atomare SQLite-WAL Datenbank (Spieler, Nonces, Magma-Runs, Rucksack, Helden-Ranking)
│   │   ├── LobbyInstanceManager.php        # Distrikt-Sharding (#1..50, Soft-Cap 16 / Hard-Cap 20), 60-FPS Peer-Sync, Chat & Shadow-Mute
│   │   └── MagmaParkourEngine.php          # Server-Anti-Cheat, 6-Herzen-Escrow, S-Kurven-Anker Raum 1..10, Schild & Level-Abstufung
│   └── Views/
│       ├── gate_locked.php                 # HTTP 403 Schwarz-Gold Vorschau ("Das Funkenreich · Geschlossene Alpha-Phase")
│       └── game_client.php                 # Vollbild 60-FPS 3D-Spiel-Client (Herzen, Mana, Skills 1..5, Q/R, Rucksack I, Haendler, Chat)
├── public/
│   ├── .htaccess                           # Apache Rewrite auf index.php + Asset-Caching + Security-Headers
│   ├── robots.txt                          # User-agent: * / Disallow: /
│   ├── index.php                           # Schlanker, schneller PHP 8.4 Front-Controller & API-Router
│   ├── images/lobby/icons/                 # Lokale .webp Icons (Faehigkeiten 1..5, Heiltrank, Rucksack)
│   ├── shop/                               # Lokale .glb Funki-Modelle (Stufe 1..10), Showroom-Texturen & BGM/SFX (.mp3)
│   ├── vendor/three/                       # Lokale three.min.js & OrbitControls.js (0 externe CDNs!)
│   └── js/
│       ├── vendor/                         # Lokale alpine.min.js, three.module.min.js & GLTFLoader.js
│       ├── seelenfunke-louvre-showroom-3d.js # Abwaertskompatibler Re-Export auf LegacyFullWorld3D.js
│       └── funkenreich/                    # MODULARER ES-MODULE BAUKASTEN
│           ├── main.js                     # Zentraler Bootstrapper & lokaler Alpine-Starter
│           ├── core/
│           │   ├── GameConfig.js           # Zonen, API-Routen, Keybindings & Basis-Konstanten
│           │   └── AudioEngine.js          # Autarke 2-Spur WebAudio-Engine (Calm-Lobby & Magma-Action + Matte SFX)
│           ├── character/
│           │   └── MovementSpec.js         # Physik-, Sprung-, Shift-Sprint-, Kamera- & 24-Bone-Rig-Spezifikation (Baustein 1 & 2)
│           ├── combat/
│           │   └── AbilityCatalog.js       # Faehigkeiten 1..5, 4er-Kombo & Cooldown-Katalog (Baustein 1 & spieli2)
│           ├── world/
│           │   ├── MagmaGymSpec.js         # Deterministische S-Kurven-Insel-Anker & Raum-1..10-Parameter
│           │   └── MerchantCatalog.js      # Haendler-Sortiment (Heiltrank) & Rucksack-/Paperdoll-Spezifikation (Baustein 3)
│           ├── ui/
│           │   └── GameHudController.js    # WeakMap-isolierte HUD-, Distrikt-, Chat-, Haendler-, Inventar- & Settings-Steuerung
│           └── engine/
│               └── LegacyFullWorld3D.js    # Verlustfreier 18.698-Zeilen 3D-Spielkern (Halle der Helden, Goldklinge, Magma 1..10)
├── storage/                                # Geschuetzte Laufzeitdaten: database/funkenreich.sqlite, nonces/, logs/
└── tests/
    └── run_tests.php                       # Vollautomatische Pflicht-Test-Suite (PHP, ES-Module, SSO, Multiplayer, Magma, HTTP-E2E)
```

---

## 3. Kryptographischer SSO- & Live-Widerrufs-Vertrag mit dem Shop

### 3.1 Einstieg via `GET /sso/callback?token=<base64url_payload>.<hex_hmac_sha256>`
1. **Signaturpruefung:** `hash_equals(hash_hmac('sha256', $base64urlPayload, FUNKENREICH_SSO_SECRET), strtolower($hexSig))`.
2. **JSON-Payload-Schema:**
   ```json
   {
     "iss": "mein-seelenfunke",
     "sub_type": "admin",
     "sub_id": "...",
     "display_name": "Alina",
     "funki_level": 10,
     "funken_balance": 500,
     "funken_total_earned": 6000,
     "magma_unlocked_level": 10,
     "magma_hearts": 6,
     "healing_potions": 5,
     "mmorpg_access": true,
     "iat": 1763460000,
     "exp": 1763460060,
     "nonce": "32-char-random-hex"
   }
   ```
3. **Replay-Schutz & Session-Rotation:**
   - Prueft `iss === "mein-seelenfunke"`, `exp >= time()` und `mmorpg_access === true`.
   - Verbraucht `nonce` atomar in SQLite (`used_sso_nonces` Primary Key). Kein Token kann ein zweites Mal eingelost werden.
   - Synchronisiert das Profil in `PlayerStoreService`, rotiert die Session-ID (`session_regenerate_id(true)`) und leitet per **HTTP `302`** sauber auf `/` weiter (0 Token in der Browser-Adresszeile).

### 3.2 Live-Widerrufs-Check (`SsoGateGuard`)
- Fuer aktive Kunden-Sessions (`sub_type === 'customer'`) prueft `SsoGateGuard` alle **30 Sekunden** gegen `SHOP_INTERNAL_VERIFY_URL` (`?sub_type=...&sub_id=...`).
- Signatur-Header:
  - `X-Funkenreich-Timestamp: <unix_ts>`
  - `X-Funkenreich-Signature: hash_hmac('sha256', "$subType|$subId|$ts", FUNKENREICH_SSO_SECRET)`
- Meldet der Shop `{"allowed": false}`, wird `mmorpg_access` sofort entzogen und die Spiel-Session augenblicklich beendet.

---

## 4. Gemappte HTTP- & Spiel-API-Routen (`public/index.php`)

| Methode | Pfad | Funktion |
| :--- | :--- | :--- |
| `GET` | `/up` | Healthcheck (`{"status":"ok","app":"funkenreich"}`) |
| `GET` | `/` | Closed-Alpha-Gate (`403`) bzw. Vollbild 60-FPS 3D-Spiel-Client (`200`) |
| `GET` | `/sso/callback` | HMAC-SSO-Anmeldung, Nonce-Verbrauch & `302` Redirect auf `/` |
| `GET/POST` | `/logout` | Entfernt Spieler aus Distrikt, beendet Session & leitet zum Shop |
| `GET` | `/shop/showroom-data` | Bootstrap-Daten inkl. Spieler-Profil, Goldklinge (`gold_blade`) & Magma-Status |
| `GET` | `/funkenwerk-welt/heroes-data` | Zero-PII Ehrenpodeste der Halle der Helden (mindestens 10 Slots) |
| `GET` | `/api/gamification/funkenwerk-heroes` | Alias fuer `heroes-data` |
| `GET` | `/api/player/state` | Aktueller Spieler-, Herz-, Funken- & Rucksack-Zustand |
| `POST` | `/funkenwerk-welt/lobby/join` | Distrikt-Beitritt (Auto-Sharding Soft-Cap 16 / Hard-Cap 20) |
| `POST` | `/funkenwerk-welt/lobby/sync` | 60-FPS Peer-Synchronisation, Anti-Teleport-Clamp & Chat-Abruf |
| `POST` | `/shop/showroom-sync` | Abwaertskompatibler Alias auf `lobby/sync` |
| `POST` | `/funkenwerk-welt/lobby/switch` | Manueller Distrikt-Wechsel (`1..50`, `409` bei `20/20`) |
| `POST` | `/funkenwerk-welt/lobby/leave` | Sofortige Freigabe des Distrikt-Slots (Fetch & `sendBeacon`) |
| `POST` | `/funkenwerk-welt/lobby/chat` | Raum-Chat mit HTML/XSS-Sperre, 2s Cooldown & Flood-Schutz |
| `POST` | `/shop/showroom-chat` | Abwaertskompatibler Alias auf `lobby/chat` |
| `POST` | `/funkenwerk-welt/lobby/report` | Mitspieler-Meldung (ab 2 Meldungen 24h Shadow-Mute) |
| `POST` | `/shop/showroom-report` | Abwaertskompatibler Alias auf `lobby/report` |
| `POST` | `/funkenwerk-welt/lava-parkour/start` | Startet Magma-Lauf (Raum `1..10`) inkl. Pessimistic 6-Herzen-Escrow |
| `POST` | `/funkenwerk-welt/lava-parkour/commit` | Markiert Lauf nach Verlassen des Startbalkons als aktiv (`committed`) |
| `POST` | `/funkenwerk-welt/lava-parkour/leave` | Sicheres Verlassen auf dem Startbalkon (`rel_x <= 3.2`) ohne Herzverlust |
| `POST` | `/funkenwerk-welt/lava-parkour/hit` | Verarbeitet Treffer, Aegis-Schild (max. 3/Run), Herzabzug & 0/6 Level-Abstufung |
| `POST` | `/funkenwerk-welt/lava-parkour/checkpoint` | Verifiziert Checkpoint 1 & 2 (Distanz, Split-Zeiten, `life_nonce`, `claim_token`) |
| `POST` | `/funkenwerk-welt/lava-parkour/step` | Alias auf `lava-parkour/checkpoint` |
| `POST` | `/funkenwerk-welt/lava-parkour/claim` | Single-Use Schatztruhen-Abschluss, Funken-Gutschrift & Raum-Freischaltung |
| `POST` | `/funkenwerk-welt/lava-parkour/complete` | Alias auf `lava-parkour/claim` |
| `POST` | `/funkenwerk-welt/merchant/buy-potion` | Heiltrank-Kauf (8 Funken aus `funken_balance`, Stack bis 5, max. 30) |
| `POST` | `/funkenwerk-welt/inventory/use-item` | Trinkt Heiltrank (`+2` Herzen bis `6/6`, Schutz `HEARTS_FULL`) |
| `POST` | `/funkenwerk-welt/inventory/equip-slot` | Rucksack-Slot-Tausch (`0..5`) & Quick-Slot-Belegung (`Q` / `R`) |
| `POST` | `/customer/gamification/audio-settings` | Speichert Lautstaerke (`0..100`) & Stummschaltung |
| `POST` | `/funkenwerk-welt/inline-login` | SSO-Session-Statusabfrage fuer Client-Kompatibilitaet |
| `POST` | `/funkenwerk-welt/activate-magic` | Magie-Statusabfrage fuer Client-Kompatibilitaet |

---

## 5. Spieli-Studio-Zustaendigkeiten & Ausbau-Fahrplan

- **`spieli` (Lead Game Director & Engine/Movement Architect):** Gesamtkoordination, Pflege dieser `funkenreich.md`, `core/`, `character/MovementSpec.js`, Next-Gen Movement, `Shift`-Sprint, Physik & 60-FPS-Kamera.
- **`spieli1` (3D Character, Rigging, Armor-Extraction & Animation Artist):** Weibliche/maennliche Basis-Charaktere, Voelker (*Funkenwaechter* Gold vs. *Seelenzerstoerer* Rot), extrahierte `.glb`-Ruestungen/Waffen & Animationen.
- **`spieli2` (Combat, Abilities, Shader-VFX & Matte Audio Designer):** Kampfsystem (`combat/`), Faehigkeiten-Choreografien, Slash-/Partikel-Shader, gruene Checkpoint-Effekte & matte Kampf-SFX.
- **`spieli3` (RPG Backend, SSO-Gate, Naming & Paperdoll-UI Architect):** Closed-Alpha-SSO, Level `1..100`, 6 Attribute (`STR, DEX, CON, INT, WIS, CHA`), Charakter-Editor, 6-Stufen-Namenspruefung, Multiplayer-Instanzen & 13-Slot Paperdoll-Inventar.

---

## 6. Verbindliche Pflicht-Tests vor jedem Abschluss (`100 % Gruen`)

```bash
cd /home/ubuntuxina/meine-projekte/funkenreich && php tests/run_tests.php
```

Alle PHP-Lints, `node --input-type=module --check` Pruefungen, Asset-Checks, Zero-Dash-Pruefungen, SSO-Replay-Tests, Multiplayer-Sharding-Tests, Magma-Escrow-Tests und Live-HTTP-Tests muessen zu **100 % Gruen** durchlaufen.
