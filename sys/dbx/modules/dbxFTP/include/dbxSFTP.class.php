<?php
namespace dbx\dbxFTP;

include_once dbx_os_path_file(dbx_get_base_dir().'dbx/add_ons/sftp/vendor/autoload.php');



class dbxSFTP {

  private function get_file_info($file,$what) {
    // // 064-001_2024-02-07.ldt
    $retval='';
    if ($what == 'praxis')  $retval=substr($file, 0, 3); 
    if ($what == 'pat')     $retval=substr($file, 4, 3); 
    if ($what == 'dat')     $retval=substr($file, 8, 10); 
    
    return $retval;
  }  


  public function send_order() { 
    $content=''; 

    $host    ='home22904123.1and1-data.host';
    $username='acc241427790';
    $password='Bentox64!#Lda-sftp-2023';
    $port    = 22;

    $file_path  = dbx_get_base_dir().'files/myOrder/send-order/';
    $file_local = dbx_get_base_dir().'files/myOrder/send-order/test.txt';
    
    $files = scandir($file_path);
    $files = array_diff($files, array('.', '..'));   
    // Display the list of files
      



    
    $file_remote= 'test.send.txt';
     


    //use phpseclib3\Net\SFTP;

    $sftp = new \phpseclib3\Net\SFTP($host);
    $sftp->login($username, $password);
    // $sftp->put($file_remote, $content); to put content to the file


    //$file_local = dbx_get_base_dir().'files/myOrder/send-order/test.txt';
    //$file_remote= 'test.send.txt';

    foreach ($files as $file) {
        $file_local =dbx_get_base_dir().'files/myOrder/send-order/'.$file;
        $file_remote=$file;
        $ldt=1; // check ext = .ldt 
        if ($ldt) {
            
            $ok=$sftp->put($file_remote, $file_local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file
            $content.="put ($file) ok=($ok) <br>";
            if ($ok) {
                $pra=$this->get_file_info($file,'praxis');
                $pat=$this->get_file_info($file,'pat');
                $dat=$this->get_file_info($file,'dat'); 

                //$where='';
                //$ok=$oDB->save(); setze send datum 

                $content.="Praxis=($pra) Pat=($pat) Dat=($dat) save=($ok)<br>"; 
                

            }
        }
    }    


    //$ok=$sftp->put($file_remote, $file_local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE); // upload a file with the content of the file




    return $content;
  }

  public function run() {
     $work=dbx_get_ModulVar('dbx_work'); 
     

     $content='';

     switch ($work) {
       case 'send_order':
           $content=$this->send_order();
           break;

       default:
         $content="<div class='alert alert-warning' role='alert'>Modul=($modul) Inc=(dbxSFTP) Work=($work) is undef.</div>";
     } // switch
     return $content;
  } // run()

} // class

?>