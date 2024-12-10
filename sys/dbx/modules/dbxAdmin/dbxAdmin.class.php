<?php
namespace dbx\dbxAdmin;
Class dbxAdmin {


   public function run($action='') {
      $content='';
      $uid   =dbx_get_CurrentUser();
      $mid   =dbx_get_sysVar('dbx_modul_id');
      $modul =dbx_get_SysVar('dbx_modul');


      if (!$action) $action=dbx_get_ModulVar('dbx_action','run');

      switch ($action) {



        case 'run':
          $design=dbx_get_SysVar('dbx_activ_design',dbx_get_SysVar('dbx_design','undeff'));
          $page  =dbx_get_SysVar('dbx_activ_page'  ,dbx_get_SysVar('dbx_page'  ,'default'));
          $edit  =dbx_get_SysVar('dbx_edit',0);
          $content="<br><br><h1>dbxAdmin</h1>Design=($design) Page=($page) Edit=($edit)";
          break;


        case 'pos-and-size':
          $obj_na=dbx_get_ModulVar('obj_na' ,0,'*');
          $obj_id=dbx_get_ModulVar('obj_id' ,0,'*');
          $for_id=dbx_get_ModulVar('form_id',0,'*');
          $obj_x =dbx_get_ModulVar('obj_x'  ,0,'*');
          $obj_y =dbx_get_ModulVar('obj_y'  ,0,'*');
          $obj_h =dbx_get_ModulVar('obj_h'  ,0,'*');
          $obj_w =dbx_get_ModulVar('obj_w'  ,0,'*');
          dbx_debug ("FORM=($for_id) FLD=($obj_id / $obj_na) POS-X=($obj_x) POS-Y=($obj_y) OBJ-H=($obj_h) OBJ-W=($obj_w)");
        break;



        case 'design':
          $obj=dbx_get_Modul_include_object('dbxDesign');
          $content=$obj->run();
        break;

        case 'modul':
            $obj=dbx_get_Modul_include_object('dbxModul');
            $content=$obj->run();
        break;


        case 'menu':
          $obj=dbx_get_Modul_include_object('dbxMenu');
          $content=$obj->run();
          break;

        case 'missing':
          $obj=dbx_get_Modul_include_object('dbxMissing');
          $content=$obj->run();
          break;


        case 'modules':
            $obj=dbx_get_Modul_include_object('dbxModules');
            $content=$obj->run('modules');
        break;

        case 'config':
          $obj=dbx_get_Modul_include_object('dbxConfig');
          $content=$obj->run('modules');
        break;   

       // - - - -
        case 'session':
          $obj=dbx_get_Modul_include_object('dbxSession');
          $content=$obj->run('list');
        break;

 

        case 'user':
          $obj=dbx_get_Modul_include_object('dbxUser');
          $content=$obj->run('user');
        break;



        case 'export_sql': // logoff
          $obj=dbx_get_Modul_include_object('dbxSQLdump');
          $content=$obj->run('export');
        break;

        case 'server':
          $obj=dbx_get_Modul_include_object('dbxServer');
          $content=$obj->run();
        break;

        case 'datadic':
          $obj=dbx_get_Modul_include_object('dbxDataDic');
          $content=$obj->run();
        break;        


        case '_edittpl': // edit TPL
            dbx_set_SysVar('tpleditor',1);
            dbx_set_SysVar('dbx_page','_tpledit');
            $obj=dbx_get_Modul_include_object('dbxTplEdit');
            $content=$obj->run();
            break;

        case '_editpage': // edit TPL
            dbx_set_SysVar('tpleditor',2);
            dbx_set_SysVar('dbx_page','_pageedit');
            $obj=dbx_get_Modul_include_object('dbxPageEdit');
            $content=$obj->run();
            break;



        default:
          $oTPL=dbx_get_sys_object('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) is undef.";
          $content=$oTPL->get_tpl('dbx','alert-warning',$msg);
      }


      return $content;
   }
}

