# myInvoices – ausführbares dbXapp-Referenzmodul

`myInvoices` ist die lauffähige Referenz zum verbindlichen Modulhandbuch. Es
zeigt Rechnungen mit eingebetteten Artikelpositionen und verwendet die
dbXapp-Libraries ohne parallele Infrastruktur:

- `dbxDB` für alle Fachdatenzugriffe,
- zwei DDs auf demselben SQLite-Server,
- `dbxForm` plus FD für das Rechnungskopfformular und die Installation,
- `dbxReport` für beide Listenstufen,
- automatische `{fid}_next_record`-Callbacks für virtuelle Summenspalten,
- spätes `add_rep()` für Endsummen und `{rpt:colspan}` für den Footer,
- `dbxTPL` für sämtliches Strukturmarkup,
- `[modul=myInvoices]` für die serverseitige zweite Listenstufe,
- `dbxAjax` und `dbxConfirm` für progressive Browserinteraktion,
- Action-Token nur für den mutierenden Delete-GET,
- eine gemeinsame `dbxDB`-Transaktion für Kopf und Positionen.

## Installation und Demo-Daten

Browser:

```text
?dbx_modul=myInvoices&dbx_run1=install
```

Die Mutation läuft erst nach einem gültigen `dbxForm`-POST. Alternativ:

```powershell
php dbx/modules/myInvoices/tools/install_demo.php
```

Der Installer ist idempotent. Bestehende Demo-Rechnungen werden weder
überschrieben noch dupliziert. `create_date`, `create_uid`, `update_date`,
`update_uid` und `owner` werden nicht als Fixture-Werte übergeben, sondern
von `dbxDB` automatisch gesetzt.

## Routen

| Route | Funktion | Mutation |
| --- | --- | --- |
| `report` | paginierte Rechnungen mit Positions-Inclusion | nein |
| `positions&invoice_id=…` | eigenständiger Positionsreport | nein |
| `form&rid=new` | neue Rechnung per dbxForm | POST |
| `form&rid=…` | Rechnungskopf ändern per dbxForm | POST |
| `delete&rid=…` | Kopf und Positionen atomar löschen | GET, durch zentrale Policy automatisch tokenisiert |
| `install` | DD-Sync und Demo-Fixtures | POST |

## Summenvertrag

Die DD `invoice.total_gross` enthält den fachlichen Snapshot. Der automatische
Callback `invoice_report_next_record()` formatiert und summiert diesen Snapshot
für die sichtbare Seite. `invoice_items_report_next_record()` berechnet je
Zeile `quantity * unit_price`, setzt die virtuelle Spalte `sum` und akkumuliert
in Cent. Beide Callbacks aktualisieren den jeweiligen Footerwert spät mit
`add_rep()`. dbxReport setzt ihn beim Footerlauf ein und berechnet
`{rpt:colspan}` selbst. Eigene Footer-Methoden sowie Owner- und Callback-Setter
sind dafür nicht nötig.

## Tests

Siehe [tests/README.md](tests/README.md). Die Tests greifen ebenfalls nur über
`dbxDB` und DDs auf Fachdaten zu.
