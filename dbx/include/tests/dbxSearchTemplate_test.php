<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/dbxKernel.php';

$form = dbx()->get_system_obj('dbxForm');
$token = $form->create_fld(array(
   'name' => 'probe',
   'tpl' => 'dbx|search',
   'data' => array(
      'name' => 'probe',
      'label' => '<strong>Suche</strong>',
   ),
   'options' => array(),
   'value' => '"><script>alert(1)</script>',
   'error' => 0,
   'verify' => 1,
), 7);

$norep_key = trim((string)$token, '[]');
$html = (string)($_SESSION['dbx']['norep'][$norep_key] ?? '');
if ($html === '') {
   fwrite(STDERR, "FAIL: dbxForm hat das Suchfeld nicht gerendert.\n");
   exit(1);
}
if (strpos($html, '<strong>Suche</strong>') === false) {
   fwrite(STDERR, "FAIL: Vertrauenswuerdiges Template-HTML wurde escaped.\n");
   exit(2);
}
if (strpos($html, 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"') === false) {
   fwrite(STDERR, "FAIL: Der Benutzerwert wurde nicht einmal korrekt escaped.\n");
   exit(3);
}
if (strpos($html, '&amp;quot;') !== false || strpos($html, '&amp;lt;') !== false) {
   fwrite(STDERR, "FAIL: Der Benutzerwert wurde doppelt escaped.\n");
   exit(4);
}
if (strpos($html, 'value=""><script>alert(1)</script>') !== false) {
   fwrite(STDERR, "FAIL: Der Benutzerwert wurde als HTML ausgegeben.\n");
   exit(5);
}
if (strpos($html, 'id="probe_7"') === false || strpos($html, 'for="probe_7"') === false) {
   fwrite(STDERR, "FAIL: Label und Eingabefeld verwenden keine gemeinsame Template-ID.\n");
   exit(6);
}
if (strpos($html, 'class="dbx-search-icon"') === false || strpos($html, 'class="dbx-clear-btn"') === false) {
   fwrite(STDERR, "FAIL: Lupe oder Reset-Schaltflaeche fehlt im Suchfeld-Template.\n");
   exit(7);
}

echo "OK: Suchfeld nutzt dbxForm/dbxTPL; Template-HTML bleibt erhalten und Benutzerwerte werden einmal escaped.\n";
