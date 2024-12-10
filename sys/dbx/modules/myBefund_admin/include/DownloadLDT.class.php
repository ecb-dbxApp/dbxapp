<?php
namespace dbx\myBefund_admin;




class DownloadLDT {

   private function decrypt($path_file,$file) {
      $ok=0; 
      $path_file=dbx_os_path_file($path_file);
      dbx_debug("decrypt=($path_file)"); 
      if (file_exists($path_file)) {
         $decryt_file=str_replace('.crypt','',$path_file);
         $crypt_content   = file_get_contents($path_file);
         $decrypt_content = dbx_decrypt($crypt_content,$file);
         file_put_contents($decryt_file, $decrypt_content);
         
         unlink($path_file);   
         $ok=1;
      }
      return $ok;
   }
   

   public function run() {
      $count=0;
      $praxis=dbx_get_cfg('myOrderLDT','praxis');
      $praxis=sprintf("%03d", $praxis);

      $host=dbx_get_cfg('dbx','sftp_host');
      $user=dbx_get_cfg('dbx','sftp_user');
      $pass=dbx_get_cfg('dbx','sftp_pass');
      $port=dbx_get_cfg('dbx','sftp_port');
  
      $path=dbx_get_file_dir().'myBefund/ldt-in/';


      dbx_debug("#SFTP# Login (myBefund->DownloadLDT->run)");

      $attempts = 0;
      $maxRetries =7;
      $connected = false;
      
      $files= null;        

      while ($attempts < $maxRetries && !$connected) {
          try {
              dbx_debug("#SFTP# Try connect ($attempts)"); 
              $sftp = null;
              $sftp = new \phpseclib3\Net\SFTP($host);
              if (!$sftp->login($user, $pass)) {
                  throw new \RuntimeException('Login failed');
              }
              $sftp->chdir('/befund');
              $files = $sftp->nlist();
              $connected = true; // Verbindung erfolgreich
          } catch (\Exception $e) {
              $attempts++;
              $sftp = null; // Verbindung zurücksetzen
              if ($attempts >= $maxRetries) {
                  throw new \RuntimeException('Error reading from socket after ' . $maxRetries . ' attempts: ' . $e->getMessage());
              }
          }
      }


      dbx_debug("#SFTP# SERVER FILES Praxis=($praxis)",$files); 

      if (is_array($files)) {
         foreach ($files as $file) {
         $prax=substr($file,0,3);
         //dbx_debug("prax=($prax)");
         if ($praxis == $prax) {
               
               $count++;
               $file_remote=$file;
               $file_local =$path.$file;
               dbx_debug("Download ($file_remote) -> ($file_local)");


               $sftp->get($file_remote,$file_local); 
               dbx_debug("get file");


               $ok=$this->decrypt($file_local,$file);
               dbx_debug("decrypt ok=($ok)");

               if ($ok) $sftp->delete($file_remote); // für test ausschalten  
               dbx_debug("delete ok=($ok)");
               
         } 
         } 
         dbx_debug("download count=($count)");
      }
      return $count;     
   }

}    