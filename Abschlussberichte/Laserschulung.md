# Abschlussbericht: Laserschulung (Wissensdatenbank-Seeding)

**Status:** In Bearbeitung
**Projekt:** Seelenfunke
**Zieldatei:** `database/seeders/AiKnowledgeBaseLaserSeeder.php`

## Zielsetzung
Das primäre Ziel dieses Workflows ist die systematische Erfassung, Aufbereitung und Speicherung der Inhalte aus der Laserschutzschulung. Die generierte Wissensdatenbank dient als fundierte Informationsquelle für die KI (z.B. für Support-Telefonie oder Beratungsassistenzen im Arbeitsschutz). 

## Aktueller Workflow
Wir erarbeiten die Schulungsinhalte interaktiv und iterativ in folgendem Rhythmus:

1. **Informationsbeschaffung:** Der User postet die Rohdaten der Schulungsfolien (Texte, Zusammenfassungen, Aufzählungen) sowie Screenshots der dazugehörigen Quizfragen in den Chat.
2. **Datenverarbeitung & Strukturierung:** Die KI analysiert die bereitgestellten Informationen, extrahiert die wichtigsten technischen und biologischen Fakten (z.B. Linseneffekt, Expositionsgrenzwerte, thermische vs. fotochemische Effekte) und bereitet sie verständlich und logisch strukturiert auf.
3. **Quiz-Lösung:** Die KI analysiert die Multiple-Choice-Fragen aus den bereitgestellten Bildern, vergleicht sie mit dem Schulungsmaterial und ermittelt die exakt korrekte Antwortkombination.
4. **Datenbank-Seeding:** Die aufbereiteten Informationen sowie die dazugehörigen Quizfragen (inklusive der richtigen Antwort und einer kurzen KI-Erklärung) werden direkt als neuer Artikel in das Array des `AiKnowledgeBaseLaserSeeder.php` geschrieben.
5. **Validierung:** Der Befehl `php artisan db:seed --class=AiKnowledgeBaseLaserSeeder` wird ausgeführt, um Syntax-Fehler auszuschließen und die Daten direkt in die lokale Entwicklungsdatenbank zu laden.

## Bisheriger Fortschritt
- **Modul 1 (Grundlagen):** Physikalische Grundlagen von Laserstrahlung, Funktion von Resonator und aktivem Medium, Definition der stimulierten Emission.
- **Modul 2 (Biologische Wirkungen):**
  - **Auge:** Anatomie (Gelber/Blinder Fleck), Linseneffekt (500.000-fache Verstärkung), Wellenlängenabhängigkeit (IR-A vs. UV), Katarakt, Fotokeratitis.
  - **Haut:** Eindringtiefe verschiedener Spektralbereiche, Schutzmechanismus durch Melanin, Verbrennungen (thermisch) vs. Hautkrebs/Elastose (fotochemisch).
  - **Wirkungsmechanismen:** Thermisch, Fotochemisch, Fotoablation, Fotodisruption.
  - **Grenzwerte:** Expositionsgrenzwerte (EGW), MZB, ICNIRP, Richtlinie 2006/25/EG, OStrV.
  - **Sekundäre Gefahren:** Brand-/Explosionsgefahr, Laserschmauch, toxische Stoffe, elektrische Hochspannung, ionisierende Strahlung bei UKP-Lasern.
  - **Risiken nach Anwendungsbereich:** Materialbearbeitung vs. Mess-/Prüftechnik.
