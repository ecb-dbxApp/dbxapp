<?php

/** Automatisch aus der stabilen Fassade extrahierte interne Verantwortung. */
trait dbxReportChromeTrait
{
/**
 * Setzt Trefferzahl und ungefilterte Gesamtzahl getrennt.
 *
 * Die Trefferzahl steuert Pagination und "Selektiert". Die Gesamtzahl
 * beschreibt dagegen immer den Datenbestand vor dem Such-/Auswahlfilter.
 */
public function set_report_counts(int $filtered_count, int $total_count): static {
        $this->_rcount = max(0, $filtered_count);
        $this->_count_all = max(0, $total_count);
        return $this;
    }

/** Optional explizit angeordnete Felder der Reportleiste; leer = `dbx_r*` automatisch. */
public $_report_bar_flds = array();

/** Ordnet die sichtbaren Filterfelder der Standard-Reportleiste explizit. */
public function set_report_bar_fields(array $fields): static {
        $this->_report_bar_flds = array_values(array_unique(array_filter(
            array_map(static fn($field) => trim((string)$field), $fields),
            static fn($field) => $field !== ''
        )));
        return $this;
    }

protected function build_grid_bar_obj() {
        if ($this->_mode !== 'tabulator') {
            return;
        }
    }

protected function prepare_report_frame_replaces(int $i, array $options = array()): void {
        $with_form = (string)($this->_replaces['frame_use_form'] ?? '0') !== '0';
        if (array_key_exists('with_form', $options)) {
            $with_form = (bool)$options['with_form'];
        }
        $panel_class = trim('dbxReport ' . (string)($this->_replaces['report_shell_class'] ?? '') . ' ' . (string)($this->_replaces['shell_panel_class'] ?? ''));
        $frame_id = trim((string)($this->_replaces['frame_id'] ?? ''));
        if ($frame_id === '') {
            $frame_id = 'dbx_target_' . $i;
        }

        $this->add_rep('frame_id', $frame_id);
        if (trim((string)($this->_replaces['frame_panel_class'] ?? '')) === '') {
            $this->add_rep('frame_panel_class', trim($panel_class));
        }
        $panel_attrs = (string)($this->_replaces['report_shell_attrs'] ?? '');
        if ($panel_attrs === '') {
            $panel_attrs = (string)($this->_replaces['shell_panel_attrs'] ?? '');
        }
        $this->add_rep('frame_panel_attrs', $panel_attrs);
        $this->add_rep('frame_subbar', '');
        $this->add_rep('frame_body_class', (string)($this->_replaces['shell_body_class'] ?? ''));
        $this->add_rep('frame_body_head', (string)($this->_replaces['frame_body_head'] ?? ''));
        $this->add_rep('frame_body_tail', (string)($this->_replaces['frame_body_tail'] ?? ''));

        if ($with_form) {
            $report_form_class = trim((string)($this->_replaces['report_form_class'] ?? ''));
            $report_form_attrs = trim((string)($this->_replaces['report_form_attrs'] ?? ''));
            $this->add_rep('frame_form_open', '<form action="' . htmlspecialchars((string)$this->_action, ENT_QUOTES) . '" method="post" id="dbx_form_' . $i . '" class="dbxAjax' . ($report_form_class !== '' ? ' ' . $report_form_class : '') . '"' . ($report_form_attrs !== '' ? ' ' . $report_form_attrs : '') . '>');
            $this->add_rep('frame_form_close', '</form>');
        } else {
            $this->add_rep('frame_form_open', '');
            $this->add_rep('frame_form_close', '');
        }
    }

/**
     * Entfernt unbenutzte Report-Bar-Feldslots ({obj:...} ohne Felddefinition).
     *
     * @param string $content
     *
     * @return string
     */
    protected function apply_report_count_replaces($content) {
        $rcount   = (int) $this->_rcount;
        $count_all = ($this->_count_all >= 0) ? (int) $this->_count_all : $rcount;

        $content = str_replace('{pagination:count_all}',      (string) $count_all, (string) $content);
        $content = str_replace('{pagination:count_selected}', (string) $rcount, (string) $content);
        $content = str_replace('{pagination:count_checked}',  (string) $this->get_count_selects(), (string) $content);
        $content = str_replace('{report_extra_stats}', (string)($this->_replaces['report_extra_stats'] ?? ''), (string) $content);
        $content = str_replace('{report_bar_actions}', (string)($this->_replaces['report_bar_actions'] ?? ''), (string) $content);

        return $content;
    }

protected function cleanup_report_bar_slots($content) {
        return preg_replace(
            '/<div class="dbx-report-bar-field"[^>]*>\s*\{obj:[a-z0-9_]+\}\s*<\/div>\s */i',
            '',
            (string) $content
        );
    }

/**
     * Liefert die sichtbaren Auswahlfelder fuer die gemeinsame Reportleiste.
     *
     * Standardmaessig werden nur tatsaechlich definierte `dbx_r*`-Felder
     * verwendet. Interne Zustandsfelder und Hidden-Felder bleiben unsichtbar.
     * Ein Report kann Reihenfolge und Auswahl ueber `_report_bar_flds`
     * ausdruecklich festlegen.
     *
     * @return array<int,string>
     */
    protected function report_bar_field_names(): array {
        $configured = is_array($this->_report_bar_flds)
            ? $this->_report_bar_flds
            : array();
        $names = $configured !== array()
            ? $configured
            : array_keys($this->_flds);
        $visible = array();

        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name === '' || !isset($this->_flds[$name])) {
                continue;
            }
            if ($configured === array() && strpos($name, 'dbx_r') !== 0) {
                continue;
            }
            if (isset($this->_report_state_flds[$name])) {
                continue;
            }

