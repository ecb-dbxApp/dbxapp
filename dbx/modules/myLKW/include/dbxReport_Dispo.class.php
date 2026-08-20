<?php

declare(strict_types=1);

namespace dbx\myLKW;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Dispo extends \dbxReport
{
    public function run_body($content)
    {
        $record = $this->_record;
        $record['observacion'] = str_repeat('&nbsp;', 10);
        $record['d2_carga_lugar'] = substr($record['d2_carga_lugar'], 0, 10);
        $record['d2_observaciones'] = substr($record['d2_observaciones'], 0, 7);
        $this->_class_body['TIPO'] = $record['TIPO'];
        $this->_class_body['d2_carga_region'] = strtoupper(
            substr($record['d2_carga_region'], 0, 1)
        );
        $record['TIPO'] = str_replace('-', '&#8209;', $record['TIPO']);
        $this->_record = $record;
        return $this->forward_run_body($content);
    }
}
