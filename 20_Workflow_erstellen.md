# Workflow erstellen {#dbxapp_workflow_create}

Diese Seite beschreibt, wie ein neuer dbXapp-Workflow geplant, im visuellen
Prozess-Designer aufgebaut, geprüft und mit einem Fachmodul verbunden wird.
Die technische Gesamtarchitektur und das vollständige JSON-Schema stehen im
@ref dbxapp_workflow_guide.

## Wann ein Workflow sinnvoll ist

Ein Workflow ist richtig, wenn ein fachliches Ziel mehrere nachvollziehbare
Schritte benötigt, zum Beispiel:

- ein Angebot aus Kundendaten, Leistungen und Freigabe zusammensetzen;
- eine Kontaktanfrage prüfen, beantworten und abschließen;
- einen Shopartikel vorbereiten, Channel-Daten prüfen und veröffentlichen;
- eine Entscheidung treffen und abhängig vom Ergebnis unterschiedlich
  fortfahren;
- Daten automatisch prüfen, bevor ein Benutzer eine externe Aktion bestätigt.

Ein Workflow ist kein Ersatz für ein umfangreiches Fachformular. Bestehende
Artikel-, Kunden-, CRM- oder ERP-Formulare werden als Modulschritt geöffnet und
nicht im Workflow nachgebaut.

## Designer öffnen

Die Workflow-Verwaltung ist im Admin-Menü unter **Workflow** erreichbar.

```text
?dbx_modul=dbxWorkflow_admin
```

Eine neue Definition wird hier angelegt:

```text
?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new
```

Zum Bearbeiten einer vorhandenen Definition wird deren Datensatz-ID verwendet:

```text
?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=4
```

Die Bearbeitung ist auf Administratoren begrenzt. `dbxRunAsAdmin` ist kein
normaler Anmeldemechanismus und darf nur vorübergehend in einem geschützten
Testsystem verwendet werden.

## Kopfdaten festlegen

| Feld | Zweck | Beispiel |
| --- | --- | --- |
| Workflow Key | stabiler technischer Schlüssel | `project_offer` |
| Name | sichtbarer Titel des Ablaufs | `Angebot vorbereiten` |
| Ziel | Ergebnis, das am Ende erreicht sein soll | `Versandfertiges Angebot` |
| Beschreibung | kurze fachliche Erklärung | `Kunde, Leistungen und Freigabe prüfen.` |
| Aktiv | steuert, ob neue Instanzen gestartet werden dürfen | aktiviert |

Der Workflow Key wird in URLs, Instanzen und Bindings gespeichert. Er sollte
kleingeschrieben sein, keine Leerzeichen enthalten und nach dem produktiven
Einsatz nicht mehr umbenannt werden.

Nur Definitionen aus `dbxWorkflow_admin` werden ausgeführt. Ein unbekannter
oder deaktivierter Key fällt nicht auf einen Demo-Workflow zurück. Die
mitgelieferten Beispiele werden bei der Installation nur ergänzt, wenn ihr Key
noch fehlt; spätere Admin-Anpassungen werden nicht automatisch überschrieben.

## Prozess mit Bausteinen aufbauen

Der visuelle Designer besitzt links eine Palette und rechts den Ablauf von
**Start** bis **Ziel**. Ein Baustein kann angeklickt oder in den Ablauf gezogen
werden. Bestehende Bausteine werden am Griff verschoben. Die Verbindungslinien
werden nach jeder Änderung neu gezeichnet.

| Baustein | Verwendung |
| --- | --- |
| Eingabe | einen Wert erfassen oder aus einer Liste auswählen |
| Aktion | ein Fachformular öffnen oder eine bewusst bestätigte Aktion ausführen |
| Prüfung | Daten oder einen Providerstatus kontrollieren |
| Entscheidung | einen Ergebniswert erzeugen, von dem spätere Zweige abhängen |

