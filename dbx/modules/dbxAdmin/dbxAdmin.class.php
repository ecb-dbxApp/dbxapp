<?php
namespace dbx\dbxAdmin;
Class dbxAdmin {


   public function run() {
      $content='';
      $uid   =dbx()->user();
      $mid   =dbx()->get_system_var('dbx_modul_id');
      $modul =dbx()->get_system_var('dbx_modul');
      $oTPL  =dbx()->get_system_obj('dbxTPL');


      $action=dbx()->get_modul_var('dbx_run1','run');

      switch ($action) {



        case 'run':
        case 'dashboard':
          $obj=dbx()->get_include_obj('dbxDashboard');
          $content=$obj->run();
        break;  



        case 'design':
          $obj=dbx()->get_include_obj('dbxDesign');
          $content=$obj->run();
        break;

        case 'modul':
            $obj=dbx()->get_include_obj('dbxModul');
            $content=$obj->run();
        break;


        case 'menu':
          $obj=dbx()->get_include_obj('dbxMenu');
          $content=$obj->run();
          break;

        case 'edit_dd':
          $obj=dbx()->get_include_obj('dbxEdit_dd');
          $content=$obj->run();
          break;

        case 'edit_fd':
          $obj=dbx()->get_include_obj('dbxEdit_fd');
          $content=$obj->run();
          break;


        case 'dd':
          $obj=dbx()->get_include_obj('dbxSchema');
          $content=$obj->run('dd');
          break;

        case 'db':
          $obj=dbx()->get_include_obj('dbxSchema');
          $content=$obj->run('db');
          break;

        case 'missing':
          $obj=dbx()->get_include_obj('dbxMissing');
          $content=$obj->run();
          break;

        case 'trace':
          $obj=dbx()->get_include_obj('dbxTrace');
          $content=$obj->run();
          break;


        case 'sysmsg':
          $obj=dbx()->get_include_obj('dbxSysMsg');
          $content=$obj->run();
          break;


        case 'modules':
            $obj=dbx()->get_include_obj('dbxModules');
            $content=$obj->run('modules');
        break;

        case 'config':
          $obj=dbx()->get_include_obj('dbxConfig');
          $content=$obj->run('modules');
        break;   

       // - - - -
        case 'session':
          $obj=dbx()->get_include_obj('dbxSession');
          $content=$obj->run();
        break;

        case 'cache':
          $obj=dbx()->get_include_obj('dbxPageCache');
          $content=$obj->run();
        break;

        case 'update':
          $obj=dbx()->get_include_obj('dbxUpdate');
          $content=$obj->run();
        break;

        case 'help':
          $obj=dbx()->get_include_obj('dbxContentContextHelp', 'dbxContent');
          $content=is_object($obj) ? $obj->run() : '';
        break;

 

        case 'user':
          $obj=dbx()->get_include_obj('dbxUser');
          $content=$obj->run('user');
        break;

        case 'contact':
          $obj=dbx()->get_include_obj('dbxContactAdmin','dbxContact_admin');
          $content=$obj->run();
        break;



        case 'export_sql': // logoff
          $obj=dbx()->get_include_obj('dbxSQLdump');
          $content=$obj->run('export');
        break;

        case 'server':
          $obj=dbx()->get_include_obj('dbxServer');
          $content=$obj->run();
        break;

        
        case 'datadic':
          $obj=dbx()->get_include_obj('dbxDataDic');
          $content=$obj->run();
        break;        


        case '_edittpl': // edit TPL
            dbx()->set_system_var('tpleditor',1);
            dbx()->set_system_var('dbx_page','_tpledit');
            $obj=dbx()->get_include_obj('dbxTplEdit');
            $content=$obj->run();
            break;

        case '_editpage': // edit TPL
            dbx()->set_system_var('tpleditor',2);
            dbx()->set_system_var('dbx_page','_pageedit');
            $obj=dbx()->get_include_obj('dbxPageEdit');
            $content=$obj->run();
            break;



        default:
          $oTPL=dbx()->get_system_obj('dbxTPL');
          $msg['msg']="Modul=($modul) Action=($action) is undef.";
          $content=$oTPL->get_tpl('dbx|alert-warning',$msg);
      }


      return $content;
   }
}
