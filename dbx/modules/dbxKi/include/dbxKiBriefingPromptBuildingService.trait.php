<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPromptBuildingServiceTrait {

   private function buildStartMd(string $recipe, string $taskLabel, bool $heroAssets, string $contextHint, string $contentTemplate = ''): string {
      if ($contentTemplate === '') {
         $contentTemplate = $heroAssets ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $zipExtra = $heroAssets
         ? "- `assets/hero.jpg` (" . $this->heroImageSpecText() . ")\n"
         : '';
      $assetsShort = $heroAssets
         ? '**Ja:** genau `assets/hero.jpg` — ' . $this->heroImageSpecText() . '. `asset_ref` = `hero.jpg` (nicht aendern).'
         : '**Nein.** Keinen `assets/` Ordner anlegen.';
      return $this->renderTemplateFile('ki-start.md', array(
         'task_label' => $taskLabel,
         'recipe' => $recipe,
         'zip_extra' => $zipExtra,
         'assets_short' => $assetsShort,
         'context_hint' => $contextHint,
         'content_layout_short' => $this->contentMarkersGuideShort($contentTemplate),
      ));
   }

   private function bundleRulesForRecipe(string $recipe, bool $withHero = false, string $contentTemplate = ''): array {
      $actions = array();
      switch ($recipe) {
         case 'page.create.v1':
            $actions = $withHero
               ? array('media.create_base64', 'page.create', 'media.assign')
               : array('page.create');
            break;
         case 'page.update.v1':
            $actions = $withHero
               ? array('page.hero_replace_image', 'page.hero_create_image', 'media.create_base64', 'page.update', 'media.assign')
               : array('page.update');
            break;
         case 'translation.v1':
            $actions = array('translation.apply', 'page.update');
            break;
      }
      return array(
         'recipe' => $recipe,
         'allowed_actions' => $actions,
         'refs' => '$ref:{step_id}.{field}',
         'asset_ref' => 'Dateiname relativ zu assets/, z.B. hero.jpg',
         'forbidden' => array('*.delete', 'data_base64'),
         'content' => $this->contentRulesForBriefing($contentTemplate, $withHero),
      );
   }

   private function contentRulesForBriefing(string $contentTemplate, bool $withHero): array {
      if ($contentTemplate === '') {
         $contentTemplate = $withHero ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $slots = $this->analyzeTemplateSlots($contentTemplate);
      $markers = $this->contentMarkersMeta($slots);
      $rules = array(
         'format' => 'HTML in page.create/page.update content oder translation.content',
         'marker_type' => 'hr',
         'markers' => $markers,
         'marker_order' => array('hero', 'header', 'footer'),
         'marker_attributes' => 'class="dbx-cms-marker", data-dbx-marker="dbx:hero|header|footer", data-label',
         'sections' => array(
            'before_hero' => 'Hero-Text ({cms:hero_text})',
            'between_hero_and_header' => 'Header ({cms:header}) — eigener Abschnitt nach Hero, vor Body',
            'between_header_and_footer' => 'Body ({cms:col1})',
            'after_footer' => 'Footer ({cms:footer})',
         ),
         'missing_marker' => 'Bereich entfaellt, Text gehoert zum Body',
         'no_column_markers' => 'Spalten-Marker (col2/col3a/col3b) nicht von der KI setzen',
          'hero_image' => 'Hero-Bild nicht im HTML. Bestehendes Hero aendern: page.hero_replace_image. Neues Hero setzen: page.hero_create_image oder media.create_base64 + media.assign mit media_folder=img/hero.',
          'fake_hero_forbidden' => 'Ein Seitenkopf aus Inline-Bild plus position-relative/position-absolute Textebene ist verboten. Das Bild gehoert in slot=hero/hero_image_id; der Text vor den dbx:hero-Marker.',
         'hero_height_default' => self::HERO_DEFAULT_HEIGHT . ' in page.create/page.update setzen, wenn ein Hero-Bild neu angelegt wird und nichts anderes angegeben ist',
         'hero_text_default' => 'Hero-Text maximal ' . self::HERO_TEXT_MAX_LINES . ' Zeilen, wenn nicht anders angegeben',
         'inline_images' => 'CMS-Medien nie als files/media/... in img src setzen. Nach media.create_* inline_src oder inline_img verwenden: index.php?dbx_modul=dbxContent&dbx_run1=media&dbx_mid={id} plus data-cms-media-id.',
         'package_pages' => 'Paket-Detailseiten (dbxapp-paket-*) per page.update mit patch.package_product_image=true auf home-package-* Produktbild umstellen. page.get liefert package_hint.',
      );
      $rules['template'] = $contentTemplate;
      $rules['template_slots'] = $slots;
      return $rules;
   }

   private function sanitizeEmbeddedPolicy(string $policy): string {
      $policy = strtolower(trim($policy));
      return in_array($policy, array('modify', 'preserve', 'reorder', 'remove'), true) ? $policy : 'preserve';
   }

   private function embeddedPolicyText(string $policy, string $notes): string {
      $policy = $this->sanitizeEmbeddedPolicy($policy);
      $notes = trim($notes);
      $lines = array(
         'Standard: Bestehende eingebettete Medien, Videos und `[modul=...]...[/modul]`-Aufrufe exakt beibehalten.',
         'Keine bestehenden Medienpfade manuell umschreiben; dbxKi/CMS-Befehle loesen Speicherpfade automatisch.',
      );
      if ($policy === 'modify') {
         $lines[] = 'Erlaubt: vorhandene Medien/Module inhaltlich aendern oder neue hinzufuegen, aber nur soweit es zur gewuenschten Aenderung passt.';
      } elseif ($policy === 'reorder') {
         $lines[] = 'Erlaubt: vorhandene Medien/Module neu anordnen, aber nur soweit es zur gewuenschten Aenderung passt.';
      } elseif ($policy === 'remove') {
         $lines[] = 'Erlaubt: vorhandene Medien/Module entfernen, aber nur wenn unten konkret beschrieben ist, was weg soll.';
      } else {
         $lines[] = 'Nicht erlaubt: Medien/Module entfernen, ersetzen oder neu anordnen.';
      }
      if ($notes !== '') {
         $lines[] = 'Konkrete Anweisung zu Medien/Modulen: ' . $notes;
      }
      return implode("\n", $lines);
   }

   private function moduleCallsFromContent(string $content): array {
      $calls = array();
      if (preg_match_all('/\[modul=([A-Za-z0-9_]+)\]([\s\S]*?)\[\/modul\]/i', $content, $m, PREG_SET_ORDER)) {
         foreach ($m as $idx => $match) {
            $calls[] = array(
               'index' => $idx + 1,
               'modul' => (string) ($match[1] ?? ''),
               'params' => trim((string) ($match[2] ?? '')),
               'marker' => (string) ($match[0] ?? ''),
            );
         }
      }
      return $calls;
   }

   private function inlineMediaIdsFromContent(string $content): array {
      $ids = array();
      if (preg_match_all('/data-cms-media-id=["\']?([0-9]+)/i', $content, $m)) {
         foreach ($m[1] as $id) {
            $ids[(int) $id] = (int) $id;
         }
      }
      if (preg_match_all('/(?:dbx_mid|media_id)=([0-9]+)/i', $content, $m)) {
         foreach ($m[1] as $id) {
            $ids[(int) $id] = (int) $id;
         }
      }
      return array_values(array_filter($ids));
   }

   private function mediaContextForPage(int $pageId, string $content): array {
      $db = dbx()->get_system_obj('dbxDB');
      $mediaIds = $this->inlineMediaIdsFromContent($content);
      $usageRows = $db->select('dbxMediaUsage', dbxContentMediaUsageScope::withLanguage('content_id = ' . (int) $pageId . ' AND active = 1'), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      if (!is_array($usageRows)) {
         $usageRows = array();
      }
      foreach ($usageRows as $usage) {
         $id = (int) ($usage['media_id'] ?? 0);
         if ($id > 0) {
            $mediaIds[$id] = $id;
         }
      }

      $rows = array();
      foreach (array_values(array_unique($mediaIds)) as $id) {
         $media = $db->select1('dbxMedia', (int) $id);
         if (!is_array($media)) {
            $rows[] = array('id' => (int) $id, 'missing' => 1);
            continue;
         }
         $usage = array_values(array_filter($usageRows, function ($row) use ($id) {
            return (int) ($row['media_id'] ?? 0) === (int) $id;
         }));
         $rows[] = array(
            'id' => (int) $id,
            'title' => (string) ($media['title'] ?? $media['file_name'] ?? ''),
            'file_name' => (string) ($media['file_name'] ?? ''),
            'media_type' => (string) ($media['media_type'] ?? ''),
            'mime' => (string) ($media['mime'] ?? ''),
            'slots' => array_values(array_unique(array_map(function ($row) {
               return (string) ($row['slot'] ?? '');
            }, $usage))),
            'inline_reference' => in_array((int) $id, $this->inlineMediaIdsFromContent($content), true) ? 1 : 0,
         );
      }
      return $rows;
   }

   private function renderedTextForPage(string $lng, int $pageId): string {
      try {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $html = $this->withContentLng($lng, function () use ($renderer, $pageId) {
            return $renderer->render($pageId);
         });
         return $this->truncate(strip_tags((string) $html), 12000);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function pageContextForKi(string $lng, array $page): array {
      $pageId = (int) ($page['id'] ?? 0);
      $content = (string) ($page['content'] ?? '');
      return array(
         'render_reference' => '[modul=dbxContent]dbx_run1=show&cid=' . $pageId . '[/modul]',
         'folder_label' => $this->folderLabel($lng, (int) ($page['folder'] ?? 0)),
         'template' => (string) ($page['template'] ?? ''),
         'rendered_text_excerpt' => $this->renderedTextForPage($lng, $pageId),
         'embedded_media' => $this->mediaContextForPage($pageId, $content),
         'module_calls' => $this->moduleCallsFromContent($content),
         'rules' => array(
            'default' => 'Preserve embedded media and module calls exactly unless the briefing explicitly allows reorder/remove.',
            'media_paths' => 'Do not rewrite existing media paths manually. Keep existing HTML/media references or use dbxKi media steps for new hero assets.',
         ),
      );
   }

   private function embeddedSummaryForPrompt(array $context): string {
      $media = is_array($context['embedded_media'] ?? null) ? $context['embedded_media'] : array();
      $modules = is_array($context['module_calls'] ?? null) ? $context['module_calls'] : array();
      $lines = array();
      $lines[] = 'Eingebettete Medien: ' . count($media);
      foreach ($media as $row) {
         $label = '#' . (int) ($row['id'] ?? 0);
         $title = trim((string) ($row['title'] ?? $row['file_name'] ?? ''));
         $slots = implode(',', array_filter((array) ($row['slots'] ?? array())));
         $lines[] = '- ' . $label . ($title !== '' ? ' ' . $title : '') . ($slots !== '' ? ' [' . $slots . ']' : '');
      }
      $lines[] = 'Modul-Aufrufe: ' . count($modules);
      foreach ($modules as $call) {
         $lines[] = '- [modul=' . (string) ($call['modul'] ?? '') . ']' . (string) ($call['params'] ?? '') . '[/modul]';
      }
      return implode("\n", $lines);
   }

   private function renderPageContextHtml(string $lng, array $page): string {
      $context = $this->pageContextForKi($lng, $page);
      $media = is_array($context['embedded_media'] ?? null) ? $context['embedded_media'] : array();
      $modules = is_array($context['module_calls'] ?? null) ? $context['module_calls'] : array();
      $mediaHtml = '';
      foreach ($media as $row) {
         $title = trim((string) ($row['title'] ?? $row['file_name'] ?? ''));
         $slots = implode(', ', array_filter((array) ($row['slots'] ?? array())));
         $mediaHtml .= '<li><code>#' . (int) ($row['id'] ?? 0) . '</code> '
            . $this->esc($title !== '' ? $title : 'Medium')
            . ($slots !== '' ? ' <span class="text-muted">(' . $this->esc($slots) . ')</span>' : '')
            . '</li>';
      }
      if ($mediaHtml === '') {
         $mediaHtml = '<li class="text-muted">Keine eingebetteten Medien erkannt.</li>';
      }

      $moduleHtml = '';
      foreach ($modules as $call) {
         $moduleHtml .= '<li><code>[modul=' . $this->esc($call['modul'] ?? '') . ']</code> '
            . '<span class="text-muted">' . $this->esc($call['params'] ?? '') . '</span></li>';
      }
      if ($moduleHtml === '') {
         $moduleHtml = '<li class="text-muted">Keine Modul-Aufrufe erkannt.</li>';
      }

      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-update-context', array(
         'page_id' => (string) (int) ($page['id'] ?? 0),
         'page_title' => $this->esc((string) ($page['title'] ?? '')),
         'folder_label' => $this->esc((string) ($context['folder_label'] ?? '')),
         'template' => $this->esc((string) ($context['template'] ?? '')),
         'permalink' => $this->esc((string) ($page['permalink'] ?? '')),
         'media_count' => (string) count($media),
         'module_count' => (string) count($modules),
         'media_items' => $mediaHtml,
         'module_items' => $moduleHtml,
      ));
   }

   private function createPlacementTitle(string $lng, int $folderId, int $afterPageId): string {
      $folder = $folderId > 0 ? $this->folderLabel($lng, $folderId) : '';
      if ($afterPageId > 0) {
         try {
            $page = $this->loadPage($lng, $afterPageId);
            return 'Unter #' . $afterPageId . ' ' . (string) ($page['title'] ?? '') . ' in ' . $folder;
         } catch (\Throwable $e) {
            return $folder !== '' ? $folder : 'Zielposition waehlen';
         }
      }
      return $folder !== '' ? $folder . ' / am Ende' : 'Zielposition waehlen';
   }

   private function renderCreatePlacementHtml(string $lng, int $folderId, int $afterPageId): string {
      $folderLabel = $folderId > 0 ? $this->folderLabel($lng, $folderId) : '';
      $pageTitle = '';
      $sorter = '';
      if ($afterPageId > 0) {
         try {
            $page = $this->loadPage($lng, $afterPageId);
            $pageTitle = (string) ($page['title'] ?? '');
            $folderId = (int) ($page['folder'] ?? $folderId);
            $folderLabel = $this->folderLabel($lng, $folderId);
            $sorter = $this->sorterAfterPage($lng, $afterPageId);
         } catch (\Throwable $e) {
            $afterPageId = 0;
         }
      }
      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-create-placement', array(
         'folder_id' => $folderId > 0 ? (string) $folderId : '-',
         'folder_label' => $this->esc($folderLabel !== '' ? $folderLabel : 'Noch kein Zielordner gewaehlt'),
         'after_page_id' => $afterPageId > 0 ? (string) $afterPageId : '-',
         'after_page_title' => $this->esc($pageTitle !== '' ? $pageTitle : 'Keine Seite als Sortieranker'),
         'sorter' => $this->esc($sorter !== '' ? $sorter : 'automatisch am Ende'),
      ));
   }

   private function zipStructureCreate(bool $heroEnabled): string {
      if ($heroEnabled) {
         return "antwort.zip\n"
            . "├── auftrag.contract.json  (unveraendert aus dem Auftrag kopieren)\n"
            . "├── answer.json\n"
            . "├── README.md\n"
            . "└── assets/\n"
            . "    └── hero.jpg";
      }
      return "antwort.zip\n"
         . "├── auftrag.contract.json  (unveraendert aus dem Auftrag kopieren)\n"
         . "├── answer.json\n"
         . "└── README.md";
   }

   private function zipStructureUpdate(bool $heroChange): string {
      if ($heroChange) {
         return "antwort.zip\n"
            . "├── auftrag.contract.json  (unveraendert aus dem Auftrag kopieren)\n"
            . "├── answer.json\n"
            . "├── README.md\n"
            . "└── assets/\n"
            . "    └── hero.jpg";
      }
      return "antwort.zip\n"
         . "├── auftrag.contract.json  (unveraendert aus dem Auftrag kopieren)\n"
         . "├── answer.json\n"
         . "└── README.md";
   }

   private function assetsRulesCreate(bool $heroEnabled, string $heroBrief): string {
      if ($heroEnabled) {
         $brief = $heroBrief !== '' ? $heroBrief : 'passend zum Seitenthema';
         return "**Hero-Bild ist vorgesehen** (`briefing.hero.enabled = true`):\n\n"
            . "1. Lege **genau eine** Datei an: `assets/hero.jpg` (" . $this->heroImageSpecText() . ").\n"
            . "2. Motiv: " . $brief . "\n"
            . "3. Den Alt-Text nur im vorgesehenen Feld `hero.alt` in `answer.json` eintragen.\n"
            . "4. Hero-Medienordner bleibt `img/hero`.\n"
            . "5. **Kein** `data_base64` eintragen. **Keine** weiteren Dateien in `assets/`.\n"
            . "6. `auftrag.contract.json` niemals aendern.";
      }
      return "**Kein Hero-Bild** (`briefing.hero.enabled = false`):\n\n"
         . "1. **Keinen** `assets/` Ordner in der Antwort-ZIP anlegen.\n"
         . "2. Keine Medien-Steps — `job.vorlage.json` enthaelt nur `page.create`.\n"
         . "3. Nicht ueber Bilder nachdenken; direkt Text/HTML in `content` schreiben.";
   }

   private function assetsRulesUpdate(bool $heroChange, string $heroBrief): string {
      if ($heroChange) {
         $brief = $heroBrief !== '' ? $heroBrief : 'passend zur Seite';
         return "**Neues Hero-Bild** (`hero` in change_fields):\n\n"
            . "1. Lege `assets/hero.jpg` an (" . $this->heroImageSpecText() . "). Motiv: " . $brief . "\n"
            . "2. Fuer ein wirklich neues Hero-Bild: neue Medienverknuepfung in `img/hero` setzen.\n"
            . "3. Fuer eine reine Aenderung des bestehenden Hero-Bildes: nur die bestehende Hero-Datei ersetzen, keine neue Verknuepfung.\n"
            . "4. `asset_ref` bleibt `hero.jpg`. Kein `data_base64`. Keine weiteren Assets.";
      }
      return "**Kein Hero-Wechsel** (kein `hero` in change_fields):\n\n"
         . "1. **Keinen** `assets/` Ordner anlegen.\n"
         . "2. Nur die vorgesehenen Felder in `answer.json` ausfuellen.";
   }

   private function assetsReadmeHero(string $heroBrief): string {
      return "KI: Lege hier die Datei hero.jpg ab.\n\n"
         . "Pfad in der Antwort-ZIP: assets/hero.jpg\n"
         . "Groesse: " . $this->heroImageSpecText() . "\n"
         . "Der Dateiname ist im signierten Auftrag fest vorgegeben.\n"
         . "Motiv: " . ($heroBrief !== '' ? $heroBrief : 'passend zum Seitenthema') . "\n";
   }

   private function renderTemplateFile(string $basename, array $vars): string {
      $path = dirname(__DIR__) . '/tpl/briefing/' . $basename;
      if (!is_file($path)) {
         return '';
      }
      $text = file_get_contents($path);
      if (!is_string($text)) {
         return '';
      }
      $replacements = array();
      foreach ($vars as $key => $value) {
         $replacements['{' . $key . '}'] = (string)$value;
      }
      // Ein Durchlauf: Inhalte aus Briefing-/Freitextfeldern duerfen selbst
      // keine weiteren Template-Platzhalter aktivieren.
      return strtr($text, $replacements);
   }



}
