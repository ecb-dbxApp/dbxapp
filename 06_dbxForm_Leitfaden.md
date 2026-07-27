# dbxForm {#dbxapp_dbxform}

[Offizielle dbXapp Website](https://dbxapp.de)

`dbxForm` ist die zentrale Eingabe- und Formularpipeline von dbXapp. Sie
verbindet Template, DD/FD, Request-Auswertung, Validierung, Meldungen,
Formularzustand, AJAX und Speichern.

## Einordnung im Golden Path

Das vollständig zusammengesetzte Formbeispiel mit DD, FD, Template, Ajax und
Service steht unter @ref dbxapp_module_reference. Dieses Kapitel erläutert die
erweiterten Fähigkeiten und Varianten von `dbxForm`.

`dbxForm` ist bewusst eine zustandsbehaftete Fassade. Feldquelle, Requestwerte,
Fehler, Submit-Schutz, Meldungen und Rendering gehören zu einem Formularlauf.
Eine Aufteilung allein nach Dateigröße würde diesen Vertrag auf mehrere
öffentliche Teilobjekte verteilen und die Modulnutzung komplizierter machen.

## Wann dbxForm verwendet wird

- Datensätze anlegen und bearbeiten.
- Filter- und Konfigurationsmasken.
- Login-, Kontakt- und Checkoutformulare.
- Admin-Panels mit `{obj:*}`-Teilbereichen.
- Formulare, die Reports oder andere Module einbetten.
- Mehrstufige Formulare mit Remember-/Workflow-State.

Ein Formular sollte nicht als großer PHP-HTML-String gebaut werden. PHP
bereitet Zustand und Fachlogik vor; das Template bestimmt die Anordnung.

## Lebenszyklus

```text
init(fid, template)
  -> DD, FD, Daten und Action setzen
  -> Felder und Objekte hinzufügen
  -> submit() auswerten
  -> errors() prüfen
  -> Fachlogik / save_post()
  -> run() rendert Template, Meldungen, Felder und JavaScript
```

`submit()` und `errors()` lösen die zentrale Request-Auswertung aus. Felder
müssen deshalb vor der Submit-Prüfung angelegt sein.

## Reales CRUD-Formular

Dieses Muster entspricht dem vom Modul-Wizard erzeugten Code und dem aktuellen
Workflow-Adminformular:

```php
public function edit($rid = null) {
    $rid = $rid === null
        ? (int)dbx()->get_modul_var('rid', 0, 'int')
        : (int)$rid;

    $dd = 'myTasks|myTask';
    $data = $rid > 0
        ? dbx()->get_system_obj('dbxDB')->select1($dd, $rid)
        : array('status' => 'open', 'active' => 1);

    $form = dbx()->get_system_obj('dbxForm');
    $form->init('my-task-form', 'my-task-form');
    $form->_dd = $dd;
    $form->_fd = 'myTasks|myTask-form';
    $form->_data = is_array($data) ? $data : array();
    $form->_rid = $rid;
    $form->_action = '?dbx_modul=myTasks&dbx_run1=edit&rid=' .
        ($rid > 0 ? $rid : 'new');

    $form->add_rep('bar_title', $rid > 0
        ? 'Aufgabe bearbeiten'
        : 'Neue Aufgabe');
    $form->add_rep('bar_subtitle', 'Aufgaben und Status');
    $form->add_obj('bar_actions', 'obj-value',
        '<button class="btn btn-primary btn-sm" type="submit">' .
        '<i class="bi bi-save"></i> Speichern</button>'
    );

    $form->add_flds();

    if ($form->submit() && !$form->errors()) {
        $ok = $form->save_post($dd, $rid > 0 ? $rid : 'new');
        $form->_msg_success = $ok ? 'Aufgabe gespeichert.' : '';
        $form->_msg_error = $ok ? '' : 'Aufgabe konnte nicht gespeichert werden.';
    }

    return $form->run();
}
```

Wichtige Punkte:

- `get_system_obj('dbxForm')` liefert eine von dbXapp verwaltete Instanz.
- `init()` setzt den Formularzustand zurück und erzeugt den Submit-Schutz.
- `_data` enthält den Ausgangsdatensatz; Request-Werte erhalten beim Submit
  Vorrang.
- `add_flds()` muss vor `submit()` laufen.
- `save_post()` speichert nur die durch die Feldpipeline geprüften Werte.
- `dbxDB` setzt `create_date`, `create_uid`, `owner`, `update_date` und
  `update_uid` automatisch, sofern diese Felder in der DD vorhanden sind.
- `run()` wird auch nach Fehlern ausgeführt, damit Werte und Feldmeldungen
  erhalten bleiben.

Ein reales Beispiel steht in
`dbx/modules/dbxWorkflow_admin/include/dbxWorkflowAdmin.class.php` in der
Methode für Workflow-Definitionen.

## Passendes Template

```html
<div id="dbxForm_{i}" class="dbx-panel dbxForm_wrapper dbx-ajax-root">
 [tpl=dbx|module-bar]
 <form action="{action}" method="post" class="dbxAjax"
       data-target="dbxForm_{i}">
  <div class="dbx-panel-body">
   <div class="mb-3">{obj:form_msg}</div>
   <div class="row g-3">
    [dbx:form]
   </div>
   {obj:extra}
  </div>
 </form>
 [dbx:js]
</div>
```

| Marker | Aufgabe |
| --- | --- |
| `{i}` | Eindeutige Instanznummer für mehrfach verwendete Formulare |
| `{action}` | Wert aus `_action` |
| `[dbx:form]` | Alle angelegten Formularfelder |
| `{obj:form_msg}` | Standardmeldungen der Formpipeline |
| `{obj:extra}` | Beliebiges mit `add_obj()` gesetztes Teilobjekt |
| `[dbx:js]` | Von der Pipeline registrierte Initialisierung |

## DD, FD und manuelle Felder

### Alle Felder aus der FD

```php
$form->_dd = 'myTasks|myTask';
$form->_fd = 'myTasks|myTask-form';
$form->add_flds();              // Standardquelle fd::
```

Die aktive Sprache bestimmt automatisch die Datei: `task-form_en.fd.php`,
`task-form_es.fd.php`, `task-form_de.fd.php` oder als Fallback
`task-form.fd.php`. Die FD liefert neben `$fields` auch ihre Meldungen:

```php
$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success']; // Legacy-Alias
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';
```

Nach `save_post()` zeigt dbxForm bei Erfolg `save_success`; schlägt
`dbxDB->save()` fehl, wird `save_error` als allgemeiner Formularfehler
verwendet. Modulcode muss diese beiden Standardmeldungen nicht nochmals setzen.
Eigene fachliche Meldungen werden in derselben FD sprachabhängig ergänzt und
nach dem Laden der Felder über `get_fd_message()` gelesen:

```php
$messages['empty_result'] = 'Keine passenden Datensätze gefunden';

$form->add_flds();
$form->_msg_info = $form->get_fd_message('empty_result');
```

Werden Titel oder Meldungen bereits vor `add_fld()`/`add_flds()` benötigt,
lädt `load_fd_messages()` nur den Meldungsvertrag, ohne ein Feld anzulegen:

```php
$form->_fd = 'myTasks|myTask-form';
$form->load_fd_messages();
$form->add_module_bar(
    $form->get_fd_message('bar_title'),
    'bi-list-check',
    $form->get_fd_message('bar_subtitle')
);
```

Dynamische Werte bleiben ebenfalls in der FD. `format_fd_message()` ersetzt
benannte Platzhalter zentral; das Modul benötigt kein eigenes `str_replace()`:

```php
// FD: $messages['delete_question'] = 'Datensatz #{id} löschen?';
$question = $form->format_fd_message(
    'delete_question',
    array('id' => $rid)
);
```

Damit bleiben auch fachliche Hinweise im Sprachvertrag der Formulardefinition.
Der optionale zweite Parameter von `get_fd_message()` ist ausschließlich ein
Fallback für ältere FDs. Alle Sprachvarianten einer FD müssen dieselben
Meldungsschlüssel besitzen; nur die Texte unterscheiden sich.

### Verbindliche Quelle für sichtbare Formulartexte

Labels, Optionen, Platzhalter, Bar-Titel, Validierungs-, Erfolgs-, Fehler- und
Confirmtexte einer Fachmaske kommen aus ihrer aktiven FD. Modulcode enthält
dafür keine parallelen deutschen Konstanten. Die Reihenfolge ist verbindlich:

```php
$form = dbx()->get_system_obj('dbxForm');
$form->init('task-form', 'myTasks|task-form');
$form->_dd = 'myTasks|myTask';
$form->_fd = 'myTasks|task-form';
$form->load_fd_messages();

$form->_msg_info = $form->get_fd_message('form_info');
$form->add_rep('bar_title', $form->get_fd_message('bar_title'));
$form->add_flds();
```

Benötigt ein Service nur Texte für eine Seite, ein Dialogfenster oder einen
zweiten Report, wird ein leichter FD-Kontext ohne `init()` verwendet:

```php
$texts = new dbxForm();
$texts->set_form_help_enabled(false);
$texts->_fd = 'myTasks|task-form';
$texts->load_fd_messages();

$title = $texts->get_fd_message('bar_title');
```

`init()` gehört zum tatsächlichen Formularlauf. Ein reiner Textkontext ruft es
nicht auf, weil sonst unnötig Formularzustand, Hilfelogik und Instanzmetadaten
entstehen. Der Kontext darf pro Service und FD zwischengespeichert werden.

Sprachdateien übersetzen die Oberfläche, nicht automatisch gespeicherte
Fachdaten. Einsprachige Artikel-, Statusbeschreibungs- oder Konfigurationswerte
bleiben Datenbankinhalt, bis das Datenmodell echte Sprachtabellen vorsieht.
Eine DD wird allein für übersetzte Labels niemals dupliziert.

### Alle Felder direkt aus der DD

```php
$form->_dd = 'myTasks|myTask';
$form->add_flds('dd::');
```

### Nur ausgewählte Felder

```php
$form->add_fld('title');
$form->add_fld('status');
$form->add_fld('description');
```

### FD übernehmen und gezielt überschreiben

```php
$form->add_fld(
    'status',
    tpl: 'select-single-label',
    label: 'Bearbeitungsstatus',
    rules: 'parameter|max=24',
    options: array(
        'open' => 'Offen',
        'working' => 'In Arbeit',
        'done' => 'Erledigt',
    )
);
```

Die Marker bedeuten:

- `fd::`: Wert aus der aktiven FD; ohne FD fällt die Quelle auf DD zurück.
- `dd::`: Wert ausdrücklich aus der DD.
- Konkreter Wert: diese Eigenschaft bewusst überschreiben.

Beispiel: eigenes Template, aber DD-Label und sonst FD-Werte:

```php
$form->add_fld('title', tpl: 'text-label', label: 'dd::');
```

Gängige Templates sind `text-label`, `textarea-label`, `checkbox-label`,
`select-single-label`, `select-multible-label`/`multiselect2`, `date-label`,
`integer-label`, `password-label` und `hidden`. Vorhandene Module und die
Templateauswahl im Editor sind die verbindliche Quelle für tatsächlich
installierte Varianten.

## Werte lesen und zusätzlich validieren

```php
$title = $form->get_post_data('title', '', '*|min=2|max=160');
$status = $form->get_post('status', 'open', 'parameter|max=24');
$active = $form->get_post('active', 0, 'int');
```

- `get_post()` liest Request-Werte und nutzt standardmäßig striktere
  alphanumerische Regeln.
- `get_post_data()` ist für freiere Inhalte gedacht und verwendet standardmäßig
  `parameter`.
- `get_fld_val()` bezieht zusätzlich Formularzustand und `_data` ein.

Fachliche Fehler werden am Feld und in der Formularmeldung angezeigt:

```php
if ($status === 'done' && trim($title) === '') {
    $form->add_fld_error('title', 'Erledigte Aufgaben benötigen einen Titel.');
    $form->_msg_error = 'Bitte Eingaben prüfen.';
}
```

Für Pflicht-E-Mailfelder wird die vollständige Regel verwendet:

```php
$form->add_fld('email', 'text-label', 'E-Mail', 'email|min=1|max=254');
```

`email` prüft genau ein `@`, lokale und gesamte Längen, Punkte im Local-Part,
Domainlabels, vollständige TLD, optionale IDN-Normalisierung und abschließend
`FILTER_VALIDATE_EMAIL`. Ein Pflichtfeld bleibt zusätzlich durch `min=1`
gekennzeichnet und wird bei leerem Submit als Feldfehler gerendert.

Automatische DD-Systemfelder werden nicht über das Formular gesetzt:

```php
$ok = $form->save_post($form->_dd, $rid ?: 'new');
```

`dbxDB` übernimmt Benutzer, Owner und Zeitstempel. `set_post()` oder der dritte
Parameter von `save_post()` sind nur für echte fachliche Werte gedacht, die
das Modul bewusst fest vorgibt und die in der DD existieren. Sie sind kein
Ersatz für die Systemfeldautomatik.

## Speichermöglichkeiten

### Standard: `save_post()`

Für ein normales DD-Formular:

```php
if ($form->submit() && !$form->errors()) {
    $ok = $form->save_post($form->_dd, $rid ?: 'new');
}
```

Nach einem Insert übernimmt dbxForm die neue ID und kann den Datensatz erneut
lesen. Feldwerte und Formularzustand sind danach synchron.

### Eigene Fachtransaktion

Wenn mehrere Tabellen, externe Provider oder komplexe Regeln beteiligt sind,
liest das Modul validierte Werte aus dbxForm und delegiert an einen Service:

```php
if ($form->submit() && !$form->errors()) {
    $result = $service->saveOrder(array(
        'customer_name' => $form->get_post_data('customer_name', '', '*|min=2|max=160'),
        'email' => $form->get_post('email', '', 'email|max=255'),
        'payment' => $form->get_post('payment', '', 'parameter|max=40'),
    ));

    $form->_msg_success = !empty($result['ok']) ? 'Bestellung gespeichert.' : '';
    $form->_msg_error = !empty($result['ok']) ? '' : (string)($result['message'] ?? 'Fehler');
}
```

dbxForm bleibt für Request, Felder und Fehler zuständig; der Service übernimmt
die fachliche Operation. Dadurch wird `save_post()` nicht zu einer vermeintlich
universellen Transaktionslogik überladen.

## Replaces und Objekte

`add_rep()` setzt einfache Templatewerte:

```php
$form->add_rep('headline', 'Aufgabe #' . (int)$rid);
```

```html
<h2>{headline}</h2>
```

`replaces()` wendet die gesammelten Werte auch auf einen bereits geladenen
Teilinhalt an:

```php
$form->add_rep('total', '47,30 EUR');
$content = $form->replaces($content);
```

Alternativ kann ein explizites Array übergeben werden. `dbxReport` erbt beide
Funktionen. Dadurch können während eines Record-Callbacks spät gesetzte Summen
im anschließenden Footer ersetzt werden, ohne dass ein Modul eine eigene
`str_replace()`-Methode benötigt.

`add_obj()` setzt gerenderte Teilbereiche:

```php
$form->add_obj('history', 'obj-value', $this->historyReport($rid));
$form->add_obj('safe_note', 'obv-value', $untrustedPlainText);
```

- `obj-value` übernimmt bewusst bereits gerendertes HTML.
- `obv-value` escaped den Wert für eine sichere Textausgabe.
- Bei einem Template-Namen rendert `add_obj()` das Teiltemplate über dbxTPL.

## Standard-Shell und Modulbar

Für einheitliche Adminformulare kann dbxForm die vorhandenen Shell-Marker
vorbereiten:

```php
$form->add_module_bar(
    'Aufgabe bearbeiten',
    'bi-check2-square',
    'Titel, Status und Beschreibung'
);
$form->add_module_bar_form_actions(array(
    'save' => true,
    'delete' => $rid > 0,
    'delete_url' => '?dbx_modul=myTasks&dbx_run1=delete&rid=' . $rid,
));
$form->prepare_form_shell(array('class' => 'my-task-form'));
```

Diese Variante passt zu Templates mit `form-shell-head` und
`form-shell-foot`. Ein eigenes Modultemplate ist sinnvoll, wenn die Anordnung
fachlich stark abweicht.

Die Formularhilfe wird in `buildModuleBarObj()` aufgelöst. Das Hilfe-Symbol
steht über `bar_extra` immer ganz rechts. `dbxAdminHelp::formButton()` liefert
auch für Formulare ohne fest registriertes Thema eine robuste Fallback-Hilfe.
Eingebettete Steuerformulare, die bereits die Hilfe ihres Elternbereichs
anzeigen, schalten die automatische Schaltfläche gezielt ab:

```php
$form->set_form_help_enabled(false);
```

Jedes Formtemplate muss `{obj:form_msg}` oder den passenden
`#form_msg_error#`-/Erfolgsplatzhalter an der vorgesehenen Stelle besitzen.
Unersetzte Marker dürfen niemals als sichtbarer Text ausgegeben werden.

## Fehlversuche und Rücksetzung

`check_try_count()` begrenzt wiederholte fehlerhafte Submits. Neben der
kurzzeitigen Sperre existiert `_try_count_reset` mit aktuell 600 Sekunden. Ist
der letzte Fehlversuch länger her, beginnen `dbx_try_count` und die Sperrstufe
wieder bei null. Die Sperrmeldung gehört in das betroffene Formular und nicht
als freistehende Meldung in den umgebenden Seitencontent.

## Callbacks

Callbacks erlauben kleine, definierte Eingriffe, ohne die Pipeline zu kopieren:

```php
$form->init('task-form', 'myTasks|task-form');
```

Der direkte Aufrufer von `init()` wird automatisch als Callback-Owner
übernommen. Die normalisierte Formular-ID liefert die Defaultnamen:

```php
public function task_form_init($form, $value) {
    return $value;
}

public function task_form_submit($form, $submit) {
    return $submit;
}

public function task_form_run($form, $content) {
    return $content;
}
```

`set_form_callback_owner()` und die `set_*_callback()`-Methoden bleiben für
bewusste Abweichungen kompatibel, sind im normalen Modulablauf aber nicht
notwendig.

Der Rückgabewert muss zum jeweiligen Callback passen. Fachlogik sollte nicht
verdeckt in Render-Callbacks wandern; sie bleibt im Service oder Controller.

## Formular mit eingebettetem Report

```php
$form->add_obj('messages', 'obj-value', $this->messageReport($rid));
```

```html
<form action="{action}" method="post" class="dbxAjax"
      data-target="dbxForm_{i}">
 [dbx:form]
</form>
<section class="mt-3">{obj:messages}</section>
```

Formular- und Report-Target müssen eindeutig sein. Eine Aktion des Reports
darf nicht versehentlich das gesamte übergeordnete Formular ersetzen.

## AJAX und normale Requests

```html
<form action="{action}" method="post" class="dbxAjax"
      data-target="dbxForm_{i}" data-replace="target">
```

Der Server liefert im üblichen dbxForm-Ablauf wieder HTML für dasselbe Target.
Ohne `dbxAjax` funktioniert das Formular als normaler Request. Reine JSON-
Endpunkte sind separate API-Aktionen und geben keine Formularansicht zurück.

## Submit-Schutz und Action-Token

`dbxForm::init()` erzeugt den versteckten, formularspezifischen Submit-Schutz.
`submit()` vergleicht ihn zeitkonstant und rotiert ihn nach einem gültigen
POST. Das gilt identisch für Ajax und den normalen Browser-Submit.

Eine normale Form-Route erhält **keinen** zusätzlichen `dbx_token`.
`dbx_token` gehört zu automatisch erkannter, zustandsändernder
Link-Navigation. `dbxForm` führt seine Action durch denselben Resolver:
Enthält die Action ausdrücklich `delete` oder `save` als Aktionsbestandteil
und zugleich `rid`, wird sie automatisch signiert; alle anderen Form-Actions
bleiben unverändert. Der Formular-POST behält unabhängig davon seinen eigenen
Submit-Schutz. Modulcode fügt keinen Token manuell hinzu.

## Sicherheits- und Qualitätsregeln

1. Felder vor `submit()` anlegen.
2. DD/FD und Validator-Regeln verwenden; Request-Daten nie ungeprüft speichern.
3. `save_post()` für Standard-CRUD, Service-Methoden für komplexe Fachlogik.
4. Automatische Systemfelder von `dbxDB` setzen lassen.
5. HTML-Struktur in Templates; nur kleine Aktionsfragmente bewusst als Objekt.
6. Eindeutige `{i}`-Targets für mehrere Instanzen und AJAX.
7. Fehlermeldungen über `add_fld_error()` und `_msg_error` führen.
8. Geheimnisse und Passwörter weder zurückrendern noch protokollieren.
9. `obj-value` nur für kontrolliertes, bereits gerendertes HTML verwenden.
10. Pflicht-E-Mailfelder mit `email|min=1` validieren.
11. Hilfe rechts in der Modulbar und Formularmeldungen im Formtemplate prüfen.
12. Normale Formular-Actions nicht manuell mit `dbx_token` doppelt absichern.

## Reale Referenzen

- `dbx/modules/dbxWorkflow_admin/include/dbxWorkflowAdmin.class.php`:
  Formular mit FD, eigenen Feldern und fachlicher JSON-Aufbereitung.
- `dbx/modules/dbxAdmin/include/dbxWizard.class.php`: generiertes CRUD-Muster
  mit `save_post()`, Callbacks und Aktionen.
- `dbx/modules/dbxUser/include/dbxUser_profil.class.php`: gegliedertes
  Profilformular mit Shell und Teilobjekten.
- `dbx/modules/dbxShop`: Checkout- und Adminformulare mit Fachservice.
- @ref dbxapp_module_reference — verbindliches Gesamtbeispiel.
- @ref dbxapp_dbxdb_dd_fd — DD-/FD- und Datenreferenz.
- @ref dbxapp_javascript_libs — Ajax- und Confirm-Vertrag.
