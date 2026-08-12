<?php
namespace dbx\dbxAdmin;

trait dbxSchemaCoreServiceTrait {

   /**
    * Stabiler sprachabhängiger Textkontext für alle Schema-Reports.
    */
   private function schema_texts() {
      if ($this->schemaTexts) {
         return $this->schemaTexts;
      }
      dbx()->get_system_obj('dbxForm', 'use');
      $texts = new \dbxForm();
      $texts->set_form_help_enabled(false);
      $texts->_fd = 'dbxAdmin|schema-report';
      $texts->load_fd_messages();
      $this->schemaTexts = $texts;
      return $this->schemaTexts;
   }


   /**
    * Escaped einen Wert fuer die sichere HTML-Ausgabe in Templates, Tabellen und Attributen.
    *
    * @param mixed $value Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function esc($value) {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
   }



   /**
    * Erzeugt einen normalisierten Vergleichsschluessel aus DB-Server und Tabellenname.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function norm_key($server, $table) {
      return strtolower((string) $server) . '|' . strtolower((string) $table);
   }



   /**
    * Kodiert Server und Tabelle zu einer stabilen Report-RID fuer DB-Zeilen.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $table Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function encode_db_rid($server, $table) {
      $json = json_encode(array((string)$server, (string)$table), JSON_UNESCAPED_SLASHES);
      return 'db_' . rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
   }



   /**
    * Dekodiert eine DB-Report-RID zurueck in Server und Tabelle.
    *
    * @param string $rid Eingabeparameter fuer diese Methode.
    * @return array
    */
   private function decode_db_rid($rid) {
      $rid = (string)$rid;

      if (str_starts_with($rid, 'db_')) {
         $raw = substr($rid, 3);
         $pad = strlen($raw) % 4;
         if ($pad) {
            $raw .= str_repeat('=', 4 - $pad);
         }

         $json = base64_decode(strtr($raw, '-_', '+/'), true);
         $data = $json !== false ? json_decode($json, true) : null;
         if (is_array($data) && count($data) >= 2) {
            return array((string)$data[0], (string)$data[1]);
         }
      }

      $parts = explode('|', $rid, 2);
      return count($parts) == 2 ? $parts : array('', '');
   }



