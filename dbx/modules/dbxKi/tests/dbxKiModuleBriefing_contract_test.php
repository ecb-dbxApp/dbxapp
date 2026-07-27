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

if ($class->getConstant('MODULE_BRIEFING_VERSION') !== '0.6') {
    $fail('Modul-Briefing-Version ist nicht 0.6.', 1);
}

$rules = $invoke($class, $service, 'hardRules', array('demoModule'));
$rulesText = implode("\n", $rules);
foreach (array(
    'ausschliesslich ueber dbxDB und DD-Namen',
    'create_date, create_uid, update_date, update_uid und owner',
    'dbxapp-Exportformat',
    'Keine $addField-Closure',
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
) as $requiredRule) {
    if (strpos($rulesText, $requiredRule) === false) {
        $fail('Verbindliche Regel fehlt: ' . $requiredRule, 2);
    }
}

$way = $invoke($class, $service, 'dbxappWay');
foreach (array(
    'database',
    'create_update',
    'navigation',
    'form_security',
    'action_security',
    'callbacks',
    'system_objects',
    'reference',
) as $requiredKey) {
    if (!isset($way[$requiredKey]) || trim((string)$way[$requiredKey]) === '') {
        $fail('dbxappWay-Eintrag fehlt: ' . $requiredKey, 3);
    }
}
$contract = $invoke(
    $class,
    $service,
    'dbxApiContract',
    array('demoModule')
);
foreach (array('forms', 'reports', 'dd', 'db', 'audit', 'objects', 'escaping', 'get', 'action_links') as $requiredKey) {
    if (!isset($contract[$requiredKey]) || trim((string)$contract[$requiredKey]) === '') {
        $fail('dbxApiContract-Eintrag fehlt: ' . $requiredKey, 4);
    }
}
foreach (array('{fid}_{event}-Callback-Defaults', 'add_rep()', '{rpt:colspan}') as $requiredReportRule) {
    if (strpos((string)$contract['reports'], $requiredReportRule) === false) {
        $fail('dbxReport-Default fehlt im API-Vertrag: ' . $requiredReportRule, 4);
    }
}

$reference = $invoke($class, $service, 'referenceStandard');
if (
    ($reference['export_manual'] ?? '') !== 'reference/25_Verbindliches_Modulhandbuch.md'
    || ($reference['export_module'] ?? '') !== 'reference/myInvoices'
) {
    $fail('Exportierte Modulreferenz ist unvollstaendig.', 5);
}

$guide = $invoke(
    $class,
    $service,
    'modulePipelineGuide',
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
    '$this->addReferenceToZip($zip);',
    "'reference/README.md' => \$this->referenceText()",
) as $requiredSource) {
    if (strpos($source, $requiredSource) === false) {
        $fail('Exportimplementierung fehlt: ' . $requiredSource, 8);
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
    '## Module bearbeiten und aktualisieren',
    'reference/25_Verbindliches_Modulhandbuch.md',
    'reference/myInvoices/',
    '`delete` und `save`',
    'zusammen mit `rid` automatisch erkannt',
    'dbx()->action_url($url)',
    'keinen zusätzlichen',
) as $requiredDocumentation) {
    if (strpos($instructions, $requiredDocumentation) === false) {
        $fail('KI-Dokumentation fehlt: ' . $requiredDocumentation, 10);
    }
}

echo "OK dbxKi module briefing contract\n";
