<?php

declare(strict_types=1);

/** Optionale, rueckrollbare Datenmigration eines geprueften Komponentenpakets. */
interface dbxPackageMigrationInterface
{
    /** @param array $context Gepruefter Installationskontext der Vorwaertsmigration. */
    public function up(array $context): void;

    /** @param array $context Gepruefter Installationskontext der Rueckwaertsmigration. */
    public function down(array $context): void;
}
