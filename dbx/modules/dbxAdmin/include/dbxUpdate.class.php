<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

/** Administrativer Controller fuer Kernel, Komponenten und Marktplatz. */
final class dbxUpdate
{
    public function run(): string
    {
        dbx()->get_system_obj('dbxPackageManager', 'use');
        $manager = \dbxPackageManager::configured();

        $form = dbx()->get_system_obj('dbxForm');
        $form->init('admin-package-marketplace', 'admin-update');
        $form->set_field_definition('dbxAdmin|admin-update');
        $form->load_fd_messages();
        $form->set_msg_info('');
        $form->set_form_help_enabled(false);
        $form->set_action('?dbx_modul=dbxAdmin&dbx_run1=update&dbx_page=admin');
        $form->set_data_value('update_request', 1);
        $form->set_data_value('update_operation', '');
        $form->set_data_value('package_install_id', '');
        $form->add_fld('update_request', 'hidden', rules: 'int', dd: '');
        $form->add_fld('update_operation', 'hidden', rules: 'parameter', dd: '');
        $form->add_fld('package_install_id', 'hidden', rules: 'parameter', dd: '');

        $run2 = (string)dbx()->get_request_var('dbx_run2', '', 'parameter');
        if ($run2 === 'package_section') {
            return $this->section($manager, $form);
        }

        $force = false;
        if ($form->submit()) {
            $operation = (string)$form->get_fld_value('update_operation', '', 'parameter');
            $direct_install_id = (string)$form->get_fld_value('package_install_id', '', 'parameter');
            if ($direct_install_id !== '') {
                $operation = 'package_install_now';
            }
            try {
                switch ($operation) {
                    case 'catalog_refresh':
                        $force = true;
                        $form->set_msg_ok($form->get_fd_message('catalog_refreshed'));
                        break;
                    case 'package_prepare':
                        $prepared = $manager->prepare($this->selected_package_ids(), true);
                        $form->set_msg_ok($form->format_fd_message('packages_prepared', array(
                            'count' => count((array)$prepared['packages']),
                        )));
                        break;
                    case 'package_install':
                        $installed = $manager->install_prepared();
                        $form->set_msg_ok($form->format_fd_message('packages_installed', array(
                            'count' => count((array)$installed['packages']),
                        )));
                        break;
                    case 'package_install_now':
                        $manager->prepare(array($this->valid_package_id($direct_install_id)), true);
                        $installed = $manager->install_prepared();
                        $form->set_msg_ok($form->format_fd_message('packages_downloaded_installed', array(
                            'count' => count((array)$installed['packages']),
                        )));
                        break;
                    case 'package_stop':
                        $stopped = $manager->cancel();
                        $form->set_msg_ok($form->format_fd_message('packages_stopped', array(
                            'count' => count((array)$stopped['packages']),
                        )));
                        break;
                    case 'package_rollback':
                        $rolled_back = $manager->rollback();
                        $form->set_msg_ok($form->format_fd_message('packages_rolled_back', array(
                            'count' => count((array)$rolled_back['packages']),
                        )));
                        break;
                    default:
                        $form->set_error($form->get_fd_message('operation_invalid'));
                }
            } catch (\Throwable $exception) {
                $form->set_error($exception->getMessage());
            }
        }

        try {
            // Der erste Seitenaufbau benoetigt nur Manifest- und Katalogdaten.
            // Die teuren Datei-Hashes werden je Bereich lazy nachgeladen.
            $status = $manager->status($force, array());
        } catch (\Throwable $exception) {
            $form->set_error($exception->getMessage());
            $status = array(
                'installed' => $manager->inventory(false),
                'available' => array(),
                'catalog' => array('generated_at' => ''),
                'staged' => array(),
                'rollback' => array(),
            );
        }

        $installed = (array)$status['installed'];
        $available = (array)$status['available'];
        $updates = array_filter($available, static fn(array $package): bool => !empty($package['update_available']));
        $staged_count = count((array)($status['staged']['packages'] ?? array()));
        $rollback_available = is_dir((string)($status['rollback']['backup_directory'] ?? ''))
            && empty($status['rollback']['rolled_back_at']);
        $kernel = $installed['dbxapp/kernel/dbxapp'] ?? array('version' => trim((string)@file_get_contents(dbx()->get_base_dir() . 'VERSION')));

        $replacements = array(
            'bar_title' => $form->get_fd_message('bar_title'),
            'bar_subtitle' => $form->get_fd_message('bar_subtitle'),
            'bar_icon' => 'bi-boxes',
            'bar_actions' => '',
            'bar_class' => 'dbx-bar--module',
            'bar_title_class' => 'dbx-bar-title',
            'bar_title_pre' => '',
            'bar_title_heading_attrs' => '',
            'bar_middle' => '',
            'bar_extra' => '',
            'bar_actions_class' => 'dbx-bar-actions',
            'intro' => $form->get_fd_message('intro'),
            'kernel_label' => $form->get_fd_message('kernel_label'),
            'installed_label' => $form->get_fd_message('installed_label'),
            'updates_label' => $form->get_fd_message('updates_label'),
            'staged_label' => $form->get_fd_message('staged_label'),
            'kernel_version' => dbx()->esc((string)($kernel['version'] ?? '—')),
            'installed_count' => (string)count($installed),
            'updates_count' => (string)count($updates),
            'staged_count' => (string)$staged_count,
            'catalog_refresh_label' => $form->get_fd_message('catalog_refresh_label'),
            'prepare_label' => $form->get_fd_message('prepare_label'),
            'install_label' => $form->get_fd_message('install_label'),
            'stop_label' => $form->get_fd_message('stop_label'),
            'rollback_label' => $form->get_fd_message('rollback_label'),
            'install_confirm' => dbx()->esc($form->get_fd_message('install_confirm')),
            'rollback_confirm' => dbx()->esc($form->get_fd_message('rollback_confirm')),
            'prepare_disabled' => $this->has_actionable($available) && $staged_count === 0 ? '' : 'disabled',
            'install_disabled' => $staged_count > 0 ? '' : 'disabled',
            'stop_disabled' => $staged_count > 0 ? '' : 'disabled',
            'rollback_disabled' => $rollback_available ? '' : 'disabled',
            'nav_kernel' => $form->get_fd_message('nav_kernel'),
            'nav_modules' => $form->get_fd_message('nav_modules'),
            'nav_designs' => $form->get_fd_message('nav_designs'),
            'nav_marketplace' => $form->get_fd_message('nav_marketplace'),
            'kernel_html' => $this->lazy_section('kernel', $form),
            'modules_html' => $this->lazy_section('module', $form),
            'designs_html' => $this->lazy_section('design', $form),
            'marketplace_html' => $this->lazy_section('marketplace', $form),
            'security_title' => $form->get_fd_message('security_title'),
            'security_text' => $form->get_fd_message('security_text'),
            'vendor_title' => $form->get_fd_message('vendor_title'),
            'vendor_text' => $form->get_fd_message('vendor_text'),
            'msg_class' => '',
        );
        foreach ($replacements as $key => $value) {
            $form->add_rep($key, $value);
        }
        return $form->run();
    }

