# Dokumentation: Standalone Python-Umgebung auf dem Mittwald-Staging-Server

Diese Dokumentation beschreibt die Einrichtung und Konfiguration einer dedizierten, standalone Python-Umgebung auf dem Mittwald-Staging-Server (`seelenfunke-stage`), um die Generierung von Word-Berichten (`.docx`) mittels des AI-Agenten über das PHP-System zu ermöglichen.

---

## 1. Ausgangslage & Problemstellung

Um Word-Dokumente (`.docx`) dynamisch über KI-Agenten zu generieren, benötigt die Anwendung Zugriff auf Python und die Bibliothek `python-docx` (sowie deren Binärabhängigkeit `lxml`). 

### Restriktionen auf dem Mittwald-Hosting:
* **Kein globales Python 3:** Der PHP-FPM-Container bzw. die CLI-Umgebung besitzt standardmäßig kein globales oder aktuelles Python 3.
* **Fehlende Root-Rechte:** Es ist uns nicht möglich, Systempakete über einen Paketmanager (`apt`, `yum`) global zu installieren.
* **Chroot-Gefängnis (Jail):** Der PHP-Prozess läuft in einer Sandbox-Umgebung mit eingeschränktem Pfad-Zugriff, was das Ausführen von externen Programmen einschränkt.

---

## 2. Die gewählte Architektur: CPython Standalone

Um diese Einschränkungen zu umgehen, nutzen wir eine **portable Standalone-Distribution von Python** aus dem Projekt `python-build-standalone` (bereitgestellt von Astral). 

* **Vorteil:** Die gesamte Python-Laufzeitumgebung befindet sich direkt im Projektverzeichnis (`/home/p-g27wim/html/seelenfunke-stage/python`) und ist vollständig portabel und isoliert.
* **Gewählte Version:** **CPython 3.10.13** (x86_64, GNU/Glibc-Flavor, `install_only`).

---

## 3. Die Glibc-Kompatibilitätshürde (ELFCLASS32-Fehler)

Bei der ersten Ausführung der standalone Python-Binärdatei stießen wir auf folgenden kritischen Fehler des dynamischen Linkers:

```bash
/home/p-g27wim/html/seelenfunke-stage/python/bin/python3.10.bin: error while loading shared libraries: libutil.so.1: wrong ELF class: ELFCLASS32
```

### Ursachenanalyse:
1. **Bibliotheks-Konflikt:** Die Python-Binärdatei benötigt Hilfsbibliotheken wie `libutil.so.1`, `librt.so.1` und `libdl.so.2`. Der Standard-Suchpfad des Systems verwies jedoch auf 32-Bit-Versionen dieser Bibliotheken (`wrong ELF class: ELFCLASS32`), während Python ein 64-Bit-Prozess ist.
2. **Glibc-Architekturänderung ab v2.34:** 
   * Auf dem Server läuft **Debian GLIBC 2.36** (eine moderne 64-Bit-Version).
   * Seit **Glibc 2.34** sind die Funktionen von Legacy-Bibliotheken (`libdl`, `libutil`, `librt`, `libpthread`) vollständig in die Haupt-C-Bibliothek (`libc.so.6`) integriert worden.
   * Auf modernen Systemen existieren diese Bibliotheken oft gar nicht mehr als eigenständige 64-Bit-Dateien oder sind nur leere Stubs.
   * Weil die Bibliotheken im regulären 64-Bit-Bibliothekspfad fehlten, suchte der Linker weiter und stieß fälschlicherweise auf die 32-Bit-Legacy-Bibliotheken des Systems.

---

## 4. Die technische Lösung (Der Symlink- & Linker-Workaround)

Um das Problem ohne Root-Rechte und ohne Änderung des globalen Betriebssystems zu lösen, haben wir zwei Mechanismen implementiert:

### 1. Kompatibilitäts-Bibliotheksordner (`compat-libs`)
Wir haben einen lokalen Ordner unter `python/compat-libs/` angelegt. Da ab Glibc 2.34 alle benötigten Symbole von `libdl`, `libutil` und `librt` in der `libc.so.6` enthalten sind, haben wir symbolische Links erstellt, die diese Bibliotheken direkt auf die 64-Bit-Version von `/usr/local/php/lib/libc.so.6` verweisen lassen:

* `python/compat-libs/libdl.so.2` &rarr; `/usr/local/php/lib/libc.so.6`
* `python/compat-libs/libutil.so.1` &rarr; `/usr/local/php/lib/libc.so.6`
* `python/compat-libs/librt.so.1` &rarr; `/usr/local/php/lib/libc.so.6`

