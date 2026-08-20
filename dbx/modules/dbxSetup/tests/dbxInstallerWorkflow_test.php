<?php

declare(strict_types=1);

$root = dirname(__DIR__, 4);
chdir($root);
$_SERVER['REQUEST_URI'] = '/dbxapp/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_SOFTWARE'] = 'dbxapp-test';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/dbxapp/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

if (!defined('dbxSystem')) {
    define('dbxSystem', 'dbxWebApp');
}
if (!defined('dbxRunAsAdmin')) {
    define('dbxRunAsAdmin', 1);
}

require_once $root . '/dbx/vendor/autoload.php';
require_once $root . '/dbx/include/dbxKernel.php';
require_once dirname(__DIR__) . '/include/dbxInstallationService.class.php';
require_once dirname(__DIR__) . '/include/dbxInstall.class.php';

use dbx\dbxSetup\dbxInstall;

function installer_workflow_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$installer = new dbxInstall();
$checks = $installer->system_checks();
$by_id = array_column($checks, null, 'id');
foreach (array(
    'php',
    'ext-session',
    'ext-pdo',
    'ext-json',
    'ext-openssl',
    'write-config',
    'write-files',
    'write-tmp',
    'vendor',
) as $required_id) {
    installer_workflow_assert(
        isset($by_id[$required_id])
            && $by_id[$required_id]['required'] === true
            && $by_id[$required_id]['status'] === 'ok',
        'Notwendige Systemprüfung fehlt oder schlägt fehl: ' . $required_id
    );
}
foreach (array(
    'optional-pdo_sqlite',
    'optional-pdo_mysql',
    'optional-pdo_pgsql',
    'optional-pdo_sqlsrv',
    'https',
    'memory',
) as $optional_id) {
    installer_workflow_assert(
        isset($by_id[$optional_id]),
        'Optionale Systemprüfung fehlt: ' . $optional_id
    );
}

$template = (string)file_get_contents(
    $root . '/dbx/design/dbxapp/htm/install.htm'
);
$style = (string)file_get_contents(
    $root . '/dbx/design/dbxapp/css/install.css'
);
$javascript = (string)file_get_contents(
    $root . '/dbx/design/dbxapp/js/install.js'
);
$utilities = (string)file_get_contents(
    $root . '/dbx/js/lib/utilities.js'
);
$open_win = (string)file_get_contents(
    $root . '/dbx/js/lib/openWin.js'
);
$workflow = (string)file_get_contents(
    dirname(__DIR__) . '/include/dbxInstall.class.php'
);
$web_app = (string)file_get_contents(
    $root . '/dbx/include/dbxWebApp.class.php'
);
$web_app_traits = glob($root . '/dbx/include/dbxWebApp*.trait.php') ?: array();
foreach ($web_app_traits as $trait_file) {
    $web_app .= "\n" . (string)file_get_contents($trait_file);
}
$api = (string)file_get_contents(
    $root . '/dbx/include/dbxApi.php'
);
$api_traits = glob($root . '/dbx/include/dbxApi*.trait.php') ?: array();
foreach ($api_traits as $trait_file) {
    $api .= "\n" . (string)file_get_contents($trait_file);
}
$pipeline = (string)file_get_contents(
    $root . '/dbx/include/dbxRequestPipeline.class.php'
);
$mail = (string)file_get_contents(
    $root . '/dbx/include/dbxMail.class.php'
);

