# dbxDB, dbxDD und FD {#dbxapp_dbxdb_dd_fd}

`dbxDB`, DD und FD bilden die Datenbasis von dbXapp. Fachcode arbeitet nicht
direkt mit PDO und kennt im Normalfall auch keinen physischen Tabellennamen.
Er verwendet eine DD-Referenz wie `dbxWorkflow|workflowDefinition`.

## Einordnung im Golden Path

@ref dbxapp_module_reference zeigt dieselbe Datenpipeline in einem vollständigen
Modul. Dieses Kapitel ist die vertiefende Referenz für Abfragen, Schreiben,
DD/FD und Schemaabgleich.

## Zusammenspiel

| Baustein | Verantwortung | Typischer Ort |
| --- | --- | --- |
| `dbxDB` | Lesen, Schreiben, Rechte, Owner-Filter, Trace und DB-Abstraktion | `dbx/include/dbxDB.class.php` |
| `dbxDD` | DD-Modell lesen sowie DD und physische DB synchronisieren | `dbx/include/dbxDD.class.php` |
| DD | Tabelle, Felder, Indizes, Rechte, Defaults, Validierung | `dbx/modules/{modul}/dd/*.dd.php` |
| FD | Formularsicht auf DD-Felder: Reihenfolge, Template, Label, Optionen | `dbx/modules/{modul}/fd/*.fd.php` |

```text
Modulcode -> DD-Referenz -> dbxDB -> konfigurierter Server -> physische Tabelle
                         -> dbxForm -> FD/DD -> Eingabe und Validierung
                         -> dbxDD -> Schema-Vergleich und Synchronisation
```

Eine explizite Referenz besteht aus Modul und DD-Name:

```php
$dd = 'dbxWorkflow|workflowDefinition';
```

Ohne Modulpräfix sucht dbXapp zuerst im aktiven Modul und anschließend im
Kernmodul `dbx`. In wiederverwendbarem Fachcode ist die explizite Referenz
meist verständlicher und verhindert Namenskollisionen.

## dbxDB und dbxDD als stabile Fassaden

`dbxDB` ist groß, weil Verbindung, DD-Auflösung, Rechte, Owner-Filter,
Transaktionen, Trace, Fehler und DB-Abstraktion in jedem Modul gleich
funktionieren müssen. `dbxDD` erweitert diese Fassade um Schema-, Backup-,
Restore- und Transferprozesse.

Die Klassen werden nicht nach Zeilenzahl geteilt. Eine interne Extraktion ist
nur sinnvoll, wenn eine eigenständige Verantwortung mit Tests und
kompatibler öffentlicher API nachgewiesen ist. Fachmodule verwenden weiterhin
`dbxDB`/`dbxDD` als einzigen Einstieg und kennen keine internen Helfer.

## Eine vollständige DD

Das folgende Muster entspricht dem von dbxapp exportierten DD-Format. Tabelle,
Felder und Indexe stehen direkt in den Abschnitten `TABLE`, `FIELDS` und
`INDEXES`. Jedes Feld ist vollständig sichtbar und wird anschließend mit
`$fields[]=$field` angehängt. Lokale `$addField`-Closures oder andere
Hilfsabstraktionen gehören nicht in eine DD.

```php
<?php

/* =========================================================
   TABLE
   ========================================================= */
$table['server']='myTasks|myTasks.db3';
$table['table']='my_task';
$table['datadic']='myTask';
$table['primary']='id';
$table['language']='0';
$table['version']='1.0';
$table['autosync']='1';
$table['cache']='0';
$table['trash']='0';
$table['trace']='0';
$table['update_sql']='';
$table['default_sort']='title ASC';
$table['form-dd-table']='';
$table['read']='admin';
$table['create']='admin';
$table['update']='admin';
$table['delete']='admin';
$table['read_owner']='admin,owner';
$table['create_owner']='admin,owner';
$table['update_owner']='admin,owner';
$table['delete_owner']='admin,owner';


/* =========================================================
   FIELDS
   ========================================================= */
$field['name']='id';
$field['type']='int';
$field['index']='PRI';
$field['length']='11';
$field['default']='';
$field['label']='ID';
$field['rules']='int';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='hidden';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='title';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='160';
$field['default']='';
$field['label']='Titel';
$field['rules']='*|min=2|max=160';
$field['tooltip']='Kurzer, verständlicher Titel.';
$field['errormsg']='';
$field['placeholder']='Aufgabe benennen';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='';
$field['tpl']='text-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;

$field['name']='status';
$field['type']='varchar';
$field['index']='MUL';
$field['length']='24';
$field['default']='open';
$field['label']='Status';
$field['rules']='parameter|max=24';
$field['tooltip']='';
$field['errormsg']='';
$field['placeholder']='';
$field['convert']='';
$field['protect']='0';
$field['group']='';
$field['mask']='';
$field['data']='';
$field['options']='open=Offen&working=In Arbeit&done=Erledigt';
$field['tpl']='select-single-label';
$field['js']='';
$field['prompt']='';
$fields[]=$field;


/* =========================================================
   INDEXES
   ========================================================= */
$index['name']='pk_my_task';
$index['type']='PRIMARY';
$index['fields']='id';
$index['unique']='1';
$index['comment']='from field index PRI';
$indexes[]=$index;

$index['name']='idx_my_task_title';
$index['type']='INDEX';
$index['fields']='title';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;

$index['name']='idx_my_task_status';
$index['type']='INDEX';
$index['fields']='status';
$index['unique']='0';
$index['comment']='from field index MUL';
$indexes[]=$index;
```

