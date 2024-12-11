<?php
namespace dbx\myBefund_admin;


class ImportLDT {

  private function import_lab($in) {
    global $dbx_syssetting;
    $content=""; $analyse_rec=array(); $haeder_rec=array(); $current_typ="";
    $haeder_id=0;  $pat=0; $arzt=0; $no=0;

    $in.="\n12382018201"; // Close last Rec
    $db=dbx_get_sys_object('dbxDB');


    $lines=explode("\n",$in);
    foreach ($lines as $ln => $line) {
        //$content.="<br>".$line;

        // 8201==Labor-Facharzt-Bericht
        // if ($current_typ=="8201") $content.="<br>".$line;

        $fld=substr($line, 3, 4);
        $val=trim(substr($line, 7));
        if ($val=="") $fld="xxxx"; //

        switch ($fld) {

          Case "xxxx": // Satzlaenge
            // Val is empty
          break;

          Case "8100": // Satzlaenge
          break;

          // Haeder-Daten- - - - - - -
          case "9212":
            if (!isset($haeder_rec['version'])) $haeder_rec['version']=$val;
          break;

          case "0201":
            if (!isset($haeder_rec['bsnr'])) $haeder_rec['bsnr']=$val;
          break;

          case "0203":
            if (!isset($haeder_rec['bsnrb'])) $haeder_rec['bsnrb']=$val;
          break;

          case "0212":
            if (!isset($haeder_rec['lanr'])) $haeder_rec['lanr']=$val;
          break;

          case "0211":
            if (!isset($haeder_rec['arztname'])) $haeder_rec['arztname']=$val;
          break;

          case "0205":
            if (!isset($haeder_rec['bsnr_strasse'])) $haeder_rec['bsnr_strasse']=$val;
          break;

          case "0206"; // old style
            $len = strlen($val);
            $pos = strpos($val," ");
            $plz = substr($val, 1, $pos);
            $ort = substr($val, $pos, $len-$pos);
            if (!isset($haeder_rec['bsnr_plz'])) $haeder_rec['bsnr_plz']=$plz;
            if (!isset($haeder_rec['bsnr_ort'])) $haeder_rec['bsnr_ort']=$ort;
          break;

          case "0215":
            if (!isset($haeder_rec['bsnr_plz'])) $haeder_rec['bsnr_plz']=$val;
          break;

          case "0216":
            if (!isset($haeder_rec['bsnr_ort'])) $haeder_rec['bsnr_ort']=$val;
          break;

          case "8300":
            if (!isset($haeder_rec['labor'])) $haeder_rec['labor']=$val;
          break;

          case "8320":
            if (!isset($haeder_rec['labor_name'])) $haeder_rec['labor_name']=$val;
          break;

          case "8321":
            if (!isset($haeder_rec['labor_strasse'])) $haeder_rec['labor_strasse']=$val;
          break;


          case "8322":
            if (!isset($haeder_rec['labor_plz'])) $haeder_rec['labor_plz']=$val;
          break;

          case "8323":
            if (!isset($haeder_rec['labor_ort'])) $haeder_rec['labor_ort']=$val;
          break;

          case "0101":
            if (!isset($haeder_rec['kbv_sende'])) $haeder_rec['kbv_sende']=$val;
          break;

          case "9106":
            if (!isset($haeder_rec['zeichensatz'])) $haeder_rec['zeichensatz']=$val;
          break;

          //case "8312":
          //  $haeder_rec[$pat]['arzt_nummer']=$val;
          //break;

          case "9103":
            if (!isset($haeder_rec['erstellt'])) $haeder_rec['erstellt']=$val;
          break;

          case "9472":
            if (!isset($haeder_rec['informationen'])) $haeder_rec['informationen']=$val;
          break;

          case "9300":
            if (!isset($haeder_rec['signatur'])) $haeder_rec['signatur']=$val;
          break;

          case "9301":
            if (!isset($haeder_rec['krypto'])) $haeder_rec['krypto']=$val;
          break;
          // - - - - - - - - - - - - -


          Case "8310": // Anforderungs-Ident PAtNr
            //echo "<br>VAL PAT=($val) Line=$line";
            if (dbx_is_integer($val)) {
              $analyse_rec[$pat]['pat']=$val;
            } else {
              $analyse_rec[$pat]['pat']=0; // wat den datt denn ?????????????????
            }

          break;

          Case "8311": // Auftragsnummer des Labors
            if (!isset($haeder_rec['tagesnummer'])) $haeder_rec['tagesnummer']=$val;
            $analyse_rec[$pat]['tagesnummer']=$val;
            if (!isset($analyse_rec[$pat]['pat'])) {
               $analyse_rec[$pat]['pat']=$this->riegel_nr($val);
            }
          break;

          Case "8312": // Arzt
            $val=str_replace("F", "", $val);
            $analyse_rec[1]['arzt']=$val; // Für alle Analysen gleicher Arzt
            if (!isset($haeder_rec['arzt'])) $haeder_rec['arzt']=$val;
          break;

          Case "8301":  // Eingangsdatum des Auftrags im Labor
              //BefundDump.EingangsZeit = "11:00:00"
              $analyse_rec[$pat]['datum']=$this->format_date_in($val);
          break;
          Case "8302": // Berichtsdatum
            // $analyse_rec[$pat]['datum']=$this->format_date_in($val);
          break;

          Case "8303": // Berichtszeit (!NEU! ab 01.10.2001)
          break;


          Case "3101": // Name Patient
             //$val=$this->xanonyme($val);
             $analyse_rec[$pat]['patname']=$val;
          break;
          Case "3102": // Vorname Patient
             //$val=$this->xanonyme($val);
             $analyse_rec[$pat]['patvorname']=$val;
          break;

          Case "3103": // Geburtsdatum
             $analyse_rec[$pat]['gebdat']=$this->format_date_in($val);
          break;

          Case "8401": // Befundart
              $analyse_rec[$pat]['befundart']=$val;
          break;

          Case "8609": // Abrechnungstyp
              $analyse_rec[$pat]['abrechtyp']=$val;
          break;
          Case "8615": // Auftraggeber
              $analyse_rec[$pat]['auftraggeber']=$val;
          break;
          Case "8403": // Gebuehrenordnung
              $analyse_rec[$pat]['geor']=$val;
          break;

          Case "8405": // Patienteninformationen
              //PatientDump.Info = curLDTLn.fcont
          break;

          Case "8407": // Geschlecht des Patienten
              $analyse_rec[$pat]['sex']=$val;
          break;

          Case "8434":
          Case "8410": // Test-Ident Next Analyse
              $no++;
              //echo "<br>##### NEXT TEST-IDENT Pat=$pat ID=$val #############" ;
              $analyse_rec[$pat]['analyse'][$no]['Testident']=$val;
              // Rest leer anlegen
              $analyse_rec[$pat]['analyse'][$no]['Testbezeichnung']="";
              $analyse_rec[$pat]['analyse'][$no]['TestStatus']="";
              $analyse_rec[$pat]['analyse'][$no]['abrechner']="";
              $analyse_rec[$pat]['analyse'][$no]['ErgWert']="";
              $analyse_rec[$pat]['analyse'][$no]['ErgEH']="";
              $analyse_rec[$pat]['analyse'][$no]['NWUg']="";
              $analyse_rec[$pat]['analyse'][$no]['NWOg']="";
              $analyse_rec[$pat]['analyse'][$no]['NWText']="";
              $analyse_rec[$pat]['analyse'][$no]['ErgText']="";
              $analyse_rec[$pat]['analyse'][$no]['GNR']="";
              $analyse_rec[$pat]['analyse'][$no]['freitext']="";
              $analyse_rec[$pat]['analyse'][$no]['GrenzIndikator']="";
              $analyse_rec[$pat]['analyse'][$no]['bemerkung']="";
          break;

          Case "8411": // Testbezeichnung
               $analyse_rec[$pat]['analyse'][$no]['Testbezeichnung']=$val;
              //AnalyseDump(AnalyseID).Testbezeichnung = curLDTLn.fcont
          break;

          Case "5001": // Gebuehrennummer (GNR)
              $analyse_rec[$pat]['analyse'][$no]['GNR']=$val;
          break;

          Case "5009": // freier Begründungstext
              $analyse_rec[$pat]['analyse'][$no]['freitext']=$val;
          break;


          Case "8406": // Kosten in Cent
              $analyse_rec[$pat]['analyse'][$no]['cent']=$val;
          break;

          Case "5002": // Art der Untersuchung
              //GNRDump(AnalyseID, GNRCntr).UsArt = curLDTLn.fcont
          break;

          Case "5005": // Multiplikator
              //GNRDump(AnalyseID, GNRCntr).Multipl = curLDTLn.fcont
          break;

          Case "8614": // Abrechnung Durch
              $analyse_rec[$pat]['analyse'][$no]['abrechner']=$val;
          break;

          Case "8418": // Test-Status
              $analyse_rec[$pat]['analyse'][$no]['TestStatus']=$val;
          break;

          Case "8428": // Probematerial-Ident
              //AnalyseDump(AnalyseID).ProbeMatIdent = curLDTLn.fcont
          break;

          Case "8429": // Probematerial-Index
              $analyse_rec[$pat]['analyse'][$no]['ProbeMatIdx']=$val;
          break;

          Case "8430": // Probematerial-Bezeichnung
              $analyse_rec[$pat]['analyse'][$no]['ProbeMatBez']=$val;
          break;

          Case "8431": // Probematerial-Spezifikation
              $analyse_rec[$pat]['analyse'][$no]['ProbMatSpez']=$val;
          break;

          Case "8420": //Ergebniswert
               $analyse_rec[$pat]['analyse'][$no]['ErgWert']=$val;
          break;

          Case "8421": //Einheit
               $analyse_rec[$pat]['analyse'][$no]['ErgEH']=$val;
          break;

          Case "8480": // Ergebnis-Text
              $analyse_rec[$pat]['analyse'][$no]['ErgText']=$val;
          break;

          //Case "8460": #ALB war hier auch an ??? mal schauen was mit NWText ist 
          Case "8470": //Testbezogener-Hinweis Bemerkung

              $bem=$analyse_rec[$pat]['analyse'][$no]['bemerkung'];
              if ($val > "") $bem.=$val."#BR#";
              $bem = trim($bem);
              $lang=strlen($bem);
              if ($lang > 2500) $bem=substr($bem,0,2500);
              $analyse_rec[$pat]['analyse'][$no]['bemerkung']=$bem;

              //echo "Pat=$pat Ana=$no <br>Old= $old New=$val <br>";


              //if ($val > "") $analyse_rec[$pat]['analyse'][$no]['Bemerkung'].=$val."#BR#";
          break;

          Case "8460": //Normalwert-Text NWText  #todo ??? 8460 ist oben schon ??
              if ($val > "") {
                  //if (!isset($haeder_rec['bemerkung'])) $haeder_rec['bemerkung']='nix';
                  $analyse_rec[$pat]['analyse'][$no]['NWText'].=$val."#BR#";
                  //$haeder_rec['bemerkung'].=$val.'#BR#';
                  //dbx_debug("Add Bem=($val)");
              }
          break;

          Case "8461": // Normalwert-Untergrenze NWUg
              $analyse_rec[$pat]['analyse'][$no]['NWUg']=$val;
          break;

          Case "8462": // Normalwert-Obergrenze NWOg
              $analyse_rec[$pat]['analyse'][$no]['NWOg']=$val;
          break;

          Case "8422": // Grenzwert-Indikator GrenzIndikator
              $analyse_rec[$pat]['analyse'][$no]['GrenzIndikator']=$val;
          break;

          Case "8490": // Auftragsbezogener-Hinweis
              if ($val > "") $analyse_rec[$pat]['bemerkung'].=$val."#BR#";
          break;

          Case "8000": //Satzident

              $ok=strpos("#8201#8202#8203#8204#8218#8219#", $val);
              if ($ok) {

                 $current_typ=$val;
                 $pat++;
                 $no=0; // analysen je Patient
                 $labor=$haeder_rec['labor'];
                 if ($labor == "LDA") $val="8202"; // LG-Bericht
                 //echo "<br>Labor=$labor Befund-Typ=$val";

                 $analyse_rec[$pat]['befundtyp']=$val;
                 $analyse_rec[$pat]['bemerkung']="";
                 $analyse_rec[$pat]['datum']="";
                 $analyse_rec[$pat]['eilt']="";
                 $analyse_rec[$pat]['patname']="";
                 $analyse_rec[$pat]['patvorname']="";
                 $analyse_rec[$pat]['gebdat']="";
                 $analyse_rec[$pat]['sex']="";
                 $analyse_rec[$pat]['befundart']="";
                 $analyse_rec[$pat]['abrechtyp']="";
                 $analyse_rec[$pat]['geor']="";

                 //$analyse_rec[$pat]['befundtyp']=8202;
              }
              // 8201==Labor-Facharzt-Bericht
              // 8202==LG-Bericht
              // 8203==Mikrologie-Bericht
              // 8204==Facharzt Bericht
              // 8218==Elektonische Überweisung
              // 8219==Auftrag an LG
              //NextSet = True
          break;
        }

        //$content.="<br>FLD=".$fld." V=".$val;

    }
    $content.="<hr>";




    foreach ($analyse_rec as $no => $patient) {
        if ($no) {
          $arzt= (int) $analyse_rec[1]['arzt'];
          $pat = 0;
          if (isset($patient['pat'])) {
            $datum    =$patient['datum'];
            $pat      =$patient['pat'];
          }
          $content.="<br>Arzt=($arzt) PAT=($pat) Datum=($datum)";
          if ($arzt>0 && $pat > 0) {
            $content.="<hr>";
            $content.="<br><b>Satz</b>=".$no;
            $content.="<br><b>PAT</b>=".$pat;
            $content.="<br><b>Arzt</b>=$arzt";


            //foreach ($patient as $pfld => $pval) {
            //   $content.="<br><b>PATIENT</b> $pfld -> $pval";
            //}


            // Datensatz my_befund erstellen / Update ?
            $haeder_rec['owner']         =$arzt;
            $haeder_rec['arzt']          =$arzt;
            $haeder_rec['pat']           =$patient['pat'];
            $haeder_rec['datum']         =$patient['datum'];
            $haeder_rec['tagesnummer']   =$patient['tagesnummer'];
            $haeder_rec['eilt']          =$patient['eilt'];
            $haeder_rec['patname']       =$patient['patname'];
            $haeder_rec['patvorname']    =$patient['patvorname'];
            $haeder_rec['gebdat']        =$patient['gebdat'];
            $haeder_rec['sex']           =$patient['sex'];
            $haeder_rec['befundart']     =$patient['befundart'];
            $haeder_rec['befundtyp']     =$patient['befundtyp'];
            $haeder_rec['abrechnungstyp']=$patient['abrechtyp'];
            $haeder_rec['geor']          =$patient['geor'];
            $haeder_rec['bemerkung']     =$patient['bemerkung'];

            // 1. Delete old then create new if
            $befund_id=0; $del_old=1; $save_bef=1;
            $befund_art=$patient['befundart'];
            $befund_typ=$patient['befundtyp'];



            //$rec=dbx_get_record("my_befund","datum='$datum' and arzt=$arzt and pat=$pat and befundtyp=$befund_typ"	);
            $where="datum='$datum' and arzt=$arzt and pat=$pat and befundtyp=$befund_typ";
            $rec=$db->select1('dbx_my_befund',$where);


            if (is_array($rec)) {
                $befund_id=$rec['id'];
                $befund_at=$rec['befundart'];
                if ($del_old==1) {
                   if ($befund_at=="E") {
                      if ($befund_art=="T") {
                         $save_bef=0;
                         $del_old=0;
                      }
                   }
                }
                if ($del_old==1) {
                   //$ok=dbx_Delete("my_befund",$befund_id,-3);
                   //$ok=dbx_Delete_multi("my_analyse","befund_id=$befund_id",-3);

                   //#TODO !!!

                   $befund_id=0;
                }
                //$ok=dbx_Delete_multi("my_analyse","befund_id=$befund_id",-3);
            }

            $vorname=$patient['patvorname'];
            $ok=2;

            if ($befund_id == 0 and $save_bef == 1) {
               //$ok=$db->("my_befund","new",$haeder_rec,-3);
               $datum=$haeder_rec['datum'];
               $arzt =$haeder_rec['arzt'];
               $pat  =$haeder_rec['pat'];
               $type =$haeder_rec['befundtyp'];
               $haeder_rec['owner']=$arzt;
               $query="datum='$datum' and arzt='$arzt' and pat='$pat' and befundtyp = '$type'";
               $rec=$db->select1('dbx_my_befund',$query);
               if (is_array($rec)) {
                 $id=$rec['id'];
                 if ($id) {
                    $db->delete('dbx_my_befund',$id,0,0);
                    $db->delete('dbx_my_analyse',"befund_id='$id'",0,0);
                  }
               }
               $ok=$db->insert('dbx_my_befund',$haeder_rec,0,0,0);
               $befund_id=$db->_insert_id; // Used for Relation 1:n Befund <-> Analyse
            }

            // echo "OK=($ok) <br>Befund-ID=($befund_id) Name=($vorname)<br>";


            $analyse="";

            if (isset($patient['analyse'])) $analyse=$patient['analyse'];
            if (is_array($analyse)) {
              foreach ($analyse as $no => $record) {
                if (is_array($record)) {

                  //if (!isset($record['Testident'])) $record['Testident']="x";

                  if (isset($record['Testident']) || isset($record['Testbezeichnung'])) {
                    if (!isset($record['Testident']))      $record['Testident']=$record['Testbezeichnung'];
                    if (!isset($record['Testbezeichnung']))$record['Testbezeichnung']=$record['Testident'];
                    if (!isset($record['abrechner']))      $record['abrechner']="";
                    if (!isset($record['TestStatus']))     $record['TestStatus']="";
                    if (!isset($record['ErgWert']))        $record['ErgWert']="";
                    if (!isset($record['ErgEH']))          $record['ErgEH']="";
                    if (!isset($record['NWUg']))           $record['NWUg']="";
                    if (!isset($record['NWOg']))           $record['NWOg']="";
                    if (!isset($record['NWText']))         $record['NWText']="";
                    if (!isset($record['GrenzIndikator'])) $record['GrenzIndikator']="";
                    if (!isset($record['freitext']))       $record['freitext']="";
                    if (!isset($record['ErgText']))        $record['ErgText']="";
                    if (!isset($record['GNR']))            $record['GNR']="";
                    if (!isset($record['cent']))           $record['cent']="0";
                    if (!isset($record['bemerkung']))      $record['bemerkung']="";

                    //$content.="<br>+Analyse <b>$no</b>";



                    $arzt=$haeder_rec['arzt'];
                    $rec=array();
                    $rec['owner']    = $arzt;
                    $rec['befund_id']= $befund_id;
                    $rec['testident']= trim($record['Testident']);
                    $rec['testbez']  = trim($record['Testbezeichnung']);
                    $rec['status']   = trim($record['TestStatus']);
                    $rec['abrechner']= trim($record['abrechner']);
                    $rec['ergebnis'] = trim($record['ErgWert']);
                    $rec['einheiten']= trim($record['ErgEH']);
                    $rec['nwug']     = trim($record['NWUg']);
                    $rec['nwog']     = trim($record['NWOg']);
                    $rec['nwtxt']    = trim($record['NWText']);
                    $rec['grenzindi']= trim($record['GrenzIndikator']);
                    $rec['ergtext']  = trim($record['ErgText']);
                    $rec['gnr']      = trim($record['GNR']);
                    $rec['cent']     = trim($record['cent']);
                    $rec['freitext'] = trim($record['freitext']);
                    $rec['bemerkung']= trim($record['bemerkung']);




                    if ($save_bef == 1) {
                       //$ok=dbx_Save("my_analyse","new",$rec,-3);
                       $ok=$db->insert('dbx_my_analyse',$rec,0,1,1);

                       if (!$ok) dbx_debug("#ANALYSE Insert",$rec);


                       //function insert($tab,$field_values,$verify_access=1,$verify_fields=1,$verify_values=1) {

                    }

                } // Testident
              }  // record=array()
            } // foreach record
          } // analyse
        } // arzt && pat
      } // $no
    } // analyse_rec
    //exit;
    return $content;
  }


