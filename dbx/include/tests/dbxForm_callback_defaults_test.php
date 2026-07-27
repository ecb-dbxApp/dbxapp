<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$fail = static function (string $message, int $code): void {
    fwrite(STDERR, "FAIL: $message\n");
    exit($code);
};

dbx()->get_system_obj('dbxForm', 'load');
dbx()->get_system_obj('dbxReport', 'load');

class dbxFormCallbackDefaultsOwner
{
    public function createForm(): dbxForm
    {
        $form = new dbxForm();
        $form->init('callback-default-form', 'callback-default-form');
        return $form;
    }
}

$owner = new dbxFormCallbackDefaultsOwner();
$form = $owner->createForm();
$ownerProperty = (new ReflectionClass('dbxObj'))->getProperty(
    '_callback_owner'
);
$ownerProperty->setAccessible(true);

if ($ownerProperty->getValue($form) !== $owner) {
    $fail('dbxForm uebernimmt den direkten Aufrufer nicht als Owner.', 1);
}

$form->add_rep('total', '12,30 EUR');
if ($form->replaces('Summe: {total}') !== 'Summe: 12,30 EUR') {
    $fail('Spaete dbxForm-Replacements werden nicht angewendet.', 2);
}
if ($form->replaces('{value}', array('value' => 'explizit')) !== 'explizit') {
    $fail('Explizite dbxForm-Replacements werden nicht angewendet.', 3);
}

$report = new dbxReport();
$report->_table_col_count = 6;
$report->add_rep('total', '47,30 EUR');
$footer = $report->rpt_merge_obj(
    '<th colspan="{rpt:colspan}">{total}</th>'
);
if ($footer !== '<th colspan="5">47,30 EUR</th>') {
    $fail('dbxReport erbt Replacements oder Standard-Colspan nicht.', 4);
}

echo "OK dbxForm callback defaults and report replacements\n";
