# Audit der öffentlichen `dbx()`-API

Stand: 16. August 2026 (Methodeninventar unten historisch; aktueller Zaehlstand
und laufende Referenz siehe `DBX-API-REFERENCE.md`).

Ziel ist eine kleine, eindeutig benannte Fassade. Geprüft wurde nach fachlicher
Verantwortung und beobachtbarem Verhalten, nicht nur nach identischem Sourcecode.

## Ergebnis

- Ausgangsstand vor diesem Audit: 98 öffentliche Methoden.
- Stand direkt nach diesem Audit: 85 öffentliche Methoden.
- Entfernt oder zusammengeführt in diesem Audit: 13 Methoden.
- Verbleibende reine Synonyme/Weiterleitungs-Aliase nach diesem Audit: 0.
- **Aktueller Stand (siehe `SOURCE-REFACTOR-TODO.md` Abschnitte L/M und der
  Vertragstest `dbxApiSurface_contract_test.php`): 64 öffentliche Methoden.**
  Die weitere Reduktion 85 → 70 → 64 verschob fachfremde Einzelfunktionen
  (Design/Skin, Passwortpolitik, Fehlerprotokollierung, Mailversand,
  Konfigurationscache, Such-Defaults, Frontcontroller-Ablauf) in benannte
  Kernel-Services; die untenstehende Gruppeneinteilung dieses Audits blieb
  fachlich gueltig, nur die Mitgliederliste einzelner Gruppen ist seither
  kleiner. Die vollstaendige, aktuell gueltige Methodenliste je Gruppe steht
  in `DBX-API-REFERENCE.md`.

Entfernt wurden:

- `can` zugunsten der eindeutigen booleschen Gruppenprüfung `has_group`.
- `can_modul` zugunsten von `has_module_access`.
- `html` zugunsten des zentralen UTF-8-sicheren `esc`.
- `use_system_class`, weil `get_system_obj($class, 'use')` denselben Lademodus besitzt.
- `has_text`, weil die Methode ungenutzt war.
- `timestamp` und `time_diff` zugunsten von `microtime(true)` und direkter Differenzbildung im zuständigen Formular.
- `part_select` zugunsten der lokalen Feldnamen-Auflösung in `dbxForm`.
- `parse_url` zugunsten der lokalen Ersetzungsnormalisierung in `dbxTPL`.
- `is_modul` zugunsten der privaten Modulprüfung des Routers.
- `set_cookie_var` zugunsten der zuständigen Session-Implementierung.
- `debug2` zugunsten der lokalen CSV-Importdiagnose.
- `is_int_value` zugunsten lokaler, typnaher Integer-Prüfungen.

## Geprüfte und bewusst getrennte Verantwortungen

Die folgenden 85 Methoden wurden einzeln ihrer Verantwortung zugeordnet. Innerhalb
der Gruppen haben ähnlich klingende Methoden ein anderes Verhalten, einen anderen
Zustandsbereich oder eine andere Sicherheitsgrenze.

### Objekt- und Request-Laufzeit

`get_system_obj`, `get_modul_obj`, `get_include_obj`, `get_current_owner`,
`run_owner`, `run_web_app_request`, `load_content_cache_classes`

### Request-, Modul-, Remember- und Sessionzustand

`request_context`, `get_request_var`, `get_json_request`, `append_url_params`,
`get_system_var`, `set_system_var`, `get_modul_var`, `set_modul_var`,
`get_remember_var`, `set_remember_var`, `get_session_var`, `set_session_var`,
`delete_session_var`, `delete_cookie`

Diese APIs sind keine Synonyme: Systemzustand gilt für einen Request, Modulzustand
für eine Modulinstanz, Remember-Zustand requestübergreifend für UI/Benutzer und
Sessionzustand für explizit benannte Modulbereiche.

### Konfiguration

`get_cfg`, `set_cfg`, `patch_local_config`, `set_local_config_section`,
`get_config_cache`, `set_config_cache`, `clear_config_cache`,
`config_path_store`, `config_path_resolve`, `convert_array_to_php_code`

Lesen, vollständiges Schreiben, lokales Patchen, Abschnittsersetzung, Cachepflege
und portable Pfadauflösung bleiben bewusst getrennt.

### Sicherheit und Benutzer

`is_demo_mode`, `is_admin_bypass_active`, `action_token`, `check_action_token`,
`invalidate_action_tokens`, `action_url`, `user`, `has_group`,
`has_module_access`, `is_dbx_edit`, `login`, `new_password`, `validate_var`,
`is_access_denied`, `is_db_error`

`has_group` prüft Gruppenmitgliedschaft. `has_module_access` prüft zusätzlich die
Konfiguration eines konkreten Moduls und setzt bei Ablehnung den Modulfehlerkontext.
Darum sind beide fachlich verschieden.

### Assets, Editor und Design

`editor_file_path`, `register_editor_file`, `get_editor_files`, `editor_marker`,
`add_css`, `add_js`, `get_module_assets`, `get_design_skin_ids`, `normalize_skin`,
`get_skin`, `get_skin_css`, `get_skin_class`, `get_design_catalog`, `is_design`

CSS und JavaScript besitzen verschiedene Zielpfade. Skin-ID, CSS-Datei und
Body-Klasse sind getrennte Ausgaben derselben normalisierten Designwahl.

### Sprache

`lng_current`, `accessible_lngs`, `lng_name`, `lng_resolve_file`

### Pfade, Ausgabe und Infrastruktur

`get_base_url`, `get_self_url`, `get_base_dir`, `get_file_dir`, `os_path`,
`get_version`, `error_log_file`, `error_type`, `write_php_error_log`,
`json_response`, `redirect`, `send_mail`

URL, Dateisystempfad und aktueller Request sind absichtlich unterschiedliche
Werte. Fehlerpfad, Fehlertyp und Protokollschreiben werden auch vom Bootstrap
einzeln benötigt.

### Gemeinsame Kernel-Dienste

`timer`, `log_missing`, `sys_msg`, `next_id`, `norep`, `esc`, `search_defaults`,
`debug`, `get_content_permalink_mode`

Diese Funktionen haben jeweils eigene Seiteneffekte oder Ausgabeformate. Sie sind
nicht untereinander austauschbar. Eine spätere Verschiebung in spezialisierte
Services ist möglich, wäre aber keine Redundanzbeseitigung und wird deshalb nicht
mit einem Alias kaschiert.

## Dauerhafte Grenze

Der Vertrag `dbxApiSurface_contract_test.php` verhindert die entfernten Namen,
doppelte Methodennamen und ein unbeabsichtigtes Anwachsen über 85 Methoden. Neue
Fassadenmethoden benötigen damit entweder die Entfernung einer alten Verantwortung
oder eine bewusste Anpassung dieses Audits.
