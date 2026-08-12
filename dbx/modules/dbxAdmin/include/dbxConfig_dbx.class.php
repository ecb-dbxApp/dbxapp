<?php
namespace dbx\dbxAdmin;
dbx()->use_system_class('dbxForm');

class dbxConfig_dbx {
    private const CONFIG_FD = 'fd:dbx|config';
    private const SERVER_FD = 'fd:dbxAdmin|server';
    private const NESTED_SECTIONS = array('ftp', 'mail');
    private const SQL_DB_SECTION = 'db';
    private const SERVER_FIELD_NAMES = array('activ', 'type', 'host', 'dbname', 'user', 'pass', 'port');

    private array $config = array();
    private string $activeTab = 'config';
    private string $activeSubTab = '';
    private array $sectionMessages = array();
    private array $groupMessages = array();
    private $texts;

    private function texts() {
        if ($this->texts) {
            return $this->texts;
        }
        $texts = new \dbxForm();
        $texts->set_form_help_enabled(false);
        $texts->_fd = self::CONFIG_FD;
        $texts->load_fd_messages();
        $this->texts = $texts;
        return $this->texts;
    }

    private function text(string $key, string $default = ''): string {
        return $this->texts()->get_fd_message($key, $default);
    }

    private function format_text(string $key, array $values = array(), string $default = ''): string {
        return $this->texts()->format_fd_message($key, $values, $default);
    }

    public function run() {
        $this->config = dbx()->get_cfg('dbx', '', null, true);
        if (!is_array($this->config)) {
            $this->config = array();
        }

        $this->read_active_state();
        $arraySections = $this->array_sections();

        if ($this->activeTab !== 'config'
            && $this->activeTab !== self::SQL_DB_SECTION
            && !in_array($this->activeTab, $arraySections, true)) {
            $this->activeTab = 'config';
        }

        $tabs = array(
            array(
                'id' => 'config',
                'label' => 'Config',
                'content' => $this->render_config_form(),
            ),
            array(
                'id' => self::SQL_DB_SECTION,
                'label' => $this->section_label(self::SQL_DB_SECTION),
                'content' => $this->render_array_section(self::SQL_DB_SECTION),
            ),
        );

        foreach ($arraySections as $section) {
            $tabs[] = array(
                'id' => $section,
                'label' => $this->section_label($section),
                'content' => $this->render_array_section($section),
            );
        }

        $tabsHtml = $this->render_tabs($tabs, 'dbx_config', $this->activeTab);
        $help = dbx()->get_include_obj('dbxAdminHelp', 'dbxAdmin');

        return $this->get_tpl('config-dbx-shell', array_merge(
            $help->moduleBarTemplateData('config', '', '', '', $this->text('module_subtitle')),
            array(
                'frame_id' => 'dbx_target_config_dbx',
                'frame_panel_class' => 'dbx-config-dbx',
                'frame_form_open' => '',
                'frame_form_close' => '',
                'frame_subbar' => '',
                'frame_body_class' => '',
                'frame_body_head' => '',
                'frame_body_tail' => '',
                'frame_panel_attrs' => '',
                'content' => $tabsHtml,
            )
        ));
    }

    private function tpl() {
        return dbx()->get_system_obj('dbxTPL');
    }

    private function get_tpl(string $tpl, array $data = array()): string {
        return $this->tpl()->get_tpl('dbxAdmin|' . $tpl, $data);
    }

    private function get_dbx_tpl(string $tpl, array $data = array()): string {
        return $this->tpl()->get_tpl('dbx|' . $tpl, $data);
    }

    private function base_action(): string {
        return '?dbx_modul=dbxAdmin&dbx_run1=config&dbx_run2=edit&xmodul=dbx';
    }

    private function read_active_state(): void {
        $tab = (string)($_POST['activeTab'] ?? dbx()->get_modul_var('activeTab', 'config', 'parameter'));
        $subTab = (string)($_POST['activeSubTab'] ?? dbx()->get_modul_var('activeSubTab', '', 'parameter'));

        if ($tab === '') {
            $tab = 'config';
        }

        $this->activeTab = $tab;
        $this->activeSubTab = $subTab;
    }

