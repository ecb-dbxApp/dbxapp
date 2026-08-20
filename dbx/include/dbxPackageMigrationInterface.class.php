<?php

declare(strict_types=1);

/** Optionale, rueckrollbare Datenmigration eines geprueften Komponentenpakets. */
interface dbxPackageMigrationInterface
{
    /** @param array<string,mixed> $context */
    public function up(array $context): void;

    /** @param array<string,mixed> $context */
    public function down(array $context): void;
}
