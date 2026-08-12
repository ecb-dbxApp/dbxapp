<?php

declare(strict_types=1);

/**
 * Flüchtiger Zustand genau eines HTTP- oder CLI-Requests.
 *
 * Routing-, Darstellungs- und Modulwerte gehören nicht in die PHP-Session:
 * dort würden parallele AJAX-Aufrufe unnötig dieselbe Sessiondatei sperren und
 * temporäre Werte könnten versehentlich den nächsten Request beeinflussen.
 * Dauerhafte Benutzereinstellungen werden weiterhin über die dafür
 * vorgesehenen Remember-/Session-APIs gespeichert.
 */
final class dbxRequestContext
{
    /** Systemwerte des aktuellen Requests. */
    private array $system = array();

    /** Nach Modulinstanz und Modulname isolierte Laufzeitwerte. */
    private array $modules = array();

    public function hasSystem(string $name): bool
    {
        return array_key_exists($name, $this->system);
    }

    public function system(string $name, mixed $default = null): mixed
    {
        return $this->system[$name] ?? $default;
    }

    public function setSystem(string $name, mixed $value): void
    {
        $this->system[$name] = $value;
    }

    public function hasModule(int|string $instance, string $module, string $name): bool
    {
        return array_key_exists($name, $this->modules[$instance][$module] ?? array());
    }

    public function module(int|string $instance, string $module, string $name, mixed $default = null): mixed
    {
        return $this->modules[$instance][$module][$name] ?? $default;
    }

    public function setModule(int|string $instance, string $module, string $name, mixed $value): void
    {
        $this->modules[$instance][$module][$name] = $value;
    }

    /** @return array<string,mixed> */
    public function systemSnapshot(): array
    {
        return $this->system;
    }

    /** @param array $snapshot Zuvor mit systemSnapshot() gesicherte Systemwerte. */
    public function restoreSystem(array $snapshot): void
    {
        $this->system = $snapshot;
    }

    /**
     * Sichert den vollstaendigen verschachtelten Laufkontext.
     *
     * Eingebettete Modulaufrufe brauchen neben Systemwerten auch einen eigenen
     * ModulVar-Bereich. Andernfalls kann die Schutzliste eines ersten Markers
     * die Parameter eines direkt folgenden Markers desselben Moduls blockieren.
     *
     * @return array{system:array<string,mixed>,modules:array<int|string,array<string,array<string,mixed>>>}
     */
    public function snapshot(): array
    {
        return array('system' => $this->system, 'modules' => $this->modules);
    }

    /** @param array $snapshot Vollständiger, zuvor mit snapshot() gesicherter Laufkontext. */
    public function restore(array $snapshot): void
    {
        $this->system = is_array($snapshot['system'] ?? null) ? $snapshot['system'] : array();
        $this->modules = is_array($snapshot['modules'] ?? null) ? $snapshot['modules'] : array();
    }
}
