# dbxWorkflow {#dbxapp_workflow_guide}

`dbxWorkflow` führt Benutzer schrittweise zu einem fachlichen Ergebnis. Ein
Workflow beschreibt nicht nur eine Reihenfolge von Seiten, sondern ein Ziel,
die dafür notwendigen Eingaben oder Modulaktionen, den Fortschritt und einen
kontrollierten Abschluss.

Für die praktische Arbeit sind zwei eigene Anleitungen verfügbar:

- @ref dbxapp_workflow_create erklärt Planung, visuellen Designer,
  Entscheidungen, automatische Prüfungen und Bindings.
- @ref dbxapp_workflow_use erklärt Start, Bearbeitung, Fortschritt,
  eBay-Beispiel, Review und Abschluss.

Beispiele aus der aktuellen Installation:

- Rechnung vorbereiten.
- Kontaktanfrage bearbeiten und optional per E-Mail beantworten.
- Shopartikel anlegen und veröffentlichen.
- bestehenden Shopartikel für eBay vorbereiten und exportieren.

## Architektur

```text
Workflow-Definition
  -> Start erzeugt Workflow-Instanz mit Definitions-Snapshot
  -> Engine ermittelt nächsten anwendbaren Need
  -> Benutzer wählt, erfasst, erstellt oder öffnet ein Fachmodul
  -> Schritt und Werte werden gespeichert
  -> Review zeigt das geplante Ergebnis
  -> Finish führt optional ein Modul-Binding aus
  -> Instanz ist finished, paused, canceled oder error
```

| Komponente | Aufgabe |
| --- | --- |
| `dbxWorkflow` | Frontendrouter für Übersicht, Start und laufende Instanz |
| `dbxWorkflowEngine` | Definition normalisieren, Instanz führen, Schritte rendern und speichern |
| `dbxWorkflowModule` | Fachmodule über deklarative Bindings anbinden |
| `dbxWorkflowBindRegistry` | Bindings lesen und aus Modul-DDs Grundgerüste erzeugen |
| `dbxWorkflow_admin` | Definitionen, Bindings und Instanzen verwalten |

Dateien:

```text
dbx/modules/dbxWorkflow/
  dbxWorkflow.class.php
  include/dbxWorkflowEngine.class.php
  include/dbxWorkflowModule.class.php
  include/dbxWorkflowBindRegistry.class.php
  dd/workflowDefinition.dd.php
  dd/workflowInstance.dd.php
  dd/workflowStep.dd.php
  dd/workflowModuleBind.dd.php
  tpl/htm/workflow-*.htm

dbx/modules/dbxWorkflow_admin/
  include/dbxWorkflowAdmin.class.php
  tpl/htm/workflow-*.htm
```

## Datenmodell

| DD | Zweck |
| --- | --- |
| `dbxWorkflow|workflowDefinition` | fachliche Definition und JSON-Schema |
| `dbxWorkflow|workflowInstance` | Definitions-Snapshot, laufender Zustand, Werte, Status und Fortschritt |
| `dbxWorkflow|workflowStep` | nachvollziehbare, abgeschlossene Einzelschritte |
| `dbxWorkflow|workflowModuleBind` | optionale Kopplung an DD, Templates, Finish und Mail |

Instanzen und Schritte besitzen `owner`. Erstellen ist für zulässige Benutzer
möglich, Lesen/Ändern erfolgt owner- bzw. adminbezogen. Die Adminoberfläche ist
auf die Gruppe `admin` begrenzt.

## Routen

Frontend:

```text
?dbx_modul=dbxWorkflow
?dbx_modul=dbxWorkflow&dbx_run1=overview&workflow=invoice_demo
?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=invoice_demo
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17
```

Admin:

```text
?dbx_modul=dbxWorkflow_admin
?dbx_modul=dbxWorkflow_admin&dbx_run1=edit&rid=new
?dbx_modul=dbxWorkflow_admin&dbx_run1=instances
?dbx_modul=dbxWorkflow_admin&dbx_run1=binds
?dbx_modul=dbxWorkflow_admin&dbx_run1=edit_bind&rid=new
```

