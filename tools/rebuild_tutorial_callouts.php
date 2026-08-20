<?php
$root = dirname(__DIR__);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';

define('dbxSystem', 'dbxWebApp');
define('dbxRunAsAdmin', 1);

require $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';

$source_dir = $root . '/files/media/img/tutorial_backup_before_highlight_20260707-164910';
$out_dir = $root . '/files/media/img/tutorial';
$avatar_base = $root . '/tmp/avatar-menu-current.png';
$font = 'C:/Windows/Fonts/arial.ttf';
$font_bold = 'C:/Windows/Fonts/arialbd.ttf';

$pages = [
   80 => ['sorter' => '0010', 'title' => 'Tutorial: Login, Profil und Passwort', 'permalink' => 'tutorial-login-profil-passwort', 'description' => 'Anmeldung, Avatar-Menü, Profilbild und eigenes Passwort.'],
   81 => ['sorter' => '0020', 'title' => 'Tutorial: Admin Dashboard', 'permalink' => 'tutorial-admin-dashboard', 'description' => 'Dashboard-Bereiche, Statuskarten und Systemübersicht.'],
   82 => ['sorter' => '0030', 'title' => 'Tutorial: Menü benutzen', 'permalink' => 'tutorial-menue-benutzen', 'description' => 'Hauptmenü und geöffnete Untermenüs sicher benutzen.'],
   83 => ['sorter' => '0040', 'title' => 'Tutorial: Frontpage direkt bearbeiten', 'permalink' => 'tutorial-frontpage-direkt-cms', 'description' => 'Content direkt aus der sichtbaren Frontpage im CMS öffnen.'],
   84 => ['sorter' => '0050', 'title' => 'Tutorial: CMS Content Tree', 'permalink' => 'tutorial-cms-content-tree', 'description' => 'Content Tree, Ordner, Seiten, Sprachen und Aktionen.'],
   85 => ['sorter' => '0060', 'title' => 'Tutorial: CMS Felder und Editor', 'permalink' => 'tutorial-cms-felder-editor', 'description' => 'Seitendaten, Content-Template, Editor und neue Seiten.'],
   86 => ['sorter' => '0070', 'title' => 'Tutorial: CMS Medienverwendung', 'permalink' => 'tutorial-cms-medienverwendung', 'description' => 'Hero, Gallery und Inline-Medien im rechten CMS-Bereich.'],
   87 => ['sorter' => '0080', 'title' => 'Tutorial: Medienbrowser', 'permalink' => 'tutorial-medienbrowser', 'description' => 'Medien suchen, organisieren, hochladen, auswählen und bearbeiten.'],
   88 => ['sorter' => '0090', 'title' => 'Tutorial: Medienwartung und SEO', 'permalink' => 'tutorial-medienwartung-seo', 'description' => 'Unbenutzte Medien, Übersetzung, SEO und OG-Bild.'],
   89 => ['sorter' => '0100', 'title' => 'Tutorial: dbxKi im CMS', 'permalink' => 'tutorial-dbxki-ki-cms', 'description' => 'KI-Aufträge für neue Inhalte, Änderungen und Übersetzungen.'],
];

