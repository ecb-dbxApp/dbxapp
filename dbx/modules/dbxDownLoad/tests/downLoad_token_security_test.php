<?php

declare(strict_types=1);

/**
 * Vertrag fuer dbxDownLoad: Token-Kodierung, Signatur-/Ablaufpruefung,
 * Spam-Gate vor Mailversand und dateisystemsichere Downloadpfade.
 *
 * secret()/create_token()/read_token() rufen dbx()->set_cfg() auf und
 * koennten dabei ungefragt files/config.local.php beschreiben - deshalb
 * werden sie hier nicht ausgefuehrt, sondern als Quelltextvertrag geprueft.
 * Nur die zustandslosen base64url-Helfer laufen als echter Verhaltenstest.
 */

$module = dirname(__DIR__);
$dbx_root = dirname(__DIR__, 3);

require_once $dbx_root . '/vendor/autoload.php';
require_once $dbx_root . '/include/dbxKernel.php';
require_once $dbx_root . '/include/tests/dbxModuleSourceBundle.php';
require_once $module . '/dbxDownLoad.class.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

// --- base64url()/base64url_decode(): pure, kein Config-/DB-Zugriff ---
$class = new ReflectionClass(\dbx\dbxDownLoad\dbxDownLoad::class);
$down_load = $class->newInstanceWithoutConstructor();

$encode = $class->getMethod('base64url');
$encode->setAccessible(true);
$decode = $class->getMethod('base64url_decode');
$decode->setAccessible(true);

foreach (array(
    '',
    'a',
    'ab',
    'abc',
    random_bytes(1),
    random_bytes(15),
    random_bytes(32),
    "\x00\x01\xff\xfe binaer",
) as $sample) {
    $encoded = (string)$encode->invoke($down_load, $sample);
    $assert(
        !str_contains($encoded, '+') && !str_contains($encoded, '/') && !str_contains($encoded, '='),
        'base64url() liefert kein URL-sicheres Alphabet fuer: ' . bin2hex($sample)
    );
    $round_trip = (string)$decode->invoke($down_load, $encoded);
    $assert(
        $round_trip === $sample,
        'base64url_decode(base64url($x)) != $x fuer: ' . bin2hex($sample)
    );
}
$assert(
    (string)$decode->invoke($down_load, '***not valid base64url***') === '',
    'base64url_decode() liefert bei ungueltiger Eingabe keinen leeren String zurueck.'
);

// --- Source contracts: Signatur, Ablauf, Zufallsquelle, Reihenfolge, Pfadsicherheit ---
$source = dbx_test_module_source_bundle($module . '/dbxDownLoad.class.php');

$assert(
    str_contains($source, 'if (!hash_equals($expected, $parts[1])) {'),
    'Die Token-Signatur wird nicht mehr zeitkonstant (hash_equals) geprueft.'
);
$assert(
    str_contains($source, "hash_hmac('sha256', \$body, \$this->secret(), true)")
        && str_contains($source, "hash_hmac('sha256', \$parts[0], \$this->secret(), true)"),
    'Token-Erzeugung oder -Pruefung signiert nicht mehr konsistent mit HMAC-SHA256.'
);
$assert(
    str_contains($source, "(int)(\$payload['exp'] ?? 0) < time()"),
    'Abgelaufene Tokens werden nicht mehr zurueckgewiesen.'
);
$assert(
    str_contains($source, 'bin2hex(random_bytes(32))') && str_contains($source, 'bin2hex(random_bytes(8))'),
    'Secret oder Nonce werden nicht mehr aus einer kryptographisch sicheren Zufallsquelle erzeugt.'
);

$spam_gate_pos = strpos($source, "if (\$spam_reason !== '') {");
$token_create_pos = strpos($source, '$token = $this->create_token($name, $email);');
$assert(
    $spam_gate_pos !== false && $token_create_pos !== false && $spam_gate_pos < $token_create_pos,
    'Das Spam-Gate greift nicht mehr, bevor ein Download-Token erzeugt und verschickt wird.'
);

$download_file_start = strpos($source, 'private function download_file(): string {');
$download_file_end = $download_file_start !== false ? strpos($source, "\n   }", $download_file_start) : false;
$download_file_body = $download_file_start !== false && $download_file_end !== false
    ? substr($source, $download_file_start, $download_file_end - $download_file_start)
    : '';
$assert($download_file_body !== '', 'download_file() wurde nicht gefunden.');
foreach (array('$_REQUEST', '$_GET', '$_POST', '$payload', '$token') as $user_input) {
    $assert(
        !str_contains($download_file_body, $user_input),
        'download_file() baut den Dateipfad jetzt (teilweise) aus Nutzereingaben statt nur aus der Konfiguration: ' . $user_input
    );
}

$assert(
    str_contains($source, "str_replace('\"', '', \$name)"),
    'Der Dateiname im Content-Disposition-Header wird nicht mehr gegen Anfuehrungszeichen abgesichert.'
);
$assert(
    str_contains($source, 'if (!$payload || !is_file($file) || !is_readable($file)) {')
        && str_contains($source, 'http_response_code(404);'),
    'Das Datei-Streaming liefert nicht mehr konsequent 404 bei fehlendem Token oder fehlender Datei.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK dbxDownLoad token security, spam gate ordering and path safety.\n";
