<?php
namespace dbx\dbxAdmin;

trait dbxSchemaMetaServiceTrait {



   /**
    * Ermittelt Moduloptionen fuer Filter und Auswahlfelder.
    *
    * @return array
    */
   private function get_module_options() {
      $options = array();
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*';

      foreach (glob($base, GLOB_ONLYDIR) as $dir) {
         $modul = basename($dir);
         if ($modul !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modul)) {
            $options[$modul] = $modul;
         }
      }

      if (!isset($options['dbx'])) {
         $options['dbx'] = 'dbx';
      }

      ksort($options);
      return $options;
   }



   /**
    * Ermittelt alle DB-Server und Modul-DB-Dateien fuer Schema-Reports.
    *
    * @return array
    */
   private function get_server_options() {
      $options = array();
      $config = dbx()->get_cfg('dbx', 'db');
      $moduleFiles = $this->get_module_db_files();
      $moduleFileIndex = array();

      foreach ($moduleFiles as $server => $db) {
         $options[$server] = $db['label'];

         $real = realpath((string)($db['file'] ?? ''));
         if ($real) {
            $moduleFileIndex[strtolower(str_replace('\\', '/', $real))] = 1;
         }
      }

      if (is_array($config)) {
         foreach ($config as $server => $data) {
            if (isset($options[$server])) {
               continue;
            }

            $type = strtolower((string)($data['type'] ?? ''));
            $host = (string)($data['host'] ?? '');
            $name = (string)($data['dbname'] ?? ($data['name'] ?? ''));
            $isSqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

            if ($isSqlite && ($host !== '' || $name !== '')) {
               $file = dbx()->os_path($host . $name);
               $real = realpath($file);

               if ($real) {
                  $real = strtolower(str_replace('\\', '/', $real));
                  if (isset($moduleFileIndex[$real])) {
                     continue;
                  }
               }
            }

            $options[$server] = $server;
         }
      }

      return $options;
   }



   /**
    * Sammelt SQLite-Modul-DB-Dateien aus allen Modulverzeichnissen.
    *
    * @return array
    */


   private function get_module_db_files() {
      $records = array();
      $base = str_replace('\\', '/', dbx()->get_base_dir()) . 'dbx/modules/*/db/*';

      foreach (glob($base) as $file) {
         if (!is_file($file) || !preg_match('/\.(db3|sqlite|sqlite3)$/i', $file)) {
            continue;
         }

         $norm = str_replace('\\', '/', $file);
         if (!preg_match('#/dbx/modules/([^/]+)/db/([^/]+)$#', $norm, $match)) {
            continue;
         }

         $modul = $match[1];
         $name  = $match[2];
         $server = ($modul == 'dbx') ? $name : $modul . '|' . $name;

         $records[$server] = array(
            'server' => $server,
            'modul'  => $modul,
            'name'   => $name,
            'file'   => $norm,
            'path'   => $this->path_rel($norm),
            'label'  => $this->path_rel($norm),
         );
      }

      ksort($records);
      return $records;
   }



   /**
    * Loest einen Modul-DB-Servernamen auf eine Datei im Modul-DB-Verzeichnis auf.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function resolve_module_db_file($server) {
      $server = (string)$server;
      if (!preg_match('/\.(db3|sqlite|sqlite3)$/i', $server)) {
         return '';
      }

      $modul = 'dbx';
      $name  = $server;

      if (strpos($server, '|') !== false) {
         $parts = explode('|', $server, 2);
         $modul = trim($parts[0]) ?: 'dbx';
         $name  = trim($parts[1]);
      }

      $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/' . $modul . '/db/' . $name);
      if (file_exists($file)) {
         return str_replace('\\', '/', $file);
      }

      if ($modul != 'dbx') {
         $file = dbx()->os_path(dbx()->get_base_dir() . 'dbx/modules/dbx/db/' . $name);
         if (file_exists($file)) {
            return str_replace('\\', '/', $file);
         }
      }

      return '';
   }



   /**
    * Erzeugt das sichtbare Datenbanklabel fuer Server- und Modul-DB-Eintraege.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function get_database_label($server) {
      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         return $this->path_rel($moduleFile);
      }

      $config = dbx()->get_cfg('dbx', 'db');

      if (is_array($config) && isset($config[$server])) {
         $db = $config[$server];
         $name = $db['dbname'] ?? ($db['name'] ?? '');
         $host = $db['host'] ?? '';
         $type = strtolower((string)($db['type'] ?? ''));

         if ($name && $host) {
            $full = dbx()->os_path($host . $name);
            if ($type == 'sqlite' || preg_match('/[\/\\\\]/', (string)$host)) {
               return $this->path_rel($full);
            }

            return rtrim((string)$host, '/') . '/' . $name;
         }

         if ($name) {
            return $name;
         }

         if ($host) {
            return $host;
         }
      }

      return '';
   }



   /**
    * Erzeugt den optionalen Pfadhinweis fuer dateibasierte Datenbanken.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function get_database_path_label($server) {
      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         return $this->path_rel($moduleFile);
      }

      $config = dbx()->get_cfg('dbx', 'db');
      if (!is_array($config) || !isset($config[$server])) {
         return '';
      }

      $db = $config[$server];
      $type = strtolower((string)($db['type'] ?? ''));
      $host = (string)($db['host'] ?? '');
      $name = (string)($db['dbname'] ?? ($db['name'] ?? ''));

      if ($type == 'sqlite' && ($host !== '' || $name !== '')) {
         return $this->path_rel(dbx()->os_path($host . $name));
      }

      return '';
   }



   /**
    * Ordnet einen Server dem echten Modul oder dem Pseudo-Modul sys zu.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function get_server_module($server) {
      $server = (string)$server;
      static $moduleFiles = null;
      if ($moduleFiles === null) {
         $moduleFiles = $this->get_module_db_files();
      }

      if (isset($moduleFiles[$server])) {
         return $moduleFiles[$server]['modul'] ?? '';
      }

      $config = dbx()->get_cfg('dbx', 'db');
      if (is_array($config) && isset($config[$server])) {
         $db = $config[$server];
         $type = strtolower((string)($db['type'] ?? ''));
         $host = (string)($db['host'] ?? '');
         $name = (string)($db['dbname'] ?? ($db['name'] ?? ''));
         $isSqlite = ($type == 'sqlite' || $type == 'sqlite3' || preg_match('/\.(db3|sqlite|sqlite3)$/i', $name));

         if ($isSqlite && ($host !== '' || $name !== '')) {
            $configFile = realpath(dbx()->os_path($host . $name));

            if ($configFile) {
               $configFile = strtolower(str_replace('\\', '/', $configFile));

               foreach ($moduleFiles as $moduleDb) {
                  $moduleFile = realpath((string)($moduleDb['file'] ?? ''));

                  if ($moduleFile && strtolower(str_replace('\\', '/', $moduleFile)) === $configFile) {
                     return $moduleDb['modul'] ?? '';
                  }
               }
            }
         }

         return 'sys';
      }

      return '';
   }



   /**
    * Erzeugt eine kompakte Feldtyp-Anzeige aus DD- oder DB-Feldmetadaten.
    *
    * @param string $field Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function field_type_label($field) {
      $type = trim((string)($field['type'] ?? ''));
      $len  = trim((string)($field['length'] ?? ''));
      $idx  = trim((string)($field['index'] ?? ''));

      $label = $type;
      if ($len !== '') {
         $label .= '(' . $len . ')';
      }
      if ($idx !== '') {
         $label .= ' / ' . $idx;
      }

      return $label;
   }



   /**
    * Erzeugt eine Feldanzahl mit Standard-title und vorbereitetem HTML-Tooltip.
    *
    * @param array $fields Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function field_tooltip_count($fields, $title = '') {
      $texts = $this->schema_texts();
      if ($title === '') {
         $title = $texts->get_fd_message('fields');
      }
      $fields = array_values((array)$fields);
      $count = count($fields);

      $html = '<div class="dbx-tooltip-fields">';
      $html .= '<div class="dbx-tooltip-title">' . $this->esc($title) . '</div>';

      if ($count <= 0) {
         $html .= '<div class="dbx-tooltip-empty">' . $this->esc($texts->get_fd_message('no_fields')) . '</div>';
      } else {
         $html .= '<table class="dbx-tooltip-table">';
         foreach ($fields as $field) {
            $name = trim((string)($field['name'] ?? ''));
            if ($name === '') {
               continue;
            }

            $type = $this->field_type_label($field);
            $html .= '<tr>';
            $html .= '<td><code>' . $this->esc($name) . '</code></td>';
            $html .= '<td>' . $this->esc($type) . '</td>';
            $html .= '</tr>';
         }
         $html .= '</table>';
      }

      $html .= '</div>';

      return '<span class="dbx-schema-field-count badge bg-light text-dark border" '
         . 'data-dbx-tooltip="' . $this->esc(str_replace('"', "'", $html)) . '">'
         . $this->esc($count)
         . '</span>';
   }



   /**
    * Erzeugt das Status-Badge fuer Schema-Mapping-Zeilen.
    *
    * @param string $status Eingabeparameter fuer diese Methode.
    * @return string
    */

   private function mapping_status_label($status) {
      $texts = $this->schema_texts();
      $map = array(
         'exact' => array(
            $texts->get_fd_message('mapping_status_direct'),
            'success',
         ),
         'mapped' => array(
            $texts->get_fd_message('mapping_status_mapped'),
            'primary',
         ),
         'type_conflict' => array(
            $texts->get_fd_message('mapping_status_type_check'),
            'warning text-dark',
         ),
         'new' => array(
            $texts->get_fd_message('mapping_status_new'),
            'secondary',
         ),
      );

      $item = $map[$status] ?? array($status, 'secondary');
      return '<span class="badge bg-' . $this->esc($item[1]) . '">' . $this->esc($item[0]) . '</span>';
   }



   /**
    * Erzeugt die menschenlesbare Bezeichnung einer Mapping-Art.
    *
    * @param string $kind Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function mapping_kind_label($kind) {
      $map = array(
         'dd_to_db' => 'DD -> DB',
         'db_to_dd' => 'DB -> DD',
         'transfer' => 'Transfer',
      );

      return $map[$kind] ?? 'Schema-Mapping';
   }



   /**
    * Erzeugt Aliasnamen fuer DB-Server, damit DDs und DB-Dateien korrekt zusammenfinden.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function db_server_aliases($server) {
      $aliases = array();
      $server = trim((string)$server);

      if ($server === '') {
         return array();
      }

      $add = function($value) use (&$aliases) {
         $value = trim(str_replace('\\', '/', (string)$value));
         if ($value !== '') {
            $aliases[$value] = true;
         }
      };

      $add($server);

      $norm = str_replace('\\', '/', $server);
      if ($norm !== $server) {
         $add($norm);
      }

      if (strpos($norm, '/') !== false) {
         $add(basename($norm));
      }

      if (strpos($server, '|') !== false) {
         $parts = explode('|', $server, 2);
         $modul = trim((string)($parts[0] ?? ''));
         $name  = trim((string)($parts[1] ?? ''));

         if ($name !== '') {
            $add($name);
         }

         if ($modul !== '' && $name !== '') {
            $add($modul . '|' . $name);
            $add($modul . '/db/' . $name);
         }
      }

      $moduleFile = $this->resolve_module_db_file($server);
      if ($moduleFile) {
         $file = str_replace('\\', '/', $moduleFile);
         $add($file);
         $add($this->path_rel($file));
         $add(basename($file));

         if (preg_match('#/dbx/modules/([^/]+)/db/([^/]+)$#', $file, $match)) {
            $add($match[2]);
            $add($match[1] . '|' . $match[2]);
            $add($match[1] . '/db/' . $match[2]);
         }
      }

      return array_keys($aliases);
   }
}