$slides = [
   s(80, 'tutorial-010-login-form.png', 'v5-01-login-clean.png', [330, 30, 1260, 709], 'Login starten', 'Benutzername und Passwort werden hier eingegeben. Danach wird die Anmeldung gestartet.', [[610, 250, 410, 190, 'Hier geben Sie Benutzername und Passwort ein.', 'right']]),
   s(80, 'tutorial-011-login-submit.png', 'v5-02-login-filled.png', [350, 160, 1240, 698], 'Anmeldung prüfen', 'Nach dem Absenden prüft dbXapp die Zugangsdaten und lädt die Arbeitsumgebung.', [[620, 485, 286, 52, 'Mit diesem Button melden Sie sich an.', 'right']]),
   s(80, 'tutorial-012-after-login.png', 'v5-03-home-after-login.png', [0, 0, 1600, 900], 'Nach dem Login', 'Angemeldete Benutzer sehen Admin-Menüs, Bearbeitungssymbole und persönliche Funktionen.', [[0, 0, 900, 92, 'Diese Menüleiste ist nach der Anmeldung sichtbar.', 'below']]),
   s(80, 'tutorial-013-avatar-menu.png', $avatar_base, [1160, 0, 730, 410], 'Avatar-Menü', 'Das Avatar-Menü führt zu neuen Anfragen, eigenen Anfragen und zum eigenen Profil.', [[1628, 45, 232, 135, 'Neue Anfrage, Meine Anfragen und Mein Profil liegen in diesem Menü.', 'left']]),
   s(80, 'tutorial-014-profile-data.png', 'v5-05-profile-top.png', [70, 170, 1320, 743], 'Eigene Daten', 'Im Profil werden Kontaktdaten, Adresse, Sprache, Design und Farbe bearbeitet.', [[115, 260, 1260, 545, 'Hier werden Ihre persönlichen Daten und Adressdaten gepflegt.', 'inside']]),
   s(80, 'tutorial-015-profile-password.png', 'v5-06-profile-password-avatar.png', [760, 200, 1080, 608], 'Profilbild und Passwort', 'Profilbild und Passwort liegen rechts im Profil. Passwortänderungen brauchen zwei gleiche Eingaben.', [[1395, 260, 410, 245, 'Hier laden Sie Ihr Profilbild hoch.', 'left'], [1395, 525, 410, 220, 'Das Passwort muss zweimal gleich eingegeben werden, um es zu ändern.', 'left']]),

   s(81, 'tutorial-020-dashboard-overview.png', 'v5-07-admin-dashboard.png', [70, 105, 1780, 1001], 'Dashboard Übersicht', 'Das Admin Dashboard ist die zentrale Übersicht für Systemstatus, Datenbanken und Aktivitäten.', [[410, 120, 1420, 70, 'Das Dashboard fasst Status, Kennzahlen und Aktivitäten zusammen.', 'below']]),
   s(81, 'tutorial-021-dashboard-navigation.png', 'v5-07-admin-dashboard.png', [70, 120, 520, 800], 'Dashboard-Navigation', 'Links wechseln Sie zwischen Status, Kennzahlen, Schnellzugriff, Monitoring und Auswertung.', [[95, 215, 285, 730, 'Diese Navigation schaltet die Dashboard-Bereiche um.', 'right']]),
   s(81, 'tutorial-022-dashboard-statuskarten.png', 'v5-07-admin-dashboard.png', [390, 185, 1040, 585], 'Statuskarten', 'Die Karten zeigen Request-, PHP- und Datenbankzeiten. So erkennt man Performance-Probleme schnell.', [[430, 205, 780, 560, 'Diese Karten zeigen Laufzeiten und technische Kennzahlen.', 'right']]),
   s(81, 'tutorial-023-dashboard-kontakte.png', 'v5-07-admin-dashboard.png', [1180, 190, 690, 388], 'Kontakt-Status', 'Die Kontaktbox zeigt offene, beantwortete und geschlossene Anfragen nach Status.', [[1390, 205, 420, 285, 'Hier sehen Sie den Stand der Kontaktanfragen.', 'left']]),

   s(82, 'tutorial-030-main-menu.png', 'v5-08-menu-base.png', [0, 0, 1320, 743], 'Hauptmenü', 'Das Hauptmenü öffnet die Arbeitsbereiche. Erklärt wird nur, was gerade sichtbar ist.', [[0, 0, 980, 96, 'Die Hauptpunkte öffnen die jeweiligen Untermenüs.', 'below']]),
   s(82, 'tutorial-031-content-menu.png', 'v5-09-menu-content.png', [120, 0, 1120, 630], 'Content-Menü', 'Im geöffneten Content-Menü starten Sie CMS, Medien und SEO-Funktionen.', [[210, 0, 250, 315, 'Dieses geöffnete Untermenü enthält die Content-Werkzeuge.', 'right']]),
   s(82, 'tutorial-032-dbxki-menu.png', 'v5-10-menu-dbxki.png', [250, 0, 1120, 630], 'dbxKi-Menü', 'Im geöffneten dbxKi-Menü liegen KI-Aufträge für Content und Module.', [[340, 0, 240, 315, 'Dieses Untermenü startet KI-Aufträge.', 'right']]),
   s(82, 'tutorial-033-frontend-menu.png', 'v5-11-menu-frontend-cms.png', [420, 0, 1120, 630], 'Frontend-CMS-Menü', 'Dieses geöffnete Menü startet Bearbeitungen direkt aus der Frontpage heraus.', [[500, 0, 280, 320, 'Nur wenn dieses Menü offen ist, werden diese Einträge erklärt.', 'right']]),

   s(83, 'tutorial-040-frontpage-editor-icon.png', 'v5-12-frontpage-edit-icons.png', [60, 95, 1400, 788], 'Editor-Icon auf der Seite', 'Als Admin hat jede Content-Seite links oben ein Editor-Icon zum Bearbeiten der sichtbaren Seite.', [[78, 112, 38, 32, 'Dieses Editor-Icon öffnet die Bearbeitung dieser Content-Seite.', 'right']]),
   s(83, 'tutorial-041-frontpage-openwin.png', 'v5-13-frontpage-cms-openwin.png', [360, 90, 1240, 698], 'CMS als Fenster', 'Nach dem Klick wird das CMS im Fenster geöffnet und die aktuelle Seite ist bereits geladen.', [[430, 100, 1120, 90, 'Das Fenster liegt über der Frontpage und ist direkt bearbeitbar.', 'below']]),
   s(83, 'tutorial-042-frontpage-loaded-page.png', 'v5-13-frontpage-cms-openwin.png', [420, 150, 1120, 630], 'Aktuelle Seite geladen', 'Beim Frontpage-Aufruf gibt es keinen Umweg: Sie bearbeiten die Seite, die im Frontend sichtbar war.', [[690, 155, 430, 60, 'Diese Seiten-ID und der Titel gehören zur gerade geöffneten Seite.', 'below']]),

   s(84, 'tutorial-050-cms-tree-overview.png', 'v5-14-cms-overview.png', [60, 100, 1800, 1012], 'Content Tree offen', 'Wenn der Content Tree offen ist, wird links die Seitenstruktur mit Ordnern und Seiten erklärt.', [[75, 165, 600, 880, 'Der Content Tree zeigt Ordner, Seiten und Sprachstatus.', 'right']]),
   s(84, 'tutorial-051-cms-tree-search.png', 'v5-15-cms-page-fields.png', [70, 150, 690, 700], 'Suchen und anlegen', 'Oben im Tree suchen Sie Seiten oder legen neue Ordner und Seiten an.', [[80, 175, 580, 80, 'Suche und Neu-Buttons arbeiten auf der linken Struktur.', 'right']]),
   s(84, 'tutorial-052-cms-tree-pages.png', 'v5-15-cms-page-fields.png', [80, 250, 620, 650], 'Ordner und Seiten', 'Ordner können aufgeklappt werden. Ein Klick auf eine Seite lädt rechts die Bearbeitung.', [[120, 530, 530, 165, 'Ein Klick auf eine Seite lädt sie rechts im CMS.', 'right']]),
   s(84, 'tutorial-053-cms-tree-actions.png', 'v5-15-cms-page-fields.png', [70, 430, 680, 450], 'Sprachen und Aktionen', 'Die kleinen Sprach- und Aktionssymbole zeigen vorhandene Sprachversionen und Bearbeitungsfunktionen.', [[520, 520, 120, 210, 'Diese Symbole zeigen Sprachstatus und Aktionen pro Eintrag.', 'right']]),

   s(85, 'tutorial-060-cms-fields-title.png', 'v5-15-cms-page-fields.png', [500, 170, 1060, 596], 'Titel und Permalink', 'Titel, Permalink und Status beschreiben die Seite und ihre URL.', [[458, 255, 330, 40, 'Der Titel ist der sichtbare Name der Seite.', 'below'], [1160, 255, 350, 40, 'Der Status bestimmt, ob die Seite aktiv ist.', 'below']]),
   s(85, 'tutorial-061-cms-content-template.png', 'v5-15-cms-page-fields.png', [560, 170, 1040, 585], 'Content Template', 'Das Content Template bestimmt, welche Bereiche die Seite besitzt, zum Beispiel Header, Hero, Gallery oder Body.', [[800, 255, 350, 40, 'Hier können Sie das Content Template auswählen.', 'below']]),
   s(85, 'tutorial-062-cms-description.png', 'v5-15-cms-page-fields.png', [520, 260, 1060, 596], 'Beschreibung', 'Die Beschreibung hilft intern und kann für SEO und Vorschauen verwendet werden.', [[458, 330, 1055, 65, 'Hier steht eine kurze Beschreibung der Seite.', 'below']]),
   s(85, 'tutorial-063-cms-editor.png', 'v5-16-cms-editor-area.png', [390, 330, 1120, 630], 'Editor', 'Im Editor schreiben Sie den eigentlichen Seiteninhalt.', [[450, 430, 860, 390, 'Hier wird der Text der Content-Seite bearbeitet.', 'above']]),
   s(85, 'tutorial-064-cms-editor-toolbar.png', 'v5-16-cms-editor-area.png', [390, 350, 1120, 500], 'Editor-Werkzeugleiste', 'Über die Werkzeugleiste fügen Sie Formatierungen, Links, Medien, Module und Layout-Bausteine ein.', [[455, 392, 850, 42, 'Diese Werkzeugleiste steuert Formatierung und Einfügen.', 'below']]),
   s(85, 'tutorial-065-cms-new-page.png', 'v5-18-cms-new-page.png', [430, 160, 1120, 630], 'Neue Seite', 'Beim Anlegen einer Seite wählen Sie Zielposition, Titel, Template, Schreibstil und Inhaltsauftrag.', [[538, 372, 1285, 240, 'Diese Felder beschreiben die neue Seite.', 'above']]),

   s(86, 'tutorial-070-cms-right-overview.png', 'v5-17-cms-hero-gallery.png', [1180, 150, 700, 800], 'Rechter CMS-Bereich', 'Rechts steuern Sie Medienverwendung für Hero, Gallery und Inline-Medien.', [[1320, 205, 280, 620, 'Dieser rechte Bereich steuert die Medien der Seite.', 'left']]),
   s(86, 'tutorial-071-cms-hero-image.png', 'v5-17-cms-hero-gallery.png', [1240, 170, 620, 570], 'Hero-Bild', 'Hero ist das große Kopfbild der Seite. Auswahl und Speichern wirken auf den Hero-Bereich.', [[1335, 235, 250, 115, 'Hier sehen Sie das aktuell gewählte Hero-Bild.', 'left']]),
   s(86, 'tutorial-072-cms-hero-settings.png', 'v5-17-cms-hero-gallery.png', [1240, 310, 620, 560], 'Hero-Einstellungen', 'Template, Abstand, Höhe, Variante und Sticky-Verhalten beeinflussen die Darstellung des Hero-Bildes.', [[1335, 385, 250, 245, 'Diese Felder bestimmen die Hero-Darstellung.', 'left']]),
   s(86, 'tutorial-073-cms-gallery-section.png', 'v5-17-cms-hero-gallery.png', [1240, 650, 620, 380], 'Gallery', 'Die Gallery ist die Bilder- oder Medienliste der Seite. Sie besitzt eigene Darstellungsoptionen.', [[1340, 675, 245, 80, 'Dieser Bereich konfiguriert die Gallery.', 'left']]),
   s(86, 'tutorial-074-cms-inline-media.png', 'v5-17-cms-hero-gallery.png', [1240, 760, 620, 300], 'Inline-Medien', 'Im Abschnitt Im Text werden Medien verwaltet, die direkt im Editor-Inhalt verwendet werden.', [[1340, 780, 245, 80, 'Hier werden Inline-Medien für den Editor verwaltet.', 'left']]),
   s(86, 'tutorial-075-cms-media-buttons.png', 'v5-17-cms-hero-gallery.png', [1260, 610, 600, 270], 'Auswahl und Speichern', 'Auswahl öffnet den Medienbrowser. Speichern übernimmt die Einstellungen dieses Bereichs.', [[1345, 625, 245, 45, 'Auswahl öffnet den Medienbrowser, Speichern sichert diesen Bereich.', 'left']]),

   s(87, 'tutorial-080-media-browser-overview.png', 'v5-20-media-browser.png', [430, 90, 1060, 596], 'Medienbrowser Übersicht', 'Der Medienbrowser zeigt vorhandene Medien im gewählten Kontext.', [[470, 155, 1000, 580, 'Hier wählen Sie Medien aus und übernehmen sie.', 'inside']]),
   s(87, 'tutorial-081-media-search-filter.png', 'v5-20-media-browser.png', [440, 170, 1040, 585], 'Suche und Filter', 'Suche, Ordnerauswahl und Typfilter begrenzen die angezeigten Medien.', [[515, 230, 930, 35, 'Suche, Verzeichnis und Typfilter grenzen die Medien ein.', 'below']]),
   s(87, 'tutorial-082-media-select.png', 'v5-20-media-browser.png', [440, 280, 1040, 585], 'Auswahl übernehmen', 'Medien werden zuerst ausgewählt und danach mit Auswahl übernehmen in den CMS-Bereich übertragen.', [[1295, 925, 160, 42, 'Erst dieser Button übernimmt die Auswahl.', 'above']]),
   s(87, 'tutorial-083-media-upload-youtube.png', 'v5-21-media-upload-youtube.png', [520, 150, 1120, 630], 'Upload und YouTube', 'Neue Dateien werden hochgeladen, externe Videos werden über den YouTube-Bereich eingebunden.', [[565, 210, 560, 500, 'Upload und YouTube liegen in diesem aufklappbaren Bereich.', 'right']]),
   s(87, 'tutorial-084-media-crop-resize.png', 'v5-22-media-crop-resize.png', [660, 150, 1120, 630], 'Bild bearbeiten', 'Zuschneiden und Resize bearbeiten eine Kopie. Erst Übernehmen speichert das Ergebnis am Bild.', [[1120, 270, 150, 520, 'Hier werden Ausschnitt und Größe eingestellt.', 'right']]),
   s(87, 'tutorial-085-media-batch.png', 'v5-23-media-batch.png', [500, 160, 1120, 630], 'Batch', 'Batch-Funktionen bearbeiten mehrere ausgewählte Medien nacheinander.', [[1300, 170, 150, 60, 'Batch startet Mehrfachaktionen.', 'left']]),

   s(88, 'tutorial-090-media-maintenance.png', 'v5-24-media-maintenance.png', [520, 180, 1040, 585], 'Medienwartung', 'Die Wartung findet unbenutzte Bilder und kann sie verschieben oder löschen.', [[595, 220, 870, 300, 'Hier wird der Fortschritt der Wartung angezeigt.', 'below']]),
   s(88, 'tutorial-091-translation-dialog.png', 'v5-19-cms-language-dialog.png', [480, 180, 1000, 563], 'Übersetzen', 'Der Dialog überträgt Seiten in Zielsprachen und erhält die Struktur.', [[610, 230, 700, 520, 'Hier wählen Sie Ausgangs- und Zielsprache.', 'above']]),
   s(88, 'tutorial-092-seo-fields.png', 'v5-30-seo.png', [40, 120, 1700, 956], 'SEO', 'SEO-Titel, Keywords, Robots und OG-Bild steuern Suchmaschinen und geteilte Links.', [[225, 250, 1030, 110, 'Diese Felder beschreiben die Seite für SEO.', 'below']]),

   s(89, 'tutorial-100-dbxki-hub.png', 'v5-25-dbxki-hub.png', [260, 155, 1400, 788], 'dbxKi Übersicht', 'dbxKi sammelt KI-Aufträge für neue Inhalte, Änderungen, Übersetzungen und Module.', [[312, 326, 1295, 295, 'Von hier starten Sie die KI-Aufträge.', 'above']]),
   s(89, 'tutorial-101-dbxki-new-content.png', 'v5-26-dbxki-new-content.png', [70, 160, 1800, 1012], 'Neuer Content', 'Beim neuen Content beschreibt der Auftrag Zielseite, Thema, Zielgruppe und Struktur.', [[538, 372, 1285, 432, 'Diese Felder beschreiben den neuen Content-Auftrag.', 'above']]),
   s(89, 'tutorial-102-dbxki-change-content.png', 'v5-27-dbxki-change-content.png', [70, 160, 1800, 1012], 'Content ändern', 'Bei Änderungen bleibt die vorhandene Seite Bezugspunkt. Die KI überarbeitet nur die gewählten Bereiche.', [[538, 372, 1285, 432, 'Hier wird festgelegt, was an der bestehenden Seite geändert werden soll.', 'above']]),
   s(89, 'tutorial-103-dbxki-translate.png', 'v5-28-dbxki-translate-page.png', [70, 160, 1800, 1012], 'Seite übersetzen', 'Eine vorhandene Seite wird in eine Ziel-Sprache übertragen.', [[538, 372, 1285, 432, 'Hier wählen Sie Seite, Sprache und Übersetzungsauftrag.', 'above']]),
   s(89, 'tutorial-104-dbxki-sync-all.png', 'v5-29-dbxki-sync-all.png', [260, 130, 1400, 788], 'Sprache komplett', 'Die Struktur einer Ausgangssprache kann komplett in Zielsprachen übernommen werden.', [[535, 210, 870, 470, 'Dieser Auftrag überträgt komplette Sprachstrukturen.', 'below']]),
];

