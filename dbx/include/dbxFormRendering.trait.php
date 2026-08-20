<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxFormRenderingTrait
{
/**
     * Registriert eine genutzte FD-/DD-/Class-Datei fuer Editor-Marker.
     *
     * @param string $kind Marker-Typ: fd, dd oder class
     * @param string $file Absoluter oder relativer Dateipfad
     * @return void
     */
    protected function add_editor_file(string $kind, string $file) {
        $file = trim($file);
        $kind = strtolower(trim($kind));

        if ($file === '' || $kind === '') {
            return;
        }

        $path = dbx()->editor_file_path($file);
        $key  = $kind . '|' . $path;
        $this->_editor_files[$key] = array('kind' => $kind, 'file' => $path);
        dbx()->register_editor_file($kind, $path);
    }

/**
     * Fuegt passende Editor-Marker um den gerenderten Formularinhalt.
     *
     * @param string $content Formularinhalt
     * @return string Inhalt mit Editor-Marker-Kommentaren
     */
    protected function add_editor_markers(string $content): string {
        $edit = (int) dbx()->get_system_var('dbx_edit', 0, 'int');

        if (($edit >= 4 && $edit <= 8) || $edit === 9) {
            return $content;
        }

        if ($this->_editor_class_file !== '') {
            $this->add_editor_file('class', $this->_editor_class_file);
        }

        foreach (dbx()->get_editor_files() as $file) {
            if (isset($file['kind'], $file['file'])) {
                $this->add_editor_file($file['kind'], $file['file']);
            }
        }

        if ($content === '' || !$this->_editor_files) {
            return $content;
        }

        $markers = '';

        foreach ($this->_editor_files as $file) {
            $kind = strtolower((string) ($file['kind'] ?? ''));

            // FD/DD-Marker werden pro Feld in create_fld() gesetzt.
            if ($kind === 'fd' || $kind === 'dd') {
                continue;
            }

            $marker = dbx()->editor_marker($file['kind'], $file['file']);
            $needle = trim($marker);

            if ($marker !== '' && strpos($content, $needle) === false && strpos($markers, $needle) === false) {
                $markers .= $marker;
            }
        }

        if ($markers === '') {
            return $content;
        }

        return $markers . $content . "\n<!-- DBX-EDITOR-END -->\n";
    }

/**
     * Liefert das Template fuer einen init()-Aufruf ohne explizites Template.
     * Die fid wird bewusst nicht fuer die Auswahl der Darstellung verwendet.
     */
    protected function default_tpl(string $fid): string {
        return 'dbx|form-default';
    }

/**
     * Setzt die einheitliche Modul-/Formularleiste.
     *
     * Standardmäßig werden bereits vorbereitete Metadaten beibehalten. Mit
     * `$replace = true` kann ein konkretes Formular den allgemeineren
     * Admin-Hilfetitel gezielt durch sprachabhängige FD-Texte ersetzen.
     *
     * @param string $title Titel der Leiste
     * @param string $icon Bootstrap-Iconklasse
     * @param string $subtitle Untertitel der Leiste
     * @param bool $replace Vorhandene Metadaten bewusst ersetzen
     *
     * @return void
     */
    public function add_module_bar(
        $title,
        $icon = 'bi-grid',
        $subtitle = '',
        $replace = false
    ) {
        if ($replace || !isset($this->_replaces['bar_title']) || (string)$this->_replaces['bar_title'] === '') {
            $this->add_rep('bar_title', (string)$title);
        }
        if ($replace || !isset($this->_replaces['bar_icon']) || (string)$this->_replaces['bar_icon'] === '') {
            $this->add_rep('bar_icon', (string)$icon);
        }
        if ($replace || !isset($this->_replaces['bar_subtitle']) || (string)$this->_replaces['bar_subtitle'] === '') {
            $this->add_rep('bar_subtitle', (string)$subtitle);
        }
    }

/**
     * Schaltet die automatische Formular-Hilfeleiste gezielt ein oder aus.
     * Eingebettete Steuerformulare verwenden die Hilfe ihres Elternbereichs.
     */
    public function set_form_help_enabled(bool $enabled = true): self {
        $this->_form_help_enabled = $enabled ? 1 : 0;
        if (!$enabled) {
            $this->_form_help_button = '';
            unset($this->_obj['help_button']);
        }
        return $this;
    }

public function add_module_bar_form_actions(array $options = array()) {
        $defaults = array(
            'save'         => true,
            'delete'       => false,
            'reload'       => true,
            'reload_url'   => '',
            'delete_url'   => '',
            'delete_title' => trim($this->get_tpl('dbx|form-action-delete-title')),
            'delete_hint'  => trim($this->get_tpl('dbx|form-action-delete-hint')),
        );
        $this->_module_bar_form_actions = array_merge($defaults, $options);
    }

/** Wählt die gemeinsame Form-Bar; ein leerer Wert deaktiviert sie. */
    public function set_form_bar_template(string $template): void {
        $this->_tpl_form_bar = trim($template);
    }

/** Wählt den gemeinsamen Form-Footer; ein leerer Wert deaktiviert ihn. */
    public function set_form_footer_template(string $template): void {
        $this->_tpl_form_footer = trim($template);
    }

public function prepare_form_shell(array $options = array()) {
        if (!isset($this->_replaces['form_shell_class'])) {
            $this->add_rep('form_shell_class', (string)($options['class'] ?? ''));
        }
        if (!isset($this->_replaces['form_class'])) {
            $this->add_rep('form_class', (string)($options['form_class'] ?? ''));
        }
        if (!isset($this->_replaces['form_attrs'])) {
            $this->add_rep('form_attrs', (string)($options['form_attrs'] ?? ''));
        }
    }

protected function default_module_bar_replaces(): array {
        return array(
            'bar_class'               => 'dbx-bar--module',
            'bar_title_class'         => 'dbx-bar-title',
            'bar_actions_class'       => 'dbx-bar-actions',
            'bar_title'               => '',
            'bar_icon'                => 'bi-grid',
            'bar_subtitle'            => '',
            'bar_title_pre'           => '',
            'bar_title_heading_attrs' => '',
            'bar_actions'             => '',
            'bar_extra'               => '',
            'bar_middle'              => '',
        );
    }

protected function prepare_form_frame_replaces(int $i): void {
        $form_class = trim((string)($this->_replaces['form_class'] ?? ''));
        $form_attrs = trim((string)($this->_replaces['form_attrs'] ?? ''));
        $shell_class = trim((string)($this->_replaces['form_shell_class'] ?? ''));
        $panel_class = trim('dbxForm_wrapper ' . $shell_class);
        $frame_id = trim((string)($this->_replaces['frame_id'] ?? ''));
        $form_action = dbx()->action_url((string)$this->_action);
        if ($frame_id === '') {
            $frame_id = 'dbx_target_' . $i;
        }

        $this->add_rep('frame_id', $frame_id);
        if (trim((string)($this->_replaces['frame_panel_class'] ?? '')) === '') {
            $this->add_rep('frame_panel_class', $panel_class);
        }
        $this->add_rep('frame_panel_attrs', (string)($this->_replaces['frame_panel_attrs'] ?? ''));
        $this->add_rep('frame_subbar', '');
        $this->add_rep('frame_body_class', '');
        if ((string)($this->_replaces['frame_skip_form_wrap'] ?? '') !== '1') {
            $this->add_rep('frame_form_open', '<form action="' . htmlspecialchars($form_action, ENT_QUOTES) . '" method="post" id="dbx_form_' . $i . '" class="dbxAjax' . ($form_class !== '' ? ' ' . $form_class : '') . '"' . ($form_attrs !== '' ? ' ' . $form_attrs : '') . '>');
            $this->add_rep('frame_body_head', '<div class="row"><div class="col">{obj:form_msg}</div></div>');
            $this->add_rep('frame_form_close', '</form>');
        } else {
            if (!isset($this->_replaces['frame_form_open'])) {
                $this->add_rep('frame_form_open', '');
            }
            if (!isset($this->_replaces['frame_body_head'])) {
                $this->add_rep('frame_body_head', '');
            }
            if (!isset($this->_replaces['frame_form_close'])) {
                $this->add_rep('frame_form_close', '');
            }
        }
        $this->add_rep('frame_body_tail', '');
    }

protected function apply_module_bar_replaces(array $values = array()): void {
        $defaults = array_merge($this->default_module_bar_replaces(), $values);

        foreach ($defaults as $key => $value) {
            $this->add_rep($key, (string) $value);
        }
    }

protected function build_module_bar_form_actions_html() {
        if (!$this->_module_bar_form_actions) {
            return '';
        }

        $opts = $this->_module_bar_form_actions;
        $i = (int)$this->_next_i;
        if ($i <= 0) {
            $i = (int)$this->next_i();
        }

        $form_id = 'dbx_form_' . $i;
        $html = '';

        if (!empty($opts['save'])) {
            $html .= $this->get_tpl('dbx|button-bar-save', array(
                'bar_form_id' => $form_id,
            ));
        }

        if (!empty($opts['delete']) && trim((string)($opts['delete_url'] ?? '')) !== '') {
            $html .= $this->get_tpl('dbx|button-bar-delete', array(
                'bar_delete_url'   => htmlspecialchars((string)($opts['delete_url'] ?? ''), ENT_QUOTES),
                'bar_delete_title' => htmlspecialchars((string)($opts['delete_title'] ?? 'Datensatz loeschen'), ENT_QUOTES),
                'bar_delete_hint'  => htmlspecialchars((string)($opts['delete_hint'] ?? ''), ENT_QUOTES),
            ));
        }

        if (!empty($opts['reload'])) {
            $reload_url = trim((string)($opts['reload_url'] ?? ''));
            if ($reload_url === '') {
                $reload_url = (string)$this->_action;
            }

            $html .= $this->get_tpl('dbx|button-bar-reload', array(
                'bar_reload_url' => htmlspecialchars($reload_url, ENT_QUOTES),
            ));
        }

        return $html;
    }

protected function build_module_bar_obj() {
        $modul = (string)dbx()->get_system_var('dbx_modul', '');

        try {
            $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
            $context = $help->context($modul);

            $actions = trim((string)($this->_replaces['bar_actions'] ?? ''));
            if ($actions === '' && isset($this->_obj['bar_actions'])) {
                $actions = (string)$this->_obj['bar_actions'];
            }

            $form_actions = $this->build_module_bar_form_actions_html();
            $this->_form_bar_actions = $form_actions;
            if ($form_actions !== '') {
                $actions = ($actions !== '') ? ($actions . ' ' . $form_actions) : $form_actions;
            }

            $title = (string)($this->_replaces['bar_title'] ?? '');
            $icon = (string)($this->_replaces['bar_icon'] ?? 'bi-grid');
            $subtitle = (string)($this->_replaces['bar_subtitle'] ?? '');

            $fd_title = $this->get_fd_message('bar_title', '');
            $fd_subtitle = $this->get_fd_message('bar_subtitle', '');
            $fd_icon = $this->get_fd_message('bar_icon', '');

            // Eine aktive FD ist für Formulare und Reports die verbindliche
            // sprachabhängige Quelle. Sie ersetzt automatisch leere oder von
            // der generischen Admin-Hilfe stammende Metadaten. Ein Modul darf
            // weiterhin bewusst einen spezielleren, ebenfalls aus der FD
            // gelesenen Formulartitel setzen.
            if (
                $fd_title !== '' &&
                ($title === '' || $title === $fd_title)
            ) {
                $title = $fd_title;
            }
            if (
                $fd_subtitle !== '' &&
                (
                    $subtitle === '' ||
                    $subtitle === $fd_subtitle
                )
            ) {
                $subtitle = $fd_subtitle;
            }
            if ($fd_icon !== '') {
                $icon = $fd_icon;
            }

            $help_button = '';
            if ($this->_form_help_enabled) {
                if (isset($this->_obj['help_button'])) {
                    $help_button = (string)$this->_obj['help_button'];
                }
                if ($help_button === '' && method_exists($help, 'formButton') && (string)$this->_fid !== '') {
                    $help_button = $help->form_button($modul, (string)$this->_fid, $title);
                }
                if ($help_button === '' && method_exists($help, 'button')) {
                    $help_button = $help->button($context['module'], $context['run1'], $context['run2'], $title);
                }
            }
            $this->_form_help_button = $help_button;

            $bar_extra = trim((string)($this->_replaces['bar_extra'] ?? ''));
            $visible_bar_actions = $actions . $bar_extra;
            if ($help_button !== '' && strpos($visible_bar_actions, 'bi-question-circle') === false) {
                $bar_extra .= $help_button;
            }

            $bar_class = trim((string)($this->_replaces['bar_class'] ?? ''));
            if ($bar_class === '') {
                $bar_class = 'dbx-bar--module';
            }

            $this->apply_module_bar_replaces(array(
                'bar_class'    => $bar_class,
                'bar_title'    => $title,
                'bar_icon'     => $icon !== '' ? $icon : 'bi-grid',
                'bar_subtitle' => $subtitle,
                'bar_actions'  => $actions,
                'bar_extra'    => $bar_extra,
            ));
        } catch (\Throwable $e) {
        }
    }

/**
     * Bereitet die zentralen Form-Bausteine fuer das individuelle
     * Modul-Template vor.
     *
     * Ein Modul platziert `{form:bar}`, `{form:message}` und
     * `{form:footer}` dort, wo sie fuer sein Layout passen. Die Auswahl der
     * gemeinsamen Templates bleibt trotzdem eine Eigenschaft von dbxForm.
     */
    protected function prepare_form_chrome_replaces(): void {
        $bar = '';
        if (trim((string)$this->_tpl_form_bar) !== '') {
            $bar = $this->get_tpl((string)$this->_tpl_form_bar, array(
                'form_bar_title' => htmlspecialchars(
                    (string)($this->_replaces['bar_title'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'form_bar_icon' => htmlspecialchars(
                    (string)($this->_replaces['bar_icon'] ?? 'bi-ui-checks'),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'form_bar_subtitle' => htmlspecialchars(
                    (string)($this->_replaces['bar_subtitle'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'form_bar_actions' => $this->_form_bar_actions,
                'form_help_button' => $this->_form_help_enabled
                    ? $this->_form_help_button
                    : '',
            ));
        }

        $footer = '';
        if (trim((string)$this->_tpl_form_footer) !== '') {
            $footer = $this->get_tpl((string)$this->_tpl_form_footer, array(
                'form_footer_actions' => $this->_form_bar_actions,
            ));
        }

        $this->add_rep('form:bar', $bar);
        $this->add_rep('form:message', '{obj:form_msg}');
        $this->add_rep('form:footer', $footer);
    }

/**
     * Ergänzt bei aelteren Formular-Templates ohne eigene Leiste eine
     * kompakte Standardleiste. Dadurch bleibt der Help-Button nicht nur als
     * Replace vorbereitet, sondern ist in jedem tatsaechlich gerenderten
     * dbxForm-Formular erreichbar.
     */
    protected function ensure_form_bar(string $content): string {
        if (trim((string)$this->_tpl_form_bar) === '') {
            return $content;
        }

        $help_button = $this->_form_help_enabled ? $this->_form_help_button : '';
        if (
            $content === '' ||
            ($help_button === '' && $this->_form_bar_actions === '') ||
            stripos($content, '<form') === false
        ) {
            return $content;
        }
        if (
            strpos($content, 'dbx-form-bar') !== false ||
            strpos($content, 'dbx-bar--module') !== false
        ) {
            return $content;
        }

        $title = trim((string)($this->_replaces['bar_title'] ?? ''));
        if ($title === '') {
            $title = ucwords(str_replace(array('-', '_', '.'), ' ', (string)$this->_fid));
        }
        $bar = $this->get_tpl((string)$this->_tpl_form_bar, array(
            'form_bar_title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'form_bar_icon' => htmlspecialchars((string)($this->_replaces['bar_icon'] ?? 'bi-ui-checks'), ENT_QUOTES, 'UTF-8'),
            'form_bar_subtitle' => htmlspecialchars((string)($this->_replaces['bar_subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'form_bar_actions' => $this->_form_bar_actions,
            'form_help_button' => $help_button,
        ));
        if (trim($bar) === '') {
            return $content;
        }

        return (string)preg_replace_callback('/<form\b[^>]*>/i', static function ($match) use ($bar) {
            return $match[0] . $bar;
        }, $content, 1);
    }

protected function attach_admin_help_button($run1 = '', $run2 = '') {
        $modul = (string)dbx()->get_system_var('dbx_modul', '');
        if ($modul !== 'dbxAdmin' && !str_ends_with($modul, '_admin')) {
            return;
        }

        try {
            $help = dbx()->get_include_obj('dbxModuleHelp', 'dbxHelp');
            $context = $help->context($modul, (string)$run1, (string)$run2);
            if (!$help->has($context['module'], $context['run1'], $context['run2'])) {
                return;
            }
            $this->add_obj(
                'help_button',
                'obj-value',
                $help->button($context['module'], $context['run1'], $context['run2'])
            );
        } catch (\Throwable $e) {
        }
    }

/**
     * Wandelt Query-/URL-Daten in ein Array um.
     *
     * Verwendung
     * ----------
     * Wird intern zur Normalisierung von `data` und `options` genutzt.
     *
     * Auswirkung
     * ----------
     * Strings wie `a=1&b=2` werden zu Arrays. Arrays bleiben unverändert.
     *
     * @param mixed $data String oder bereits Array
     *
     * @return array|string
     */
    public function url_to_array($data) {
        if (!is_array($data)) {
            $first = substr((string) $data, 0, 1);

            if ($data && $first != '=') {
                if (strpos((string) $data, '=') !== false) {
                    parse_str($data, $xdata);
                    $data = $xdata;
                }
            }
        }

        return $data;
    }

/**
     * Ersetzt norep-Platzhalter.
     *
     * Zweck
     * -----
     * Bestehende norep-Mechanik bleibt als Integrationsfunktion erhalten,
     * ist aber nicht mehr der Kern des Form-Designs.
     *
     * Auswirkung
     * ----------
     * Sucht `[id]`-Platzhalter im übergebenen Inhalt und ersetzt sie mit den
     * in `$_SESSION['dbx']['norep']` gespeicherten Inhalten.
     *
     * @param string $content Inhalt mit `[id]`-Platzhaltern
     *
     * @return string
     */
    public function add_norep($content) {
        $o_tpl = dbx()->get_system_obj('dbxTPL');
        if (is_object($o_tpl) && method_exists($o_tpl, 'cleanup_optional_placeholders')) {
            $content = $o_tpl->cleanup_optional_placeholders((string)$content);
        }

        if (isset($_SESSION['dbx']['norep']) && is_array($_SESSION['dbx']['norep'])) {
            $xnorep = $_SESSION['dbx']['norep'];

            for ($i = 0; $i < 2; $i++) {
                foreach ($xnorep as $id => $norep) {
                    $xid = '[' . $id . ']';
                    $content = str_replace($xid, $norep, $content);
                }
            }
        }

        return $content;
    }

/**
     * Sendet eine direkte Fast-Response.
     *
     * Zweck
     * -----
     * Für Spezialfälle, in denen eine direkte Antwort ohne normalen Render-
     * Endlauf ausgegeben werden soll.
     *
     * Auswirkung
     * ----------
     * Speichert die Session, gibt den Response aus und beendet den Request.
     * Optional wird vorher der dbxInterpreter angewendet.
     *
     * @param string $response Antwortinhalt
     * @param int    $interpreter 1 = dbxInterpreter vorher anwenden
     *
     * @return void
     */
    public function fast_response($response, $interpreter = 0) {
        if ($interpreter) {
            $o_interpreter = dbx()->get_system_obj('dbxInterpreter');
            $response = $o_interpreter->run($response);
            $response = $this->add_norep($response);
        }

        echo $response;
        exit;
    }

/**
     * Wrapper auf dbxTPL->get_tpl().
     *
     * Verwendung
     * ----------
     * Zentrale Kurzform, damit dbxForm und abgeleitete Klassen Templates über
     * die DBX-Template-Engine laden können.
     *
     * @param string $tpl Template
     * @param mixed  $data Replaces
     * @param string $type Typ
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function get_tpl($tpl, $data = '', $type = 'htm', $i = 0) {
        return $this->o_tpl->get_tpl($tpl, $data, $type, $i);
    }

/**
     * Historische obv-Hilfe.
     *
     * Hinweis
     * -------
     * Diese Methode bleibt nur als Übergangshilfe erhalten.
     * Observe-/Client-Reaktionslogik gehört heute nicht mehr in den Kern von
     * dbxForm.
     *
     * @param string $content Inhalt
     * @param string $id Platzhalter-ID
     * @param mixed  $value Wert
     *
     * @return string
     */
    public function obv_value($content, $id, $value) {
        $rep = '{obv:' . $id . '}';
        $val = '';

        if (is_string($value)) {
            $val = htmlspecialchars($value, ENT_QUOTES);
            $content = str_replace($rep, $val, $content);
        }

        return $content;
    }

/**
     * Führt grundlegende Form-Replaces in Templates aus.
     *
     * Verwendung
     * ----------
     * Wird in der Ausgabephase vor dem Einsetzen von Feldern und Objekten
     * aufgerufen.
     *
     * Auswirkung
     * ----------
     * Ersetzt Basiswerte wie `{dbx_modul}`, `{dbx_run1}`, `{action}`, `{fid}`,
     * `{rid}`, `{self}` und `{i}`.
     *
     * @param string $tpl Template-Inhalt
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function merge_tpl_data($tpl, $i = 0) {
        $editor = dbx()->get_system_var('dbx_editor', 0, 'int');

        if (!$i && !$editor) {
            $i = $this->_next_i;
        }

        $replaces = array();
        $replaces['dbx_modul']  = $this->_dbx_modul;
        $replaces['dbx_run1']   = $this->_dbx_action;
        $replaces['dbx_page']   = $this->_dbx_page;
        $replaces['dbx_design'] = $this->_dbx_design;
        $replaces['dbx_lng']    = $this->_dbx_lng;
        $replaces['action']     = dbx()->action_url((string)$this->_action);
        $replaces['fid']        = $this->_fid;
        $replaces['rid']        = $this->_rid;
        $replaces['self']       = dbx()->get_self_url();

        if ($i) {
            $replaces['i'] = $i;
        }

        return $this->o_tpl->replaces($tpl, $replaces);
    }

/**
     * Ersetzt `{obj:*}`-Platzhalter.
     *
     * Verwendung
     * ----------
     * Wird nach `merge_fld_data()` aufgerufen, um frei hinzugefügte Objekte
     * wie Buttons, Action-Blöcke oder Modul-Ausgaben einzusetzen.
     *
     * Auswirkung
     * ----------
     * Alle Einträge in `_obj` werden in passende `{obj:name}`-Slots eingesetzt.
     *
     * @param string $content Inhalt
     * @param int    $i Laufindex
     *
     * @return string
     */
    public function merge_obj($content, $i = 0) {
        if (is_array($this->_obj)) {
            foreach ($this->_obj as $id => $obj) {
                $fid = '{obj:' . $id . '}';
                $obj = $this->o_tpl->replaces($obj, $this->_replaces);
                $content = str_replace($fid, $obj, $content);
            }
        }

        return $content;
    }

/**
     * Fügt einen allgemeinen Replace-Wert hinzu.
     *
     * Verwendung
     * ----------
     * Replaces werden beim Laden/Rendern von Templates und Objekten eingesetzt.
     *
     * @param string $key Schlüssel
     * @param mixed  $val Wert
     *
     * @return void
     */
    public function add_rep($key, $val) {
        $this->_replaces[$key] = $val;
    }

/**
     * Wendet dbxForm-Replacements auf einen beliebigen Inhalt an.
     *
     * Ohne explizites Array werden die zuvor mit add_rep() gesetzten Werte
     * verwendet. Weil dbxReport von dbxForm erbt, stehen dieselbe Methode und
     * dieselben spaet gesetzten Replacements auch in Report-Callbacks bereit.
     *
     * @param string $content Inhalt mit `{name}`-Platzhaltern.
     * @param array|null $replaces Explizite Werte oder null fuer `_replaces`.
     * @return string Inhalt mit ersetzten Platzhaltern.
     */
    public function replaces($content, $replaces = null): string {
        if ($replaces === null) {
            $replaces = $this->_replaces;
        }

        return $this->o_tpl->replaces(
            (string)$content,
            is_array($replaces) ? $replaces : array()
        );
    }

/**
     * Fügt JS-Code hinzu.
     *
     * Verwendung
     * ----------
     * Modulcode kann damit kleine JS-Blöcke an den Formularlauf anhängen.
     *
     * Auswirkung
     * ----------
     * Der Code wird später in `forward_run()` über `[dbx:js]` und norep
     * in den Output eingebunden.
     *
     * @param string $javascript JS-Code
     *
     * @return void
     */
    public function add_js_code($javascript) {
        if ($javascript !== '') {
            $this->_js[] = $javascript;
        }
    }

/**
     * Fügt einen einfachen JS-Aufruf hinzu.
     *
     * Zweck
     * -----
     * Einfache Übergangshilfe für modulare JS-Aufrufe. Komplexe UI-/Observe-
     * Logik sollte heute eher in core.js / Libs liegen.
     *
     * Beispiel
     * --------
     * ```php
     * $oForm->add_js_call('roles', 'multiselect2');
     * ```
     *
     * @param string $target Zielkennung
     * @param string $function Funktionsname
     * @param string $args Optionaler JS-Argumentstring
     *
     * @return void
     */
    public function add_js_call($target, $function, $args = '') {
        $js = $function . "('" . addslashes((string) $target) . "'";

        if ($args !== '') {
            $js .= ',' . $args;
        }

        $js .= ');';

        $this->_js[] = $js;
    }

/**
     * Maskiert den Tooltip-Inhalt eines Template-Datensatzes.
     *
     * Verwendung
     * ----------
     * Wird unmittelbar vor der Übergabe von Felddaten an ein Template
     * verwendet, damit Attributwerte kein zusätzliches HTML einschleusen.
     *
     * Auswirkung
     * ----------
     * Ein vorhandener skalarer `tooltip`-Wert wird HTML-sicher maskiert.
     *
     * @param mixed $data Template-Daten oder ein unveränderter Einzelwert
     *
     * @return mixed Daten mit maskiertem Tooltip
     */
    protected function escape_tooltip_template_data($data) {
        if (is_array($data) && isset($data['tooltip']) && !is_array($data['tooltip'])) {
            $data['tooltip'] = htmlspecialchars(
                (string)$data['tooltip'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        return $data;
    }

/**
     * Erzeugt den fokussierbaren Hilfe-Marker eines Formularfeldes.
     *
     * Feld-Templates kennen nur `{tooltip}`. Der eigentliche Tooltip-Inhalt
     * bleibt dadurch zentral in dbxForm und wird sicher in das Attribut des
     * Fragezeichens geschrieben.
     *
     * @param mixed $tooltip Tooltip-Inhalt aus FD oder DD
     * @return string Leerer String oder fertiges Marker-HTML
     */
    protected function render_field_tooltip_marker($tooltip): string {
        if (is_array($tooltip) || is_object($tooltip)) {
            return '';
        }

        $tooltip = trim((string)$tooltip);
        if ($tooltip === '') {
            return '';
        }

        $tooltip_attribute = htmlspecialchars(
            $tooltip,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $accessible_label = trim((string)preg_replace(
            '/\s+/u',
            ' ',
            strip_tags(html_entity_decode($tooltip, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ));
        if ($accessible_label === '') {
            $accessible_label = 'Tooltip';
        }
        $accessible_label = htmlspecialchars(
            $accessible_label,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return '<span class="dbx-form-tooltip-marker" role="img" tabindex="0"' .
            ' data-dbx-tooltip="' . $tooltip_attribute . '"' .
            ' aria-label="' . $accessible_label . '">?</span>';
    }

public function add_obj($obj, $tpl, $data = '', $data2 = '') {
        if ($tpl != 'obj-value' && $tpl != 'obv-value') {
            $data = $this->escape_tooltip_template_data($data);
            $tpl = $this->get_tpl($tpl, $data);
        } else {
            if ($tpl == 'obv-value') {
                $tpl = htmlspecialchars((string) $data, ENT_QUOTES);
            }

            if ($tpl == 'obj-value') {
                $tpl = $data;
            }

            $tpl = $this->o_tpl->replaces(
                (string)$tpl,
                $this->escape_tooltip_template_data($data2)
            );
        }

        $tpl = str_replace('{class}', '', $tpl);
        $tpl = str_replace('{tooltip}', '', $tpl);

        $this->_obj[$obj] = $tpl;
    }

/**
     * Fügt ein Objekt hinzu, dessen Action aus `_action` abgeleitet wird.
     *
     * Verwendung
     * ----------
     * Praktisch für Buttons/Links, die an die aktuelle Formular-Action
     * andocken sollen.
     *
     * @param string $obj Platzhalter-ID
     * @param string $tpl Template
     * @param string $action Action oder `&...`-Suffix
     * @param mixed  $data Zusätzliche Daten
     *
     * @return void
     */
    public function add_action($obj, $tpl, $action = '', $data = '') {
        $xaction = $this->_action;

        if ($action !== '' && $action[0] == '&') {
            $x_action = $xaction . $action;
        } else {
            $x_action = $action;
        }

        $xdata = array();
        $xdata['action'] = dbx()->action_url((string)$x_action);

        if (is_array($data)) {
            $xdata = array_merge($xdata, $data);
        }

        $xdata = $this->escape_tooltip_template_data($xdata);
        $tpl = $this->get_tpl($tpl, $xdata);
        $tpl = str_replace('{class}', '', $tpl);
        $tpl = str_replace('{tooltip}', '', $tpl);

        $this->_obj[$obj] = $tpl;
    }

/**
     * Fügt CSS hinzu.
     *
     * @param string $css CSS-Referenz
     *
     * @return void
     */
    public function add_css($css = '') {
        if ($css) {
            $this->_css[] = $css;
        }
    }

/**
     * Führt die Ausgabephase aus.
     *
     * Ablauf
     * ------
     * - Request-Zustand sicherstellen
     * - Try-Sperre prüfen
     * - Template laden
     * - TPL-Replaces ausführen
     * - Felder einfügen
     * - Objekte einfügen
     * - Form-Meldung setzen
     * - JS-Platzhalter füllen
     * - Sysdata und Workflow-State speichern
     *
     * @return string
     */
    public function forward_run() {
        $content = '';
        $editor  = dbx()->get_system_var('dbx_editor', 0, 'int');

        $this->evaluate_request();

        $submit = $this->submit();
        $errors = $this->errors();

        $try_content = $this->check_try_count($submit, $errors, 1);

        $i = $this->next_i();
        $this->_next_i = $i;

        $this->build_module_bar_obj();
        $this->prepare_form_frame_replaces($i);
        $this->prepare_form_chrome_replaces();

        $replaces = $this->_replaces;
        $replaces['form-id'] = $this->_dbx_modul . '-' . $this->_fid;
        foreach (array('form_shell_class' => '', 'form_class' => '', 'form_attrs' => '') as $shell_key => $shell_default) {
            if (!isset($replaces[$shell_key]) || (string)$replaces[$shell_key] === '') {
                $replaces[$shell_key] = $shell_default;
            }
        }
        foreach (array('shell_panel_class' => '', 'shell_panel_attrs' => '', 'shell_body_class' => '') as $shell_key => $shell_default) {
            if (!isset($replaces[$shell_key]) || (string)$replaces[$shell_key] === '') {
                $replaces[$shell_key] = $shell_default;
            }
        }

        // Ein explizites "modul|template" bleibt erhalten. Das erlaubt
        // gemeinsam genutzte dbxForm-Templates auch dann, wenn das aufrufende
        // Modul ein anderes ist.
        $template_ref = strpos((string)$this->_tpl, '|') !== false
            ? (string)$this->_tpl
            : 'modul|' . $this->_tpl;
        $content = $this->get_tpl($template_ref, $replaces, 'htm', $i);
        $content = $this->merge_tpl_data($content, $i);
        $content = $this->merge_fld_data($content, $i);
        $content = $this->merge_obj($content, $i);
        $content = $this->ensure_form_bar($content);

        $form_msg = '';

        if ($try_content !== '') {
            $form_msg = $try_content;
        } else {
            if ($submit) {
                if ($this->errors()) {
                    $form_msg = $this->get_form_msg('error', $this->_msg_error);
                } elseif ($this->warnings()) {
                    $form_msg = $this->get_form_msg('warning', $this->_msg_warning);
                } else {
                    $form_msg = $this->get_form_msg('success', $this->_msg_success);
                }
            } else {
                $form_msg = $this->get_form_msg('info', $this->_msg_info);
            }
        }

        $content = str_replace('{obj:form_msg}', $form_msg, $content);

        $norep_ids = '';

        if (is_array($this->_js)) {
            foreach ($this->_js as $javascript) {
                $javascript = str_replace('{i}', $this->_next_i, $javascript);
                $norep = '<script>' . $javascript . '</script> ';
                $norep_ids .= dbx()->norep($norep, $i);
            }
        }

        $content = str_replace('[dbx:js]', $norep_ids, $content);

        if (!$editor && $content && $i) {
            $content = str_replace('{i}', $i, $content);
        }

        $content = $this->callback('run', $content);

        $this->store_sysdata();
        $this->store_workflow_state();

        return $this->add_editor_markers($content);
    }

/**
     * Liefert den absoluten Pfad einer FD-/DD-Quelle fuer Editor-Marker.
     *
     * @param string $source Aktive Formular-Quelle
     *
     * @return string
     */
    protected function resolve_fd_dd_editor_file(string $source): string {
        $source = trim($source);

        if ($source === '') {
            return '';
        }

        $mod  = 'dd';
        $name = $source;

        foreach (array('cfg:', 'def:', 'mod:', 'fd:', 'dd:') as $prefix) {
            if (strpos($source, $prefix) === 0) {
                $mod  = substr($prefix, 0, -1);
                $name = substr($source, strlen($prefix));
                break;
            }
        }

        if ($mod === 'dd' && strpos($source, '|') !== false) {
            $mod  = 'fd';
            $name = $source;
        }

        if ($mod === 'dd') {
            if (method_exists($this->o_db, 'get_dd_file')) {
                return (string) $this->o_db->get_dd_file($name);
            }

            $modul = $this->_dbx_modul ? $this->_dbx_modul : dbx()->get_system_var('dbx_activ_modul', 'dbx');

            return dbx()->get_base_dir() . "dbx/modules/$modul/dd/$name.dd.php";
        }

        $dd_file = '';

        switch ($mod) {
            case 'cfg':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/cfg/config.dd.php";
                break;

            case 'def':
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$name/dd/$name.dd.php";
                break;

            case 'mod':
                $modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $dd_file = dbx()->get_base_dir() . "dbx/modules/$modul/dd/$name.dd.php";
                break;

            case 'fd':
                $fd_modul = $this->_dbx_modul ? $this->_dbx_modul : dbx()->get_system_var('dbx_activ_modul', 'dbx');
                $fd_name  = $name;

                if (strpos($name, '|') !== false) {
                    $parts = explode('|', $name, 2);
                    $fd_modul = trim($parts[0]);
                    $fd_name  = trim($parts[1]);
                }

                if ($fd_modul === '') {
                    $fd_modul = dbx()->get_system_var('dbx_activ_modul', 'dbx');
                }

                $dd_file = dbx()->lng_resolve_file(
                    dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/",
                    $fd_name,
                    'fd.php',
                    $this->_dbx_lng,
                    true
                );
                if ($dd_file === '' || !is_file($dd_file)) {
                    $dd_file = dbx()->get_base_dir() . "dbx/modules/$fd_modul/fd/$fd_name.fd.php";
                }
                break;
        }

        if ($dd_file === '') {
            return '';
        }

        $real_file = realpath(dbx()->os_path($dd_file));

        return $real_file ? $real_file : $dd_file;
    }

/**
     * Erzeugt FD-/DD-Editor-Marker direkt am Feld-Slot.
     *
     * @return string
     */
    protected function get_field_editor_markers(): string {
        $markers = '';

        foreach (array('fd' => $this->_fd, 'dd' => $this->_dd) as $kind => $source) {
            $file = $this->resolve_fd_dd_editor_file($source);

            if ($file === '') {
                continue;
            }

            $marker = dbx()->editor_marker($kind, $file);

            if ($marker !== '' && strpos($markers, trim($marker)) === false) {
                $markers .= $marker;
            }
        }

        return $markers;
    }

/**
     * Öffentliche Init-Methode.
     *
     * @param string $fid Formular-ID
     * @param string $tpl Optionales Template
     *
     * @return void
     */
    public function init($fid, $tpl = '') {
        $this->forward_init($fid, $tpl);
    }

/**
     * Öffentliche Run-Methode.
     *
     * @return string
     */
    public function run() {
        return $this->forward_run();
    }
}