            $field = is_array($this->_flds[$name]) ? $this->_flds[$name] : array();
            $tpl = strtolower(trim((string)($field['tpl'] ?? '')));
            if ($tpl === 'hidden' || $tpl === 'dbx|hidden' || substr($tpl, -7) === '|hidden') {
                continue;
            }

            $visible[] = $name;
        }

        return array_values(array_unique($visible));
    }

/**
     * Baut ausschliesslich Platzhalter fuer die vorhandenen Reportfilter.
     * Die eigentlichen Felder werden danach von der normalen dbxForm-Pipeline
     * gerendert; nicht definierte Felder erzeugen daher auch kein Markup.
     */
    protected function build_report_bar_fields(): string {
        $fields = '';

        foreach ($this->report_bar_field_names() as $name) {
            $field = $this->_flds[$name];
            $slot = trim((string)($field['remap'] ?? ''));
            if ($slot === '') {
                $slot = $name;
            }
            $fields .= '<div class="dbx-report-bar-field" data-report-field="'
                . htmlspecialchars($name, ENT_QUOTES)
                . '">{obj:' . $slot . '}</div>';
        }

        return $fields;
    }

/**
     * Editor-Platzhalter <code>&#35;form_msg_info&#35;</code> im Live-Betrieb als leer behandeln.
     *
     * @param string $msg
     *
     * @return string
     */
    protected function resolve_report_msg_text($msg) {
        $msg = trim((string) $msg);

        if ($msg === '') {
            return '';
        }

        $editor = dbx()->get_system_var('dbx_editor', 0, 'int');

        if (!$editor && preg_match('/^#form_msg_(info|success|error|warning)#$/', $msg)) {
            return '';
        }

        return $msg;
    }

/**
     * Baut die sichtbare Report-Formularmeldung (leer wenn nicht gesetzt).
     *
     * @return string
     */
    protected function build_report_form_msg_html() {
        $error = $this->resolve_report_msg_text($this->_msg_error);

        if ($error === '' && !empty($this->_msg_err)) {
            $error = $this->resolve_report_msg_text($this->_msg_err);
        }

        if ($error !== '') {
            return $this->get_form_msg('error', $error);
        }

        $warning = $this->resolve_report_msg_text($this->_msg_warning);

        if ($warning !== '') {
            return $this->get_form_msg('warning', $warning);
        }

        $success = $this->resolve_report_msg_text($this->_msg_success);

        if ($success !== '') {
            return $this->get_form_msg('success', $success);
        }

        if ($this->submit() && $this->errors()) {
            return $this->get_form_msg('error', 'Pruefen Sie bitte Ihre Eingaben.');
        }

        $info = $this->resolve_report_msg_text($this->_msg_info);

        if ($info !== '') {
            return $this->get_form_msg('info', $info);
        }

        return '';
    }

