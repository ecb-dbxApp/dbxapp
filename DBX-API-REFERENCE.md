# dbx()-API-Referenz

Diese Datei ist die zusammenhaengende Referenz fuer die zentrale Laufzeit-API
`dbx()` (Klasse `dbxApi`, `dbx/include/dbxApi.php` + fuenf Trait-Dateien
`dbx/include/dbxApi*.trait.php`). Sie beantwortet drei Fragen pro Methode:
**Sinn und Zweck** (wofuer gibt es sie), **Verwendung** (wie ruft man sie auf)
und **Beispiel** (ein konkreter Aufruf).

Diese Datei ist eine Lesehilfe, keine zweite Quelle. Verbindlich ist immer der
PHPDoc-Block direkt an der Methode; jede Methode unten verlinkt auf ihre
Quelldatei. Fuer die Frage "warum sind zwei aehnlich klingende Methoden nicht
zusammengefasst" siehe `DBX-API-AUDIT.md`. Fuer den Kernel-Bearbeitungsvertrag
siehe `AGENTS.md` und `SOURCE-CONVENTIONS.md`.

Stand: 64 oeffentliche Methoden (siehe `DBX-API-AUDIT.md` fuer die historische
Herleitung; die dortige Zahl 85 ist ein aelterer Zwischenstand, siehe
`SOURCE-REFACTOR-TODO.md` Abschnitte L/M).

## 1. Sinn und Zweck von `dbx()`

`dbx()` ist die einzige oeffentliche Fassade der dbxapp-Laufzeit. Freie globale
`dbx_*`-Funktionen existieren bewusst nicht mehr (Ausnahme: `dbx_get_base_dir()`/
`dbx_get_file_dir()` in `index.php`, die vor dem Laden von `dbxApi.php` noetig
sind). Anwendungscode — Module, Includes, Templates — arbeitet ausschliesslich
ueber diese eine Funktion:

```php
$db = dbx()->get_system_obj('dbxDB');
$value = dbx()->get_request_var('dbx_run1', 'run');
dbx()->set_system_var('dbx_ajax', 1);
```

`dbx()` selbst liefert ein Singleton (`dbxApi`, ein Objekt pro PHP-Prozess/
Request). Die Klasse ist intern nach Verantwortung in fuenf Traits aufgeteilt,
nach aussen bleibt sie eine einzige stabile API:

| Trait | Datei | Verantwortung |
|---|---|---|
| `dbxApiRequestStateTrait` | `dbxApiRequestState.trait.php` | Request-Kontext, System-/Modul-/Remember-/Sessionzustand |
| `dbxApiConfigTrait` | `dbxApiConfig.trait.php` | Modulkonfiguration lesen/schreiben, Pfad-Portabilitaet |
| `dbxApiAssetsTrait` | `dbxApiAssets.trait.php` | RAD-Editor-Dateimarker |
| `dbxApiSecurityTrait` | `dbxApiSecurity.trait.php` | Demo-/Bypass-Erkennung, Action-Token, Benutzer, Rechte |
| `dbxApiLanguageTrait` | `dbxApiLanguage.trait.php` | Aktive Sprache, Sprachdateien, sprachabhaengige Namen |

Alles ausserhalb dieser Traits (Objekt-Laden, Owner-Stack, Pfade, JSON-Antworten,
Timer, Systemmeldungen, Validierung) bleibt direkt in `dbxApi.php`.

## 2. Die vier Zustandsebenen (der wichtigste Begriff in dieser API)

Vier `dbx()`-Methodenpaare klingen aehnlich, sind aber **keine Synonyme** — sie
schreiben in unterschiedliche Zustandsbereiche mit unterschiedlicher
Lebensdauer:

| Ebene | Lesen/Schreiben | Lebensdauer | Sinn und Zweck |
|---|---|---|---|
| **System** | `get_system_var()` / `set_system_var()` | ein Request | Globaler Laufkontext: aktives Modul, Design, Sprache, Ajax-Status, URLs. |
| **Modul** | `get_modul_var()` / `set_modul_var()` | eine Modul-**instanz** (`dbx_activ_modul_id`) | Erlaubt mehrere Instanzen desselben Moduls auf einer Seite, jede mit eigenem Zustand. |
| **Remember** | `get_remember_var()` / `set_remember_var()` | requestuebergreifend, pro Benutzer/UI | Sprach-/Design-/Editmodus-Wahl, zuletzt aktive Auswahl, Reportzustand. |
| **Session** | `get_session_var()` / `set_session_var()` / `delete_session_var()` | requestuebergreifend, explizit benannter Modulbereich | Strukturierter Zustand mit eigenem `$section`-Schluessel (z. B. Warenkorb, Assistenten-Fortschritt). |