   /**
    * Erzeugt die DBX-DD-Referenz aus Modul und DD-Name.
    *
    * @param string $modul Eingabeparameter fuer diese Methode.
    * @param string $dd Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function dd_ref($modul, $dd) {
      return ($modul && $modul != 'dbx') ? $modul . '|' . $dd : $dd;
   }



   /**
    * Wandelt einen absoluten Pfad in eine kurze DBX-relative Anzeige um.
    *
    * @param string $path Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function path_rel($path) {
      $path = str_replace('\\', '/', (string)$path);
      $base = str_replace('\\', '/', dbx()->get_base_dir());

      if ($base !== '' && str_starts_with($path, $base)) {
         $path = substr($path, strlen($base));
      }

      $path = ltrim($path, '/');
      if (str_starts_with($path, 'dbx/modules/')) {
         $path = substr($path, strlen('dbx/modules/'));
      }

      return $path;
   }



   /**
    * Erzeugt eine DBX-Admin-URL fuer den angegebenen Laufmodus und Parameter.
    *
    * @param string $run1 Eingabeparameter fuer diese Methode.
    * @param string $run2 Eingabeparameter fuer diese Methode.
    * @param array $params Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function build_url($run1, $run2, $params = array()) {
      $url = '?dbx_modul=dbxAdmin&dbx_run1=' . rawurlencode($run1) . '&dbx_run2=' . rawurlencode($run2);

      foreach ($params as $key => $value) {
         $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
      }

      return $url;
   }



   /**
    * Erzeugt einen openWin-Link mit Icon, Titel und Fensteroptionen.
    *
    * @param string $url Eingabeparameter fuer diese Methode.
    * @param string $icon Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @param int $width Eingabeparameter fuer diese Methode.
    * @param int $height Eingabeparameter fuer diese Methode.
    * @param string $class Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function openwin($url, $icon, $title, $width = 1200, $height = 780, $class = 'btn-inline') {
      $titleEsc = $this->esc($title);
      $urlEsc   = $this->esc($url);

      return '<a class="' . $class . ' dbx-win" href="' . $urlEsc . '" data-dbx-tooltip="' . $titleEsc . '" '
           . 'data-url="' . $urlEsc . '" data-title="' . $titleEsc
           . '" role="button"><i class="' . $this->esc($icon) . '"></i></a>';
   }



   /**
    * Erzeugt ein Bootstrap-Badge fuer Status- und Hinweiswerte.
    *
    * @param string $label Eingabeparameter fuer diese Methode.
    * @param string $class Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function badge($label, $class = 'secondary') {
      return '<span class="badge bg-' . $this->esc($class) . '">' . $this->esc($label) . '</span>';
   }



   /**
    * Erzeugt ein Icon fuer Tabellenkoepfe mit Tooltip und ARIA-Label.
    *
    * @param string $icon Eingabeparameter fuer diese Methode.
    * @param string $title Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function header_icon($icon, $title) {
      return '<i class="' . $this->esc($icon) . '" title="' . $this->esc($title) . '" aria-label="' . $this->esc($title) . '"></i>';
   }



   /**
    * Normalisiert einen Text zu einem sicheren DD-Namen.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sanitize_dd_name($name) {
      $name = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $name);
      $name = trim($name, '_');

      if ($name === '') {
         $name = 'new_dd';
      }

      if (preg_match('/^[0-9]/', $name)) {
         $name = 'dd_' . $name;
      }

      return $name;
   }



   /**
    * Normalisiert einen Server- oder Tabellennamen fuer automatisch erzeugte DB-View-DDs.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sanitize_db_view_part($name) {
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string) $name);
      $name = trim($name, '._-');

      if ($name === '') {
         $name = 'db';
      }

      return $name;
   }



   /**
    * Normalisiert einen Namensteil fuer Backup-Dateinamen.
    *
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function backup_name_part($name) {
      $name = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)$name);
      $name = trim($name, '._-');
      return $name !== '' ? $name : 'db';
   }



   /**
    * Quoted einen Datenbank-Identifier passend zum DB-Typ.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param string $name Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function quote_db_ident($server, $name) {
      $dbType = dbx()->get_system_obj('dbxDB')->get_db_type($server);
      $name = str_replace(array('`', '"', ']'), '', (string)$name);

      if ($dbType === 'mysql') {
         return '`' . str_replace('`', '``', $name) . '`';
      }

      if ($dbType === 'sqlsrv') {
         return '[' . str_replace(']', ']]', $name) . ']';
      }

      return '"' . str_replace('"', '""', $name) . '"';
   }



   /**
    * Wandelt einen PHP-Wert in einen SQL-Literalwert fuer Restore-INSERTs um.
    *
    * @param string $server Eingabeparameter fuer diese Methode.
    * @param mixed $value Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function sql_db_value($server, $value) {
      if ($value === null) {
         return 'NULL';
      }

      if (is_bool($value)) {
         return $value ? '1' : '0';
      }

      if (is_array($value) || is_object($value)) {
         $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      $oDB = dbx()->get_system_obj('dbxDB');
      return "'" . $oDB->escape((string)$value, $server) . "'";
   }



   /**
    * Sendet eine JSON-Antwort und beendet die aktuelle Anfrage.
    *
    * @param array $data Eingabeparameter fuer diese Methode.
    * @return void
    */
   private function json_response($data) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode($data, JSON_UNESCAPED_UNICODE);
      exit;
   }



   /**
    * Liest und dekodiert den JSON-Request-Body.
    *
    * @return array
    */
   private function request_json() {
      $raw = file_get_contents('php://input');
      $data = $raw ? json_decode($raw, true) : array();
      return is_array($data) ? $data : array();
   }



   /**
    * Haengt Parameter an eine URL an.
    *
    * @param string $url Eingabeparameter fuer diese Methode.
    * @param array $params Eingabeparameter fuer diese Methode.
    * @return string
    */
   private function append_url_params($url, $params = array()) {
      if (!$url) {
         return '';
      }

      foreach ($params as $key => $value) {
         $url .= (strpos($url, '?') === false ? '?' : '&')
              . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
      }

      return $url;
   }
}
