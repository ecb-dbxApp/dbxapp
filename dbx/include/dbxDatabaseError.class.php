<?php

class dbxDatabaseError{
    public $_dbMessage ='no connection to db';


    function __construct($dataDic,$err='') {
        $msg="no connection to db for dd ($dataDic). ($err)";
        $this->_dbMessage=$msg;
    }    
} 

?>