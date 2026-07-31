<?php
require_once dirname(__DIR__) . '/dbx/modules/dbxSelfTest/include/dbxSelfTestRunner.class.php';

use dbx\dbxSelfTest\dbxSelfTestRunner;

$runner = new dbxSelfTestRunner();
$testIds = array_values(array_map(
    static fn(array $test): string => (string)$test['id'],
    array_filter($runner->catalog('full'), static fn(array $test): bool => !in_array(
        (string)$test['id'],
        array('system.composer_validate', 'system.composer_audit'),
        true
    ))
));
$run = $runner->runProfile('full', $testIds, static function (array $result, ?array $current): void {
    $total = (int)($current['totals']['total'] ?? 0);
    $done = (int)($current['totals']['completed'] ?? 0);
    echo sprintf("[%d/%d] %-7s %s\n", $done, $total, strtoupper((string)$result['status']), $result['name']);
    if (($result['status'] ?? '') === 'failed') echo '  ' . $result['summary'] . PHP_EOL;
});
$totals = $run['totals'];
echo sprintf(
    "Self-Test %s: %d/%d bestanden, %d Fehler.\n",
    strtoupper((string)$run['status']),
    $totals['passed'],
    $totals['total'],
    $totals['failed']
);
exit(($run['status'] ?? '') === 'passed' ? 0 : 1);
