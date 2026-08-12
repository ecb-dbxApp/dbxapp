<?php

namespace dbx\dbxContent_admin;

/**
 * Liefert die zentralen Auswahlwerte des CMS-Editors.
 *
 * Der Katalog trennt statische Darstellungsoptionen, Template-Ermittlung und
 * lesbare Rechtewerte vom Ablauf-Controller. Dadurch verwenden Hauptformular,
 * Ordnerformular und Einstellungsbereich garantiert dieselben Werte.
 */
final class dbxContentCmsOptionCatalog
{
    private object $texts;
    private ?array $contentTemplateNames = null;

    public function __construct(object $texts)
    {
        $this->texts = $texts;
    }

    public function template_options(): string
    {
        $values = array_combine($this->content_template_names(), $this->content_template_names()) ?: array();
        return $this->options_html($values);
    }

    public function content_template_values(): array
    {
        $values = array('parent' => $this->texts->get_fd_message('option_parent'));
        foreach ($this->content_template_names() as $name) $values[$name] = $name;
        return $values;
    }

    public function rights_options(): string
    {
        return $this->options_html($this->rights_values());
    }

    public function rights_values(): array
    {
        $values = array('parent' => $this->texts->get_fd_message('option_parent'), '*' => '*');
        $db = dbx()->get_system_obj('dbxDB');
        $rows = $db->select('dbxUser_groups', '', '*', 'name');
        foreach (is_array($rows) ? $rows : array() as $row) {
            if (!is_array($row)) continue;
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') $values[$name] = $name;
        }
        return $values;
    }

    public function media_template_options(): string
    {
        return $this->options_html(array(
            'image-hero' => 'Bild Hero', 'image-gallery' => 'Bild Galerie',
            'image-teaser' => 'Bild Header', 'video-hero' => 'Video Hero',
            'video-gallery' => 'Video Galerie', 'file-gallery' => 'Datei Download',
        ));
    }

    public function hero_template_values(): array
    {
        return array(
            'parent' => $this->texts->get_fd_message('option_parent'),
            'none' => $this->texts->get_fd_message('option_no_hero'),
            'image-hero' => 'Bild Hero', 'video-hero' => 'Video Hero',
        );
    }

    public function hero_template_options(): string { return $this->options_html($this->hero_template_values()); }

    public function hero_variant_values(): array
    {
        return array(
            'parent' => $this->texts->get_fd_message('option_parent'),
            'original' => 'Original', 'yellow' => 'gelblich', 'green' => 'gruenlich',
            'blue' => 'blaeulich', 'red' => 'roetlich', 'light' => 'hell',
            'dark' => 'dunkel', 'blackwhite' => 'schwarz/weiss', 'monochrome' => 'monochrom',
        );
    }

    public function hero_variant_options(): string { return $this->options_html($this->hero_variant_values()); }

    public function hero_sticky_values(): array
    {
        return array(
            'parent' => $this->texts->get_fd_message('option_parent'),
            '0' => $this->texts->get_fd_message('option_not_sticky'),
            '1' => $this->texts->get_fd_message('option_sticky'),
        );
    }

    public function hero_scroll_layer_values(): array
    {
        return array(
            'parent' => $this->texts->get_fd_message('option_parent'),
            'under' => $this->texts->get_fd_message('option_scroll_under'),
            'over' => $this->texts->get_fd_message('option_scroll_over'),
        );
    }

    public function gallery_template_values(): array
    {
        return array('image-gallery' => 'Bildergalerie', 'video-gallery' => 'Video Galerie', 'carousel3' => 'Carousel 3', 'cols3' => '3 Spalten');
    }

    public function gallery_template_options(): string { return $this->options_html($this->gallery_template_values()); }

    public function gallery_image_size_values(): array
    {
        return array(
            '800x600' => '4:3 - Standard (800 x 600)', '1200x900' => '4:3 - gross (1200 x 900)',
            '1024x768' => '4:3 - klassisch (1024 x 768)', '1600x1200' => '4:3 - hochaufloesend (1600 x 1200)',
            '1280x720' => '16:9 - breit (1280 x 720)', '1920x1080' => '16:9 - Full HD (1920 x 1080)',
            '1200x675' => '16:9 - Web (1200 x 675)', '1080x1080' => '1:1 - Quadrat (1080 x 1080)',
            '1200x1200' => '1:1 - Quadrat gross (1200 x 1200)', '1080x1350' => '4:5 - Portrait (1080 x 1350)',
            '900x1200' => '3:4 - Portrait (900 x 1200)', '1600x900' => '16:9 - gross (1600 x 900)',
            '2560x1440' => '16:9 - QHD (2560 x 1440)',
        );
    }

    public function gallery_lightbox_width_values(): array
    {
        return array(
            '60%' => '60% - kompakt', '70%' => '70% - mittel', '80%' => '80% - Standard',
            '90%' => '90% - breit', '95%' => '95% - sehr breit', '70vw' => '70vw - Viewport mittel',
            '80vw' => '80vw - Viewport Standard', '90vw' => '90vw - Viewport breit',
            '960px' => '960px - kleine Desktopbreite', '1200px' => '1200px - Desktop',
            '1440px' => '1440px - grosser Desktop',
        );
    }

    public function gallery_overflow_values(): array
    {
        return array('grid' => 'Grid', 'scroll' => 'scroll', 'laufband' => 'laufband', 'slider' => 'slider', 'tutorial' => 'Tutorial Slideshow');
    }

    public function gallery_overflow_options(): string { return $this->options_html($this->gallery_overflow_values()); }

    public function gallery_click_values(): array
    {
        return array(
            'lightbox' => 'Lightbox', 'swiper-coverflow' => 'Swiper Coverflow',
            'swiper-cube' => 'Swiper Cube', 'swiper-cards' => 'Swiper Cards',
            'swiper-3d' => 'Swiper 3D-Slider', 'viewerjs' => 'Viewer.js',
            'blueimp' => 'blueimp Gallery', 'photoswipe' => 'PhotoSwipe',
            'deepzoom' => 'Deep-Zoom-Viewer', 'link' => 'Link',
            'newtab' => 'Neues Fenster', 'none' => 'Kein Klick',
        );
    }

    public function gallery_click_options(): string { return $this->options_html($this->gallery_click_values()); }

    private function content_template_names(): array
    {
        if (is_array($this->contentTemplateNames)) return $this->contentTemplateNames;
        $dir = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbxContent/tpl/htm/');
        $files = is_dir($dir) ? glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'c-*.htm') : array();
        $names = array();
        foreach (is_array($files) ? $files : array() as $file) {
            if (is_file($file)) $names[] = basename($file, '.htm');
        }
        $names = array_values(array_unique($names));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->contentTemplateNames = $names ?: array('c-content');
    }

    private function options_html(array $values, string $selected = ''): string
    {
        $html = '';
        foreach ($values as $value => $label) {
            $selection = (string)$value === $selected ? ' selected' : '';
            $html .= '<option value="' . dbx()->esc($value) . '"' . $selection . '>' . dbx()->esc($label) . '</option>';
        }
        return $html;
    }
}