- **Modul 3 (Rechtliche Grundlagen und Regeln der Technik):**
  - **Rechtliche Kaskade:** Europäische Richtlinie (2006/25/EG, als 19. Einzelrichtlinie der 89/391/EWG) -> Nationale Verordnung (OStrV) -> Technische Regeln (TROS Laserstrahlung).
  - **Normen & Regelwerke:** Umfassende Listung der relevanten Normen (z.B. DIN EN 60825-1, -4, -13, -14 (Anwenderleitfaden) und DIN EN 11553 Serie für Bearbeitungsmaschinen).
  - **Persönliche Schutzausrüstung (PSA):** Spezifische Anwendungsbereiche von Laserschutzbrillen (DIN EN 207) und Justierbrillen (DIN EN 208) inkl. DGUV-Informationen. Ergänzung um flammenhemmende Kleidung, Handschuhe und Gesichtsschutz bei Reflexionsgefahr. Praxisbeispiel mit LB 6 Filter ergänzt.
  - **OStrV Anwendungsbereich:** Reine Arbeitsschutzverordnung vor tatsächlichen/möglichen Gefährdungen durch künstliche optische Strahlung. Ergänzt um Praxisbeispiele (Laser-Schneidanlagen, Markierlaser, Roboter).
  - **TROS Anwendungsbereich:** Schutz vor direkten/indirekten Gefährdungen für Laserstrahlung zwischen 100 nm und 1 mm (deckt alle industriellen Laser ab). Praxisbeispiel (2-kW-Faserlaser Laserschutzbereich) ergänzt.
  - **Verantwortung & Gefährdungsbeurteilung (§ 3 & § 5 OStrV):** Der Unternehmer/Arbeitgeber trägt die oberste Verantwortung. Exakter Prozess (Prüfung -> Bewertung -> Messung/Berechnung -> Maßnahmen). Durchführung nur durch fachkundige Personen. Ab Laserklasse 3R ist ein qualifizierter Laserschutzbeauftragter (LSB) schriftlich zu bestellen. Praxisbeispiel zu LSB-Aufgaben (Schweißzellen-Überwachung) ergänzt.
- **Modul 4 (Lasersicherheit und -schutz inkl. indirekter Gefährdungen):**
  - **Sinn der Laserklassen:** Vereinfachung der Gefährdungsbeurteilung, Zusammenfassung typischer Gefährdungsniveaus. Die Klassifizierung obliegt zwingend dem Hersteller.
  - **Umgang mit Prototypen:** Auch nicht-zertifizierte Anlagen (Prototypen) dürfen verwendet werden, müssen jedoch nach Herstellervorgaben so behandelt werden, als hätten sie die entsprechende Zielklasse.
  - **Die 9 Laserklassen (DIN EN 60825-1):** 
    - **Klasse 1:** Bei bestimmungsgemäßem Gebrauch sicher, schließt gekapselte Hochleistungslaser (mit Interlocks) ein.
    - **Klasse 1M & 1C:** 1M ist gefährlich bei optischen Hilfsmitteln (M = Magnification). 1C nur für Haut/Zielgewebe konzipiert (Emission stoppt bei Distanz).
    - **Klasse 2:** Nur sichtbares Spektrum (400-700nm). Sicher bei kurzer Exposition (<0,25s). *Achtung:* Niemals auf den Lidschlussreflex verlassen, Kopf aktiv wegdrehen!
    - **Klasse 2M:** Wie Klasse 2, jedoch gefährlich bei Nutzung optischer Hilfsmittel.
    - **Klasse 3A (alt):** Veraltete Klasse (bis 1997), wurde heute durch 1M & 2M abgelöst.
    - **Klasse 3R:** Gefährlich für Augen. Grenzwert 5mW (im sichtbaren Bereich = 5-facher Wert von Klasse 2). Direkte Bestrahlung zwingend vermeiden. *Ab hier LSB-Pflicht!*
    - **Klasse 3B:** Gefahr für Augen (auch bei extrem kurzem direkten Blick) und Haut. Strahl kann entzündliche Materialien entflammen. Betrachtung ggf. über spezifische diffuse Reflektoren möglich.
    - **Klasse 4:** Extreme Gefahr! Hochleistungslaser, welche die Grenzwerte aller anderen Klassen überschreiten. Gefährlich für Auge und Haut. Sogar diffuse Strahlung ist hier hochgradig gefährlich. Es besteht akute Brand- und Explosionsgefahr.
  - **Grenzen der Klassifizierung:** Hersteller stufen extrem konservativ (Worst-Case) ein. Dennoch können unvorhergesehene Umstände (große Ferngläser, extrem kleine Strahldurchmesser bei hoher Intensität) selbst bei vermeintlich sicheren Lasern zu Schäden führen. Eine Gefährdungsbeurteilung bleibt Pflicht!
  - **Zusätzliche Industrie-Risiken:** Reflexionen an Metallen, defekte Optiken, mehrere Strahlquellen und Eingriffe in geschlossene Gehäuse. Klasse 3B und 4 dürfen nur in zertifizierten Laserschutzbereichen betrieben werden.
