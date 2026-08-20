<?php

/**
 * Repräsentiert eine fehlgeschlagene Datenbankverbindung zu einer DD-Quelle.
 */
class dbxDatabaseError{
    public $_db_message ='no connection to db';


    function __construct($data_dic,$err='') {
        $msg="no connection to db for dd ($data_dic). ($err)";
        $this->_db_message=$msg;
    }    
} 

?>
