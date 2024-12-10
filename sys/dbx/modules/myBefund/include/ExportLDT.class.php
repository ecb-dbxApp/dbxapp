<?php
namespace dbx\myBefund;
dbx_get_sys_object('dbxReport','use');


class ExportLDT {

   public $db;
   public $ldt_length=0; // Länge LDT Satz;
   public $_lenpos   =0;
   public $_length   =array();

   public $_pat =0;
   public $_arzt=0;
   public $_praxis=array();

   function __construct() {
      $this->db=dbx_get_sys_object('dbxDB');
      $this->ldt_length=0;
      $this->_length=array();
      $this->_lenpos=0;
      $this->_pat=0;
      $this->_arzt=0;
      $this->_praxis=array();
   }


   private function check_length($ldt,$var,$plus=0) {
      $length=strlen($ldt);
      $size  =strlen($var);
      $format="%0".$size."d";
      $xlang =sprintf($format,($length+$plus));
      $ldt   =str_replace($var, $xlang, $ldt);
      return $ldt;
   }


   private function add_ldt_fld($fld,$value,$default='') {
       $ldtfld = "";
       //$value = iconv("UTF-8", "Windows-1252//TRANSLIT", $value);
       $value=str_replace("÷", "ö", $value);
       $value=str_replace("³", "ü", $value);
       $value=str_replace("#br#", " ", $value);
       $value=str_replace("#BR#", " ", $value);


       $value=iconv("UTF-8", "CP437//TRANSLIT", $value); // Zeichensatz DOS IBM 
       //$value = iconv("ISO-8859-1", "cp437", $value); // value nicht utf8


       $value  = trim($value);
       //if ($value == "#BR#") $value='';
       //$value=str_replace("#BR#", "/", $value);
       if ($value=='') $value = $default;


       if ($value >"") {
         $ldt_length=$this->ldt_length;
         $length = (strlen($value)+3 + 4 + 2);
         $ldt_length=($ldt_length+$length);
         $this->ldt_length=$ldt_length;
         $xlang =sprintf("%03d", $length);
         //$ldtfld=$xlang."-".$fld."-".$value."\n\r<br>";
         $ldtfld=$xlang.$fld.$value."\r\n";
       }
       if ($fld=="8100" || $fld=="9202") {
          $pos=$this->_lenpos;
          $pos=($pos+1);
          $this->_lenpos=$pos;
       }
       $pos=$this->_lenpos;
       $len=$this->_length;
       if (!isset($len[$pos])) $len[$pos]=0;
       $len[$pos]=($len[$pos]+$length);
       $this->_length=$len;

       return $ldtfld;
    }



   private function get_ldt_haeder_disk() {
      $ldt="";
      $ldt.=$this->add_ldt_fld("8000","0020");
      $ldt.=$this->add_ldt_fld("8100","#SL1#");   // Disk-Satz-Länge  (L=5)
      $ldt.=$this->add_ldt_fld("9105","001");     // Ordnungsnummer des Datenträgers
      return $ldt;
   }


   private function get_ldt_footer_disk() {
      $ldt="";
      $ldt.=@$this->add_ldt_fld("8000","8221");      // 80008221
      $ldt.=@$this->add_ldt_fld("8100","00044");
      $ldt.=@$this->add_ldt_fld("9202","#SLANGE#");  // Disk-Satz-Länge  (L=6)
      $ldt.=@$this->add_ldt_fld("8000","0021");
      $ldt.=@$this->add_ldt_fld("8100","00027");     // Disk-Satz-Länge  (L=6)
      return $ldt;
   }


