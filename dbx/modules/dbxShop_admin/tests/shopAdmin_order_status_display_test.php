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
$dbxRoot = dirname(__DIR__, 3);

require_once $dbxRoot . '/vendor/autoload.php';
require_once $dbxRoot . '/include/dbxKernel.php';
require_once $dbxRoot . '/include/tests/dbxModuleSourceBundle.php';
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
    'orderStatusBadge' => array('new', 'payment_pending', 'paid', 'processing', 'shipped', 'done', 'cancelled'),
    'paymentStatusBadge' => array('open', 'created', 'pending', 'completed', 'paid', 'failed', 'cancelled', 'refunded'),
    'shippingStatusBadge' => array('open', 'ready', 'shipped', 'delivered', 'returned'),
) as $method => $knownValues) {
    foreach ($knownValues as $value) {
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

$channelBadge = (string)$invoke('channelBadge', array('<img src=x onerror=alert(1)>'));
$assert(
    !str_contains($channelBadge, '<img'),
    'channelBadge() escaped einen boesartigen Kanalnamen nicht.'
);

$providerLabel = (string)$invoke('paymentProviderLabel', array('unknown_provider', null));
$assert(
    $providerLabel === 'unknown_provider',
    'paymentProviderLabel() faellt bei unbekanntem Provider nicht auf den Rohwert zurueck.'
);

// --- statusMailChangesHtml(): nur tatsaechlich geaenderte Felder in der Diff-Tabelle ---
$base = array('status' => 'new', 'payment_status' => 'open', 'shipping_status' => 'open');

$noChange = (string)$invoke('statusMailChangesHtml', array($base, $base));
$assert(
    str_contains($noChange, '<dl>') && !str_contains($noChange, '<table'),
    'statusMailChangesHtml() zeigt bei unveraenderten Status trotzdem eine Diff-Tabelle statt der Zusammenfassung.'
);

$statusOnlyChanged = array('status' => 'paid', 'payment_status' => 'open', 'shipping_status' => 'open');
$diffHtml = (string)$invoke('statusMailChangesHtml', array($base, $statusOnlyChanged));
$assert(
    str_contains($diffHtml, '<table') && str_contains($diffHtml, 'Bestellstatus'),
    'statusMailChangesHtml() zeigt den geaenderten Bestellstatus nicht in der Diff-Tabelle.'
);
$assert(
    !str_contains($diffHtml, 'Zahlungsstatus') && !str_contains($diffHtml, 'Versandstatus'),
    'statusMailChangesHtml() zeigt unveraenderte Felder mit in der Diff-Tabelle an.'
);

$allChanged = array('status' => 'shipped', 'payment_status' => 'paid', 'shipping_status' => 'shipped');
$allDiffHtml = (string)$invoke('statusMailChangesHtml', array($base, $allChanged));
foreach (array('Bestellstatus', 'Zahlungsstatus', 'Versandstatus') as $label) {
    $assert(
        str_contains($allDiffHtml, $label),
        "statusMailChangesHtml() zeigt {$label} nicht, obwohl es sich geaendert hat."
    );
}

$xssOrder = array('status' => '<script>x</script>', 'payment_status' => 'open', 'shipping_status' => 'open');
$xssDiff = (string)$invoke('statusMailChangesHtml', array($base, $xssOrder));
$assert(
    !str_contains($xssDiff, '<script>x</script>'),
    'statusMailChangesHtml() escaped einen boesartigen Statuswert im Diff nicht.'
);

// --- sendOrderStatusMail(): Quelltextvertrag fuer die Absender-/Empfaenger-Guards ---
$source = dbx_test_module_source_bundle($module . '/include/dbxShopAdminOrderService.trait.php');
$assert(
    str_contains($source, 'if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {')
        && str_contains($source, "return array(false, 'Kundenmail wurde nicht gesendet: Der Mail-Absender in den Shop-Einstellungen ist ungültig.');"),
    'sendOrderStatusMail() prueft nicht mehr die Gueltigkeit der Absenderadresse.'
);
$assert(
    str_contains($source, "if (\$to === '' || !filter_var(\$to, FILTER_VALIDATE_EMAIL)) {"),
    'sendOrderStatusMail() prueft nicht mehr die Gueltigkeit der Kunden-E-Mail vor dem Versand.'
);
$assert(
    str_contains($source, "\$this->repo()->addOrderHistory((int)(\$order['id'] ?? 0), 'customer_mail',"),
    'Ein erfolgreich gesendeter Statusmail-Versand wird nicht mehr in der Bestellhistorie protokolliert.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK shop admin order status badges, mail-change diff and status-mail guards.\n";
