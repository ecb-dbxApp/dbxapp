<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/dbxUserGridActions.class.php';

use dbx\dbxUser_admin\dbxUserGridActions;

$database = new class {
    public array $deleted = array();
    public bool $result = true;

    public function delete(string $data_definition, int $id): bool
    {
        $this->deleted[] = array($data_definition, $id);
        return $this->result;
    }
};

$missing = dbxUserGridActions::delete_result($database, 'dbxUser', array(), 'ID fehlt');
if ($missing !== array('ok' => 0, 'success' => false, 'msg' => 'ID fehlt') || $database->deleted !== array()) {
    fwrite(STDERR, "Fehlende Grid-ID wird nicht einheitlich behandelt.\n");
    exit(1);
}

$success = dbxUserGridActions::delete_result($database, 'dbxUser', array('id' => '17'), 'ID fehlt');
if ($success !== array('ok' => 1, 'success' => true) || $database->deleted !== array(array('dbxUser', 17))) {
    fwrite(STDERR, "Grid-Loeschung liefert keine einheitliche Erfolgsantwort.\n");
    exit(1);
}

$database->result = false;
$failure = dbxUserGridActions::delete_result($database, 'dbxUser_groups', array('id' => 4), 'ID fehlt');
if ($failure !== array('ok' => 0, 'success' => false)) {
    fwrite(STDERR, "Fehlgeschlagene Grid-Loeschung wird nicht korrekt gemeldet.\n");
    exit(1);
}

echo "OK gemeinsame User-Grid-Aktionen\n";