    private function section(\dbxPackageManager $manager, \dbxForm $form): string
    {
        $type = (string)dbx()->get_request_var('package_type', '', 'parameter');
        if (!in_array($type, array('kernel', 'module', 'design', 'marketplace'), true)) {
            return '<div class="alert alert-danger">' . dbx()->esc($form->get_fd_message('section_invalid')) . '</div>';
        }
        try {
            $audit_types = $type === 'marketplace' ? array() : array($type);
            $status = $manager->status(false, $audit_types);
            $installed = (array)$status['installed'];
            $available = (array)$status['available'];
            if ($type === 'marketplace') {
                $missing = array_filter($available, static fn(array $package): bool => !empty($package['install_available']));
                return $this->render_marketplace($missing, $form);
            }
            return $this->render_type($type, $installed, $available, $form);
        } catch (\Throwable $exception) {
            return '<div class="alert alert-danger">' . dbx()->esc($exception->getMessage()) . '</div>';
        }
    }

    private function lazy_section(string $type, \dbxForm $form): string
    {
        $target = 'dbx_package_' . $type . '_content';
        $url = '?dbx_modul=dbxAdmin&dbx_run1=update&dbx_run2=package_section&dbx_page=admin&package_type=' . rawurlencode($type);
        return '<div id="' . $target . '" class="dbx-package-lazy" data-dbx-package-lazy="' . dbx()->esc($type) . '">'
            . '<a class="dbx-package-loader" href="' . dbx()->esc($url) . '" data-error-label="'
            . dbx()->esc($form->get_fd_message('section_load_error')) . '"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> '
            . dbx()->esc($form->get_fd_message('loading_section')) . '</a></div>';
    }