Dadurch wird dem dynamischen Linker vorgegaukelt, diese Bibliotheken seien vorhanden, und er lädt stattdessen direkt die 64-Bit-Haupt-`libc.so.6`.

### 2. Der Linker-Wrapper für `python3.10`
Um sicherzustellen, dass Python immer den korrekten 64-Bit-Linker des Systems und unseren `compat-libs`-Pfad verwendet, haben wir die originale Python-Binärdatei umbenannt und durch ein Shell-Wrapper-Skript ersetzt:

1. `python/bin/python3.10` wurde umbenannt in `python/bin/python3.10.bin` (das echte Kompilat).
2. Ein neues Bash-Skript wurde unter `python/bin/python3.10` angelegt:

```sh
#!/bin/sh
LINKER="/usr/local/php/lib/ld-linux-x86-64.so.2"
REAL_PYTHON="$(dirname "$0")/python3.10.bin"
COMPAT_LIBS="$(dirname "$0")/../compat-libs"

if [ -f "$LINKER" ]; then
    # Startet Python explizit über den 64-Bit-Linker und überschreibt den Suchpfad
    exec "$LINKER" --library-path "/usr/local/php/lib:$COMPAT_LIBS" "$REAL_PYTHON" "$@"
else
    # Fallback für andere Systeme/Umgebungen
    exec "$REAL_PYTHON" "$@"
fi
```

Da `python3` und `pip3` symbolische Links auf `python3.10` sind, greift dieser Wrapper automatisch bei jedem Python- und Pip-Aufruf!

---

## 5. Das Diagnose- & Setup-Skript: `test_python_env.php`

Um diesen komplexen Setup-Vorgang direkt auf dem Server automatisieren und testen zu können, wurde das PHP-Skript `test_python_env.php` entwickelt.

### Hauptfunktionen von `test_python_env.php`:
* **Systemdiagnose:** Erkennt Betriebssystem, Prozessorarchitektur, installierte ldd/Glibc-Versionen und das Vorhandensein von Python-Umgebungen.
* **Automatisierter Download:** Lädt das passende Standalone-Python-Archiv direkt von GitHub herunter.
* **Symlink-Erstellung:** Legt die symbolischen Links im Ordner `python/compat-libs` an.
* **Wrapper-Generierung:** Benennt die Binärdatei um und schreibt das Wrapper-Skript mit den passenden Pfaden.
* **Bibliotheken-Installation:** Bietet eine One-Click-Schaltfläche zur Installation von `python-docx` über die neu konfigurierte Standalone-Pip-Umgebung.

### Sicherheits-Verschiebung:
> [!WARNING]
> Aus Sicherheitsgründen wurde dieses Skript aus dem öffentlichen Web-Verzeichnis (`public/test_python_env.php`) in dieses Verzeichnis (`Abschlussberichte/Agenten/Python/test_python_env.php`) verschoben. 
> Da das Skript Shell-Befehle ausführen und Systemkonfigurationen verändern kann, darf es auf keinen Fall über das Internet frei zugänglich sein.

---

## 6. Integration in die Anwendung & Nutzung

Damit die Laravel-Anwendung auf dem Staging-Server die richtige Python-Umgebung nutzt, müssen folgende Schritte durchgeführt werden:

1. **Konfiguration der Umgebungsvariablen (`.env`):**
   In der `.env`-Datei des Staging-Servers muss der Pfad zum Python-Wrapper hinterlegt werden:
   ```env
   PYTHON_BINARY=/home/p-g27wim/html/seelenfunke-stage/python/bin/python3
   ```

2. **Aufruf im PHP-Code:**
   Der PHP-Bridge-Code startet Python-Prozesse nun dynamisch unter Verwendung dieser Umgebungsvariablen:
   ```php
   $pythonBinary = env('PYTHON_BINARY', 'python3');
   $process = new Process([$pythonBinary, base_path('scripts/generate_report.py'), ...]);
   $process->run();
   ```

---

## 7. Verifizierter Status

* **Python-Ausführung:** Erfolgreich (Gibt `Python 3.10.13` zurück).
* **Pip-Ausführung:** Erfolgreich.
* **Bibliotheks-Installation:** `python-docx` (Version 1.2.0), `lxml` (Version 6.1.1) und `typing_extensions` (Version 4.15.0) wurden erfolgreich im lokalen Ordner `python/lib/python3.10/site-packages` installiert.
* **Import-Test:** Der Befehl `python3 -c "import docx; print('ok')"` gibt fehlerfrei `ok` zurück.
