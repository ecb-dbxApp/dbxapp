<?php
declare(strict_types=1);

/** Kleine, fehlertolerante JSON-Dateiabstraktion für Kernelservices. */
final class dbxJsonFile
{
    /** @return array<string|int,mixed> */
    public static function read_array(string $file): array
    {
        if (!is_file($file)) {
            return array();
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        return is_array($decoded) ? $decoded : array();
    }
}

