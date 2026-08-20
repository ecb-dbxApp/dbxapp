<?php

declare(strict_types=1);

namespace dbx\dbxKi;

final class dbxKiChangeLogService
{
    public function normalize(array $change_log): array
    {
        $summary = trim((string)($change_log['summary'] ?? ''));
        $details = trim((string)($change_log['details'] ?? ''));
        $resources = $change_log['resources'] ?? array();
        if (!is_array($resources) || !array_is_list($resources)) {
            throw new \InvalidArgumentException('change_log.resources muss eine JSON-Liste sein.');
        }
        $resources = array_values(array_unique(array_filter(array_map(
            static fn($resource): string => trim(str_replace('\\', '/', (string)$resource)),
            $resources
        ))));
        if ($summary === '' || mb_strlen($summary, 'UTF-8') > 255) {
            throw new \InvalidArgumentException('change_log.summary muss 1 bis 255 Zeichen lang sein.');
        }
        if (!$resources) {
            throw new \InvalidArgumentException('change_log.resources benötigt mindestens eine betroffene Ressource.');
        }
        if (mb_strlen($details, 'UTF-8') < 3 || mb_strlen($details, 'UTF-8') > 4000) {
            throw new \InvalidArgumentException('change_log.details muss die Begründung mit 3 bis 4000 Zeichen enthalten.');
        }

        return array('summary' => $summary, 'details' => $details, 'resources' => $resources);
    }

    /** Verbindliche zentrale Schreibfunktion für erfolgreich ausgeführte dbxKi-Änderungen. */
    public function write_change_log(array $change_log): array
    {
        $entry = $this->normalize($change_log);
        $writer = dbx()->get_include_obj('dbxChangeLogWriter', 'dbxChangeLog_admin');
        $result = $writer->write(
            $entry['summary'],
            $entry['resources'],
            $entry['details'],
            'dbxKi'
        );

        return array('ok' => 1, 'change_log' => $result);
    }
}
