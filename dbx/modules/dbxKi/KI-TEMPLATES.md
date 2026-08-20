# dbxApp-Templates – verbindlicher KI-Leitfaden

Dieser Leitfaden gilt für Modulaufträge. Ziel ist eine einheitliche Oberfläche ohne Verlust der fachlichen Gestaltungsfreiheit eines Moduls.

## 1. Zuständigkeit und Speicherort

| Art | Speicherort | Referenz | Zweck |
|---|---|---|---|
| Gemeinsamer UI-Baustein | `dbx/modules/dbx/tpl/htm/` | `dbx|name` | Standardfelder, Meldungen, Buttons, Bars, Footer, Tabellenaktionen, Suche und Pagination |
| Individuelles Modul-Layout | `dbx/modules/{modul}/tpl/htm/` | `{modul}|name` | Fachliche Anordnung eines Formulars oder eines besonderen Reports |
| Kontexthilfe eines Moduls | `dbx/modules/{modul}/tpl/help/` | über die Modul-Hilfe | Hilfe für die Screens des Moduls; nicht als normales UI-Template verwenden |
| Modul-CSS | `dbx/modules/{modul}/tpl/css/` | `dbx()->get_system_obj('dbxAssetRegistry')->add_css('{modul}', 'datei.css')` | Darstellung der modulspezifischen Komponenten |
| Modulbilder | `dbx/modules/{modul}/tpl/img/` | Modul-Assetpfad | Bilder und Icons des Moduls |
| Modul-JavaScript | `dbx/modules/{modul}/js/` | `dbx()->get_system_obj('dbxAssetRegistry')->add_js('{modul}', 'datei.js')` | Ausschließlich modulspezifisches Verhalten |

Design-Seitentemplates liegen unter `dbx/design/{design}/htm/` und gehören zum Design, nicht zu einem Fachmodul. Menü- und Installationstemplates sind Kundendateien und dürfen durch einen Modulauftrag nicht verändert werden.

## 2. Entscheidung vor jeder Neuanlage

1. Im Zielmodul nach einem fachlich passenden Template suchen.
2. Unter `dbx/modules/dbx/tpl/htm/` nach einem gemeinsamen Baustein suchen.
3. Prüfen, ob das vorhandene Template durch Replaces, Slots oder eine vorhandene Property konfiguriert werden kann.
4. Nur wenn die fachliche **Anordnung** wirklich abweicht, ein individuelles Haupttemplate im Zielmodul anlegen.
5. Niemals ein Template kopieren, wenn sich nur Text, Sprache, Icon, URL, CSS-Klasse, Button, Meldung oder aktivierte Aktion unterscheidet.

Templates werden mit `dbxTPL` gerendert:

```php
$html = dbx()->get_system_obj('dbxTPL')->get_tpl(
    'dbx|form-message-save-success',
    $data
);
```

Ein Include in einem Template verwendet beispielsweise:

```text
[tpl=dbx|report-shell-head]
```

Werte werden als Replaces übergeben oder mit `add_rep()` gesetzt. Templates enthalten kein PHP, kein eingebettetes `<style>` und kein eingebettetes `<script>`.

## 3. Formulare

### Haupttemplate

Ein Formular hat fast immer ein individuelles Haupttemplate unter:

```text
dbx/modules/{modul}/tpl/htm/{formularname}.htm
```

Es bestimmt nur die fachliche Feldaufteilung. Gemeinsame Bedien- und Statusbereiche werden nicht kopiert, sondern über diese Slots eingebunden:

```html
<div id="dbxForm_{i}" class="dbx-panel dbxForm_wrapper dbx-ajax-root">
  {form:bar}
  <form action="{action}" method="post" id="dbx_form_{i}"
        class="dbxAjax" data-target="dbxForm_{i}">
    <div class="dbx-panel-body">
      {form:message}
      [dbx:form]
    </div>
    {form:footer}
    [dbx:js]
  </form>
</div>
```

