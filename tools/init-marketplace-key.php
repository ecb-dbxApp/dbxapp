<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false || !extension_loaded('openssl')) {
    fwrite(STDERR, "Projektwurzel oder OpenSSL fehlt.\n");
    exit(1);
}
$key_id = $argv[1] ?? 'dbxapp-market-2026';
if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $key_id)) {
    fwrite(STDERR, "Ungueltige Schluessel-ID.\n");
    exit(1);
}
$private_dir = $root . '/files/sys/marketplace/keys';
$public_dir = $root . '/dbx/marketplace/keys';
foreach (array($private_dir, $public_dir) as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Schluesselverzeichnis konnte nicht angelegt werden.');
    }
}
$private_file = $private_dir . '/' . $key_id . '-private.pem';
$public_file = $public_dir . '/' . $key_id . '.pem';
if (is_file($private_file) || is_file($public_file)) {
    fwrite(STDERR, "Schluessel existiert bereits; er wird nicht ueberschrieben.\n");
    exit(2);
}
$openssl_config = getenv('OPENSSL_CONF');
if (!is_string($openssl_config) || !is_file($openssl_config)) {
    $xampp_config = 'C:/xampp/apache/conf/openssl.cnf';
    $openssl_config = is_file($xampp_config) ? $xampp_config : '';
}
$options = array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 3072,
);
if ($openssl_config !== '') {
    $options['config'] = $openssl_config;
}
$resource = openssl_pkey_new($options);
$private_pem = '';
$public_pem = '';
if ($resource !== false && openssl_pkey_export($resource, $private_pem, null, $options)) {
    $details = openssl_pkey_get_details($resource);
    $public_pem = is_array($details) ? (string)($details['key'] ?? '') : '';
} else {
    require_once $root . '/dbx/vendor/autoload.php';
    $key = \phpseclib3\Crypt\RSA::createKey(3072);
    $private_pem = $key->toString('PKCS8');
    $public_pem = $key->getPublicKey()->toString('PKCS8');
}
if ($public_pem === ''
    || file_put_contents($private_file, $private_pem, LOCK_EX) === false
    || file_put_contents($public_file, $public_pem, LOCK_EX) === false) {
    @unlink($private_file);
    @unlink($public_file);
    throw new RuntimeException('Signaturschluessel konnten nicht gespeichert werden.');
}
@chmod($private_file, 0600);

$trust_file = $root . '/dbx/marketplace/trust.json';
$trust = json_decode((string)@file_get_contents($trust_file), true);
$trust = is_array($trust) ? $trust : array('schema' => 1, 'algorithm' => 'rsa-sha256', 'keys' => array());
$trust['keys'][$key_id] = array(
    'algorithm' => 'rsa-sha256',
    'file' => $key_id . '.pem',
    'status' => 'active',
    'created_at' => gmdate('c'),
);
$json = json_encode($trust, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($json) || file_put_contents($trust_file, $json . "\n", LOCK_EX) === false) {
    throw new RuntimeException('Vertrauensspeicher konnte nicht aktualisiert werden.');
}
echo "Signaturschluessel erzeugt. Privat: files/sys (nicht ausliefern), oeffentlich: dbx/marketplace/keys.\n";