    private function array_sections(): array {
        $sections = array();

        foreach (self::NESTED_SECTIONS as $section) {
            if (isset($this->config[$section]) && is_array($this->config[$section])) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    private function render_tabs(array $tabs, string $idPrefix, string $activeId): string {
        $tabHtml = '';
        $paneHtml = '';

        foreach ($tabs as $tab) {
            $tabId = (string)($tab['id'] ?? 'tab');
            $id = $this->safe_id($idPrefix . '_' . $tabId);
            $label = (string)($tab['label'] ?? '');
            $content = (string)($tab['content'] ?? '');
            $active = ($tabId === $activeId);

            $tabHtml .= $this->get_tpl('config-dbx-tab', array(
                'id' => $id,
                'label' => $this->h($label),
                'active' => $active ? 'active' : '',
                'selected' => $active ? 'true' : 'false',
            ));

            $paneHtml .= $this->get_tpl('config-dbx-pane', array(
                'id' => $id,
                'content' => $content,
                'active' => $active ? 'show active' : '',
            ));
        }

        return $this->get_tpl('config-dbx-tabs', array(
            'tabs' => $tabHtml,
            'panes' => $paneHtml,
        ));
    }

    private function render_config_form(): string {
        $data = $this->config_form_data();
        $form = $this->new_form('config_dbx_general', $data, $this->text('config_info'));
        $this->add_state_fields($form);
        $form->_fd = self::CONFIG_FD;
        $form->add_flds(self::CONFIG_FD);
        $form->add_fld('default_server', options: $this->sql_server_options());
        $form->add_obj('actions', 'dbxAdmin|config-dbx-save-actions', array('label' => $this->text('config_save')));

        if ($form->submit() && !$form->errors()) {
            $this->apply_config_form_post($form);
            $this->set_form_save_message($form);
        }

        return $form->run();
    }

    private function config_form_data(): array {
        $data = array(
            'activeTab' => 'config',
            'activeSubTab' => '',
        );

        foreach ($this->config_fd_names() as $name) {
            $value = $this->config[$name] ?? '';
            if ($name === 'groups' || $name === 'accessible_lng') {
                $data[$name] = is_array($value) ? implode(',', $value) : (string) $value;
                continue;
            }
            $data[$name] = $this->value_to_field($value);
        }

        return $data;
    }

    private function config_fd_names(): array {
        $names = array();
        $fields = array();
        $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/cfg/config.dd.php');
        if (is_file($file)) {
            include $file;
        }
        if (!is_array($fields)) {
            return $names;
        }
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }
            $names[] = (string) $field['name'];
        }
        return $names;
    }

    private function apply_config_form_post($form): void {
        foreach ($this->config_fd_names() as $name) {
            if (!array_key_exists($name, $form->_post)) {
                continue;
            }
            $raw = $form->_post[$name];
            $oldValue = $this->config[$name] ?? '';
            if ($name === 'groups' || $name === 'accessible_lng') {
                $this->config[$name] = $this->multiselect_to_array($raw);
                continue;
            }
            $this->config[$name] = $this->field_to_top_value($oldValue, (string) $raw);
        }
    }

