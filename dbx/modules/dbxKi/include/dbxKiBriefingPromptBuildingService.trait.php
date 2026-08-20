<?php
namespace dbx\dbxKi;

use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;

trait dbxKiBriefingPromptBuildingServiceTrait {

   private function build_start_md(string $recipe, string $task_label, bool $hero_assets, string $context_hint, string $content_template = ''): string {
      if ($content_template === '') {
         $content_template = $hero_assets ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $zip_extra = $hero_assets
         ? "- `assets/hero.jpg` (" . $this->hero_image_spec_text() . ")\n"
         : '';
      $assets_short = $hero_assets
         ? '**Ja:** genau `assets/hero.jpg` — ' . $this->hero_image_spec_text() . '. `asset_ref` = `hero.jpg` (nicht aendern).'
         : '**Nein.** Keinen `assets/` Ordner anlegen.';
      return $this->render_template_file('ki-start.md', array(
         'task_label' => $task_label,
         'recipe' => $recipe,
         'zip_extra' => $zip_extra,
         'assets_short' => $assets_short,
         'context_hint' => $context_hint,
         'content_layout_short' => $this->content_markers_guide_short($content_template),
      ));
   }

   private function bundle_rules_for_recipe(string $recipe, bool $with_hero = false, string $content_template = ''): array {
      $actions = array();
      switch ($recipe) {
         case 'page.create.v1':
            $actions = $with_hero
               ? array('media.create_base64', 'page.create', 'media.assign')
               : array('page.create');
            break;
         case 'page.update.v1':
            $actions = $with_hero
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
         'content' => $this->content_rules_for_briefing($content_template, $with_hero),
      );
   }

   private function content_rules_for_briefing(string $content_template, bool $with_hero): array {
      if ($content_template === '') {
         $content_template = $with_hero ? self::CONTENT_TEMPLATE_DEFAULT : 'parent';
      }
      $slots = $this->analyze_template_slots($content_template);
      $markers = $this->content_markers_meta($slots);
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
      $rules['template'] = $content_template;
      $rules['template_slots'] = $slots;
      return $rules;
   }

   private function sanitize_embedded_policy(string $policy): string {
      $policy = strtolower(trim($policy));
      return in_array($policy, array('modify', 'preserve', 'reorder', 'remove'), true) ? $policy : 'preserve';
   }

   private function embedded_policy_text(string $policy, string $notes): string {
      $policy = $this->sanitize_embedded_policy($policy);
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

   private function module_calls_from_content(string $content): array {
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

   private function inline_media_ids_from_content(string $content): array {
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

   private function media_context_for_page(int $page_id, string $content): array {
      $db = dbx()->get_system_obj('dbxDB');
      $media_ids = $this->inline_media_ids_from_content($content);
      $usage_rows = $db->select('dbxMediaUsage', dbxContentMediaUsageScope::with_language('content_id = ' . (int) $page_id . ' AND active = 1'), '*', 'slot,sorter,id', 'ASC', '', 0, 0, 0);
      if (!is_array($usage_rows)) {
         $usage_rows = array();
      }
      foreach ($usage_rows as $usage) {
         $id = (int) ($usage['media_id'] ?? 0);
         if ($id > 0) {
            $media_ids[$id] = $id;
         }
      }

      $rows = array();
      foreach (array_values(array_unique($media_ids)) as $id) {
         $media = $db->select1('dbxMedia', (int) $id);
         if (!is_array($media)) {
            $rows[] = array('id' => (int) $id, 'missing' => 1);
            continue;
         }
         $usage = array_values(array_filter($usage_rows, function ($row) use ($id) {
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
            'inline_reference' => in_array((int) $id, $this->inline_media_ids_from_content($content), true) ? 1 : 0,
         );
      }
      return $rows;
   }

   private function rendered_text_for_page(string $lng, int $page_id): string {
      try {
         $renderer = dbx()->get_include_obj('dbxContentRenderer', 'dbxContent');
         $html = $this->with_content_lng($lng, function () use ($renderer, $page_id) {
            return $renderer->render($page_id);
         });
         return $this->truncate(strip_tags((string) $html), 12000);
      } catch (\Throwable $e) {
         return '';
      }
   }

   private function page_context_for_ki(string $lng, array $page): array {
      $page_id = (int) ($page['id'] ?? 0);
      $content = (string) ($page['content'] ?? '');
      return array(
         'render_reference' => '[modul=dbxContent]dbx_run1=show&cid=' . $page_id . '[/modul]',
         'folder_label' => $this->folder_label($lng, (int) ($page['folder'] ?? 0)),
         'template' => (string) ($page['template'] ?? ''),
         'rendered_text_excerpt' => $this->rendered_text_for_page($lng, $page_id),
         'embedded_media' => $this->media_context_for_page($page_id, $content),
         'module_calls' => $this->module_calls_from_content($content),
         'rules' => array(
            'default' => 'Preserve embedded media and module calls exactly unless the briefing explicitly allows reorder/remove.',
            'media_paths' => 'Do not rewrite existing media paths manually. Keep existing HTML/media references or use dbxKi media steps for new hero assets.',
         ),
      );
   }

   private function embedded_summary_for_prompt(array $context): string {
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

   private function render_page_context_html(string $lng, array $page): string {
      $context = $this->page_context_for_ki($lng, $page);
      $media = is_array($context['embedded_media'] ?? null) ? $context['embedded_media'] : array();
      $modules = is_array($context['module_calls'] ?? null) ? $context['module_calls'] : array();
      $media_html = '';
      foreach ($media as $row) {
         $title = trim((string) ($row['title'] ?? $row['file_name'] ?? ''));
         $slots = implode(', ', array_filter((array) ($row['slots'] ?? array())));
         $media_html .= '<li><code>#' . (int) ($row['id'] ?? 0) . '</code> '
            . $this->esc($title !== '' ? $title : 'Medium')
            . ($slots !== '' ? ' <span class="text-muted">(' . $this->esc($slots) . ')</span>' : '')
            . '</li>';
      }
      if ($media_html === '') {
         $media_html = '<li class="text-muted">Keine eingebetteten Medien erkannt.</li>';
      }

      $module_html = '';
      foreach ($modules as $call) {
         $module_html .= '<li><code>[modul=' . $this->esc($call['modul'] ?? '') . ']</code> '
            . '<span class="text-muted">' . $this->esc($call['params'] ?? '') . '</span></li>';
      }
      if ($module_html === '') {
         $module_html = '<li class="text-muted">Keine Modul-Aufrufe erkannt.</li>';
      }

      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-update-context', array(
         'page_id' => (string) (int) ($page['id'] ?? 0),
         'page_title' => $this->esc((string) ($page['title'] ?? '')),
         'folder_label' => $this->esc((string) ($context['folder_label'] ?? '')),
         'template' => $this->esc((string) ($context['template'] ?? '')),
         'permalink' => $this->esc((string) ($page['permalink'] ?? '')),
         'media_count' => (string) count($media),
         'module_count' => (string) count($modules),
         'media_items' => $media_html,
         'module_items' => $module_html,
      ));
   }

   private function create_placement_title(string $lng, int $folder_id, int $after_page_id): string {
      $folder = $folder_id > 0 ? $this->folder_label($lng, $folder_id) : '';
      if ($after_page_id > 0) {
         try {
            $page = $this->load_page($lng, $after_page_id);
            return 'Unter #' . $after_page_id . ' ' . (string) ($page['title'] ?? '') . ' in ' . $folder;
         } catch (\Throwable $e) {
            return $folder !== '' ? $folder : 'Zielposition waehlen';
         }
      }
      return $folder !== '' ? $folder . ' / am Ende' : 'Zielposition waehlen';
   }

   private function render_create_placement_html(string $lng, int $folder_id, int $after_page_id): string {
      $folder_label = $folder_id > 0 ? $this->folder_label($lng, $folder_id) : '';
      $page_title = '';
      $sorter = '';
      if ($after_page_id > 0) {
         try {
            $page = $this->load_page($lng, $after_page_id);
            $page_title = (string) ($page['title'] ?? '');
            $folder_id = (int) ($page['folder'] ?? $folder_id);
            $folder_label = $this->folder_label($lng, $folder_id);
            $sorter = $this->sorter_after_page($lng, $after_page_id);
         } catch (\Throwable $e) {
            $after_page_id = 0;
         }
      }
      return $this->tpl()->get_tpl('dbxKi|ki-briefing-page-create-placement', array(
         'folder_id' => $folder_id > 0 ? (string) $folder_id : '-',
         'folder_label' => $this->esc($folder_label !== '' ? $folder_label : 'Noch kein Zielordner gewaehlt'),
         'after_page_id' => $after_page_id > 0 ? (string) $after_page_id : '-',
         'after_page_title' => $this->esc($page_title !== '' ? $page_title : 'Keine Seite als Sortieranker'),
         'sorter' => $this->esc($sorter !== '' ? $sorter : 'automatisch am Ende'),
      ));
   }

   private function zip_structure_create(bool $hero_enabled): string {
      if ($hero_enabled) {
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

   private function zip_structure_update(bool $hero_change): string {
      if ($hero_change) {
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

   private function assets_rules_create(bool $hero_enabled, string $hero_brief): string {
      if ($hero_enabled) {
         $brief = $hero_brief !== '' ? $hero_brief : 'passend zum Seitenthema';
         return "**Hero-Bild ist vorgesehen** (`briefing.hero.enabled = true`):\n\n"
            . "1. Lege **genau eine** Datei an: `assets/hero.jpg` (" . $this->hero_image_spec_text() . ").\n"
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

   private function assets_rules_update(bool $hero_change, string $hero_brief): string {
      if ($hero_change) {
         $brief = $hero_brief !== '' ? $hero_brief : 'passend zur Seite';
         return "**Neues Hero-Bild** (`hero` in change_fields):\n\n"
            . "1. Lege `assets/hero.jpg` an (" . $this->hero_image_spec_text() . "). Motiv: " . $brief . "\n"
            . "2. Fuer ein wirklich neues Hero-Bild: neue Medienverknuepfung in `img/hero` setzen.\n"
            . "3. Fuer eine reine Aenderung des bestehenden Hero-Bildes: nur die bestehende Hero-Datei ersetzen, keine neue Verknuepfung.\n"
            . "4. `asset_ref` bleibt `hero.jpg`. Kein `data_base64`. Keine weiteren Assets.";
      }
      return "**Kein Hero-Wechsel** (kein `hero` in change_fields):\n\n"
         . "1. **Keinen** `assets/` Ordner anlegen.\n"
         . "2. Nur die vorgesehenen Felder in `answer.json` ausfuellen.";
   }

   private function assets_readme_hero(string $hero_brief): string {
      return "KI: Lege hier die Datei hero.jpg ab.\n\n"
         . "Pfad in der Antwort-ZIP: assets/hero.jpg\n"
         . "Groesse: " . $this->hero_image_spec_text() . "\n"
         . "Der Dateiname ist im signierten Auftrag fest vorgegeben.\n"
         . "Motiv: " . ($hero_brief !== '' ? $hero_brief : 'passend zum Seitenthema') . "\n";
   }

   private function render_template_file(string $basename, array $vars): string {
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