Die Details eines Bausteins werden durch Anklicken seiner Kopfzeile geöffnet.
Für eine übersichtliche Reihenfolge können die Detailbereiche vor dem Ziehen
geschlossen werden.

## Eigenschaften eines Schritts

| Feld | Bedeutung |
| --- | --- |
| Baustein | `input`, `action`, `check` oder `decision` |
| Bezeichnung | verständlicher Name für Benutzer |
| Interner Schlüssel | eindeutiger Key innerhalb des Workflows |
| Ergebnis / Status danach | sichtbares Ereignis nach erfolgreicher Bearbeitung |
| Erlaubte Bearbeitungen | eine oder mehrere Möglichkeiten: Formular, Auswahl, Neuanlage oder Modulformular |
| Standardbearbeitung | bevorzugter Bearbeitungsweg aus den erlaubten Möglichkeiten |
| Automatisierung | manuell oder automatisch prüfen |
| Werte | einzelner Wert oder mehrere Werte |
| Pflicht | notwendig oder optional |
| Voraussetzung / Linie von | Schlüssel eines früheren Schritts |
| Nur bei Ergebnis | erwarteter Wert der Voraussetzung |
| Auswahl-/Entscheidungsergebnisse | erlaubte Werte und Beschriftungen |
| Bestätigung nach Modulformular | Text für die bewusste Bestätigung eines erledigten Modulschritts |
| Hinweis | konkrete Handlungsanweisung für den Bearbeiter |

Ein guter interner Schlüssel beschreibt den fachlichen Inhalt, nicht die
Position. `approval_result` bleibt verständlich, auch wenn der Schritt später
verschoben wird; `step_4` nicht.

## Auswahlen und Entscheidungen

Optionen werden zeilenweise eingetragen. Ein sichtbares Label kann von seinem
gespeicherten Wert getrennt werden:

```text
approved=Freigegeben
changes=Änderung notwendig
rejected=Abgelehnt
```

Bei einfachen Listen reicht auch ein Wert pro Zeile:

```text
Standard
Express
Abholung
```

Eine Entscheidung benötigt mindestens zwei Ergebnisse. Der gespeicherte Wert
wird in `depends_value` späterer Schritte verwendet.

## Verzweigungen aufbauen

Ein abhängiger Schritt verweist immer auf einen bereits davor liegenden
Schritt. Das Ergebnis bestimmt, ob der Zweig anwendbar ist.

```text
Freigabe prüfen (key: approval)
  ├─ approved -> Auftrag ausführen
  └─ changes  -> Rückfrage bearbeiten
```

Konfiguration für den ersten Zweig:

```json
{
  "key": "execute_order",
  "kind": "action",
  "label": "Auftrag ausführen",
  "depends_on": "approval",
  "depends_value": "approved"
}
```

Konfiguration für den zweiten Zweig:

```json
{
  "key": "clarification",
  "kind": "input",
  "label": "Rückfrage bearbeiten",
  "depends_on": "approval",
  "depends_value": "changes"
}
```

Solange die Entscheidung noch nicht getroffen ist, bleiben beide Zweige in
der Ablaufanzeige sichtbar, aber gesperrt. Danach zählt nur der gewählte Zweig
zum anwendbaren Ablauf.

## Automatische Prüfungen

Für eine Prüfung kann **Automatisch prüfen, sonst manuell** gewählt werden.
Technisch wird dabei `automation = observe` gespeichert.

```json
{
  "key": "readiness_check",
  "kind": "check",
  "automation": "observe",
  "label": "Bereitschaft automatisch prüfen",
  "depends_on": "product",
  "required": true
}
```

Die Engine automatisiert einen Schritt nur, wenn das Workflow-Fachmodul dafür
einen Wert liefern kann. Ohne passende Automatik bleibt der Schritt sichtbar
und kann manuell bearbeitet werden. Damit bleibt eine Definition auch dann
benutzbar, wenn ein externer Dienst zeitweise nicht erreichbar ist.

