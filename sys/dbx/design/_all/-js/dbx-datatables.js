function dbxDataTable(id) {
  id=dbx_check_is_id(id);
  var copy ='copy';
  var excel='excel'; 
  var pdf  ='pdf';
  var print='print';
  
  excel='';
  copy='';
    
  var oTable = $(id).DataTable({
    "sScrollY": "100%",
    "iDisplayLength": 25,
    "pageLength": 25,
    "bJQueryUI": false,
    "bStateSave": true,
    "searching": true,
    "ordering": true,
    "responsive": true, 


    "language": {
        
      "sEmptyTable":   	"Keine Daten in der Tabelle vorhanden",
      "sInfo":         	"_START_ bis _END_ von _TOTAL_ Einträgen",
      "sInfoEmpty":    	"0 bis 0 von 0 Einträgen",
      "sInfoFiltered": 	"(gefiltert von _MAX_ Einträgen)",
      "sInfoPostFix":  	"",
      "sInfoThousands":  	".",
      "sLengthMenu":   	"_MENU_ Einträge anzeigen",
      "sLoadingRecords": 	"Wird geladen...",
      "sProcessing":   	"Bitte warten...",
      "sSearch":       	"Suchen:",
      "sZeroRecords":  	"Keine Einträge vorhanden.",
      "oPaginate": {
        "sFirst":    	"|<",
        "sPrevious": 	"<",
        "sNext":     	">",
        "sLast":     	">|"
      },
      "aria": {
        "sSortAscending":  ": aktivieren, um Spalte aufsteigend zu sortieren",
        "sSortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
      },
      "select": {
        "rows": {
            "_": "%d Zeilen ausgewählt",
            "1": "1 Zeile ausgewählt"
        },
      },
      "buttons": {
        "print": "<i class='bi bi-printer'></i></span>",
        "copy": "Kopieren",
        "copyTitle": "In Zwischenablage kopieren",
        "copySuccess": {
            "_": "%d Zeilen kopiert",
            "1": "1 Zeile kopiert"
        },
        "collection": "Aktionen <span class=\"ui-button-icon-primary ui-icon ui-icon-triangle-1-s\"><\/span>",
        "colvis": "<i class='bi bi-table'></i>",
        "colvisRestore": "Sichtbarkeit wiederherstellen",
        "csv": "CSV",
        "excel": "Excel",
        "pageLength": {
            "-1": "Alle Zeilen anzeigen",
            "1": "Zeige 1 Zeile",
            "_": "Zeige %d Zeilen"
        },
        "pdf": "<i class='bi bi-file-pdf'></i>",
        "createState": "Ansicht erstellen",
        "removeAllStates": "Alle Ansichten entfernen",
        "removeState": "Entfernen",
        "renameState": "Umbenennen",
        "savedStates": "Gespeicherte Ansicht",
        "stateRestore": "Ansicht %d",
        "updateState": "Aktualisieren",
        "copyKeys": "Drücken Sie die Taste <i>STRG<\/i> oder <i>⌘<\/i> + <i>C<\/i> um die Tabelle<br \/>in den Zwischenspeicher zu kopieren.<br \/><br \/>Um den Vorgang abzubrechen, klicken Sie die Nachricht an oder drücken Sie die Escape-Taste."
    },

    },

    
    dom: 'Bfrtip',
    columnDefs: [
        {
            targets: 1,
            className: 'noVis'
        }
    ],
    buttons: [
      'pageLength',
        { extend: 'colvis',  columns: ':not(.noVis)' },
        { extend: copy    , className: 'TabelButtonCopy' },
        { extend: excel   , className: 'TableButtonExcel' },
        { extend: pdf     , className: 'TableButtonPdf' },
        { extend: print   , className: 'TableButtonPrint' },                                  
    ],    
    lengthMenu: [
      [10, 15, 20, 25, 50, -1],
      [10, 15, 20, 25, 50, '*'],
    ]


});
var but='';
if (!excel) { $('.TableButtonExcel').remove(); }
if (!copy)  { $('.TabelButtonCopy').remove(); }

$('.dt-buttons').addClass('float-end'); 
$('.dt-buttons').addClass('left-space');

$(".no-sort").removeClass("sorting");
$(".no-sort").removeClass("sorting_asc");
$(".no-sort").removeClass("noVis");
$(".no-sort").unbind();


$(id).css({ width: "100%" });
 


}