   private function get_ldt_haeder_DP($befund) { // Daten-Paket Haeder

      $arzt  =$this->_arzt;
      $praxis=$this->_praxis;

      //if (!isset($praxis['BSNR'])) return "Praxis ($arzt) nicht als Benutzer vorhanen !";

      //dbx_debug("##PRAXIS Arzt=($arzt)=",$praxis);

      $ldt="";

      $ldt.=@$this->add_ldt_fld("8000","8220");      // Satzart L-Datenpaket Haeder
      $ldt.=@$this->add_ldt_fld("8100","#SL2#");     // Satz-Länge  (L=6)
      $ldt.=@$this->add_ldt_fld("9212","LDT1001.01"); // Version

      $ldt.=@$this->add_ldt_fld("0201",$befund['bsnr']        ,$praxis['BSNR']); // (L=10)
      $ldt.=@$this->add_ldt_fld("0203",$befund['bsnrb']       ,"-bsnrb-");
      $ldt.=@$this->add_ldt_fld("0212",$befund['lanr']        ,$praxis['LANR']);
      $ldt.=@$this->add_ldt_fld("0211",$befund['arztname']    ,$praxis['name']);
      $ldt.=@$this->add_ldt_fld("0205",$befund['bsnr_strasse'],$praxis['strasse']);
      $ldt.=@$this->add_ldt_fld("0215",$befund['bsnr_plz']    ,$praxis['plz']);
      $ldt.=@$this->add_ldt_fld("0216",$befund['bsnr_ort']    ,$praxis['ort']);

      $ldt.=@$this->add_ldt_fld("8300",$befund['labor']        ,"Riegel"); // Labor Regel 425
      $ldt.=@$this->add_ldt_fld("8320",$befund['labor_name']   ,"Riegel");
      $ldt.=@$this->add_ldt_fld("8321",$befund['labor_strasse'],"Kreuzberger-Ring 60");
      $ldt.=@$this->add_ldt_fld("8322",$befund['labor_plz']    ,"65205"); // PLZ Labor
      $ldt.=@$this->add_ldt_fld("8323",$befund['labor_ort']    ,"WI-Erbenheim");

      $ldt.=@$this->add_ldt_fld("0101","X/38/0805/36/830");
      $ldt.=@$this->add_ldt_fld("9106","2"); //ISO 8859-15 Code
      $ldt.=@$this->add_ldt_fld("8312",$befund['arzt'],$arzt);
      $ldt.=@$this->add_ldt_fld("9103",$befund['erstellt']);


      //$ldt.=@$this->add_ldt_fld("9472",$befund['information'],"-information-"); // k-Text Informationen
      //$ldt.=@$this->add_ldt_fld("8300",$befund['signatur'],"-sigbatur-");    // k
      //$ldt.=@$this->add_ldt_fld("9301",$befund['krypto'],"-krypto-");      // k
      return $ldt;
   }


   private function get_ldt_haeder_LG() {
      $ldt="";
      //$ldt.=$this->add_ldt_fld("8000","8201");       // Satzart LG-Bericht "8202"  // Labor Bericht=8201
      //$ldt.=$this->add_ldt_fld("8100","#SL3#");      // Satz-Länge  (L=6)
      return $ldt;
   }


   private function get_ldt_footer() {
      $ldt="";
      $ldt.=$this->add_ldt_fld("8000","8221");    // Satzart L_Datenpaket Abschluss
      $ldt.=$this->add_ldt_fld("8100","#S2A#");   // Satz-Länge  Summe (L=6)
      $ldt.=$this->add_ldt_fld("9202","#P2A#");   // Paket-Länge Summe
      return $ldt;
   }


   private function make_ldt_date($date) {
       $yyyy=substr($date,0,4);
       $mm  =substr($date,5,2);
       $dd  =substr($date,8,2);
       $out=$dd.$mm.$yyyy;
       if ($out=="00000000") $out="";
       return $out;
   }



   private function get_ldt_footer_PA($befund) {
      $ldt=""; 
      $bemerkung = $befund['bemerkung'];
      $bemerkung = rtrim($bemerkung, '#BR#');
      // Teilt den String in ein Array anhand von '#BR#'
      $bems = explode('#BR#', $bemerkung);     
      foreach ($bems as $no => $bem) {
         $ldt.=@$this->add_ldt_fld("8490",$bem);     // Patient
      }
      return $ldt;
   }

