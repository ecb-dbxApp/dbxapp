<?php

declare(strict_types=1);

/**
 * Zentrale Passwortvorgaben für Installation, Login und Profilbearbeitung.
 */
final class dbxPasswordPolicy
{
    /** Erzeugt ein kryptografisch zufälliges Passwort aus dem erlaubten Alphabet. */
    public static function generate(int $length, string $special = '-_!'): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' . $special;
        if ($length < 1 || $alphabet === '') {
            return '';
        }

        $password = '';
        $last_index = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $last_index)];
        }
        return $password;
    }

    public static function minimum_length(?int $configured = null): int
    {
        if ($configured === null && function_exists('dbx')) {
            $configured = (int)dbx()->get_cfg(
                'dbx',
                'password_min_length',
                6
            );
        }
        $configured = $configured ?? 6;
        return max(6, min(128, $configured > 0 ? $configured : 6));
    }

    /**
     * @return string[]
     */
    public static function missing_criteria(
        string $password,
        ?int $minimum_length = null
    ): array {
        $minimum_length = self::minimum_length($minimum_length);
        $length = function_exists('mb_strlen')
            ? mb_strlen($password, 'UTF-8')
            : strlen($password);
        $missing = array();
        if ($length < $minimum_length) {
            $missing[] = 'mindestens ' . $minimum_length . ' Zeichen';
        }
        if (preg_match('/[A-Z]/', $password) !== 1) {
            $missing[] = 'ein Großbuchstabe';
        }
        if (preg_match('/[a-z]/', $password) !== 1) {
            $missing[] = 'ein Kleinbuchstabe';
        }
        if (preg_match('/[0-9]/', $password) !== 1) {
            $missing[] = 'eine Zahl';
        }
        if (preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
            $missing[] = 'ein Sonderzeichen';
        }
        return $missing;
    }

    public static function missing_message(
        array $missing,
        ?int $minimum_length = null
    ): string {
        if ($missing === array()) {
            return '';
        }
        $minimum_length = self::minimum_length($minimum_length);
        return 'Noch nicht erfüllt: ' . implode(', ', $missing) . '.'
            . ($minimum_length < 12
                ? ' Empfohlen sind insgesamt 12 oder mehr Zeichen.'
                : '');
    }

    /**
     * @return array{password?:string,repeat?:string}
     */
    public static function errors(
        string $password,
        string $repeat,
        string $current_hash = '',
        ?int $minimum_length = null
    ): array {
        $minimum_length = self::minimum_length($minimum_length);
        $errors = array();
        if (!hash_equals($password, $repeat)) {
            $errors['repeat'] = 'Die beiden Passwörter stimmen nicht überein.';
        }
        $missing = self::missing_criteria($password, $minimum_length);
        if ($missing !== array()) {
            $errors['password'] = self::missing_message(
                $missing,
                $minimum_length
            );
        } elseif ($current_hash !== ''
            && password_get_info($current_hash)['algoName'] !== 'unknown'
            && password_verify($password, $current_hash)
        ) {
            $errors['password'] = 'Das neue Passwort muss sich vom bisherigen Passwort unterscheiden.';
        }
        return $errors;
    }
}