    /** @return array<int,string> */
    private function selected_package_ids(): array
    {
        $raw = $_POST['package_ids'] ?? array();
        if (!is_array($raw)) {
            return array();
        }
        $ids = array();
        foreach ($raw as $id) {
            $id = trim((string)$id);
            if (preg_match('#^[a-z0-9][a-z0-9._-]{1,62}/(?:kernel|module|design)/[A-Za-z0-9][A-Za-z0-9_-]{1,62}$#', $id)) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function valid_package_id(string $id): string
    {
        $id = trim($id);
        if (!preg_match('#^[a-z0-9][a-z0-9._-]{1,62}/(?:kernel|module|design)/[A-Za-z0-9][A-Za-z0-9_-]{1,62}$#', $id)) {
            throw new \RuntimeException('Die Paketkennung ist ungueltig.');
        }
        return $id;
    }

    /** @param array<string,array<string,mixed>> $available */
    private function has_actionable(array $available): bool
    {
        foreach ($available as $package) {
            if (!empty($package['actionable'])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,array<string,mixed>> $installed @param array<string,array<string,mixed>> $available */
    private function render_type(string $type, array $installed, array $available, \dbxForm $form): string
    {
        $cards = array();
        foreach ($installed as $id => $current) {
            if (($current['type'] ?? '') !== $type) {
                continue;
            }
            $cards[] = $this->package_card($current, $available[$id] ?? array(), $form, true);
        }
        // Noch nicht installierte Komponenten stehen direkt in ihrem
        // fachlichen Bereich bereit und nicht nur in der Gesamtuebersicht.
        if (in_array($type, array('module', 'design'), true)) {
            foreach ($available as $id => $package) {
                if (($package['type'] ?? '') !== $type || isset($installed[$id])) {
                    continue;
                }
                $cards[] = $this->package_card(array(), $package, $form, false);
            }
        }
        return $cards !== array()
            ? implode('', $cards)
            : '<div class="dbx-market-empty">' . dbx()->esc($form->get_fd_message('no_packages')) . '</div>';
    }

    /** @param array<string,array<string,mixed>> $missing */
    private function render_marketplace(array $missing, \dbxForm $form): string
    {
        if ($missing === array()) {
            return '<div class="dbx-market-empty"><i class="bi bi-shop"></i> '
                . dbx()->esc($form->get_fd_message('marketplace_empty')) . '</div>';
        }
        $vendors = array();
        foreach ($missing as $package) {
            $vendor = (string)($package['vendor']['name'] ?? '—');
            $vendors[$vendor][] = $package;
        }
        ksort($vendors, SORT_NATURAL | SORT_FLAG_CASE);
        $html = '';
        foreach ($vendors as $vendor => $packages) {
            $html .= '<section class="dbx-market-vendor"><h3><i class="bi bi-building-check"></i> '
                . dbx()->esc($vendor) . '</h3><div class="dbx-market-grid">';
            foreach ($packages as $package) {
                $html .= $this->package_card(array(), $package, $form, false);
            }
            $html .= '</div></section>';
        }
        return $html;
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $available */
    private function package_card(array $current, array $available, \dbxForm $form, bool $installed): string
    {
        $package = $available !== array() ? $available : $current;
        $id = (string)($package['id'] ?? $current['id'] ?? '');
        $title = (string)($package['title'] ?? $package['name'] ?? $id);
        $vendor = (string)($package['vendor']['name'] ?? $current['vendor']['name'] ?? '—');
        $current_version = (string)($current['version'] ?? '—');
        $available_version = (string)($available['version'] ?? $current_version);
        $license = (string)($package['license'] ?? 'free');
        $managed = !empty($current['managed']) || (!$installed && !empty($package['managed']));
        $drift = (array)($available['drift'] ?? $current['drift'] ?? array());
        $actionable = !empty($available['actionable']);
        $state_class = $drift !== array() ? 'is-drift' : ($actionable ? 'is-actionable' : '');
        $badges = '<span class="badge text-bg-secondary">' . dbx()->esc($vendor) . '</span> '
            . '<span class="badge ' . ($license === 'paid' ? 'text-bg-warning' : ($license === 'private' ? 'text-bg-dark' : 'text-bg-success')) . '">'
            . dbx()->esc($form->get_fd_message('license_' . $license)) . '</span>';
        if (!$managed) {
            $badges .= ' <span class="badge text-bg-info">' . dbx()->esc($form->get_fd_message('local_unmanaged')) . '</span>';
        }
        if ($drift !== array()) {
            $badges .= ' <span class="badge text-bg-danger">' . dbx()->esc($form->get_fd_message('local_changes')) . '</span>';
        }
        $versions = $installed
            ? dbx()->esc($form->get_fd_message('installed_short')) . ' <strong>' . dbx()->esc($current_version) . '</strong>'
            : dbx()->esc($form->get_fd_message('not_installed'));
        if ($available !== array() && version_compare($available_version, $current_version, '>')) {
            $versions .= ' <i class="bi bi-arrow-right"></i> <strong>' . dbx()->esc($available_version) . '</strong>';
        }
        $permissions = array_map(static fn($value): string => dbx()->esc((string)$value), (array)($package['permissions'] ?? array()));
        $language = strtolower((string)dbx()->get_system_var('dbx_lng', 'de'));
        if (!in_array($language, array('de', 'en', 'es'), true)) {
            $language = 'de';
        }
        $descriptions = (array)($package['descriptions'] ?? array());
        $description = trim((string)($descriptions[$language] ?? $package['description'] ?? ''));
        $icon = preg_match('/^bi-[a-z0-9-]+$/', (string)($package['icon'] ?? ''))
            ? (string)$package['icon'] : 'bi-box-seam';
        $image = trim((string)($package['image'] ?? ''));
        $visual = '<span class="dbx-market-icon"><i class="bi ' . dbx()->esc($icon) . '"></i></span>';
        if ($image !== ''
            && preg_match('#^dbx/(?:modules|design)/[A-Za-z0-9_-]+/[A-Za-z0-9_./-]+\.(?:svg|png|webp|jpe?g|gif)$#i', $image)
            && is_file(rtrim((string)dbx()->get_base_dir(), '/\\') . '/' . $image)) {
            $visual = '<img class="dbx-market-image" src="' . dbx()->esc(rtrim((string)dbx()->get_base_url(), '/') . '/' . $image)
                . '" alt="' . dbx()->esc($title) . '" loading="lazy">';
        }
        $select = '';
        if ($actionable) {
            $select = '<div class="dbx-market-actions"><label class="dbx-market-select"><input type="checkbox" name="package_ids[]" value="'
                . dbx()->esc($id) . '"> '
                . dbx()->esc($installed ? $form->get_fd_message('select_update') : $form->get_fd_message('select_install')) . '</label>';
            if (!$installed) {
                $select .= '<button class="btn btn-sm btn-primary dbxConfirm" type="submit" name="package_install_id" value="'
                    . dbx()->esc($id) . '" data-confirm="' . dbx()->esc($form->get_fd_message('download_install_confirm'))
                    . '" data-confirm-buttons="yesno"><i class="bi bi-cloud-arrow-down"></i> '
                    . dbx()->esc($form->get_fd_message('download_install_label')) . '</button>';
            }
            $select .= '</div>';
        }
        if ($select === '' && ($package['license'] ?? '') === 'paid'
            && !empty($package['purchase_url']) && empty($available['entitled'])
            && (!$installed || !empty($available['update_available']))) {
            $select = '<a class="btn btn-sm btn-outline-warning" href="' . dbx()->esc((string)$package['purchase_url'])
                . '" target="_blank" rel="noopener noreferrer"><i class="bi bi-key"></i> '
                . dbx()->esc($form->get_fd_message('purchase_label')) . '</a>';
        }
        return '<article class="dbx-market-card ' . $state_class . '"><div class="dbx-market-card-head"><div class="dbx-market-identity">'
            . $visual . '<div><h3>' . dbx()->esc($title) . '</h3><code>' . dbx()->esc($id) . '</code></div></div>' . $select . '</div>'
            . '<div class="dbx-market-badges">' . $badges . '</div>'
            . '<p class="dbx-market-description">' . dbx()->esc($description) . '</p>'
            . '<div class="dbx-market-version">' . $versions . '</div>'
            . ($permissions !== array() ? '<div class="dbx-market-permissions"><i class="bi bi-shield-check"></i> ' . implode(', ', $permissions) . '</div>' : '')
            . '</article>';
    }
}
