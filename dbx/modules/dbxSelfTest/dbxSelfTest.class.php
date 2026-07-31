<?php
namespace dbx\dbxSelfTest;

/** Administrativer Einstieg fuer vollstaendige und einzelne Systemtests. */
class dbxSelfTest
{
    public function run()
    {
        $controller = dbx()->get_include_obj('dbxSelfTestController', 'dbxSelfTest');
        return is_object($controller) ? $controller->run() : '';
    }
}
