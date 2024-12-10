<?php
    ob_start();
    Header("Content-type: image/png");
    //Header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    //Header("Last-Modified: " . gmdate ("D, d M Y H:i:s") . " GMT");
    //Header("Pragma: no-cache");
    
    header("Cache-Control: public, max-age=36000"); // 10 Stunde Caching
    header("Pragma: cache");
    header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");

    if (!isset($_GET['ergwert'])) {
      if (isset($_GET['?ergwert'])) $_GET['ergwert']=$_GET['?ergwert']; // hot Fix für exe server  
    }

    /*
    $file="C:\dbx\debug_img.txt";
    $txt = print_r($_GET, true);
    file_put_contents($file, $txt, FILE_APPEND);
    */



    $ErgWert = $_GET['ergwert'];
    $RBOg =    $_GET['nwog'];
    $RBUg =    $_GET['nwug'];
    $width =   $_GET['width'];
    $height =  $_GET['height'];
    $base_color = $_GET['bgcol'];
    
    



    $iEinfach=0;

    $im = @ImageCreate ($width, $height)
          or die ("Kann keinen neuen GD-Bild-Stream erzeugen");
    $background_color = ImageColorAllocate ($im, 0xF4, 0xF6, 0xFF);
    $line_color = ImageColorAllocate ($im, 0x41, 0x43, 0x68);
    $mark_color = ImageColorAllocate ($im, 243, 2, 191);

    $base_red   = hexdec(substr($base_color, 0, 2));
    $base_green = hexdec(substr($base_color, 2, 2));
    $base_blue  = hexdec(substr($base_color, 4, 2));

    for ($i = 0; $i <= $width / 6; $i++) {
       if ($iEinfach) {
          // ------------------ Einfacher Farbverlauf -----------------------------------------
          $bgline1_color = @ImageColorAllocate ($im, 255, $i / ($width / 6) * 128, 0);
          $bgline2_color = @ImageColorAllocate ($im, 255, 127 + $i / ($width / 6) * 128, 0);
          $bgline3_color = @ImageColorAllocate ($im, 255 - $i / ($width / 6) * 255, 255, 0);
          $bgline4_color = @ImageColorAllocate ($im, $i / ($width / 6) * 255, 255, 0);
          $bgline5_color = @ImageColorAllocate ($im, 255, 255 - $i / ($width / 6) * 128, 0);
          $bgline6_color = @ImageColorAllocate ($im, 255, 127 - $i / ($width / 6) * 128, 0);
          // ----------------------------------------------------------------------------------
        } else {
          $bgline1_color = @ImageColorAllocate ($im, 255, $base_green + $i / ($width / 6) * ((255 - $base_green) / 2), $base_blue);
          $bgline2_color = @ImageColorAllocate ($im, 255, ($base_green + (255 - $base_green) / 2) + $i / ($width / 6) * ((255 - $base_green) / 2), $base_blue);
          $bgline3_color = @ImageColorAllocate ($im, 255 - $i / ($width / 6) * ((255 - $base_red) / 2), 255, $base_blue);
          $bgline4_color = @ImageColorAllocate ($im, ($base_red + (255 - $base_red) / 2) + $i / ($width / 6) * ((255 - $base_red) / 2), 255, $base_blue);
          $bgline5_color = @ImageColorAllocate ($im, 255, 255 - $i / ($width / 6) * ((255 - $base_green) / 2), $base_blue);
          $bgline6_color = @ImageColorAllocate ($im, 255, ($base_green + (255 - $base_green) / 2) - $i / ($width / 6) * ((255 - $base_green) / 2), $base_blue);
        }

 
        @ImageLine ($im, $i + $width / 6 * 0, 0, $i + $width / 6 * 0, $height, $bgline1_color);
        @ImageLine ($im, $i + $width / 6 * 1, 0, $i + $width / 6 * 1, $height, $bgline2_color);
        @ImageLine ($im, $i + $width / 6 * 2, 0, $i + $width / 6 * 2, $height, $bgline3_color);
        @ImageLine ($im, $i + $width / 6 * 3, 0, $i + $width / 6 * 3, $height, $bgline4_color);
        @ImageLine ($im, $i + $width / 6 * 4, 0, $i + $width / 6 * 4, $height, $bgline5_color);
        @ImageLine ($im, $i + $width / 6 * 5, 0, $i + $width / 6 * 5, $height, $bgline6_color);
    }

    @ImageLine ($im, 3, $height / 2, $width - 3, $height / 2, $line_color);
    @ImageLine ($im, $width / 3, 0, $width / 3, $height, $line_color);
    @ImageLine ($im, $width / 3 * 2, 0, $width / 3 * 2, $height, $line_color);

    $difRef = $RBOg - $RBUg;
    $RBUug = $RBUg - $difRef;
    $RBOog = $RBOg + $difRef;
    $difGanzRef = $RBOog - $RBUug;
    $lageInRB = $ErgWert - $RBUug;
    if($difGanzRef != 0) { $relPos = (100 / $difGanzRef) * $lageInRB; }
    $PosInRBStr = ($width / 100) * $relPos;
    if($PosInRBStr < 3) { $PosInRBStr = 3; }
    else if($PosInRBStr > $width - 3) { $PosInRBStr = $width - 3; }
    else { $PosInRBStr = $PosInRBStr; }
    if($ErgWert < $RBUg || $ErgWert > $RBOg) {  }

    @ImageArc ($im, $PosInRBStr, $height / 2, 9, 9, 9, 360, $mark_color);
    @ImageLine ($im, $PosInRBStr, 3, $PosInRBStr, $height - 2, $mark_color);
    @ImagePNG ($im);
    @ImageDestroy ($im);
    ob_end_flush();
