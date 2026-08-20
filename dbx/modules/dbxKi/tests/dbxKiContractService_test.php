<?php
declare(strict_types=1);

final class dbxKiContractTestApp {
    private array $session = array();

    public function get_cfg(string $area): array {
        return array('secure' => str_repeat('s', 64));
    }

    public function get_session_var(string $key, mixed $default = null, string $section = 'sys', string $module = 'modul'): mixed {
        if ($key === '*') {
            return $this->session[$module][$section] ?? $default;
        }
        return $this->session[$module][$section][$key] ?? $default;
    }

    public function set_session_var(string $key, mixed $value, string $section = 'sys', string $module = 'modul'): void {
        if ($key === '*') {
            $this->session[$module][$section] = $value;
            return;
        }
        $this->session[$module][$section][$key] = $value;
    }

    public function delete_session_var(string $key, string $section = 'sys', string $module = 'modul'): void {
        unset($this->session[$module][$section][$key]);
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
$answer = $service->answer_template($contract);
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

$active_html = $answer;
$active_html['outputs']['page.content'] = '<script>alert(1)</script>';
try {
    $service->bind($contract, $active_html);
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
