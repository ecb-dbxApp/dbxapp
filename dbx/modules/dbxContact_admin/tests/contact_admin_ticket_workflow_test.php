<?php

declare(strict_types=1);

/**
 * Vertrag fuer den Ticket-Workflow: Status-/Prioritaets-Normalisierung,
 * Mail-Gating fuer interne Notizen, transaktionales Loeschen und
 * durchgaengiges Escaping nutzergelieferter Ticketfelder.
 */

$module = dirname(__DIR__);
$dbxRoot = dirname(__DIR__, 3);

require_once $dbxRoot . '/vendor/autoload.php';
require_once $dbxRoot . '/include/dbxKernel.php';
require_once $dbxRoot . '/include/tests/dbxModuleSourceBundle.php';
require_once dirname($module) . '/dbxContact/include/dbxContactTicket.class.php';
require_once $module . '/include/dbxContactAdmin.class.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

// --- normalizeStatus()/normalizePriority(): pure, no DB, defensive fallback ---
$statusClass = \dbx\dbxContact\dbxContactTicket::class;
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::statuses()) as $status) {
    $assert(
        $statusClass::normalizeStatus($status) === $status,
        "normalizeStatus() veraendert einen gueltigen Status: {$status}"
    );
}
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::priorities()) as $priority) {
    $assert(
        $statusClass::normalizePriority($priority) === $priority,
        "normalizePriority() veraendert eine gueltige Prioritaet: {$priority}"
    );
}
foreach (array("'; DROP TABLE contact_request; --", '<script>alert(1)</script>', '', 'unknown_status', 'OPEN') as $malicious) {
    $assert(
        $statusClass::normalizeStatus($malicious) === 'open',
        'normalizeStatus() faellt bei ungueltigem Wert nicht auf "open" zurueck: ' . var_export($malicious, true)
    );
    $assert(
        $statusClass::normalizePriority($malicious) === 'normal',
        'normalizePriority() faellt bei ungueltigem Wert nicht auf "normal" zurueck: ' . var_export($malicious, true)
    );
}

// --- statusClass()/priorityClass(): private, pure, reflectable without DB ---
$class = new ReflectionClass(\dbx\dbxContact_admin\dbxContactAdmin::class);
$admin = $class->newInstanceWithoutConstructor();

$statusClassMethod = $class->getMethod('statusClass');
$statusClassMethod->setAccessible(true);
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::statuses()) as $status) {
    $assert(
        is_string($statusClassMethod->invoke($admin, $status)) && $statusClassMethod->invoke($admin, $status) !== '',
        "statusClass() liefert keine CSS-Klasse fuer Status: {$status}"
    );
}
$assert(
    $statusClassMethod->invoke($admin, 'not_a_real_status') === 'text-bg-light',
    'statusClass() faellt bei unbekanntem Status nicht auf das neutrale Badge zurueck.'
);

$priorityClassMethod = $class->getMethod('priorityClass');
$priorityClassMethod->setAccessible(true);
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::priorities()) as $priority) {
    $assert(
        is_string($priorityClassMethod->invoke($admin, $priority)) && $priorityClassMethod->invoke($admin, $priority) !== '',
        "priorityClass() liefert keine CSS-Klasse fuer Prioritaet: {$priority}"
    );
}
$assert(
    $priorityClassMethod->invoke($admin, 'not_a_real_priority') === 'text-bg-light',
    'priorityClass() faellt bei unbekannter Prioritaet nicht auf das neutrale Badge zurueck.'
);

// --- deleteConfirmText(): pure formatting, reflectable with a minimal FD-message stub ---
$deleteConfirmText = $class->getMethod('deleteConfirmText');
$deleteConfirmText->setAccessible(true);
$fdStub = new class {
    public function get_fd_message(string $key, string $fallback = ''): string { return $key; }
    public function format_fd_message(string $key, array $vars = array()): string {
        return $key . ':' . implode(',', array_map('strval', $vars));
    }
};
$emptyThread = $deleteConfirmText->invoke($admin, 5, 0, $fdStub);
$assert(
    is_array($emptyThread) && str_contains((string)$emptyThread['hint'], 'delete_hint_empty'),
    'deleteConfirmText() nutzt bei leerem Verlauf nicht den "leer"-Hinweis.'
);
$withThread = $deleteConfirmText->invoke($admin, 5, 3, $fdStub);
$assert(
    is_array($withThread) && str_contains((string)$withThread['hint'], 'delete_hint_thread'),
    'deleteConfirmText() nutzt bei vorhandenem Verlauf nicht den Thread-Hinweis.'
);

// --- Source contracts: mail-gating, transactional delete, escaping ---
$source = dbx_test_module_source_bundle($module . '/include/dbxContactAdmin.class.php');

$assert(
    str_contains(
        $source,
        "\$sendMail = (int) \$form->get_post('send_mail', 0, 'int') === 1 && \$visibility === 'public' && dbxContactConfig::mailOnReply();"
    ),
    'Interne Notizen (visibility=internal) koennten eine Kunden-Mail versenden - Mail-Gating fehlt oder wurde geaendert.'
);
$assert(
    str_contains($source, 'filter_var($from, FILTER_VALIDATE_EMAIL) === false'),
    'Antwort-Mails werden ohne Pruefung der Absenderadresse verschickt.'
);

$deleteStart = strpos($source, 'private function deleteTicketRecord(int $rid): bool {');
$assert($deleteStart !== false, 'deleteTicketRecord() wurde nicht gefunden.');
$deleteEnd = $deleteStart !== false ? strpos($source, "\n   }", $deleteStart) : false;
$deleteBody = $deleteStart !== false && $deleteEnd !== false ? substr($source, $deleteStart, $deleteEnd - $deleteStart) : '';
$assert(
    str_contains($deleteBody, '$db->begin(')
        && str_contains($deleteBody, '$db->commit(')
        && str_contains($deleteBody, '$db->rollback('),
    'Das Loeschen eines Tickets laeuft nicht mehr in einer Transaktion mit Rollback.'
);
$messagesPos = strpos($deleteBody, 'DD_MESSAGE');
$ticketDeletePos = strpos($deleteBody, "delete(dbxContactTicket::DD_TICKET, \$rid");
$assert(
    $messagesPos !== false && $ticketDeletePos !== false && $messagesPos < $ticketDeletePos,
    'Nachrichten werden nicht mehr vor dem Ticket selbst geloescht/geprueft.'
);

foreach (array(
    "'subject' => \$this->h(\$ticket['subject'] ?? '')",
    "'name' => \$this->h(\$ticket['name'] ?? '')",
    "'email' => \$this->h(\$ticket['email'] ?? '')",
) as $needle) {
    $assert(
        str_contains($source, $needle),
        'Ticketfeld wird nicht mehr escaped beim Rendern der Detailansicht: ' . $needle
    );
}
$assert(
    str_contains($source, "nl2br(\$this->h(\$message['body'] ?? ''))"),
    'Der Nachrichtentext im Verlauf wird nicht mehr escaped, bevor er als HTML gerendert wird.'
);

$assert(
    str_contains($source, 'if (!$this->schemaReady())'),
    'run() prueft nicht mehr, ob das Ticket-Datenmodell installiert ist, bevor Aktionen laufen.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK contact admin ticket workflow: Normalisierung, Mail-Gating, Transaktion und Escaping.\n";
