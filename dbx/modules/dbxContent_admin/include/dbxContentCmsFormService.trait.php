<?php
namespace dbx\dbxContent_admin;

use dbx\dbxContent\dbxContentHome;
use dbx\dbxContent\dbxContentLng;
use dbx\dbxContent\dbxContentLngSync;
use dbx\dbxContent\dbxContentMediaUsageScope;
use dbx\dbxContent\dbxContentPageCache;
use dbx\dbxContent\dbxContentPermalinkIndex;
use dbx\dbxContent\dbxContentRenderer;
use dbx\dbxContent\dbxContentTranslate;
use dbx\dbxContent\dbxContent_permalink;

/**
 * Formularaufbau ausschliesslich ueber dbxForm und zentrale FD-/Template-Vertraege.
 *
 * Zustandslose Quelltext-Komposition: keine zusaetzliche Instanz, kein Proxy
 * und keine zusaetzlichen Datenbank- oder Templatezugriffe.
 */
trait dbxContentCmsFormServiceTrait {


   private function render_page_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-page', 'cms-admin-page-form');
      $form->set_data_source(dbxContentLng::dd_content(), 'dbxContent_admin|cms-page');
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $this->add_form_message_replaces($form, array(
         'page_section_title',
         'content_template_edit_title',
         'content_template_edit_aria',
         'content_template_select_first',
      ));
      $form->set_data(array(
         'activ' => 1,
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
         'keywords' => '',
         'meta_robots' => 'index,follow',
         'seo_title' => '',
         'seo_image_id' => 0,
         'menu_title' => '',
      ));
      $form->add_fld('id', 'dbxContent_admin|cms-field-hidden', data: $this->cms_field_data('id', 'page'));
      $form->add_fld('folder', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_folder'), rules: 'int', data: $this->cms_field_data('folder', 'page'), options: $this->page_folder_values());
      $form->add_fld('title', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_title'), rules: 'varchar|min=1', data: $this->cms_field_data('title', 'page'));
      $form->add_fld('menu_title', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_menu_title'), rules: 'varchar|max=96', data: $this->cms_field_data('menu_title', 'page'), placeholder: $texts->get_fd_message('placeholder_menu_title'));
      $form->add_fld('permalink', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_permalink'), rules: 'permalink|max=254', data: $this->cms_field_data('permalink', 'page'), tooltip: $texts->get_fd_message('tooltip_permalink'));
      $form->add_fld('activ', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_status'), rules: 'int', data: $this->cms_field_data('activ', 'page'), options: array('1' => $texts->get_fd_message('option_active'), '0' => $texts->get_fd_message('option_inactive')));
      $form->add_fld('template', 'dbxContent_admin|cms-field-content-template-select', label: $texts->get_fd_message('label_template'), rules: 'varchar', data: $this->cms_field_data('template', 'page'), options: $this->cms_options()->content_template_values());
      $form->add_fld('description', 'dbxContent_admin|cms-field-textarea', label: $texts->get_fd_message('label_description'), rules: 'varchar', data: $this->cms_field_data('description', 'page'), placeholder: $texts->get_fd_message('placeholder_description'));
      $form->add_fld('keywords', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_keywords'), rules: 'varchar', data: $this->cms_field_data('keywords', 'page'), placeholder: $texts->get_fd_message('placeholder_keywords'));
      $form->add_fld('content', 'dbxContent_admin|cms-field-textarea-hidden', label: $texts->get_fd_message('label_content'), rules: 'text', data: $this->cms_field_data('content', 'page'));
      return $form->run();
   }



   private function page_folder_values(): array {
      $values = array('0' => $this->cms_texts()->get_fd_message('option_root'));
      $db = dbx()->get_system_obj('dbxDB');
      $rows = $db->select(
         dbxContentLng::dd_folder(),
         '',
         'id,parent_id,name,sorter',
         'sorter,name,id',
         'ASC',
         '',
         0,
         0,
         0
      );

      $folders = array();
      foreach (is_array($rows) ? $rows : array() as $row) {
         $id = (int)($row['id'] ?? 0);
         if ($id > 0) $folders[$id] = $row;
      }

      $children = array();
      foreach ($folders as $id => $row) {
         $parent = (int)($row['parent_id'] ?? 0);
         if ($parent === $id || !isset($folders[$parent])) $parent = 0;
         $children[$parent][] = $id;
      }

      $seen = array();
      $append = function (int $parent, int $depth) use (&$append, &$values, &$seen, $children, $folders): void {
         foreach ($children[$parent] ?? array() as $id) {
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $name = trim((string)($folders[$id]['name'] ?? ''));
            if ($name === '') $name = 'Ordner ' . $id;
            $values[(string)$id] = str_repeat('-- ', $depth) . $name;
            $append($id, $depth + 1);
         }
      };
      $append(0, 0);

      // Defekte Altstrukturen mit Zyklen bleiben auswählbar und können dadurch
      // im CMS wieder einer gültigen Ordnerstruktur zugeordnet werden.
      foreach (array_keys($folders) as $id) {
         if (isset($seen[$id])) continue;
         $name = trim((string)($folders[$id]['name'] ?? ''));
         $values[(string)$id] = $name !== '' ? $name : 'Ordner ' . $id;
      }

      return $values;
   }



   private function render_folder_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-folder', 'cms-admin-folder-form');
      $form->set_data_source(dbxContentLng::dd_folder(), 'dbxContent_admin|cms-page');
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $this->add_form_message_replaces($form, array(
         'folder_form_title',
         'folder_form_subtitle',
         'close_title',
         'folder_close_aria',
         'folder_delete_title',
         'folder_save_title',
         'delete_label',
         'save_label',
         'content_template_edit_title',
         'content_template_edit_aria',
         'content_template_select_first',
      ));
      $form->set_data(array(
         'parent_id' => 0,
         'group_read' => 'parent',
         'template' => 'parent',
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
      ));
      $form->add_fld('id', 'dbxContent_admin|cms-field-folder-hidden', data: $this->cms_field_data('id', 'folder'));
      $form->add_fld('name', 'dbxContent_admin|cms-field-folder-text', label: $texts->get_fd_message('label_name'), rules: 'varchar|min=1', data: $this->cms_field_data('name', 'folder'));
      $form->add_fld('parent_id', 'dbxContent_admin|cms-field-folder-select', label: $texts->get_fd_message('label_assignment'), rules: 'int', data: $this->cms_field_data('parent_id', 'folder'), options: array('0' => $texts->get_fd_message('option_root')));
      $form->add_fld('template', 'dbxContent_admin|cms-field-folder-content-template-select', label: $texts->get_fd_message('label_template'), rules: 'varchar', data: $this->cms_field_data('template', 'folder'), options: $this->cms_options()->content_template_values());
      $form->add_fld('group_read', 'dbxContent_admin|cms-field-folder-rights', label: $texts->get_fd_message('label_read_rights'), rules: 'varchar', data: $this->cms_field_data('group_read', 'folder'), options: $this->cms_options()->rights_values());
      return $form->run();
   }



   private function render_settings_form() {
      $texts = $this->cms_texts();
      $form = dbx()->get_system_obj('dbxForm');
      $form->init('cms-settings', 'cms-admin-settings-panels');
      $form->set_data_source(dbxContentLng::dd_content(), 'dbxContent_admin|cms-page');
      $form->load_fd_messages();
      $form->set_form_help_enabled(false);
      $this->add_form_message_replaces($form, array(
         'hero_panel_title',
         'hero_preview_empty',
         'hero_select_title',
         'hero_remove_title',
         'hero_remove_label',
         'hero_save_title',
         'gallery_panel_title',
         'gallery_select_title',
         'gallery_save_title',
         'selection_label',
         'save_label',
         'media_gallery_empty',
      ));
      $form->set_data(array(
         'hero_template' => 'parent',
         'hero_image_id' => 'parent',
         'hero_margin_top' => 'parent',
         'hero_height' => 'parent',
         'hero_variant' => 'parent',
         'hero_sticky' => 'parent',
         'hero_scroll_layer' => 'parent',
         'gallery_template' => 'image-gallery',
         'gallery_visible_count' => '3',
         'gallery_image_size' => 'original',
         'gallery_lightbox_width' => '100vw',
         'gallery_overflow' => 'grid',
         'gallery_click_behavior' => 'lightbox',
      ));
      $form->add_fld('hero_template', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_template'), rules: 'varchar', data: $this->cms_field_data('hero_template', 'settings'), options: $this->cms_options()->hero_template_values());
      $form->add_fld('hero_image_id', 'dbxContent_admin|cms-field-hidden', rules: 'varchar', data: $this->cms_field_data('hero_image_id', 'settings'));
      $form->add_fld('hero_margin_top', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_hero_margin_top'), rules: 'varchar', data: $this->cms_field_data('hero_margin_top', 'settings'));
      $form->add_fld('hero_height', 'dbxContent_admin|cms-field-text', label: $texts->get_fd_message('label_hero_height'), rules: 'varchar', data: $this->cms_field_data('hero_height', 'settings'));
      $form->add_fld('hero_variant', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_variant'), rules: 'varchar', data: $this->cms_field_data('hero_variant', 'settings'), options: $this->cms_options()->hero_variant_values());
      $form->add_fld('hero_sticky', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_sticky'), rules: 'varchar', data: $this->cms_field_data('hero_sticky', 'settings'), options: $this->cms_options()->hero_sticky_values());
      $form->add_fld('hero_scroll_layer', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_hero_scroll_layer'), rules: 'varchar', data: $this->cms_field_data('hero_scroll_layer', 'settings'), options: $this->cms_options()->hero_scroll_layer_values());
      $form->add_fld('gallery_image_size', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_image_size'), rules: 'varchar', data: $this->cms_field_data('gallery_image_size', 'settings'), options: $this->cms_options()->gallery_image_size_values());
      $form->add_fld('gallery_lightbox_width', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_lightbox_width'), rules: 'varchar', data: $this->cms_field_data('gallery_lightbox_width', 'settings'), options: $this->cms_options()->gallery_lightbox_width_values());
      $form->add_fld('gallery_overflow', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_overflow'), rules: 'varchar', data: $this->cms_field_data('gallery_overflow', 'settings'), options: $this->cms_options()->gallery_overflow_values());
      $form->add_fld('gallery_click_behavior', 'dbxContent_admin|cms-field-select', label: $texts->get_fd_message('label_gallery_click'), rules: 'varchar', data: $this->cms_field_data('gallery_click_behavior', 'settings'), options: $this->cms_options()->gallery_click_values());
      return $form->run();
   }
}