Dazu kommt `get_request_var()` (liest **nur** den rohen GET/POST-Wert des
aktuellen Requests, kein Fallback auf Session/Modulzustand) und
`get_json_request()` (liest den JSON-Requestbody statt klassischem POST).

Beispiel, das den Unterschied zeigt:

```php
// Modulinstanz A und B auf derselben Seite duerfen unterschiedliche
// Werte fuer denselben Variablennamen halten:
dbx()->set_modul_var('active_tab', 'details');   // gilt nur fuer diese Instanz

// Die Spracheinstellung des Benutzers soll dagegen ueber Requests hinweg
// erhalten bleiben:
dbx()->set_remember_var('dbx_lng', 'en', 'dbx');
```

## 3. Objekte laden: `get_system_obj` / `get_modul_obj` / `get_include_obj`

```php
$db   = dbx()->get_system_obj('dbxDB');        // Kernel-Systemklasse, gecacht
$mod  = dbx()->get_modul_obj('dbxAdmin');      // Hauptmodul starten
$inc  = dbx()->get_include_obj('user_list');   // Include-Klasse des aktiven Moduls
```

- `get_system_obj($class)` laedt eine Kernel-Systemklasse aus `dbx/include/`
  und cached sie unter dem Originalnamen. Existiert
  `dbx/modules/myX/sysclass/my<Suffix>.class.php` (Projekt-Override), wird
  automatisch **diese** Klasse instanziert statt der Originalklasse — Zugriff
  bleibt trotzdem ueber den Originalnamen (`myDB extends dbxDB`,
  `get_class($db) === 'myDB'`). `dbxForm`, `dbxReport`, `dbxView`, `dbxProcess`
  sind bewusst zustandsbehaftete Builder und werden **nie** aus dem Cache
  geteilt (jeder Aufruf erzeugt eine neue Instanz).
- `get_modul_obj($class)` startet ein Hauptmodul: erhoeht die
  Modulinstanz-ID, setzt `dbx_activ_modul`, laedt
  `dbx/modules/<modul>/<modul>.class.php`.
- `get_include_obj($class, $modul = '')` laedt eine Include-Klasse aus
  `dbx/modules/<modul>/include/`; ohne `$modul` wird das aktuell aktive Modul
  verwendet.

Details/Fehlerverhalten stehen im PHPDoc der jeweiligen Methode in
`dbxApi.php`.

## 4. Methodenuebersicht nach Verantwortung

Jede Zeile verweist auf die Datei mit dem vollstaendigen PHPDoc (Parameter,
Rueckgabewert, Beispiel). Diese Tabelle ist bewusst kurz gehalten — sie dient
zum Auffinden, nicht als Ersatz fuer den Methoden-Docblock.

### Objekt- und Request-Laufzeit — `dbxApi.php`
`get_system_obj`, `get_modul_obj`, `get_include_obj`, `get_current_owner`,
`run_owner`, `load_content_cache_classes`

### Request-, Modul-, Remember- und Sessionzustand — `dbxApiRequestState.trait.php`
`request_context`, `get_request_var`, `get_json_request`, `append_url_params`,
`get_system_var`, `set_system_var`, `get_modul_var`, `set_modul_var`,
`get_remember_var`, `set_remember_var`, `get_session_var`, `set_session_var`,
`delete_session_var`, `delete_cookie` (Cookie-Loeschung, `dbxApi.php`)

Siehe Abschnitt 2 fuer die Bedeutung der vier Ebenen.

### Konfiguration — `dbxApiConfig.trait.php`
`get_cfg`, `set_cfg`, `patch_local_config`, `set_local_config_section`,
`config_path_store`, `config_path_resolve`