   private function get_ldt_haeder_PA($befund) {
      $now  = dbx_DateTime();
      $date = substr($now,0,10);
      $time = substr($now,12,5);

      $Vname =$befund['patvorname'];
      $Nname =$befund['patname'];
      $gebDat=$befund['gebdat'];
      $befDat=$befund['datum'];
      $sex    =$befund['sex'];

      $date  =$this->make_ldt_date($date);
      $gebDat=$this->make_ldt_date($gebDat);
      $befDat=$this->make_ldt_date($befDat);

      $xsex=$befund['sex'];
      $sex=1;
      if ($xsex=='Frau') $sex=2;
      if ($xsex=='Kind') $sex=3;
      if ($xsex=='W')    $sex=2;
      if ($xsex=='F')    $sex=2;
      if ($xsex=='K')    $sex=3;


      $ldt="";
      $ldt.=@$this->add_ldt_fld("8000","8201");
      //$ldt.=@$this->add_ldt_fld("8000",$befund['befundtypt'],"8202");    // Satzart: LG-Bericht
      $ldt.=@$this->add_ldt_fld("8100","#SL3#");     //

      $ldt.=@$this->add_ldt_fld("8310",$befund['pat'],"-pat-");     // Patient
      $ldt.=@$this->add_ldt_fld("8311",$befund['tagesnummer'],"-TN-");
      $ldt.=@$this->add_ldt_fld("8301",$befDat,"-eingang-datum-");
      $ldt.=@$this->add_ldt_fld("8302",$date);  // bericht-datum (heute)
      //$ldt.=@$this->add_ldt_fld("8302",$time);  // bericht-zeit  (jetzt)
      $ldt.=@$this->add_ldt_fld("3101",$Nname,"-unbekannt-");
      $ldt.=@$this->add_ldt_fld("3102",$Vname,"-unbekannt-");
      $ldt.=@$this->add_ldt_fld("3103",$gebDat,"01011900");
      $ldt.=@$this->add_ldt_fld("3104",$sex);
      $ldt.=@$this->add_ldt_fld("8401",$befund['befundart'],"-BefundArt-");
      $ldt.=@$this->add_ldt_fld("8609",$befund['abrechnungstyp'],"-AbrechnungsTyp-");
      $ldt.=@$this->add_ldt_fld("8615",$befund['versicherungsnr'],"V-NR");
      
     
      $ldt.=@$this->add_ldt_fld("8403",$befund['geor'],1);
      //$ldt.=@$this->add_ldt_fld("8401",$befund['PatInfo'],"-PatInfo-");
      $ldt.=@$this->add_ldt_fld("8407",$sex,1);   // ?????????????????????????
      return $ldt;
   }



   private function get_ldt_befund($id) {
      $befund=$this->db->select1('dbx_my_befund',$id);
      if (is_array($befund)) {
        $praxis=$befund['arzt'];
        $where ="userid = $praxis";
        $this->_pat =$befund['pat'];
        $this->_arzt=$befund['arzt'];
        
        $prax=$this->db->select1('dbx_user',$where);
        if (!is_array($prax)) {
         $where ="userid = 1";
         $prax=$this->db->select1('dbx_user',$where);
        } 

        $this->_praxis=$prax;
      } 
      //dbx_debug("#Befund ($id)=",$befund);
      return $befund;
   }


