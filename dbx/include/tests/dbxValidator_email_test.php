<?php

$root = dirname(__DIR__, 2);
require_once $root . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'dbxValidator.class.php';

$validator = new dbxValidator();
$valid = array(
   'name@example.org',
   'first.last+tag@sub.example.co.uk',
   'customer_42@example-domain.de',
);
$invalid = array(
   '',
   'name',
   'name@',
   '@example.org',
   '.name@example.org',
   'name.@example.org',
   'first..last@example.org',
   'name@example..org',
   'name@-example.org',
   'name@example-.org',
   'name@example.c',
   'name example@example.org',
);

foreach ($valid as $email) {
   if (!$validator->validate($email, 'email|min=6|max=180', 'email')) {
      fwrite(STDERR, "Gueltige E-Mail-Adresse wurde abgelehnt: {$email}\n");
      exit(1);
   }
}

foreach ($invalid as $email) {
   if ($validator->validate($email, 'email|min=6|max=180', 'email')) {
      fwrite(STDERR, "Ungueltige E-Mail-Adresse wurde akzeptiert: {$email}\n");
      exit(1);
   }
}

$fd = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxContact' . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR . 'contact-form.fd.php');
$dd = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxContact' . DIRECTORY_SEPARATOR . 'dd' . DIRECTORY_SEPARATOR . 'contactRequest.dd.php');
$download_fd = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxDownLoad' . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR . 'download-link.fd.php');
$download_fd_en = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxDownLoad' . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR . 'download-link_en.fd.php');
$download_fd_es = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxDownLoad' . DIRECTORY_SEPARATOR . 'fd' . DIRECTORY_SEPARATOR . 'download-link_es.fd.php');
$download_class = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'dbxDownLoad' . DIRECTORY_SEPARATOR . 'dbxDownLoad.class.php');
$flowers_form_css = (string)file_get_contents($root . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'flowers' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'c-form.css');
if (strpos($fd, "\$field['rules']='email|min=6|max=180';") === false
   || strpos($dd, "\$field['rules']='email|min=6|max=180';") === false
   || strpos($download_fd, "\$field['rules'] = 'email|min=6|max=180';") === false
   || strpos($download_fd_en, "\$field['rules'] = 'email|min=6|max=180';") === false
   || strpos($download_fd_es, "\$field['rules'] = 'email|min=6|max=180';") === false
   || strpos($download_class, "get_post_data('email', '', 'email|min=6|max=180')") === false
   || strpos($download_class, "set_msg_info('')") === false
   || strpos($download_class, "get_fd_message('validation_error')") === false
   || strpos($download_class, "get_fd_message('send_success')") === false
   || strpos($download_class, "format_fd_message(") === false
   || strpos($download_class, "'sent_to'") === false
   || strpos($download_fd, "\$messages['validation_error']") === false
   || strpos($download_fd_en, "\$messages['validation_error']") === false
   || strpos($download_fd_es, "\$messages['validation_error']") === false
   || strpos($flowers_form_css, '.form-control.fld-error') === false
   || strpos($flowers_form_css, 'border-color: var(--dbx-danger') === false) {
   fwrite(STDERR, "Das E-Mail-Feld des Kontaktformulars ist nicht in FD und DD als Pflichtfeld definiert.\n");
   exit(1);
}

echo "OK: E-Mail-Syntax und Pflichtfeldregeln von Kontakt- und Demo-Download-Formular geprueft.\n";
