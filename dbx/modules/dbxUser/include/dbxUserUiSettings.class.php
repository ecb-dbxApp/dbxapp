<?php

declare(strict_types=1);

namespace dbx\dbxUser;

final class dbxUserUiSettings
{
    private string $message = '';
    private bool $message_error = false;

    public function run(): string
    {
        $user_id = (int)dbx()->user();
        if ($user_id <= 0) {
            return '<div class="alert alert-warning">Anmeldung erforderlich.</div>';
        }

        $service = dbx()->get_system_obj('dbxUiSettingsService');
        $action = (string)dbx()->get_modul_var('dbx_do', '', 'parameter');
        $is_post = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
        if ($action !== '' && !$is_post) {
            $this->message = 'UI-Profile können nur per Formular geändert werden.';
            $this->message_error = true;
        } elseif ($action === 'ui_profile_save') {
            $this->save($service, $user_id);
        } elseif ($action === 'ui_profile_delete') {
            $this->clear($service, $user_id);
        }

        try {
            $service->ensure_user_schema();
            $profiles = $service->load_user_profiles($user_id);
        } catch (\Throwable $exception) {
            $profiles = array('desktop' => array(), 'mobile' => array());
            $this->message = $exception->getMessage();
            $this->message_error = true;
        }

        $save_action = dbx()->action_url(
            '?dbx_modul=dbxUser&dbx_run1=ui_settings&dbx_do=ui_profile_save&rid=ui_profile'
        );
        $clear_action = dbx()->action_url(
            '?dbx_modul=dbxUser&dbx_run1=ui_settings&dbx_do=ui_profile_delete&rid=ui_profile'
        );
        $meta = is_array($profiles['_meta'] ?? null) ? $profiles['_meta'] : array();
        $desktop = is_array($profiles['desktop'] ?? null) ? $profiles['desktop'] : array();
        $mobile = is_array($profiles['mobile'] ?? null) ? $profiles['mobile'] : array();

        return dbx()->get_system_obj('dbxTPL')->get_tpl('dbxUser|ui-settings', array(
            'message' => $this->message_html(),
            'save_action' => dbx()->esc($save_action),
            'clear_action' => dbx()->esc($clear_action),
            'desktop_count' => (string)count($desktop),
            'mobile_count' => (string)count($mobile),
            'desktop_meta' => $this->meta_text($meta['desktop'] ?? array()),
            'mobile_meta' => $this->meta_text($meta['mobile'] ?? array()),
            'desktop_json' => $service->settings_json_html($desktop),
            'mobile_json' => $service->settings_json_html($mobile),
            'desktop_load_disabled' => $desktop ? '' : 'disabled',
            'mobile_load_disabled' => $mobile ? '' : 'disabled',
        ));
    }

    private function save($service, int $user_id): void
    {
        try {
            $json = (string)($_POST['ui_settings_json'] ?? '');
            if (strlen($json) > 131072) {
                throw new \RuntimeException('Das UI-Profil ist zu umfangreich.');
            }
            $settings = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($settings)) {
                throw new \RuntimeException('Das UI-Profil ist ungültig.');
            }
            $context = $service->normalize_context((string)($_POST['ui_settings_context'] ?? 'desktop'));
            $count = $service->save_user_profile($context, $settings, $user_id);
            $this->message = sprintf('%d UI-Einstellungen wurden sessionübergreifend gespeichert.', $count);
        } catch (\Throwable $exception) {
            $this->message = $exception->getMessage();
            $this->message_error = true;
        }
    }

    private function clear($service, int $user_id): void
    {
        try {
            $context = $service->normalize_context((string)($_POST['ui_settings_context'] ?? 'desktop'));
            if (!$service->clear_user_profile($context, $user_id)) {
                throw new \RuntimeException('Das UI-Profil konnte nicht gelöscht werden.');
            }
            $this->message = 'Das persönliche UI-Profil wurde entfernt. Der Systemstandard ist wieder aktiv.';
        } catch (\Throwable $exception) {
            $this->message = $exception->getMessage();
            $this->message_error = true;
        }
    }

    private function message_html(): string
    {
        if ($this->message === '') {
            return '';
        }
        $tone = $this->message_error ? 'danger' : 'success';
        $icon = $this->message_error ? 'bi-exclamation-triangle' : 'bi-check-circle';
        return '<div class="alert alert-' . $tone . ' d-flex align-items-center gap-2" role="alert">'
            . '<i class="bi ' . $icon . '" aria-hidden="true"></i><span>'
            . dbx()->esc($this->message) . '</span></div>';
    }

    private function meta_text($meta): string
    {
        if (!is_array($meta) || trim((string)($meta['update_date'] ?? '')) === '') {
            return 'Noch nicht gespeichert';
        }
        $timestamp = strtotime((string)$meta['update_date']);
        $date = $timestamp === false ? (string)$meta['update_date'] : date('d.m.Y H:i', $timestamp);
        return 'Revision ' . max(1, (int)($meta['revision'] ?? 1)) . ' · ' . dbx()->esc($date);
    }

}