Ein fachlicher Datensatz kann beim Start vorgewählt werden, wenn das Binding
`prefill_rid` erlaubt:

```text
?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=ticket_demo&rid=42
```

## Eine vollständige Definition

Die Definition wird als JSON in `definition_json` gespeichert. Das folgende
Beispiel zeigt Auswahl, Freitext, Mehrfachauswahl, einen optionalen Schritt und
eine Abhängigkeit:

```json
{
  "workflow_key": "project_offer",
  "title": "Angebot vorbereiten",
  "result": "Versandfertiges Angebot",
  "description": "Kunde, Leistungen und Versandweg werden gemeinsam geprüft.",
  "needs": [
    {
      "key": "customer",
      "label": "Kunde",
      "mode": "single",
      "required": true,
      "actions": ["select", "create"],
      "preferred": "select",
      "hint": "Vorhandenen Kunden wählen oder neu anlegen.",
      "options": [
        {"value": "101", "label": "Muster GmbH"},
        {"value": "102", "label": "Beispiel AG"}
      ]
    },
    {
      "key": "services",
      "label": "Leistungen",
      "mode": "multiple",
      "required": true,
      "actions": ["select"],
      "preferred": "select",
      "options": ["Beratung", "Einrichtung", "Schulung"]
    },
    {
      "key": "delivery",
      "label": "Versandweg",
      "mode": "single",
      "required": true,
      "actions": ["select"],
      "options": [
        {"value": "mail", "label": "E-Mail"},
        {"value": "portal", "label": "Kundenportal"}
      ]
    },
    {
      "key": "portal_note",
      "label": "Hinweis im Portal",
      "mode": "single",
      "required": false,
      "actions": ["form"],
      "hint": "Optionaler Hinweis für den Kunden.",
      "depends_on": "delivery",
      "depends_value": "portal"
    }
  ],
  "finish": {
    "label": "Angebot abschließen"
  }
}
```

## Definitionseigenschaften

### Oberste Ebene

| Feld | Bedeutung |
| --- | --- |
| `workflow_key` | eindeutiger technischer Schlüssel |
| `title` | sichtbarer Name des Ablaufs |
| `result` | fachliches Ziel bzw. Ergebnis |
| `description` | kurze Erklärung für den Benutzer |
| `needs` | geordnete Liste notwendiger Schritte |
| `finish` | Label und optionales Abschlussverhalten |
| `bind_ref` | optionale Referenz `modul|bind_key` |

### Need

| Feld | Möglichkeiten |
| --- | --- |
| `key` | eindeutiger, normalisierter Schlüssel |
| `label`, `hint` | Benutzerführung |
| `kind` | `input`, `action`, `check` oder `decision` |
| `automation` | `manual` oder sichere Beobachtung mit `observe` |
| `mode` | `single` oder `multiple` |
| `required` | `true`; bei `false` ist Überspringen möglich |
| `actions` | `select`, `create`, `form`, `module` |
| `preferred` | bevorzugte der erlaubten Aktionen |
| `question`, `validation` | abgeleitete Prüffrage und Vollständigkeitsregel |
| `missing_message`, `resolver` | Fehlermeldung und passender Bearbeitungsweg |
| `options` | Strings oder `{value,label}`-Objekte |
| `event` | sichtbares Ergebnis-/Ereignislabel |
| `depends_on` | vorheriger Need, von dem dieser Schritt abhängt |
| `depends_value` | optional erforderlicher Wert der Abhängigkeit |
| `module_links` | Links zu Fachformularen, meist als openWin |
| `complete_label` | Bestätigungstext nach einem erledigten Modulformular |

### Aktionsarten

- `select`: einen oder mehrere Werte auswählen.
- `create`: einen neuen Wert innerhalb des Workflow-Schritts erfassen.
- `form`: freien bzw. durch Binding vorbelegten Text erfassen.
- `module`: ein vorhandenes Fachformular öffnen, dort speichern und den Schritt
  anschließend bestätigen.

