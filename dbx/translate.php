<?php


//dbx_debug("## Translate Lng=($lng) ##",$content);

if ($lng=='de') {
   $content=str_replace('#Y#','Ja',$content);
   $content=str_replace('#N#','Nein',$content);
   $content=str_replace('#count#','Anzahl',$content);
   $content=str_replace('#pages#','Seiten',$content);
   $content=str_replace('#page#','Seite',$content);
 
}

 

$content=str_replace('#Y#','Yes',$content);
$content=str_replace('#N#','No',$content);

?>