<?php
/**
 * Installationsbezogene Geheimnisse.
 *
 * Diese Datei als config.local.php kopieren. config.local.php wird durch
 * .gitignore ausgeschlossen und zur Laufzeit rekursiv ueber config.php gelegt.
 * Nur lokale beziehungsweise geheime Werte hier eintragen.
 */
$config['db']['dbxApp']['pass'] = '';
$config['db']['dbxTestCodex']['pass'] = '';
$config['ftp']['web']['sftp_pass'] = '';
$config['mail']['dbxApp']['pass'] = '';

/*
 * Der ausgelieferte Stand startet mit install=1. Erst der erfolgreich
 * abgeschlossene Assistent setzt den lokalen, updatesicheren Wert auf 0.
 * "internal" legt Mailereignisse nur im internen Systemprotokoll ab,
 * "external" erlaubt den konfigurierten Mailtransport.
 */
$config['install'] = 0;
$config['mail_delivery_mode'] = 'internal';

/*
 * Optional: einzelne DDs installationsbezogen auf einen anderen Server
 * binden. Nicht genannte DDs verwenden weiterhin ihren Server aus der DD.
 * DB3 und SQL-Server koennen beliebig gemischt werden.
 */
$config['dd_server_bindings']['dbx|dbxUser'] = 'dbxApp';
$config['dd_server_bindings']['dbx|dbxUser_groups'] = 'dbxUser.db3';