Ein Workflow ersetzt kein gutes Fachformular. `module` ist richtig, wenn ein
Artikel, Kunde oder Mapping bereits eine umfangreiche Pflegeoberfläche besitzt.

## Kurze Need-Syntax

Die Engine kann außer JSON auch zeilenbasierte Definitionen normalisieren:

```text
result=Versandfertiges Angebot
Kunde=single|required|select!|create|hint=Kunde wählen oder anlegen
Leistungen=multiple|required|select!|options=Beratung,Einrichtung,Schulung
Notiz=single|optional|form!
```

`!` markiert die bevorzugte Aktion. JSON ist für Abhängigkeiten, Modul-Links,
strukturierte Optionen und Bindings besser geeignet; die Zeilensyntax ist für
kleine Workflows und schnelle Entwürfe gedacht.

## Definition in PHP normalisieren

Die Engine akzeptiert Array, JSON oder Need-Zeilen:

```php
$engine = dbx()->get_include_obj('dbxWorkflowEngine', 'dbxWorkflow');
$definition = $engine->normalize_definition($jsonOrArray, 'project_offer');

if (count((array)($definition['needs'] ?? array())) === 0) {
    throw new \InvalidArgumentException('Mindestens ein Need ist erforderlich.');
}
```

Die Adminoberfläche verwendet diesen Weg vor dem Speichern und legt die
normalisierte Definition als lesbares JSON ab. Dabei wird die vorhandene
Definition als Basis verwendet: bekannte Designerfelder werden aktualisiert,
unbekannte Schema- und Modulerweiterungen bleiben round-trip-sicher erhalten.

## Instanz und Schritte

Beim Start erzeugt die Engine einen Datensatz:

```php
$record = array(
    'workflow_key' => $definition['workflow_key'],
    'result_label' => $definition['result'],
    'status' => 'running',
    'current_need' => '',
    'percent' => 0,
    'step_percent' => 0,
    'message' => 'Workflow gestartet.',
    'definition_json' => json_encode($definition),
    'data_json' => json_encode($prefillValues),
);

$db = dbx()->get_system_obj('dbxDB');
$iid = ($db->insert('dbxWorkflow|workflowInstance', $record, 0, 1, 1, 1) === 1)
    ? $db->get_insert_id()
    : 0;
```

`dbxDB` ergänzt `owner`, Benutzer- und Zeitfelder automatisch aus der DD.

`definition_json` friert den beim Start gültigen Ablauf für diese Instanz ein.
Änderungen oder Deaktivierungen in `dbxWorkflow_admin` wirken dadurch nur auf
neue Starts. Alte Instanzen aus einer Version ohne Snapshot laden für die
Abwärtskompatibilität weiterhin die aktuelle Definition.

Die Engine schreibt jeden übernommenen Need zusätzlich in `workflowStep`.
`data_json` enthält den aktuellen Gesamtwert, die Step-Tabelle die
nachvollziehbare Historie.

Statuswerte:

- `running`: Bearbeitung möglich.
- `paused`: angehalten, kann fortgesetzt werden.
- `canceled`: abgebrochen.
- `finished`: Review und Finish erfolgreich abgeschlossen.
- `error`: fachlicher oder technischer Fehlerzustand.

Prozessbefehle laufen über denselben Instanz-Endpunkt:

```text
&proc_cmd=pause
&proc_cmd=resume
&proc_cmd=continue
&proc_cmd=cancel
&proc_cmd=restart
```

`process.js` erhält Status, URLs, Prozentwerte und Intervall über die vom
Workflow-Frame erzeugten `data-process-*`-Attribute.

## Fachmodule über Bindings anbinden

Ein Workflow soll ein Fachmodul nicht hart kennen müssen. Er referenziert:

```json
"bind_ref": "dbxContact|contact_reply"
```