installer_workflow_assert(
    str_contains($template, '[dbx:content]')
        && str_contains($template, 'install.css')
        && str_contains($template, 'dbx/design/dbxapp/css/colors.css')
        && str_contains($template, 'install.js')
        && str_contains($template, 'install.css?v={dbx:asset_version}')
        && str_contains($template, 'install.js?v={dbx:asset_version}')
        && str_contains($template, 'colors.css?v={dbx:asset_version}'),
    'Eigenständiges Installationsdesign ist unvollständig.'
);
installer_workflow_assert(
    str_contains($style, '.dbx-install-layout')
        && str_contains($style, '@media (max-width: 780px)')
        && str_contains($style, 'dbx-content-blue-v1.svg?v=2')
        && str_contains($style, 'background-attachment: fixed')
        && str_contains($style, '.dbx-install-password-toggle')
        && str_contains($javascript, 'data-dbx-password-toggle')
        && str_contains($javascript, 'data-install-storage-note')
        && str_contains($javascript, 'syncStorageMode')
        && str_contains($javascript, 'Passwort verbergen'),
    'Installationsdesign ist nicht responsiv oder nicht im dbxapp-Blue-Stil definiert.'
);
installer_workflow_assert(
    str_contains($open_win, 'getViewportPageBounds()')
        && str_contains($open_win, 'window.visualViewport')
        && str_contains($open_win, 'bounds.top + (viewportHeight - height) / 2')
        && str_contains($open_win, 'Math.max(bounds.top, bounds.bottom - height)')
        && str_contains($open_win, 'this.clampWindowToViewport(windowData)'),
    'openWin muss Installationshilfen vollständig innerhalb des sichtbaren Viewports öffnen.'
);
installer_workflow_assert(
    str_contains($workflow, 'data-install-storage-note="sqlite"')
        && str_contains($workflow, 'data-install-storage-note="mysql"')
        && str_contains($workflow, 'data-install-storage-note="configured"')
        && str_contains($workflow, "value=\"' . \$this->h(\$check_action) . '\"")
        && str_contains($workflow, "'check_database'")
        && str_contains($workflow, "'check_mail'")
        && str_contains($workflow, 'Datenbank erneut prüfen')
        && str_contains($workflow, 'E-Mail erneut prüfen')
        && str_contains($workflow, 'Zuletzt erfolgreich:')
        && str_contains($javascript, 'markCheckedValuesChanged')
        && str_contains($javascript, 'Werte seit der letzten Prüfung geändert'),
    'Datenspeicher, E-Mail-Werte und ihr aktueller Prüfstatus müssen eindeutig dargestellt werden.'
);
installer_workflow_assert(
    str_contains($workflow, 'storage_advanced_confirm')
        && str_contains($workflow, 'Erweiterte Datenbankauswahl bestätigen')
        && str_contains($workflow, 'auch später in der Administration auswählen')
        && str_contains($workflow, 'Bereits eingerichtete Datenbanken')
        && !str_contains($workflow, "'Vorhandene Bindungen'")
        && str_contains($javascript, 'data-install-storage-confirm')
        && str_contains($style, '.dbx-install-storage-confirm'),
    'PDO und bestehende Datenbankziele benötigen eine verständliche Bestätigung mit DB3 als einfachem Standard.'
);
installer_workflow_assert(
    str_contains($workflow, 'if (!$this->stay_on_step)')
        && str_contains($workflow, '$step === 3 && $action === \'check_database\'')
        && str_contains($workflow, '$step === 6 && $action === \'check_mail\'')
        && str_contains($workflow, 'mail_test_recipient')
        && str_contains($workflow, 'Empfänger der Test-E-Mail')
        && str_contains($workflow, 'Ohne diese ausdrückliche Auswahl wird keine E-Mail versendet.')
        && str_contains($style, '.dbx-install-action-group')
        && str_contains($style, '.dbx-install-check-status'),
    'Datenbank- und E-Mail-Prüfungen müssen wiederholbar sein, ohne den Installationsschritt zu wechseln.'
);
foreach (array(
    'Systemprüfung',
    'Website & Design',
    'Datenspeicher',
    'Strukturen & Daten',
    'Administration',
    'E-Mail',
    'Prüfen & starten',
) as $step_title) {
    installer_workflow_assert(
        str_contains($workflow, $step_title),
        'Installationsschritt fehlt: ' . $step_title
    );
}
installer_workflow_assert(
    str_contains($workflow, "patch_local_config('dbx', \$patch)")
        && str_contains($workflow, "'install' => 0")
        && !str_contains($workflow, "set_cfg('dbx', \$config)"),
    'Installationsabschluss ist nicht ausschließlich lokal/updatefest.'
);
installer_workflow_assert(
    str_contains($web_app, 'if ($install || !$ok)')
        && str_contains($web_app, "\$design = 'dbxapp'")
        && str_contains($web_app, "\$page   = 'install'"),
    'Installationsrouting reagiert nicht verbindlich auf install=1.'
);
installer_workflow_assert(
    str_contains($pipeline, 'if (!$install_mode)')
        && str_contains($pipeline, 'check_perma()'),
    'Bootstrap trennt Installation und Datenbank-/Permalinkauflösung nicht.'
);
installer_workflow_assert(
    str_contains($mail, "array('internal', 'disabled', 'external')")
        && str_contains($mail, 'handle_internal_delivery'),
    'Globaler E-Mail-Schalter ist nicht vollständig umgesetzt.'
);
installer_workflow_assert(
    substr_count($workflow, "\$current[self::SQL_SERVER]['pass'] ?? ''") >= 2,
    'Leere Passwortfelder müssen vorhandene SQL- und Mail-Passwörter bewahren.'
);
installer_workflow_assert(
    str_contains($workflow, '$installer->verify_bundled_schema()')
        && str_contains($workflow, "'read_only' => 1")
        && str_contains($workflow, 'es wurde nichts verändert')
        && str_contains($workflow, "\$this->actions(4, 'Weiter zur Administration')")
        && str_contains($workflow, 'Danach folgt Schritt 5'),
    'Der ausgelieferte DB3-Standard muss ohne Schema- oder Datenmutation geprüft werden.'
);
installer_workflow_assert(
    str_contains($workflow, '$this->installer()->ensure_initial_admin(')
        && str_contains($workflow, "\$this->post_secret('admin_password'")
        && str_contains($workflow, "\$this->post_secret('admin_password_repeat'")
        && str_contains($workflow, '$this->password_criteria_missing(')
        && str_contains($workflow, 'bisherige Passwort des vorhandenen Benutzers')
        && str_contains($workflow, 'Persönlicher Administratorzugang')
        && str_contains($workflow, 'Administratorzugang speichern')
        && str_contains($workflow, 'Persönliches Passwort eingerichtet')
        && !str_contains($workflow, 'admin / 123456 festlegen')
        && !str_contains($workflow, 'Passwortwechsel beim ersten Login erforderlich'),
    'Schritt 5 muss das persönliche Admin-Passwort zweimal abfragen, prüfen und unmittelbar setzen.'
);
installer_workflow_assert(
    str_contains($workflow, "'password_min_length' => max(")
        && str_contains($workflow, 'Standard: 6 Zeichen')
        && str_contains($workflow, '12 oder mehr Zeichen empfohlen')
        && str_contains($workflow, 'data-install-password-rules')
        && str_contains($javascript, 'initInstallPasswordRules')
        && str_contains($style, '.dbx-install-password-rules'),
    'Die konfigurierbare Passwort-Mindestlänge und ihre Live-Kriterien fehlen im Installer.'
);
installer_workflow_assert(
    str_contains($workflow, 'dbx-install-mail-sections')
        && str_contains($workflow, 'data-install-mail-fields')
        && str_contains($workflow, 'box.hidden=')
        && str_contains($style, '.dbx-install-mail-sections'),
    'Die E-Mail-Konfiguration muss kompakt gruppiert und nur bei externem Versand sichtbar sein.'
);
installer_workflow_assert(
    str_contains($workflow, 'private function field_tooltip(')
        && str_contains($workflow, 'private function tooltip_icon(')
        && str_contains($workflow, 'data-dbx-tooltip=')
        && str_contains($style, '.dbx-install-tooltip'),
    'Installer-Eingabefelder benötigen zentrale, verständliche Tooltips.'
);
foreach (array(
    'site_title',
    'brand_name',
    'brand_tagline',
    'default_lng',
    'timezone',
    'default_design_user',
    'default_design_admin',
    'storage_advanced_confirm',
    'db_type',
    'db_host',
    'db_port',
    'db_name',
    'db_user',
    'db_password',
    'db_create',
    'migrate_data',
    'initial_admin_user',
    'admin_password',
    'admin_password_repeat',
    'password_min_length',
    'mail_transport',
    'mail_host',
    'mail_port',
    'mail_secure',
    'mail_auth',
    'mail_user',
    'mail_password',
    'mail_from_email',
    'mail_from_name',
    'mail_sender',
    'mail_from_domains',
    'contact_mail_from',
    'shop_mail_from',
    'mail_force_from',
    'mail_test_recipient',
    'mail_send_test',
    'confirm_install',
) as $tooltip_field) {
    installer_workflow_assert(
        str_contains($workflow, "'" . $tooltip_field . "' => '"),
        'Verständlicher Installer-Tooltip fehlt für: ' . $tooltip_field
    );
}
installer_workflow_assert(
    str_contains($utilities, 'isSameWebsiteNavigation')
        && str_contains($utilities, 'allowIfInternalNavigation')
        && str_contains($utilities, 'window.addEventListener("beforeunload"')
        && str_contains($utilities, 'leaveGuard')
        && str_contains($utilities, 'data-dbx-leave-allow')
        && str_contains($api, 'leaveGuard.allowIfInternal')
        && !str_contains($javascript, 'beforeunload'),
    'Die globale Verlassenswarnung muss zentral liegen und interne dbxapp-Weiterleitungen freigeben.'
);
installer_workflow_assert(
    str_contains($workflow, "'mysql' => 'pdo_mysql'")
        && str_contains($workflow, "'pgsql' => 'pdo_pgsql'")
        && str_contains($workflow, "'sqlsrv' => 'pdo_sqlsrv'")
        && substr_count(
            $workflow,
            '$db->can_connect_database_config($db_config, true)'
        ) >= 2
        && str_contains(
            $workflow,
            '$db->ensure_database_exists(self::SQL_SERVER, $db_config)'
        ),
    'PDO-Migration darf erst nach erreichbarer oder erfolgreich angelegter Zieldatenbank starten.'
);
installer_workflow_assert(
    str_contains($workflow, "\$profile['host']")
        && str_contains($workflow, "\$profile['dbname']")
        && str_contains($workflow, "\$profile['user']")
        && substr_count(
            $workflow,
            'Leer lassen: vorhandenes Passwort beibehalten'
        ) >= 2,
    'Vorhandene DB- und Mailwerte müssen als sichere Vorgaben weiterverwendet werden.'
);
installer_workflow_assert(
    str_contains($workflow, "contact_mail_from")
        && str_contains($workflow, "shop_mail_from")
        && str_contains($workflow, "patch_local_config('dbxContact'")
        && str_contains($workflow, "patch_local_config('dbxShop'")
        && str_contains($workflow, 'Kontakt-Absender')
        && str_contains($workflow, 'Shop-Absender'),
    'Kontakt und Shop benötigen eigene frei konfigurierbare und lokal gespeicherte Absender.'
);
installer_workflow_assert(
    str_contains($workflow, 'lib=openWin|id=install-help-')
        && str_contains($workflow, "array('design', 'db3', 'pdo', 'email')")
        && str_contains($workflow, 'KI-gestützte Designanpassung erklärt')
        && str_contains($workflow, 'Das Design später mit KI weiterentwickeln')
        && str_contains($workflow, 'Mitgelieferte DB3 direkt verwenden')
        && str_contains($workflow, 'DB3-Daten auf einen PDO-Server übertragen')
        && str_contains($workflow, 'E-Mail-Versand sicher konfigurieren')
        && str_contains($workflow, 'E-Mail-Konfiguration ausführlich erklärt')
        && str_contains($workflow, 'Globalen Absender für alle Module erzwingen')
        && str_contains($workflow, 'dbxContact.mail_from')
        && str_contains($workflow, 'dbxShop.mail_from'),
    'Design, DB3-Standard, PDO-Migration und E-Mail-Konfiguration benötigen ausführliche openWin-Hilfen.'
);

$config = array();
require $root . '/dbx/modules/dbx/cfg/config.php';
installer_workflow_assert(
    ($config['mail_delivery_mode'] ?? '') === 'internal',
    'Öffentlicher Mailstandard muss intern und damit netzwerkfrei sein.'
);
installer_workflow_assert(
    (int)($config['password_min_length'] ?? 0) === 6,
    'Die ausgelieferte Passwort-Mindestlänge muss standardmäßig 6 Zeichen betragen.'
);
installer_workflow_assert(
    (int)($config['install'] ?? 0) === 1,
    'Die ausgelieferte Standardkonfiguration muss den Installer aktivieren.'
);

echo "OK installer workflow, environment checks, secure local finish and mail modes\n";
