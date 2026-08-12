<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/dbxFormValueResolver.class.php';
require_once dirname(__DIR__) . '/dbxReportDataWindow.class.php';

$validator = new class {
    public function validateResult(mixed $value, string $rules, string $name): array {
        $normalized = str_contains($rules, 'trim') ? trim((string)$value) : $value;
        return array('valid' => $normalized !== '', 'normalized' => $normalized, 'code' => $normalized !== '' ? 'ok' : 'required');
    }
};
$resolver = new dbxFormValueResolver($validator);
$resolved = $resolver->resolve(
    'title', 'default', 'trim|required', array('title' => 'data'), array('title' => 'sys'),
    array('title' => '  post  '), array('title' => 'get'), true, true
);
if ($resolved['value'] !== 'post' || $resolved['origin'] !== 'post' || $resolved['ok'] !== 1) {
    fwrite(STDERR, "FAIL Form-ValueResolver Priorität/Validierung.\n");
    exit(1);
}
$window = (new dbxReportDataWindow())->slice(array('a', 'b', 'c', 'd'), 1, 2);
if ($window !== array('b', 'c')) {
    fwrite(STDERR, "FAIL Report-Datenfenster.\n");
    exit(1);
}
echo "OK getrennte Form-Wertauflösung und Report-Datenfenster.\n";