function s(int $page, string $file, string $source, array $crop, string $title, string $caption, array $bubbles): array {
   return compact('page', 'file', 'source', 'crop', 'title', 'caption', 'bubbles');
}

function rgba($img, array $rgb, int $alpha = 0) {
   return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
}

function rounded_rect($img, $x1, $y1, $x2, $y2, $r, $col, bool $filled = true): void {
   $x1 = (int)round($x1); $y1 = (int)round($y1); $x2 = (int)round($x2); $y2 = (int)round($y2);
   $r = max(1, (int)round($r));
   if ($filled) {
      imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $col);
      imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $col);
      imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $col);
      imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $col);
      imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $col);
      imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $col);
      return;
   }
   imageline($img, $x1 + $r, $y1, $x2 - $r, $y1, $col);
   imageline($img, $x1 + $r, $y2, $x2 - $r, $y2, $col);
   imageline($img, $x1, $y1 + $r, $x1, $y2 - $r, $col);
   imageline($img, $x2, $y1 + $r, $x2, $y2 - $r, $col);
   imagearc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $col);
   imagearc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $col);
   imagearc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $col);
   imagearc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $col);
}

function border_rounded($img, $x, $y, $w, $h, $r, $thick, $col): void {
   for ($i = 0; $i < $thick; $i++) {
      rounded_rect($img, $x + $i, $y + $i, $x + $w - $i, $y + $h - $i, max(1, $r - $i), $col, false);
   }
}

