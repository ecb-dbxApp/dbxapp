<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

trait dbxKiCmsDescribeServiceTrait {

   private function describe(): array {
      return array(
         'ok' => 1,
         'api_version' => self::API_VERSION,
         'module' => 'dbxKi',
         'purpose' => 'KI-optimierte Bedienung des dbXapp-CMS ohne direkten SQL-Zugriff.',
         'endpoint' => '?dbx_modul=dbxKi&dbx_run1=api',
         'authentication' => array(
            'read_and_preview' => 'Normale dbXapp-Modulberechtigung.',
            'execute' => 'Admin-Sitzung plus token.',
            'token' => dbx()->action_token(self::TOKEN_SCOPE),
            'token_scope' => self::TOKEN_SCOPE,
         ),
         'protocol' => array(
            'method' => 'GET oder POST; für komplexe Daten POST mit application/json verwenden.',
            'request' => array(
               'action' => 'Eine Aktion aus actions.',
               'mode' => 'preview oder execute; bei Leseaktionen wird mode ignoriert.',
               'params' => 'Aktionsparameter.',
               'token' => 'Nur für execute.',
               'expected_plan_id' => 'Optional. Verhindert Ausführung, falls sich der Plan seit preview geändert hat.',
               'confirm' => 'Bei Löschaktionen für execute zwingend true.',
            ),
            'automation' => array(
               'safe' => 'Erst preview aufrufen und execute_request aus der Antwort unverändert senden.',
               'direct' => 'Für vollautomatische Ausführung action, mode=execute, token und params direkt senden.',
               'rule' => 'Keine SQL-Befehle erzeugen. Ausschließlich diese Aktionen verwenden.',
            ),
         ),
         'page_workflows' => $this->page_workflows(),
         'languages' => dbxContentLngSync::accessible_lngs(),
         'actions' => $this->catalog(),
         'examples' => array(
            'preview_page_create' => array(
               'action' => 'page.create',
               'mode' => 'preview',
               'params' => array(
                  'lng' => 'de',
                  'folder_id' => 1,
                  'title' => 'Neue KI-Seite',
                  'content' => '<p>Inhalt</p>',
               ),
            ),
            'automatic_page_update' => array(
               'action' => 'page.update',
               'mode' => 'execute',
               'token' => dbx()->action_token(self::TOKEN_SCOPE),
               'params' => array(
                  'lng' => 'de',
                  'id' => 1,
                  'title' => 'Aktualisierter Titel',
               ),
            ),
            'translation' => array(
               'action' => 'translation.apply',
               'mode' => 'execute',
               'token' => dbx()->action_token(self::TOKEN_SCOPE),
               'params' => array(
                  'source_lng' => 'de',
                  'target_lng' => 'en',
                  'source_id' => 1,
                  'translation' => array(
                     'title' => 'Translated title',
                     'description' => 'Translated description',
                     'keywords' => 'translated, keywords',
                     'content' => '<p>Translated content</p>',
                     'seo_title' => 'Translated SEO title',
                  ),
               ),
            ),
         ),
      );
   }

