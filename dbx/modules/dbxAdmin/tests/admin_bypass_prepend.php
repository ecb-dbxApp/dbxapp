<?php
/**
 * Opt-in Admin-Bypass fuer lokale HTTP-Integrationstests.
 *
 * Verwendung:
 * php -d auto_prepend_file=dbx/modules/dbxAdmin/tests/admin_bypass_prepend.php \
 *     -S 127.0.0.1:8127 -t C:/xampp/htdocs/dbxapp
 */
if (!defined('dbxRunAsAdmin')) {
   define('dbxRunAsAdmin', 1);
}
