<?php

declare(strict_types=1);

namespace dbx\dbxContact;

final class dbxContactPresentation
{
    /** Ergänzt die konfigurierte Mail-Verbindung für alle Kontakt-Mails. */
    public static function mail_options(array $options = array()): array
    {
        $profile = trim((string)dbx()->get_cfg('dbxContact', 'mail_profile'));
        if ($profile !== '') {
            $options['mail_profile'] = $profile;
        }

        return $options;
    }

    public static function status_class(string $status): string
    {
        return array(
            'open' => 'text-bg-primary',
            'in_progress' => 'text-bg-info',
            'waiting_customer' => 'text-bg-warning',
            'answered' => 'text-bg-success',
            'closed' => 'text-bg-secondary',
        )[$status] ?? 'text-bg-light';
    }
}
