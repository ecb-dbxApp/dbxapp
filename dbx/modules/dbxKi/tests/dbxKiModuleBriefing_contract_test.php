<?php
/**
 * Vertragstest für die dbxKi-Modulbearbeitung.
 *
 * Der Test benötigt keine laufende dbXapp-Sitzung. Er prüft den von dbxKi
 * exportierten Architekturvertrag direkt an den dafür zuständigen Methoden.
 */

require_once dirname(__DIR__) . '/include/dbxKiModuleBriefingService.class.php';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit($code);
};

$class = new ReflectionClass('dbx\\dbxKi\\dbxKiModuleBriefingService');
$service = $class->newInstance();
$invoke = static function (
    ReflectionClass $class,
    object $service,
    string $method,
    array $arguments = array()
) {
    $reflection = $class->getMethod($method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($service, $arguments);
};

if ($class->getConstant('MODULE_BRIEFING_VERSION') !== '2.0') {
    $fail('Modul-Briefing-Version ist nicht 2.0.', 1);
}

$rules = $invoke($class, $service, 'hard_rules', array('demoModule'));
$rules_text = implode("\n", $rules);
foreach (array(
    'Kundendatei und Systemquelle unterscheiden',
    'Menueinhalte und installationsbezogene Menue-Templates',
    'nicht durch Updates ueberschreiben',
    'PHP, DD, FD, JavaScript, CSS und andere Modul-Sourcen',
    'ausschliesslich ueber dbxDB und DD-Namen',
    'create_date, create_uid, update_date, update_uid und owner',
    'dbxapp-Exportformat',
    'Jede DD-Aenderung wird geschrieben und danach verbindlich per DD->DB Sync',
    'Keine $add_field-Closure',
    'Vor jedem neuen Template zuerst vorhandene Templates',
    'Ein Formular besitzt fast immer ein individuelles Haupttemplate',
    '{form:bar}',
    'Form-ID und Template sind unabhaengig',
    'Default form-default nicht automatisch ueberschreiben',
    'Normale Tabellenreports verwenden das dbxReport-Standardtemplate',
    '{report:bar}',
    'Keine _en/_es-Markupkopien',
    'Nach einem Insert die von dbxDB gelieferte RID',
    '{fid}_next_record-Default',
    'spaet per add_rep()',
    '{rpt:colspan}',
    'keine unnoetigen Callback-Setter',
    'Keine Modulmethoden',
    'Nicht pauschal escapen',
    'GET bleibt fuer Navigation',
    'delete und save',
    'Keine action_routes-Konfiguration',
    'dbx()->action_url($url)',
    'keine check_action_token()-Pruefung',
    'keinen dbx_token hinzu',
    'Standardaktionen werden automatisch signiert',
    'Ajax und normaler POST',
    'myInvoices-Modul ist die ausfuehrbare Architektur-Referenz',
) as $required_rule) {
    if (strpos($rules_text, $required_rule) === false) {
        $fail('Verbindliche Regel fehlt: ' . $required_rule, 2);
    }
}

$way = $invoke($class, $service, 'dbxapp_way');
foreach (array(
    'database',
    'create_update',
    'navigation',
    'form_security',
    'action_security',
    'callbacks',
    'system_objects',
    'reference',
) as $required_key) {
    if (!isset($way[$required_key]) || trim((string)$way[$required_key]) === '') {
        $fail('dbxappWay-Eintrag fehlt: ' . $required_key, 3);
    }
}
$contract = $invoke(
    $class,
    $service,
    'dbx_api_contract',
    array('demoModule')
);
foreach (array('forms', 'reports', 'dd', 'db', 'audit', 'objects', 'escaping', 'get', 'action_links') as $required_key) {
    if (!isset($contract[$required_key]) || trim((string)$contract[$required_key]) === '') {
        $fail('dbxApiContract-Eintrag fehlt: ' . $required_key, 4);
    }
}
foreach (array('{fid}_{event}-Callback-Defaults', 'add_rep()', '{rpt:colspan}') as $required_report_rule) {
    if (strpos((string)$contract['reports'], $required_report_rule) === false) {
        $fail('dbxReport-Default fehlt im API-Vertrag: ' . $required_report_rule, 4);
    }
}

$reference = $invoke($class, $service, 'reference_standard');
if (
    ($reference['export_manual'] ?? '') !== 'reference/25_Verbindliches_Modulhandbuch.md'
    || ($reference['export_template_guide'] ?? '') !== 'reference/KI-TEMPLATES.md'
    || ($reference['export_module'] ?? '') !== 'reference/myInvoices'
) {
    $fail('Exportierte Modulreferenz ist unvollstaendig.', 5);
}

$guide = $invoke(
    $class,
    $service,
    'module_pipeline_guide',
    array('demoModule')
);
if (($guide['manifest']['task_type'] ?? '') !== 'update') {
    $fail('Standardaufgabe der Modul-Pipeline ist nicht update.', 6);
}
if (($guide['reference_standard']['export_module'] ?? '') !== 'reference/myInvoices') {
    $fail('Pipeline-Guide liefert den Referenzstandard nicht aus.', 7);
}

$source = (string)file_get_contents(
    dirname(__DIR__) . '/include/dbxKiModuleBriefingService.class.php'
);
foreach (array(
    "'update' => 'Bestehendes Modul bearbeiten / aktualisieren'",
    '$this->add_reference_to_zip($zip);',
    "\$zip->addFile(\$template_guide, 'reference/KI-TEMPLATES.md');",
    "'reference/README.md' => \$this->reference_text()",
) as $required_source) {
    if (strpos($source, $required_source) === false) {
        $fail('Exportimplementierung fehlt: ' . $required_source, 8);
    }
}
if (preg_match(
    '/private function \w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\{\s*'
    . 'return\s+dbx\(\)->get_(?:system|include)_obj/s',
    $source
)) {
    $fail('Reine dbx()-Wrapper sind in der Modulbearbeitung nicht erlaubt.', 8);
}

$template = (string)file_get_contents(
    dirname(__DIR__) . '/tpl/htm/ki-module-briefing.htm'
);
if (
    strpos($template, 'Module bearbeiten und aktualisieren') === false
    || strpos($template, 'Handbuch + Referenz') === false
) {
    $fail('Modul-Briefing zeigt den neuen Standard nicht an.', 9);
}

$instructions = (string)file_get_contents(dirname(__DIR__) . '/KI-INSTRUCTIONS.md');
foreach (array(
    'verbindlicher Auftrag-/Antwortweg',
    'auftrag.contract.json',
    'answer.json',
    'reference/25_Verbindliches_Modulhandbuch.md',
    'KI-TEMPLATES.md',
    'reference/myInvoices/',
    'Staging',
    'Ausführungstoken',
    'Template-Regeln für Module',
    'dbx|form-default',
    '{form:message}',
    '{report:footer}',
) as $required_documentation) {
    if (strpos($instructions, $required_documentation) === false) {
        $fail('KI-Dokumentation fehlt: ' . $required_documentation, 10);
    }
}

$template_guide = (string)file_get_contents(dirname(__DIR__) . '/KI-TEMPLATES.md');
foreach (array(
    'dbx/modules/{modul}/tpl/htm/',
    'dbx/modules/dbx/tpl/htm/',
    '{form:bar}',
    '{form:message}',
    '{form:footer}',
    'dbx|form-default',
    'dbx|report-default',
    '{report:bar}',
    '{report:message}',
    '{report:footer}',
    'set_table_actions()',
    'Form-ID',
) as $required_template_rule) {
    if (strpos($template_guide, $required_template_rule) === false) {
        $fail('Template-Leitfaden fehlt: ' . $required_template_rule, 11);
    }
}

echo "OK dbxKi module briefing contract\n";
