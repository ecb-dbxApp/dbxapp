# Workflow nutzen {#dbxapp_workflow_use}

Diese Seite erklärt die Bedienung eines dbXapp-Workflows vom Start bis zum
kontrollierten Abschluss. Sie richtet sich an Benutzer, Administratoren und
Tester. Das Erstellen einer Definition beschreibt @ref dbxapp_workflow_create.

## Workflow starten

Ein aktiver Workflow wird über seinen stabilen Workflow Key gestartet:

```text
?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=project_offer
```

Der Start erzeugt eine neue Workflow-Instanz. Ein erneuter Aufruf der
Start-Route erzeugt deshalb eine weitere Instanz und öffnet nicht automatisch
die vorherige. Gleichzeitig wird der aktuelle Definitionsstand gespeichert,
damit spätere Admin-Änderungen diesen Durchlauf nicht verändern.

Unbekannte und deaktivierte Workflow Keys werden mit einer Warnung abgelehnt;
dbxWorkflow startet dafür keinen Ersatz- oder Demo-Ablauf.

Soll ein vorhandener Fachdatensatz vorausgewählt werden, kann ein Binding den
Parameter `rid` übernehmen:

```text
?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=ticket_demo&rid=42
```

Direkter Aufruf einer vorhandenen Instanz:

```text
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17
```

Benutzer sehen nur Instanzen, für die sie durch Owner- oder Adminregeln
berechtigt sind.

## Oberfläche verstehen

Eine laufende Instanz zeigt:

- den Namen und das fachliche Ziel;
- Status und Gesamtfortschritt;
- alle Schritte in ihrer Reihenfolge;
- erledigte, aktuelle und gesperrte Schritte;
- die aktuelle Aufgabe mit Hinweis und Eingabemöglichkeit;
- Meldungen von automatischen Prüfungen und Fachmodulen;
- die Abschlussprüfung, sobald alle anwendbaren Schritte erledigt sind.

Die Schrittnavigation kann bei langen Workflows horizontal gescrollt werden.
Ein gesperrter Schritt nennt in seinem Tooltip die noch fehlende
Voraussetzung.

## Zustände eines Schritts

| Anzeige | Bedeutung |
| --- | --- |
| Aktuell | dieser Schritt kann jetzt bearbeitet werden |
| Erledigt | ein Wert oder eine gültige Bestätigung wurde gespeichert |
| Gesperrt | eine Voraussetzung ist noch nicht erfüllt |
| Optional | der Schritt kann bei Bedarf übersprungen werden |
| Automatik | das System versucht eine sichere Prüfung selbst auszuführen |

Erledigte, weiterhin anwendbare Schritte können erneut geöffnet werden. Eine
Änderung kann nachfolgende Entscheidungen beeinflussen. Deshalb sollte nach
einer Korrektur der restliche Ablauf erneut kontrolliert werden.

## Werte erfassen

### Einzelwert

Bei `mode = single` wird genau ein Wert ausgewählt oder eingegeben. Mit
**Übernehmen** speichert die Engine den Wert und wechselt zum nächsten
anwendbaren Schritt.

### Mehrfachauswahl

Bei `mode = multiple` können mehrere Optionen gewählt werden. Alle gewählten
Werte werden gemeinsam in der Instanz gespeichert.

### Optionalen Schritt überspringen

Ein optionaler Schritt kann übersprungen werden. Die Engine speichert dies
ausdrücklich als erledigten Schritt, damit der Ablauf nachvollziehbar bleibt.

### Fachmodul öffnen

Ein Modulschritt zeigt Links wie **Artikel bearbeiten**, **eBay-Mapping** oder
**Kontaktanfrage öffnen**. Das Fachformular wird im vorgesehenen dbXapp-Fenster
geöffnet. Danach gilt:

1. Daten im Fachmodul bearbeiten und dort speichern.
2. Fachfenster schließen oder zum Workflow zurückkehren.
3. Die im Workflow verlangte Bestätigung aktivieren.
4. Schritt mit **Übernehmen** abschließen.

Die Bestätigung ersetzt nicht die Validierung des Fachmoduls. Beim Finish
werden die entscheidenden Daten erneut geprüft.