function datatable_fix(id) {
  id=dbx_check_is_id(id);
  var table = $(id).DataTable();
  table.columns.adjust();

  $.each($(id).find("th"), function (key, val2) {
     $(key).removeAttr("style");
  });

}


function dbxDataTable1(id) {
  id=dbx_check_is_id(id);
  var copy ='';
  var excel=''; 
  var pdf  ='';
  var print='';
  
    
  var oTable1 = $(id).DataTable({
    "sScrollY": "100%",
    "iDisplayLength": 1,
    "pageLength": 999,
    "bJQueryUI": false,
    "bStateSave": true,
    "searching": true,
    "ordering": false,
    "responsive": true, 
   

    "language": {
        
      "sEmptyTable":   	"Keine Daten in der Tabelle vorhanden",
      "sInfo":         	"_START_ bis _END_ von _TOTAL_ Einträgen",
      "sInfoEmpty":    	"0 bis 0 von 0 Daten",
      "sInfoFiltered": 	"(gefiltert von _MAX_ Daten)",
      "sInfoPostFix":  	"",
      "sInfoThousands":  	".",
      "sLengthMenu":   	"_MENU_ Daten anzeigen",
      "sLoadingRecords": 	"Wird geladen...",
      "sProcessing":   	"Bitte warten...",
      "sSearch":       	"Suchen:",
      "sZeroRecords":  	"Keine Daten vorhanden.",
      "oPaginate": {
        "sFirst":    	"",
        "sPrevious": 	"",
        "sNext":     	"",
        "sLast":     	"|"
      },
      "aria": {
        "sSortAscending":  ": aktivieren, um Spalte aufsteigend zu sortieren",
        "sSortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
      },
      "select": {
        "rows": {
            "_": "%d Zeilen ausgewählt",
            "1": "1 Zeile ausgewählt"
        },
      },
      "buttons": {
        "print": "<i class='bi bi-printer'></i></span>",
        "copy": "Kopieren",
        "copyTitle": "In Zwischenablage kopieren",
        "copySuccess": {
            "_": "%d Zeilen kopiert",
            "1": "1 Zeile kopiert"
        },
        "collection": "Aktionen <span class=\"ui-button-icon-primary ui-icon ui-icon-triangle-1-s\"><\/span>",
        "colvis": "<i class='bi bi-table'></i>",
        "colvisRestore": "Sichtbarkeit wiederherstellen",
        "csv": "CSV",
        "excel": "Excel",
        "pageLength": {
            "-1": "Alle Zeilen anzeigen",
            "1": "Zeige 1 Zeile",
            "_": "Zeige %d Zeilen"
        },
        "pdf": "<i class='bi bi-file-pdf'></i>",
        "copyKeys": "Drücken Sie die Taste <i>STRG<\/i> oder <i>⌘<\/i> + <i>C<\/i> um die Tabelle<br \/>in den Zwischenspeicher zu kopieren.<br \/><br \/>Um den Vorgang abzubrechen, klicken Sie die Nachricht an oder drücken Sie die Escape-Taste."
    },

    },

    
    dom: 'Bfrtip',
    columnDefs: [
        {
            targets: 1,
            className: 'noVis'
        }
    ],
    buttons: [
      'pageLength',
        { extend: 'colvis',  columns: ':not(.noVis)' },
        { extend: copy    , className: 'TabelButtonCopy' },
        { extend: excel   , className: 'TableButtonExcel' },
        { extend: pdf     , className: 'TableButtonPdf' },
        { extend: print   , className: 'TableButtonPrint' },                                  
    ],    
    lengthMenu: [
      [10, 15, 20, 25, 50, -1],
      [10, 15, 20, 25, 50, '*'],
    ]


});
var but='';
$('.TableButtonExcel').remove(); 
$('.TabelButtonCopy').remove(); 
$('.TableButtonPrint').remove(); 
$('.buttons-page-length').remove();
$('.dataTables_paginate').remove(); 
$('.dataTables_info').remove();
$('.TableButtonPdf').remove();
$('.buttons-collection').remove();

$('.dt-buttons').addClass('float-end'); 
$('.dt-buttons').addClass('left-space');

$(".no-sort").removeClass("sorting");
$(".no-sort").removeClass("sorting_asc");
$(".no-sort").removeClass("noVis");
$(".no-sort").unbind();

//$('th').removeAttr("style"); // important !
//$(id).css({ width: "98%" });
//alert("datatab-1");

}




