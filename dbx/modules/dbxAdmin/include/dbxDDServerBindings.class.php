<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

/**
 * dbxForm-Oberflaeche fuer installationsbezogene Serverbindungen pro DD.
 */
class dbxDDServerBindings
{
    public function run(): string
    {
        dbx()->get_include_obj('dbxInstallationService', 'dbxSetup');
        $installerClass = '\\dbx\\dbxSetup\\dbxInstallationService';
        $installer = new $installerClass();
        $catalog = $installer->discoverDDs();
        $db = dbx()->get_system_obj('dbxDB');
        $config = dbx()->get_cfg('dbx');
        $bindings = is_array($config['dd_server_bindings'] ?? null)
            ? $config['dd_server_bindings']
            : array();

        $serverOptions = array();
        foreach ((array)($config['db'] ?? array()) as $name => $serverConfig) {
            if (is_array($serverConfig)
                && $db->db_server_config_is_active((string)$name, $serverConfig)
            ) {
                $type = strtoupper((string)($serverConfig['type'] ?? 'DB'));
                $serverOptions[(string)$name] = (string)$name . ' · ' . $type;
            }
        }

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('admin-dd-server-bindings', 'dd-server-bindings');
        $form->_fd = 'dbxAdmin|dd-server-bindings';
        $form->load_fd_messages();
        $form->_action = '?dbx_modul=dbxAdmin&dbx_run1=dd_bindings&dbx_page=admin';
        $form->_msg_info = $form->get_fd_message('intro');
        $form->_data = array_merge($form->_data, array('binding_save' => 1));
        $form->add_fld('binding_save', 'dbx|hidden', rules: 'int', dd: '');

        $fields = array();
        $allowedValues = array();
        foreach ($catalog as $record) {
            $ddRef = (string)$record['dd'];
            $info = $db->get_dd_server_binding_info($ddRef);
            $declared = (string)($info['declared_server'] ?? '');
            $field = 'binding_' . substr(hash('sha256', $ddRef), 0, 16);
            $fields[$field] = $ddRef;

            $options = array(
                '__default__' => $form->format_fd_message(
                    'dd_default',
                    array('server' => $declared)
                ),
            ) + $serverOptions;
            if ($declared !== '' && !isset($options[$declared])) {
                $options[$declared] = $declared . ' · DB3/DD';
            }
            $current = (string)($bindings[$ddRef] ?? '__default__');
            if ($current !== '__default__' && !isset($options[$current])) {
                $options[$current] = $current . ' · ' . $form->get_fd_message('custom_binding');
            }
            $allowedValues[$field] = array_fill_keys(array_keys($options), true);

            $form->_data[$field] = $current;
            $form->add_fld(
                $field,
                'select-single-label',
                label: $ddRef,
                rules: 'parameter',
                options: $options,
                tooltip: $form->format_fd_message(
                    'binding_tooltip',
                    array(
                        'table' => (string)$db->get_dd_table($ddRef),
                        'server' => $declared,
                    )
                ),
                dd: ''
            );
        }

        if ($form->submit() && !$form->errors()) {
            $newBindings = array();
            foreach ($fields as $field => $ddRef) {
                $value = trim((string)$form->get_post_data(
                    $field,
                    '__default__',
                    'parameter'
                ));
                if ($value === '__default__') {
                    continue;
                }
                if (!isset($allowedValues[$field][$value])) {
                    $form->add_fld_error(
                        $field,
                        $form->get_fd_message('binding_invalid')
                    );
                    continue;
                }
                $newBindings[$ddRef] = $value;
            }
            ksort($newBindings);

            if (!$form->errors()) {
                if (!dbx()->set_local_config_section(
                    'dbx',
                    'dd_server_bindings',
                    $newBindings
                )) {
                    $form->set_error($form->get_fd_message('save_error'));
                } else {
                    $form->_msg_success = $form->format_fd_message(
                        'save_success_count',
                        array('count' => count($newBindings))
                    );
                    $bindings = $newBindings;
                }
            }
        }

        foreach (array(
            'bar_title' => $form->get_fd_message('bar_title'),
            'bar_subtitle' => $form->get_fd_message('bar_subtitle'),
            'bar_icon' => 'bi-database-gear',
            'bar_actions' => '',
            'bar_class' => 'dbx-bar--module',
            'bar_title_class' => 'dbx-bar-title',
            'bar_title_pre' => '',
            'bar_title_heading_attrs' => '',
            'bar_middle' => '',
            'bar_extra' => '',
            'bar_actions_class' => 'dbx-bar-actions',
        ) as $name => $value) {
            $form->add_rep($name, $value);
        }
        $form->add_rep('save_label', $form->get_fd_message('save_label'));
        $form->add_rep('binding_count', (string)count($bindings));
        $form->add_rep(
            'binding_count_label',
            $form->get_fd_message('binding_count_label')
        );
        $form->add_rep('dd_count', (string)count($catalog));
        $form->add_rep('dd_count_label', $form->get_fd_message('dd_count_label'));

        return $form->run();
    }
}