## Entscheidungen und Zweige

Eine Entscheidung speichert einen eindeutigen Ergebniswert. Nur Schritte mit
passendem `depends_value` werden anschließend anwendbar.

```text
Freigabe = approved
  -> Auftrag ausführen wird geöffnet
  -> Rückfrage bearbeiten bleibt außerhalb des aktiven Zweigs
```

Bei einer späteren Änderung des Ergebnisses berechnet die Engine den aktiven
Zweig neu. Nicht gewählte Zweige werden nicht für den Abschluss verlangt.

## Automatische Prüfungen

Ein Schritt mit `automation = observe` wird ausgeführt, sobald er der nächste
anwendbare Schritt ist. Der Benutzer muss dafür keinen zusätzlichen Button
drücken.

Der Ablauf ist:

```text
vorherigen Schritt speichern
  -> Engine sucht nächsten Schritt
  -> observe-Prüfung fragt das Workflow-Fachmodul
  -> Ergebnis und Meldung werden gespeichert
  -> nächster manueller Schritt wird angezeigt
```

Kann die Automatik keinen sicheren Wert bestimmen, bleibt der Schritt manuell
bearbeitbar. Eine nicht erreichbare API darf den gesamten Workflow nicht ohne
verständliche Meldung abbrechen.

Automatische Meldungen sind fachlich relevant. Beispiel:

```text
Automatische Bereitschaftsprüfung: Bitte ergänzen: Kategorie-ID,
Payment-Policy und Return-Policy.
```

Der Benutzer korrigiert die genannten Daten im zuständigen Fachmodul und kann
die Prüfung danach erneut aufrufen.

## Beispiel: Artikel auf eBay veröffentlichen

Start:

```text
?dbx_modul=dbxWorkflow&dbx_run1=start&workflow=shop_ebay_publish
```

Der aktuelle Ablauf besteht aus:

1. **Artikel auswählen** – vorhandenen Shopartikel festlegen.
2. **eBay-Bereitschaft automatisch prüfen** – Channel, Zuordnung,
   Zugangsdaten, Kategorie, Policies, SKU und Titel kontrollieren.
3. **eBay-Daten bearbeiten und bestätigen** – Artikelpflege und eBay-Mapping
   öffnen und fehlende Angaben ergänzen.
4. **Export durchführen** – die externe Veröffentlichung bewusst bestätigen.
5. **Statusmeldungen prüfen** – vorhandenen Connectorstatus automatisch
   auswerten; bei fehlender eindeutiger Rückmeldung manuell prüfen.
6. **eBay-Angebot ansehen** – optionalen Ergebnislink kontrollieren.
7. **Abschluss** – nur bei erfolgreichem Exportstatus und vorhandener
   Listing-ID abschließen.

Der Export ist absichtlich nicht unsichtbar automatisiert. Vorprüfung und
Statusauswertung sind automatisch, die externe Veröffentlichung bleibt eine
sichtbare Benutzeraktion. Dadurch führen Seitenaufrufe oder Wiederholungen
nicht versehentlich zu einem erneuten Angebot.

## Fortschritt

Der Prozentwert bezieht auch spätere, noch gesperrte Schritte ein. Sobald eine
Entscheidung gefallen ist, werden nicht gewählte Zweige aus dem anwendbaren
Gesamtumfang entfernt.

Beispiel mit sechs Schritten:

```text
2 erledigt / 6 relevante Schritte = 33 %
```

Ein Workflow erhält erst nach erfolgreichem Finish den Status `finished` und
100 Prozent. Ein sichtbarer Fortschritt ist deshalb keine fachliche
Erfolgsmeldung.

## Pause, Fortsetzen, Abbrechen und Neustarten

Prozessbefehle werden an dieselbe Instanz gesendet:

```text
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17&proc_cmd=pause
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17&proc_cmd=resume
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17&proc_cmd=cancel
?dbx_modul=dbxWorkflow&dbx_run1=run&iid=17&proc_cmd=restart
```

| Befehl | Wirkung |
| --- | --- |
| Pause | Bearbeitung anhalten, Werte erhalten |
| Fortsetzen | pausierte Instanz wieder öffnen |
| Abbrechen | Instanz kontrolliert beenden |
| Neustart | neue Bearbeitung nach den vorgesehenen Regeln beginnen |