function wrap_text(string $text, string $font, int $size, int $max_width): array {
   $words = preg_split('/\s+/', trim($text));
   $lines = [];
   $line = '';
   foreach ($words as $word) {
      $test = trim($line . ' ' . $word);
      $box = imagettfbbox($size, 0, $font, $test);
      if ($line !== '' && ($box[2] - $box[0]) > $max_width) {
         $lines[] = $line;
         $line = $word;
      } else {
         $line = $test;
      }
   }
   if ($line !== '') $lines[] = $line;
   return $lines;
}

function clamp($v, $min, $max) {
   return max($min, min($max, $v));
}

function transform_rect(array $rect, array $map): array {
   [$x, $y, $w, $h] = $rect;
   return [
      $map['dx'] + ($x - $map['cx']) * $map['scale'],
      $map['dy'] + ($y - $map['cy']) * $map['scale'],
      $w * $map['scale'],
      $h * $map['scale'],
   ];
}

function draw_bubble($img, array $note, array $map, string $font, string $font_bold): void {
   $target = transform_rect(array_slice($note, 0, 4), $map);
   [$tx, $ty, $tw, $th] = $target;
   $text = (string)$note[4];
   $side = strtolower((string)($note[5] ?? 'right'));
   $font_size = 21;
   $bubble_w = 450;
   $lines = wrap_text($text, $font, $font_size, $bubble_w - 44);
   $bubble_h = max(86, 34 + count($lines) * 31);
   $gap = 18;

   if ($side === 'inside') {
      $bx = $tx + max(18, min(44, $tw * 0.08));
      $by = $ty + max(18, min(44, $th * 0.12));
   } elseif ($side === 'left') {
      $bx = $tx - $bubble_w - $gap;
      $by = $ty + ($th - $bubble_h) / 2;
   } elseif ($side === 'right') {
      $bx = $tx + $tw + $gap;
      $by = $ty + ($th - $bubble_h) / 2;
   } elseif ($side === 'above') {
      $bx = $tx + ($tw - $bubble_w) / 2;
      $by = $ty - $bubble_h - $gap;
   } else {
      $bx = $tx + ($tw - $bubble_w) / 2;
      $by = $ty + $th + $gap;
   }
   $bx = clamp($bx, 16, 1600 - $bubble_w - 16);
   $by = clamp($by, 16, 900 - $bubble_h - 16);

   $shadow = rgba($img, [92, 72, 18], 92);
   $fill = rgba($img, [255, 245, 170], 3);
   $border = rgba($img, [238, 177, 0], 0);
   $text_col = rgba($img, [20, 33, 50], 0);

   rounded_rect($img, $bx + 7, $by + 8, $bx + $bubble_w + 7, $by + $bubble_h + 8, 28, $shadow, true);
   rounded_rect($img, $bx, $by, $bx + $bubble_w, $by + $bubble_h, 28, $fill, true);
   border_rounded($img, $bx, $by, $bubble_w, $bubble_h, 28, 3, $border);

   if ($side !== 'inside') {
      $tcx = $tx + $tw / 2;
      $tcy = $ty + $th / 2;
      if ($tcx < $bx) {
         $pts = [$bx, $by + $bubble_h * .36, $bx - 30, $by + $bubble_h * .5, $bx, $by + $bubble_h * .64];
      } elseif ($tcx > $bx + $bubble_w) {
         $pts = [$bx + $bubble_w, $by + $bubble_h * .36, $bx + $bubble_w + 30, $by + $bubble_h * .5, $bx + $bubble_w, $by + $bubble_h * .64];
      } elseif ($tcy < $by) {
         $pts = [$bx + 54, $by, $bx + 82, $by - 30, $bx + 110, $by];
      } else {
         $pts = [$bx + 54, $by + $bubble_h, $bx + 82, $by + $bubble_h + 30, $bx + 110, $by + $bubble_h];
      }
      imagefilledpolygon($img, array_map('intval', $pts), 3, $fill);
      imagepolygon($img, array_map('intval', $pts), 3, $border);
   }

   $yy = $by + 39;
   foreach ($lines as $line) {
      imagettftext($img, $font_size, 0, (int)$bx + 22, (int)$yy, $text_col, $font_bold, $line);
      $yy += 31;
   }
}