- **Update Modul 3 (Recht):** Hinweis ergänzt, dass die DGUV Vorschrift 11 seit dem 1. April 2023 außer Kraft gesetzt ist und durch BetrSichV und TROS abgelöst wurde.
- **Kennzeichnung von Lasern:** Spezifischer Abschnitt zu den Schildern nach DIN EN 60825-1 hinzugefügt (Rechteck für Klasse 1, obligatorisches Warn-Dreieck ab Klasse 2 sowie Pflichtangaben zu Wellenlänge/Leistung). Mit Praxisbeispielen (Markier-, Faser- und Justierlaser) angereichert.
- **Vermeidung von Gefährdungen (§ 7 OStrV):** S-T-O-P Prinzip rechtlich verankert. Klargestellt, dass kollektive Maßnahmen immer Vorrang haben. Ergänzt um organisatorische Detail-Pflichten wie Reflexionsvermeidung (Ablegen von Schmuck/Uhren), dedizierte Planung der Strahlwege (nicht in Verkehrswege) und die zwingende LSB-Bestellung ab Klasse 3R. Im Bereich PSA wurde ein extrem detaillierter Leitfaden integriert: Unterscheidung von Schutz- (LB) und Justierbrillen (RB), das 5-Sekunden-Schutzfenster, die CE-Kategorie II, die Schutzfunktion von spezieller Kleidung sowie strenge Vorgaben zu Pflege und Aufbewahrung (Kratzschutz). Passendes Quiz zu PSA und Maßnahmen Gruppen integriert.
- **Arbeitsmedizinische Vorsorge:** Detaillierte Auflistung der Rechte und Pflichten. Unterscheidung zwischen reiner Laserstrahlung (Wunschvorsorge) und zusätzlicher inkohärenter Strahlung wie Schweißlicht (Angebotsvorsorge bei Grenzwertnähe, Pflichtvorsorge bei Grenzwertüberschreitung). Neues Quiz hinzugefügt.
- **Bauliche und konstruktive Schutzmaßnahmen (BG ETEM):** Detaillierte Auflistung der anlagenspezifischen Notwendigkeiten gestaffelt nach Laserklasse (z.B. Not-Halt bei allen Klassen, Verriegelung ab 3R, Schlüsselschalter ab 3B). Pflicht zu speziellen Beobachtungseinrichtungen (Laserfilter-Sichtfenster oder Kameras) ergänzt. Passendes Quiz zu den technischen Minimalanforderungen integriert.
- **Indirekte & Sekundäre Gefahren (Modul 2):** Stark erweiterte Richtlinien zum Umgang mit Blendungen (bauliche Abschirmung, reflexionsarmer Arbeitsplatz), Explosions-/Brandgefahr (speziell für Laserklasse 4: separater Raum, Kühlung, GefStoffV) sowie zum Schutz vor UV-, inkohärenter und ionisierender Strahlung. Neues Quiz integriert.
- **Pflichten des Arbeitgebers (Erweiterte Schutzmaßnahmen):** Gesetzliche Vorgaben zu fortlaufend aktualisierten Betriebsanweisungen, der Pflicht zur Überprüfung der PSA-Anwendung, den Meldepflichten der Arbeitnehmer bei Gefahren und Defekten sowie der strikten Dokumentationspflicht (inkl. jährlicher Unterweisung) integriert. Ein Best-Practice-Szenario zum Abschluss eingefügt. Inklusive Quiz.

## Nächste Schritte
Abschlussbericht und Datenbasis sind up to date. Bereit für den Feinschliff oder die finale Deploy-Prüfung der Module.
