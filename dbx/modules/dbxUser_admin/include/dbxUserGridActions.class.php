<?php
declare(strict_types=1);

namespace dbx\dbxUser_admin;

/** Gemeinsame zustandslose Aktionen der Benutzer- und Gruppengrids. */
final class dbxUserGridActions
{
    /** Erzeugt die einheitliche JSON-Antwort einer Grid-Löschaktion. */
    public static function delete_result(object $database, string $data_definition, array $payload, string $missing_id_message): array
    {
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            return array('ok' => 0, 'success' => false, 'msg' => $missing_id_message);
        }

        $deleted = (bool)$database->delete($data_definition, $id);
        return array('ok' => $deleted ? 1 : 0, 'success' => $deleted);
    }
}