/**
     * Registriert Report-Aktionen und baut Footer-Select-Metadaten auf.
     *
     * @param string $obj
     * @param string $tpl
     * @param string $action
     * @param mixed  $data
     *
     * @return void
     */
    public function add_action($obj, $tpl, $action = '', $data = '') {
        $dbx_do = $this->parse_report_action_code($action);
        $action_url = (string)$action;

        if ($action_url !== '' && $action_url[0] === '&') {
            $action_url = $this->get_report_action_url() . $action_url;
        }

        if ($action_url !== '') {
            $action_url = dbx()->action_url($action_url);
        }

        parent::add_action($obj, $tpl, $action_url, $data);

        if ($dbx_do === '') {
            return;
        }

        $confirm = '';

        if ($tpl === 'action_button_delete') {
            $confirm = (string) $this->_msg_confirm_delete;
        }

        $this->_report_multi_actions[$dbx_do] = array(
            'obj'     => (string) $obj,
            'label'   => $this->get_report_action_label($tpl),
            'tpl'     => (string) $tpl,
            'action'  => (string) $action_url,
            'confirm' => $confirm,
            'quick'   => in_array($dbx_do, array('multi_select', 'multi_deselect'), true),
        );
    }

/**
     * @param string $action
     *
     * @return string
     */
    protected function parse_report_action_code($action) {
        $action = (string) $action;

        if ($action === '' || $action[0] !== '&') {
            return '';
        }

        if (preg_match('/(?:^|&)dbx_do=([^&]+)/', $action, $match)) {
            return (string) $match[1];
        }

        if (preg_match('/(?:^|&)dbx_run2=([^&]+)/', $action, $match)) {
            return (string) $match[1];
        }

        return '';
    }

/**
     * @param string $tpl
     *
     * @return string
     */
    protected function get_report_action_label($tpl) {
        $labels = array(
            'action_button_delete' => array(
                'key' => 'action_delete_selected',
                'de' => 'Ausgewählte löschen',
                'en' => 'Delete selected',
                'es' => 'Eliminar seleccionados',
            ),
            'action_button_activate' => array(
                'key' => 'action_activate_selected',
                'de' => 'Ausgewählte aktivieren',
                'en' => 'Activate selected',
                'es' => 'Activar seleccionados',
            ),
            'action_button_deactivate' => array(
                'key' => 'action_deactivate_selected',
                'de' => 'Ausgewählte deaktivieren',
                'en' => 'Deactivate selected',
                'es' => 'Desactivar seleccionados',
            ),
            'action_button_select' => array(
                'key' => 'action_select_visible',
                'de' => 'Sichtbare auswählen',
                'en' => 'Select visible',
                'es' => 'Seleccionar visibles',
            ),
            'action_button_deselect' => array(
                'key' => 'action_deselect_visible',
                'de' => 'Sichtbare abwählen',
                'en' => 'Deselect visible',
                'es' => 'Deseleccionar visibles',
            ),
        );

        if (!isset($labels[$tpl])) {
            return (string) $tpl;
        }

        $language = in_array($this->_dbx_lng, array('en', 'es'), true)
            ? $this->_dbx_lng
            : 'de';
        $definition = $labels[$tpl];

        return $this->get_fd_message(
            $definition['key'],
            $definition[$language]
        );
    }