- `{form:bar}`: Titel, Untertitel, Hilfe und Kopfleistenaktionen; Default `dbx|form-bar-default`.
- `{form:message}`: einheitliche Validierungs-, Erfolgs-, Warn- und Fehlermeldungen.
- `[dbx:form]`: von dbxForm erzeugte Felder und Sicherheitsdaten.
- `{form:footer}`: Speichern, Löschen und weitere Formularaktionen; Default `dbx|form-footer-default`.
- `[dbx:js]`: von dbxForm registrierter JavaScript-Ausgabeslot.

Initialisierung:

```php
$form = dbx()->get_system_obj('dbxForm');
$form->init('customer-edit', 'customer-form');
```

`customer-edit` ist die stabile Form-ID und damit die Identität des UI-State. `customer-form` ist das Layout. Beides darf nicht miteinander verwechselt werden. Ohne explizites Template bleibt `dbx|form-default` aktiv; die Form-ID wird nicht als Templatename eingesetzt.

Nur bei einer echten fachlichen Abweichung dürfen gemeinsame Bereiche ersetzt werden:

```php
$form->set_form_bar_template('{modul}|form-bar-special');
$form->set_form_footer_template('{modul}|form-footer-special');
```

Felder werden über DD/FD und dbxForm erzeugt. Gemeinsame Feldtypen verwenden die zentralen Templates:

- `dbx|field-input-default`
- `dbx|field-select-default`
- `dbx|field-textarea-default`
- `dbx|field-checkbox-default`
- `dbx|field-status-default`

Standardmeldungen, Delete-Titel und Delete-Hinweise kommen ebenfalls aus den vorhandenen `dbx|form-message-*`- und `dbx|form-action-delete-*`-Templates.

## 4. Reports

Normale Tabellenreports verwenden ohne eigenes Layout:

- Haupttemplate `dbx|report-default`
- Leiste `dbx|report-bar-default`
- Footer `dbx|report-footer`
- Pagination `dbx|pagination`
- Tabellenaktionen `dbx|table_header_action` und `dbx|table_row_action`

```php
$report = dbx()->get_system_obj('dbxReport');
$report->init('customer-list');
$report->set_table_actions(array(
    'edit' => array('window' => true, 'width' => '900'),
    'show',
    'delete',
));
```

Die Report-ID ist State- und Callback-Identität, kein Templatename. Standardaktionen werden mit `set_table_actions()` und `set_table_action_options()` konfiguriert; dafür werden keine eigenen Edit-/Show-/Delete-Templates kopiert.

Nur ein Report mit wirklich anderer Struktur erhält ein eigenes Haupttemplate im Zielmodul. Dieses verwendet weiterhin:

```html
{report:bar}
{report:message}
<!-- individuelle Report-Anordnung -->
{report:footer}
```

Ein bewusst abweichendes Template wird über `set_report_tpl()` beziehungsweise bei `init()` gesetzt. Bar und Footer werden nur im Ausnahmefall über `set_report_bar_tpl()` oder `set_report_footer_tpl()` ersetzt.

## 5. Sprache

Fachtexte gehören in die sprachabhängigen FD-Dateien des Moduls. Sprachabhängige Standardtexte gemeinsamer Bedienelemente kommen aus zentralen sprachfähigen `dbx`-Templates beziehungsweise deren `{ui:...}`-Werten.

`name_en.htm` oder `name_es.htm` ist nur zulässig, wenn sich Struktur oder vollständiger fachlicher Inhalt tatsächlich unterscheiden. Unterscheidet sich lediglich sichtbarer Text, bleibt das Markup in genau einer Template-Datei.

## 6. Wann ein neues Template gerechtfertigt ist

Ein neues Template ist gerechtfertigt, wenn mindestens einer dieser Punkte zutrifft:

- Die fachliche Feldanordnung eines Formulars ist neu.
- Ein Spezialreport ist keine normale Tabelle und benötigt eine eigene Struktur.
- Ein vorhandener gemeinsamer Baustein besitzt absichtlich nicht den benötigten semantischen Slot.
- Die Abweichung lässt sich nicht sauber über Daten, Replaces, Properties oder vorhandene Slots ausdrücken.

Vor der Neuanlage muss die KI in ihrer Antwort kurz nennen, welche vorhandenen Templates geprüft wurden und warum keines davon passt.
