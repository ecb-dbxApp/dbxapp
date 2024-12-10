<?php
namespace dbx\dbxTest;

dbx_use_sys_class('dbxDataDictonary2');

dbx_use_sys_class('dbxDB');
dbx_use_sys_class('dbxDD');


class dbxTest {

  private function test() {
    $old = '1964-02-06 12:30:45';

    $now   = dbx_DateTime('now');
    $later = dbx_DateTime('now',60);
    $from  = dbx_DateTime($old);
    $from2 = dbx_DateTime($old,60);
    $from3 = dbx_DateTime($old,0,'last day of this month');
    $from4 = dbx_DateTime($old,0,'first day of January');

    $content="Now=($now) later=($later) From Date=($from) <br>From Date2=($from2) Date3=($from3) Date4=($from4)<hr>";

    

    $dd=new \dbxDD; 
    $db=new \dbxDB; 
    /*
    //$db->connect_tab('dbx_my_testdata'); 
    //$data=$db->select('dbx_my_testdata');

    $data=$db->select1('dbx_my_testdata','id > 100');
    //dbx_debug("#DATA-1 'dbx_my_testdata=",$data);
    $count=$db->get_CountSelected('dbx_my_testdata','id > 100');


    $msg=$db->dbMessage;

    $content.="<br>dbx_my_testdata db-Message=($msg) Count=($count)<br>";



    $data=$db->select1('dbx_my_testdata2','id > 100');
    //dbx_debug("#DATA-2 'dbx_my_testdata2=",$data);
    $count=$db->get_CountSelected('dbx_my_testdata2','id > 100');


    $msg=$db->dbMessage;

    $content.="<br>dbx_my_testdata2 db-Message=($msg) Count=($count)<br>";


    


    //$content.= print_r($data);
    $da1=$db->get_table_exist('dbx_my_testdata');
    $da2=$db->get_table_exist('dbx_my_testdata2');
    $da3=$db->get_table_exist('dbx_session');

    $content.="<br> Table 'dbx_my_testdata'=($da1)"; 
    $content.="<br> Table 'dbx_my_testdata2'=($da2)"; //  'dbx_session'=($da3)";
    $content.="<br> Table 'dbx_session'=($da3)"; //  'dbx_session'=($da3)";
   
    
    $dd='dbx_my_testdata';
    $field_values['sex']='3';
    $field_values['nachname']='Braun';
    $field_values['vorname'] ='Armin';
    $field_values['gebdat']  ='1964-04-06';





    $ok       =$db->insert($dd,$field_values); // ,$verify_access=1,$verify_fields=1,$verify_values=1,$trace=1) {
    $insert_id=$db->_insert_id;
    $content.="<br>INSERT DD=($dd) ok=($ok) Insert-Id=($insert_id)"; //  
 
    
    $field_values['vorname'] ='Armin  Leonard';
    $where=350237; // id of record to update 

    $ok       =$db->update($dd,$field_values,$where);
    $affected =$db->_update_count;
    $content.="<br>Update DD=($dd) ok=($ok) Affected=($affected)"; //  
   
    $field_values['nachname']='BRAUN';
    $ok       =$db->save($dd,$field_values,$where);
    $affected =$db->_update_count;
    $content.="<br>Save DD=($dd) ok=($ok) Affected=($affected)"; //  
    

    $tabeles1=$db->get_db_tables('dbXmodule');
    $content.='<br>Tablels of (dbXmodule)=';
    $content.=print_r($tables1,true);


    $tabeles2=$db->get_db_tables('dbXmysql');
    $content.='<br>Tablels of (dbXmysql)=';
    $content.=print_r($tables2,true);

    $tabels3=$db->get_db_tables('dbXmysql');
    $content.='<br>Tablels of (medistar)=';
    $content.=print_r($tabels3,true);

    $dds=$db->get_dd_tables();
    $dbs=$db->get_db_tables('dbXsystem');
    dbx_debug("DDs=",$dds); 
    dbx_debug("dbs=",$dbs);

    $fields=$db->get_dd_fields('lda_methoden');
    dbx_debug("DD-Fields of (lda_methoden)",$fields);
    
    $fields=$db->get_db_fields('dbXmysql','dbx_de_content');
    dbx_debug("DB-Fields of (dbx_de_content)",$fields);
    

    $sync=$dd->get_dd_sync('dbx_my_testdata');
    $content="dbx_my_testdata sync=($sync)"; 

*/
    $content="<h1>Export/Import</h1>";

    //$content.='[modul=dbxAdmin]dbx_action=datadic&dbx_work=export_csv&dd=dbx_my_testdata[/modul]<br>';

    $content.='[modul=dbxAdmin]dbx_action=datadic&dbx_work=import_csv&dd=dbx_my_testdata[/modul]<br>';

   // $content.='[modul=dbxAdmin]dbx_action=datadic&dbx_work=export_csv&dd=kv_kassen[/modul]<br>';

    return $content;
  }



  public function run() {
     $uid   =dbx_get_CurrentUser();
     $mid   =dbx_get_sysVar('dbx_modul_id');
     $modul =dbx_get_SysVar('dbx_modul');

     $action=dbx_get_ModulVar('dbx_action');

     switch ($action) {
        case 'test':
           $content=$this->test();
        break;


       default:
         $oTPL=dbx_get_sys_object('dbxTPL');
         $msg['msg']="Modul=($modul) Action=($action) is undef.";
         $content=$oTPL->get_tpl('dbx','alert-warning',$msg);

     } // sqitch()
     
     return $content;
   } 
   
   
} // class

?>