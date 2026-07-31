# Installation, Updates und DD-Serverbindungen {#dbxapp_install_update_dd_bindings}

Dieses Kapitel ist der verbindliche Betriebsvertrag für Neuinstallationen,
Release-Updates und Datenbankänderungen. Maßgeblich ist immer die lokale
Installation. Das Release-Verzeichnis ist eine daraus erzeugte,
geprüfte Veröffentlichung und keine zweite Entwicklungsquelle.

## Grundsatz: Die DD entscheidet, nicht die Datenbankart

Jede DD besitzt in `$table['server']` einen ausgelieferten Standardserver.
Eine Installation darf diesen Standard für jede einzelne DD lokal
überschreiben. Dadurch sind in derselben Installation gleichzeitig möglich:

- DDs in verschiedenen `.db3`-Dateien;
- mehrere DDs in derselben `.db3`-Datei;
- einzelne DDs auf MySQL/MariaDB;
- weitere DDs weiterhin auf SQLite;
- DDs verschiedener Module auf unterschiedlichen SQL-Servern.

Fachmodule merken davon nichts. Sie verwenden weiterhin ausschließlich eine
DD-Referenz und `dbxDB`:

```php
$db = dbx()->get_system_obj('dbxDB');
$users = $db->select('dbx|dbxUser', array('trash' => 0));
$orders = $db->select('dbxShop|shopOrder', array('trash' => 0));
```

Direkter PDO-, SQLite- oder MySQL-Code im Modul, Installer oder in einer
Migration ist nicht zulässig.

## Auflösung einer Serverbindung

Die lokale Konfiguration liegt ausschließlich in:

```text
dbx/modules/dbx/cfg/config.local.php
```

`config.php` enthält nur den ausgelieferten Standard:

```php
$config['dd_server_bindings'] = array();
```

Eine lokale gemischte Installation kann beispielsweise so gebunden sein:

```php
$config['dd_server_bindings'] = array(
    'dbx|dbxUser' => 'dbxApp',
    'dbx|dbxUser_groups' => 'dbx|dbxUser.db3',
    'dbxShop|shopOrder' => 'dbxApp',
    'dbxShop|shopProduct' => 'dbxShop|dbxShop.db3',
);
```

`dbxApp` ist dabei der Name eines aktiven Eintrags in `$config['db']`.
Ein Wert wie `dbxShop|dbxShop.db3` ist ein DD-/DB3-Serverziel.

Die Auflösung ist eindeutig:

1. exakte lokale Bindung `modul|dd`;
2. kompatible ältere Bindung nur über den nackten DD-Namen;
3. andernfalls `$table['server']` aus der DD.

Eine vorhandene, aber ungültige lokale Bindung fällt bewusst nicht auf den
DD-Standard zurück. Sie wird als Konfigurationsfehler abgelehnt. So kann ein
Tippfehler niemals unbemerkt auf die falsche Datenbank schreiben.

Der aktuelle Zustand kann ohne Datenmutation geprüft werden:

```php
$info = $db->get_dd_server_binding_info('dbxShop|shopOrder');
```

Die Rückgabe nennt deklarierten und aufgelösten Server, Quelle und Gültigkeit.

## Administration

Unter **System → DD-Serverbindungen** listet `dbxAdmin` alle gefundenen DDs.
Jede DD kann unabhängig auf:

- ihren ausgelieferten DD-Standard;
- einen aktiven konfigurierten SQL-Server;
- ihr bisheriges explizites DB3-Ziel

gesetzt werden. Gespeichert werden nur Abweichungen. Passwörter und andere
lokale Datenbankwerte werden dabei nicht neu geschrieben.

Eine Zieländerung verschiebt keine Daten. Erst ein ausdrücklicher
Transfer-/Migrationsprozess darf Tabellen und Datensätze auf ein anderes Ziel
übertragen.

## Neuinstallation und Installationsschalter

Eine vollständige Installationsausgabe kann über dbxapp.de bereitgestellt
werden. Produktive `.db3`-Dateien aus der Entwicklungsinstallation gehören
nicht in ein öffentliches Quell- oder Updatepaket.

Eine Auslieferung startet mit:

```php
$config['install'] = 1;
```

Solange dieser Wert aktiv ist, wird jeder dynamische Seitenaufruf vor
Permalink-, Content-, Session-DB- und Seiten-Cache-Zugriffen verbindlich auf
`dbxSetup` und das eigenständige Installationsdesign geleitet. Nach
erfolgreichem Abschluss speichert der Assistent ausschließlich in der
lokalen, updategeschützten `config.local.php`:

```php
$config['install'] = 0;
```

Der Installer arbeitet in sieben nachvollziehbaren Schritten:

