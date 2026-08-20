<?php

declare(strict_types=1);

namespace dbx\dbxAdmin;

dbx()->get_system_obj('dbxReport', 'use');

class dbxReport_Tables extends \dbxReport {


    /**
     * Scannt ein Verzeichnis nach allen *.dd.php Dateien und gibt ein Array mit den Dateinamen zurück,
     * bei denen die Bedingungen $table['table'] == $db_table und $table['server'] == $db_server erfüllt sind.
     *
     * @param string $db_server Der Name des Datenbank-Servers, der in der $table['server'] Variable gesucht wird.
     * @param string $db_table Der Name der Datenbank-Tabelle, der in der $table['table'] Variable gesucht wird.
     * @param string $path Der Pfad, in dem nach den *.dd.php Dateien gesucht werden soll. Standardmäßig das aktuelle Verzeichnis.
     * @return array Ein Array mit den Dateinamen der passenden DataDictionary-Dateien.
     */
    private function get_dd_exist($db_server, $db_table, $path = '.') {
        $datadics = array();
        
        // Sicherstellen, dass der Pfad mit einem Slash endet
        $path = rtrim($path, '/') . '/';
        
        // Verzeichnis nach *.dd.php Dateien durchsuchen
        $files = glob($path . '*.dd.php');
        
        foreach ($files as $file) {
            // Temporäre Variablen initialisieren, um Konflikte zu vermeiden
            $table = array();
            $fields = array();
            
            // Datei einbinden
            include $file;
            
            // Überprüfen, ob die Bedingungen erfüllt sind
            if (isset($table['table']) && $table['table'] == $db_table && 
                isset($table['server']) && $table['server'] == $db_server) {
                $datadics[] = $file;
            }
        }
        
        return $datadics;
    }
    
   

    /**
     * Verarbeitet den übergebenen Inhalt und aktualisiert den Datensatz mit einer Liste der gefundenen DataDictionary-Dateien.
     * Nach jedem DataDictionary-Eintrag wird der $but_edit-Button angezeigt, und am Ende wird der $but_add-Button angehängt.
     * Die Buttons haben die gleiche Größe, und die Einträge werden nebeneinander angezeigt.
     *
     * @param string $content Der zu verarbeitende Inhalt (wird unverändert zurückgegeben).
     * @return string Der unveränderte Inhalt.
     */
    public function run_body($content) {
        // Aktuellen Datensatz aus der Instanzvariable holen
        $record = $this->_record;
        
        // Informationen aus dem Datensatz extrahieren
        $server = $record['server']; // Name des Datenbank-Servers
        $table  = $record['name'];   // Name der Datenbank-Tabelle
        $path   = dbx()->get_base_dir() . 'dbx/modules/dbx/dd/'; // Pfad zu den DataDictionary-Dateien
        $rid    = $server.'|'.$table;

        // DataDictionary-Dateien suchen
        $dds = $this->get_dd_exist($server, $table, $path);
        
        // Dateinamen verarbeiten:
        // 1. '.dd.php' entfernen
        // 2. Nach jedem Dateinamen $but_edit einfügen
        $dd_list = implode('', array_map(function($file) {
            // Den Dateinamen ohne '.dd.php' extrahieren
            $dd = basename($file, '.dd.php');
            
            // Button für das Bearbeiten eines DataDictionary

            $but_edit = '<a class="nav-link openWin" href="?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=list_dd&dbx_run3=row_edit&rid='. $dd .'" data-dbx_win_width="1400" data-dbx_win_height="800"><i class="bi bi-pencil-square"></i></a>';


            // Den DataDictionary-Eintrag und den Button kombinieren
            return '<span class="dd-item">' . $dd . '</span>' . $but_edit;
        }, $dds));
        
        // Button für das Hinzufügen eines neuen DataDictionary
        $base_url=dbx()->get_base_url();
        $bt['title']      = $this->get_fd_message('create_dd_title');
        $bt['buttonText'] = "<i class='bi bi-plus-lg'></i>"; // Text des Buttons
        $bt['class']      = "btn btn-primary btn-sm p-1 d-flex align-items-center justify-content-center";
        $bt['style']      = "width: 36px; height: 24px; cursor: pointer; margin-left: 5px;"; 
        $bt['url']        = $base_url."?dbx_modul=dbxAdmin&dbx_run1=datadic&dbx_run2=add_dd&rid=".$rid;  
        $bt['modalClass'] = "modal-xl";
        $bt['returnJs']   = "dbxReSendForm(\'#dbx_form_{i}\')"; //"alert(\'JS run\');";
        $bt['isPrompt']   = 'false' ; // true, wenn es sich um ein Prompt-Modal handelt
        $bt['selectValueClass'] = ""; // Nur relevant, wenn $isPrompt true ist
        $bt['selectTarget']     = ""; // Nur relevant, wenn $isPrompt true ist
        $but_add =$this->get_tpl('button_modal',$bt);

  
                 


        // Kombiniere die DataDictionary-Einträge und die Buttons in einer Flexbox-Struktur
        $dd_list = '<div class="d-flex justify-content-between align-items-center w-100">
                        <span class="dd-list">' . $dd_list . '</span>
                        ' . $but_add . '
                    </div>';
        
        // Aktualisiere den Datensatz mit der Liste der gefundenen Dateien
        $record['dd'] = $dd_list;
        
        // Aktualisiere die Instanzvariable mit dem modifizierten Datensatz
        $this->_record = $record;
        
        // Gib den unveränderten Inhalt zurück
        return $content;
    }
    

}    
    