Die Binding-Tabelle löst diese Referenz in ein deklaratives `bind_json` auf.
Das Fachmodul bleibt unabhängig; dbxWorkflow kennt DD, Felder, Templates und
Abschlussabbildung aus dem Binding.

### Reales Binding-Muster

```json
{
  "modul": "dbxContact",
  "record": {
    "dd": "dbxContact|contactRequest",
    "id_need": "contact_request",
    "prefill_rid": true
  },
  "context": {
    "tpl": "dbxContact|contact-request-summary",
    "hide_on_need": "contact_request",
    "fields": {
      "rid": "id",
      "subject": "subject",
      "name": "name",
      "email": "email",
      "phone": "phone"
    }
  },
  "needs": {
    "contact_request": {
      "type": "dd_select",
      "where": {"status": "open", "trash": 0},
      "label": "#{id} - {subject} ({name})",
      "fields": ["id", "subject", "name"],
      "order_field": "create_date",
      "order_dir": "DESC"
    },
    "status": {
      "type": "dd_field_options",
      "field": "status"
    },
    "customer_reply": {
      "type": "dd_field_value",
      "field": "reply_text"
    },
    "send_mail": {
      "type": "static_select",
      "options": [
        {"value": "1", "label": "Ja, E-Mail senden"},
        {"value": "0", "label": "Nein, nur speichern"}
      ]
    }
  },
  "finish": {
    "type": "dd_update",
    "map": {
      "status": "status",
      "reply_text": "customer_reply",
      "reply_date": "@now",
      "reply_uid": "@uid"
    }
  }
}
```

### Binding-Quellen

| Typ | Wirkung |
| --- | --- |
| `dd_select` | Optionen aus einer DD mit WHERE, Feldern, Label und Sortierung |
| `dd_field_options` | Optionsliste des angegebenen DD-Feldes |
| `dd_field_value` | vorhandenen Feldwert als Formwert vorbelegen |
| `static_select` | feste `{value,label}`-Optionen |

`show_if_config` kann einen Need nur zeigen, wenn eine Modulkonfiguration eine
Fähigkeit aktiviert hat, z. B. Mailversand.

### Finish-Mapping

Bei `type = dd_update` wird der über `record.id_need` gewählte Datensatz
aktualisiert. Quellen im Mapping:

- Name eines Needs, z. B. `customer_reply`.
- Feld des geladenen Datensatzes.
- `@need:key` für einen ausdrücklichen Need-Wert.
- `@now` für den aktuellen Zeitstempel.
- `@uid` für den aktiven Benutzer.
- ein konstanter Wert.

Ein optionaler `finish.mail`-Block kann Empfängerfeld, Betreff, Bodytemplate,
Variablen und Trackingfelder definieren. Der Versand läuft erst nach
erfolgreicher fachlicher Prüfung und nur, wenn die Modulkonfiguration ihn
erlaubt.

## Binding-Grundgerüst erzeugen

`dbxWorkflowBindRegistry` kann eine Modul-DD untersuchen:

```php
$registry = dbx()->get_include_obj(
    'dbxWorkflowBindRegistry',
    'dbxWorkflow'
);

$record = $registry->generateBindSkeleton(
    'dbxContact',
    'dbxContact|contactRequest',
    'contact_reply'
);
```

Das Ergebnis ist ein Startpunkt, kein blind fertiger Fachprozess. WHERE,
Labels, Pflichtschritte, Finish-Mapping, Mail und Rechte müssen fachlich geprüft
werden.

## Eigenes Modul an Workflow anbinden

1. Fach-DD und Formulare im Modul fertigstellen.
2. Einen eindeutigen `bind_key` wählen.
3. Binding-Grundgerüst erzeugen oder im Workflow-Admin anlegen.
4. Record-DD und `id_need` festlegen.
5. Needs auf DD-Auswahl, Feldoptionen, Feldwerte oder statische Werte abbilden.
6. Optional ein `*-summary.htm`-Template als Kontext bereitstellen.
7. Finish-Mapping mit einem Testdatensatz prüfen.
8. Erst dann `bind_ref` in der Workflow-Definition setzen.
9. Start ohne RID und mit `&rid=...` testen.
10. Pause, Fortsetzen, Abbruch, Neustart und Review testen.