1. PHP-Version, notwendige Erweiterungen, Composer-Abhängigkeiten und
   Schreibrechte prüfen. Optionale Erweiterungen werden getrennt ausgewiesen.
2. Seitentitel, Markenname, Claim, Standardsprache, Zeitzone sowie Benutzer-
   und Admin-Design festlegen.
3. Zielmodell wählen: die vollständig ausgelieferten DB3-Dateien direkt
   verwenden, alle DDs auf einen geprüften PDO-Server oder vorhandene
   Einzelbindungen setzen. Unterstützte Installationsziele sind zunächst
   MySQL/MariaDB, PostgreSQL und Microsoft SQL Server. Vorhandene nicht geheime
   Serverwerte werden als Formularvorgaben übernommen; leere Passwortfelder
   bewahren ein bereits lokal gespeichertes Passwort.
4. Im DB3-Standard alle vorhandenen Tabellen ausschließlich lesend auf
   Vollständigkeit prüfen. Nur für ein gewähltes PDO-Ziel werden die DD-Schemas
   synchronisiert. Eine optionale, ausdrücklich bestätigte Übertragung kann
   vorhandene lokale DD-Tabellen erst danach auf das erreichbare SQL-Ziel
   kopieren.
5. Den verbindlichen Standardzugang `admin` / `admin` herstellen. Fehlt der
   Benutzer `admin`, wird er angelegt. Ist er bereits vorhanden, setzt der
   Installer sein Passwort bewusst auf `admin`, aktiviert und bestätigt das
   Konto und stellt die Admin-Rolle sicher. Andere Profildaten und nicht
   installationsbezogene Einstellungen bleiben erhalten. Der Login erzwingt
   vor Freigabe einer Sitzung ein persönliches, starkes Passwort.
6. Globalen E-Mail-Betrieb, Transport, SMTP-Zugang, sichtbaren Absender,
   Envelope-Absender und erlaubte Absenderdomains konfigurieren.
7. Eine geheimnisfreie Zusammenfassung bestätigen und die Installation
   lokal aktivieren.

Der Installer verändert keine vorhandenen Gruppen. Der Benutzer `admin` ist
eine bewusste Ausnahme: Jede bestätigte Installation stellt für dieses Konto
erneut `admin` / `admin` her und setzt `password_reset_required`. Das Passwort
wird mit `password_hash()` gespeichert. Die Erkennung des Standardpassworts
und das Kennzeichen führen in den geschützten Passwortänderungsdialog. Solange
der Hash noch zu `admin` passt, zeigt auch das Admin-Dashboard eine Warnung mit
direktem Link zur Passwortänderung. Wiederholtes Ausführen erzeugt keine
doppelten Seed-Datensätze.

### PDO-Migration

Eine Migration wird nur angeboten, nachdem Servertyp, Host, Datenbank,
Benutzer und Port erfasst wurden. Vor dem Speichern der DD-Bindungen muss eine
Verbindung direkt zur Zieldatenbank gelingen. Existiert die Datenbank noch
nicht, darf der Assistent sie nur nach ausdrücklicher Auswahl und mit einem
dafür berechtigten Konto anlegen; anschließend wird die Verbindung zur
angelegten Datenbank erneut geprüft.

Erst im folgenden, getrennt bestätigten Schritt werden Zieltabellen aus den DDs
erzeugt und – falls ebenfalls ausdrücklich gewählt – vorhandene DB3-Daten
übertragen. Ein Verbindungs-, Anlege- oder Prüfungsfehler beendet den Ablauf vor
jeder Schema- oder Datenmigration.

### Hilfe im Installationsassistenten

Schritt 3 bietet zwei direkt erreichbare `openWin`-Hilfen:

- **Mitgelieferte DB3 genau erklärt** beschreibt den unveränderten Lieferzustand
  von Tabellenstruktur und Fachdaten, die ausschließlich lesende
  Vollständigkeitsprüfung sowie das separate Zurücksetzen des Zugangs auf
  `admin` / `admin`.
- **PDO-Migration Schritt für Schritt** trennt Serverdaten, Verbindung,
  optionale Datenbankanlage, erneute Verbindungsprüfung, DD-Strukturaufbau und
  die ausdrücklich zu bestätigende Datenübertragung.

Beide Hilfen werden ohne Formularübermittlung geöffnet. Sie zeigen keine
Zugangsdaten und weisen klar darauf hin, dass ohne erfolgreiche Zielverbindung
keine Schema- oder Datenänderung startet.

### Globaler E-Mail-Betrieb

`$config['mail_delivery_mode']` gilt vor allen Modulkonfigurationen:

