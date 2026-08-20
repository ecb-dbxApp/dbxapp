<?php

require_once dirname(__DIR__) . '/dbxApi.php';

class dbxMissingLogTestDb {
   public array $rows = array();
   public bool $transaction = false;
   public int $begins = 0;
   public int $commits = 0;
   public int $rollbacks = 0;
   private int $insert_id = 0;

   public function begin(string $dd): int {
      if ($dd !== 'dbxMissing' || $this->transaction) return 0;
      $this->transaction = true;
      $this->begins++;
      return 1;
   }

   public function rollback(string $dd): int {
      $this->transaction = false;
      $this->rollbacks++;
      return 1;
   }

   public function commit(string $dd): int {
      if (!$this->transaction) return 0;
      $this->transaction = false;
      $this->commits++;
      return 1;
   }

   public function select1(string $dd, array $where, string $fields, int $verify_access) {
      foreach ($this->rows as $row) {
         if ($row['missing'] === $where['missing']) {
            return array('id' => $row['id'], 'count' => $row['count']);
         }
      }
      return array();
   }

   public function update(string $dd, array $values, $id, ...$options): int {
      if (!$this->transaction) return 0;
      foreach ($this->rows as &$row) {
         if ((int)$row['id'] === (int)$id) {
            $row = array_merge($row, $values);
            unset($row);
            return 1;
         }
      }
      unset($row);
      return 0;
   }

   public function insert(string $dd, array $values, ...$options): int {
      if (!$this->transaction) return 0;
      $this->insert_id++;
      $values['id'] = $this->insert_id;
      $this->rows[] = $values;
      return 1;
   }

   public function get_insert_id(): int {
      return $this->insert_id;
   }
}

class dbxMissingLogTestApi extends dbxApi {
   public dbxMissingLogTestDb $test_db;
   public array $errors = array();

   public function __construct() {
      $this->test_db = new dbxMissingLogTestDb();
   }

   public function get_system_obj(string $class, string $use = ''): ?object {
      if ($class === 'dbxDB') return $this->test_db;
      if ($class === 'dbxRuntime') return new class($this) {
         public function __construct(private dbxMissingLogTestApi $api) {}
         public function write_php_error_log(string $type, string $message, string $file = '', int $line = 0): void {
            $this->api->errors[] = $type . ': ' . $message;
         }
      };
      return null;
   }

   public function user($key = 'id') {
      return 0;
   }

}

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

$api = new dbxMissingLogTestApi();
$_SERVER['HTTP_REFERER'] = 'https://localhost/dbxapp/home/tutorial?token=secret#bereich';

$first_id = $api->log_missing('assets/missing.svg');
$second_id = $api->log_missing('assets/missing.svg');

if ($first_id !== 1 || $second_id !== 1 || count($api->test_db->rows) !== 1
   || (int)$api->test_db->rows[0]['count'] !== 2) {
   $fail('Wiederholte Ressourcenfehler werden nicht in einer Zeile gezaehlt.', 1);
}

if (($api->test_db->rows[0]['request'] ?? '') !== 'https://localhost/dbxapp/home/tutorial') {
   $fail('Aufrufende Seite wird nicht ohne sensible Querydaten gespeichert.', 2);
}

if ($api->test_db->begins !== 2 || $api->test_db->commits !== 2
   || $api->test_db->rollbacks !== 0 || $api->test_db->transaction) {
   $fail('dbxMissing-Schreibvorgaenge sind nicht sauber transaktional.', 3);
}

if ($api->log_missing('') !== 0 || $api->test_db->begins !== 2 || $api->errors !== array()) {
   $fail('Leere Eintraege werden nicht sauber ignoriert.', 4);
}

echo "OK dbxApi missing log\n";