Verbindliche reale Beispiele sind
`dbx/modules/dbx/dd/dbxMissing.dd.php` und die beiden DDs des
`myInvoices`-Referenzmoduls.

### Wichtige Tabellenattribute

| Attribut | Bedeutung |
| --- | --- |
| `server` | Konfigurierter DB-Server oder modulbezogene SQLite-Datei |
| `table` | Physischer Tabellenname |
| `primary` | Primärschlüssel; Standard ist `id` |
| `language` | `0` neutral, Sprachcode fest oder `*` dynamisch |
| `autosync` | Tabelle darf aus der DD synchronisiert werden |
| `trash` | Papierkorb-/Trace-Verhalten der Tabelle |
| `trace` | Änderungen werden über die DB-Pipeline nachvollziehbar protokolliert |
| `read/create/update/delete` | Gruppenrechte für die jeweilige Operation |
| `read_owner/update_owner` | Owner-basierte Rechte; dbxDB ergänzt den Owner-Filter |

`owner`, Zeitstempel und `trash` sind keine Pflicht für jede Tabelle. Sie
sollten aber gemeinsam und bewusst eingesetzt werden, wenn Eigentum,
Nachvollziehbarkeit oder Soft-Delete fachlich benötigt werden.

Sind `create_date`, `create_uid`, `owner`, `update_date` und `update_uid` in
der DD vorhanden, setzt `dbxDB` sie automatisch. Ein Fachmodul soll diese
Infrastrukturwerte weder im Formular noch vor `insert()`, `update()` oder
`save()` nachbauen.

### Lokale Serverbindung pro DD

`$table['server']` ist der ausgelieferte Standard, nicht eine globale
Festlegung für das ganze System. Eine Installation kann jede DD in
`config.local.php` einzeln auf eine DB3-Datei oder einen aktiven SQL-Server
binden. `dbxDB` löst diese Bindung zentral auf; Fachmodule bleiben unverändert.

```php
$config['dd_server_bindings'] = array(
    'dbx|dbxUser' => 'dbxInstall',
    'dbxShop|shopOrder' => 'dbxShop|dbxShop.db3',
);
```

Ungültige explizite Bindungen werden abgelehnt und fallen nicht unbemerkt auf
den DD-Standard zurück. Installation, Migration, Sicherung und Rollback sind
unter @ref dbxapp_install_update_dd_bindings verbindlich beschrieben.

### Feldattribute

| Attribut | Bedeutung |
| --- | --- |
| `name`, `type`, `length` | Datenbankfeld und Datentyp |
| `index` | z. B. `PRI`, `UNI` oder `MUL` |
| `default` | Default für neue bzw. leere Datensätze |
| `rules` | Validator-Regeln, z. B. `int`, `parameter`, `email`, `min`, `max` |
| `tpl` | Standard-Feldtemplate für dbxForm |
| `options` | Auswahlwerte, gewöhnlich `wert=Label&wert2=Label2` |
| `data` | Template-Daten, z. B. `rows=6` |
| `convert` | Ausgabe-/Eingabekonvertierung |

## FD: eine Formularsicht auf die DD

Die DD beschreibt die fachliche Datenstruktur. Eine FD wählt daraus die
Formularfelder aus, ordnet sie und kann Darstellungseigenschaften überschreiben.
So können Bearbeiten-, Such- und Schnellformular dieselbe DD unterschiedlich
darstellen.

