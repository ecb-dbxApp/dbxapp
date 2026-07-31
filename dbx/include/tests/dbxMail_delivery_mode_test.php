<?php

declare(strict_types=1);

final class dbxMailDeliveryTestKernel
{
    public string $mode = 'internal';
    public array $messages = array();

    public function get_config(string $module, string $key, $default = 'undef')
    {
        if ($module === 'dbx' && $key === 'mail_delivery_mode') {
            return $this->mode;
        }

        return $default;
    }

    public function sys_msg(
        string $level,
        string $type,
        string $source,
        string $message,
        array $details = array()
    ): void {
        $this->messages[] = compact(
            'level',
            'type',
            'source',
            'message',
            'details'
        );
    }
}

$dbxMailDeliveryTestKernel = new dbxMailDeliveryTestKernel();

function dbx(): dbxMailDeliveryTestKernel
{
    global $dbxMailDeliveryTestKernel;
    return $dbxMailDeliveryTestKernel;
}

require_once dirname(__DIR__) . '/dbxMail.class.php';

function dbx_mail_delivery_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$mail = new dbxMail();
$mail->init();
$mail->set_from('system@example.test', 'dbxapp');
$mail->bodytext('Dieser Inhalt darf nicht protokolliert werden.');

$dbxMailDeliveryTestKernel->mode = 'internal';
$accepted = $mail->send(
    array('email' => 'recipient@example.test', 'name' => 'Empfänger'),
    'Interne Nachricht'
);
dbx_mail_delivery_assert($accepted === 1, 'Interne Zustellung muss angenommen werden.');
dbx_mail_delivery_assert(
    ($dbxMailDeliveryTestKernel->messages[0]['type'] ?? '') === 'mail-internal',
    'Interne Zustellung muss als mail-internal protokolliert werden.'
);
$loggedInternal = json_encode(
    $dbxMailDeliveryTestKernel->messages[0] ?? array(),
    JSON_UNESCAPED_UNICODE
);
dbx_mail_delivery_assert(
    !str_contains((string)$loggedInternal, 'Dieser Inhalt'),
    'Interne Zustellung darf keinen Mailinhalt protokollieren.'
);

$dbxMailDeliveryTestKernel->mode = 'disabled';
$blocked = $mail->send('recipient@example.test', 'Gesperrte Nachricht');
dbx_mail_delivery_assert($blocked === 0, 'Globale E-Mail-Sperre muss den Versand ablehnen.');
dbx_mail_delivery_assert(
    $mail->get_error() === 'E-Mail-Versand ist global deaktiviert.',
    'Die globale E-Mail-Sperre benötigt eine eindeutige Fehlermeldung.'
);
dbx_mail_delivery_assert(
    ($dbxMailDeliveryTestKernel->messages[1]['type'] ?? '') === 'mail-disabled',
    'Gesperrte Zustellung muss als mail-disabled protokolliert werden.'
);

$dbxMailDeliveryTestKernel->mode = 'unbekannt';
dbx_mail_delivery_assert(
    $mail->delivery_mode() === 'internal',
    'Unbekannte Versandarten müssen auf den sicheren internen Modus zurückfallen.'
);

echo "OK global mail delivery modes\n";
