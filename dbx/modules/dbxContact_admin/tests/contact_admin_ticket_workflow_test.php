<?php

declare(strict_types=1);

/**
 * Vertrag fuer den Ticket-Workflow: Status-/Prioritaets-Normalisierung,
 * Mail-Gating fuer interne Notizen, transaktionales Loeschen und
 * durchgaengiges Escaping nutzergelieferter Ticketfelder.
 */

$module = dirname(__DIR__);
$dbx_root = dirname(__DIR__, 3);

require_once $dbx_root . '/vendor/autoload.php';
require_once $dbx_root . '/include/dbxKernel.php';
require_once $dbx_root . '/include/tests/dbxModuleSourceBundle.php';
require_once dirname($module) . '/dbxContact/include/dbxContactTicket.class.php';
require_once dirname($module) . '/dbxContact/include/dbxContactPresentation.class.php';
require_once $module . '/include/dbxContactAdmin.class.php';

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

// --- normalizeStatus()/normalizePriority(): pure, no DB, defensive fallback ---
$status_class = \dbx\dbxContact\dbxContactTicket::class;
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::statuses()) as $status) {
    $assert(
        $status_class::normalize_status($status) === $status,
        "normalizeStatus() veraendert einen gueltigen Status: {$status}"
    );
}
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::priorities()) as $priority) {
    $assert(
        $status_class::normalize_priority($priority) === $priority,
        "normalizePriority() veraendert eine gueltige Prioritaet: {$priority}"
    );
}
foreach (array("'; DROP TABLE contact_request; --", '<script>alert(1)</script>', '', 'unknown_status', 'OPEN') as $malicious) {
    $assert(
        $status_class::normalize_status($malicious) === 'open',
        'normalizeStatus() faellt bei ungueltigem Wert nicht auf "open" zurueck: ' . var_export($malicious, true)
    );
    $assert(
        $status_class::normalize_priority($malicious) === 'normal',
        'normalizePriority() faellt bei ungueltigem Wert nicht auf "normal" zurueck: ' . var_export($malicious, true)
    );
}

// --- zentrale Statusdarstellung und lokale Prioritaetsdarstellung ---
$class = new ReflectionClass(\dbx\dbxContact_admin\dbxContactAdmin::class);
$admin = $class->newInstanceWithoutConstructor();

foreach (array_keys(\dbx\dbxContact\dbxContactTicket::statuses()) as $status) {
    $assert(
        \dbx\dbxContact\dbxContactPresentation::status_class($status) !== '',
        "status_class() liefert keine CSS-Klasse fuer Status: {$status}"
    );
}
$assert(
    \dbx\dbxContact\dbxContactPresentation::status_class('not_a_real_status') === 'text-bg-light',
    'status_class() faellt bei unbekanntem Status nicht auf das neutrale Badge zurueck.'
);

$priority_class_method = $class->getMethod('priority_class');
$priority_class_method->setAccessible(true);
foreach (array_keys(\dbx\dbxContact\dbxContactTicket::priorities()) as $priority) {
    $assert(
        is_string($priority_class_method->invoke($admin, $priority)) && $priority_class_method->invoke($admin, $priority) !== '',
        "priorityClass() liefert keine CSS-Klasse fuer Prioritaet: {$priority}"
    );
}
$assert(
    $priority_class_method->invoke($admin, 'not_a_real_priority') === 'text-bg-light',
    'priorityClass() faellt bei unbekannter Prioritaet nicht auf das neutrale Badge zurueck.'
);

// --- deleteConfirmText(): pure formatting, reflectable with a minimal FD-message stub ---
$delete_confirm_text = $class->getMethod('delete_confirm_text');
$delete_confirm_text->setAccessible(true);
$fd_stub = new class {
    public function get_fd_message(string $key, string $fallback = ''): string { return $key; }
    public function format_fd_message(string $key, array $vars = array()): string {
        return $key . ':' . implode(',', array_map('strval', $vars));
    }
};
$empty_thread = $delete_confirm_text->invoke($admin, 5, 0, $fd_stub);
$assert(
    is_array($empty_thread) && str_contains((string)$empty_thread['hint'], 'delete_hint_empty'),
    'deleteConfirmText() nutzt bei leerem Verlauf nicht den "leer"-Hinweis.'
);
$with_thread = $delete_confirm_text->invoke($admin, 5, 3, $fd_stub);
$assert(
    is_array($with_thread) && str_contains((string)$with_thread['hint'], 'delete_hint_thread'),
    'deleteConfirmText() nutzt bei vorhandenem Verlauf nicht den Thread-Hinweis.'
);

// --- Source contracts: mail-gating, transactional delete, escaping ---
$source = dbx_test_module_source_bundle($module . '/include/dbxContactAdmin.class.php');

$assert(
    str_contains(
        $source,
        "\$send_mail = (int) \$form->get_post('send_mail', 0, 'int') === 1 && \$visibility === 'public' && dbxContactConfig::mail_on_reply();"
    ),
    'Interne Notizen (visibility=internal) koennten eine Kunden-Mail versenden - Mail-Gating fehlt oder wurde geaendert.'
);
$assert(
    str_contains($source, 'filter_var($from, FILTER_VALIDATE_EMAIL) === false'),
    'Antwort-Mails werden ohne Pruefung der Absenderadresse verschickt.'
);

$delete_start = strpos($source, 'private function delete_ticket_record(int $rid): bool {');
$assert($delete_start !== false, 'deleteTicketRecord() wurde nicht gefunden.');
$delete_end = $delete_start !== false ? strpos($source, "\n   }", $delete_start) : false;
$delete_body = $delete_start !== false && $delete_end !== false ? substr($source, $delete_start, $delete_end - $delete_start) : '';
$assert(
    str_contains($delete_body, '$db->begin(')
        && str_contains($delete_body, '$db->commit(')
        && str_contains($delete_body, '$db->rollback('),
    'Das Loeschen eines Tickets laeuft nicht mehr in einer Transaktion mit Rollback.'
);
$messages_pos = strpos($delete_body, 'DD_MESSAGE');
$ticket_delete_pos = strpos($delete_body, "delete(dbxContactTicket::DD_TICKET, \$rid");
$assert(
    $messages_pos !== false && $ticket_delete_pos !== false && $messages_pos < $ticket_delete_pos,
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
    str_contains($source, 'if (!$this->schema_ready())'),
    'run() prueft nicht mehr, ob das Ticket-Datenmodell installiert ist, bevor Aktionen laufen.'
);

if ($failures !== array()) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK contact admin ticket workflow: Normalisierung, Mail-Gating, Transaktion und Escaping.\n";