```php
<?php

$messages = array();
$messages['save_success'] = 'Daten wurden gespeichert';
$messages['save_succeass'] = $messages['save_success'];
$messages['save_error'] = 'Daten konnten nicht gespeichert werden';

$field = array();
$field['name']        = 'title';
$field['type']        = 'varchar';
$field['tpl']         = 'text-label';
$field['default']     = '';
$field['label']       = 'Aufgabe';
$field['rules']       = '*|min=2|max=160';
$field['placeholder'] = 'Was ist zu tun?';
$fields[] = $field;

$field = array();
$field['name']    = 'status';
$field['type']    = 'varchar';
$field['tpl']     = 'select-single-label';
$field['default'] = 'open';
$field['label']   = 'Status';
$field['rules']   = 'parameter|max=24';
$field['options'] = 'open=Offen&working=In Arbeit&done=Erledigt';
$fields[] = $field;

$field = array();
$field['name']  = 'description';
$field['type']  = 'mediumtext';
$field['tpl']   = 'textarea-label';
$field['label'] = 'Beschreibung';
$field['rules'] = '*|max=5000';
$field['data']  = 'rows=6';
$fields[] = $field;

?>
```

### Sprachversionen und Meldungen

Eine FD enthält sichtbare Labels, Optionen, Platzhalter und Meldungen. Deshalb
gibt es für jede FD eine deutsche, englische und spanische Fassung:

| Sprache | Datei | `save_success` | `save_error` |
| --- | --- | --- | --- |
| Deutsch | `task-form.fd.php` oder `task-form_de.fd.php` | `Daten wurden gespeichert` | `Daten konnten nicht gespeichert werden` |
| Englisch | `task-form_en.fd.php` | `Data was saved` | `Data could not be saved` |
| Spanisch | `task-form_es.fd.php` | `Los datos se guardaron` | `Los datos no se pudieron guardar` |

`dbxForm` löst die Datei über die aktive Sprache auf, lädt `$fields` und
`$messages` gemeinsam und hält beides im zentralen FD-Cache. `dbxReport` erbt
genau denselben Ablauf. Der verbindliche Schlüssel heißt `save_success`;
`save_succeass` wird nur als kompatibler Alias mitgeführt.

Eine DD erhält dagegen nur dann Sprachdateien, wenn tatsächlich getrennte
Sprachtabellen existieren. Sichtbare Übersetzungen allein sind kein Grund, eine
DD zu duplizieren.

Möglichkeiten:

- Nur `_dd` setzen: `dbxForm` verwendet Feldangaben der DD.
- `_dd` und `_fd` setzen: Die FD bestimmt die konkrete Formularsicht.
- Einzelne Felder manuell ergänzen oder gezielt DD-Werte mit `dd::` verwenden.
- Für einen Report eine eigene Selection-FD mit `dbx_rwhere`, `dbx_rsort`,
  `dbx_rdesc`, `dbx_rrows` und optional `dbx_rselect` verwenden.

## dbxDB lesen

```php
$db = dbx()->get_system_obj('dbxDB');
$dd = 'myTasks|myTask';
```

### Einen Datensatz lesen

```php
$task = $db->select1($dd, (int)$rid);
```

Eine Integer-WHERE wird gegen den in der DD definierten Primärschlüssel
aufgelöst. Spalten können begrenzt werden:

```php
$task = $db->select1($dd, array('id' => (int)$rid, 'trash' => 0),
    array('id', 'title', 'status'));
```

`select1()` liefert bei keinem Treffer die leere Standardstruktur der DD.
Deshalb sollte Fachcode nicht nur `is_array()` prüfen, sondern auch die ID:

```php
if ((int)($task['id'] ?? 0) <= 0) {
    return dbx()->get_system_obj('dbxTPL')->get_tpl(
        'dbx|alert-warning',
        array('msg' => 'Nicht gefunden.')
    );
}
```

### Listen, Sortierung und Pagination

```php
$rows = $db->select(
    $dd,
    array('status' => 'open', 'trash' => 0),
    array('id', 'title', 'status', 'update_date'),
    'update_date',
    'DESC',
    '',
    25,
    0
);
```

Die Parameter nach `$columns` sind `orderby`, `ASC|DESC`, `groupby`, `max`,
`offset` und `verify_access`. Stammt Sortierung aus einem Request, müssen
Spaltenname und Richtung vorher gegen feste Allowlists geprüft werden; sie sind
keine freien Suchwerte.

