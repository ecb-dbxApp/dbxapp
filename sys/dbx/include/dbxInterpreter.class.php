<?php
// ---- INTERPRETER ----
class dbxInterpreter {

  public function run($content) {
    //return $content;

    //dbx_debug("#RUN-INTERPRETER#");

    $f1=''; $f2=''; $modul=''; $parameter='';



    for ($a=1; $a< 512; $a++) { // break  4
      $do=false;



      // preg_match_all("'[html](.*?)[/html]'si", $content, $match);
      // preg_match_all("'<p class=\"review\">(.*?)</p>'si", $source, $match);
      // foreach($match[1] as $norep)  {
      //   $key=dbx_add_norep($norep);
      //   $rep="[html]".$norep."[/html]";
      //   $content=(str_replace($rep,$key,$content));
      // }
      // // - - - - -

      $patterns = "/\[modul=([^\[]*)\]([^\[]*)\[\/modul\]/i";
      preg_match ($patterns, $content, $matches);
      $count=count($matches);

      if ($count) {
        $funki = ($matches[1]);
        $f1=$matches[1];
        $f2=$matches[2];

        if ($f1 > "" && $f2 > "") {
          $modul    = $f1;
          $parameter= $f2;

          $replacements = "ersetzt";

          $replacements  = $this->get_modul_content($modul,$parameter);
          $inc_patterns  = "[modul=".$f1."]".$f2."[/modul]";


          $pos1   = strpos ($content, $inc_patterns );
          $lang1  = strlen ($inc_patterns);
          $lang2  = strlen ($content);
          $vor    = substr ( $content, 0 , $pos1);
          $nach   = substr ( $content, ($pos1+$lang1) , $lang2);
          $content = $vor.$replacements.$nach;

          $do=true;
        }
      }
      // - - - - -
      if (!$do) break; // Kill if Dubbel Break needed
      //echo "<br>Loops=$a";
    } // Loop a

    //dbx_debug("RETURN",$content);
    return $content;

  }

 

  private function get_modul_content($modul,$parameter='') {
    dbx_run_time('run-'.$modul,'P='.$parameter);  


    $modul_content=''; $xparameter=''; $action='undef'; $modul_id=0;
    $access=dbx_check_modul_access($modul);
    //dbx_debug("###DBX-Interpreter###  M=($modul) Param=($parameter) Access=($access)");
    if ($access) {
      $oModul   = dbx_get_Modul_object($modul); 
      $modul_id = dbx_get_SysVar('dbx_activ_modul_id');
      $parameter= trim($parameter);
      $uid      = dbx_get_CurrentUser();
      $part_select = explode ("&", $parameter); // Parameter vom Aufruf
      if (is_array($part_select)) { // Zur Sicherheit
        foreach ($part_select as $id => $value) {
          $pos = strpos($value,"=");
          $pos2=($pos+1);
          $paramn = substr($value,0,$pos); //
          $paramv = substr($value,$pos2);
          if ($paramv=='?') $paramv=dbx_get_PostGetVar($paramn,'','parameter');
          if ($paramn=='dbx_action') $action=$paramv;
          //$xparameter.= "Parameter($paramn)=($paramv) ";
          dbx_Set_ModulVar($paramn,$paramv);
          //dbx_debug("##############Modul=($modul) Par=($paramn) Val=($paramv)");
          //dbx_set_SysVar($paramn,$paramv);
        }
      }
      if ($modul) {
        dbx_set_SysVar('dbx_modul'       ,$modul);
        dbx_set_SysVar('dbx_action'      ,$action);
        dbx_set_SysVar('dbx_activ_modul' ,$modul);
        dbx_set_SysVar('dbx_activ_action',$action);
    

        $modul_content=$oModul->run($action);



      } else {
        $modul_content="<p class='red'>#undef# Modul=($modul) Action=($action)</p>";
      }
      //dbx_debug("Interpreter $modul $action",$modul_content);
    } else { // no access
      $uid=dbx_get_CurrentUser();
      if ( $uid) {
        $oTPL=dbx_get_sys_object('dbxTPL');
        $modul_content=$oTPL->get_tpl('dbx','alert-warning',"msg=Sie haben keinen Zugriff auf ($modul)." ); 
      }
      if (!$uid) {
          $master=dbx_get_SysVar('dbx_master_modul',$modul);
          $perma =dbx_get_SysVar('dbx_permalink','');
          
          $modul_content='[modul=dbxLogin]dbx_action=login[/modul]';

          //dbx_debug("#Redir after login=($perma)");

          if ($perma) {
            dbx_set_Remember('dbx_redir_after_login',$perma,'dbx');
          } else {
            dbx_set_Remember('dbx_redir_after_login',"?dbx_modul=$modul&$parameter",'dbx');
          }
      }
    }
    $redir=dbx_get_Remember('dbx_redir_after_login','','*','dbx');
    //dbx_debug("#DBX#  Modul=($modul) Param=($parameter) Access=($access) M-id=($modul_id)  redirect=($redir)");
    dbx_run_time('run-'.$modul);
    return $modul_content;
  }




} // Class