Eine Automatik darf sichere Beobachtungen und Validierungen übernehmen, etwa:

- Vollständigkeit von Pflichtdaten prüfen;
- vorhandene Channel-Zuordnungen lesen;
- einen bereits gespeicherten Exportstatus auswerten;
- das Ergebnis einer internen Regel bestimmen.

Externe Veröffentlichungen, Mailversand, Zahlungen oder andere schwer
rückgängig zu machende Aktionen bleiben sichtbare und bewusst bestätigte
Aktionen. Eine automatische Vorprüfung darf diese Bestätigung nicht umgehen.

## Bearbeitungsarten wählen

### Formular

`form` erfasst einen Wert direkt im Workflow. Das eignet sich für kurze Texte,
Bestätigungen oder durch ein Binding vorbelegte Werte.

### Auswahl

`select` zeigt statische Optionen oder über ein Binding geladene Datensätze.
Mit `mode = multiple` können mehrere Werte gespeichert werden.

### Neuanlage

`create` bietet eine einfache Neuanlage innerhalb des Schritts. Umfangreiche
Fachobjekte sollten stattdessen im zuständigen Modul gepflegt werden.

### Modulformular

`module` öffnet eine vorhandene Fachoberfläche, zum Beispiel Artikelpflege,
eBay-Mapping oder CRM-Datensatz. Nach dem Speichern bestätigt der Benutzer den
Workflow-Schritt. Links werden in `module_links` abgelegt.

## Modul-Binding ergänzen

Ein Binding verbindet die allgemeine Workflow-Engine mit einer Fach-DD und
optional mit Kontext, Vorbelegung und Finish-Logik.

```text
?dbx_modul=dbxWorkflow_admin&dbx_run1=binds
?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new
```

Referenz in der Definition:

```json
"bind_ref": "dbxContact|contact_reply"
```

Typische Binding-Aufgaben:

- Datensätze mit `dd_select` als Optionen laden;
- DD-Feldoptionen mit `dd_field_options` verwenden;
- einen vorhandenen Feldwert mit `dd_field_value` vorbelegen;
- statische Optionen mit `static_select` definieren;
- beim Finish genau den vorgesehenen Datensatz aktualisieren;
- optional nach erfolgreichem Abschluss eine Nachricht versenden.

Das vollständige Binding-Schema und ein reales Kontakt-Beispiel stehen in
@ref dbxapp_workflow_guide.

## Validierung im Designer

Der Designer prüft Änderungen sofort. Beim Speichern wird dieselbe Struktur
zusätzlich serverseitig kontrolliert. Gespeichert wird nur ein schlüssiger
Ablauf.

Geprüft werden insbesondere:

- mindestens ein aktiver Schritt;
- Bezeichnung und interner Schlüssel jedes Schritts;
- eindeutige Schlüssel;
- mindestens zwei Ergebnisse für Entscheidungen;
- vorhandene Abhängigkeiten;
- Abhängigkeiten nur auf frühere Schritte;
- ein verständliches Ziel und Abschlusslabel.

Die clientseitige Anzeige verbessert die Bedienung. Verbindlich bleibt die
serverseitige Prüfung, weil Requests auch ohne Browser-JavaScript eintreffen
können.

## Round-Trip-Sicherheit

Der Designer aktualisiert beim Speichern die bereits geladene und normalisierte
Definition. Er baut nicht mehr nur aus den sichtbaren Feldern ein neues JSON
auf. Dadurch bleiben insbesondere erhalten:

- zusätzliche Top-Level-Felder und Schema-Metadaten;
- nicht im Designer dargestellte Need-Eigenschaften;
- Erweiterungen in `resolver`, `source`, `bind` und `module_links`;
- zusätzliche Eigenschaften des `finish`-Blocks;
- Erweiterungen abgeleiteter Checks mit demselben `key`;
- mehrere erlaubte `actions` einschließlich ihrer bestehenden Reihenfolge.