  private function riegel_nr($val) {
     $riegelnr=999999;
     //echo "<br>rigel-In=$val";
     $teile = explode("_", $val);
     if (isset($teile[1])) $riegelnr=$teile[1];
     if (isset($teile[2])) $riegelnr=$teile[2];
     if (isset($teile[3])) $riegelnr=$teile[3];
     return $riegelnr;
  }


  private function xanonyme($val) {
     $val=@substr($val, 0, 2);
     $val.="*";
     return $val;
  }


  function check_datum($val) {
     $convert=0;    //  20150810
     $jahr = substr($val, 0, 4);
     if ($jahr > 1900 && $jahr < 2020) $convert=1;
     if ($convert==1) {
        $jahr = substr($val, 0, 4);
        $monat= substr($val, 4, 2);
        $day  = substr($val, 6, 2);
        if ($day >= 1 && $day <= 31) {
          if ($monat>=1 && $monat <= 12) {
            //echo " J=$jahr M=$monat T=$day ";
            $val=$day;
            $val.=$monat;
            $val.=$jahr;
          }
        }

     }
     return $val;
  }


  private function format_date_in($val) {
     //echo "<br>DATE <- ($val) ";
     //$val=$this->check_datum($val);
     //echo " ( $val ) ";
     $jj=substr($val, 4, 4);
     $mm=substr($val, 2, 2);
     $dd=substr($val, 0, 2);
     $date=$jj."-".$mm."-".$dd;
     //$date=$dd."-".$mm."-".$jj;
     //echo " DATE ->($date)";
     return $date;
  }




