# myInvoices-Testvertrag

Das Modul besitzt zwei automatisierte PHP-Tests und einen reproduzierbaren
Browserablauf.

```powershell
php dbx/modules/myInvoices/tests/myInvoices_contract_test.php
php dbx/modules/myInvoices/tests/myInvoices_integration_test.php
```

Der Integrationstest synchronisiert die DDs idempotent, prüft die
Demo-Fixtures, automatische Auditfelder, Snapshot- und Callback-Summen,
Token-Ablehnung, erfolgreichen Mehrtabellen-Delete und einen erzwungenen
Rollback nach dem ersten Löschschritt.

## Browserablauf

Mit lokalem Admin-Bypass:

```powershell
php -d auto_prepend_file=dbx/modules/dbxAdmin/tests/admin_bypass_prepend.php `
  -S 127.0.0.1:8131 -t C:/xampp/htdocs/dbxapp
```

1. `http://127.0.0.1:8131/?dbx_modul=myInvoices&dbx_run1=install` öffnen.
2. `[data-install-submit]` absenden und Erfolgsmeldung prüfen.
3. Report öffnen; drei Demo-Rechnungen und je einen eingebetteten
   `[data-invoice-items]`-Report prüfen.
4. Für `DBX-DEMO-1001` müssen die Zeilensummen `39,80 EUR`, `7,50 EUR` und
   die Endsumme `47,30 EUR` sichtbar sein.
5. Neues Kopfformular normal und per Ajax speichern.
6. Löschaktion öffnen, zuerst „Nein“ wählen und unveränderte Zeilenzahl
   prüfen; danach einen eigens angelegten Testdatensatz mit „Ja“ löschen.
7. Einen Delete-Link ohne oder mit falschem `dbx_token` aufrufen und
   unveränderte Daten plus Ablehnungsmeldung prüfen.

Die Selektoren sind absichtlich im realen `dbxTPL`-Markup definiert. Es gibt
keinen separaten Browser-API- oder Test-Controller.