Bei externen Aktionen muss vor einem Neustart geprüft werden, ob die Aktion
beim Provider bereits erfolgreich war. Die lokale Instanz ist nicht immer die
einzige Wahrheit.

## Review und Abschluss

Sind alle anwendbaren Schritte erledigt, wird **Abschluss** geöffnet. Das
Review zeigt die erfassten Ergebnisse und das geplante Ziel.

Beim Bestätigen führt die Engine die Finish-Logik aus. Je nach Definition kann
sie:

- nur die Instanz abschließen;
- einen Fachdatensatz über ein Binding aktualisieren;
- eine Nachricht nach erfolgreicher Fachoperation versenden;
- einen Shop- oder Providerstatus erneut kontrollieren;
- den Abschluss mit einer verständlichen Fehlermeldung verweigern.

Ein fehlgeschlagener Export, eine fehlende Listing-ID oder ein ungültiger
Fachdatensatz dürfen nicht als erfolgreich abgeschlossen gespeichert werden.

## Laufende Instanzen verwalten

Administratoren finden alle Instanzen hier:

```text
?dbx_modul=dbxWorkflow_admin&dbx_run1=instances
```

Der Report zeigt Startzeit, Workflow Key, Ziel, Status, aktuelle Aufgabe,
Fortschritt, letzte Meldung und eine Aktion zum Fortsetzen oder Ansehen.

Die Schritt-Historie in `dbxWorkflow|workflowStep` dokumentiert:

- Instanz und Position;
- internen Schritt-Key;
- manuelle Aktion, Überspringen oder Automatik;
- gespeicherten Wert;
- Meldung und Bearbeiter;
- Abschlusszeitpunkt.

Diese Historie erleichtert Support und Fehlersuche, ersetzt aber kein
fachliches Audit-Log, wenn gesetzliche Aufbewahrungsregeln gelten.

## Typische Probleme

### Nächster Schritt bleibt gesperrt

- Prüfen, ob der vorherige Pflichtschritt wirklich übernommen wurde.
- Bei Verzweigungen gespeicherten Wert und `depends_value` vergleichen.
- Kontrollieren, ob die Abhängigkeit im Designer vor dem Zielschritt liegt.

### Automatik läuft nicht

- Der Schritt muss der nächste anwendbare Schritt sein.
- `automation` muss `observe` sein.
- Das Fachmodul muss für diesen Workflow Key und Need einen Wert liefern.
- Provider- oder Datensatzmeldung im Workflow lesen.

### Fortschritt wirkt unerwartet

- Gesperrte spätere Schritte gehören bis zur Entscheidung zum Gesamtumfang.
- Nicht gewählte Zweige werden erst nach der Entscheidung entfernt.
- Optionale Schritte werden als erledigt gespeichert, wenn sie übersprungen
  wurden.

### Finish wird verweigert

- Review-Meldung vollständig lesen.
- Fachdatensatz und externe Rückmeldung prüfen.
- Bei eBay insbesondere Exportstatus und Listing-ID kontrollieren.
- Fehler im Fachmodul beheben und Abschluss erneut versuchen.

## Bedien- und Testcheckliste

- Richtiger Workflow Key wurde gestartet.
- Keine unbeabsichtigte zweite Instanz über die Start-Route erzeugt.
- Aktuelle Aufgabe und Ziel sind verständlich.
- Pflichtwerte wurden gespeichert, optionale Schritte bewusst bearbeitet oder
  übersprungen.
- Automatische Meldungen wurden gelesen und fachlich geprüft.
- Modulformulare wurden tatsächlich gespeichert.
- Vor externer Aktion wurden Daten und Zielsystem kontrolliert.
- Review enthält die erwarteten Werte.
- Finish meldet fachlichen Erfolg und nicht nur 100 Prozent UI-Fortschritt.
- Bei Problemen wurden Instanzreport und Schritthistorie geprüft.

## Weiterführende Seiten

- @ref dbxapp_workflow_create
- @ref dbxapp_workflow_guide
- @ref dbxapp_shop_guide
- @ref dbxapp_module_patterns