Die bekannten Designerfelder werden weiterhin bewusst überschrieben. Ein
entfernter Schritt, eine geleerte optionale Eigenschaft oder ein gelöschtes
`bind_ref` soll deshalb auch aus der gespeicherten Definition verschwinden.
Beim Umbenennen eines Need-Keys werden zugehörige Check-Erweiterungen
mitgeführt; der Browser aktualisiert außerdem abhängige Schritte auf den neuen
Key.

Die technischen Check-Felder bleiben aus den Needs abgeleitet. Eigene
Erweiterungsfelder eines Checks werden erhalten, während `label`, `question`,
`validation`, `required`, `missing_message` und der Bearbeitungsweg erneut aus
dem zugehörigen Need erzeugt werden.

## Änderungen und laufende Instanzen

Beim Start speichert `dbxWorkflow` die normalisierte Definition als Snapshot in
der Instanz. Eine spätere Änderung im Designer gilt daher für neue Starts und
ändert einen bereits begonnenen Durchlauf nicht rückwirkend. Zum Testen einer
Korrektur muss eine neue Instanz gestartet werden.

## Vollständiges Beispiel

```json
{
  "workflow_key": "project_offer",
  "title": "Angebot vorbereiten",
  "result": "Freigegebenes Angebot",
  "description": "Kunde, Leistungen und Freigabe werden kontrolliert.",
  "needs": [
    {
      "key": "customer",
      "kind": "input",
      "label": "Kunde auswählen",
      "mode": "single",
      "required": true,
      "actions": ["select", "create"],
      "preferred": "select",
      "event": "Kunde ausgewählt"
    },
    {
      "key": "services",
      "kind": "input",
      "label": "Leistungen festlegen",
      "mode": "multiple",
      "required": true,
      "actions": ["select"],
      "options": ["Beratung", "Einrichtung", "Schulung"],
      "depends_on": "customer",
      "event": "Leistungen vollständig"
    },
    {
      "key": "approval",
      "kind": "decision",
      "label": "Angebot freigeben",
      "required": true,
      "actions": ["select"],
      "options": [
        {"value": "approved", "label": "Freigegeben"},
        {"value": "changes", "label": "Änderung notwendig"}
      ],
      "depends_on": "services",
      "event": "Freigabe entschieden"
    },
    {
      "key": "send_offer",
      "kind": "action",
      "label": "Angebot versenden",
      "required": true,
      "actions": ["module"],
      "depends_on": "approval",
      "depends_value": "approved",
      "event": "Angebot versendet"
    },
    {
      "key": "change_note",
      "kind": "input",
      "label": "Änderungswunsch erfassen",
      "required": true,
      "actions": ["form"],
      "depends_on": "approval",
      "depends_value": "changes",
      "event": "Änderung beschrieben"
    }
  ],
  "finish": {
    "label": "Angebot abschließen"
  }
}
```

## Vor der Freigabe testen

1. Workflow ohne vorbelegten Datensatz starten.
2. Pflichtfelder und optionale Schritte prüfen.
3. Jede Entscheidung mit allen Ergebnissen durchlaufen.
4. Prüfen, ob nur der gewählte Zweig anwendbar wird.
5. Automatische Prüfungen mit vollständigen und unvollständigen Daten testen.
6. Modulfenster öffnen, speichern und zum Workflow zurückkehren.
7. Pause, Fortsetzen, Abbruch und Neustart testen.
8. Review und Finish mit einem Testdatensatz prüfen.
9. Sicherstellen, dass externe Aktionen nicht doppelt ausgeführt werden.
10. Instanz- und Schritthistorie in der Administration kontrollieren.

## Weiterführende Seiten

- @ref dbxapp_workflow_use
- @ref dbxapp_workflow_guide
- @ref dbxapp_module_patterns
- @ref dbxapp_dbxdb_dd_fd
- @ref dbxapp_dbxform
