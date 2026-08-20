<?php
namespace dbx\myInvoices;

/**
 * Installiert das DD-Schema und optionale, idempotente Beispieldaten.
 *
 * Schemaoperationen laufen über dbxDD. Alle Fachdaten werden ausschließlich
 * über dbxDB und die beiden Modul-DDs geschrieben.
 */
class myInvoicesFixtures
{
    private const INVOICE_DD = 'myInvoices|invoice';
    private const ITEM_DD = 'myInvoices|invoiceItem';

    /**
     * Synchronisiert beide DDs und installiert auf Wunsch Demo-Rechnungen.
     *
     * @param bool $seedDemo Demo-Daten ergänzen.
     * @return array Ergebnis mit Status und Zählern.
     */
    public function install(bool $seed_demo = true): array
    {
        $result = array(
            'ok' => 0,
            'schema' => array(),
            'created_invoices' => 0,
            'existing_invoices' => 0,
            'created_items' => 0,
            'message' => '',
        );

        $dd = dbx()->get_system_obj('dbxDD');
        foreach (array('invoice', 'invoiceItem') as $name) {
            $dd->sync_dd_to_db('myInvoices', $name, 'reset');
            $state = array();
            for ($step = 0; $step < 80; $step++) {
                $state = $dd->sync_dd_to_db(
                    'myInvoices',
                    $name,
                    'apply'
                );
                $status = (string)($state['status'] ?? '');
                if (in_array($status, array('finished', 'error'), true)) {
                    break;
                }
            }

            $result['schema'][$name] = (string)($state['status'] ?? '');
            if ($result['schema'][$name] !== 'finished') {
                $result['message'] =
                    'DD-Sync fehlgeschlagen: ' . $name . ' – '
                    . (string)($state['message'] ?? 'unbekannter Fehler');
                return $result;
            }
        }

        if (!$seed_demo) {
            $result['ok'] = 1;
            $result['message'] = 'Schema synchronisiert.';
            return $result;
        }

        $demo = array(
            array(
                'invoice_no' => 'DBX-DEMO-1001',
                'invoice_date' => '2026-07-01',
                'customer' => 'Beispielkunde Nord',
                'status' => 'open',
                'items' => array(
                    array(
                        'position_no' => 10,
                        'article_no' => 'ART-100',
                        'description' => 'Analysepaket',
                        'quantity' => '2.00',
                        'unit_price' => '19.90',
                    ),
                    array(
                        'position_no' => 20,
                        'article_no' => 'ART-210',
                        'description' => 'Dokumentation',
                        'quantity' => '1.00',
                        'unit_price' => '7.50',
                    ),
                ),
            ),
            array(
                'invoice_no' => 'DBX-DEMO-1002',
                'invoice_date' => '2026-07-08',
                'customer' => 'Beispielkunde Süd',
                'status' => 'paid',
                'items' => array(
                    array(
                        'position_no' => 10,
                        'article_no' => 'ART-310',
                        'description' => 'Modulprüfung',
                        'quantity' => '3.00',
                        'unit_price' => '12.00',
                    ),
                    array(
                        'position_no' => 20,
                        'article_no' => 'ART-420',
                        'description' => 'Integrationstest',
                        'quantity' => '1.50',
                        'unit_price' => '20.00',
                    ),
                ),
            ),
            array(
                'invoice_no' => 'DBX-DEMO-1003',
                'invoice_date' => '2026-07-15',
                'customer' => 'Beispielkunde West',
                'status' => 'draft',
                'items' => array(
                    array(
                        'position_no' => 10,
                        'article_no' => 'ART-510',
                        'description' => 'Referenzimplementierung',
                        'quantity' => '1.00',
                        'unit_price' => '119.00',
                    ),
                    array(
                        'position_no' => 20,
                        'article_no' => 'ART-610',
                        'description' => 'Browserprüfung',
                        'quantity' => '2.00',
                        'unit_price' => '5.25',
                    ),
                ),
            ),
        );

        $db = dbx()->get_system_obj('dbxDB');
        if ($db->begin(self::INVOICE_DD) !== 1) {
            $result['message'] = 'Fixture-Transaktion konnte nicht starten.';
            return $result;
        }

        try {
            foreach ($demo as $invoice) {
                $existing = $db->select1(
                    self::INVOICE_DD,
                    array('invoice_no' => $invoice['invoice_no'])
                );
                if ((int)($existing['id'] ?? 0) > 0) {
                    $result['existing_invoices']++;
                    continue;
                }

                $total_cents = 0;
                foreach ($invoice['items'] as $item) {
                    $total_cents += (int)round(
                        (float)$item['quantity']
                        * (float)$item['unit_price']
                        * 100
                    );
                }

                $ok = $db->insert(self::INVOICE_DD, array(
                    'invoice_no' => $invoice['invoice_no'],
                    'invoice_date' => $invoice['invoice_date'],
                    'customer' => $invoice['customer'],
                    'status' => $invoice['status'],
                    'total_gross' => number_format(
                        $total_cents / 100,
                        2,
                        '.',
                        ''
                    ),
                ));
                $invoice_id = (int)$db->_insert_id;
                if ($ok !== 1 || $invoice_id <= 0) {
                    throw new \RuntimeException(
                        'Rechnung konnte nicht angelegt werden: '
                        . $invoice['invoice_no']
                    );
                }
                $result['created_invoices']++;

                foreach ($invoice['items'] as $item) {
                    $ok = $db->insert(self::ITEM_DD, array(
                        'invoice_id' => $invoice_id,
                        'position_no' => $item['position_no'],
                        'article_no' => $item['article_no'],
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ));
                    if ($ok !== 1) {
                        throw new \RuntimeException(
                            'Position konnte nicht angelegt werden: '
                            . $item['article_no']
                        );
                    }
                    $result['created_items']++;
                }
            }

            if ($db->commit(self::INVOICE_DD) !== 1) {
                throw new \RuntimeException(
                    'Fixture-Transaktion konnte nicht abgeschlossen werden.'
                );
            }
        } catch (\Throwable $exception) {
            $db->rollback(self::INVOICE_DD);
            $result['message'] = $exception->getMessage();
            return $result;
        }

        $result['ok'] = 1;
        $result['message'] = 'Schema und Demo-Rechnungen sind bereit.';
        return $result;
    }
}

?>
