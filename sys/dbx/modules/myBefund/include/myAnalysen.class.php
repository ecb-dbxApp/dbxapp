<?php
namespace dbx\myBefund;
dbx_get_sys_object('dbxReport','use');

class dbxReport_Analys extends \dbxReport {
   Public $_comments='';

 

   function get_ref_img($record) {
       $bemerkung=' - + - ';


       // Werte aus dem Record
       $ErgWert = str_replace(',', '.', $record['ergebnis']);
       $nwug = str_replace(',', '.', $record['nwug']);
       $nwog = str_replace(',', '.', $record['nwog']);


       
       // Bereichsgrenzen
       $minWert = (float) $nwug;
       $maxWert = (float) $nwog;
       $ErgWert = (float) $ErgWert;

       if ($minWert >= $maxWert) return $bemerkung; 
       if ($ErgWert == '' )      return $bemerkung;
       if ($ErgWert == 0)        return $bemerkung;
       if ($ErgWert == null)     return $bemerkung;

       dbx_debug("#Grafik Wert=($ErgWert) Min=($minWert) Max=($maxWert)");

       // Berechnung der Position des Symbols (Fadenkreuz im Kreis)
       $sternData = $this->berechneSternPosition($ErgWert, $minWert, $maxWert);
       $sternPosition = $sternData['position'];
       $sternFarbe = $sternData['farbe'];
   
       // Erstellung der Grafik mit Inline-CSS
       $grafik = '
       <div class="messbereiche" style="position: relative; width: 100%; height: 24px; background: linear-gradient(to right, rgba(255, 0, 0, 0.1) 0%, rgba(255, 0, 0, 0.1) 24%, rgba(0, 255, 0, 0.1) 24%, rgba(0, 255, 0, 0.1) 76%, rgba(255, 0, 0, 0.1) 76%, rgba(255, 0, 0, 0.1) 100%); border: 1px solid blue;">';
   
       // Fadenkreuz innerhalb eines kleineren Kreises mit kürzeren, dickeren Linien (höchster z-index)
       $grafik .= '<div class="fadenkreuz" style="position: absolute; left: ' . $sternPosition . '%; top: 50%; transform: translate(-50%, -50%); z-index: 1000;">
                       <div style="position: relative; width: 16px; height: 16px; border-radius: 50%; border: 2px solid ' . $sternFarbe . '; display: flex; align-items: center; justify-content: center;">
                           <!-- Horizontale Linie des Fadenkreuzes -->
                           <div style="position: absolute; width: 8px; height: 2px; background-color: ' . $sternFarbe . ';"></div>
                           <!-- Vertikale Linie des Fadenkreuzes -->
                           <div style="position: absolute; height: 8px; width: 2px; background-color: ' . $sternFarbe . ';"></div>
                       </div>
                   </div>';
   
       // Vertikale Linien (24%, 50%, 76%) mit Inline-Style (z-index: 900)
       $grafik .= '<div class="vertical-line line-24" style="position: absolute; width: 1px; height: 100%; left: 24%; background-color: blue; z-index: 900;"></div>';
       $grafik .= '<div class="vertical-line line-middle" style="position: absolute; width: 1px; height: 100%; left: 50%; background-color: blue; z-index: 900;"></div>';
       $grafik .= '<div class="vertical-line line-76" style="position: absolute; width: 1px; height: 100%; left: 76%; background-color: blue; z-index: 900;"></div>';
   
       // Horizontale Linie in der Mitte (z-index: 900)
       $grafik .= '<div class="horizontal-line" style="position: absolute; width: 100%; height: 1px; top: 50%; background-color: gray; z-index: 900;"></div>';
   
       // Skalierungslinien alle 6% mit Inline-Style (z-index: 850)
       for ($i = 6; $i <= 100; $i += 6) {
           // Mittellinie bei 50% ohne doppelte Linie (einzige vertikale Linie in der Mitte)
           if (abs($i - 50) < 3) continue;  // Mittlere Linie wird übersprungen, keine nahe Linien erlaubt
           
           $lineColor = ($i === 48 || $i === 52) ? 'blue' : 'lightgray';  // Skala mit exakter Mittellinie bei 48% und 52% entfernt
           $grafik .= '<div class="scale-line" style="position: absolute; width: 1px; height: 100%; left: ' . $i . '%; background-color: ' . $lineColor . '; z-index: 850;"></div>';
       }
   
       // Abschluss des Grafikelements
       $grafik .= '</div>';
   
       return $grafik;
   }
   