   public function run() {
      $ldt=''; $ldt_LG ='';  $ok=0; $haeder=0; $leninfo='';

      

      $do       =dbx_get_ModulVar('dbx_do','selected');
      $praxis   =dbx_get_cfg('myOrderLDT','praxis');
      $path_file=dbx_get_cfg('myOrderLDT','path_medisoft');
      $oTPL     =dbx_get_sys_object('dbxTPL');

      $multipages = dbx_get_Remember('multi_ldt_ids');

      $msg['msg']="Export Befunde für Praxis ($praxis)";
      $content=$oTPL->get_tpl('dbx','alert-primary',$msg);






      if (is_array($multipages)) {
         foreach($multipages as $id => $nox) {
            if (is_array($nox)) $id=$id['id'];// ID vom Befund
            $befund_rec =$this->get_ldt_befund($id);
               if (is_array($befund_rec)) {

               $pat  =$befund_rec['pat'];
               $datum=dbx_get_webDate($befund_rec['datum']); //###################
               

               $msg['msg']="Export Befund ($praxis - $pat - $datum)";
               $content.=$oTPL->get_tpl('dbx','alert-success',$msg);


               if (!$haeder) {
                  $ldt.=$this->get_ldt_haeder_disk();
                  $ldt.=$this->get_ldt_haeder_DP($befund_rec);
                  $ldt.=$this->get_ldt_haeder_LG();
                  $haeder=1;
               }
   
               
               $ldt.=$this->befund_ldt($id,0);
   
               $pos=$this->_lenpos;
               $len=$this->_length;
               $length=$len[$pos];
               $leninfo.="LENPOS=$pos\r\n";
   
   
               for ($i = 1; $i <= $pos; $i++) {
                  $leninfo.="LEN $i=".$len[$i]."\r\n";
                  $rep=sprintf("%05d", $len[$i]);
                  $org="#SL".$i."#";
                  $ldt=str_replace($org, $rep, $ldt);
               }
               //$ldt.=$leninfo;
               $this->_lenpos=2;
               $len[3]=0;
               $this->_length=$len;
   
               $field_values=array();
               $oDD=dbx_get_sys_object('dbxDB');
               $field_values['id']= $id;
               $field_values['ldt']=1;
               $where = "id = $id";
               $ok=$oDD->update('dbx_my_befund',$field_values,$where,0,0,0,0);


             //$ldt.=$this->get_ldt_footer();
            
            }
         }
         $ArztNr=sprintf("%03d", $praxis);
         $ldt.=$this->get_ldt_footer_disk();

         // - Codierung - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
         //$ldt="Test-äöü-ÄÖÜ-";
         //$ldt = iconv("UTF-8", "CP437//TRANSLIT", $ldt);
         //$ldt = iconv("UTF-8", "Windows-1252//TRANSLIT", $ldt);
         //$ldt = iconv("Windows-1252", "CP850//TRANSLIT", $ldt);


         // - - - - - - - - - - - - - - - - -




         //$ldt = iconv("UTF-8", "CP437//TRANSLIT", $ldt); // Zeichensatz DOS IBM 
         $ldt = $this->check_length($ldt,"#SLANGE#",-27);

   
         dbx_debug("## Export LDT file=($path_file) ");

         file_put_contents($path_file, $ldt);
   
 
      
      } 
      return $content;
   }


   public function befund_ldt($id,$out=1) {
      global $dbx_config;
      $ldt=""; $ldt_LG =""; $fax="";  $ok=0; $haeder=0;
      $befund_rec =$this->get_ldt_befund($id);

      if (is_array($befund_rec)) {
        if ($out) $ldt.=$this->get_ldt_haeder_disk();
        if ($out) $ldt.=$this->get_ldt_haeder_DP($befund_rec);
        if ($out) $ldt.=$this->get_ldt_haeder_LG();
        $ldt.=$this->get_ldt_haeder_PA($befund_rec);
        $ldt.=$this->get_ldt($id);    // Analysen vom Befund
        $ldt.=$this->get_ldt_footer_PA($befund_rec);
        if ($out) $ldt.=$this->get_ldt_footer_disk();

        // --------------------------------------------
        $leninfo="";
        $pos=$this->_lenpos;
        $len=$this->_length;
        $length=$len[$pos];
        $leninfo.="LENPOS=$pos\r\n";

        if ($out) {
          for ($i = 1; $i <= $pos; $i++) {
              $leninfo.="LEN $i=".$len[$i]."\r\n";
              $rep=sprintf("%05d", $len[$i]);
              $org="#SL".$i."#";
              $ldt=str_replace($org, $rep, $ldt);
          }

          $ldt=$this->check_length($ldt,"#SLANGE#",-27);
          // ----------------------------------------------
          // $ldt.=$leninfo;
        }
      }
      return $ldt;
  }