    private function multiselect_to_array($raw): array {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw), static function ($item) {
                return $item !== '';
            }));
        }
        $parts = preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? array_values($parts) : array();
    }

    private function sql_server_options(): string {
        $options = array();
        foreach ($this->sql_db_servers() as $name => $entry) {
            $options[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $name);
        }
        return implode('&', $options);
    }

    private function sql_db_servers(): array {
        $db = $this->config['db'] ?? array();
        if (!is_array($db)) {
            return array();
        }
        $servers = array();
        foreach ($db as $name => $entry) {
            if (!is_array($entry) || $this->is_module_db_entry((string) $name, $entry)) {
                continue;
            }
            $servers[(string) $name] = $entry;
        }
        return $servers;
    }

    private function is_module_db_entry(string $name, array $entry): bool {
        return dbx()->get_system_obj('dbxConfigStore')->is_module_db_entry($name, $entry);
    }

    private function strip_module_db_from_config(): void {
        if (!isset($this->config['db']) || !is_array($this->config['db'])) {
            return;
        }
        foreach ($this->config['db'] as $name => $entry) {
            if (is_array($entry) && $this->is_module_db_entry((string) $name, $entry)) {
                unset($this->config['db'][$name]);
            }
        }
    }

    private function render_array_section(string $section): string {
        $value = $this->config[$section] ?? array();
        if (!is_array($value)) {
            return $this->get_dbx_tpl('alert-info', array('msg' => $this->text('no_entries')));
        }

        if ($this->is_grouped_section($value)) {
            return $this->render_grouped_section($section, $value);
        }

        return $this->render_section_form($section, $value);
    }

    private function render_grouped_section(string $section, array $groups): string {
        $this->process_group_add($section);
        $this->process_group_action($section);

        $groups = $section === self::SQL_DB_SECTION
            ? $this->sql_db_servers()
            : ($this->config[$section] ?? array());
        if (!is_array($groups)) {
            $groups = array();
        }

        $addForm = $this->render_group_add_form($section, $groups);

        if (!$groups) {
            $emptyMsg = $section === self::SQL_DB_SECTION
                ? $this->text('no_sql_servers')
                : $this->text('no_entries');
            return $this->get_tpl('config-dbx-section', array(
                'add_form' => $addForm,
                'tabs' => $this->get_dbx_tpl('alert-info', array('msg' => $emptyMsg)),
            ));
        }

        $activeGroup = ($this->activeTab === $section) ? $this->activeSubTab : '';
        if ($activeGroup === '' || !isset($groups[$activeGroup])) {
            $keys = array_keys($groups);
            $activeGroup = (string)reset($keys);
        }

        $tabs = array();
        foreach ($groups as $group => $values) {
            if (!is_array($values)) {
                $values = array();
            }

            $tabs[] = array(
                'id' => (string)$group,
                'label' => (string)$group,
                'content' => $this->render_group_form($section, (string)$group, $values),
            );
        }

        return $this->get_tpl('config-dbx-section', array(
            'add_form' => $addForm,
            'tabs' => $this->render_tabs($tabs, 'dbx_config_' . $section, $activeGroup),
        ));
    }

    private function render_section_form(string $section, array $values): string {
        $used = array();
        $fields = $this->collect_fields('cfg_' . $section, $values, array(), $used);
        $data = array(
            'activeTab' => $section,
            'activeSubTab' => '',
        );

        foreach ($fields as $field) {
            $data[$field['name']] = $this->value_to_field($field['value']);
        }

        $label = $this->section_label($section);
        $form = $this->new_form(
            'config_dbx_' . $this->safe_token($section),
            $data,
            $this->format_text('edit_section', array('section' => $label))
        );
        $this->add_state_fields($form);
        $this->add_fields($form, $fields, $label);
        $form->add_obj('actions', 'dbxAdmin|config-dbx-save-actions', array(
            'label' => $this->format_text('save_section', array('section' => $label)),
        ));

        if ($form->submit() && !$form->errors()) {
            foreach ($fields as $name => $field) {
                $oldValue = $field['value'];
                $newValue = (string)($form->_post[$name] ?? '');
                $this->set_path($this->config[$section], $field['path'], $this->field_to_value($oldValue, $newValue));
            }

            $this->set_form_save_message($form);
        }

        return $form->run();
    }

    private function render_group_form(string $section, string $group, array $values): string {
        $form = $this->build_group_form($section, $group, $values);
        $this->apply_group_message($form, $section, $group);

        return $form->run();
    }

    private function process_group_add(string $section): void {
        $form = $this->build_group_add_form($section, $this->config[$section] ?? array());

        if (!$form->submit()) {
            return;
        }

        if ($form->errors()) {
            $this->sectionMessages[$section] = array('error', $this->text('check_new_name'));
            return;
        }

        $entry = trim((string)($form->_post['configEntry'] ?? ''));
        if ($entry === '') {
            $this->sectionMessages[$section] = array('error', $this->text('enter_new_name'));
            return;
        }

        if (!isset($this->config[$section]) || !is_array($this->config[$section])) {
            $this->config[$section] = array();
        }

        if (isset($this->config[$section][$entry])) {
            $this->sectionMessages[$section] = array(
                'error',
                $this->format_text('entry_exists', array('entry' => $entry))
            );
            return;
        }

        $this->config[$section][$entry] = $section === self::SQL_DB_SECTION
            ? array(
                'activ' => '1',
                'type' => 'mysql',
                'host' => '127.0.0.1',
                'dbname' => '',
                'user' => '',
                'pass' => '',
                'port' => '3306',
            )
            : $this->empty_group_template($this->config[$section]);

        if (!$this->save_config()) {
            $this->sectionMessages[$section] = array('error', $this->text('entry_create_error'));
            return;
        }

        $this->activeTab = $section;
        $this->activeSubTab = $entry;
        $this->sectionMessages[$section] = array(
            'success',
            $this->format_text('entry_created', array('entry' => $entry))
        );
    }

    private function process_group_action(string $section): void {
        if (($this->activeTab !== $section) || $this->activeSubTab === '') {
            return;
        }

        $group = $this->activeSubTab;
        if (!isset($this->config[$section][$group]) || !is_array($this->config[$section][$group])) {
            return;
        }

        $values = $this->config[$section][$group];
        $form = $this->build_group_form($section, $group, $values);

        if (!$form->submit()) {
            return;
        }

        if (!empty($_POST['deleteAction'])) {
            unset($this->config[$section][$group]);

            if (!$this->save_config()) {
                $this->sectionMessages[$section] = array(
                    'error',
                    $this->format_text('entry_delete_error', array('entry' => $group))
                );
                return;
            }

            $keys = array_keys((array)$this->config[$section]);
            $this->activeSubTab = $keys ? (string)reset($keys) : '';
            $this->sectionMessages[$section] = array(
                'success',
                $this->format_text('entry_deleted', array('entry' => $group))
            );
            return;
        }

        if ($form->errors()) {
            $this->groupMessages[$section][$group] = array('error', $this->text('check_input'));
            return;
        }

        if ($section === self::SQL_DB_SECTION) {
            $record = $this->server_record_from_post($form->_post);
            if (strtolower((string) ($record['type'] ?? '')) === 'sqlite') {
                $this->groupMessages[$section][$group] = array('error', $this->text('module_sqlite_forbidden'));
                return;
            }
            $this->config[$section][$group] = $record;
        } else {
            foreach ($this->group_fields($section, $group, $values) as $name => $field) {
                $oldValue = $field['value'];
                $newValue = (string)($form->_post[$name] ?? '');
                $this->set_path($this->config[$section][$group], $field['path'], $this->field_to_value($oldValue, $newValue));
            }
        }

        if (!$this->save_config()) {
            $this->groupMessages[$section][$group] = array('error', $this->text('entry_save_error'));
            return;
        }

        $this->groupMessages[$section][$group] = array('success', $this->text('entry_saved'));
    }

    private function render_group_add_form(string $section, array $groups): string {
        $form = $this->build_group_add_form($section, $groups);
        $this->apply_section_message($form, $section);
        return $form->run();
    }

    private function build_group_add_form(string $section, array $groups) {
        $label = $this->section_label($section);
        $form = $this->new_form('config_dbx_' . $this->safe_token($section) . '_add', array(
            'activeTab' => $section,
            'activeSubTab' => '',
            'configAction' => 'add',
            'configEntry' => '',
        ), $this->format_text('new_entry_info', array('section' => $label)));

        $this->add_state_fields($form);
        $form->add_fld('configAction', 'dbx|hidden', rules: 'parameter', dd: '');
        $form->add_fld(
            'configEntry',
            'text-label',
            label: $this->text('label_new_entry'),
            rules: 'parameter|min=1',
            tooltip: $this->text('tooltip_new_entry'),
            dd: ''
        );
        $form->add_obj('actions', 'dbxAdmin|config-dbx-save-actions', array(
            'label' => $this->format_text('create_section', array('section' => $label)),
        ));

        return $form;
    }

    private function build_group_form(string $section, string $group, array $values) {
        $fields = $this->group_fields($section, $group, $values);
        $data = array(
            'activeTab' => $section,
            'activeSubTab' => $group,
            'configAction' => 'save',
        );

        if ($section === self::SQL_DB_SECTION) {
            foreach (self::SERVER_FIELD_NAMES as $name) {
                $data[$name] = (string) ($values[$name] ?? ($name === 'activ' ? '1' : ''));
            }
        } else {
            foreach ($fields as $field) {
                $data[$field['name']] = $this->value_to_field($field['value']);
            }
        }

        $label = $this->section_label($section);
        $form = $this->new_form(
            'config_dbx_' . $this->safe_token($section) . '_' . $this->safe_token($group),
            $data,
            $this->format_text(
                'edit_entry_info',
                array('section' => $label, 'entry' => $group)
            )
        );
        $this->add_state_fields($form);
        $form->add_fld('configAction', 'dbx|hidden', rules: 'parameter', dd: '');
        if ($section === self::SQL_DB_SECTION) {
            $this->add_server_fields($form);
        } else {
            $this->add_fields($form, $fields, $label . ' ' . $group);
        }
        $form->add_obj('actions', 'dbxAdmin|config-dbx-group-actions', array(
            'save_label' => $this->format_text('save_section', array('section' => $label)),
            'delete_label' => $this->format_text('delete_section', array('section' => $label)),
            'confirm' => $this->format_text(
                'confirm_delete_entry',
                array('section' => $label, 'entry' => $group)
            ),
        ));

        return $form;
    }

    private function group_fields(string $section, string $group, array $values): array {
        $used = array();
        return $this->collect_fields('cfg_' . $section . '_' . $group, $values, array(), $used);
    }

    private function new_form(string $fid, array $data, string $info) {
        $form = dbx()->get_system_obj('dbxForm');
        $form->init($fid, 'form-config-dbx');
        $form->_fd = self::CONFIG_FD;
        $form->load_fd_messages();
        $form->_action = $this->base_action();
        $form->_data = array_merge($form->_data, $data);
        $form->_msg_info = $info;
        $form->_fld_change_state = '*';

        return $form;
    }

    private function add_state_fields($form): void {
        $form->add_fld('activeTab', 'dbx|hidden', rules: 'parameter', dd: '');
        $form->add_fld('activeSubTab', 'dbx|hidden', rules: 'parameter', dd: '');
    }

    private function add_fields($form, array $fields, string $tooltipPrefix): void {
        foreach ($fields as $field) {
            $form->add_fld(
                $field['name'],
                $this->field_template($field),
                label: $field['label'],
                rules: '*',
                tooltip: $tooltipPrefix . ': ' . $field['label'],
                dd: ''
            );
        }
    }

    private function field_template(array $field): string {
        $path = (array)($field['path'] ?? array());
        $last = strtolower((string)end($path));
        $sensitive = array('pass', 'password', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'private_key');

        foreach ($sensitive as $needle) {
            if ($last === $needle || str_ends_with($last, '_' . $needle) || str_contains($last, $needle)) {
                return 'password-label';
            }
        }

        return 'text-label';
    }

    private function add_server_fields($form): void {
        foreach (self::SERVER_FIELD_NAMES as $name) {
            $form->add_fld($name, dd: self::SERVER_FD);
        }
    }

    private function server_record_from_post(array $post): array {
        $record = array();
        foreach (self::SERVER_FIELD_NAMES as $name) {
            $record[$name] = trim((string) ($post[$name] ?? ($name === 'activ' ? '1' : '')));
        }
        return $record;
    }

    private function set_form_save_message($form): void {
        if ($this->save_config()) {
            $form->_msg_success = $form->get_fd_message('config_saved');
            return;
        }

        $form->_msg_error = $form->get_fd_message('config_save_error');
    }

    private function save_config(): bool {
        $this->strip_module_db_from_config();
        return (bool)dbx()->set_cfg('dbx', $this->config);
    }

    private function apply_section_message($form, string $section): void {
        if (empty($this->sectionMessages[$section])) {
            return;
        }

        $this->apply_message($form, $this->sectionMessages[$section]);
    }

    private function apply_group_message($form, string $section, string $group): void {
        if (empty($this->groupMessages[$section][$group])) {
            return;
        }

        $this->apply_message($form, $this->groupMessages[$section][$group]);
    }

    private function apply_message($form, array $message): void {
        $mode = (string)($message[0] ?? 'info');
        $text = (string)($message[1] ?? '');

        if ($mode === 'success') {
            $form->_msg_success = $text;
            return;
        }

        if ($mode === 'error') {
            $form->_msg_error = $text;
            return;
        }

        if ($mode === 'warning') {
            $form->_msg_warning = $text;
            return;
        }

        $form->_msg_info = $text;
    }

    private function empty_group_template(array $groups): array {
        foreach ($groups as $group) {
            if (is_array($group)) {
                return $this->empty_like($group);
            }
        }

        return array('name' => '');
    }

    private function empty_like($value) {
        if (is_array($value)) {
            $empty = array();
            foreach ($value as $key => $item) {
                $empty[$key] = $this->empty_like($item);
            }

            return $empty;
        }

        return '';
    }

    private function collect_fields(string $prefix, array $value, array $path, array &$used): array {
        $fields = array();

        foreach ($value as $key => $item) {
            $nextPath = array_merge($path, array((string)$key));

            if (is_array($item)) {
                $fields = array_merge($fields, $this->collect_fields($prefix, $item, $nextPath, $used));
                continue;
            }

            $field = $this->field_definition($prefix, $nextPath, $item, $used);
            $fields[$field['name']] = $field;
        }

        return $fields;
    }

    private function field_definition(string $prefix, array $path, $value, array &$used): array {
        $name = $this->field_name($prefix, $path);
        $baseName = $name;
        $counter = 2;

        while (isset($used[$name])) {
            $name = $baseName . '_' . $counter;
            $counter++;
        }

        $used[$name] = 1;

        return array(
            'name' => $name,
            'path' => $path,
            'label' => implode(' / ', $path),
            'value' => $value,
        );
    }

    private function set_path(array &$target, array $path, $value): void {
        if (!$path) {
            return;
        }

        $key = array_shift($path);

        if (!$path) {
            $target[$key] = $value;
            return;
        }

        if (!isset($target[$key]) || !is_array($target[$key])) {
            $target[$key] = array();
        }

        $this->set_path($target[$key], $path, $value);
    }

    private function value_to_field($value): string {
        if (is_array($value)) {
            if ($this->is_list_array($value)) {
                return implode(',', array_map('strval', $value));
            }

            return http_build_query($value);
        }

        return (string)$value;
    }

    private function field_to_top_value($oldValue, string $value) {
        if (is_array($oldValue)) {
            if ($this->is_list_array($oldValue)) {
                return $value;
            }

            if (strpos($value, '&') !== false) {
                $parsed = array();
                parse_str($value, $parsed);
                return $parsed;
            }

            return $value;
        }

        return $this->field_to_value($oldValue, $value);
    }

    private function field_to_value($oldValue, string $value) {
        if (is_int($oldValue)) {
            return (int)$value;
        }

        if (is_float($oldValue)) {
            return (float)$value;
        }

        if (is_bool($oldValue)) {
            return in_array(strtolower($value), array('1', 'true', 'yes', 'on'), true);
        }

        return $value;
    }

    private function is_list_array(array $value): bool {
        if ($value === array()) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function is_grouped_section(array $value): bool {
        if (!$value) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private function section_label(string $section): string {
        $labels = array(
            'db' => 'SQL-Server',
            'ftp' => 'FTP',
            'mail' => 'Mail',
        );

        return $labels[$section] ?? $section;
    }

    private function field_name(string $prefix, array $parts): string {
        $tokens = array($this->safe_token($prefix));
        foreach ($parts as $part) {
            $tokens[] = $this->safe_token((string)$part);
        }

        return implode('_', $tokens);
    }

    private function safe_token(string $value): string {
        $value = preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $value = trim((string)$value, '_');

        if ($value === '') {
            return 'x';
        }

        return $value;
    }

    private function safe_id(string $value): string {
        return 'dbx_' . strtolower($this->safe_token($value));
    }

    private function h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
