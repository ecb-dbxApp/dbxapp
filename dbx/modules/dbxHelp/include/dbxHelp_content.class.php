<?php
namespace dbx\dbxHelp;




class dbxHelp_content {

   Public $oTPL;

   public function __construct() {
     $this->oTPL = dbx()->get_system_obj('dbxTPL');
   }

   function getServerInfoHTML() {
    // Serverinformationen sammeln
    $info = [
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'Server Name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
        'PHP Version' => PHP_VERSION,
        'PHP SAPI' => php_sapi_name(),
        'Installed Modules' => implode(', ', get_loaded_extensions()), // Alle Module als String
        'Operating System' => PHP_OS,
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'Server Protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'N/A',
        'Server Port' => $_SERVER['SERVER_PORT'] ?? 'N/A',
        'Client IP' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    ];

    $base     =dbx()->get_base_url();
    $praxis   =dbx()->get_cfg('myOrderLDT','praxis');
    $path_pat =dbx()->get_cfg('myOrderLDT','import_pat');
    $exe_medi =dbx()->get_cfg('myOrderLDT','medisoft');
    $path_medi=dbx()->get_cfg('myOrderLDT','path_medisoft');
    $version  =dbx()->get_cfg('dbx', 'version');

    // HTML-String für Bootstrap-Tabelle erstellen
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-bordered">';
    $html .= '<thead class="thead-dark"><tr><th>Eintrag</th><th>Wert</th></tr></thead>';
    $html .= '<tbody>';
    $html .= "<tr><td>Version</td><td>{$version}</td></tr>";
    $html .= "<tr><td>Praxis ID für das Labor</td><td>{$praxis}</td></tr>";
    $html .= "<tr><td>Praxis Software </td><td>{$exe_medi}</td></tr>";

    $html .= "<tr><td>Datei für Patientendaten</td><td>{$path_pat}</td></tr>";
    $html .= "<tr><td>Datei für Befunde Import</td><td>{$path_medi}</td></tr>";
    $html .= "<tr><td>Base URL System</td><td>{$base}</td></tr>";

    // Array durchlaufen und als Zeilen in der Tabelle anzeigen
    foreach ($info as $key => $value) {
        if ($key === 'Installed Modules') {
            // Separate die Module in Zeilen mit max. 8 Modulen pro Zeile
            $modules = explode(', ', $value);
            $formattedModules = '';
            foreach (array_chunk($modules, 8) as $chunk) {
                $formattedModules .= implode(', ', $chunk) . '<br>';
            }
            $value = rtrim($formattedModules, '<br>'); // Letztes <br> entfernen
        }

        $html .= "<tr><td>{$key}</td><td>{$value}</td></tr>";
    }

    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';

    // CSS hinzufügen, um die Modulzeilen ohne horizontale Scrollbar zu ermöglichen
    $html .= '
    <style>
        .table td {
            white-space: normal; /* Zeilenumbruch innerhalb der Zelle */
        }
    </style>';

    return $html;
}




  public function run() {
    $content='xxx';
    $cid=dbx()->get_modul_var('dbx_cid',0,'int');
    if ($cid) $content="[modul=dbxContent]cid=$cid&dbx_run1=show[/modul]";
    if (!$cid) {
      $content=$this->getServerInfoHTML();

    }

    return $content;
  } // run()


} // class

