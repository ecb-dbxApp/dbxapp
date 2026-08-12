<?php

declare(strict_types=1);

/** Reine, seiteneffektfreie Auswahl eines Report-Datenfensters. */
class dbxReportDataWindow
{
    /** @return array<int,mixed> */
    public function slice(array $rows, int $offset, int $length): array
    {
        return array_values(array_slice($rows, max(0, $offset), max(0, $length), false));
    }
}