   private function page_workflows(): array {
      return array(
         'contract' => array(
            'ki_role' => 'Die KI fuellt nur deklarierte Inhalte in answer.json und liefert erlaubte Assets. dbxKi rekonstruiert Aktionen aus dem signierten Vertrag.',
            'no_external_tools' => 'Keine eigenen PHP-, SQL-, Shell-, Python- oder Node-Tools fuer CMS-Aenderungen erzeugen.',
            'delivery' => 'antwort.zip mit unveraendertem auftrag.contract.json, answer.json und ausschliesslich deklarierten Assets.',
            'auto_execute' => 'Nicht erlaubt: Import zeigt immer eine Vorschau; Ausfuehrung erfordert einen dbxKi-Token.',
         ),
         'page_create' => array(
            'guide_action' => 'page.create_guide',
            'sequence' => array(
               'Arbeitskontext mit cms.snapshot oder page.create_guide lesen.',
               'Neue Medien zuerst mit media.create_base64 oder media.create_image_variant als Step anlegen.',
               'page.create mit lng, folder_id, title, template, content, description, keywords, activ anlegen.',
               'Inline-Bilder im content immer mit $ref:{media_step}.inline_src und data-cms-media-id setzen.',
               'Verwendete Inline-/Gallery-/Hero-Medien mit media.assign zuordnen.',
               'dbxKi bindet answer.json an den signierten Ablauf, validiert alle Schritte und zeigt die Vorschau.',
            ),
             'fixed_rules' => array(
                'folder_id, lng und title aus dem Auftrag nicht eigenmaechtig aendern.',
                'template nur aus Auftrag/Guide verwenden; bei Root-Seiten nie parent verwenden.',
                'Kein SQL, keine direkten Dateipfade files/media/... in img src.',
                'HTML ist erlaubt; Bootstrap-5-Klassen sind erlaubt; kein eigenes JavaScript.',
                'Hero-Bilder unter img/hero, Gallery-Bilder unter img/gallery, normale Inline-Bilder unter img/images.',
                'Ein Seitenkopf mit Bild und ueberlagertem Text ist immer ein CMS-Hero: Bild per media.assign slot=hero und hero_image_id/hero_template setzen; Hero-Text vor den dbx:hero-Marker schreiben.',
                'Niemals einen Hero als Inline-Bild mit position-relative/position-absolute im Content nachbauen.',
             ),
         ),
         'page_update' => array(
            'guide_action' => 'page.update_guide',
            'sequence' => array(
               'Bestehende Seite mit page.get oder page.update_guide lesen.',
               'Nur Felder aendern, die im Auftrag/change_fields erlaubt sind.',
               'Bei content-Aenderung vorhandene data-cms-media-id, dbx_mid-URLs, Links und Modulaufrufe erhalten, ausser der Auftrag fordert Aenderung.',
               'Bestehendes Hero-Bild ersetzen: page.hero_replace_image. Neues Hero-Bild setzen: page.hero_create_image.',
               'page.update nur fuer Seitenfelder verwenden; Medien danach bei Bedarf mit media.assign verknuepfen.',
               'dbxKi bindet answer.json an den signierten Ablauf, validiert alle Schritte und zeigt die Vorschau.',
            ),
             'fixed_rules' => array(
                'id, lng und permalink der Zielseite nicht eigenmaechtig aendern.',
                'Kein page.delete in KI-Auftraegen.',
                'Keine vorhandenen Medienpfade manuell umschreiben.',
                'Wenn content nicht in change_fields steht, content unveraendert lassen.',
                'Hero-Aenderungen nur ueber page.hero_replace_image/page.hero_create_image oder Hero-Felder plus media.assign slot=hero ausfuehren.',
                'Keinen Inline-Schein-Hero mit absolut positioniertem Text am Seitenanfang erzeugen.',
             ),
         ),
      );
   }

   private function page_create_guide(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $folder_id = max(0, (int)($params['folder_id'] ?? $params['folder'] ?? 0));
      $title = $this->clean($params['title'] ?? '___TITEL___', 254);
      $with_hero = $this->bool_value($params['with_hero'] ?? false);
      $with_gallery = $this->bool_value($params['with_gallery'] ?? false);
      $template = $this->clean($params['template'] ?? ($with_hero ? 'c-title-hero_header-gallery-body1-footer' : 'c-body1-footer'), 254);
      if ($folder_id === 0 && strtolower($template) === 'parent') {
         $template = 'c-body1-footer';
      }

      $steps = array();
      if ($with_hero) {
         $steps[] = array(
            'id' => 'hero',
            'action' => 'media.create_base64',
            'params' => array(
               'asset_ref' => 'hero.jpg',
               'file_name' => 'hero.jpg',
               'media_folder' => 'img/hero',
               'title' => $title . ' Hero',
               'alt' => $title,
            ),
         );
      }
      if ($with_gallery) {
         $steps[] = array(
            'id' => 'gallery_1',
            'action' => 'media.create_base64',
            'params' => array(
               'asset_ref' => 'gallery-1.jpg',
               'file_name' => 'gallery-1.jpg',
               'media_folder' => 'img/gallery',
               'title' => $title . ' Galerie',
               'alt' => $title,
            ),
         );
      }
      $steps[] = array(
         'id' => 'page',
         'action' => 'page.create',
         'params' => array(
            'lng' => $lng,
            'folder_id' => $folder_id,
            'title' => $title,
            'template' => $template,
            'hero_height' => $with_hero ? '300px' : 'parent',
            'description' => '___SEO_BESCHREIBUNG___',
            'keywords' => '___KEYWORDS___',
            'activ' => 1,
            'content' => '___HTML_CONTENT___',
         ),
      );
      if ($with_hero) {
         $steps[] = array(
            'id' => 'hero_assign',
            'action' => 'media.assign',
            'params' => array(
               'lng' => $lng,
               'media_id' => '$ref:hero.media_id',
               'content_id' => '$ref:page.page_id',
               'slot' => 'hero',
            ),
         );
      }
      if ($with_gallery) {
         $steps[] = array(
            'id' => 'gallery_assign_1',
            'action' => 'media.assign',
            'params' => array(
               'lng' => $lng,
               'media_id' => '$ref:gallery_1.media_id',
               'content_id' => '$ref:page.page_id',
               'slot' => 'gallery',
            ),
         );
      }

      return array(
         'workflow' => $this->page_workflows()['page_create'],
         'manifest' => array(
            'title' => $title,
            'recipe' => 'page.create.v1',
            'lng' => $lng,
            'auto_execute' => false,
         ),
         'job' => array('steps' => $steps),
         'content_contract' => $this->content_contract(),
      );
   }

