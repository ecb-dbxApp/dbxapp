<?php

declare(strict_types=1);

/**
 * Lädt und validiert deklarative Modulaktionen.
 *
 * Jede Aktion beschreibt Handler, erlaubte HTTP-Methoden, Gruppen,
 * Mutationsstatus und Antworttyp an genau einer Stelle. Module müssen damit
 * keine parallelen Switch-, Sicherheits- und Response-Listen pflegen.
 */
class dbxActionManifest
{
    /** Request-lokaler Cache der normalisierten Modulaktionen. */
    private array $cache = array();

    /** @return array<string,array<string,mixed>> */
    public function module(string $module, string $manifest = 'actions'): array
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $module)) {
            throw new InvalidArgumentException('Ungültiger Modulname im Aktionsmanifest.');
        }
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $manifest)) {
            throw new InvalidArgumentException('Ungültiger Name des Aktionsmanifests.');
        }
        $cache_key = $module . '|' . $manifest;
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $file = dbx()->get_base_dir() . 'dbx/modules/' . $module . '/cfg/' . $manifest . '.php';
        if (!is_file($file)) {
            return $this->cache[$cache_key] = array();
        }
        $actions = (static function (string $manifest_file): mixed {
            return require $manifest_file;
        })($file);
        if (!is_array($actions)) {
            throw new UnexpectedValueException($module . ': Aktionsmanifest muss ein Array liefern.');
        }

        $normalized = array();
        foreach ($actions as $name => $definition) {
            $name = trim((string)$name);
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || !is_array($definition)) {
                throw new UnexpectedValueException($module . ': ungültige Aktionsdefinition.');
            }
            $handler = trim((string)($definition['handler'] ?? ''));
            $methods = array_values(array_unique(array_map(
                static fn(mixed $method): string => strtoupper(trim((string)$method)),
                (array)($definition['methods'] ?? array('GET'))
            )));
            $groups = array_values(array_filter(array_map('strval', (array)($definition['groups'] ?? array()))));
            $response = strtolower(trim((string)($definition['response'] ?? 'html')));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $handler)
                || array_diff($methods, array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'))
                || !in_array($response, array('html', 'json', 'file'), true)) {
                throw new UnexpectedValueException($module . '.' . $name . ': Aktionsvertrag ist unvollständig.');
            }
            $normalized[$name] = array_replace($definition, array(
                'handler' => $handler,
                'methods' => $methods,
                'groups' => $groups,
                'mutation' => (bool)($definition['mutation'] ?? false),
                'response' => $response,
            ));
        }
        return $this->cache[$cache_key] = $normalized;
    }

    /** @return array<string,mixed>|null */
    public function action(string $module, string $action, string $manifest = 'actions'): ?array
    {
        return $this->module($module, $manifest)[$action] ?? null;
    }
}
