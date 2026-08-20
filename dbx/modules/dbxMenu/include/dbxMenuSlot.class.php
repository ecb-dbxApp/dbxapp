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
 * liefern die zum vorhandenen Menue passende Struktur, normalerweise ein
 * HTML-Listenelement.
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
      $master_module = trim((string)dbx()->get_system_var('dbx_master_modul', '', '*'));
      $active_module = trim((string)dbx()->get_system_var('dbx_activ_modul', '', '*'));

      if (!$this->is_area($area)) {
         return $this->reject('Unbekannter Menuebereich: ' . $area);
      }
      if (!$this->is_safe_name($master_module) || !$this->is_safe_name($template)) {
         return $this->reject('Ungueltiges Modul oder Template.');
      }
      if (!$this->is_safe_data($data)) {
         return $this->reject('Menue-Daten duerfen nur Skalare, null und Arrays enthalten.');
      }
      if ($active_module !== '' && $active_module !== $master_module) {
         return $this->reject(
            'Nur das aktive Hauptmodul darf Menuebeitraege registrieren.'
         );
      }
      if (!$this->template_exists($master_module, $template)) {
         return $this->reject(
            'Menue-Template fehlt: ' . $master_module . '|' . $template
         );
      }

      $registry = $this->registry();
      $registry[$area] = array(
         'module' => $master_module,
         'template' => $template,
         'data' => $data,
      );
      dbx()->set_system_var(self::SYSTEM_VAR, $registry);

      return true;
   }

   /** Rendert den registrierten Beitrag oder liefert fuer den Leerfall ''. */
   public function render(string $area): string {
      $area = strtolower(trim($area));
      if (!$this->is_area($area)) {
         return '';
      }
      if ($area === 'admin' && !dbx()->has_group('admin')) {
         return '';
      }

      $entry = $this->registry()[$area] ?? null;
      if (!is_array($entry)) {
         return '';
      }

      $module = trim((string)($entry['module'] ?? ''));
      $template = strtolower(trim((string)($entry['template'] ?? '')));
      $data = $entry['data'] ?? array();
      if (!$this->is_safe_name($module)
          || !$this->is_safe_name($template)
          || !is_array($data)
          || !$this->is_safe_data($data)
          || !$this->template_exists($module, $template)) {
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
      if (!$this->is_area($area)) {
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
      $registry = $context->has_system(self::SYSTEM_VAR)
         ? $context->system(self::SYSTEM_VAR)
         : array();
      return is_array($registry) ? $registry : array();
   }

   private function is_area(string $area): bool {
      return in_array($area, self::AREAS, true);
   }

   private function is_safe_name(string $name): bool {
      return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/', $name);
   }

   private function is_safe_data(array $data, int $depth = 0): bool {
      if ($depth > 20) {
         return false;
      }
      foreach ($data as $value) {
         if (is_array($value)) {
            if (!$this->is_safe_data($value, $depth + 1)) {
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

   private function template_exists(string $module, string $template): bool {
      if (!$this->is_safe_name($module) || !$this->is_safe_name($template)) {
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