## Wann ein Built-in sinnvoll ist

Die Engine enthält derzeit besondere Shop-Workflows, weil Artikelanlage,
Channel-Mapping, Export und Providerstatus zusätzliche Fachlogik besitzen.
Neue Standardfälle sollten bevorzugt über Definition und Binding entstehen.
Ein neuer hart codierter Engine-Zweig ist nur gerechtfertigt, wenn deklaratives
`dd_update` die fachliche Operation nicht sicher abbilden kann.

Komplexe Abschlüsse gehören dann in einen klaren Fachservice oder Adapter. Die
Workflow-Engine koordiniert und zeigt Status; sie sollte keine zweite Kopie der
Shop-, CRM- oder ERP-Logik enthalten.

## Administration

Die Adminoberfläche bietet:

- Definitionen filtern, neu anlegen und bearbeiten.
- Needs visuell zusammenstellen und normalisieren.
- Modul-Bindings anlegen, generieren und prüfen.
- laufende und abgeschlossene Instanzen mit Fortschritt anzeigen.
- Demo- und Shopdefinitionen bei der Installation bereitstellen.

Definitionen werden mit dbxForm gepflegt, Listen mit dbxReport. DD-Sync läuft
über die vier Workflow-DDs und wird nicht durch manuelle Tabellenänderungen
ersetzt.

## Sicherheits- und Konsistenzregeln

1. Instanzen und Schritte bleiben owner-/adminbezogen.
2. `workflow_key`, `need_key`, Sortierung und Requestbefehle werden validiert.
3. Dynamische Optionen kommen über dbxDB und definierte DD-Felder.
4. Finish prüft Datensatz und Werte erneut; ein fertiger UI-Schritt ist keine
   Berechtigung.
5. Externe Aktionen und Mail erfolgen erst nach erfolgreicher Fachoperation.
6. Ein wiederholter Request darf keine unkontrollierten Doppeloperationen
   erzeugen.
7. Module werden über Bindings angebunden; das Fachmodul kennt den Workflow
   nicht zwingend.
8. Templates rendern die Oberfläche, Engine und Fachservice die Logik.
9. Gastinstanzen sind zusätzlich an die aktive PHP-Session gebunden.
10. Mutierende GET-Aktionen verwenden den vorhandenen Kernel-Action-Token;
    reine Navigation bleibt tokenlos.
11. Ein alter Start-/Command-Link ohne Token bleibt erreichbar, mutiert aber
    nicht und führt auf eine Seite mit frischem Aktionslink.
12. Der Abschluss wird atomar als `finishing` beansprucht; nur der Gewinner
    darf Fachoperation, Mail oder Provideraktion ausführen.

## Prüfliste

- Definition enthält eindeutigen Key, Titel, Ergebnis und mindestens einen Need.
- Pflicht-, optionale und abhängige Needs verhalten sich korrekt.
- Single- und Multiple-Auswahl speichern erwartete Werte.
- Modul-Links öffnen die vorhandene Fachoberfläche und kehren verständlich
  zum Workflow zurück.
- Start mit RID befüllt nur erlaubte Werte vor.
- Review zeigt alle anwendbaren Schritte korrekt.
- Finish aktualisiert genau den vorgesehenen Datensatz.
- Mail/Providerfehler lassen die Instanz nicht fälschlich als erfolgreich
  erscheinen.
- Pause, Resume, Cancel und Restart funktionieren.
- Adminreport zeigt Status, aktuellen Need, Fortschritt und Meldung.

## Verwandte Dokumentation

- @ref dbxapp_security_integrity_performance
- @ref dbxapp_workflow_create
- @ref dbxapp_workflow_use
- @ref dbxapp_module_patterns
- @ref dbxapp_dbxdb_dd_fd
- @ref dbxapp_dbxform
- @ref dbxapp_dbxreport
- @ref dbxapp_shop_guide
