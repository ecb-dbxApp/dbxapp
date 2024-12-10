<?php



class dbxMail {

  Public $errstr;
  Public $headers=array();
  Public $textbody;
  Public $htmlbody;
  Public $attachments=array();
  Public $html_images=array();
  Public $auto_embbed_images;
  Public $boundary;
  Public $sendmail_path;

   //==========================================================
   // Method: send
   //
   // Description:
   // 	Default constructor, sets up default header and boundary.
   //==========================================================
   function init() {

      $semi_rand = md5(time());
      $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";

      $this->attachments = array();
      $this->html_images = array();
      $this->boundary = $mime_boundary;

      $this->auto_embbed_images = true;

      //$this->headers = array(
      //    'From' => 'root@localhost'
      //);
      $this->clear_htmltext();
      $this->clear_htmltext();
      if (!defined('_HTML_CHARSET')) {
         // You can change these settings if necessary
        define('_HTML_CHARSET','utf-8');
        define('_TEXT_CHARSET','utf-8');
        define('_HTML_ENCODING','8bit');
        define('_TEXT_ENCODING','8bit');
     }
   }
   //==========================================================
   // Method: checkEmail
   //
   // Description:
   // 	checks for valid email; true if valid
   //==========================================================
   function checkEmail($inAddress) {
    return (ereg( "^[^@ ]+@([a-zA-Z0-9\-]+\.)+([a-zA-Z0-9\-]{2}|net|com|gov|mil|org|edu|int)\$",$inAddress));
   }

   //==========================================================
   // Method: get_body
   //
   // Description:
   // 	For debugging purposes you can display the body you are about
   //         to send.
   //==========================================================
   function get_body() {
    $retval = $textbody;
    $retval .= $htmlbody;

    foreach($this->attachments as $tblck)
      $retval .= $tblck;

    return $retval;

   }

   //==========================================================
   // Method: get_header
   //
   // Description:
   // 	Convert the values in the header array into the correct format.
   //==========================================================
   function get_header() {
    $retval = "";
    foreach($this->headers as $key => $value)
      $retval .= "$key: $value\n";

    return $retval;

   }

   //==========================================================
   // Method: find_html_images
   //
   // Description:
   // 	checks for emedded images in the mail-body
   //==========================================================
   function find_html_images() {

    if (!$this->auto_embbed_images)	return;

    preg_match_all('|<img .*src="(.+)".*>|Ui', $this->htmlbody, $image_matches, PREG_PATTERN_ORDER);
    $html_images = array_unique($image_matches[1]);

    foreach ($html_images as $filename) {
    	if (file_exists($filename)){
    		$this->htmlbody = str_replace($filename, 'cid:part.'.md5(basename($filename)), $this->htmlbody);
    		$this->html_images[] = $filename;
  	  }
    }
   }

   //==========================================================
   // Method: set_header
   //
   // Description:
   // 	Add your own header entry or modify a header.
   //==========================================================
   function set_header($name, $value) {
     $this->headers[$name] = $value;
   }

   //==========================================================
   // Method: attachfile
   //
   // Description:
   // 	Attach a file to the message.
   //==========================================================
		function attachfile($file, $disp='attachment', $contentType='', $cid='') {
			//echo "<br>Attach=$file";
			if(!($fd = fopen($file, "r"))) {
			  //echo "<br>Error read $file";
			  $this->errstr = "Error opening $file for reading.";
			  return 0;
			}
			$_buf = fread($fd, filesize($file));
			fclose($fd);

			$fname = $file;
			for($x = (strlen($file)-1); $x > 0; $x--)
			  if($file[$x] == "/")
			    $fname = substr($file, $x, strlen($file) - $x);

			if ($contentType == ''){
			   $contentType = $this->getContentType($file);
			   //echo " c-type=$contentType";
			   if ($contentType=="text/plain") {
			      $_buf=dbx_merge_Data($_buf);
			   }

			}
			$fname=basename($fname);
			//$fname="test.txt";

			// Convert to base64 becuase mail attachments are not binary safe.
			$_buf = chunk_split(base64_encode($_buf));

			$this->attachments[$file]  = "\n--".$this->boundary."\n";
			$this->attachments[$file] .= "Content-Type: $contentType; \n   name=\"$fname\"\n";
			$this->attachments[$file] .= "Content-Transfer-Encoding: base64\n";

			if ($cid == '') $cid="file";

			if ($cid != ''){
			 	$this->attachments[$file] .= "Content-ID: <".$cid.">\n";
			}

			$this->attachments[$file] .= "Content-Disposition: ".$disp."; \n   filename=\"$fname\"\n\n";
			$this->attachments[$file] .= $_buf;

			return 1;
		}


