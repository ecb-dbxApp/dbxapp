<?php
declare(strict_types=1);

final class dbxKiContractTestApp {
    public function get_cfg(string $area): array {
        return array('secure' => str_repeat('s', 64));
    }
}

function dbx(): dbxKiContractTestApp {
    static $app;
    return $app ??= new dbxKiContractTestApp();
}

require_once dirname(__DIR__) . '/include/dbxKiContractService.class.php';

$service = new \dbx\dbxKi\dbxKiContractService();
$contract = $service->create(
    'cms',
    'page.update.v1',
    array('area' => 'cms', 'recipe' => 'page.update.v1'),
    array('steps' => array(array('id' => 'page', 'action' => 'page.update', 'params' => array('id' => 7, 'patch' => array('content' => '{{output:page.content}}'))))),
    array('page.content' => array('type' => 'html', 'required' => true)),
    array(),
    array('type' => 'page', 'id' => 7)
);
$answer = $service->answerTemplate($contract);
$answer['outputs']['page.content'] = '<p>Sicherer Inhalt</p>';
$bound = $service->bind($contract, $answer);
if (($bound['job']['steps'][0]['params']['patch']['content'] ?? '') !== '<p>Sicherer Inhalt</p>') {
    fwrite(STDERR, "FAIL: Antwort wurde nicht an den signierten Ablauf gebunden.\n");
    exit(1);
}

$tampered = $contract;
$tampered['job_template']['steps'][0]['action'] = 'page.delete';
try {
    $service->bind($tampered, $answer);
    fwrite(STDERR, "FAIL: Manipulierter Vertrag wurde akzeptiert.\n");
    exit(2);
} catch (InvalidArgumentException $expected) {
}

$extra = $answer;
$extra['outputs']['unexpected'] = 'x';
try {
    $service->bind($contract, $extra);
    fwrite(STDERR, "FAIL: Nicht deklariertes Antwortfeld wurde akzeptiert.\n");
    exit(3);
} catch (InvalidArgumentException $expected) {
}

$activeHtml = $answer;
$activeHtml['outputs']['page.content'] = '<script>alert(1)</script>';
try {
    $service->bind($contract, $activeHtml);
    fwrite(STDERR, "FAIL: Aktives HTML wurde akzeptiert.\n");
    exit(4);
} catch (InvalidArgumentException $expected) {
}

$service->consume($contract);
try {
    $service->bind($contract, $answer);
    fwrite(STDERR, "FAIL: Bereits verbrauchter Vertrag wurde erneut akzeptiert.\n");
    exit(5);
} catch (RuntimeException $expected) {
}

echo "OK dbxKi signed contract binding\n";
