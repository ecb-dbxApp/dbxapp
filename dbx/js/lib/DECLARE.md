# dbx.declare – Technische Referenz

Implementierung in `core.js`. Libs registrieren Schemas in ihrer JS-Datei.

## Ablauf

1. `dbx.scan(ctx)` → `dbx.declare.scanAutoload(ctx)` (Klassen)
2. `dbx.scan(ctx)` → `[data-dbx]` (komplexe Libs)
3. `dbx.runTasks()` → `resolveFeature` → Lib-`init`

## Autoload-Regeln (`scanAutoload`)

| Selektor | Lib |
|----------|-----|
| `form.dbxAjax` | ajax, form |
| `.dbx-ajax-root` | ajax |
| `form.dbxAjax` mit `.dbxConfirm` / `[data-confirm]` | confirm |
| `.dbx-win`, `.dbx-win-preload` | openWin |

## Schema registrieren

```javascript
dbx.declare.registerSchema("myLib", {
  fields: {
    target: {
      default: "",
      aliases: ["data-target", "data-ajax-target"],
      infer: function (el, ctx) { return dbx.declare.infer.ajaxTarget(ctx.root); },
      required: false
    }
  }
});

dbx.declare.transforms.myLib = function (raw, root) {
  return { /* normalisierte Config */ };
};
```

`dbx.declare.resolve(lib, root)` liefert Config-Array (gecacht als `_dbx{Lib}Configs`).

Reihenfolge pro Feld: `data-dbx` Pipe → `data-*` / Aliase → `infer` → `default`.

## Diagnose (`dbx.diag`)

```javascript
dbx.diag("warn", "ajax", "TARGET_MISSING", "Pflicht-Attribut fehlt", {
  el: formEl,
  field: "target",
  source: "missing"
});
```

| Level | Konsole (`log=on`) | SysMsg |
|-------|-------------------|--------|
| `info` | ja | nein |
| `warn` | ja | ja |
| `error` | ja | ja |

Format: `[dbx][lib][CODE] message @ #element field=… source=…`

PHP: `dbxSysMsg::client_diag()` → `dbx()->sys_msg($status, $lib, $code, $message, $detail)`.

## Registrierte Schemas

- **ajax** – `ajax.js`
- **confirm** – `confirm.js`
- **openWin** – `openWin.js`

## Infer-Helfer

- `dbx.declare.infer.ajaxTarget(root)` – `dbx_target_N` aus Form-ID
- `dbx.declare.infer.confirmRoot(el)` – nächstes `form.dbxAjax`

## API

```javascript
dbx._diagnostics          // alle Diagnose-Einträge
dbx.diag.format(entry)    // lesbare Zeile
dbx.diag.flushReports()   // SysMsg-Queue senden
dbx.declare.readField(el, lib, field, spec, ctx)
```