| Wert | Verhalten |
|---|---|
| `internal` | Kein Netzwerkversand. Empfänger, Absender und Betreff werden als internes Mailereignis in `dbxSysMsg` angenommen; Inhalt und Anhänge werden nicht gespeichert. |
| `external` | Der ausgewählte PHP-Mail-, Sendmail- oder SMTP-Transport darf Nachrichten versenden. |
| `disabled` | Das Mailereignis wird abgelehnt und die globale Sperre intern protokolliert. |

Der sichere Auslieferungsstandard ist `internal`. SMTP-Zugang und Absender
können bereits vollständig vorbereitet werden, während externer Versand noch
ausgeschaltet bleibt.

Der globale Profilabsender ist nur der Rückfallwert für allgemeine
Systemnachrichten. Geschäftsprozesse besitzen getrennte, lokal gespeicherte
From-Adressen:

| Prozess | Konfigurationswert | Vorgabe |
|---|---|---|
| Kontaktanfragen, Eingangsbestätigungen und Supportantworten | `dbxContact.mail_from` | `kontakt@dbxapp.de` |
| Bestellungen, Bestellstatus und Widerrufe | `dbxShop.mail_from` | `shop@dbxapp.de` |

Beide Werte sind in Installationsschritt 6 frei eingebbar und werden zusammen
mit `mail_profile = dbxApp` in der jeweiligen `config.local.php` gespeichert.
Das Kontaktformular verwendet die Adresse des Anfragenden ausschließlich als
`Reply-To`; dadurch bleibt der sichtbare Absender stabil und clientseitige
Mailregeln können Kontakt- und Shop-Prozesse zuverlässig unterscheiden. Ist
`force_from` im globalen Mailprofil aktiviert, ersetzt der Maildienst bewusst
beide Modulabsender durch den globalen From-Wert.

## Datenbankmigration eines Moduls

Eine Release-Migration liegt unter:

```text
dbx/modules/{modul}/migrations/*.migration.php
```

Minimales Beispiel:

```php
<?php

return array(
    'id' => 'my-module-4.1.0-order-index',
    'version' => '4.1.0',
    'description' => 'Bestellstruktur auf den neuen DD-Stand bringen.',
    'affected_dd' => array(
        'dbxShop|shopOrder',
        'dbxShop|shopOrderItem',
    ),
    'operations' => array(
        array('type' => 'sync_dd', 'dd' => 'dbxShop|shopOrder'),
        array('type' => 'sync_dd', 'dd' => 'dbxShop|shopOrderItem'),
    ),
);
```

Verbindliche Regeln:

1. Die ID ist dauerhaft eindeutig und wird nach Veröffentlichung nie geändert.
2. Die Versionsnummer ist die erste Release-Version, welche die Änderung
   benötigt.
3. Jede möglicherweise veränderte DD steht in `affected_dd`.
4. Schemaänderungen stammen aus der neuen DD und laufen als `sync_dd`.
5. Fachliche Datenänderungen verwenden ausschließlich das an `up` übergebene
   `dbxDB`-/`dbxDD`-Objekt.
6. Eine Migration wird niemals durch direkte SQL-Dateien oder PDO ergänzt.
7. Bereits ausgeführte Migrationen dürfen nicht nachträglich verändert werden;
   der SHA-256-Abgleich würde das Update absichtlich stoppen.

`sync_dd` verwendet standardmäßig den sicheren Modus `apply`: fehlende Felder
und Indizes werden ergänzt, ein notwendiger Tabellen-Rebuild stoppt dagegen
mit einer Fehlermeldung. Nur wenn der Release-Autor den bereits gesicherten
Rebuild bewusst geprüft hat, wird er explizit angefordert:

```php
array(
    'type' => 'sync_dd',
    'dd' => 'myModule|myRecord',
    'mode' => 'rebuild',
),
```

Ein optionales `up` erhält die systemweiten Fassaden:

```php
'up' => static function (object $db, object $dd): void {
    $db->update(
        'myModule|myRecord',
        array('status' => 'active'),
        array('status' => '')
    );
},
```

Für wiederkehrende Seeds ist ein idempotenter Installationsservice zu
verwenden. Migrationen dürfen keine lokalen Servernamen voraussetzen.

## Update-Ablauf

```text
Release prüfen
  -> Manifest, Version, PHP, SHA-256 und ZIP-Pfade prüfen
  -> Paket in einen abgeschotteten Stagingbereich entpacken
  -> Benutzer kann das vorbereitete Update stoppen
  -> betroffene Dateien und jede betroffene DD-Tabelle sichern
  -> neue Dateien installieren
  -> ausstehende DD-Migrationen auf ihren lokal gebundenen Servern anwenden
  -> neue VERSION bestätigen
  -> Erfolg protokollieren
```

Bis zum Start der Installation kann der Benutzer das vorbereitete Update
vollständig stoppen. Der danach gestartete kritische DB-/Dateischritt ist
bewusst nicht teilunterbrechbar. Ein Fehler löst automatisch den gemeinsamen
Rollback von Datenbanktabellen und Dateien aus.