   // Berechnung der Position des Fadenkreuzes basierend auf dem Ergebniswert
   function berechneSternPosition($ergWert, $minWert, $maxWert) {
       if ($ergWert < $minWert) {
           // ErgWert ist kleiner als der Mindestwert -> Links außen
           $position = 0;
           $farbe = 'red';  // Rot für Werte außerhalb des unteren Bereichs
       } elseif ($ergWert > ($maxWert * 2)) {
           // ErgWert ist größer als das Doppelte des Maximalwerts -> Rechts außen
           $position = 100;
           $farbe = 'red';  // Rot für Werte außerhalb des oberen Bereichs
       } elseif ($ergWert >= $minWert && $ergWert <= $maxWert) {
           // ErgWert liegt im mittleren Bereich
           $position = 24 + (($ergWert - $minWert) / ($maxWert - $minWert)) * (76 - 24);  // Position relativ im mittleren Bereich
           $farbe = 'green';  // Grün für Werte im Normalbereich
       } else {
           // ErgWert liegt rechts vom Maximum
           $position = 76 + (($ergWert - $maxWert) / $maxWert) * (100 - 76);  // Position im rechten Bereich (von 76% bis 100%)
           $farbe = 'red';  // Rot für Werte rechts des Normalbereichs
       }
   
       return ['position' => $position, 'farbe' => $farbe];
   }
   
   
  private function insert_string($intostring, $insertstring,$offset) {
     $part1 = substr($intostring, 0, $offset);
     $part2 = substr($intostring, $offset);
     $part1 = $part1 . $insertstring;
     $whole = $part1 . $part2;
     return $whole;
  }