  private function encodeToUtf8($string,$special=0) {
    return iconv("CP437", "UTF-8//TRANSLIT", $string);
  }

  private function encodeToIso($string) {
    //return iconv("UTF-8", "Windows-1252//TRANSLIT", $string);
    return iconv("CP437", "UTF-8//TRANSLIT", $string);
  }



  public function run($path_file='') {
    $special=0; $copy=0;
    $path_file=dbx_os_path_file($path_file);
    $file=basename($path_file);
    $path_file=dbx_os_path_file($path_file);
    $path_for_praxis_import=dbx_get_cfg('myOrderLDT','path_medisoft');
    $path_for_praxis_import=dbx_os_path_file($path_for_praxis_import);

    //dbx_debug("Import-LDT=$path_file");
    $content = "LDT Datei $path_file nicht gefunden.";
     
    if ($path_for_praxis_import && $path_file) {
      $file_for_praxis=$path_for_praxis_import.$file;
      if (file_exists($path_file)) {
        if (is_file($path_file)) { 
          if ($copy) $ok=copy($path_file, $file_for_praxis);
        }  
      }
    }

    if (strpos($path_file, '_RIE_') !== false) {
      //$special=1;
    } 
    

    if (file_exists($path_file)) {
      if (is_file($path_file)) {
        $in=file_get_contents($path_file); // -> run private import ldt file.LAB
        $in=$this->encodeToUtf8($in,$special);
        $in=str_replace("\r","",$in);

       

        $content=$this->import_lab($in);
      }
    }
    return $content;
  }



} // Class


