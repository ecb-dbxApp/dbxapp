<?php

class dbxObj {}

require_once dirname(__DIR__) . '/include/dbxContent_cms.class.php';

$class = new ReflectionClass('dbx\\dbxContent_admin\\dbxContent_cms');
$cms = $class->newInstanceWithoutConstructor();
$sanitize = $class->getMethod('sanitize_content_html');
$sanitize->setAccessible(true);

$html = '<p class="dbx-cms-inline-media" style="float: left; margin-right: 1.5rem; position: fixed;">'
   . '<img data-cms-media-id="108" src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=108">'
   . '</p><p>Text rechts neben dem Bild.</p>';
$out = $sanitize->invoke($cms, $html);

if (strpos($out, 'float: left;') === false || strpos($out, 'margin-right: 1.5rem;') === false) {
   fwrite(STDERR, "FAIL: Linke Bildausrichtung wurde beim Sanitizing entfernt.\n$out\n");
   exit(1);
}
if (strpos($out, 'position:') !== false) {
   fwrite(STDERR, "FAIL: Nicht freigegebene CSS-Eigenschaften wurden gespeichert.\n$out\n");
   exit(2);
}

$bare_image = $sanitize->invoke(
   $cms,
   '<img data-cms-media-id="108" class="dbxki-visual" src="index.php?dbx_modul=dbxContent&amp;dbx_run1=media&amp;dbx_mid=108" style="float: left; margin-right: 1.5rem; width: 400px; height: auto; max-width: 100%; max-height: 480px; width: expression(alert(1));">'
      . '<p>Text rechts neben dem zweiten Bild.</p>'
);
if (strpos($bare_image, 'float: left;') === false
   || strpos($bare_image, 'margin-right: 1.5rem;') === false
   || strpos($bare_image, 'width: 400px;') === false
   || strpos($bare_image, 'height: auto;') === false
   || strpos($bare_image, 'max-width: 100%;') === false
   || strpos($bare_image, 'max-height: 480px;') === false
   || strpos($bare_image, 'expression(') !== false) {
   fwrite(STDERR, "FAIL: Ausrichtung oder Groesse eines Bildes ohne Medien-Wrapper wurde entfernt.\n$bare_image\n");
   exit(3);
}

$centered = $sanitize->invoke(
   $cms,
   '<img src="bild.webp" style="display: block; margin-left: auto; margin-right: auto; transform: rotate(5deg);">'
);
if (strpos($centered, 'display: block;') === false
   || strpos($centered, 'margin-left: auto;') === false
   || strpos($centered, 'margin-right: auto;') === false
   || strpos($centered, 'transform:') !== false) {
   fwrite(STDERR, "FAIL: Zentrierte Bildausrichtung ist ungueltig.\n$centered\n");
   exit(4);
}

$ordinary = $sanitize->invoke($cms, '<div style="float: right; text-align: center;">Text</div>');
if (strpos($ordinary, 'float:') !== false || strpos($ordinary, 'text-align: center;') === false) {
   fwrite(STDERR, "FAIL: CSS-Whitelist fuer normalen Content ist ungueltig.\n$ordinary\n");
   exit(5);
}

$colored_button = $sanitize->invoke(
   $cms,
   '<a class="btn btn-outline-btn-lg mb-2" href="kontakt" style="background-color: #ff9900; color: rgb(255, 255, 255); background-image: url(javascript:alert(1)); position: fixed;">Projekt besprechen</a>'
);
if (strpos($colored_button, 'background-color: #ff9900;') === false
   || strpos($colored_button, 'color: rgb(255, 255, 255);') === false
   || strpos($colored_button, 'background-image:') !== false
   || strpos($colored_button, 'position:') !== false
   || strpos($colored_button, 'javascript:') !== false) {
   fwrite(STDERR, "FAIL: Sichere Jodit-Farben werden nicht dauerhaft gespeichert oder unsichere Styles passieren den Filter.\n$colored_button\n");
   exit(6);
}

$unsafe_color = $sanitize->invoke(
   $cms,
   '<span style="color: expression(alert(1)); background-color: var(--danger);">Text</span>'
);
if (strpos($unsafe_color, 'style=') !== false || strpos($unsafe_color, 'expression(') !== false || strpos($unsafe_color, 'var(') !== false) {
   fwrite(STDERR, "FAIL: Unsichere Farbwerte wurden gespeichert.\n$unsafe_color\n");
   exit(7);
}

echo "OK dbxContent CMS image alignment\n";
