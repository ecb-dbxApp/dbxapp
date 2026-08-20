<?php

namespace dbx\dbxAdmin;

trait dbxDashboardUiDefaultsServiceTrait
{
    private function process_ui_defaults_action(string $action): void
    {
        $this->ui_defaults_message = '';
        $this->ui_defaults_message_error = false;
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            $this->ui_defaults_message = 'UI-Standards können nur per Formular geändert werden.';
            $this->ui_defaults_message_error = true;
            return;
        }
        $service = dbx()->get_system_obj('dbxUiSettingsService');
        $context = $service->normalize_context((string)($_POST['ui_defaults_context'] ?? 'desktop'));

        try {
            if ($action === 'save') {
                $json = (string)($_POST['ui_defaults_json'] ?? '');
                if (strlen($json) > 131072) {
                    throw new \RuntimeException('Die übertragenen UI-Einstellungen sind zu umfangreich.');
                }
                $settings = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($settings)) {
                    throw new \RuntimeException('Die UI-Einstellungen sind ungültig.');
                }
                $count = $service->save_defaults($context, $settings, (int)dbx()->user());
                $this->ui_defaults_message = sprintf(
                    '%d UI-Standards für %s wurden gespeichert.',
                    $count,
                    $context === 'mobile' ? 'Mobile' : 'Desktop'
                );
                return;
            }

            if ($action === 'clear') {
                if (!$service->clear_defaults($context)) {
                    throw new \RuntimeException('Die UI-Standards konnten nicht gelöscht werden.');
                }
                $this->ui_defaults_message = sprintf(
                    'Die UI-Standards für %s wurden auf den Produktstandard zurückgesetzt.',
                    $context === 'mobile' ? 'Mobile' : 'Desktop'
                );
            }
        } catch (\Throwable $exception) {
            $this->ui_defaults_message = $exception->getMessage();
            $this->ui_defaults_message_error = true;
        }
    }

    private function ui_defaults_panel(): string
    {
        $service = dbx()->get_system_obj('dbxUiSettingsService');
        try {
            $service->ensure_schema();
            $payload = $service->load_defaults();
        } catch (\Throwable $exception) {
            $payload = array('desktop' => array(), 'mobile' => array());
            $this->ui_defaults_message = $exception->getMessage();
            $this->ui_defaults_message_error = true;
        }

        $message = '';
        if ($this->ui_defaults_message !== '') {
            $tone = $this->ui_defaults_message_error ? 'danger' : 'success';
            $icon = $this->ui_defaults_message_error ? 'bi-exclamation-triangle' : 'bi-check-circle';
            $message = '<div class="alert alert-' . $tone . ' d-flex align-items-center gap-2" role="alert">'
                . '<i class="bi ' . $icon . '" aria-hidden="true"></i><span>'
                . dbx()->esc($this->ui_defaults_message) . '</span></div>';
        }

        $save_action = dbx()->action_url(
            '?dbx_modul=dbxAdmin&dbx_run1=dashboard&dbx_run2=ui_defaults_save'
            . '&dbx_do=ui_defaults_save&rid=ui_defaults'
        );
        $clear_action = dbx()->action_url(
            '?dbx_modul=dbxAdmin&dbx_run1=dashboard&dbx_run2=ui_defaults_delete'
            . '&dbx_do=ui_defaults_delete&rid=ui_defaults'
        );

        $meta = is_array($payload['_meta'] ?? null) ? $payload['_meta'] : array();
        $desktop = is_array($payload['desktop'] ?? null) ? $payload['desktop'] : array();
        $mobile = is_array($payload['mobile'] ?? null) ? $payload['mobile'] : array();
        return dbx()->get_system_obj('dbxTPL')->get_tpl(
            'dbxAdmin|admin-dashboard-ui-defaults',
            array(
                'message' => $message,
                'save_action' => dbx()->esc($save_action),
                'clear_action' => dbx()->esc($clear_action),
                'desktop_count' => (string)count($desktop),
                'mobile_count' => (string)count($mobile),
                'desktop_meta' => $this->ui_defaults_meta($meta['desktop'] ?? array()),
                'mobile_meta' => $this->ui_defaults_meta($meta['mobile'] ?? array()),
                'desktop_settings' => $this->ui_defaults_rows($desktop),
                'mobile_settings' => $this->ui_defaults_rows($mobile),
                'desktop_json' => $service->settings_json_html($desktop),
                'mobile_json' => $service->settings_json_html($mobile),
                'desktop_load_disabled' => $desktop ? '' : 'disabled',
                'mobile_load_disabled' => $mobile ? '' : 'disabled',
            )
        );
    }

    private function ui_defaults_meta($meta): string
    {
        if (!is_array($meta) || trim((string)($meta['update_date'] ?? '')) === '') {
            return 'Noch kein Admin-Standard gespeichert';
        }

        $timestamp = strtotime((string)$meta['update_date']);
        $date = $timestamp === false ? (string)$meta['update_date'] : date('d.m.Y H:i', $timestamp);
        return 'Revision ' . max(1, (int)($meta['revision'] ?? 1))
            . ' · ' . dbx()->esc($date)
            . ' · Benutzer ' . max(0, (int)($meta['update_uid'] ?? 0));
    }

    private function ui_defaults_rows($settings): string
    {
        if (!is_array($settings) || !$settings) {
            return '<div class="dbx-admin-ui-defaults-empty">Produktstandard aktiv</div>';
        }

        $rows = '';
        foreach ($settings as $storage_key => $value) {
            $display_key = preg_replace('/^dbx\.UI\./', '', (string)$storage_key);
            $display_value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $rows .= '<div class="dbx-admin-ui-defaults-row"><code>'
                . dbx()->esc((string)$display_key) . '</code><span>'
                . dbx()->esc(is_string($display_value) ? $display_value : '') . '</span></div>';
        }
        return $rows;
    }

}
