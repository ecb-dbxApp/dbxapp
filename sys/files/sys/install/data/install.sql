-- Status:12:437:MP_0:abtest:php:1.24.4::10.4.21-MariaDB:1:::utf8:EXTINFO
--
-- TABLE-INFO
-- TABLE|dbx_adminmsg|0|1024|2021-11-26 13:17:21|MyISAM
-- TABLE|dbx_de_content|9|32232|2021-11-26 13:17:21|MyISAM
-- TABLE|dbx_de_content_folder|4|2264|2021-11-26 13:17:21|MyISAM
-- TABLE|dbx_missing|0|4096|2021-12-06 15:22:07|MyISAM
-- TABLE|dbx_my_analyse|0|1024|2021-12-06 15:22:12|MyISAM
-- TABLE|dbx_my_befund|0|1024|2021-12-06 15:22:17|MyISAM
-- TABLE|dbx_my_testdata|415|29856|2021-11-26 13:17:42|MyISAM
-- TABLE|dbx_session|0|1024|2021-12-06 15:21:59|MyISAM
-- TABLE|dbx_trace|0|1024|2021-11-26 13:17:42|MyISAM
-- TABLE|dbx_trash|0|1024|2021-11-26 13:17:42|MyISAM
-- TABLE|dbx_user|3|2744|2021-12-06 13:35:37|MyISAM
-- TABLE|dbx_user_groups|6|2492|2021-11-26 13:17:42|MyISAM
-- EOF TABLE-INFO
--
-- Dump by MySQLDumper 1.24.4 (http://mysqldumper.net)
/*!40101 SET NAMES 'utf8' */;
SET FOREIGN_KEY_CHECKS=0;
-- Dump created: 2021-12-06 15:23

--
-- Create Table `dbx_adminmsg`
--

DROP TABLE IF EXISTS `dbx_adminmsg`;
CREATE TABLE `dbx_adminmsg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `xuser` int(11) NOT NULL DEFAULT 0,
  `level` int(11) NOT NULL,
  `message` varchar(254) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `status` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_adminmsg`
--

/*!40000 ALTER TABLE `dbx_adminmsg` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_adminmsg` ENABLE KEYS */;


--
-- Create Table `dbx_de_content`
--

DROP TABLE IF EXISTS `dbx_de_content`;
CREATE TABLE `dbx_de_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL,
  `activ` int(1) NOT NULL DEFAULT 1,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  `template` varchar(254) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `addmenu` varchar(254) COLLATE utf8_unicode_ci NOT NULL,
  `class` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `target` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `folder` int(11) NOT NULL DEFAULT 0,
  `group_read` varchar(128) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sorter` varchar(4) COLLATE utf8_unicode_ci DEFAULT NULL,
  `title` varchar(254) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `permalink` varchar(254) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(254) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `keywords` varchar(254) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `hits` int(11) NOT NULL DEFAULT -1,
  `data` int(1) NOT NULL DEFAULT 0,
  `modules` int(1) NOT NULL DEFAULT 0,
  `thesar` text COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `xvote` int(11) NOT NULL DEFAULT 0,
  `vote` decimal(3,2) NOT NULL DEFAULT 0.00,
  `vote1` int(6) NOT NULL DEFAULT 0,
  `vote2` int(6) NOT NULL DEFAULT 0,
  `vote3` int(6) NOT NULL DEFAULT 0,
  `vote4` int(6) NOT NULL DEFAULT 0,
  `vote5` int(6) NOT NULL DEFAULT 0,
  `lastuservote` int(11) NOT NULL DEFAULT 0,
  `upload1` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `upload2` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `upload3` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `upload4` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `upload5` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `upload6` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `sorter` (`folder`,`sorter`)
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_de_content`
--

/*!40000 ALTER TABLE `dbx_de_content` DISABLE KEYS */;
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('2','2008-04-08 14:52:55','2','2021-07-07 11:17:22','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test3','','','','3','1','','Impressum','/Impressum','','impressum test 2','-1','0','0','','<table class=\"\\\" border=\"\\\" width=\"\\\" align=\"\\\">\r\n<tbody>\r\n<tr class=\"\\\">\r\n<td>\r\n<h2><strong>Impressumccccccxz</strong></h2>\r\n</td>\r\n</tr>\r\n<tr>\r\n<td> </td>\r\n</tr>\r\n<tr>\r\n<td valign=\"\\\">\r\n<h1 class=\"\\\">Verantwortlich für diese Webseite:</h1>\r\n<br />Armin Leonard Braun<br />Informatiker & DVK<br />Kirnberger Str. 6a<br />64297 Darmstadt<br /><br />06151 / 67 92 700<br />webmaster@pceinfach.de<br /><br /><strong> dbXwebApp</strong> ist ein open-source Projekt von <a href=\"\\\" target=\"\\\">pceinfach.de<br /></a></td>\r\n</tr>\r\n<tr>\r\n<td valign=\"\\\">\r\n<h1 class=\"\\\">Lizenz:</h1>\r\n<br /><strong>db<span class=\"\\\">X</span>webApp </strong>ist unter der <a href=\"\\\">GPL Lizenz</a> frei verfügbar. Das heisst u.a. dass Ihre Nutzungs- und Ã„nderungsrechte im Vordergrund stehen und dass der Quellcode offen zur Verfügung steht. Keine Gängelung des Nutzers, keine Geheimnisse. <br /><br />Für nahezu alle Anwendungsfälle ist die Nutzung der Software für sie kostenlos. Das gilt auch wenn Sie <strong>db<span class=\"\\\">X</span>webApp</strong> nutzen und mit eigenen Modulen erweitern um dadurch eine komerzielle Anwendung zu realisieren. Es besteht aber die Pflicht eines Backlinks nach <a title=\"\\\"PHP\" href=\"\\\"http:/www.dbxwebapp.org\\\"\" target=\"\\\"_blank\\\"\"><strong>http://www.dbxwebapp.org</strong>, welcher in allen Design-Templates zu setzen ist.<br /><br /><strong> </strong><br /><small>Spezielle Module und AddOns können anderen Lizenzbedingungen unterliegen.<br /><br /></small></td>\r\n</tr>\r\n<tr>\r\n<td valign=\"\\\">\r\n<h1>Unsere Leistung:</h1>\r\n<br />Wir stellen das System <strong><strong>dbx<span style=\"color: 0#ff0000\\;;\">E-Mail.<br /><br /><br />Diese Seite ist optimiert für eine Auflösung von mind. 1024x768 in TrueColor sowie für den <strong>Firefox</strong> Browser .<br /><br /></span></strong></strong><hr size=\"\\\" width=\"\\\" /><strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>1. Inhalt des Onlineangebotes</u></strong><br />Der Autor übernimmt keinerlei Gewähr für die Aktualität, Korrektheit, Vollständigkeit oder Qualität der bereitgestellten Informationen. Haftungsansprüche gegen den Autor, welche sich auf Schäden materieller oder ideeller Art beziehen, die durch die Nutzung oder Nichtnutzung der dargebotenen Informationen bzw. durch die Nutzung fehlerhafter und unvollständiger Informationen verursacht wurden sind grundsätzlich ausgeschlossen, sofern seitens des Autors kein nachweislich vorsätzliches oder grob fahrlässiges Verschulden vorliegt. Alle Angebote sind freibleibend und unverbindlich. Der Autor behält es sich ausdrücklich vor, Teile der Seiten oder das gesamte Angebot ohne gesonderte Ankündigung zu verändern, zu ergänzen, zu löschen oder die Veröffentlichung zeitweise oder endgültig einzustellen.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>2. Verweise und Links</u></strong><br />Bei direkten oder indirekten Verweisen auf fremde Internetseiten (\\\"Links\\\"), die außerhalb des Verantwortungsbereiches des Autors liegen, würde eine Haftungsverpflichtung ausschließlich in dem Fall in Kraft treten, in dem der Autor von den Inhalten Kenntnis hat und es ihm technisch möglich und zumutbar wäre, die Nutzung im Falle rechtswidriger Inhalte zu verhindern. Der Autor erklärt hiermit ausdrücklich, dass zum Zeitpunkt der Linksetzung die entsprechenden verlinkten Seiten frei von illegalen Inhalten waren. Auf die aktuelle und zukünftige Gestaltung, die Inhalte oder die Urheberschaft der gelinkten/verknüpften Seiten hat der Autor keinerlei Einfluss. Deshalb distanziert er sich hiermit ausdrücklich von allen Inhalten aller gelinkten/verknüpften Seiten, die nach der Linksetzung verändert wurden. Diese Feststellung gilt für alle innerhalb des eigenen Internetangebotes gesetzten Links und Verweise sowie für Fremdeinträge in vom Autor eingerichteten Gästebüchern, Diskussionsforen und Mailinglisten. Für illegale, fehlerhafte oder unvollständige Inhalte und insbesondere für Schäden, die aus der Nutzung oder Nichtnutzung solcherart dargebotener Informationen entstehen, haftet allein der Anbieter der Seite, auf welche verwiesen wurde, nicht derjenige, der über Links auf die jeweilige Veröffentlichung lediglich verweist.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>3. Urheber- und Kennzeichenrecht</u></strong><br />Der Autor ist bestrebt, in allen Publikationen die Urheberrechte der verwendeten Grafiken, Tondokumente, Videosequenzen und Texte zu beachten, von ihm selbst erstellte Grafiken, Tondokumente, Videosequenzen und Texte zu nutzen oder auf lizenzfreie Grafiken, Tondokumente, Videosequenzen und Texte zurückzugreifen. Alle innerhalb des Internetangebotes genannten und ggf. durch Dritte geschützten Marken- und Warenzeichen unterliegen uneingeschränkt den Bestimmungen des jeweils gültigen Kennzeichenrechts und den Besitzrechten der jeweiligen eingetragenen Eigentümer. Allein aufgrund der bloßen Nennung ist nicht der Schluß zu ziehen, dass Markenzeichen nicht durch Rechte Dritter geschützt sind! Das Copyright für veröffentlichte, vom Autor selbst erstellte Objekte bleibt allein beim Autor der Seiten. Eine Vervielfältigung oder Verwendung solcher Grafiken, Tondokumente, Videosequenzen und Texte in anderen elektronischen oder gedruckten Publikationen ist ohne ausdrückliche Zustimmung des Autors nicht gestattet.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>4. Datenschutz</u></strong><br />Sofern innerhalb des Internetangebotes die Möglichkeit zur Eingabe persönlicher oder geschäftlicher Daten (Emailadressen, Namen, Anschriften) besteht, so erfolgt die Preisgabe dieser Daten seitens des Nutzers auf ausdrücklich freiwilliger Basis. Die Inanspruchnahme und Bezahlung aller angebotenen Dienste ist - soweit technisch möglich und zumutbar - auch ohne Angabe solcher Daten bzw. unter Angabe anonymisierter Daten oder eines Pseudonyms gestattet.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>5.</u></strong> Downloads<br />Sollten sie sich innerhalb dieses Internetangebotes Dateien runterladen, so erfolgt die Nutzung der Programme/Skripte aus unserem Download-Verzeichnis auf eigene Gefahr. Für evtl. Schäden an EDV-Systemen & Co. übernehmen wir keinerlei Haftung.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<strong><strong><span style=\"color: 0#ff0000\\;;\"><br /><br /></span></strong></strong>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td>\r\n<table border=\"\\\" width=\"\\\" cellspacing=\"\\\" cellpadding=\"\\\" bgcolor=\"\\\">\r\n<tbody>\r\n<tr>\r\n<td><strong><u>6. Rechtswirksamkeit dieses Haftungsausschlusses</u></strong><br />Dieser Haftungsausschluss ist als Teil des Internetangebotes zu betrachten, von dem aus auf diese Seite verwiesen wurde. Sofern Teile oder einzelne Formulierungen dieses Textes der geltenden Rechtslage nicht, nicht mehr oder nicht vollständig entsprechen sollten, bleiben die übrigen Teile des Dokumentes in ihrem Inhalt und ihrer Gültigkeit davon unberührt.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<p><br /><br /><strong>Related Pages 4 dbXwebApp</strong><br /><a title=\"\\\" href=\"\\\" target=\"\\\">www.pceinfach.de</a> - <a title=\"\\\"php\" href=\"\\\"http:/www.phpcms-shop.org\\\"\" target=\"\\\"_blank\\\"\">www.phpcms-shop.org - <a title=\"\\\"content\" href=\"\\\"http:/www.content-management-webapp.org\\\"\" target=\"\\\"_blank\\\"\">www.content-management-webapp.org - <a title=\"\\\"php\" href=\"\\\"http:/www.phpcms-webapp.org\\\"\" target=\"\\\"_blank\\\"\">www.phpcms-webapp.org<br /><br /></p>\r\n<hr size=\"\\\" width=\"\\\" />','0','0.00','0','0','0','0','0','0','Armin.png','hot-girl.jpg','Jutta13.jpg','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('10','2007-10-04 12:52:41','2','2021-07-07 10:54:09','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','2','1','','Kernel','/Willkommen','','Keywords Keys','0','0','0','','<h2>dbXwebApp Kernel</h2><b>dbxWebApp.php</b> arbeitet als Application Controller. Alle Aufrufe laufen grundsätzlich über dieses Script.<br />\r\nIn diesem Script sind die Kern-Funktionen von <b>dbXwebApp</b> includiert.<br />\r\n<br />\r\nDie Scripte der gerade aktuell verwendetetn Module werden dynamisch eingebunden. Dadurch ist die ausführende<br />\r\nAnwendung immer nur so gross wie gerade nötig.<br />\r\n<br />\r\n<b>\r\nDer Kernel beinhaltet alle grundlegenden Funktionen für die Nutzung des Systems.</b><br />\r\n<br />\r\n<br />\r\n<ul>\r\n	<li><b>Kernel</b> (<b>dbXwebApp.php</b>)<br />\r\n	<ul>\r\n		<li>dbx_globals.php </li>\r\n		<li>dbx_init.php</li>\r\n		<li>dbx_install.php </li>\r\n		<li>dbx_session.php </li>\r\n		<li>dbx_cache.php </li>\r\n		<li>dbx_dbaction.php</li>\r\n		<li>dbx_datetime.php</li>\r\n		<li>dbx_report.php    </li>\r\n		<li>dbx_run.php</li>\r\n		<li>dbx_interpreter.php</li>\r\n		<li>dbx_output.php</li>\r\n	</ul>\r\n	</li>\r\n</ul>\r\n<br />\r\nDer Kernel Übernimmt alle Datenbank Zugriffe. Dabei sorgt er für die Prüfung der Berechtigungen.<br />\r\nAuch sorgt der Kernel dafür das Inhalte gecahed werden und je nach Art der Ausgabe formatiert werden.<br />\r\n<br />\r\n<b>\r\nModule nutzen durch direkten Aufruf der im Kernel definierten Funktionen diese Möglichkeiten.<br />\r\nModulle müssen aber keine dieser Funktionen nutzen und können auch völlig \\\"eigenständig\\\" sein.<br />\r\n</b>\r\n<br />\r\nBesonders vor zu heben ist dabei der Umgang mit Listen, Formularen und Daten im Allgemeinem.<br />\r\nNahezu alle Inhalte und auch Teile der Programmlogik kommen bei dbxWebApp aus der Datenbank.<br />\r\nDiese Inhalte und Funktionsaufrufe lassen sich jeder Zeit <b>online erstellen/ändern</b>.<br />\r\n<br />\r\nFür alle Inhalte und Module gibt es in <b>dbXwebApp</b> eine <b>einheitliche Verwaltung</b> bei der jedem Modul,<br />\r\njeder Tabelle und jedem Feld individuelle Eigenschaften zugewiesen werden können.<br />\r\n<br />\r\nFür alle Datenbank-Tabellen und deren Felder legt <b>dbXwebApp</b> jeweils ein <b>DataDictonary</b> an.<br />\r\nDie Zugriffe auf Daten erfolgt durch die eingebauten Funktionen über diese DataDictonarys.<br />\r\nIn diesen DataDictonarys kann z.B. auch festgelegt werden für welche Felder Auswahllisten oder Checkboxes<br />\r\nautomatisch erstellt werden sollen. Auch eine Validierung der Daten kann im DataDictonary angegeben werden.<br />\r\n<br />\r\n<br />\r\nInnerhalb der Anwendung gibt es nahezu keine Zeile HTML-Code. Alles was HTML ist, ist bei <b>dbXwebApp</b> Content oder Template. Diese Inhalte kommen überwiegend aus der Datenbank. Können aber auch statisch vom Filesystem kommen.<br />\r\n<br />\r\nEin, zwei zusätzliche Spalten in einer Liste, eine andere Sortierung. Das Entfernen von Formularfeldern u.s.w. bedarf bei<br />\r\n<b>db<span class=\\\"red\\\">X</span>webApp</b> keinerlei Ã„nderungen an den PHP-Sourcen. Nahezu alles basiert auf Vorlagen und Inhalte.<br />\r\nDabei ist das Design komplett entkoppelt und wird durch eine <b>CSS</b> Datei gesteuert.<br />\r\n<br />\r\n<br />','0','0.00','0','0','0','0','0','0','','','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('15','2007-10-14 14:54:38','2','2021-07-07 12:03:29','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','2','4','a1','Funktionen API-x','test','','api, php, oop','0','0','0','','<h3>dbx-API</h3><br />\r\n<h2>dbXwebApp ist auch ein ein PHP rapid development  framework.</h2><br />\r\n<b>db<span class=\\\"red\\\">X</span>webApp </b>stellt etliche Funktionen für Standard Aufgaben zur Verfügung.<br />\r\n<br />\r\nAlle API Funktionen lassen sich auch ohne OOP  nutzen. Die  API Funktionen erstellen und nutzen bei Bedarf die benötigten Objekte  selbstständig.<br />\r\n<br />\r\nDadurch ist die Nutzung dieser Funktionen, auch innerhalb eigener <b>Klassen/Objekte</b>, sehr einfach.<br />\r\n<br />\r\nDas Einmischen der Daten, Template Funktionen, und das Einbinden von Modul-Aufrufen erfolgt automatisch.<br />\r\n<br />\r\n<br />\r\n<b>In der OOP $dbxapi gibt es aktuell folgende Funktionen:</b><br />\r\n<br />\r\n// - - - - - - - - - - -<br />\r\n<br />\r\nfunction get_error_count() {<br />\r\nfunction get_DataDictionary($dbxtab) {<br />\r\nfunction get_is_dbfield_exist($dbxtab,$field) {<br />\r\nfunction get_action_allowed($dbxtab,$action,$own) {<br />\r\nfunction get_select_record($dbxtab,$where,$sys=0) {<br />\r\nfunction get_save_record($dbxtab,$where,$pv,$sys=0) {<br />\r\nfunction get_delete_record($dbxtab,$where,$sys=0) {<br />\r\nfunction get_record($dbxtab,$where,$order=\\\"\\\",$UpDown=\\\"ASC\\\") {<br />\r\nfunction get_validate($dbxtab,$dv) {<br />\r\nfunction get_multi_records($dbxtab,$where,$order=\\\"\\\",$UpDown=\\\"ASC\\\",$limit=0) {<br />\r\nfunction get_select_multi_records($dbxtab,$where,$order=\\\"\\\",$UpDown=\\\"ASC\\\",$group=\\\"\\\",$limit=0) {<br />\r\nfunction get_multi_record($dbxtab) {<br />\r\nfunction get_next_multi_record($dbxtab) {<br />\r\nfunction get_count_select($dbxtab,$where=\\\"\\\") {<br />\r\nfunction get_last_insert_id($dbxtab) {<br />\r\nfunction table_sql($dbxtab,$sql) {<br />\r\nfunction get_update_dbField($dbxtab,$field,$fielddef,$oldname=\\\"\\\") {<br />\r\nfunction get_server_sql($server,$sql) {<br />\r\nfunction get_Report($report_modul,$dbxtab,$where,$order,$sort,$limit=0,$rpos,$template,empty,$group,0,$sys) {<br />\r\nfunction payment_check() { <br />\r\nfunction get_timediff($Start_Datum,$End_datum) {<br />\r\nfunction get_is_valid_date($in_date) {<br />\r\nfunction get_correct_date($in_date) {<br />\r\nfunction get_correct_sys_date($sys_date) {<br />\r\nfunction get_correct_sys_date_time($sys_date_time) {<br />\r\nfunction get_count_down($Start_Datum,$Ende_Datum,$days=1) {<br />\r\nfunction get_day_number($date) {<br />\r\nfunction calc_date_time($datum_start,$dauer) {  <br />\r\nfunction get_date_calc_day($date,$calc) {<br />\r\nfunction get_calc_day($year=0,$month=0,$day=0,$calc=0) {<br />\r\nfunction get_calc_month($year,$month,$day,$calc) {<br />\r\nfunction get_calc_year($year,$month,$day,$calc) {<br />\r\nfunction get_week($year,$month,$day) {<br />\r\nfunction get_day_of_week($year, $month, $day) {<br />\r\nfunction get_first_day_of_week($year, $month, $day) {<br />\r\nfunction get_last_day_of_week($year, $month, $day) {<br />\r\nfunction get_last_day_of_month($year, $month) {<br />\r\nfunction get_monthName($month) {<br />\r\nfunction get_dayname($wday) {<br />\r\nfunction get_day_differenz($from,$to) {<br />\r\nfunction convert_dates($db_tab,$io) {<br />\r\n<br />\r\nZusätzlich gibt es noch etliche Funktionen die global im Kernel definiert sind.<br />\r\n<br />\r\n<br />\r\n<b>Beispiel</b>, API-Funktion:<br />\r\n<br />\r\nWenn Sie in Ihrem Content (Inhalt) z.B die Anzahl aller Mitglieder anzeigen möchten dann nehmen Sie dafür einen beliebigen Platzhalter.<br />\r\n<b>{anzahl_mitglieder}</b><br />\r\nDieser Platzhalter* wird dann definiert und mit einem Wert ersetzt durch eine einfache Anweisung.<br />\r\n<br />\r\n$_av[\\\'<b>anzahl_mitglieder</b>\\\']=dbx_count_Select(\\\"dbx_user\\\");<br />\r\n<br />\r\nDiese Anweisung kann im Modul als PHP  Befehl stehen, oder auch im verwendeten Template in der on_read  oder on_report  Anweisung.<br />\r\n<br />\r\n<br />\r\nDurch die Kombination von Modul-Aufrufen und aktiven Templates ist das System extrem flexibel.<br />\r\nDurch die diversen automatischen Funktionen ist es sehr einfach komplexe Datenstrukturen zu bearbeiten oder anzuzeigen.<br />\r\n<br />\r\n<br />\r\n<br />\r\n<b>\r\n*</b> <i>Alle Platzhalter in dbXwebApp stehhen immer in geschwiften Klammern {platzhalter_name}</i><br />\r\n* <i>Alle Modul- und Funktionsaufrufe innerhalb von Templates stehen immer in eckigen Klammern [modul= ...]</i><br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />','0','0.00','0','0','0','0','0','0','Armin.png','avatar.jpg','Weihnachten.jpg','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('16','2008-02-26 16:19:35','2','2021-07-07 12:04:50','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','-','_self','1','4','a1','Templates x','test','','template,css,xhtml,xml,ajax','-1','1','1','','<h2>dbXwebApp Templates</h2>\r\n<p><br />Die Template Engine von <strong>db<span class=\"\\\"red\\\"\">X</span>webApp </strong>unterscheidet sich von den meisten anderen Systemen grundlegend.<br />Nahezu Alles, was HTML ist, wird bei <strong>db<span class=\"\\\"red\\\"\">X</span>webApp</strong> durch Templates zur Verfügung gestellt.<br /><br />In <strong>db<span class=\"\\\"red\\\"\">X</span>webApp </strong>bestehen alle <strong>Templates </strong>aus purem <strong>HTML </strong>/ <strong>xHTLM</strong>. Es gibt nur ein paar Befehle die innerhalb des HTML Inhaltes <strong>nur </strong>in Ausnahmefällen angegeben werden können. <br /><br />Eine wesentliche Eigenschaft vom System ist es, ein Template in Bereiche einteilen zu können.<br />So werden z.B. alle Listen jeweils durch nur ein Template definiert. Das Template kann dann in Header / Body und Footer Bereich aufgeteilt werden. Jedes Template lässt sich im online Editor bearbeiten.<br /><br />Der Listengenerator gibt dann zuerst den Header-Bereich, dann n mal den Body-Bereich und zuletzt den Footer-Bereich aus.<br />In jedem Bereich können wieder beliebige Modulaufrufe (auch Listen) und auch beliebiige weiter Templates \\\"includiert\\\" werden.<br /><br /></p>\r\n<h2>Grundsätzlicher Ablauf vom System:</h2>\r\n<p> </p>\r\n<ol>\r\n<li>Über GET oder POST kann ein Modul aktiv aufgerufen werden.Â Wenn kein Modul angegeben wurde, wird automatisch dbx_home aktiviert.</li>\r\n<li>Das jeweils aufgerufene Modul kann festlegen welches Design-Template genutzt werden soll. <br />Ansonsten wird das Design-Template mit dem Namen vom aktiven Modul genommen, falls über den Parameter dbx_template kein anderes Design-Template angegeben wurde. Falls das jeweils so ermittelte Design-Template nicht vorhanden ist, wird automatisch das Design-Template default.htm genutzt.</li>\r\n<li>Im Design-Template wird der Platzhalter {--modul_content} durch den Rückgabewert des jeweils aktiv aufgerufene Moduls ersetzt.</li>\r\n<li>Das Design Template wird interpretiert.<br />Alle Modulaufrufe [-..] werden mit den Rückgabewerten der jeweiligen Module ersetzt.<br />Alle Platzhalter {-...} werden mit Ihren Werten ersetzt</li>\r\n<li>Dar Inhalt des jeweilige Design-Template inclusiv aller Erstzungen wird an den Browser übertragen.</li>\r\n</ol>\r\n<p><br />Jeder Aufruf, jedes Modul, kann ein unterschiedliches Design-Template nutzen. Für PopUps, Druckausgabe, PDFs u.s.w. werden automatisch die entsprechenden Templates genutzt.<br /><br />Ausgaben von Modulen können <strong>immer</strong> und <strong>überall*</strong> dort eingefügt werden, wo sie dann auch erscheinen sollen. Alle Module können <strong>visuell</strong> als Bild in den Inhalt (Content) eingefügt werden. Diese Modul-Bilder sind \\\"Platzhalter\\\" und sie werden vom System durch die jeweilige Ausgabe der Module ersetzt. <br /><br />Dabei kann sowohl die Position als auch die Größe der Modulausgabe durch das Bild definiert werden. Auch können dabei alle benötigten Parameter mit angegeben werden.<br /><br />Für alle verwendeten db-Tabellen und deren Felder werden vom System <strong>automatisch</strong> die entsprechenden <strong>Platzhalter</strong> erstellt. Dabei wird automatisch berücksichtigt welche Inhalte (von db-Felder) der jeweilige Benutzer sehen/bearbeiten darf. Weiter benötigte Platzhalter können jeder Zeit einfach definiert werden.<br /><br />Bei <strong>db<span class=\"\\\"red\\\"\">X</span>webApp</strong> können Vorlagen, je nach Verwendung, in der Datenbank oder im Dateisystem gespeichert werden.<br /><br />Alle Vorlagen, die aus der Datenbank kommen, können selbstständig Anweisungen durchführen. Diese <strong>aktiven</strong> Vorlagen <strong>reagieren</strong> auf verschiedene <strong>Ereignisse</strong>. Sie übersetzen sich selbstständig (anhand der Übersetzungs-Tabelle) und können z.B. benötigte Auswahllisten bei Bedarf erstellen.<br /><br /><strong>Ansicht Template bearbeiten für eine Liste (Report):</strong><br /><br /></p>\r\n<div align=\"\\\"center\\\"\">Bei Verwendung dieses Templates als Report werden die eingetragenen Funktionen <br />bei On_report für jeden Datensatz automatisch ausgeführt. </div>\r\n<div><img title=\"\\\"Actives\" src=\"\\\"upload/web/Image/editor_on_report.png\\\"\" alt=\"\\\"Actives\" /></div>\r\n<div align=\"\\\"center\\\"\">Die Liste, die mit diesem Template generiert wird, können Sie im Bereich <a href=\"\\\"dbxWebApp.php/dbx_modul/dbx_download/op/liste\\\"\">Downloads</a> sehen</div>\r\n<h2> </h2>\r\n<p><strong>Ansicht Template bearbeiten für eine Eingabeformular (Adressdaten):<br /><br /></strong></p>\r\n<div><img title=\"\\\"Template\" src=\"\\\"upload/web/Image/TemplateFormular.png\\\"\" alt=\"\\\"Template\" width=\"\\\"840\\\"\" height=\"\\\"529\\\"\" /></div>\r\n<p><br /><strong><br /><br /></strong></p>\r\n<h2><strong><strong>Anweisungen die innerhalb eines Templates stehen können.</strong></strong></h2>\r\n<p><strong><strong><br />1. In jedem Template können beliebig viele Modul und Funktionsaufrufe im Inhalt stehen.<br /></strong></strong>[--modul=modul_name]parameter1=wert1¶meter2=wert2&override=1¶meter3=wert3[-/modul]<br />Alle Parameter die vor override=1 stehen werden statisch an das Modul übergeben. Alle Parameter nach override=1 können durch die entsprechenden POST oder GET Parameter überschrieben werden. <br /><br />Innerhalb jeder Modulausgabe können wiederum beliebig viele Modulaufrufe stehen, die dann vom System ausgewertet werden.<strong><strong><br /><br /><br /></strong></strong></p>\r\n<h3><strong><strong>Die folgenden Funktionen werden nur in Ausnahmefällen genutzt. </strong></strong></h3>\r\n<p><br />In den meisten Fällen wird die logische Steuerung und das Ausführen von Funktionen durch die jeweiligen Module komplett selbst übernommen. Innerhalb der Module gibt es ein paar sehr einfache Befehle, welche die Nutzung von Templates sehr einfach machen.<br /><strong><strong><br />2. In Inhalten und Templates können weitere Templates an jeder beliebigen Stelle includiert werden.<br /></strong></strong>[--inc=123]dbx_page 123 vom aktivem Modul wird includiert[-/inc]<strong><strong><strong><br /></strong></strong></strong>[--inc=123,dbx_global]dbx_page 123 von der db-Tabelle dbx_pages_dbx_globals wird includiert[-/inc]<strong><strong><br /><br />3. Inhalte können auch nur einmalig includiert werden. Auch wenn diese Anweisung mehrfach im Inhalt steht wird die Page 123 nur 1 * includiert.<br /></strong></strong>[--inc_once=123]Template Page 123 wird includiert[-/inc]<strong><strong><br /><br />4. Content includieren, ist ein Alias für [--modul=dbx_content]cid=123&op=show[-/modul]<br /></strong></strong>[--cid=123]Inhalt von dbx_content ID 123[-/cid]<strong><strong><br /><br />5. Bedingtes Einfügen von Inhalten. Die Page 123 wird nur includiert wenn die angegebene Function true zurück gibt.<br /></strong></strong>[--inc_if=accepted_function(parameter);]123[-/inc]<br />Durch das bedingte \\\"Includieren\\\" kann die Ausgabe je nach Parametern, Daten, Benutzergruppe u.s.w gesteuert werden.<br /><strong><strong><br />6.Funktione können direkt aus dem Inhalt heraus aufgerufen werden.<br /></strong></strong>[--inf=1]accepted_function(parameter);[-/inf]<strong><strong><strong><br /><br /></strong>7. Funktione können auch nur einmalig includiert werden. Auch wenn diese Anweisung mehrfach im Inhalt.<br /></strong></strong>[--inf_once=1]dbx_GetRows(dbx_content_admin,dbx_content_folder,parent_id=0,name,ASC,990,0,4);[-/inf]<br /><strong><strong><br />[--inf=1] , [-inf_if=...], und [-inf_once..] können nur Funktionen aufrufen, die im Kernel oder im aktivem Modul definiert sind.<br /></strong></strong>Der jeweilige Aufruf wird vom System durch den Rückgabewert der jeweiligen Funktion ersetzt.<strong><strong><br /><br /></strong></strong></p>\r\n<h3><strong><strong> </strong></strong></h3>\r\n<h3><strong><strong>Templates innerhalb von Modulen</strong></strong></h3>\r\n<p><strong><strong><br /></strong></strong>Die Verwendung der Templates innerhalb von Modulen ist denkbar einfach.<br /><br />Insbesondere durch die Verwendung folgender zwei leistungsfähigen Funktionen.<br /><br /></p>\r\n<ul>\r\n<li><strong>$template_content=dbx_Get_Page(\\\"modul,id\\\");</strong><br />Diese Funktion gibt den Inhalt eines db-Templates als String zurück.<br />Dabei werden alle mit dem jeweiligem Template verbundenen Funktionen automatisch ausgeführt.<br />Dazu gehört auch eine mögliche automatische sprachliche Übersetzung.<br />Alle vorhandenen Daten werden in das Template eingemischt.<br />Wenn Template-Anweisungen [-.. .] im Template Content stehen werden diese ausgewertet.<br />.</li>\r\n<li><strong>$template_content=dbx_get_DataPage(</strong><strong>\\\"modul,id\\\",\\\"db-table\\\",\\\"sql-where\\\"</strong><strong>);</strong><br />Diese Funktion verhält sich wie dbx_Get_Page mit der Erweiterung, dass ein bestimmter Datensatz vorher<br />ausgewählt wird und dessen Daten zusätzlich in das Template eingemischt wird.<br />.</li>\r\n</ul>\r\n<p><br /><strong>Beispiel:</strong><br /><br />Mit der folgenden Funktion wird im Modul dbx_user die Adresse des jeweils aktiven Benutzers in einem Eingabeformular angezeigt.<br /><br />[-code]<br />public function adress() {- global $current_user; $uid=$current_user[-\\\'current_user__id\\\']; // Ermittlung der ID des aktuellen Benutzers $dbx_page=dbx_get_mcv(\\\"dbx_user\\\",\\\"adress\\\"); // Ein vom Modul konfigurierbarer Wert, der die ID einer Page ist. $content=dbx_get_DataPage($dbx_page,\\\"dbx_user\\\",$uid); // Template laden und Adressdaten einmischen. return $content; // Inhalt an das System zurückgeben. } [-/code] <br /><br />Diese Ausgabe der Adressdaten kann dan als aktives Modul mit<br />dbXwebApp.php/dbx_modul/dbx_user/op/adress<br />oder aus dem Design-Template und auch aus jedem Modultemplate mit<br />[--modul=dbx_user]op=adress[-/modul]<br />aufgerufen werden.<br /><br /><br /></p>\r\n<h3>Template-Engine</h3>\r\n<p><br />dbXwebApp beinhaltet einen leistungsfähige Engine für die Verarbeitung von Templates.<br /><br />Diese Template-Engine wertet jeden Inhalt aus, den das System oder die Module ausgeben.<br />Diese Inhalte werden nach Funktions- und Modulaufrufen ausgewertet und die jeweiligen Aufrufe werden mit der Ausgabe der jeweiligen Funktion b.z.w. Moduls ersetzt.<br /><br />Jede Ausgabe kann wieder Funktions- und Modulaufrufe beinhalten. Dadurch sind auch tief verschachtelte Strukturen einfach möglich.<br /><br />Ein sehr gutes Beispiel für den Nutzen dieses Systems ist z.B die Baumdarstellung der db-Templates im Admin-Bereich.<br /><br />Obwohl die dort dargestelle Liste recht komplex ist besteht sie nur aus einfachen Modulaufrufen die ineinander verschachtelt sind.<br />Dadurch ist für diese Liste auch keine komplizierte SQL Anweisung nötig.<br /><br /><img title=\"\\\"Baumansicht\" src=\"\\\"upload/web/Image/tepmplates1.png\\\"\" alt=\"\\\"Template-Report\\\"\" width=\"\\\"600\\\"\" height=\"\\\"456\\\"\" /><br /><br /><br />* accepted function = Die Template-Enginer erlaubt nur Funktionen die im Kernel oder in einem aktiven Modul definiert sind. <br />Anstelle einer Funktion kann auch der Wert 1 oder 0, b.z.w. ein Platzhalter der dann diesen Wert hat, angegeben werden.<br /><br /><br /></p>','0','0.00','0','0','0','0','0','0','','','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('17','2008-02-26 16:20:59','2','2021-07-07 12:09:16','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','2','4','','API-Content xy','/Willkommen','','Content,Inhalte','-1','0','0','<p>Das ist der Thesar2</p>','<p>Das ist <strong>der</strong> Content2</p>','0','0.00','0','0','0','0','0','0','','','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('4110','2021-05-20 12:38:04','2','2021-07-07 12:02:52','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','6','4','a1xy','Test-Ende','/Willkommen','','mysql, ajax,web2.0','-1','0','0','','','0','0.00','0','0','0','0','0','0','hot-girl.jpg','Armin.png','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('4117','2021-05-21 13:25:19','2','2021-07-07 12:24:19','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test3','','','','2','4','a1','Titel neu ab HALLOx','/Willkommen','','kex wrt','-1','0','0','','','0','0.00','0','0','0','0','0','0','','','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('4120','0000-00-00 00:00:00','0','2021-07-07 12:26:20','2','2','0','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','3','4,3','','Test Datei Root','test2','','Keywords Keys','0','0','0','','','0','0.00','0','0','0','0','0','0','','','','','','');
INSERT INTO `dbx_de_content` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`activ`,`from`,`to`,`template`,`addmenu`,`class`,`target`,`folder`,`group_read`,`sorter`,`title`,`permalink`,`description`,`keywords`,`hits`,`data`,`modules`,`thesar`,`content`,`xvote`,`vote`,`vote1`,`vote2`,`vote3`,`vote4`,`vote5`,`lastuservote`,`upload1`,`upload2`,`upload3`,`upload4`,`upload5`,`upload6`) VALUES ('1','2021-06-29 12:20:08','2','2021-07-30 10:57:02','2','2','1','0000-00-00 00:00:00','0000-00-00 00:00:00','C-Test1','','','','1','4,3','','Home Page Test','/home','','Keywords Keys x','-1','0','0','','<p>Das iste ein <strong>Test</strong> Content&nbsp; Page 1 (Home)!</p>\r\n<p>&nbsp;</p>\r\n<p>Mit mehr Text</p>\r\n<p>&nbsp;</p>\r\n<p>Mit Bilder</p>\r\n<p>fehlendes Bild <img id=\"avatar_upload_img_5\" class=\"{-class}\" src=\"dbx/files/upload/user/u-0/img/avatar.png?5\" alt=\"Image\" /></p>\r\n<p>fehlendes Bild <img id=\"avatar_upload_img_5\" class=\"{-class}\" src=\"dbx/files/upload/user/u-0/img/test.jpg\" alt=\"Image\" /></p>','0','0.00','0','0','0','0','0','0','Armin.png','IMG_20190912_121528.jpg','Jutta13.jpg','','','');
/*!40000 ALTER TABLE `dbx_de_content` ENABLE KEYS */;


--
-- Create Table `dbx_de_content_folder`
--

DROP TABLE IF EXISTS `dbx_de_content_folder`;
CREATE TABLE `dbx_de_content_folder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `module` int(1) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `group_read` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
  `template` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_de_content_folder`
--

/*!40000 ALTER TABLE `dbx_de_content_folder` DISABLE KEYS */;
INSERT INTO `dbx_de_content_folder` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`name`,`module`,`parent_id`,`group_read`,`template`) VALUES ('1','0000-00-00 00:00:00','2','2021-07-05 11:19:52','2','Home','0','0','2','C-Test1');
INSERT INTO `dbx_de_content_folder` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`name`,`module`,`parent_id`,`group_read`,`template`) VALUES ('2','2021-07-02 10:04:07','2','2021-07-05 12:21:29','2','Top-Menu','0','1','4','C-Test1');
INSERT INTO `dbx_de_content_folder` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`name`,`module`,`parent_id`,`group_read`,`template`) VALUES ('3','2021-07-02 11:30:17','2','2021-07-02 13:29:25','2','Left-Menu','0','1','100,101,103','c1_test1');
INSERT INTO `dbx_de_content_folder` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`name`,`module`,`parent_id`,`group_read`,`template`) VALUES ('6','2021-07-05 09:51:58','2','2021-07-05 11:12:24','2','AB-Test','0','2','201,103','C-Test1');
/*!40000 ALTER TABLE `dbx_de_content_folder` ENABLE KEYS */;


--
-- Create Table `dbx_missing`
--

DROP TABLE IF EXISTS `dbx_missing`;
CREATE TABLE `dbx_missing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL,
  `count` int(11) NOT NULL,
  `missing` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `request` varchar(250) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `missing` (`missing`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_missing`
--

/*!40000 ALTER TABLE `dbx_missing` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_missing` ENABLE KEYS */;


--
-- Create Table `dbx_my_analyse`
--

DROP TABLE IF EXISTS `dbx_my_analyse`;
CREATE TABLE `dbx_my_analyse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `befund_id` int(11) NOT NULL,
  `testident` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `testbez` varchar(60) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `status` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `ergebnis` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `einheiten` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `nwug` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `nwog` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `nwtxt` varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `ergtext` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `abrechner` int(1) NOT NULL DEFAULT 0,
  `gnr` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `cent` int(11) NOT NULL DEFAULT 0,
  `freitext` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `bemerkung` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `befund_id` (`befund_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_my_analyse`
--

/*!40000 ALTER TABLE `dbx_my_analyse` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_my_analyse` ENABLE KEYS */;


--
-- Create Table `dbx_my_befund`
--

DROP TABLE IF EXISTS `dbx_my_befund`;
CREATE TABLE `dbx_my_befund` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL,
  `owner` int(11) NOT NULL,
  `arzt` int(6) NOT NULL DEFAULT 0,
  `pat` int(6) DEFAULT NULL,
  `datum` date DEFAULT NULL,
  `tagesnummer` varchar(20) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `befundart` varchar(2) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT 'UL',
  `befundtyp` int(4) NOT NULL DEFAULT 0,
  `abrechnungstyp` varchar(20) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT 'UL',
  `geor` varchar(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '0',
  `auftraggeber` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `sex` varchar(10) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `patname` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `patvorname` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `gebdat` date DEFAULT '1900-01-01',
  `eilt` int(3) DEFAULT NULL,
  `bemerkung` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `fax` int(1) NOT NULL DEFAULT 0,
  `ldt` int(1) NOT NULL DEFAULT 0,
  `bsnr` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `bsnrb` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `lanr` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `arztname` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `bsnr_strasse` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `bsnr_plz` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `bsnr_ort` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `labor` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `labor_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `labor_strasse` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `labor_plz` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `labor_ort` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `kbv_sende` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `zeichensatz` varchar(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `informationen` varchar(512) COLLATE utf8_unicode_ci NOT NULL,
  `signatur` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `krypto` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `version` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `erstellt` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `datum` (`datum`,`arzt`,`pat`,`befundtyp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_my_befund`
--

/*!40000 ALTER TABLE `dbx_my_befund` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_my_befund` ENABLE KEYS */;


--
-- Create Table `dbx_my_testdata`
--

DROP TABLE IF EXISTS `dbx_my_testdata`;
CREATE TABLE `dbx_my_testdata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime NOT NULL,
  `create_uid` int(11) NOT NULL,
  `update_date` datetime NOT NULL,
  `update_uid` int(11) NOT NULL,
  `owner` int(11) NOT NULL,
  `datum` date DEFAULT NULL,
  `sex` varchar(10) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `nachname` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `vorname` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `gebdat` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=448461 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_my_testdata`
--

/*!40000 ALTER TABLE `dbx_my_testdata` DISABLE KEYS */;
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350495','2020-08-21 21:36:06','2','2020-08-21 21:36:06','2','402','2020-08-18','2','Bo*','Chiara','2002-05-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350597','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-18','2','Im*','Julia','1987-09-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350598','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-18','2','Gi*','Anna','1984-11-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350599','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-18','2','Sc*','Laura','1983-12-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350600','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-18','2','M?*','Anne','1977-09-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350601','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-19','2','Hu*','Anne','1985-12-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350602','2020-08-21 21:36:33','2','2020-08-21 21:36:33','2','424','2020-08-19','2','Ge*','Caroline','1982-08-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350604','2020-08-21 21:36:34','2','2020-08-21 21:36:34','2','424','2020-08-19','2','L?*','Aline','1989-09-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350605','2020-08-21 21:36:34','2','2020-08-21 21:36:34','2','424','2020-08-19','2','Vo*','Anna','1991-04-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350308','2020-08-21 21:35:23','2','2020-08-21 21:35:23','2','342','2020-08-20','1','Le*','Günter','1936-06-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350485','2020-08-21 21:36:03','2','2020-08-21 21:36:03','2','400','2020-08-20','2','R?*','Irmgard','1933-03-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350511','2020-08-21 21:36:12','2','2020-08-21 21:36:12','2','405','2020-08-20','2','B?*','Ursula','1947-09-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350606','2020-08-21 21:36:34','2','2020-08-21 21:36:34','2','424','2020-08-20','2','Bu*','Elfi','1950-03-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350001','2020-08-21 21:34:01','2','2020-08-21 21:34:01','2','423','2020-08-20','2','BR*','KÄTHE','1937-02-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350099','2020-08-21 21:34:30','2','2020-08-21 21:34:30','2','219','2020-08-20','1','Ro*','Michael','1966-10-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350100','2020-08-21 21:34:30','2','2020-08-21 21:34:30','2','219','2020-08-21','1','Kr*','Willi','1955-10-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350102','2020-08-21 21:34:30','2','2020-08-21 21:34:30','2','219','2020-08-21','1','Fi*','Arne','1984-09-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350104','2020-08-21 21:34:30','2','2020-08-21 21:34:30','2','219','2020-08-21','2','Le*','Eva-Maria','1961-03-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350105','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','2','Sc*','Hannelore','1940-12-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350106','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','2','We*','Lisa','1993-01-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350107','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','2','Ad*','Priscilla','1979-11-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350108','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','1','Wi*','Aaron','1980-03-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350109','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','2','Sp*','Julia','1955-06-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350110','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','1','Al*','Sezai','1980-01-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350111','2020-08-21 21:34:31','2','2020-08-21 21:34:31','2','224','2020-08-21','2','La*','Stefanie','1968-05-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350112','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','2','Lo*','Hannah Lena','1988-02-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350113','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','2','Fe*','Arcadia','1959-01-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350114','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','2','Me*','Sandra','1973-07-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350115','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','1','Mu*','Leandro','1986-02-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350116','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','2','Fi*','Vanessa','1991-07-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350117','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','2','Uh*','Elsbeth','1950-08-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350118','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','1','Ze*','Atilla','1992-10-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350119','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','1','R?*','Jürgen','1951-10-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350120','2020-08-21 21:34:32','2','2020-08-21 21:34:32','2','224','2020-08-21','1','Ke*','Guenole','1985-02-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350121','2020-08-21 21:34:33','2','2020-08-21 21:34:33','2','230','2020-08-21','1','Ko*','Alexander','1990-08-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350122','2020-08-21 21:34:33','2','2020-08-21 21:34:33','2','230','2020-08-21','1','St*','Michael','1969-04-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350123','2020-08-21 21:34:33','2','2020-08-21 21:34:33','2','230','2020-08-21','2','Sc*','Tatjana','1970-03-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350124','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Pf*','Hannelore','1955-10-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350125','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Od*','Alice','1955-09-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350126','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','1','M?*','Rolf','1948-02-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350127','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','He*','Petra','1963-01-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350128','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','He*','Waltraud','1943-03-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350129','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','1','P?*','Jürgen','1964-06-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350130','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Kr*','Karla','1934-01-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350131','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','St*','Helga','1948-09-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350132','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','1','De*','Goran','1967-05-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350133','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Ba*','Hildegard','1966-01-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350134','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','So*','Tamasne','1972-06-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350135','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Sc*','Laura Sophie','1996-01-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350136','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','He*','Gisela','1941-01-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350137','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','1','Ri*','Ulrich','1963-02-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350138','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','2','Ha*','Semira','1995-01-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350140','2020-08-21 21:34:35','2','2020-08-21 21:34:35','2','232','2020-08-21','1','Ba*','Ricardo','1965-09-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350141','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','2','Ve*','Gislinde','1943-07-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350142','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','1','Oh*','Peter','1949-09-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350143','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','1','Sc*','Jürgen','1965-11-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350144','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','2','Ge*','Berta','1958-03-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350145','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','2','Re*','Emma','1939-12-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350146','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','1','Po*','Giuseppe','1971-10-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350147','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','1','Gr*','Hermann','1962-03-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350148','2020-08-21 21:34:37','2','2020-08-21 21:34:37','2','243','2020-08-21','1','St*','Rene','1968-04-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350149','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','1','Gu*','Georgi','1964-12-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350150','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','2','Li*','Sylvia','1968-01-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350151','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','1','Am*','Mark-Steven','1989-05-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350152','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','2','Ru*','Claudia','1968-05-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350153','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','2','Ge*','Tatjana','1978-02-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350154','2020-08-21 21:34:39','2','2020-08-21 21:34:39','2','262','2020-08-21','2','Fa*','Shilan','1976-07-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350172','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Sa*','Richard','1950-06-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350173','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Ge*','Heidi','1953-12-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350174','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Pe*','Helmut','1932-04-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350175','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Be*','Elfriede','1943-09-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350176','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Vi*','Dirk','1967-10-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350177','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Zo*','Christian','1983-05-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350178','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Is*','Maria Felicia Oare','1969-10-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350179','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','R?*','Sascha','1973-10-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350181','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Ap*','Monika','1956-06-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350182','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Wi*','Angelica','1960-04-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350183','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Wi*','Francoise','1953-02-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350184','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Sc*','Ludwig','1944-03-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350185','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','2','Ha*','Gerda','1952-04-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350186','2020-08-21 21:34:43','2','2020-08-21 21:34:43','2','266','2020-08-21','1','Ru*','Bernd','1946-06-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350196','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','1','Ba*','Wolfgang','1968-01-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350197','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','2','Da*','Erna','1941-02-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350198','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','1','Mo*','Prabha','1974-05-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350199','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','2','We*','Hannelore','1950-09-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350200','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','2','Ko*','Maraike','1988-04-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350201','2020-08-21 21:34:50','2','2020-08-21 21:34:50','2','302','2020-08-21','2','Ke*','Julia','1994-10-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350203','2020-08-21 21:34:52','2','2020-08-21 21:34:52','2','304','2020-08-21','1','K?*','Gerd','1952-10-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350206','2020-08-21 21:34:53','2','2020-08-21 21:34:53','2','307','2020-08-21','1','La*','Walter','1941-11-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350208','2020-08-21 21:34:54','2','2020-08-21 21:34:54','2','307','2020-08-21','2','An*','Marija','1959-11-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350209','2020-08-21 21:34:54','2','2020-08-21 21:34:54','2','307','2020-08-21','1','Ha*','Jörg','1968-03-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350210','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','1','Ha*','Dieter','1948-01-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350211','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','1','We*','Reinhard','1945-02-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350212','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','1','Sc*','Vladimir','1966-07-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350213','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','2','M\\\'*','Amal','1988-04-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350214','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','1','Br*','Peter','1961-02-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350215','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','2','G?*','Silke','1977-07-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350216','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','2','Kn*','Tanja','1998-12-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350217','2020-08-21 21:34:55','2','2020-08-21 21:34:55','2','309','2020-08-21','1','Do*','Gerhard','1939-05-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350218','2020-08-21 21:34:57','2','2020-08-21 21:34:57','2','311','2020-08-21','1','La*','Hubert','1933-02-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350220','2020-08-21 21:35:00','2','2020-08-21 21:35:00','2','313','2020-08-21','1','L?*','Hans Werner','1944-02-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350221','2020-08-21 21:35:00','2','2020-08-21 21:35:00','2','313','2020-08-21','2','Ni*','Karin','1960-11-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350222','2020-08-21 21:35:00','2','2020-08-21 21:35:00','2','313','2020-08-21','2','Sc*','Christine','1962-03-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350223','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Ok*','Lara','1994-07-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350225','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Zi*','Prayoug','1950-03-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350226','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Ma*','Jacqueline','1997-05-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350227','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','1','Na*','Wolfgang-Friedrich','1949-03-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350228','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Ve*','Milena','1997-07-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350229','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Cl*','Barbara','1938-05-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350230','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','K?*','Rita','1952-09-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350232','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','1','Ha*','Georg','1949-07-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350234','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','1','Cr*','Manfred','1948-10-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350235','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','G?*','Melanie','1979-02-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350236','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','Na*','Bushra','1977-09-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350237','2020-08-21 21:35:01','2','2020-08-21 21:35:01','2','313','2020-08-21','2','B?*','Marianne','1944-02-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350238','2020-08-21 21:35:02','2','2020-08-21 21:35:02','2','313','2020-08-21','1','Ru*','Helmut','1930-06-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350239','2020-08-21 21:35:03','2','2020-08-21 21:35:03','2','314','2020-08-21','2','Pe*','Selina','1999-01-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350241','2020-08-21 21:35:03','2','2020-08-21 21:35:03','2','314','2020-08-21','1','Sc*','Karl-Rainer','1956-10-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350242','2020-08-21 21:35:03','2','2020-08-21 21:35:03','2','314','2020-08-21','1','Wi*','Wilhelm','1944-12-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350243','2020-08-21 21:35:03','2','2020-08-21 21:35:03','2','314','2020-08-21','1','Gr*','Malte','1902-05-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350244','2020-08-21 21:35:05','2','2020-08-21 21:35:05','2','316','2020-08-21','1','r.*','Schein nicht lesbar.','0000-00-00');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350246','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','1','Kl*','Franz Joseph','1944-02-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350247','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','1','Pr*','Joachim','1962-02-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350248','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','2','Po*','Sultan','1975-01-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350249','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','2','Ho*','Katrin','1991-02-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350250','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','2','Sp*','Dorota','1958-01-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350252','2020-08-21 21:35:07','2','2020-08-21 21:35:07','2','317','2020-08-21','1','Ma*','Manfred','1942-12-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350253','2020-08-21 21:35:08','2','2020-08-21 21:35:08','2','319','2020-08-21','1','Ge*','Daniel','1990-12-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350254','2020-08-21 21:35:09','2','2020-08-21 21:35:09','2','319','2020-08-21','2','Br*','Melita','1968-08-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350255','2020-08-21 21:35:09','2','2020-08-21 21:35:09','2','319','2020-08-21','2','Kl*','Michaela','1982-09-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350256','2020-08-21 21:35:09','2','2020-08-21 21:35:09','2','319','2020-08-21','1','Sc*','Fabian','1987-06-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350257','2020-08-21 21:35:09','2','2020-08-21 21:35:09','2','319','2020-08-21','2','K?*','Veronika','1956-07-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350258','2020-08-21 21:35:09','2','2020-08-21 21:35:09','2','319','2020-08-21','2','M?*','Alexandra','1971-08-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350259','2020-08-21 21:35:10','2','2020-08-21 21:35:10','2','321','2020-08-21','1','Sc*','Steffen','1969-07-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350261','2020-08-21 21:35:10','2','2020-08-21 21:35:10','2','321','2020-08-21','1','Kr*','Werner','1941-05-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350263','2020-08-21 21:35:13','2','2020-08-21 21:35:13','2','324','2020-08-21','2','Ma*','Patrizia','1995-07-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350264','2020-08-21 21:35:13','2','2020-08-21 21:35:13','2','324','2020-08-21','2','Re*','Dunja','1969-05-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350265','2020-08-21 21:35:13','2','2020-08-21 21:35:13','2','324','2020-08-21','2','Ri*','Friederike','1965-01-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350266','2020-08-21 21:35:13','2','2020-08-21 21:35:13','2','324','2020-08-21','1','Bi*','Ralf','1944-10-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350268','2020-08-21 21:35:14','2','2020-08-21 21:35:14','2','324','2020-08-21','1','Lo*','Horst','1941-12-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350269','2020-08-21 21:35:14','2','2020-08-21 21:35:14','2','324','2020-08-21','1','Pe*','Stefan','1968-01-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350270','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','2','Ro*','Angelika','1964-01-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350271','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','2','Gr*','Olga','1941-10-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350272','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','2','Kn*','Hermine','1941-08-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350273','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','1','S?*','Wilhelm','1938-12-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350274','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','2','Kn*','Gisela','1954-12-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350275','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','1','Ew*','Guenter','1952-02-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350276','2020-08-21 21:35:15','2','2020-08-21 21:35:15','2','325','2020-08-21','1','Eb*','Wilfried','1958-07-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350277','2020-08-21 21:35:16','2','2020-08-21 21:35:16','2','325','2020-08-21','1','Gl*','Werner','1946-11-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350279','2020-08-21 21:35:16','2','2020-08-21 21:35:16','2','325','2020-08-21','1','Ma*','Lars','1977-08-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350280','2020-08-21 21:35:17','2','2020-08-21 21:35:17','2','336','2020-08-21','2','Ra*','Lise','1954-03-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350281','2020-08-21 21:35:17','2','2020-08-21 21:35:17','2','336','2020-08-21','2','Ko*','Swetlana','1965-07-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350282','2020-08-21 21:35:17','2','2020-08-21 21:35:17','2','336','2020-08-21','2','La*','Valentina','1951-07-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350283','2020-08-21 21:35:19','2','2020-08-21 21:35:19','2','337','2020-08-21','2','Fl*','Liliana','1988-01-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350284','2020-08-21 21:35:19','2','2020-08-21 21:35:19','2','337','2020-08-21','2','Sc*','Angelika','1952-07-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350285','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Dr*','Mary Louise','1959-12-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350286','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Ge*','Marie','2001-06-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350287','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Gr*','Ruth','1928-12-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350288','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','1','Ho*','Ernest','1941-05-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350289','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Im*','Florentina','2002-03-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350290','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Ju*','Karyn','1992-01-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350291','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Ka*','Hildegard','1933-12-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350292','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Kl*','Ursula','1950-10-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350293','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','1','Kr*','Heinz','1937-10-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350294','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Le*','Ruth','1934-10-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350295','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','M?*','Carola','1957-05-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350296','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','1','Na*','Salvatore','1986-08-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350297','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Pr*','Riccardina','1967-02-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350298','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','1','Ra*','Günther','1937-06-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350299','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Sc*','Gabriele','1958-11-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350300','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','St*','Barbara','1945-05-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350301','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Th*','Elsamma','1945-01-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350302','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Va*','Franziska','1989-10-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350303','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','We*','Petra','1959-10-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350304','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Ma*','Vera','1940-03-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350305','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','He*','Anni','1937-06-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350306','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','2','Ge*','Irmgard','1940-09-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350307','2020-08-21 21:35:21','2','2020-08-21 21:35:21','2','339','2020-08-21','1','Kr*','Werner','1944-05-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350309','2020-08-21 21:35:23','2','2020-08-21 21:35:23','2','342','2020-08-21','2','Te*','Marion','1975-04-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350310','2020-08-21 21:35:24','2','2020-08-21 21:35:24','2','348','2020-08-21','1','Ka*','Valentin','1950-04-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350311','2020-08-21 21:35:24','2','2020-08-21 21:35:24','2','348','2020-08-21','1','K?*','Lutz','1962-10-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350312','2020-08-21 21:35:24','2','2020-08-21 21:35:24','2','348','2020-08-21','1','Sc*','Wolfgang','1961-03-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350316','2020-08-21 21:35:27','2','2020-08-21 21:35:27','2','354','2020-08-21','1','Wi*','Georg','1949-07-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350317','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','2','Dr*','Julia','1987-07-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350319','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','2','Ja*','Ursula','1959-02-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350320','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','1','Al*','Lars','1976-06-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350321','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','2','Ge*','Bettina','1960-12-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350322','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','1','Ka*','Max','1934-10-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350323','2020-08-21 21:35:28','2','2020-08-21 21:35:28','2','354','2020-08-21','1','Ge*','Ralf','1970-08-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350324','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','Sc*','Anna-Lena','1998-08-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350325','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','W?*','Margarete','1920-12-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350326','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','Dr*','Hannah Pauline','1996-11-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350327','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','1','Kn*','Sven','2001-02-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350328','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','1','Di*','Andreas','1963-04-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350330','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','1','Sc*','Matthias','1957-12-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350331','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','Re*','Christina','1994-06-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350332','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','La*','Ronja','2002-12-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350333','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','1','Sc*','Reiner','1959-09-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350334','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','Ta*','Jana','1996-11-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350335','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','1','Ay*','Muret','1973-01-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350336','2020-08-21 21:35:29','2','2020-08-21 21:35:29','2','355','2020-08-21','2','Je*','Natalia','1974-11-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350337','2020-08-21 21:35:30','2','2020-08-21 21:35:30','2','355','2020-08-21','2','We*','Petra','1961-06-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350338','2020-08-21 21:35:30','2','2020-08-21 21:35:30','2','355','2020-08-21','2','Ta*','Jana','1996-11-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350339','2020-08-21 21:35:30','2','2020-08-21 21:35:30','2','355','2020-08-21','2','Ta*','Jana','1996-11-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350340','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Lo*','Heinz-Jürgen','1961-04-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350341','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','2','Ho*','Ingried','1944-11-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350342','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Ne*','Josef','1938-03-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350343','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Wi*','Frank','1961-12-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350344','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','2','Tr*','Lioba','1962-12-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350345','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','2','Gr*','Elvira','1952-05-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350346','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','2','Wa*','Jutta','1958-11-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350347','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Ri*','Heiko','1985-03-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350348','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Sa*','Alexander','1989-08-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350349','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','2','Wi*','Hedwig','1943-10-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350350','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Dr*','Oswald','1933-04-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350351','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Ha*','Bernd','1964-05-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350352','2020-08-21 21:35:31','2','2020-08-21 21:35:31','2','360','2020-08-21','1','Ma*','Simon','1992-10-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350353','2020-08-21 21:35:32','2','2020-08-21 21:35:32','2','360','2020-08-21','2','St*','Arianna','1994-11-16');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350354','2020-08-21 21:35:32','2','2020-08-21 21:35:32','2','360','2020-08-21','2','Li*','Michelle','1994-12-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350355','2020-08-21 21:35:32','2','2020-08-21 21:35:32','2','360','2020-08-21','1','Ca*','Mahmut-Emre','2001-03-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350356','2020-08-21 21:35:33','2','2020-08-21 21:35:33','2','365','2020-08-21','2','Ei*','Hannah','1944-08-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350357','2020-08-21 21:35:33','2','2020-08-21 21:35:33','2','365','2020-08-21','1','Ra*','Hans','1956-12-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350358','2020-08-21 21:35:33','2','2020-08-21 21:35:33','2','365','2020-08-21','1','We*','Christoph','1968-10-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350363','2020-08-21 21:35:35','2','2020-08-21 21:35:35','2','368','2020-08-21','2','As*','Gudrun','1947-11-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350366','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Pa*','Melanie','1987-01-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350367','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Sc*','Heidemarie','1946-05-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350368','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','1','Li*','Philipp','1943-04-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350369','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Sc*','Anke','1970-06-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350370','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Sc*','Olga','1938-11-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350371','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Be*','Mirja Beate','1969-12-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350372','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','2','Lo*','Iris','1972-03-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350373','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','1','B?*','Georg','1937-08-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350374','2020-08-21 21:35:37','2','2020-08-21 21:35:37','2','373','2020-08-21','1','Wo*','Kai-Uwe','1964-09-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350380','2020-08-21 21:35:40','2','2020-08-21 21:35:40','2','378','2020-08-21','1','Mo*','Waldemar','1986-04-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350381','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Be*','Marlies','1940-09-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350382','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Li*','Natalia','1974-06-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350383','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','1','Ru*','Bernhard','1943-02-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350384','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','1','Bi*','Karl-Heinz','1940-04-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350385','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Bi*','Eva','1943-09-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350386','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Ha*','Inge','1959-12-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350387','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Ja*','Beata','1979-02-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350388','2020-08-21 21:35:43','2','2020-08-21 21:35:43','2','383','2020-08-21','2','Sa*','Ilse','1954-08-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350389','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Se*','Christa','1932-07-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350391','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Ab*','Sarah','2003-10-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350394','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Di*','Michele','1955-08-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350395','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','La*','Luigi','1962-07-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350396','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Sa*','Claudio','1980-12-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350397','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Ul*','Andrea','1970-03-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350398','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Po*','Mohammad','1958-05-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350399','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Be*','Gerhard','1948-09-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350400','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Ve*','Nils','1999-03-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350401','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Ta*','Tina','1981-05-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350402','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Ha*','Maike','1991-12-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350403','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','2','Ho*','Brigitte','1954-03-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350404','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','He*','Thomas','1960-05-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350408','2020-08-21 21:35:45','2','2020-08-21 21:35:45','2','386','2020-08-21','1','Re*','Immanuel','2002-01-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350409','2020-08-21 21:35:47','2','2020-08-21 21:35:47','2','388','2020-08-21','1','Ni*','Viona','1985-09-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350410','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','2','Ax*','Antje','1942-10-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350411','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','1','Fa*','Mohamed','1953-09-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350412','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','2','Al*','Meike','1970-05-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350413','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','2','Ba*','Mussuda','1954-07-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350414','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','1','Ha*','Thilo','1961-04-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350415','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','2','He*','Gerdi','1945-05-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350416','2020-08-21 21:35:48','2','2020-08-21 21:35:48','2','389','2020-08-21','2','Pr*','Erika','1950-07-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350418','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','Sc*','Hannelore','1942-09-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350419','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','Sc*','Miriam','1991-01-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350420','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','Ha*','Tabea','1992-06-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350421','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','He*','Lieselotte','1936-05-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350422','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','Fr*','Ruth','1938-05-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350423','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','1','Ku*','Erich','1945-01-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350424','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','2','Ge*','Gabriele','1947-05-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350425','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','1','Vi*','Riccardo','1947-07-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350426','2020-08-21 21:35:50','2','2020-08-21 21:35:50','2','391','2020-08-21','1','Sc*','Heiner','1943-10-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350427','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','1','Be*','Paul','1961-10-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350428','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','Fr*','Anna','1979-11-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350429','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','Gl*','Sabine','1966-09-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350430','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','St*','Brigitte','1951-05-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350431','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','Mu*','Marita','1948-07-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350432','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','Mu*','Marita','1948-07-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350433','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','1','Sc*','Thomas','1967-07-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350434','2020-08-21 21:35:52','2','2020-08-21 21:35:52','2','393','2020-08-21','2','Wo*','Sabine','1983-08-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350435','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','1','St*','Günter','1944-11-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350436','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','Dr*','Irmgard','1948-09-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350437','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','1','Ta*','Hans-Jürgen','1941-04-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350438','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','Ta*','Heidemarie','1944-11-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350439','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','1','Ba*','Arthur','1947-12-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350441','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','Sc*','Vanessa','1994-04-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350442','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','Ki*','Inge','1948-12-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350443','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','Ha*','Enisa','1968-03-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350444','2020-08-21 21:35:54','2','2020-08-21 21:35:54','2','395','2020-08-21','2','He*','Cornelia','1955-08-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350445','2020-08-21 21:35:56','2','2020-08-21 21:35:56','2','396','2020-08-21','1','Ha*','Theo','1947-05-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350446','2020-08-21 21:35:56','2','2020-08-21 21:35:56','2','396','2020-08-21','','Bi*','Andr','0000-00-00');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350447','2020-08-21 21:35:57','2','2020-08-21 21:35:57','2','397','2020-08-21','2','Kr*','Renata','1969-09-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350452','2020-08-21 21:35:57','2','2020-08-21 21:35:57','2','397','2020-08-21','2','Sc*','Andrea','1964-02-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350453','2020-08-21 21:35:57','2','2020-08-21 21:35:57','2','397','2020-08-21','1','Ve*','Werner','1961-03-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350456','2020-08-21 21:35:57','2','2020-08-21 21:35:57','2','397','2020-08-21','2','Ka*','Gertrud','1946-07-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350457','2020-08-21 21:35:57','2','2020-08-21 21:35:57','2','397','2020-08-21','2','Ke*','Rita','1945-01-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350458','2020-08-21 21:35:58','2','2020-08-21 21:35:58','2','397','2020-08-21','2','Br*','Martina','1968-05-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350460','2020-08-21 21:35:58','2','2020-08-21 21:35:58','2','397','2020-08-21','1','Kl*','Bernd','1962-08-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350463','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Sc*','Edeltraud','1952-10-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350466','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','1','Sc*','Josef','1940-09-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350467','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Sc*','Lydia','1941-07-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350468','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Ha*','Martina','1978-01-22');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350469','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','1','We*','Matthias','1983-06-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350470','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','1','Ke*','Manfred','1941-07-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350471','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','St*','Helga','1936-11-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350472','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Ne*','Elisabeth','1934-10-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350473','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Pa*','Ksenija','1974-02-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350474','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Sa*','Sevan','1961-02-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350475','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Sc*','Sabine','1967-01-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350476','2020-08-21 21:35:59','2','2020-08-21 21:35:59','2','398','2020-08-21','2','Ke*','Hannelore','1939-11-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350477','2020-08-21 21:36:00','2','2020-08-21 21:36:00','2','398','2020-08-21','1','He*','Peter','1946-11-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350478','2020-08-21 21:36:00','2','2020-08-21 21:36:00','2','398','2020-08-21','1','Pr*','Mario','1985-01-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350479','2020-08-21 21:36:00','2','2020-08-21 21:36:00','2','398','2020-08-21','2','Be*','Kimberly','1997-12-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350480','2020-08-21 21:36:01','2','2020-08-21 21:36:01','2','399','2020-08-21','1','Sc*','Sebastian','1990-07-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350481','2020-08-21 21:36:01','2','2020-08-21 21:36:01','2','399','2020-08-21','2','Sc*','Annette','1960-11-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350482','2020-08-21 21:36:01','2','2020-08-21 21:36:01','2','399','2020-08-21','2','Ba*','Nicole','1979-09-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350483','2020-08-21 21:36:01','2','2020-08-21 21:36:01','2','399','2020-08-21','2','Sc*','Katja','1966-03-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350486','2020-08-21 21:36:03','2','2020-08-21 21:36:03','2','400','2020-08-21','1','Ma*','Volker','1951-07-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350487','2020-08-21 21:36:03','2','2020-08-21 21:36:03','2','400','2020-08-21','2','We*','Helga','1941-12-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350488','2020-08-21 21:36:03','2','2020-08-21 21:36:03','2','400','2020-08-21','1','Ro*','Sven','1979-06-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350489','2020-08-21 21:36:03','2','2020-08-21 21:36:03','2','400','2020-08-21','1','Di*','Harry','1953-04-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350492','2020-08-21 21:36:05','2','2020-08-21 21:36:05','2','401','2020-08-21','2','Ro*','Waltraud','1934-06-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350493','2020-08-21 21:36:05','2','2020-08-21 21:36:05','2','401','2020-08-21','1','Ba*','Martin','1943-09-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350494','2020-08-21 21:36:05','2','2020-08-21 21:36:05','2','401','2020-08-21','2','De*','Lilian','1967-05-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350496','2020-08-21 21:36:06','2','2020-08-21 21:36:06','2','402','2020-08-21','2','Wi*','Helena','1957-09-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350497','2020-08-21 21:36:06','2','2020-08-21 21:36:06','2','402','2020-08-21','1','Wi*','Andreas','1954-08-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350498','2020-08-21 21:36:06','2','2020-08-21 21:36:06','2','402','2020-08-21','1','Ar*','Eleazar Alej','1991-06-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350499','2020-08-21 21:36:07','2','2020-08-21 21:36:07','2','402','2020-08-21','2','Fo*','Sanaa','1973-07-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350500','2020-08-21 21:36:07','2','2020-08-21 21:36:07','2','402','2020-08-21','1','Ok*','Martin','1973-09-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350501','2020-08-21 21:36:07','2','2020-08-21 21:36:07','2','402','2020-08-21','2','Si*','Tamara','1979-06-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350503','2020-08-21 21:36:07','2','2020-08-21 21:36:07','2','402','2020-08-21','2','Pa*','Ana-Maria','1989-03-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350504','2020-08-21 21:36:07','2','2020-08-21 21:36:07','2','402','2020-08-21','1','Pa*','Elisei','1992-03-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350505','2020-08-21 21:36:08','2','2020-08-21 21:36:08','2','403','2020-08-21','3','Bl*','Joshua Gabriel','2017-12-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350506','2020-08-21 21:36:08','2','2020-08-21 21:36:08','2','403','2020-08-21','3','Bl*','Arius Maxim','2015-03-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350507','2020-08-21 21:36:08','2','2020-08-21 21:36:08','2','403','2020-08-21','3','Ha*','Lukas','2020-02-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350508','2020-08-21 21:36:08','2','2020-08-21 21:36:08','2','403','2020-08-21','2','Ha*','Kerstin','1989-12-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350509','2020-08-21 21:36:08','2','2020-08-21 21:36:08','2','403','2020-08-21','2','Ab*','Johanna','1983-08-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350512','2020-08-21 21:36:12','2','2020-08-21 21:36:12','2','405','2020-08-21','1','Be*','Lukas Jakob','1992-07-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350513','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','1','Ne*','Richard','1942-03-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350514','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','2','Pe*','Laura','2000-11-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350516','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','1','Zw*','Robert','1951-09-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350517','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','1','St*','Roy','1992-04-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350518','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','2','La*','Nicole','1973-08-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350519','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','2','Ha*','Ann-Catherine','1999-10-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350521','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','2','Bu*','Helga','1936-06-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350522','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','1','Ma*','Claus','1954-10-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350523','2020-08-21 21:36:13','2','2020-08-21 21:36:13','2','406','2020-08-21','1','Y?*','Ceylan','1966-07-10');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350525','2020-08-21 21:36:15','2','2020-08-21 21:36:15','2','407','2020-08-21','2','St*','Tanja','1969-05-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350527','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','1','Ko*','Waleri','1963-03-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350528','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','1','St*','Wolfgang','1943-06-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350529','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','1','He*','Winfried','1944-02-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350530','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','1','Ba*','Heinz','1948-07-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350531','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','2','Ba*','Leyla','1984-12-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350532','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','2','Fr*','Jasmin','1988-02-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350533','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','2','M?*','Franziska-Ida','1994-02-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350534','2020-08-21 21:36:17','2','2020-08-21 21:36:17','2','408','2020-08-21','2','Lu*','Sandra','1968-01-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350535','2020-08-21 21:36:19','2','2020-08-21 21:36:19','2','410','2020-08-21','2','Ke*','Maria-Elisabeth','1948-11-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350536','2020-08-21 21:36:19','2','2020-08-21 21:36:19','2','410','2020-08-21','2','Za*','Kim','1993-01-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350537','2020-08-21 21:36:20','2','2020-08-21 21:36:20','2','411','2020-08-21','2','Ru*','Sophia','1993-09-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350540','2020-08-21 21:36:23','2','2020-08-21 21:36:23','2','414','2020-08-21','3','Za*','Julian Elian','2016-12-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350541','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','P?*','Roswitha','1944-02-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350542','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Ac*','Olena','1971-04-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350543','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','La*','Lars','2005-10-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350544','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','K?*','Eleni-Irini','1970-10-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350545','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','Pu*','Mike','1975-04-04');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350546','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Kr*','Sandra','1969-11-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350547','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','As*','Adolf','1934-06-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350548','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','To*','Diana','1974-03-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350549','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','Li*','Frank','1962-10-24');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350550','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','Sc*','Janek','1990-11-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350551','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Oe*','Elisabeth','1939-04-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350552','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Po*','Ulrike','1981-11-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350553','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','Gr*','Wolfgang','1946-05-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350554','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','M?*','Hans-Jürgen','1946-12-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350555','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','La*','Kätha','1930-02-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350556','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Ca*','Brigitte','1951-08-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350557','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','De*','Manfred','1944-11-23');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350558','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','2','Ni*','Elisabeth','1927-01-28');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350559','2020-08-21 21:36:25','2','2020-08-21 21:36:25','2','418','2020-08-21','1','Sc*','Franz-Alois','1931-12-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350560','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','1','Kl*','Jeffry','1996-04-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350561','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','1','Mo*','Kumars','1963-02-06');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350562','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','No*','Mahshid','1985-05-20');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350563','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Po*','Melanie','1973-10-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350564','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','1','Ca*','Domenico','1991-09-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350565','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Or*','Cornelia','1982-01-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350566','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Se*','Ilona','1961-04-19');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350567','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Fo*','Elwira','1968-05-02');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350568','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Pe*','Monika','1960-02-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350569','2020-08-21 21:36:27','2','2020-08-21 21:36:27','2','419','2020-08-21','2','Ak*','Aylin','1994-11-17');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350570','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','1','Eh*','Thomas','1968-08-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350571','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Pl*','Lieselotte','1936-12-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350573','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Ur*','Anni','1951-11-05');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350574','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Ma*','Gerlinde','1942-01-07');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350575','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Go*','Sigrid','1950-10-25');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350576','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Hi*','Rosa','1937-08-08');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350577','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','1','Lu*','Wilfried','1933-07-13');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350579','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','B?*','Luise','1936-04-21');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350580','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','L?*','Christina','1982-12-12');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350581','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Lu*','Kerstin','1979-11-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350582','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Da*','Kerstin','1972-12-27');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350583','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','1','Wi*','Roland','1959-10-03');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350584','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Gr*','Rosalie','1957-01-26');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350585','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','1','Gr*','Guenter','1954-10-29');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350586','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','We*','Helga','1938-01-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350587','2020-08-21 21:36:29','2','2020-08-21 21:36:29','2','421','2020-08-21','2','Sc*','Beatrix','1954-12-31');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350589','2020-08-21 21:36:30','2','2020-08-21 21:36:30','2','421','2020-08-21','2','Ha*','Iris','1965-05-14');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350590','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','2','Ka*','Christine','1977-07-01');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350591','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','1','Mo*','Neman','1961-03-30');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350592','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','2','Po*','Rosemarie','1942-12-18');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350593','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','2','Dl*','Ingeborg','1938-03-11');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350594','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','1','St*','Jens','1985-08-09');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350595','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','2','Po*','Petra','1967-05-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350596','2020-08-21 21:36:31','2','2020-08-21 21:36:31','2','422','2020-08-21','2','Po*','Petra','1967-05-15');
INSERT INTO `dbx_my_testdata` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`datum`,`sex`,`nachname`,`vorname`,`gebdat`) VALUES ('350608','2020-08-21 21:36:34','2','2020-08-21 21:36:34','2','424','2020-08-21','2','Sc*','Sonja','1984-08-27');
/*!40000 ALTER TABLE `dbx_my_testdata` ENABLE KEYS */;


