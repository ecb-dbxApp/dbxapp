<?php
require_once dirname(__DIR__) . '/include/dbxSelfTestRunner.class.php';

use dbx\dbxSelfTest\dbxSelfTestRunner;

$profile = 'full';
$requested = array();
$json = false;
foreach (array_slice($argv ?? array(), 1) as $argument) {
    if ($argument === '--quick' || $argument === '--profile=quick') $profile = 'quick';
    if ($argument === '--json') $json = true;
    if (str_starts_with($argument, '--test=')) $requested[] = substr($argument, 7);
}

$runner = new dbxSelfTestRunner();
$run = $runner->runProfile($profile, $requested, static function (array $result, ?array $current) use ($json): void {
    if ($json) return;
    $total = (int)($current['totals']['total'] ?? 0);
    $done = (int)($current['totals']['completed'] ?? 0);
    $label = strtoupper((string)$result['status']);
    fwrite(STDOUT, sprintf("[%d/%d] %-7s %s (%s)\n", $done, $total, $label, $result['name'], $result['duration_ms'] . ' ms'));
    if (($result['status'] ?? '') === 'failed') {
        fwrite(STDOUT, '  ' . str_replace("\n", "\n  ", (string)$result['summary']) . "\n");
    }
});

if ($json) {
    echo json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    $totals = $run['totals'];
    fwrite(STDOUT, sprintf(
        "\nSelf-Test %s: %d bestanden, %d fehlgeschlagen, %d uebersprungen. Protokoll: %s\n",
        strtoupper((string)$run['status']),
        $totals['passed'],
        $totals['failed'],
        $totals['skipped'],
        $runner->runLogPath((string)$run['id'])
    ));
}
exit(($run['status'] ?? '') === 'passed' ? 0 : 1);