function render_slide(array $slide, string $source_dir, string $out_dir, string $font, string $font_bold): void {
   $src_path = is_file($slide['source']) ? $slide['source'] : $source_dir . '/' . $slide['source'];
   if (!is_file($src_path)) throw new RuntimeException('Missing source: ' . $src_path);
   $src = imagecreatefrompng($src_path);
   if (!$src) throw new RuntimeException('Cannot load: ' . $src_path);
   [$cx, $cy, $cw, $ch] = $slide['crop'];
   $sw = imagesx($src); $sh = imagesy($src);
   $cx = clamp($cx, 0, $sw - 1); $cy = clamp($cy, 0, $sh - 1);
   $cw = min($cw, $sw - $cx); $ch = min($ch, $sh - $cy);
   $scale = min(1600 / $cw, 900 / $ch);
   $dw = (int)round($cw * $scale); $dh = (int)round($ch * $scale);
   $dx = (int)round((1600 - $dw) / 2); $dy = (int)round((900 - $dh) / 2);

   $img = imagecreatetruecolor(1600, 900);
   imagealphablending($img, true);
   imagesavealpha($img, true);
   imagefilledrectangle($img, 0, 0, 1600, 900, rgba($img, [248, 251, 255], 0));
   imagecopyresampled($img, $src, $dx, $dy, $cx, $cy, $dw, $dh, $cw, $ch);
   $map = ['cx' => $cx, 'cy' => $cy, 'scale' => $scale, 'dx' => $dx, 'dy' => $dy];
   foreach ($slide['bubbles'] as $bubble) {
      draw_bubble($img, $bubble, $map, $font, $font_bold);
   }
   imagepng($img, $out_dir . '/' . $slide['file'], 5);
   imagedestroy($img);
   imagedestroy($src);
}