`get_cfg($modul, $key)` liest `cfg/config.php` **plus** `cfg/config.local.php`
(lokale Werte ueberschreiben, z. B. Zugangsdaten) und cached pro Modul, bis
sich Dateigroesse/-zeit aendern. `set_cfg($modul, $config, 'base'|'local')`
schreibt gezielt die Basis- oder die lokale Datei. `patch_local_config()`
merged rekursiv in `config.local.php`, ohne andere lokale Werte zu verlieren.
`config_path_store()`/`config_path_resolve()` machen Installationspfade in
Konfigurationswerten portabel (relativ speichern, absolut auflösen).

### Sicherheit und Benutzer — `dbxApiSecurity.trait.php` (+ `validate_var`/`is_access_denied`/`is_db_error` in `dbxApi.php`)
`is_demo_mode`, `is_admin_bypass_active`, `action_token`, `check_action_token`,
`invalidate_action_tokens`, `action_url`, `user`, `has_group`,
`has_module_access`, `is_dbx_edit`, `login`, `validate_var`,
`is_access_denied`, `is_db_error`

`has_group($groups)` prueft nur Gruppenmitgliedschaft. `has_module_access($modul)`
prueft zusaetzlich die Konfiguration eines konkreten Moduls und setzt bei
Ablehnung den Modulfehlerkontext — beide sind fachlich unterschiedlich (siehe
`DBX-API-AUDIT.md`). `action_token()`/`check_action_token()`/`action_url()`
sind der CSRF-Schutz fuer zustandsaendernde GET-Links; siehe die reichhaltigen
PHPDoc-Beispiele direkt an den Methoden.

### Assets und Editor — `dbxApiAssets.trait.php` (+ `editor_marker` in `dbxApi.php`)
`editor_file_path`, `register_editor_file`, `get_editor_files`, `editor_marker`

Diese vier Methoden bilden zusammen den RAD-Runtime-Editor-Marker-Mechanismus:
Kernel-Code registriert waehrend des Requests genutzte Dateien
(`register_editor_file`), `editor_marker` gibt dafuer im passenden
`dbx_edit`-Modus einen HTML-Kommentar mit Bearbeiten-Link aus.

### Sprache — `dbxApiLanguage.trait.php`
`lng_current`, `accessible_lngs`, `lng_name`, `lng_resolve_file`

### Pfade, Ausgabe und Infrastruktur — `dbxApi.php`
`get_base_url`, `get_self_url`, `get_base_dir`, `get_file_dir`, `os_path`,
`get_version`, `json_response`, `redirect`

### Gemeinsame Kernel-Dienste — `dbxApi.php`
`timer`, `log_missing`, `sys_msg`, `next_id`, `norep`, `esc`, `debug`,
`get_content_permalink_mode`

`esc()` ist der zentrale UTF-8-sichere Ersatz fuer lokale
`htmlspecialchars()`-Aufrufe — kein Modul soll eigene Escape-Helfer bauen.
`sys_msg()` schreibt strukturierte, nach `dbx()->get_cfg('dbx','sys_msg_level')`
gefilterte Systemmeldungen; `debug()` schreibt unbedingt nur, wenn
`files/dbxDebugActiv.txt` existiert.

## 5. Was NICHT mehr in `dbx()` ist

Waehrend des 4.3-Refactorings (`SOURCE-REFACTOR-TODO.md`) wurden fachfremde
Einzelfunktionen aus der Fassade in benannte Kernel-Services verschoben, u.a.:

- Design/Skin-Katalog, CSS/JS-Modul-Assets → `dbxPresentation` / `dbxAssetRegistry`
- Passwortgenerierung → `dbxPasswordPolicy`
- Fehlerprotokollierung (`error_log_file`, `write_php_error_log`, ...) → `dbxRuntime`
- Mailversand/-vorbereitung → `dbxMail`
- Konfigurationscache-Verwaltung → `dbxConfigStore`
- Such-Feld-Defaults → `dbxSearchDefaults`
- Kompletter Frontcontroller-Ablauf → `dbxRequestPipeline`

Diese Services werden weiterhin ueber `dbx()->get_system_obj('<Service>')`
geladen; nur die duenne, semantisch redundante Weiterleitungsmethode in
`dbxApi` selbst wurde entfernt. Neue Fassadenmethoden in `dbxApi` brauchen laut
Vertragstest (`dbxApiSurface_contract_test.php`) entweder das Entfernen einer
alten Verantwortung oder eine bewusste Anpassung von `DBX-API-AUDIT.md`.
