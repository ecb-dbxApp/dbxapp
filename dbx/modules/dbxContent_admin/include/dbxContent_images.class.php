<?php
namespace dbx\dbxContent_admin;

Class dbxContent_images {

   private function convert_image_to_webp($file) {
      if (!extension_loaded('gd') || !function_exists('imagewebp')) return '';
      if ($file === '' || !is_file($file) || !is_readable($file)) return '';

      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      if (!in_array($ext, array('jpg','jpeg','png'), true)) return '';

      $image = $ext === 'png'
         ? (function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false)
         : (function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file) : false);
      if (!$image) return '';

      imagepalettetotruecolor($image);
      imagealphablending($image, true);
      imagesavealpha($image, true);

      $webp = preg_replace('/\.[^.]+$/', '.webp', $file);
      if ($webp === $file || $webp === null) {
         imagedestroy($image);
         return '';
      }

      $ok = @imagewebp($image, $webp, 86);
      imagedestroy($image);
      if (!$ok || !is_file($webp)) return '';

      @unlink($file);
      return basename($webp);
   }

   public function check_data($iid,$data) {
     if (is_array($data)) {
       $img_src='';
       $img_key='upload'.$iid;
       //$path = dbx()->get_base_dir().'dbx/modules/dbxContent/img/';
       $path = dbx()->get_file_dir().'/dbxContent/img/';

       if (isset($data[$img_key])) $img_src=$data[$img_key];
       if (!$img_src) $img_src='content.gif';

       $path_img_ext=$path.$img_src;
       if (!file_exists($path_img_ext)) $img_src='content.gif';

       $data[$img_key]=$img_src;
     }
     return $data;
   }


   public function run() {
      $content=''; $ok=false;

      $iid=dbx()->get_modul_var('iid',1,'int');
      //$img=dbx()->get_modul_var('img','1');
      $lng=dbx()->get_system_var('dbx_lng','de');

      $rid=dbx()->get_modul_var('rid',0);
      $obs=dbx()->get_modul_var('dbx_obs_fld');
      $obv=dbx()->get_modul_var('dbx_obs_val');
      //dbx_debug("#Observ  rid=($rid) obs=($obs) obv=($obv)");
      if (!$rid) {
         if ($obs && $obv) $rid=$obv;
      }


      $oForm = dbx()->get_system_obj('dbxForm');
      $db    = dbx()->get_system_obj('dbxDB');
      //$path  = dbx()->get_base_dir().'dbx/modules/dbxContent/img/';
      //$url   = dbx()->get_base_url().'dbx/modules/dbxContent/img/';
      $path  = dbx()->get_file_dir().'/dbxContent/img/';
      $url   = dbx()->get_base_url().'files/dbxContent/img/';

      $target= dbx()->get_system_obj('dbxRequest')->get('dbx_target');

      $tab_content = dbx()->lng_name('content', $lng);
      $img_key     = 'upload'.$iid;
      $obs_key     = 'obs_'.$img_key;


      $data=$db->select1($tab_content,$rid); //,"id,$img_key,");
      if (!is_array($data)) { return "Content ID ($rid) nicht gefunden"; }


      $oForm->init('dbxContent_image_'.$iid,'form-images');
      $oForm->_data  =$data;
      $oForm->_action="?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=images&rid=$rid&iid=$iid"; // set_action() rid 'new' or record.id
      if (!$rid) $oForm->_tpl='form-images_wait';

      if ($rid) {
        $oForm->add_fld('img_alt_'.$iid,'text-label'    ,rules: 'words',label: 'Kurtz Beschreibung');  //#+
        $oForm->add_fld('img_des_'.$iid,'textarea-label',rules: 'words',label: 'Beschreibung');        //#+
      }


      $submit=$oForm->submit();
      if ($submit) {
        $img_file=dbx()->get_modul_var($obs_key,'no-img.png','filename-img');

        dbx()->debug("##GEt val=($obs_key) for=($img_key) val=($img_file)");

        if ($img_file) {
           $oForm->_post[$img_key]=$img_file;
           $ok=$oForm->save_post($tab_content,$rid);
           if ($ok) $oForm->_msg_success="Bild: ($img_file)";
           //$oForm->_data[$img_key]=$img_file;
        } else {
           $oForm->add_fld_error($obs_key,'x');
           $imgag_file=dbx()->get_request_var($obs_key,0,'*');
           $oForm->_msg_error="Kein Bild: ($img_file)";
        }
      }
      //dbx_debug("FORM submit=($submit)",$_GET,$_POST);

      if (!empty($_FILES) ) {
        $oUpload=dbx()->get_system_obj('dbxUpload');  // https://github.com/verot/class.upload.php
        $oUpload->upload($_FILES['upload_file']);
        $oUpload->allowed            = array('image/*');
        $oUpload->file_overwrite     = true;
        //$oUpload->file_new_name_body = 'avatar';
        $oUpload->image_resize       = true;
        $oUpload->image_ratio_crop   = true;
        $oUpload->image_x            = 1280;
        $oUpload->image_y            = 800;

        //$oUpload->image_ratio_y      = true;
        $oUpload->process($path);
        if ($oUpload->processed) {
          $img=$oUpload->file_dst_pathname;
          $uploaded_name = $oUpload->file_dst_name;
          $webp_name = $this->convert_image_to_webp($img);
          if ($webp_name !== '') {
            $uploaded_name = $webp_name;
          }
          $field_values['id']=$rid;
          $field_values[$img_key]=$uploaded_name;

          $ok=$oForm->save_post($tab_content,$rid,$field_values);

          //$ok=$db->save($tab_content,$field_values,$rid);
          if ($ok) {
            $data[$img_key]=$uploaded_name;
            $url_img_ext=$url.$uploaded_name;
            $img_src    =$uploaded_name;
            $oForm->set_msg_ok("Bild Datei erfolgreich hochgeladen.");
          }
        } else {
          $content= 'error : ' . $oUpload->error;
          $oForm->set_msg_error("Bild Datei nicht hochgeladen.");
        }
        $oUpload->clean();
  		} else {
        // Update Data if Filebrauser select Img

      }

      $oForm->_msg_info= 'Bild ('.$iid.')';



      $oForm->_data=$this->check_data($iid,$oForm->_data);
      $img_src=$oForm->_data[$img_key];
      $url_img_ext=$url.$img_src;



      $modal1['title']     ='Bild Auswahl';
      $modal1['label']     ='Filebrauser';
      $modal1['dbx_get']   ='?dbx_modul=dbxContent_admin&dbx_run1=content&dbx_run2=ibrowser&dbx_caller='.$obs_key;
      //$modal1['dbx_jsys']  ='dbx_select_max=1&dbx_caller='.$obs_key.'_{i}';


      $img_data['dbx_get']    = '?dbx_modul=dbxContent_admin&dbx_run1=set_observer';
      $img_data['dbx_target'] = $img_key.'_{i}';
      $img_data['id']         = 'selectimg_{i}';
      $img_data['name']       = $img_key;
      $img_data['src']        = $url_img_ext;
      $img_data['class']      = 'dbxImg';



      $oForm->add_rep('rid',$rid);
      $oForm->add_rep('iid',$iid);



      if (!$rid) {
        $observer='obs_content_rid';
        $observ['name']   =  $observer;
        $observ['form']   =  'dbx_form_{i}';
        $observ['observ'] =  'content_rid'; // field must be dev inside one form of the view
        $observ['value']  =  $rid; //  $img_src
        $observ['old']    =  $rid;

        $oForm->add_obj('img_wait','modul|img_wait',"src=$url_img_ext");
        $oForm->add_obj('msg','alert-warning','msg=Ein Content Bild kann erst nach dem Speichern der Systemdaten vom Content gewählt werden.');
        $oForm->add_obj($observer,'observer',$observ);

        $oForm->add_js_observe($observer,11500);   // watch rid from content->sysdata
      }

      if ($rid) {
        $observer='obs_'.$img_key;
        $observ['name']   =  $observer;
        $observ['form']   =  'dbx_form_{i}';
        $observ['observ'] =  $observer; // self
        $observ['value']  =  $img_src;
        $observ['old']    =  $img_src;

        dbx()->debug("## images.class add_observer=($observer)",$observ);

        $oForm->add_obj($observer,'dbx|observer',$observ);

        $oForm->add_obj($img_key,'image-upload',$img_data);
        //$oForm->add_obj('content-modal','modal1b',$modal1);
        $oForm->add_obj('button-modal' ,'button-modal1b',$modal1);
        $oForm->add_obj('button-submit','button-submit','label=Save');

        $oForm->add_js_observe($observer,1500);   // watch Filebrauser select img
        //$oForm->add_js("dbxUploadImg('#uploader_img_{i}_$iid');");

        $oForm->add_js_call("uploader_img_{i}_$iid",'upload');


      }












      $content=$oForm->run();


      return $content;
   }


}

?>
