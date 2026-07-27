<?php
/**
 * Lokale Mail- und Token-Konfiguration für geschützte Downloads.
 *
 * Diese Datei als config.local.php kopieren. token_secret benötigt einen
 * installationsspezifischen Zufallswert, z. B. aus:
 * php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */
$config['mail_from'] = '';
$config['token_secret'] = '';
