<?php
namespace dbx\myOrderLDT;


class LDT {

   public $_ldt = '';
   public $output_file='';
   public $ldt_length=0; // Länge LDT Satz;
   public $_lenpos   =0;
   public $_length   =array();


   function __construct() {
      $this->_ldt='';
      $this->ldt_length=0;

      $this->_lenpos=0;
      $this->_length=0;
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
        $output_file=$this->output_file;    
        $ldtfld = '';
        if ($value==null) $value='';
        $value=trim($value);
        if ($value == '') $value=$default;
        if ($value >  '') {
           //$org=$value;
 
           $ldt_length=$this->ldt_length;
           $length = (strlen($value)+3 + 4 + 2);
           $ldt_length=($ldt_length+$length);
           $this->ldt_length=$ldt_length;
           $xlang =sprintf("%03d", $length);
           $ldtfld=$xlang.$fld.$value."\r\n";
           $this->_ldt.=$ldtfld;
       }       
    }

  

   private function get_first_char($chars) {
      $char=(substr($chars,0,1));
      return $char;
   }



   
   private function get_ldt_date($date) {
       $yyyy='0000'; $mm='00'; $dd='00';
       if (dbx_strpos($date,'.')) {
        $yyyy=substr($date,6,4);
        $mm  =substr($date,3,2);
        $dd  =substr($date,0,2);
       } else {
        if ($date > '') { 
         $yyyy=substr($date,0,4);
         $mm  =substr($date,5,2);
         $dd  =substr($date,8,2);
        }
       }
       $out=$yyyy.$mm.$dd;

       if ($yyyy < '1900') {
         $yyyy='19'.substr($yyyy,2,2);
       }
       if ($out=="00000000") $out='';
       return $out;
   }



  public function get_ldt_anforderung($order) {
      $this->_ldt='';

      $arzt_name  ='Dr. X';

      $praxis_name='Praxis';
      $praxis_str ='Feldweg';
      $praxis_plz ='64297';
      $praxis_ort ='Darmstadt';

      $labor_name='Laborgemeinschaft Darmstadt';
      $labor_str ='Grüner Weg 18';
      $labor_plz ='64285';
      $labor_ort ='Darmstadt';

      $kbv_prnr  = 'X9907830';  // a/nn/JJMM/MM/aaa
      $ldt_zeichensatz='3';     // ISO 8859-1 Code
      $ldt_datum = $this->get_ldt_date($order['datum']);

      
      $pat_titel  ='' ; // 
      $pat_geb    = $this->get_ldt_date($order['gebdat']);
      $pat_str    = $order['strasse'];
      $pat_str_nr = '';
      $pat_lnd    = 'DE';
      $pat_sex    = $order['geschlecht'];
      dbx_debug("ORDER PAT sex=($pat_sex)");
       
      if ($pat_sex == 'W') $pat_sex='F';





      $ver_status = $order['status'];
      $ver_einlese=$this->get_ldt_date($order['datum']);

      $pat_art=$order['pk'];   // 'K' oder 'P'  kasse/privat 
      $kurativ=$order['kurativ'];
      $praev  =$order['praeventiv'];
      $beleg  =$order['belegarzt'];
      $unfall =$order['unfall'];

      $kurativ_unfall=$kurativ;

      if ($kurativ) $kurativ_unfall=1;
  
      if ($beleg)   $kurativ_unfall=4;


      if ($praev)   $kurativ_unfall=2;
      if ($unfall)  $kurativ_unfall=3;


      $this->add_ldt_fld('8000','0020');
      $this->add_ldt_fld('8100','9999');
      $this->add_ldt_fld('9105','001');
      $this->add_ldt_fld('8000','8230');
      $this->add_ldt_fld('8100','8888');
      $this->add_ldt_fld('9212','LDT1014.01');
      $this->add_ldt_fld('0201',$order['bsnr']);
      $this->add_ldt_fld('0203',$praxis_name);
      $this->add_ldt_fld('0212',$order['lanr']);
      $this->add_ldt_fld('0211',$arzt_name);
      $this->add_ldt_fld('0205',$praxis_str);
      $this->add_ldt_fld('0215',$praxis_plz);
      $this->add_ldt_fld('0216',$praxis_ort);
      $this->add_ldt_fld('8320',$labor_name);
      $this->add_ldt_fld('8321',$labor_str);
      $this->add_ldt_fld('8322',$labor_plz);      
      $this->add_ldt_fld('8323',$labor_ort);
      $this->add_ldt_fld('0101',$kbv_prnr);
      $this->add_ldt_fld('9106',$ldt_zeichensatz);
      $this->add_ldt_fld('8312',$order['praxis']);
      $this->add_ldt_fld('9103',$ldt_datum);
      $this->add_ldt_fld('8000','8218'); 
      $this->add_ldt_fld('8100','7777');
      $this->add_ldt_fld('8310',$order['pat']);
      $this->add_ldt_fld('8609',$pat_art);
      $this->add_ldt_fld('3100',$pat_titel);

      $this->add_ldt_fld('3101',$order['nachname']);
      $this->add_ldt_fld('3102',$order['vorname']);
      $this->add_ldt_fld('3103',$pat_geb);
      $this->add_ldt_fld('3104',$pat_titel);
      $this->add_ldt_fld('3105',$order['versicherungsnr']);
      $this->add_ldt_fld('3107',$pat_str);
      $this->add_ldt_fld('3109',$pat_str_nr);
      $this->add_ldt_fld('3112',$order['plz']);
      $this->add_ldt_fld('3114',$pat_lnd);
      $this->add_ldt_fld('3113',$order['ort']);
      $this->add_ldt_fld('3108',$ver_status);
      $this->add_ldt_fld('3110',$pat_sex);

      $this->add_ldt_fld('2002',$order['krankenkasse']);
      $this->add_ldt_fld('4104',$order['kostentraeger']);
      $this->add_ldt_fld('4106',$order['abrechbereich']);
      $this->add_ldt_fld('4109',$ver_einlese);
      $this->add_ldt_fld('4110',''); // Ende Versicherung Datum
      $this->add_ldt_fld('4111',$order['kostentraeger']);

      $this->add_ldt_fld('4112',$order['status']);

      $this->add_ldt_fld('4221',$kurativ_unfall);
                         
      $this->add_ldt_fld('4242',$order['lanr']);
      $this->add_ldt_fld('4218',$order['bsnr']);

      $this->add_ldt_fld('8219',$order['abdatum']);
      $this->add_ldt_fld('8220',$order['abzeit']);
    

      $anf=$order['anforderungen'];
      if ($anf) {
         $anforderungen= explode(",", $anf); 
         foreach ($anforderungen as $no => $anforderung) {
            $this->add_ldt_fld('8410',$anforderung);
         }   
      }
      
      $this->add_ldt_fld('8000','8231'); 

      /*
      01380008231
      014810000044
      017920200001015
      01380000021
      014810000027
      */


      //8410                    
      //$this->add_ldt_fld('4112',$ver_status);

      
     //  If sFeld eq "4110" Move sWert to  PATKARTE.KVK_DAT  // Gültig bis (KVK)
     //  If sFeld eq "4113" Move sWert to  PATKARTE.VERS_STATUS_ER // Statusergänzung (DMP)    



      return $this->_ldt;
  }

} // Class
