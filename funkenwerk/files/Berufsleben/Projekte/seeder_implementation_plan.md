# Implementierungsplan: Aktualisierung AiKnowledgeBaseSeeder

## Ausgangslage
Die Wissensdatenbank in der Datenbank enthält aktuelle Informationen zu Laserbauarten und -anwendungen, die im `AiKnowledgeBaseSeeder` fehlen.

## Ziel
Den `AiKnowledgeBaseSeeder` so zu aktualisieren, dass er alle aktuellen Daten aus der Datenbank widerspiegelt, um die Konsistenz bei Neuinstallationen zu gewährleisten.

## Schritte
1.  **Datenextraktion:** Abrufen des neuen Laser-Wissensartikels aus der Datenbank.
2.  **Code-Analyse:** Identifizierung der Stelle im Seeder, an der neue Artikel eingefügt werden müssen.
3.  **Seeder-Update:** Einfügen der fehlenden Daten in das `$articles` Array im Seeder.
4.  **Kategorien/Tags:** Sicherstellen, dass notwendige neue Kategorien ('Laserschutz & Sicherheit') und Tags ('Laser', 'Bauarten', 'Anwendungen') im Seeder definiert sind.

## Daten für das Update
**Neuer Artikel:**
*   **Titel:** 'Detaillierte Laserdaten Bauarten und Anwendungen'
*   **Kategorie:** 'Laserschutz & Sicherheit'
*   **Tags:** ['Laser', 'Wissen', 'Tabelle']
*   **Inhalt:** (Markdown-Tabelle aus dem Memory)