function now(): string {
   return date('Y-m-d H:i:s.v');
}

foreach ($slides as $slide) {
   render_slide($slide, $source_dir, $out_dir, $font, $font_bold);
   echo 'created ' . $slide['file'] . PHP_EOL;
}

$db = dbx()->get_system_obj('dbxDB');
$content_dd = 'content_de';
$media_dd = 'dbxMedia';
$usage_dd = 'dbxMediaUsage';
if (!is_object($db) || !$db->connect_db_server('dbx|dbxContent.db3') || !$db->connect_db_server('dbx|dbxMedia.db3')) {
   throw new RuntimeException('Tutorial-Datenbanken konnten nicht ueber dbxDB verbunden werden.');
}
if ($db->begin($content_dd) !== 1 || $db->begin($media_dd) !== 1) {
   throw new RuntimeException('Tutorial-Transaktionen konnten nicht gestartet werden.');
}

try {
   $max_rows = $db->select($content_dd, '', 'id', 'id', 'DESC', '', 1, 0, 0);
   $max_id = is_array($max_rows) && isset($max_rows[0]['id']) ? (int)$max_rows[0]['id'] : 0;
   foreach ($pages as $id => $page) {
      $content = '<p>' . htmlspecialchars($page['description'], ENT_QUOTES, 'UTF-8') . '</p><p>Nutzen Sie die Slideshow, um die einzelnen Schritte in Ruhe durchzugehen.</p>';
      $row = $db->select1($content_dd, (int)$id, 'id', 0);
      $values = array(
         'update_date' => now(),
         'update_uid' => 1,
         'activ' => 1,
         'template' => 'c-body1-footer',
         'addmenu' => '1',
         'folder' => 15,
         'group_read' => '*',
         'sorter' => $page['sorter'],
         'title' => $page['title'],
         'permalink' => $page['permalink'],
         'description' => $page['description'],
         'keywords' => 'dbxapp,tutorial,cms,ki,medien',
         'content' => $content,
         'hero_template' => 'none',
         'hero_image_id' => 'none',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '1',
         'gallery_image_size' => 'original',
         'gallery_overflow' => 'tutorial',
         'gallery_click_behavior' => 'lightbox',
         'gallery_lightbox_width' => '100vw',
         'seo_title' => $page['title'],
      );
      if (is_array($row)) {
         if ($db->update($content_dd, $values, (int)$id, 0) !== 1) {
            throw new RuntimeException('Tutorial-Seite konnte nicht aktualisiert werden: ' . $id);
         }
      } else {
         $id = max($id, ++$max_id);
         $values += array(
            'id' => $id,
            'create_date' => now(),
            'create_uid' => 1,
            'owner' => 1,
            'class' => '',
            'target' => '',
            'hits' => 0,
            'data' => '',
            'modules' => '',
            'thesar' => '',
            'hero_margin_top' => '0',
            'hero_height' => 'parent',
            'hero_variant' => 'parent',
            'hero_sticky' => 'parent',
            'hero_scroll_layer' => 'parent',
            'lng_uid' => 'p_tutorial_' . $id,
            'lng_sync' => 'auto',
            'lng_rev' => 1,
            'lng_synced_rev' => 0,
            'meta_robots' => 'index,follow',
            'seo_image_id' => 0,
         );
         if ($db->insert($content_dd, $values, 0) !== 1) {
            throw new RuntimeException('Tutorial-Seite konnte nicht angelegt werden: ' . $id);
         }
      }
   }

   $overview = '<p>Diese Tutorial-Seiten erklären dbXapp als CMS und KI-unterstütztes CMS in einzelnen, klar getrennten Bereichen.</p><div class="list-group">';
   foreach ($pages as $page) {
      $overview .= '<a class="list-group-item list-group-item-action" href="' . htmlspecialchars($page['permalink'], ENT_QUOTES, 'UTF-8') . '"><strong>' . htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') . '</strong><br><span class="text-muted">' . htmlspecialchars($page['description'], ENT_QUOTES, 'UTF-8') . '</span></a>';
   }
   $overview .= '</div>';
   $db->update($content_dd, array(
      'update_date' => now(),
      'update_uid' => 1,
      'content' => $overview,
      'title' => 'dbXapp Tutorials',
      'description' => 'Übersicht aller dbXapp CMS- und KI-Tutorials.',
   ), 79, 0);

   $media_ids = [];
   foreach ($slides as $i => $slide) {
      $path = $out_dir . '/' . $slide['file'];
      $dim = getimagesize($path);
      $rel = 'media/img/tutorial/' . $slide['file'];
      $media_row = $db->select1($media_dd, array('file_path' => $rel), 'id', 0);
      $mid = is_array($media_row) ? (int)($media_row['id'] ?? 0) : 0;
      $sorter = sprintf('%04d', ($i + 1) * 10);
      $media_values = array(
         'update_date' => now(),
         'update_uid' => 1,
         'active' => 1,
         'title' => $slide['title'],
         'alt' => $slide['title'],
         'caption' => $slide['caption'],
         'file_name' => $slide['file'],
         'file_path' => $rel,
         'mime' => $dim['mime'],
         'size' => filesize($path),
         'width' => $dim[0],
         'height' => $dim[1],
         'tags' => 'tutorial,dbxapp',
         'usage' => '',
         'template' => '',
         'sorter' => $sorter,
         'thumb_file_path' => '',
         'thumb_width' => 0,
         'thumb_height' => 0,
         'media_type' => 'image',
         'storage_type' => 'local',
         'media_folder' => 'img/tutorial',
      );
      if ($mid > 0) {
         if ($db->update($media_dd, $media_values, $mid, 0) !== 1) {
            throw new RuntimeException('Tutorial-Medium konnte nicht aktualisiert werden: ' . $rel);
         }
      } else {
         $media_values += array(
            'create_date' => now(),
            'create_uid' => 1,
            'owner' => 1,
            'content_id' => 0,
            'folder_id' => 15,
            'slot' => 'gallery',
         );
         if ($db->insert($media_dd, $media_values, 0) !== 1) {
            throw new RuntimeException('Tutorial-Medium konnte nicht angelegt werden: ' . $rel);
         }
         $mid = (int)$db->get_insert_id();
      }
      $media_ids[$slide['file']] = $mid;
   }

   $ids = implode(',', array_map('intval', array_keys($pages)));
   $db->update($usage_dd, array('active' => 0, 'update_date' => now()), 'content_id IN (' . $ids . ") AND slot = 'gallery'", 0);
   $per_page_sort = [];
   foreach ($slides as $slide) {
      $page = (int)$slide['page'];
      $per_page_sort[$page] = ($per_page_sort[$page] ?? 0) + 10;
      if ($db->insert($usage_dd, array(
         'create_date' => now(),
         'create_uid' => 1,
         'update_date' => now(),
         'update_uid' => 1,
         'owner' => 1,
         'active' => 1,
         'media_id' => $media_ids[$slide['file']],
         'content_id' => $page,
         'folder_id' => 15,
         'slot' => 'gallery',
         'sorter' => sprintf('%04d', $per_page_sort[$page]),
         'template' => '',
         'caption' => $slide['caption'],
         'settings' => '',
      ), 0) !== 1) {
         throw new RuntimeException('Tutorial-Medienzuordnung konnte nicht angelegt werden: ' . $slide['file']);
      }
   }

   if ($db->commit($content_dd) !== 1 || $db->commit($media_dd) !== 1) {
      throw new RuntimeException('Tutorial-Transaktionen konnten nicht abgeschlossen werden.');
   }
} catch (Throwable $e) {
   $db->rollback($content_dd);
   $db->rollback($media_dd);
   throw $e;
}

echo 'updated tutorial pages and media usage' . PHP_EOL;
