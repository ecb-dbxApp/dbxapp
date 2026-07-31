<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

/**
 * Kleine Adaptergrenze zwischen Datei-Updater und DD-Migrationsrunner.
 */
class dbxUpdateDatabaseAdapter
{
    private dbxDatabaseMigrationService $migrations;

    public function __construct(?dbxDatabaseMigrationService $migrations = null)
    {
        if ($migrations === null) {
            dbx()->get_include_obj('dbxDatabaseMigrationService', 'dbxAdmin');
            $migrations = new dbxDatabaseMigrationService();
        }
        $this->migrations = $migrations;
    }

    public function prepare(
        string $releaseRoot,
        array $manifest,
        string $backupDirectory
    ): array {
        return $this->migrations->prepare(
            $releaseRoot,
            (string)($manifest['version'] ?? ''),
            $backupDirectory
        );
    }

    public function apply(array $state): array
    {
        return $this->migrations->apply($state);
    }

    public function rollback(array $state): array
    {
        return $this->migrations->rollback($state);
    }
}