/**
     * Baut Footer mit Aktions-Select und Schnellaktionen.
     *
     * @return void
     */
    protected function build_report_footer_obj() {
        if (!$this->_create_row_select || !$this->_report_multi_actions) {
            return;
        }

        if (isset($this->_obj['report_footer'])) {
            return;
        }

        $select_options = '';
        $action_links   = '';
        $has_select     = 0;

        foreach ($this->_report_multi_actions as $dbx_do => $action) {
            if (!empty($action['quick'])) {
                continue;
            }

            $has_select     = 1;
            $label         = $this->get_report_action_label(
                (string) ($action['tpl'] ?? '')
            );
            $action_suffix  = (string) ($action['action'] ?? '');
            $action_url     = $action_suffix;

            if ($action_suffix !== '' && $action_suffix[0] === '&') {
                $action_url = $this->get_report_action_url() . $action_suffix;
            }

            $select_options .= $this->get_tpl('dbx|report-footer-action-option', array(
                'value' => (string) $dbx_do,
                'label' => $label,
            ));

            $action_link = trim($this->get_tpl((string) ($action['tpl'] ?? 'dbx|action_button'), array(
                'action'  => $action_url,
                'label'   => $label,
                'title'   => $this->get_fd_message(
                    'report_action_confirm_title',
                    array(
                        'de' => 'Aktion bestätigen',
                        'en' => 'Confirm action',
                        'es' => 'Confirmar acción',
                    )[in_array($this->_dbx_lng, array('en', 'es'), true)
                        ? $this->_dbx_lng
                        : 'de']
                ),
                'confirm' => (string) ($action['confirm'] ?? ''),
                'class'   => '',
                'tooltip' => '',
            )));

            if ($action_link !== '') {
                $action_links .= $this->get_tpl('dbx|report-footer-action-link', array(
                    'value'       => (string) $dbx_do,
                    'action_link' => $action_link,
                ));
            }
        }

        if (!$has_select) {
            return;
        }

        $i = (int) $this->_next_i;
        $action_main = $this->get_tpl('dbx|report-footer-action-main', array(
            'action_id'             => 'dbx_report_action_' . $i,
            'report_action_options' => $select_options,
            'report_action_links'   => $action_links,
        ));

        $footer_tpl = trim((string)$this->_tpl_report_footer);
        if ($footer_tpl === '') {
            return;
        }

        $html = trim($this->get_tpl($footer_tpl, array(
            'report_action_main' => $action_main,
        )));

        if ($html !== '') {
            $this->add_obj('report_footer', 'obj-value', $html);
        }
    }

/**
     * Bereitet die drei einheitlichen Report-Bausteine vor.
     *
     * Das Haupttemplate entscheidet nur noch ueber die individuelle Anordnung.
     * Leiste und Footer bleiben ueber Properties austauschbar; Meldungen laufen
     * durch dieselbe sprachabhaengige Message-Pipeline wie Formulare.
     */
    protected function prepare_report_chrome_replaces(): void {
        $bar = '';
        if (trim((string)$this->_tpl_report_bar) !== '') {
            $filter_fields = $this->build_report_bar_fields();
            $has_filters = $filter_fields !== '';
            $bar = $this->get_tpl((string)$this->_tpl_report_bar, array(
                'report:filters' => $filter_fields,
                'report_filter_class' => $has_filters ? '' : 'd-none',
                'report_filter_action' => $has_filters
                    ? $this->get_tpl('dbx|button-bar-filter', array())
                    : '',
            ));
        }

        $footer = isset($this->_obj['report_footer'])
            ? '{obj:report_footer}'
            : '';

        $this->add_rep('report:bar', $bar);
        $this->add_rep('report:message', '{obj:form_msg}');
        $this->add_rep('report:footer', $footer);
    }

/**
     * Fuegt den layoutneutralen Report-Footer nach der ersten Tabelle ein,
     * falls ein aelteres Template noch keinen eigenen Slot besitzt.
     *
     * @param string $content
     *
     * @return string
     */
    protected function inject_report_footer($content) {
        if (!isset($this->_obj['report_footer'])) {
            return (string) $content;
        }

        if (strpos($content, '{obj:report_footer}') !== false) {
            return (string) $content;
        }

        if (strpos($content, 'dbx-report-footer') !== false) {
            return (string) $content;
        }

        return (string) preg_replace(
            '/<\/table>/i',
            '</table>{obj:report_footer}',
            (string) $content,
            1
        );
    }
}