### Sichere Suche

Array-WHEREs validieren Felder gegen die DD und escapen Werte zentral:

```php
$where = array(
    'trash' => 0,
    'search' => array(
        'value' => $search,
        'like' => array('title', 'description'),
        'mode' => 'contains',
    ),
);

$rows = $db->select($dd, $where, '*', 'title', 'ASC', '', 50, 0);
```

Für ein einzelnes LIKE-Feld ist ebenfalls eine strukturierte Form möglich:

```php
$rows = $db->select($dd, array(
    'title' => array('like' => $search, 'mode' => 'starts_with'),
));
```

Neue Request-Suchen sollten diese Formen verwenden. Ein String-WHERE bleibt
für bestehenden und intern aufgebauten Code möglich, darf aber nicht durch
unkontrolliertes Konkatenieren von Benutzereingaben entstehen.

### Zählen

```php
$all   = $db->count($dd);
$open  = $db->count($dd, array('status' => 'open', 'trash' => 0));
```

## dbxDB schreiben

### Automatische Systemfelder

Bei `insert()` setzt `dbxDB` automatisch:

- `create_date`
- `create_uid`
- `owner`
- `update_date`
- `update_uid`

Bei `update()` setzt `dbxDB` automatisch `update_date` und `update_uid`.
`dbxForm::save_post()` verwendet dieselbe `dbxDB`-Pipeline. Die Automatik ist
ein wesentlicher Vorteil der DD-Nutzung: alle Module erhalten identische
Audit- und Owner-Werte, ohne sie selbst zu verwalten.

### Insert

`insert()` liefert `1` bei Erfolg. Die neue ID wird anschließend gelesen:

```php
$ok = $db->insert($dd, array(
    'title'       => 'Dokumentation prüfen',
    'status'      => 'open',
    'description' => 'Beispiele im Browser nachvollziehen.',
));

$rid = ($ok === 1) ? $db->get_insert_id() : 0;
```

### Update

```php
$ok = $db->update($dd, array(
    'status' => 'done',
), array('id' => (int)$rid, 'trash' => 0));
```

### Insert oder Update mit `save()`

```php
$values = array('title' => $title, 'status' => $status);
$ok = $db->save($dd, $values, $rid > 0 ? $rid : 0);
$rid = ($rid > 0) ? $rid : $db->get_insert_id();
```

`save()` aktualisiert bei vorhandener WHERE/RID und fügt sonst ein. Für
fachlich komplexe Speicherungen sind getrennte Insert-/Update-Zweige oft
lesbarer; für Standardformulare verwendet `dbxForm::save_post()` intern diesen
Weg.

### Delete

```php
$ok = $db->delete($dd, array('id' => (int)$rid));
```

Ein Delete ohne WHERE wird von dbxDB blockiert. Ob eine Tabelle wirklich
gelöscht, getraced oder über einen fachlichen Papierkorb behandelt werden soll,
entscheidet das Modul zusammen mit der DD. Nicht eigenmächtig Rechte- oder
Trace-Prüfungen deaktivieren.

### Parameter für Rechte, Felder, Werte und Trace

Die Schreibmethoden besitzen am Ende Infrastruktur-Schalter:

```php
$db->insert($dd, $values,
    $verify_access, $verify_fields, $verify_values, $trace);
$db->update($dd, $values, $where,
    $verify_access, $verify_fields, $verify_values, $trace);
$db->delete($dd, $where, $verify_access, $trace);
```

Im Fachmodul bleiben alle Werte normalerweise `1`. Aufrufe mit `0` sind nur
für klar begrenzte Systempfade vorgesehen, etwa Installation, interne
Synchronisation oder bereits separat geschützte Infrastruktur. Reale Beispiele
dazu stehen in `dbxWorkflowEngine`, `dbxContentLngSync` und
`dbxShopRepository`.

## Baumdaten

Für Parent-/Child-Strukturen kann `select_tree()` Ordner und optionale Items
gemeinsam normalisieren:

```php
$folderDd = \dbx\dbxContent\dbxContentLng::ddFolder();
$contentDd = \dbx\dbxContent\dbxContentLng::ddContent();

$tree = $db->select_tree(
    $folderDd,
    $contentDd,
    array(
        'folder_parent' => 'parent_id',
        'folder_title' => 'name',
        'item_parent' => 'folder',
        'item_title' => 'title',
        'root' => 0,
    )
);

$nodes = $tree['nodes'];
$flat  = $tree['flat'];
```

