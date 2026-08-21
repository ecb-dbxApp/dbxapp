<?php

declare(strict_types=1);

/**
 * Verwaltet installationsweite UI-Standards fuer Benutzer ohne eigene Werte.
 *
 * Persoenliche Werte bleiben im Browser unter dbx.UI.*. Der Service speichert
 * nur explizit freigegebene, stabile Darstellungsschluessel und trennt sie in
 * Desktop- und Mobile-Kontexte.
 */
class dbxUiSettingsService
{
    public const DD = 'dbx|dbxUiDefault';
    public const USER_DD = 'dbx|dbxUiProfile';
    public const CONTEXT_DESKTOP = 'desktop';
    public const CONTEXT_MOBILE = 'mobile';
    private const MAX_SETTINGS = 500;
    private const MAX_JSON_BYTES = 131072;

    /** @return array<string,array<string,mixed>> */
    public function load_defaults(): array
    {
        $defaults = array(
            self::CONTEXT_DESKTOP => array(),
            self::CONTEXT_MOBILE => array(),
        );

        try {
            $rows = dbx()->get_system_obj('dbxDB')->select(
                self::DD,
                array(),
                array(
                    'context' => 'Kontext',
                    'settings' => 'Einstellungen',
                    'revision' => 'Revision',
                    'update_date' => 'Geaendert',
                    'update_uid' => 'Benutzer',
                ),
                'context',
                'ASC',
                '',
                2,
                0,
                0
            );
        } catch (Throwable $exception) {
            return $defaults;
        }

        foreach (is_array($rows) ? $rows : array() as $row) {
            $stored_context = strtolower(trim((string)($row['context'] ?? '')));
            if (!in_array($stored_context, array(self::CONTEXT_DESKTOP, self::CONTEXT_MOBILE), true)) {
                continue;
            }
            $context = $this->normalize_context($stored_context);
            $decoded = json_decode((string)($row['settings'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            $defaults[$context] = $this->normalize_settings($decoded, $context);
            $defaults['_meta'][$context] = array(
                'revision' => max(0, (int)($row['revision'] ?? 0)),
                'update_date' => (string)($row['update_date'] ?? ''),
                'update_uid' => max(0, (int)($row['update_uid'] ?? 0)),
            );
        }

        return $defaults;
    }

    public function ensure_schema(): bool
    {
        return dbx()->get_system_obj('dbxDD')->create_db_tab(self::DD) === 1;
    }

    public function ensure_user_schema(): bool
    {
        return dbx()->get_system_obj('dbxDD')->create_db_tab(self::USER_DD) === 1;
    }

    /** @return array<string,array<string,mixed>> */
    public function load_user_profiles(int $user_id): array
    {
        $profiles = array(
            self::CONTEXT_DESKTOP => array(),
            self::CONTEXT_MOBILE => array(),
        );
        if ($user_id <= 0) {
            return $profiles;
        }

        try {
            $rows = dbx()->get_system_obj('dbxDB')->select(
                self::USER_DD,
                array('user_id' => $user_id),
                array(
                    'context' => 'Kontext',
                    'settings' => 'Einstellungen',
                    'revision' => 'Revision',
                    'update_date' => 'Geaendert',
                ),
                'context',
                'ASC',
                '',
                2,
                0,
                0
            );
        } catch (Throwable $exception) {
            return $profiles;
        }

        foreach (is_array($rows) ? $rows : array() as $row) {
            $stored_context = strtolower(trim((string)($row['context'] ?? '')));
            if (!in_array($stored_context, array(self::CONTEXT_DESKTOP, self::CONTEXT_MOBILE), true)) {
                continue;
            }
            $context = $this->normalize_context($stored_context);
            $decoded = json_decode((string)($row['settings'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            $profiles[$context] = $this->normalize_settings($decoded, $context);
            $profiles['_meta'][$context] = array(
                'revision' => max(0, (int)($row['revision'] ?? 0)),
                'update_date' => (string)($row['update_date'] ?? ''),
            );
        }
        return $profiles;
    }

    /**
     * @param string $context Zielkontext desktop oder mobile
     * @param array $settings Zu speichernde Benutzereinstellungen
     * @param int $user_id Kennung des angemeldeten Benutzers
     */
    public function save_user_profile(string $context, array $settings, int $user_id): int
    {
        if ($user_id <= 0) {
            throw new RuntimeException('Für das UI-Profil ist eine Anmeldung erforderlich.');
        }
        $context = $this->normalize_context($context);
        $settings = $this->normalize_settings($settings, $context);
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('Das UI-Profil ist zu umfangreich.');
        }
        if (!$this->ensure_user_schema()) {
            throw new RuntimeException('Die DD dbxUiProfile konnte nicht synchronisiert werden.');
        }

        $db = dbx()->get_system_obj('dbxDB');
        $existing = $db->select1(
            self::USER_DD,
            array('user_id' => $user_id, 'context' => $context),
            array('id', 'revision'),
            0
        );
        $now = date('Y-m-d H:i:s');
        $record = array(
            'user_id' => $user_id,
            'context' => $context,
            'settings' => $json,
            'revision' => max(1, (int)($existing['revision'] ?? 0) + 1),
            'update_date' => $now,
            'update_uid' => $user_id,
            'owner' => $user_id,
        );
        if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
            if ((int)$db->update(self::USER_DD, $record, array('id' => (int)$existing['id'])) <= 0) {
                throw new RuntimeException('Das UI-Profil konnte nicht aktualisiert werden.');
            }
            return count($settings);
        }

        $record['create_date'] = $now;
        $record['create_uid'] = $user_id;
        if ((int)$db->insert(self::USER_DD, $record) !== 1) {
            throw new RuntimeException('Das UI-Profil konnte nicht gespeichert werden.');
        }
        return count($settings);
    }

    public function clear_user_profile(string $context, int $user_id): bool
    {
        if ($user_id <= 0 || !$this->ensure_user_schema()) {
            return false;
        }
        $db = dbx()->get_system_obj('dbxDB');
        $existing = $db->select1(
            self::USER_DD,
            array('user_id' => $user_id, 'context' => $this->normalize_context($context)),
            array('id'),
            0
        );
        if (!is_array($existing) || (int)($existing['id'] ?? 0) <= 0) {
            return true;
        }
        return (bool)$db->delete(self::USER_DD, array('id' => (int)$existing['id']));
    }

    /**
     * @param string $context Zielkontext desktop oder mobile
     * @param array $settings Zu speichernde UI-Standards
     * @param int $user_id Kennung des verantwortlichen Benutzers
     */
    public function save_defaults(string $context, array $settings, int $user_id): int
    {
        $context = $this->normalize_context($context);
        $settings = $this->normalize_settings($settings, $context);
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > self::MAX_JSON_BYTES) {
            throw new RuntimeException('Die UI-Standards sind zu umfangreich.');
        }
        if (!$this->ensure_schema()) {
            throw new RuntimeException('Die DD dbxUiDefault konnte nicht synchronisiert werden.');
        }

        $db = dbx()->get_system_obj('dbxDB');
        $existing = $db->select1(self::DD, array('context' => $context), array('id', 'revision'), 0);
        $now = date('Y-m-d H:i:s');
        $record = array(
            'context' => $context,
            'settings' => $json,
            'revision' => max(1, (int)($existing['revision'] ?? 0) + 1),
            'update_date' => $now,
            'update_uid' => max(0, $user_id),
            'owner' => max(0, $user_id),
        );

        if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
            $ok = $db->update(self::DD, $record, array('id' => (int)$existing['id']));
            if ((int)$ok <= 0) {
                throw new RuntimeException('Die UI-Standards konnten nicht aktualisiert werden.');
            }
            return count($settings);
        }

        $record['create_date'] = $now;
        $record['create_uid'] = max(0, $user_id);
        if ((int)$db->insert(self::DD, $record) !== 1) {
            throw new RuntimeException('Die UI-Standards konnten nicht gespeichert werden.');
        }

        return count($settings);
    }

    public function clear_defaults(string $context): bool
    {
        $context = $this->normalize_context($context);
        if (!$this->ensure_schema()) {
            return false;
        }

        $existing = dbx()->get_system_obj('dbxDB')->select1(
            self::DD,
            array('context' => $context),
            array('id'),
            0
        );
        if (!is_array($existing) || (int)($existing['id'] ?? 0) <= 0) {
            return true;
        }

        return (bool)dbx()->get_system_obj('dbxDB')->delete(
            self::DD,
            array('id' => (int)$existing['id'])
        );
    }

    /**
     * @param array $settings Zu normalisierende Einstellungen
     * @param string $context Zielkontext desktop oder mobile
     * @return array<string,mixed>
     */
    public function normalize_settings(array $settings, string $context): array
    {
        $context = $this->normalize_context($context);
        $normalized = array();

        foreach ($settings as $storage_key => $value) {
            $storage_key = trim((string)$storage_key);
            if (!$this->is_eligible_key($storage_key, $context)) {
                continue;
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded) || strlen($encoded) > 8192) {
                continue;
            }
            $normalized[$storage_key] = $value;
            if (count($normalized) >= self::MAX_SETTINGS) {
                break;
            }
        }

        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
        return $normalized;
    }

    public function normalize_context(string $context): string
    {
        return strtolower(trim($context)) === self::CONTEXT_MOBILE
            ? self::CONTEXT_MOBILE
            : self::CONTEXT_DESKTOP;
    }

    /** @param array $settings Sicher in HTML einzubettende Einstellungen. */
    public function settings_json_html(array $settings): string
    {
        $json = json_encode((object)$settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return htmlspecialchars(is_string($json) ? $json : '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function is_eligible_key(string $storage_key, string $context): bool
    {
        $context = $this->normalize_context($context);
        if (preg_match('/^dbx\.UI\.grid\.[A-Za-z0-9_.:-]{1,180}\.(PAGE\.SIZE|GRIDLINES|AUTOSAVE|COLUMNS\.ORDER|COLUMNS\.(SIZE|VISIBLE)\.[A-Za-z0-9_.:-]{1,120})$/', $storage_key)) {
            return true;
        }
        if ($context === self::CONTEXT_DESKTOP
            && preg_match('/^dbx\.UI\.grid\.[A-Za-z0-9_.:-]{1,180}\.HEIGHT$/', $storage_key)
        ) {
            return true;
        }
        if (preg_match('/^dbx\.UI\.utilities\.global\.(mode|theme|skin(?::[a-z0-9_-]{1,80})?)$/', $storage_key)) {
            return true;
        }
        if (preg_match('/^dbx\.UI\.collapse\.[A-Za-z0-9_.:-]{1,180}\.state$/', $storage_key)) {
            return true;
        }
        if (preg_match('/^dbx\.UI\.menu\.[A-Za-z0-9_.:-]{1,180}\.branches$/', $storage_key)) {
            return true;
        }

        return $storage_key === 'dbx.UI.adminDashboard.admin-dashboard.section';
    }
}
