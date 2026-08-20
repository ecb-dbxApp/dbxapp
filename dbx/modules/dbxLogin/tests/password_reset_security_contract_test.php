<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
$source = (string)file_get_contents($root . '/dbx/modules/dbxLogin/include/password_reset.class.php');
$module = (string)file_get_contents($root . '/dbx/modules/dbxLogin/dbxLogin.class.php');
$login = (string)file_get_contents($root . '/dbx/modules/dbxLogin/tpl/htm/form-login.htm');
$request = (string)file_get_contents($root . '/dbx/modules/dbxLogin/tpl/htm/form-password-reset-request.htm');
$reset = (string)file_get_contents($root . '/dbx/modules/dbxLogin/tpl/htm/form-password-reset.htm');

$assert = static function (bool $condition, string $message): void {
   if (!$condition) {
      throw new RuntimeException($message);
   }
};

$assert(str_contains($module, "case 'password_reset'") && str_contains($login, 'dbx_run1=password_reset'), 'Passwort-Reset ist nicht über Login und Router erreichbar.');
$assert(str_contains($source, 'bin2hex(random_bytes(32))') && str_contains($source, "hash('sha256', \$token)"), 'Einmal-Token ist nicht kryptografisch erzeugt und gehasht gespeichert.');
$assert(str_contains($source, 'hash_equals(') && str_contains($source, '$token_lifetime = 3600'), 'Tokenprüfung oder Ablaufzeit fehlt.');
$assert(str_contains($source, '$request_cooldown = 60') && str_contains($source, 'password reset rate limited'), 'Anforderungsbegrenzung fehlt.');
$assert(substr_count($source, 'Falls ein aktives, bestätigtes Konto passt') === 1 && !str_contains($request, 'Konto existiert nicht'), 'Öffentliche Antwort ermöglicht Benutzer-Ermittlung.');
$assert(str_contains($source, '\\dbxPasswordPolicy::errors') && str_contains($source, 'password_hash($password, PASSWORD_DEFAULT)'), 'Zentrale Passwort-Richtlinie oder sichere Speicherung fehlt.');
$assert(str_contains($source, "unset(\$settings['password_reset']") && str_contains($source, "delete('dbxSession', 'userid='"), 'Einmal-Verbrauch oder Sitzungswiderruf fehlt.');
$assert(str_contains($request, '[dbx:form]') && str_contains($reset, '[dbx:form]'), 'Reset-Formulare verwenden den dbxForm-Schutz nicht.');
$assert(!preg_match('/token_hash[^\n]+\$token[^\n]+settings/s', $source), 'Klartext-Token darf nicht in settings gespeichert werden.');

echo "OK password reset security contract: neutral response, hashed one-time token, expiry, policy and session revocation\n";
