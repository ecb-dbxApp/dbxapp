<?php

declare(strict_types=1);

namespace dbx\dbxChangeLog_admin;

final class dbxChangeLogWriter
{
    private const DD = 'dbxChangeLog_admin|dbxChangeLog';

    public function write(string $summary, array $resources, string $details = '', string $actor = 'Codex'): array
    {
        $summary = trim($summary);
        $actor = trim($actor);
        $resources = array_values(array_unique(array_filter(array_map(
            static fn($resource): string => trim(str_replace('\\', '/', (string)$resource)),
            $resources
        ))));
        if ($summary === '' || mb_strlen($summary, 'UTF-8') > 255) {
            throw new \InvalidArgumentException('Der Change-Log-Text muss 1 bis 255 Zeichen lang sein.');
        }
        if (!$resources) {
            throw new \InvalidArgumentException('Mindestens eine betroffene Ressource ist erforderlich.');
        }
        if (mb_strlen(trim($details), 'UTF-8') < 3 || mb_strlen(trim($details), 'UTF-8') > 4000) {
            throw new \InvalidArgumentException('Eine verständliche Begründung mit 3 bis 4000 Zeichen ist erforderlich.');
        }
        if ($actor === '' || mb_strlen($actor, 'UTF-8') > 80) {
            throw new \InvalidArgumentException('Der Akteur muss 1 bis 80 Zeichen lang sein.');
        }

        $dd = dbx()->get_system_obj('dbxDD');
        if ($dd->create_db_tab(self::DD) !== 1) {
            throw new \RuntimeException('Die Change-Log-Datenbank konnte nicht synchronisiert werden.');
        }
        $date = date('Y-m-d H:i:s');
        $db = dbx()->get_system_obj('dbxDB');
        $saved = $db->insert(self::DD, array(
            'change_date' => $date,
            'actor' => $actor,
            'summary' => $summary,
            'details' => trim($details),
            'resources' => implode("\n", $resources),
        ));
        $rid = $saved > 0 ? (int)$db->get_insert_id() : 0;
        if ($rid <= 0) {
            throw new \RuntimeException('Der Change-Log-Eintrag konnte nicht gespeichert werden.');
        }

        return array(
            'id' => (int)$rid,
            'date' => $date,
            'actor' => $actor,
            'summary' => $summary,
            'resources' => $resources,
        );
    }
}
