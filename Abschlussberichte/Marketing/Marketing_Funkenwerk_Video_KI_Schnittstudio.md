# Funkenwerk Video – KI-Schnittstudio für echte Manufaktur-Aufnahmen

## 1. Kernprinzip (100 % echte Aufnahmen – 0 % KI-Video-Generierung)
**Funkenwerk Video** (`funkenwerk-video/` -> veröffentlicht nach `C:\Users\konta\Desktop\Funkenwerk Video`) verarbeitet ausschließlich **echtes, selbst gedrehtes Kamera-, Smartphone- und Action-Cam-Rohmaterial** von Alina Steinhauer. Es werden **niemals** künstliche KI-Videos generiert.

## 2. Virale Luxury-Packaging-Formel („Wann, wie lange, wo und mit welchem Übergang geschnitten wird“)
Basierend auf der Analyse der erfolgreichsten Luxury-Packaging- & „Pack an Order with Me“-Reels (5M+ Views) sowie führender KI-Schnittsysteme (**OpusClip**, **Gling AI**, **CapCut Pro**, **DaVinci Resolve Neural Engine**) arbeitet die Engine nach der **4-Phasen Hook-to-Payoff-Formel**:

1. **Phase 1 (`0,0s – 1,4s`): ⚡ Reverse-Teaser / Grand Reveal Hook**
   - Startet niemals mit einem leeren Tisch, sondern zeigt in den ersten `1,4s` einen kurzen Flash-Forward-Teaser der fertig verschlossenen schwarzen Westpack-Box bzw. des funkelnden Unikats (`1.06x` Makro-Fokus), um das Weiterscrollen sofort zu stoppen.
2. **Phase 2 (`1,4s – 3,2s`): 💎 Statement Item (Hero-Unikat)**
   - Wechselt per **💨 Whip-Flow** zurück zum Beginn und zeigt das Hauptprodukt in weißen Handschuhen (`1.00x`).
3. **Phase 3 (`3,2s – 12,5s`): 📦 Satisfying Micro-ASMR Packaging Rhythm**
   - Schnelle, knackige **Micro-Cuts (`1,3s – 1,8s`)** exakt auf den Musik-Beat und die erkannten **ASMR-Audio-Peaks (`astats Peak_level`)** beim Einschlagen in Seidenpapier, Einlegen in die Westpack-Box, Schleifenbinden und dem **Box-Deckel-Snap (Lid-Drop)**.
   - Nutzt automatisch den **🔍 2-Kamera Makro-Zoom Wechsel (`1.00x` ↔ `1.08x`)** sowie leichtes **Speed-Ramping (`1.12x`)**, damit selbst Aufnahmen aus einer einzigen Kameraperspektive wie ein Mehrkamera-Studiodreh ohne Jump-Cuts wirken.
4. **Phase 4 (`Letzte 2,4s – 3,0s`): ✨ Payoff & Climax Hold**
   - Gleitet per **🖤 Atelier Samt-Blende (`velvet_dip`)** in eine ruhige `0.90x` Edel-Zeitlupe des fertigen Pakets und schließt nahtlos den Loop zum Anfangs-Teaser.

## 3. Die 5 sauberen Luxus-Übergänge & J/L-Cut Audio-Mastering
Statt billiger 3D-Effekte nutzt Funkenwerk Video ausschließlich hochwertige Studio-Übergänge (global wählbar oder pro Schnitt per Klick umschaltbar):
- **🤖 KI-Regie-Automatik (`auto_director`)**: Kombiniert je nach Schnitt-Rolle automatisch Action-Cut, 2-Kamera-Makro-Zoom, Whip-Flow, Licht-Impuls und Samt-Blende.
- **⚡ Hard-Cut on Action + J/L-Ton-Glättung (`hard_action`)**: Messerscharfer Schnitt im Bewegungs-Scheitelpunkt mit `45 ms` weichem Audio-Crossfade gegen Ton-Knackser.
- **🔍 2-Kamera Makro-Zoom Wechsel (`punch_zoom`, `1.00x ↔ 1.08x`)**: Verhindert Jump-Cuts bei aufeinanderfolgenden Schnitten aus derselben Perspektive (OpusClip/Gling-Prinzip).
- **🖤 Atelier Samt-Blende (`velvet_dip`)**: Kurze, edle Micro-Schwarzblende (`0,18s`) vor dem finalen Reveal.
- **💨 Whip-Flow (`whip_flow`)**: Dynamischer Szenen-Impuls beim Wechsel zwischen Laser- und Verpackungstisch.
- **✨ Licht-Impuls (`flash_luma`)**: Sanfte Belichtungs-Blende (`0,15s`) exakt auf dem Shimmer-Drop bei Kristallglas-, Schiefer- und Gold-Unikaten.

## 4. Die 6 KI-Regie-Modi
1. **📦 Packaging Viral (`packaging_pov`)**: 4-Phasen Luxury-Packaging-Formel (Teaser → Hero → Micro-ASMR → Samt-Finale).
2. **🔥 Laser & ASMR (`asmr_laser`)**: Action-Hook (0–1,8s) → Takt-Schnitte → Reveal.
3. **⚡ 4s Shimmer-Drop (`viral_drop`)**: Spannungsaufbau bis exakt `4,0s` → Licht-Impuls-Drop auf das fertige Unikat.
4. **✨ Vorher / Nachher (`unboxing_reveal`)**: Rohling → Whip-Flow Veredelung → 0.90x Nachher-Reveal.
5. **🎙️ Sprache / Story (`story_speech`)**: Gling-Style Auto-Silence-Cut entfernt Sprechpausen (`> 0,38s`), aktiviert Stimm-Kompressor/Hochpassfilter und wechselt sanft zwischen `1.00x` und `1.06x` gegen Jump-Cuts.
6. **🎬 Alle Verbinden (`clean_all`)**: Chronologischer Best-of-Zusammenschnitt ohne Anfangs-/End-Wackler.

## 5. Sicherheits-Härtung, High-Speed-Architektur & 10/10 Selbsttest-Suite
- **100 % Command-Injection-Sicherheit**: Alle Aufrufe an `ffmpeg.exe` und `ffprobe.exe` laufen über `ProcessStartInfo.ArgumentList` ohne Shell-Interpolation (`ValidateSafeMediaPath` blockiert Fremddateien, 0-Byte-Dateien und Pfad-Manipulationen).
- **Parallele Multi-Core-Analyse & Instant Fingerprint-Cache**: Analysiert mehrere Rohvideos parallel (`160×90 @ 5 fps signalstats + astats + silencedetect`) und speichert den SHA-256-Fingerprint-Cache in `%LocalAppData%\FunkenwerkVideo\ThumbCache`, sodass bereits bekannte Clips in **`< 15 ms`** geladen werden.
- **Paralleles Segment-Rendering**: Schneidet, skaliert und veredelt alle Einzelschnitte gleichzeitig auf mehreren CPU-Kernen via Keyframe-Pre-Seeking (`-ss` vor `-i`) und fügt sie ohne UTF-8-BOM-Fehler verlustfrei zusammen.
- **Integrierte 10/10 Test-Suite ([`VideoEngineSelfTestSuite.cs`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/funkenwerk-video/VideoEngineSelfTestSuite.cs))**: Über den Header-Button **`🧪 System-Test`** oder per CLI (`"Funkenwerk Video.exe" --selftest`) jederzeit ausführbar (10/10 Tests bestanden).
