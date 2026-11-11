# Workflow: KI-Agentenwechsel

Dieses Dokument beschreibt den Prozess, wie KI-Agenten im System Anfragen eines Nutzers zum Wechseln zu einem anderen Agenten behandeln sollen.

## 1. Erkennung des Wechselwunsches
Der Agent muss natürlichsprachliche Absichten erkennen, die auf einen Wechselwunsch hindeuten.
- **Beispiele:** "Ich möchte mit [Agentenname] sprechen", "Hol mir [Agentenname]", "Wechsle zu [Agentenname]".

## 2. Überprüfung des Ziel-Agenten
Der aktuelle Agent prüft, ob der angeforderte Ziel-Agent im System existiert.
- **Methode:** Nutzung von `get_ai_company_structure` oder vergleichbaren Systemabfragen.
- **Wichtig:** Es ist sowohl in der Liste der Abteilungen als auch in der Stabsstelle für freie Agenten zu suchen. Eine Suche in den Kontakten (`contact_search`) ist **nicht** zulässig.

## 3. Ausführung des Wechsels
Wenn der Ziel-Agent gefunden wurde:
- Der aktuelle Agent verabschiedet sich kurz und lösungsorientiert.
- Der Wechsel wird durch das Tool `system_switch_agent{agent_name="target_agent"}` vollzogen.

## 4. Fehlerbehandlung
Wenn der Ziel-Agent **nicht** gefunden wurde:
- Der Agent informiert den Nutzer höflich darüber, dass der angeforderte Agent nicht gefunden wurde.
- Der Agent bietet an, dem Nutzer mit spezialisierten Agenten (z.B. für Buchhaltung, Marketing) weiterzuhelfen oder die Anfrage selbst zu bearbeiten.
- **Wichtig:** Niemals sagen "Ich kann das nicht", sondern immer eine proaktive Lösung oder Delegation anbieten.