Die konkrete CMS-DD wird sprachabhängig über `dbxContentLng` ermittelt. Das
Beispiel zeigt die API; CMS-Code sollte die vorhandenen Resolver verwenden.

## DD-Modell lesen

```php
$model = dbx()->get_system_obj('dbxDD')
    ->get_dd_model('myTasks|myTask');

$table  = $model['table']  ?? array();
$fields = $model['fields'] ?? array();
```

Das ist für Generatoren, Admin-Werkzeuge und Workflow-Bindings sinnvoll.
Fachcode sollte seine Geschäftslogik nicht bei jedem Request dynamisch aus
Felddefinitionen erraten.

## DD und Datenbank synchronisieren

DD-Sync ist ein schrittweiser Prozess. Das robuste Muster aus den aktuellen
Modulen setzt den Prozess zurück und ruft `apply` auf, bis er fertig ist:

```php
$dd = dbx()->get_system_obj('dbxDD');
$dd->sync_dd_to_db('myTasks', 'myTask', 'reset');

do {
    $state = $dd->sync_dd_to_db('myTasks', 'myTask', 'apply');
} while (($state['status'] ?? '') === 'running');

if (($state['status'] ?? '') !== 'finished') {
    throw new \RuntimeException((string)($state['message'] ?? 'DD-Sync fehlgeschlagen'));
}
```

Verfügbare Einsatzarten:

- `plan`/`check`: Unterschiede anzeigen, ohne das Schema anzuwenden.
- `reset`: gespeicherten Prozesszustand zurücksetzen.
- `apply`: geplante Schritte kontrolliert anwenden.
- `force`: nur in dafür vorgesehenen Admin-/Installationspfaden erzwingen.
- `sync_db_to_dd(...)`: eine bestehende DB-Struktur in eine DD übernehmen oder
  mit ihr zusammenführen.

DB nach DD ist keine normale Fachaktion. Sie gehört in Schema-/Wizard-Werkzeuge
und muss anschließend als vollständige, lesbare DD geprüft werden.

## Datenbanksysteme

dbxDB kapselt den Zugriff über PDO. Unterstützt werden – abhängig von
Konfiguration und PHP-Treibern – unter anderem SQLite, MySQL/MariaDB,
PostgreSQL, SQL Server, Oracle, Firebird sowie weitere PDO-Treiber. Eine DD darf
daher keine SQLite-spezifische Fachlogik erzwingen. Unterschiede bei Schema und
Limits werden in dbxDB/dbxDD behandelt.

## Verbindliche Regeln

1. Fachmodule verwenden `dbxDB`, nicht direkt PDO.
2. DD ist die versionierbare Wahrheit der Tabellenstruktur.
3. FD beschreibt eine Formularsicht und dupliziert nicht unnötig das Datenmodell.
4. Request-Werte werden validiert; Suchbedingungen bevorzugen Array-WHEREs.
5. HTML gehört nicht in Datenbankmethoden und SQL nicht in Templates.
6. `verify_access=0` und `trace=0` sind begründete Infrastruktur-Ausnahmen.
7. DD-Sync wird vollständig bis `finished` ausgeführt und auf Fehler geprüft.
8. Jede neue Tabelle erhält eine eigene DD und einen eindeutigen DD-Namen.
9. Automatische Owner-, Benutzer- und Zeitfelder werden nicht im Modul
   dupliziert.

## Reale Referenzen

- `dbx/modules/dbxWorkflow/dd/workflowDefinition.dd.php`: vollständige DD.
- `dbx/modules/dbxWorkflow/fd/workflow-definition.fd.php`: Formularsicht.
- `dbx/modules/dbxWorkflow/include/dbxWorkflowEngine.class.php`: CRUD mit
  Array-WHEREs und fachlicher Persistenz.
- `dbx/modules/dbxShop/include/dbxShopRepository.class.php`: DD-Sync und
  umfangreicher Repository-Zugriff.
- `dbx/modules/dbxContent/include/dbxContentLngSync.class.php`:
  sprachabhängige DDs und kontrollierte Systemschreibvorgänge.
- @ref dbxapp_module_reference — vollständiger, verbindlicher Modulablauf.
- @ref dbxapp_db_roundtrip — getesteter DB3-MySQL-DB3-Transfer.
