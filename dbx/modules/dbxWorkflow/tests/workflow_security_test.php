<?php

$root = dirname(__DIR__);
$engineFile = $root . '/include/dbxWorkflowEngine.class.php';
$adminFile = dirname(__DIR__, 2) . '/dbxWorkflow_admin/include/dbxWorkflowAdmin.class.php';
$engine = file_get_contents($engineFile);
$admin = file_get_contents($adminFile);

$fail = static function (string $message, int $code): void {
   fwrite(STDERR, "FAIL: $message\n");
   exit($code);
};

if (!is_string($engine) || !is_string($admin)) {
   $fail('Workflow-Quellen konnten nicht gelesen werden.', 1);
}

if (strpos($engine, "check_action_token('dbxWorkflow.start'") === false
   || strpos($engine, 'has_instance_action_token($iid)') === false) {
   $fail('Start- oder Instanzmutationen sind nicht mit dbx_token abgesichert.', 2);
}

if (strpos($engine, 'guest_can_access_instance($iid)') === false
   || !preg_match('/select\\(\\$this->ddInstance,[\\s\\S]*?,\\s*1\\s*\\);/', $engine)) {
   $fail('Instanzzugriffe nutzen weder Gast-Sessionbindung noch DD-Pruefung.', 3);
}

if (substr_count($engine, '$db->begin($this->ddInstance)') < 3
   || substr_count($engine, '$db->rollback($this->ddInstance)') < 3
   || substr_count($engine, '$db->commit($this->ddInstance)') < 3) {
   $fail('Workflow-Schritt, Automation und Abschluss sind nicht vollstaendig atomar.', 4);
}

if (strpos($engine, "'status' => 'finishing'") === false
   || strpos($engine, "(int)\$db->_update_count !== 1") === false) {
   $fail('Der externe Abschluss wird nicht atomar gegen Wiederholung beansprucht.', 5);
}

if (preg_match("/\\\\\$nextUrl\\s*\\.\\s*'&proc_cmd=/", $engine) === 1
   || strpos($admin, "action_token('dbxWorkflow.instance.' . \$id)") === false) {
   $fail('Ein zustandsaendernder Workflow-GET-Link wird noch ohne Token erzeugt.', 6);
}

echo "OK workflow security\n";
