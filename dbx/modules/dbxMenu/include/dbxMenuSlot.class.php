<?php
namespace dbx\dbxMenu;

/**
 * Request-basierte Erweiterung der bestehenden Benutzer- und Admin-Menues.
 *
 * Das aktive Hauptmodul registriert nur Template und Daten. dbxMenu rendert
 * das Modul-Template spaeter an einem expliziten Slot im Kunden-Menue:
 *
 * @code{.php}
 * $menu = dbx()->get_include_obj('dbxMenuSlot', 'dbxMenu');
 * $menu->register('user', 'menu-user', array('count' => $count));
 * $menu->register('admin', 'menu-admin');
 * @endcode
 *
 * Die Templates liegen im registrierenden Hauptmodul unter tpl/htm und
 * liefern die zum vorhandenen Menue passende Struktur, normalerweise <li>.
 */
class dbxMenuSlot {
   private const SYSTEM_VAR = 'dbx_module_menu_slots';
   private const AREAS = array('user', 'admin');

   /**
    * Registriert genau einen Template-Beitrag fuer einen Menuebereich.
    * Ein weiterer Aufruf desselben Hauptmoduls ersetzt dessen bisherigen
    * Beitrag in diesem Bereich. Verschachtelte Module koennen das Menue nicht
    * nachtraeglich oder in fremdem Namen veraendern.
    */
   public function register(string $area, string $template, array $data = array()): bool {
      $area = strtolower(trim($area));
      $template = strtolower(trim($template));
      $masterModule = trim((string)dbx()->get_system_var('dbx_master_modul', '', '*'));
      $activeModule = trim((string)dbx()->get_system_var('dbx_activ_modul', '', '*'));

      if (!$this->isArea($area)) {
         return $this->reject('Unbekannter Menuebereich: ' . $area);
      }
      if (!$this->isSafeName($masterModule) || !$this->isSafeName($template)) {
         return $this->reject('Ungueltiges Modul oder Template.');
      }
      if (!$this->isSafeData($data)) {
         return $this->reject('Menue-Daten duerfen nur Skalare, null und Arrays enthalten.');
      }
      if ($activeModule !== '' && $activeModule !== $masterModule) {
         return $this->reject(
            'Nur das aktive Hauptmodul darf Menuebeitraege registrieren.'
         );
      }
      if (!$this->templateExists($masterModule, $template)) {
         return $this->reject(
            'Menue-Template fehlt: ' . $masterModule . '|' . $template
         );
      }

      $registry = $this->registry();
      $registry[$area] = array(
         'module' => $masterModule,
         'template' => $template,
         'data' => $data,
      );
      dbx()->set_system_var(self::SYSTEM_VAR, $registry);

      return true;
   }

   /** Rendert den registrierten Beitrag oder liefert fuer den Leerfall ''. */
   public function render(string $area): string {
      $area = strtolower(trim($area));
      if (!$this->isArea($area)) {
         return '';
      }
      if ($area === 'admin' && !dbx()->can('admin')) {
         return '';
      }

      $entry = $this->registry()[$area] ?? null;
      if (!is_array($entry)) {
         return '';
      }

      $module = trim((string)($entry['module'] ?? ''));
      $template = strtolower(trim((string)($entry['template'] ?? '')));
      $data = $entry['data'] ?? array();
      if (!$this->isSafeName($module)
          || !$this->isSafeName($template)
          || !is_array($data)
          || !$this->isSafeData($data)
          || !$this->templateExists($module, $template)) {
         $this->reject('Ungueltige Menue-Registrierung wurde verworfen.');
         return '';
      }

      try {
         $tpl = dbx()->get_system_obj('dbxTPL');
         return trim((string)$tpl->get_tpl(
            $module . '|' . $template,
            $data,
            'htm',
            dbx()->next_id()
         ));
      } catch (\Throwable $e) {
         $this->reject('Menue-Template konnte nicht gerendert werden: ' . $e->getMessage());
         return '';
      }
   }

   /** Entfernt einen einzelnen Bereich oder alle Registrierungen des Requests. */
   public function clear(string $area = ''): void {
      $area = strtolower(trim($area));
      if ($area === '') {
         dbx()->set_system_var(self::SYSTEM_VAR, array());
         return;
      }
      if (!$this->isArea($area)) {
         return;
      }

      $registry = $this->registry();
      unset($registry[$area]);
      dbx()->set_system_var(self::SYSTEM_VAR, $registry);
   }

   private function registry(): array {
      // Ausschliesslich den internen Request-Speicher lesen. get_system_var()
      // wuerde ohne Sessionwert auch gleichnamige GET-/POST-Daten akzeptieren.
      $context = dbx()->request_context();
      $registry = $context->hasSystem(self::SYSTEM_VAR)
         ? $context->system(self::SYSTEM_VAR)
         : array();
      return is_array($registry) ? $registry : array();
   }

   private function isArea(string $area): bool {
      return in_array($area, self::AREAS, true);
   }

   private function isSafeName(string $name): bool {
      return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $name);
   }

   private function isSafeData(array $data, int $depth = 0): bool {
      if ($depth > 20) {
         return false;
      }
      foreach ($data as $value) {
         if (is_array($value)) {
            if (!$this->isSafeData($value, $depth + 1)) {
               return false;
            }
            continue;
         }
         if (!is_scalar($value) && $value !== null) {
            return false;
         }
      }

      return true;
   }

   private function templateExists(string $module, string $template): bool {
      if (!$this->isSafeName($module) || !$this->isSafeName($template)) {
         return false;
      }

      $path = rtrim((string)dbx()->get_base_dir(), '/\\')
         . '/dbx/modules/' . $module . '/tpl/htm/' . strtolower($template) . '.htm';
      return is_file(dbx()->os_path($path));
   }

   private function reject(string $message): bool {
      dbx()->debug('[dbxMenuSlot] ' . $message);
      return false;
   }
}