   private function page_update_guide(array $params): array {
      $lng = $this->language($params['lng'] ?? '');
      $id = max(0, (int)($params['id'] ?? 0));
      $hero_mode = strtolower(trim((string)($params['hero_mode'] ?? 'none')));
      if (!in_array($hero_mode, array('none', 'replace', 'create'), true)) {
         $hero_mode = 'none';
      }
      $fields = $params['change_fields'] ?? array('content');
      if (is_string($fields)) {
         $fields = array_values(array_filter(array_map('trim', explode(',', $fields))));
      }
      if (!is_array($fields) || !$fields) {
         $fields = array('content');
      }

      $steps = array();
      if ($hero_mode === 'replace') {
         $steps[] = array(
            'id' => 'hero_replace',
            'action' => 'page.hero_replace_image',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'source_file' => 'assets/hero.jpg',
               'width' => 1280,
               'height' => 300,
               'fit' => 'cover',
            ),
         );
      } elseif ($hero_mode === 'create') {
         $steps[] = array(
            'id' => 'hero_create',
            'action' => 'page.hero_create_image',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'source_file' => 'assets/hero.jpg',
               'file_name' => 'hero.jpg',
               'width' => 1280,
               'height' => 300,
               'fit' => 'cover',
            ),
         );
      }

      $patch = array();
      foreach ($fields as $field) {
         $field = trim((string)$field);
         if ($field === '') continue;
         $patch[$field] = $field === 'content' ? '___HTML_CONTENT___' : '___' . strtoupper($field) . '___';
      }
      if ($patch) {
         $steps[] = array(
            'id' => 'page_update',
            'action' => 'page.update',
            'params' => array(
               'lng' => $lng,
               'id' => $id,
               'patch' => $patch,
            ),
         );
      }

      $current = array();
      if ($id > 0) {
         try {
            $current = $this->page_get(array('lng' => $lng, 'id' => $id));
         } catch (\Throwable $e) {
            $current = array('error' => $e->getMessage());
         }
      }

      return array(
         'workflow' => $this->page_workflows()['page_update'],
         'target' => array('lng' => $lng, 'id' => $id, 'change_fields' => array_values($fields), 'hero_mode' => $hero_mode),
         'current_page' => $current,
         'manifest' => array(
            'title' => 'Seite ' . $id . ' aktualisieren',
            'recipe' => 'page.update.v1',
            'lng' => $lng,
            'auto_execute' => false,
         ),
         'job' => array('steps' => $steps),
         'content_contract' => $this->content_contract(),
      );
   }

   private function content_contract(): array {
      return array(
         'html_allowed' => true,
         'bootstrap_allowed' => true,
         'forbidden' => array('SQL', 'direkte SQLite-Aenderungen', 'eigene PHP-Tools', 'eigene JavaScript-Logik im Content', 'files/media/... als img src', 'Inline-Schein-Hero mit position-relative/position-absolute'),
         'inline_media' => 'Immer inline_src/index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid={id} plus data-cms-media-id verwenden.',
         'hero' => array(
            'image' => 'Das Hero-Bild gehoert in die CMS-Hero-Zuordnung (slot=hero und hero_image_id), niemals in content.',
            'text' => 'Hero-Text steht vor dem hr-Marker data-dbx-marker="dbx:hero".',
            'validation' => 'dbxKi lehnt einen Inline-Bildblock mit absolut ueberlagertem Hero-Text am Seitenanfang ab.',
         ),
         'openwin' => 'openWin nur ueber class dbx-win und data-dbx="lib=openWin|url=...|title=...|width=...|height=..." verwenden.',
         'markers' => array(
            'dbx:hero' => 'Text davor wird Hero-Text.',
            'dbx:header' => 'Text danach bis zum naechsten Marker wird Header.',
            'dbx:footer' => 'Text danach wird Footer.',
         ),
      );
   }

   private function catalog(): array {
      return array(
         'system.health' => array(
            'write' => false,
            'description' => 'Prüft Modul, Benutzer, Sprachen und CMS-Datenzugriff.',
            'params' => array(),
         ),
         'cms.snapshot' => array(
            'write' => false,
            'description' => 'Liefert Ordner, Seiten und Medien in einem begrenzten Arbeitskontext.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Optionaler Ordner', 'limit' => '1..200'),
         ),
         'folder.list' => array(
            'write' => false,
            'description' => 'Listet CMS-Ordner.',
            'params' => array('lng' => 'Sprachcode', 'parent_id' => 'Optionaler Parent', 'limit' => '1..500'),
         ),
         'folder.get' => array(
            'write' => false,
            'description' => 'Liest einen Ordner.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID'),
         ),
         'folder.create' => array(
            'write' => true,
            'description' => 'Erstellt einen Ordner.',
            'required' => array('name'),
            'params' => array(
               'lng' => 'Sprachcode',
               'name' => 'Bezeichnung',
               'parent_id' => 'Parent-ID, Standard 0',
               'group_read' => 'parent oder kommaseparierte Gruppen',
               'template' => 'Content-Template',
               'hero_*' => 'Optionale Hero-Einstellungen',
            ),
         ),
         'folder.update' => array(
            'write' => true,
            'description' => 'Ändert oder verschiebt einen Ordner.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID', 'patch' => 'Zu ändernde Felder oder Felder direkt in params'),
         ),
         'folder.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht einen leeren Ordner in einer Sprache.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Ordner-ID'),
         ),
         'page.list' => array(
            'write' => false,
            'description' => 'Listet CMS-Seiten.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Optionaler Ordner', 'limit' => '1..500'),
         ),
         'page.get' => array(
            'write' => false,
            'description' => 'Liest eine Seite einschließlich Medienzuordnungen.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID'),
         ),
         'page.create_guide' => array(
            'write' => false,
            'description' => 'Liefert eine lesende Vorschau des intern von dbxKi erzeugten Ablaufs fuer das Anlegen einer CMS-Seite.',
            'params' => array('lng' => 'Sprachcode', 'folder_id' => 'Zielordner', 'title' => 'Seitentitel', 'with_hero' => '0/1', 'with_gallery' => '0/1'),
         ),
         'page.update_guide' => array(
            'write' => false,
            'description' => 'Liefert eine lesende Vorschau des intern von dbxKi erzeugten Ablaufs fuer das Aendern einer CMS-Seite.',
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID', 'change_fields' => 'Liste erlaubter Felder', 'hero_mode' => 'none, replace oder create'),
         ),
         'page.create' => array(
            'write' => true,
            'description' => 'Erstellt eine CMS-Seite.',
            'required' => array('title'),
            'params' => array(
               'lng' => 'Sprachcode',
               'folder_id' => 'Ordner-ID',
                'title' => 'Titel',
                'seo_title' => 'Optionaler SEO-Titel; Standard ist der Seitentitel',
                'content' => 'HTML-Inhalt',
               'description' => 'Meta-Beschreibung',
               'keywords' => 'Meta-Keywords',
               'permalink' => 'Optional; wird sonst erzeugt',
               'activ' => '0 oder 1',
               'template' => 'Content-Template',
            ),
         ),
         'page.update' => array(
            'write' => true,
            'description' => 'Aktualisiert ausgewählte Seitenfelder. Inline-Bilder in content werden automatisch auf CMS-Medien-URLs (dbx_mid) normalisiert.',
            'required' => array('id'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'patch' => 'Zu ändernde Felder oder Felder direkt in params',
               'package_product_image' => 'Optional 1: Paket-Card auf vorhandenes Produktbild (home-package-*) umstellen',
               'package_media_id' => 'Optional: Medien-ID statt Permalink-Zuordnung',
               'package_image_alt' => 'Optional: alt-Text fuer das Produktbild',
            ),
         ),
         'page.hero_replace_image' => array(
            'write' => true,
            'description' => 'Ersetzt nur die bestehende Hero-Bilddatei einer Seite. Medienverknüpfung und Seitenfelder bleiben unverändert.',
            'required' => array('id', 'source_file'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'source_file' => 'Absolute oder dbxapp-relative neue Bildquelle',
               'width' => 'Optional; Standard Breite des bestehenden Hero-Mediums',
               'height' => 'Optional; Standard Höhe des bestehenden Hero-Mediums',
               'fit' => 'cover oder contain, Standard cover',
               'quality' => '1..100, Standard 82',
            ),
         ),
         'page.hero_create_image' => array(
            'write' => true,
            'description' => 'Erstellt ein neues Hero-Bild in files/media/img/hero und setzt es als Hero der Seite.',
            'required' => array('id', 'source_file'),
            'params' => array(
               'lng' => 'Sprachcode',
               'id' => 'Seiten-ID',
               'source_file' => 'Absolute oder dbxapp-relative Bildquelle',
               'file_name' => 'Optionaler Dateiname, Standard aus Permalink',
               'width' => 'Zielbreite, Standard 1280',
               'height' => 'Zielhöhe, Standard 300',
               'fit' => 'cover oder contain, Standard cover',
               'quality' => '1..100, Standard 82',
            ),
         ),
         'page.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht eine Seite in einer Sprache und deaktiviert ihre Medienzuordnungen.',
            'required' => array('id'),
            'params' => array('lng' => 'Sprachcode', 'id' => 'Seiten-ID'),
         ),
         'media.list' => array(
            'write' => false,
            'description' => 'Listet aktive Medien und optional deren Zuordnungen.',
            'params' => array('media_type' => 'image, video, file oder external_video', 'folder' => 'Medienordner', 'limit' => '1..500'),
         ),
         'media.get' => array(
            'write' => false,
            'description' => 'Liest ein Medium und seine aktiven Verwendungen.',
            'params' => array('id' => 'Medien-ID'),
         ),
         'module.assets' => array(
            'write' => false,
            'description' => 'Listet vorhandene Modulbilder aus dbx/modules/*/tpl/mod und files/mod fuer Content- und Modul-Visualisierungen.',
            'params' => array('module' => 'Optionaler Modulname', 'limit' => '1..500'),
         ),
         'media.create_base64' => array(
            'write' => true,
            'description' => 'Speichert eine Base64-Datei über dbXapp und registriert sie als Medium. Liefert inline_src/inline_img fuer die Content-Einbindung.',
            'required' => array('file_name', 'data_base64'),
            'params' => array(
               'file_name' => 'Dateiname mit Endung',
               'data_base64' => 'Reines Base64 oder Data-URL',
               'media_folder' => 'Standard img/images, img/video oder file/ki; Hero immer img/hero, Gallery immer img/gallery',
               'title' => 'Titel',
               'alt' => 'Alternativtext',
               'caption' => 'Bildunterschrift',
               'tags' => 'Tags',
            ),
            'returns' => array('id', 'row', 'inline_src', 'inline_img'),
            'usage' => 'Im Content immer inline_src oder inline_img verwenden. Niemals files/media/... direkt in img src setzen.',
         ),
         'media.create_image_variant' => array(
            'write' => true,
            'description' => 'Erzeugt aus einer lokalen Bildquelle eine zugeschnittene, skalierte und optional farblich getönte Bildvariante und registriert sie als Medium. Liefert inline_src/inline_img fuer die Content-Einbindung.',
            'required' => array('source_file', 'file_name'),
            'params' => array(
               'source_file' => 'Absolute oder dbxapp-relative Quelldatei',
               'file_name' => 'Zieldateiname mit .webp, .jpg, .jpeg oder .png',
               'width' => 'Zielbreite, Standard Originalbreite',
               'height' => 'Zielhöhe, Standard Originalhöhe',
               'fit' => 'cover oder contain, Standard cover',
               'crop_x/crop_y/crop_width/crop_height' => 'Optionaler Quell-Ausschnitt in Pixeln vor dem Skalieren',
               'tint' => 'Optionale Farbe als #RRGGBB',
               'tint_strength' => '0..1, Standard 0',
               'quality' => '1..100, Standard 82',
               'media_folder' => 'Standard img/images; Hero immer img/hero, Gallery immer img/gallery',
               'title' => 'Titel',
               'alt' => 'Alternativtext',
               'caption' => 'Bildunterschrift',
               'tags' => 'Tags',
            ),
            'returns' => array('id', 'row', 'inline_src', 'inline_img'),
            'usage' => 'Im Content immer inline_src oder inline_img verwenden. Niemals files/media/... direkt in img src setzen.',
         ),
         'media.update' => array(
            'write' => true,
            'description' => 'Ändert Metadaten eines Mediums.',
            'required' => array('id'),
            'params' => array('id' => 'Medien-ID', 'patch' => 'title, alt, caption, tags, template'),
         ),
         'media.assign' => array(
            'write' => true,
            'description' => 'Ordnet ein Medium einer Seite oder einem Ordner zu.',
            'required' => array('media_id'),
            'params' => array(
               'media_id' => 'Medien-ID',
               'content_id' => 'Seiten-ID',
               'folder_id' => 'Ordner-ID',
               'slot' => 'hero, gallery, inline, header, teaser oder footer',
               'template' => 'Darstellungs-Template',
               'caption' => 'Kontextspezifische Bildunterschrift',
               'settings' => 'Objekt oder JSON-Text',
            ),
         ),
         'media.unassign' => array(
            'write' => true,
            'description' => 'Deaktiviert eine Medienzuordnung.',
            'required' => array('usage_id'),
            'params' => array('usage_id' => 'ID aus dbxMediaUsage'),
         ),
         'media.delete' => array(
            'write' => true,
            'destructive' => true,
            'description' => 'Löscht ein unbenutztes Medium einschließlich lokaler Datei.',
            'required' => array('id'),
            'params' => array('id' => 'Medien-ID'),
         ),
         'translation.preview' => array(
            'write' => false,
            'description' => 'Liefert Quelltext, vorhandenes Ziel und genaue Übersetzungsanweisung.',
            'required' => array('source_lng', 'target_lng', 'source_id'),
            'params' => array('source_lng' => 'Quellsprache', 'target_lng' => 'Zielsprache', 'source_id' => 'Quellseiten-ID'),
         ),
         'translation.apply' => array(
            'write' => true,
            'description' => 'Speichert eine von der KI gelieferte Übersetzung; kein externer Übersetzungsdienst nötig.',
            'required' => array('source_lng', 'target_lng', 'source_id', 'translation'),
            'params' => array(
               'source_lng' => 'Quellsprache',
               'target_lng' => 'Zielsprache',
               'source_id' => 'Quellseiten-ID',
               'translation' => 'Objekt mit title, description, keywords und content; optional seo_title sowie img_alt_1..3 und img_des_1..3',
               'copy_media' => '1 kopiert aktive Medienzuordnungen, Standard 1',
            ),
         ),
         'translation.sync_all' => array(
            'write' => true,
            'description' => 'Übersetzt eine komplette CMS-Sprachstruktur aus einer Quellsprache in eine oder mehrere Zielsprachen.',
            'required' => array('source_lng'),
            'params' => array(
               'source_lng' => 'Quellsprache',
               'target_lngs' => 'Optional: Array oder kommaseparierte Zielsprachen; Standard alle aktiven Sprachen außer source_lng',
               'root_folder_id' => 'Optional: Ordner-Teilbaum; Standard 0 = alle Ordner und Seiten',
               'update_existing' => '1 aktualisiert vorhandene Zielseiten/-ordner, Standard 1',
               'skip_manual' => '1 überspringt Ziel-Datensätze mit lng_sync=manual, Standard 0',
               'copy_media' => '1 kopiert aktive Medienzuordnungen, Standard 1',
               'replace_media_usage' => '1 ersetzt Medienzuordnungen der Zielseite; Standard 0 = nur fehlende ergänzen',
            ),
         ),
      );
   }

   private function health(): array {
      $this->ensure_schema();
      return array(
         'ok' => 1,
         'api_version' => self::API_VERSION,
         'user_id' => (int)dbx()->user(),
         'admin' => 1,
         'execute_enabled' => (int)dbx()->get_cfg('dbxKi', 'allow_execute', 1),
         'languages' => dbxContentLngSync::accessible_lngs(),
         'master_language' => dbxContentLngSync::master_lng(),
         'content_count' => $this->db->count(dbxContentLng::dd_content($this->language(''))),
         'folder_count' => $this->db->count(dbxContentLng::dd_folder($this->language(''))),
         'media_count' => $this->db->count('dbxMedia', 'active = 1'),
      );
   }
}
