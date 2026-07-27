<?php

class dbxDownload {

  function run($dir,$file,$mobile=1) {
    $content='Hallo';
    $dir_file     = $dir.$file;
    $href_dir_file= dbx()->get_base_url().$dir_file;

    $ar_ext = explode('.', $file);
    $ext = strtolower(end($ar_ext));
    $extensions = array(
      'bmp'   => 'image/bmp',
      'csv'   => 'text/csv',
      'doc'   => 'application/msword',
      'docx'   => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'exe'   => 'application/octet-stream',
      'gif'   => 'image/gif',
      'htm'   => 'text/html',
      'html'  => 'text/html',
      'txt'   => 'text/html',
      'ldt'   => 'text/html',
      'ldtx'  => 'application/ldtx',
      'ico'   => 'image/vnd.microsoft.icon',
      'jpeg'  => 'image/jpg',
      'jpe'   => 'image/jpg',
      'jpg'   => 'image/jpg',
      'pdf'   => 'application/pdf',
      'png'   => 'image/png',
      'ppt'   => 'application/vnd.ms-powerpoint',
      'psd'   => 'image/psd',
      'swf'   => 'application/x-shockwave-flash',
      'tif'   => 'image/tiff',
      'tiff'  => 'image/tiff',
      'xhtml' => 'application/xhtml+xml',
      'xml'   => 'application/xml',
      'xls'   => 'application/vnd.ms-excel',
      'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'zip'   => 'application/zip'
    );

    $ctype = isset($extensions[$ext]) ? $extensions[$ext] : 'application/force-download';
    if ($mobile) $ctype='application/force-download';

    if (file_exists($dir_file) && is_readable($dir_file)) {
      if (!$mobile) {
        // required for IE, otherwise Content-disposition is ignored
        if(ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');

        header('Pragma: public'); // required
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private',false);    // required for certain browsers
        header('Content-Type: '. $ctype);
        header('Content-Disposition: attachment; filename="'. $file .'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '. filesize($dir_file));
        readfile($dir_file);
        $content="Desktop: <a href='$href_dir_file'>$file</a>";
      }
      if ($mobile) {
        header('Pragma: public');
        header('Expires: 0');
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false);
        header('Content-Type: '. $ctype);
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"{$file}\";" );
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '. filesize($dir.$file));
        //@ob_clean();
        readfile($dir_file);
        $content="Mobile: <a href='$href_dir_file'>$file</a>";
      }

    }
    else {
      $content.="<h1>File Not Found:</h1><br>($dir_file)<br> href=($href_dir_file)";
    }
    return $content;
  }

}