   //==========================================================
   // Method: getContentType()
   //
   // Description:
   //
   //==========================================================
   function getContentType($inFileName){
    //--strip path
    $inFileName = basename($inFileName);

    //--check for no extension
    if(strrchr($inFileName,".") == false){
        return "application/octet-stream";
    }

    //--get extension and check cases
    $extension = strrchr($inFileName,".");

    switch($extension){
    	case ".gz":	return "application/x-gzip";
    	case ".zip":  return "application/zip";
    	case ".tar":	return "application/x-tar";
    	case ".htm":	return "text/html";
    	case ".html":	return "text/html";
    	case ".txt":  return "text/plain";
    	case ".jpg":	return "image/jpeg";
    	case ".gif":	return "image/gif";
    	case ".png":	return "image/png";
    	case ".swf":	return "application/x-shockwave-flash";

    	default:	return "application/octet-stream";
    }
    return "application/octet-stream";
   }

   //==========================================================
   // Method: bodytext
   //
   // Description:
   // 	Set the content type to text/plain for the text message.
   //==========================================================
   function bodytext($text) {
    if(strlen(trim($text)) > 0){
     	$this->textbody = $text;
    }
   }

   function bodyhtml($text) {
    if(strlen(trim($text)) > 0){
     	$this->htmlbody = $text;
    }
   }


   //==========================================================
   // Method: format_text
   //
   // Description:
   // 	Set the content type to text for the message.
   //==========================================================
   function format_text() {
    $outTextHeader  = "Content-Type: text/plain; charset="._TEXT_CHARSET."\n";
    $outTextHeader .= "Content-Transfer-Encoding: "._TEXT_ENCODING."\n\n";
    $outTextHeader .= $this->textbody."\n";

    return $outTextHeader;
   }


   //==========================================================
   // Method: htmltext
   //
   // Description:
   //
   //==========================================================
   function htmltext($text) {
    if(strlen(trim($text)) > 0){
    	$this->htmlbody .= $text;
    }
   }

   //==========================================================
   // Method: format_htmltext
   //
   // Description:
   // 	Set the content type to html for the message.
   //==========================================================
   function format_htmltext() {
    $outTextHeader  = "Content-Type: text/html; charset="._HTML_CHARSET."\n";
    $outTextHeader .= "Content-Transfer-Encoding: "._HTML_ENCODING."\n\n";

    if (substr($this->htmlbody, 0, 6) != "<html>"){
     	$outTextHeader .= "<html>\n".$this->htmlbody."\n</html>\n";
    } else {
      $outTextHeader .= $this->htmlbody."\n";
    }

    return $outTextHeader;
   }


   function clear_bodytext() { $this->textbody = ""; }
   function clear_htmltext() { $this->htmlbody = ""; }
   function get_error() { return $this->errstr; }

   //==========================================================
   // Method: send
   //
   // Description:
   // 	Send the headers and body using php's built in mail.
   //==========================================================
   function send($to = "root@localhost", $subject = "Default Subject") {
    $_body="";
    if(isset($this->sendmail_path))
     	@ini_set("sendmail_path", $this->sendmail_path);

    //---------------------------------
    // Nur Text ohne Attachments
    //---------------------------------
    if (($this->textbody != "") and (count($this->attachments) == 0)){
      //$_body  = $this->format_text(); // ?
    	$_body .= $this->textbody;
    }

    // Bei HTML-Mail nach Embeddet Images suchen
    if ($this->htmlbody != "") {
	    // Emedded Images?
     	$this->find_html_images();
      // Add Emedded Images
    	for($i=0; $i<count($this->html_images); $i++) {
    		$this->attachfile($this->html_images[$i], 'inline', '', 'part.'.md5(basename($this->html_images[$i])));
    	}
    }

    //---------------------------------
    // Nur HTML ohne Attachments
    //---------------------------------
    if (($this->htmlbody != "") and (count($this->attachments) == 0)){
        $this->set_header('MIME-Version', '1.0');
       	$this->set_header('Content-Type', 'text/html; charset='._HTML_CHARSET);
      	$_body .= $this->htmlbody;
    } elseif (count($this->attachments) > 0){

    	//---------------------------------
    	// HTML/Text + Attachment (Mixed)
    	//---------------------------------
    	$this->set_header('MIME-Version', '1.0');
    	$this->set_header('Content-Type', 'multipart/mixed;
    	boundary="'.$this->boundary.'"');

    	$_body .= "\n\nThis is a multi-part message in MIME format.\n\n";

    	if ($this->textbody != "") {
    		$_body .= "--".$this->boundary."\n";
    		$_body .= $this->format_text();
    	}

    	if ($this->htmlbody != "") {
    		$_body .= "--".$this->boundary."\n";
    		$_body .= $this->format_htmltext();
    	}

    	// Add Emedded Images
    	if (count($this->html_images) > 0){
    		for($i=0; $i<count($this->html_images); $i++) {
    			$this->attachfile($this->html_images[$i], 'inline', '', 'part.'.md5(basename($this->html_images[$i])));
    		}
    		$this->set_header('Content-Type', 'multipart/related;
    		boundary="'.$this->boundary.'"');
    	}

    	// Attachments
    	foreach($this->attachments as $tblck){
    		$_body .= $tblck;
    	}
    	$_body .= "\n--$this->boundary--\n";
    }

     //------------------------------------------
     // send the e-mail
     //$to="immo.testserver@dbxwebapp.org";
     //------------------------------------------
     return @mail($to, $subject, $_body, $this->get_header());

   }

 //===============
 // END OF CLASS
 //===============

}


?>