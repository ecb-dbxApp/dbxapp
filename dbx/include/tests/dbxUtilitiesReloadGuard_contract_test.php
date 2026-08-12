<?php
declare(strict_types=1);

$base = dirname(__DIR__, 2);
$source = (string)file_get_contents($base . '/js/lib/utilities.js');
$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$assert(
    str_contains($source, 'key === "f5"')
        && str_contains($source, '(event.ctrlKey || event.metaKey) && key === "r"')
        && str_contains($source, 'allowLeaveGuardNavigation();'),
    'F5, Strg+R oder Cmd+R geben den Reload nicht vor der Verlassenswarnung frei.'
);
$assert(
    str_contains($source, 'window.navigation.addEventListener("navigate"')
        && str_contains($source, 'if (event) {'),
    'Browser-Navigationen werden bei verfuegbarer Navigation API nicht vor der Schliesswarnung freigegeben.'
);
$assert(
    str_contains($source, 'Ein normaler Link ist eine bewusste Navigation')
        && str_contains($source, 'const target = String(form.getAttribute("target")')
        && substr_count($source, 'allowLeaveGuardNavigation();') >= 4,
    'Link- oder Formularnavigationen werden weiterhin wie ein Tab-Schliessen behandelt.'
);
$assert(
    str_contains($source, 'window.addEventListener("beforeunload"')
        && str_contains($source, 'Date.now() <= leaveGuardAllowUntil'),
    'Der Reload-Bypass ist nicht mit der zentralen Verlassenswarnung verbunden.'
);
$assert(
    !str_contains($source, 'hasReloadSafeWorkspace()')
        && str_contains($source, 'event.preventDefault();')
        && str_contains($source, 'event.returnValue = "";'),
    'Das Schliessen eines Tabs oder Browserfensters ist nicht mehr durch beforeunload geschuetzt.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK reload bypasses the leave confirmation while the navigation guard remains active.\n";