--
-- Create Table `dbx_session`
--

DROP TABLE IF EXISTS `dbx_session`;
CREATE TABLE `dbx_session` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) DEFAULT NULL,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) DEFAULT NULL,
  `owner` int(11) DEFAULT NULL,
  `sessid` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `userid` int(11) NOT NULL DEFAULT 0,
  `ip` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `host` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `lastaction` datetime DEFAULT NULL,
  `page` char(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `modul` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `action` char(32) COLLATE utf8_unicode_ci NOT NULL,
  `design` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `color` char(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `language` char(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `request_counter` int(11) DEFAULT NULL,
  `request_last` char(254) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `request_current` char(254) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `counter_id` int(11) DEFAULT 0,
  `mobile` int(1) DEFAULT 0,
  `robot` int(1) DEFAULT 0,
  `name` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `ver` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `os` char(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `width` int(6) DEFAULT 0,
  `height` int(6) DEFAULT 0,
  `cookie` int(1) DEFAULT 0,
  `edit` int(2) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sessid` (`sessid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=COMPRESSED;

--
-- Data for Table `dbx_session`
--

/*!40000 ALTER TABLE `dbx_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_session` ENABLE KEYS */;


--
-- Create Table `dbx_trace`
--

DROP TABLE IF EXISTS `dbx_trace`;
CREATE TABLE `dbx_trace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `xdbxtab` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xdbtab` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xlng` varchar(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xaction` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xwehre` varchar(128) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xip` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xstatus` int(1) DEFAULT NULL,
  `xsql` longtext CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_trace`
--

/*!40000 ALTER TABLE `dbx_trace` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_trace` ENABLE KEYS */;


--
-- Create Table `dbx_trash`
--

DROP TABLE IF EXISTS `dbx_trash`;
CREATE TABLE `dbx_trash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `xuser` int(11) NOT NULL DEFAULT 0,
  `xdate` date NOT NULL,
  `xdbxtab` varchar(64) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xlng` varchar(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xwhere` varchar(128) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xip` varchar(16) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xhost` varchar(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `xrecord` longtext CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_trash`
--

/*!40000 ALTER TABLE `dbx_trash` DISABLE KEYS */;
/*!40000 ALTER TABLE `dbx_trash` ENABLE KEYS */;


--
-- Create Table `dbx_user`
--

DROP TABLE IF EXISTS `dbx_user`;
CREATE TABLE `dbx_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `uname` varchar(60) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `userid` int(11) DEFAULT NULL,
  `pass` varchar(40) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `language` varchar(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `design` varchar(32) CHARACTER SET utf8 COLLATE utf8_swedish_ci DEFAULT NULL,
  `color` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `name` varchar(60) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `email` varchar(60) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `roles` varchar(250) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `reg_date` datetime DEFAULT NULL,
  `login_pid` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `logout_pid` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `lastvisit` datetime DEFAULT NULL,
  `anrede` varchar(60) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `name2` varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `strasse` varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `land` char(3) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `plz` varchar(6) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `ort` varchar(80) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `telefon` varchar(20) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `handy` varchar(20) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `fax` varchar(20) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `geschlecht` char(1) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `geburtstag` date NOT NULL,
  `avatar` varchar(128) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `settings` mediumtext CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `is_confirm` int(1) DEFAULT 0,
  `status` int(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1005 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_user`
--

/*!40000 ALTER TABLE `dbx_user` DISABLE KEYS */;
INSERT INTO `dbx_user` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`uname`,`userid`,`pass`,`language`,`design`,`color`,`name`,`email`,`roles`,`reg_date`,`login_pid`,`logout_pid`,`lastvisit`,`anrede`,`name2`,`strasse`,`land`,`plz`,`ort`,`telefon`,`handy`,`fax`,`geschlecht`,`geburtstag`,`avatar`,`settings`,`is_confirm`,`status`) VALUES ('1','0000-00-00 00:00:00','2','2021-02-15 12:38:58','0','0','Gast','0','d4061b1486fe2da19dd578e8d970f7eb','de','',NULL,'Gast Benutzer','','guest','0000-00-00 00:00:00','dbx_modul/dbx_home','','0000-00-00 00:00:00','','','Friedrich-Ebert-Anlage 49','de','60327','Frankfurt','','','','','0000-00-00','avatar.jpg','','0','1');
INSERT INTO `dbx_user` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`uname`,`userid`,`pass`,`language`,`design`,`color`,`name`,`email`,`roles`,`reg_date`,`login_pid`,`logout_pid`,`lastvisit`,`anrede`,`name2`,`strasse`,`land`,`plz`,`ort`,`telefon`,`handy`,`fax`,`geschlecht`,`geburtstag`,`avatar`,`settings`,`is_confirm`,`status`) VALUES ('2','0000-00-00 00:00:00','2','2021-11-26 13:17:42','0','0','admin','2','21232f297a57a5a743894a0e4a801fc3','de','',NULL,'Administrator (x)','leo4u@gmx.de','admin','2006-08-10 20:24:45','dbx_modul/dbx_admin','dbx_modul/dbx_home','2006-10-20 15:45:04','Herr','Braun  Armin !','Kirnberger Str. 6 a','de','64297','Darmstadt','06151679270','06151 679270','','M','0000-00-00','avatar.jpg','','1','1');
INSERT INTO `dbx_user` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`uname`,`userid`,`pass`,`language`,`design`,`color`,`name`,`email`,`roles`,`reg_date`,`login_pid`,`logout_pid`,`lastvisit`,`anrede`,`name2`,`strasse`,`land`,`plz`,`ort`,`telefon`,`handy`,`fax`,`geschlecht`,`geburtstag`,`avatar`,`settings`,`is_confirm`,`status`) VALUES ('4','2011-08-01 11:57:11','2','2021-11-26 14:14:48','2','2','member','4','f79df8051a223a9c94108d83ffa8149b','0','0',NULL,'Sebastian Schellhaas','flotow4@lgflotow.de','member','0000-00-00 00:00:00','cid/7/top_M_a/2/cms/Befunde','0','0000-00-00 00:00:00','0','0','Marktstr. 9','de','64401','Groß-Bieberau','','','','0','0000-00-00','avatar-4.jpg','0','0','1');
/*!40000 ALTER TABLE `dbx_user` ENABLE KEYS */;


--
-- Create Table `dbx_user_groups`
--

DROP TABLE IF EXISTS `dbx_user_groups`;
CREATE TABLE `dbx_user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `create_date` datetime DEFAULT NULL,
  `create_uid` int(11) NOT NULL DEFAULT 0,
  `update_date` datetime DEFAULT NULL,
  `update_uid` int(11) NOT NULL DEFAULT 0,
  `owner` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL DEFAULT '',
  `description` mediumtext CHARACTER SET utf8 COLLATE utf8_swedish_ci NOT NULL,
  `active` int(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1002 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Data for Table `dbx_user_groups`
--

/*!40000 ALTER TABLE `dbx_user_groups` DISABLE KEYS */;
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('2','0000-00-00 00:00:00','0','2021-07-27 10:44:47','2','2','admin','SysAdmin.  Hat alle Rechte','1');
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('1','0000-00-00 00:00:00','0','2021-07-27 10:43:58','2','2','guest','Gäste - Benutzer ohne Anmeldung','1');
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('4','0000-00-00 00:00:00','0','2021-07-27 10:52:06','2','2','member','Mitglied (nach Anmeldung und Freischaltung)','1');
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('3','2007-01-21 15:10:49','2','2021-07-27 10:47:45','2','2','registered','Regestriert - Standart nach Anmeldung','1');
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('1001','2011-01-13 13:18:48','2','2021-07-27 10:56:33','2','2','praxis','Einsender Labor mit Zugriff auf ihre Befunde','1');
INSERT INTO `dbx_user_groups` (`id`,`create_date`,`create_uid`,`update_date`,`update_uid`,`owner`,`name`,`description`,`active`) VALUES ('88','0000-00-00 00:00:00','0','2021-07-27 10:45:29','2','2','all','Alle','0');
/*!40000 ALTER TABLE `dbx_user_groups` ENABLE KEYS */;

SET FOREIGN_KEY_CHECKS=1;
-- EOB