  public function run_body($content) {
    $bemerkung=$this->_comments;
    $record =$this->_record;
    $record['img'] = $this->get_ref_img($record);
    $bem=$record['bemerkung'];
    $len=strlen($bem);

    if ($record['ergtext'] == '') {
       $record['ergtext']=$record['ergebnis'].' '.$record['einheiten'];
    }
    if ($bem > '') {
       
       if ($len > 30) {
         $count=substr_count($bem, '#BR#');
         if ($count > 3 || $len > 60) {
            $bemerkung.='<b>'.$record['testident'].'</b><br>'.$bem.'<br>';
            $record['bemerkung']='Siehe Bemerkungen';
         } else {
            $record['bemerkung']=str_replace('#BR#','  ',$bem);
         }
       }
    }
    $bemerkung=str_replace('#BR#','  ',$bemerkung);
    $this->_comments=$bemerkung;
    $this->_record=$record;

    return $content;
  }
 

}


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
Class myAnalysen {

   private function get_befundtyp($typ) {
      $retval='-?-';
      if ($typ == 8201) $retval='Labor-Bericht';
      if ($typ == 8202) $retval='LG-Bericht';
      if ($typ == 8203) $retval='Mikrobiologie-Bericht';
      return $retval;
   }

   private function get_befundart($art) {
      $retval='-?-';
      if ($art=='N') $retval='Nachforderung';
      if ($art=='T') $retval='Teilbefund';
      if ($art=='E') $retval='Endbefund';
      if ($art=='P') $retval='Befund';
      return $retval;
   }

   private function get_laborname($labor) {
      if (!$labor) $labor='MVZ Dr. med. Helge Riegel GmbH';
      return $labor;
   }


   private function get_sex($sex) {
      $retval=$sex;
      if ($sex == 1)  $retval='Mann';
      if ($sex == 2)  $retval='Frau';
      if ($sex == 3)  $retval='Kind';
      if ($sex =='W') $retval='Frau';
      if ($sex =='F') $retval='Frau';
      if ($sex =='K') $retval='Kind';
      if ($sex =='M') $retval='Mann';
      return $retval;
   }

   private function splitStringByLength($string, $maxLength = 120, $tolerance = 8) {
      $words = preg_split('/\s+/', $string); // Zerlege den String in Wörter
      $segments = [];
      $currentSegment = '';
  
      foreach ($words as $word) {
          // Prüfen, ob das aktuelle Segment zu lang wird, wenn das nächste Wort hinzugefügt wird
          if (strlen($currentSegment) + strlen($word) + 1 > $maxLength) {
              // Wenn ein Doppelpunkt im aktuellen Segment ist, immer dort trennen
              if (strpos($currentSegment, ':') !== false) {
                  $segments[] = trim($currentSegment);  // Trennen am Doppelpunkt
              }
              // Innerhalb der Toleranz nach einem Satzzeichen suchen
              elseif (preg_match('/[.!?,:;]$/', $currentSegment) && strlen($currentSegment) >= $maxLength - $tolerance) {
                  $segments[] = trim($currentSegment);  // Trennen am Satzzeichen
              } else {
                  $segments[] = trim($currentSegment);  // An der Wortgrenze trennen
              }
              $currentSegment = ''; // Neues Segment beginnen
          }
  
          // Das Wort dem aktuellen Segment hinzufügen
          $currentSegment .= ($currentSegment === '' ? '' : ' ') . $word;
      }
  
      // Das letzte Segment hinzufügen
      if (!empty($currentSegment)) {
          $segments[] = trim($currentSegment);
      }
  
      return $segments;
  }





   private function report_analysen($befund_id=0) {

      if (!$befund_id) $befund_id= dbx_get_ModulVar('rid',0,'int');
      // if (!$befund_id) $befund_id=$oForm->_data['id']; // nach dem speichern ??
      dbx_set_Remember('my_befund-activ_id',$befund_id);

      $oReport = new dbxReport_Analys;
      $oDB     = dbx_get_sys_object('dbxDB');
      $lng     = dbx_get_ModulVar('lng','de');
      $data    = $oDB->select1('dbx_my_befund',$befund_id);

      if (!is_array($data)) return "Befund ($befund_id) nicht gefunden !";

      $gebdat=$oReport->php_date_usr($data['gebdat']);
      $befund_typ =$data['befundtyp'];
      $name       =$data['patvorname'].' '.$data['patname'];
      $sex        =$this->get_sex($data['sex']);

      $oReport->init('report-analysen');
      $oReport->_data=$data;
      $oReport->_create_sel_flds=0;
      $oReport->_fld_id='testindent';

      $oReport->_msg_info =''; 
      $oReport->_msg_success=''; 
      $flds=array();

      //return "Befund-Type=($befund_typ)";

      if ($befund_typ=='8201') { //
        $flds['testident'] ='Abk';
        $flds['testbez']   ='Analyse';
        $flds['ergtext']   ='Ergebnis';
        $flds['einheiten'] ='';
        $flds['ergebnis']  ='';
        $flds['nwug']      ='';
        $flds['nwog']      ='';
        $flds['bemerkung'] ='Bemerkung';
      }

      if ($befund_typ=='8202') {
        $flds['testident'] ='Abk';
        $flds['testbez']   ='Analyse';
        $flds['ergebnis']  ='Wert';
        $flds['einheiten'] ='Einheit';
        $flds['nwug']      ='Untergr.';
        $flds['nwog']      ='Obergr.';
        $flds['img']       ='Referenzbereich';
        //$flds['gnr']       ='LZiffer';
        $flds['bemerkung'] ='';
        $flds['ergtext']   ='';
      }

      if ($befund_typ=='8203') {
        $flds['testident'] ='Abk';
        $flds['testbez']   ='Analyse';
        $flds['ergtext']   ='Ergebnis';
        $flds['img']       ='Bemerkung';
        $flds['ergebnis']  ='';
        $flds['einheiten'] ='';
        $flds['nwug']      ='';
        $flds['nwog']      ='';
        $flds['bemerkung'] ='';
        //$flds['gnr']       ='LZiffer';
      }


      $oReport->add_obj('labor'     ,'obv-value',$this->get_laborname($data['labor_name']));
      $oReport->add_obj('befundtyp' ,'obv-value',$this->get_befundtyp($data['befundtyp']));
      $oReport->add_obj('befundart' ,'obv-value',$this->get_befundart($data['befundart']));
      $oReport->add_obj('datum'     ,'obv-value',$oReport->php_date_usr($data['datum']));
      $oReport->add_obj('pat'       ,'obv-value',$data['pat']);
      $oReport->add_obj('now'       ,'obv-value',date("d.m.Y"));
      $oReport->add_obj('pat_name'  ,'obv-value',$name);
      $oReport->add_obj('pat_gebdat','obv-value',$gebdat);
      $oReport->add_obj('pat_sex'   ,'obv-value',$sex);
      $oReport->add_js_call('dbx_table','datatable1');
      $oReport->add_js("datatable_fix('#dbx_table_{i}')",100); // work arround hack


      //$oReport->add_action('button_mail', 'modul|button_mail','&dbx_work=analys_mail&befund_id='.$befund_id);

   

      //$oReport->add_obj('bemerkung','obv-value',$bemerkung);
      $oReport->add_obj('bemerkung','obv-value','{obv-bemerkungen}');

      //$oReport->add_obj('bemerkung','obv-value','{obv-bemerkungen}');

      $oReport->add_action('button_prn',  'modul|button_prn', '&dbx_work=analys_print&befund_id='.$befund_id);
      //$oReport->add_action('button_ldt',  'modul|button_ldt', '&dbx_work=analys_ldt&befund_id='.$befund_id);


      // get all selections and order
      $rgroup=''; $rrows=100; $rpos=0; $rsort='befund_id'; $rdesc ='ASC';
      $rwhere = "befund_id = $befund_id and (testident <> 'Blub') ";

      $oReport->_rcount=$oDB->count('dbx_my_analyse',$rwhere);
      $oReport->_rdata =$oDB->select('dbx_my_analyse',$rwhere,$flds,$rsort,$rdesc,$rgroup,$rrows,$rpos);

      //dbx_debug("ANALYSEN#",$oReport->_rdata);
     


      $oReport->add_js("dbx_set_print_ids('dd=dbx_my_befund&id=$befund_id');");
      $do=dbx_get_ModulVar('dbx_do','','parameter');
      if ($do == 'row_print') $oReport->add_js("$('#btnPrint').trigger('click');");

      
      $content=$oReport->run(1,$flds,'table');
      $bemerkungen=$oReport->_comments;

      $bems     = $this->splitStringByLength($bemerkungen);
      $bemerkungen='';
      foreach ($bems as $no => $line) {
         $bemerkungen.=$line.'<br>';
      }   


      $content=str_replace('{obv-bemerkungen}',$bemerkungen,$content);

      //dbx_debug("##Bemerkungen##",$oReport->_comments);

      return $content;
   } // report_content_flat()






   public function run() {
      $work=dbx_get_ModulVar('dbx_work');
      $content='Modul myBefund->myAnalysen action('.$work.') not defined';
      switch ($work) {
        case 'print_analys': 
        case 'list_analys':
            $befund_id=dbx_get_ModulVar('rid',0,'int');
            $content=$this->report_analysen($befund_id);
            break;

      }
      return $content;
   } // run

} // class