   private function get_ldt($id) {
       $ldt="";
       $befund=$this->db->select1("dbx_my_befund",$id);
       if (is_array($befund)) {
          // $ldt.=$this->get_patient($befund)
          $analysen=$this->db->select("dbx_my_analyse","befund_id = $id");
          if (is_array($analysen)) {
             foreach($analysen as $no => $analyse) {
                 //$ldt.="Analyse<br>";

                 $ldt.=@$this->add_ldt_fld("8410",$analyse['testident']);
                 $ldt.=@$this->add_ldt_fld("8411",$analyse['testbez']);
                 $ldt.=@$this->add_ldt_fld("5001",$analyse['gnr']);
                 $ldt.=@$this->add_ldt_fld("8406",$analyse['cent']);
                 $ldt.=@$this->add_ldt_fld("5009",$analyse['freitext']);
                 //$ldt.=@$this->add_ldt_fld("8406",$analyse['cent']);
                 if ($analyse['abrechner'] > 0) {
                    $ldt.=@$this->add_ldt_fld("8614",$analyse['abrechner']);
                 }
                 $ldt.=@$this->add_ldt_fld("8418",$analyse['status']);


                 $ldt.=@$this->add_ldt_fld("8420",$analyse['ergebnis']);
                 $ldt.=@$this->add_ldt_fld("8421",$analyse['einheiten']);
                 $ldt.=@$this->add_ldt_fld("8480",$analyse['ergtext']);
                 $ldt.=@$this->add_ldt_fld("8470",$analyse['bemerkung']);
                 $ldt.=@$this->add_ldt_fld("8460",$analyse['nwtxt'],'0');
                 $ldt.=@$this->add_ldt_fld("8461",$analyse['nwug'],"0");
                 $ldt.=@$this->add_ldt_fld("8462",$analyse['nwog'],"0");


             }
          }
       }
       return $ldt;
   }

  public function get_ldtx($id,$out=1) {
    global $dbx_config;
    $ldt=""; $ldt_LG =""; $fax="";  $ok=0; $haeder=0;
    $befund_rec =$this->get_ldt_befund($id);

    if (is_array($befund_rec)) {
      if ($out) $ldt.=$this->get_ldt_haeder_disk();
      if ($out) $ldt.=$this->get_ldt_haeder_DP($befund_rec);
      if ($out) $ldt.=$this->get_ldt_haeder_LG();
      $ldt.=$this->get_ldt_haeder_PA($befund_rec);
      $ldt.=$this->get_ldt($id);    // Analysen vom Befund
      $ldt.=$this->get_ldt_footer_PA($befund_rec);
      if ($out) $ldt.=$this->get_ldt_footer_disk();

      // --------------------------------------------
      $leninfo="";
      $pos=$this->_lenpos;
      $len=$this->_length;
      $length=$len[$pos];
      $leninfo.="LENPOS=$pos\r\n";

      if ($out) {
        for ($i = 1; $i <= $pos; $i++) {
            $leninfo.="LEN $i=".$len[$i]."\r\n";
            $rep=sprintf("%05d", $len[$i]);
            $org="#SL".$i."#";
            $ldt=str_replace($org, $rep, $ldt);
        }

        $ldt=$this->check_length($ldt,"#SLANGE#",-27);
        // ----------------------------------------------
      }
    }
    return $ldt;

  }




  private function insert_string($intostring, $insertstring,$offset) {
     $part1 = substr($intostring, 0, $offset);
     $part2 = substr($intostring, $offset);
     $part1 = $part1 . $insertstring;
     $whole = $part1 . $part2;

     return $whole;
  }
} // Class