Vor einer Migration wird jede betroffene DD erneut aufgelöst. Die Sicherung
wird nach dem tatsächlich verwendeten Server gruppiert. Damit bleibt ein
Update korrekt, wenn beispielsweise Benutzer und Bestellungen auf MySQL,
Content und Sessions aber in verschiedenen DB3-Dateien liegen.

## Schutz gegen unbeabsichtigte Serverwechsel

Ändert ein Release den in einer DD ausgelieferten `$table['server']`, darf das
Update eine bestehende Installation nicht stillschweigend auf das neue Ziel
umlenken. Ohne explizite lokale Bindung wird der Updatevorgang abgelehnt.

Der Betreiber entscheidet dann bewusst:

1. bisheriges Ziel als lokale DD-Bindung festschreiben; oder
2. Daten mit dem vorgesehenen Transferprozess auf das neue Ziel übertragen und
   erst danach die Bindung ändern.

Das gilt auch für DB3-zu-MySQL- und MySQL-zu-DB3-Wechsel.

## Migrations-Ledger und Rollback

Ausgeführte Migrationen werden in `dbxAdmin|dbxMigration` mit ID, Version,
Modul, Checksum, Status, betroffenen Servern und Sicherungsreferenz
protokolliert.

Vor dem ersten Schreibschritt werden Schema, Indizes und Daten jeder
betroffenen existierenden Tabelle gesichert. Beim Rollback werden:

- neu angelegte Tabellen entfernt;
- frühere Tabellenstrukturen und Indizes wiederhergestellt;
- gesicherte Daten zurückgespielt;
- der Migrationsstatus als zurückgerollt markiert;
- geänderte Programmdateien aus derselben Updatesicherung wiederhergestellt.

## Release-Verantwortung

Die Entwicklung findet in `C:\xampp\htdocs\dbxapp` statt. Erst ein bewusster
Release-Schritt synchronisiert den geprüften Quellstand nach
`C:\xampp\htdocs\dbxapp-github`, erstellt Inventar, ZIP, Prüfsummen und
Manifest und veröffentlicht anschließend GitHub.

Installationsdaten und lokale Konfiguration werden nie von GitHub in eine
bestehende Installation kopiert. Updates liefern Code, DDs und versionierte
Migrationen. Die lokale DD-Serverbindung bleibt die Wahrheit der jeweiligen
Installation.

## Abnahmematrix

Vor Veröffentlichung eines Releases müssen mindestens bestanden sein:

1. DD-Auflösung ohne Binding, mit DB3-Binding und mit SQL-Alias;
2. parallele Auflösung zweier DDs auf unterschiedliche Serverarten;
3. ungültige Bindung wird abgelehnt;
4. `install=1` erreicht den Assistenten ohne vorherigen DD-, Permalink- oder
   Seiten-Cache-Zugriff;
5. notwendige Systemprüfungen blockieren den nächsten Schritt, optionale
   Prüfungen bleiben klar als Empfehlungen erkennbar;
6. Neuinstallation, erneuter Seitenaufruf und wiederholte Seeds erzeugen keine
   doppelten Datensätze; der Benutzer `admin` wird bei Bestätigung von Schritt 5
   reproduzierbar auf `admin` / `admin`, Admin-Rolle, aktiv, bestätigt und
   erzwungenen Passwortwechsel gesetzt; leere Passwortfelder für bestehende
   Server- und Mailzugänge bleiben unangetastet;
7. unveränderte, rein lesend geprüfte DB3-Tabellenstruktur und Fachdaten sowie
   PDO-Installation:
   ohne erreichbare und vorhandene beziehungsweise erfolgreich angelegte
   Zieldatenbank darf weder Schema- noch Datenmigration starten;
8. die globalen Mailmodi `internal`, `disabled` und `external`; insbesondere
   darf `internal` keinen Netzwerktransport starten und keinen Nachrichten-
   inhalt protokollieren;
9. deutsche Installationsoberfläche auf Desktop und Mobilgerät ohne
   horizontalen Seitenüberlauf; Englisch und Spanisch folgen erst nach
   fachlicher und gestalterischer Freigabe der deutschen Fassung;
10. Anmeldung mit `admin` / `admin` führt zwingend in den Passwortwechsel,
    falsche, schwache oder wiederverwendete Passwörter werden abgelehnt;
11. Migrationserkennung, Checksum-Ledger und bereits ausgeführte Migration;
12. Backup und Rollback über mindestens zwei verschieden gebundene Server;
13. gemeinsamer Datei-/DB-Rollback bei simuliertem Fehler;
14. bestehende `dbxDB`-, `dbxForm`-, `dbxReport`- und Update-Regressionstests.
