<?php

declare(strict_types=1);

/**
 * Zentrale Passwortvorgaben für Installation, Login und Profilbearbeitung.
 */
final class dbxPasswordPolicy
{
    public static function minimumLength(?int $configured = null): int
    {
        if ($configured === null && function_exists('dbx')) {
            $configured = (int)dbx()->get_config(
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
    public static function missingCriteria(
        string $password,
        ?int $minimumLength = null
    ): array {
        $minimumLength = self::minimumLength($minimumLength);
        $length = function_exists('mb_strlen')
            ? mb_strlen($password, 'UTF-8')
            : strlen($password);
        $missing = array();
        if ($length < $minimumLength) {
            $missing[] = 'mindestens ' . $minimumLength . ' Zeichen';
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

    public static function missingMessage(
        array $missing,
        ?int $minimumLength = null
    ): string {
        if ($missing === array()) {
            return '';
        }
        $minimumLength = self::minimumLength($minimumLength);
        return 'Noch nicht erfüllt: ' . implode(', ', $missing) . '.'
            . ($minimumLength < 12
                ? ' Empfohlen sind insgesamt 12 oder mehr Zeichen.'
                : '');
    }

    /**
     * @return array{password?:string,repeat?:string}
     */
    public static function errors(
        string $password,
        string $repeat,
        string $currentHash = '',
        ?int $minimumLength = null
    ): array {
        $minimumLength = self::minimumLength($minimumLength);
        $errors = array();
        if (!hash_equals($password, $repeat)) {
            $errors['repeat'] = 'Die beiden Passwörter stimmen nicht überein.';
        }
        $missing = self::missingCriteria($password, $minimumLength);
        if ($missing !== array()) {
            $errors['password'] = self::missingMessage(
                $missing,
                $minimumLength
            );
        } elseif ($currentHash !== ''
            && password_get_info($currentHash)['algoName'] !== 'unknown'
            && password_verify($password, $currentHash)
        ) {
            $errors['password'] = 'Das neue Passwort muss sich vom bisherigen Passwort unterscheiden.';
        }
        return $errors;
    }
}
