<?php

declare(strict_types=1);

/**
 * Vertrag fuer die Bestell-Statusanzeige und Statusmail-Diffs:
 * unbekannte Statuswerte duerfen weder abstuerzen noch ungeschuetzt
 * (XSS) gerendert werden, und die Statusmail-Aenderungstabelle darf nur
 * tatsaechlich geaenderte Felder zeigen. Der Mailversand selbst wird als
 * Quelltextvertrag geprueft (keine echte Mail im Testlauf).
 *
 * shop_admin_action_security_test.php (dbx/modules/dbxShop/tests/) deckt
 * bereits Token-/CSRF-Absicherung ab - hier geht es ausschliesslich um die
 * bisher ungetestete Statusanzeige- und Diff-Logik.
 */

$module = dirname(__DIR__);
$dbx_root = dirname(__DIR__, 3);

require_once $dbx_root . '/vendor/autoload.php';
require_once $dbx_root . '/include/dbxKernel.php';
require_once $dbx_root . '/include/tests/dbxModuleSourceBundle.php';
require_once $module . '/include/dbxShopAdmin.class.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$class = new ReflectionClass(\dbx\dbxShop_admin\dbxShopAdmin::class);
$admin = $class->newInstanceWithoutConstructor();

$invoke = static function (string $method, array $args) use ($class, $admin) {
    $m = $class->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($admin, $args);
};

// --- Status-/Kanal-Badges: bekannte Werte rendern, unbekannte stuerzen nicht ab ---
foreach (array(
    'order_status_badge' => array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled'),
    'payment_status_badge' => array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded'),
    'shipping_status_badge' => array('open', 'ready', 'shipped', 'delivered', 'returned'),
) as $method => $known_values) {
    foreach ($known_values as $value) {
        $html = (string)$invoke($method, array($value, null));
        $assert(
            str_contains($html, 'badge') && str_contains($html, 'text-bg-'),
            "{$method}('{$value}') liefert kein Badge-Markup."
        );
    }
    $unknown = (string)$invoke($method, array('<script>alert(1)</script>', null));
    $assert(
        !str_contains($unknown, '<script>') && str_contains($unknown, '&lt;script&gt;'),
        "{$method}() escaped einen unbekannten/boesartigen Statuswert nicht."
    );
    $assert(
        str_contains($unknown, 'text-bg-secondary'),
        "{$method}() faellt bei unbekanntem Status nicht auf ein neutrales Badge zurueck."
    );
}

$channel_badge = (string)$invoke('channel_badge', array('<img src=x onerror=alert(1)>'));
$assert(
    !str_contains($channel_badge, '<img'),
    'channel_badge() escaped einen boesartigen Kanalnamen nicht.'
);

$provider_label = (string)$invoke('payment_provider_label', array('unknown_provider', null));
$assert(
    $provider_label === 'unknown_provider',
    'payment_provider_label() faellt bei unbekanntem Provider nicht auf den Rohwert zurueck.'
);

// --- status_mail_changes_html(): nur tatsaechlich geaenderte Felder in der Diff-Tabelle ---
$base = array('status' => 'new', 'payment_status' => 'open', 'shipping_status' => 'open');

$no_change = (string)$invoke('status_mail_changes_html', array($base, $base));
$assert(
    str_contains($no_change, '<dl>') && !str_contains($no_change, '<table'),
    'status_mail_changes_html() zeigt bei unveraenderten Status trotzdem eine Diff-Tabelle statt der Zusammenfassung.'
);

$status_only_changed = array('status' => 'paid', 'payment_status' => 'open', 'shipping_status' => 'open');
$diff_html = (string)$invoke('status_mail_changes_html', array($base, $status_only_changed));
$assert(
    str_contains($diff_html, '<table') && str_contains($diff_html, 'Bestellstatus'),
    'status_mail_changes_html() zeigt den geaenderten Bestellstatus nicht in der Diff-Tabelle.'
);
$assert(
    !str_contains($diff_html, 'Zahlungsstatus') && !str_contains($diff_html, 'Versandstatus'),
    'status_mail_changes_html() zeigt unveraenderte Felder mit in der Diff-Tabelle an.'
);

$all_changed = array('status' => 'shipped', 'payment_status' => 'paid', 'shipping_status' => 'shipped');
$all_diff_html = (string)$invoke('status_mail_changes_html', array($base, $all_changed));
foreach (array('Bestellstatus', 'Zahlungsstatus', 'Versandstatus') as $label) {
    $assert(
        str_contains($all_diff_html, $label),
        "status_mail_changes_html() zeigt {$label} nicht, obwohl es sich geaendert hat."
    );
}

$xss_order = array('status' => '<script>x</script>', 'payment_status' => 'open', 'shipping_status' => 'open');
$xss_diff = (string)$invoke('status_mail_changes_html', array($base, $xss_order));
$assert(
    !str_contains($xss_diff, '<script>x</script>'),
    'status_mail_changes_html() escaped einen boesartigen Statuswert im Diff nicht.'
);

// --- send_order_status_mail(): Quelltextvertrag fuer die Absender-/Empfaenger-Guards ---
$source = dbx_test_module_source_bundle($module . '/include/dbxShopAdminOrderService.trait.php');
$assert(
    str_contains($source, 'if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {')
        && str_contains($source, "return array(false, 'Kundenmail wurde nicht gesendet: Der Mail-Absender in den Shop-Einstellungen ist ungültig.');"),
    'send_order_status_mail() prueft nicht mehr die Gueltigkeit der Absenderadresse.'
);
$assert(
    str_contains($source, "if (\$to === '' || !filter_var(\$to, FILTER_VALIDATE_EMAIL)) {"),
    'send_order_status_mail() prueft nicht mehr die Gueltigkeit der Kunden-E-Mail vor dem Versand.'
);
$assert(
    str_contains($source, "\$this->repo()->add_order_history((int)(\$order['id'] ?? 0), 'customer_mail',"),
    'Ein erfolgreich gesendeter Statusmail-Versand wird nicht mehr in der Bestellhistorie protokolliert.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK shop admin order status badges, mail-change diff and status-mail guards.\n";
