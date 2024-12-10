// dbxWebApp Javascript jQuery functions
var dbx_print_ids='';
var dbx_sync_form=-1;


function dbxRemovePreloader() {
  $('#preloader').remove();
}

function disable_form(xForm) {
    xForm=dbx_check_is_id(xForm);
    var $form = $(xForm);

    var $inputs  = $form.find("input, select, textarea");
    $inputs.prop("disabled", true);

}


function dbxDragable() {
  $('.draggable').each(function() {
      var $element = $(this);
      var $form = $element.closest('form'); // Find the closest form element

      // 1. Add necessary context menu HTML
      if (!$('#context-menu-' + $element.attr('id')).length) {
          $('<div>', {
              id: 'context-menu-' + $element.attr('id'),
              class: 'context-menu',
              html: '<ul><li class="save-position">Position speichern</li></ul>'
          }).appendTo('body');
      }

      // 2. Initialize draggable and resizable
      $element.draggable({
          containment: "body",
          stop: function(event, ui) {
              console.log("Position:", ui.position);
          }
      }).resizable({
          stop: function(event, ui) {
              console.log("Size:", ui.size);
          }
      });

      // 3. Right-click to show context menu
      $element.on('contextmenu', function(event) {
          event.preventDefault();
          console.log("Context menu event detected");

          // Hide any open context menus
          $(".context-menu").hide();

          var contextMenu = $('#context-menu-' + $element.attr('id'));
          contextMenu.css({
              top: event.pageY + 'px',
              left: event.pageX + 'px'
          }).show();
      });

      // 4. Hide context menu on click outside
      $(document).on('click', function(event) {
          if (!$(event.target).closest('.context-menu, .draggable').length) {
              $(".context-menu").hide();
          }
      });

      // 5. Handle position save from context menu
      $('#context-menu-' + $element.attr('id') + ' .save-position').on('click', function() {
          var position = $element.position();
          var size = {
              'obj-w': $element.width(),
              'obj-h': $element.height()
          };
          var objPosition = {
              'obj-x': position.left,
              'obj-y': position.top
          };

          // Data to send via AJAX
          var data = {
              'obj_id': $element.attr('id'), // Add obj_id
              'obj_na': $element.attr('name'),
              'obj_x': objPosition['obj-x'],
              'obj_y': objPosition['obj-y'],
              'obj_w': size['obj-w'],
              'obj_h': size['obj-h'],
              'form_id': $form.attr('id') // Add form_id
          };

          // AJAX Request
          $.ajax({
              url: 'index.php?dbx_modul=dbxAdmin&dbx_action=pos-and-size&dbx_work=save', // PHP file that handles the request
              method: 'POST',
              async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
              data: data,
              success: function(response) {
                  alert("Position und Größe gespeichert!");
              },
              error: function(xhr, status, error) {
                  alert("Es gab ein Problem: " + error);
              }
          });

          // Hide context menu after saving
          $('#context-menu-' + $element.attr('id')).hide();
      });
  });
}


function dbx_tooltip() {
  // Tooltip erstellen und zur Seite hinzufügen
  const tooltip = $('<div class="custom-tooltip"></div>').appendTo('body');

  // Tooltip-Anzeige bei Klick auf das Label
  $('[data-tooltip]').on('click', function (event) {
      const message = $(this).data('tooltip');
      
      // Wenn das Tooltip-Attribut leer ist, Tooltip nicht anzeigen
      if (!message) {
          return;
      }

      event.stopPropagation(); // Verhindert das Schließen beim Klicken auf den Tooltip
      tooltip.text(message).fadeIn(200);

      // Tooltip-Position relativ zum Label aktualisieren
      const offset = $(this).offset();
      const tooltipHeight = tooltip.outerHeight();
      const windowHeight = $(window).height();
      let tooltipTop, tooltipLeft;

      // Prüfen, ob der Tooltip unter oder über dem Label angezeigt werden soll
      if (offset.top + $(this).outerHeight() + tooltipHeight + 10 < windowHeight) {
          // Tooltip unterhalb anzeigen, Pfeil oben
          tooltipTop = offset.top + $(this).outerHeight() + 5;
          tooltip.removeClass('arrow-bottom').addClass('arrow-top'); // Pfeil oben
      } else {
          // Tooltip oberhalb anzeigen, Pfeil unten
          tooltipTop = offset.top - tooltipHeight - 10;
          tooltip.removeClass('arrow-top').addClass('arrow-bottom'); // Pfeil unten
      }

      tooltipLeft = offset.left;

      tooltip.css({
          top: tooltipTop,
          left: tooltipLeft,
          position: 'absolute',
          zIndex: 1055  // Über dem Modal anzeigen
      });
  });

  // Tooltip-Ausblenden, wenn man außerhalb des Labels klickt
  $(document).on('click', function () {
      tooltip.fadeOut(200);
  });

  // Tooltip-Ausblenden, wenn die Maus das Label verlässt
  $('[data-tooltip]').on('mouseleave', function () {
      tooltip.fadeOut(200);
  });
}

// Call the function after document is ready and modal is shown
function dbxContexMenuModal() {
    // Make draggable elements when modal is shown
    $('#dbxModal1').on('shown.bs.modal', function () {
      dbxDragable(); // Apply draggable/resizable and menu logic
    });
};  


function dbx_fld_help() {
  // Dynamische Erstellung des Modals für Hilfetext
  const helpModal = $(`
      <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header">
                      <h5 class="modal-title" id="helpModalLabel">Hilfetext</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                  </div>
                  <div class="modal-body" id="helpContent">
                      <!-- AJAX-Inhalt wird hier geladen -->
                  </div>
              </div>
          </div>
      </div>
  `);
  $('body').append(helpModal); // Modal zum Body hinzufügen

  // Beispiel Modal mit einem Label und Eingabefeld
  const dbxHelpModalContent = $(`
      <div class="modal fade" id="dbxHelpModal" tabindex="-1" aria-labelledby="dbxHelpModalLabel" aria-hidden="true">
          <div class="modal-dialog">
              <div class="modal-content">
                  <div class="modal-header">
                      <h5 class="modal-title" id="dbxHelpModalLabel">Modal Titel</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                  </div>
                  <div class="modal-body">
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                      <button type="button" class="btn btn-primary">Speichern</button>
                  </div>
              </div>
          </div>
      </div>
  `);
  
  $('body').append(dbxHelpModalContent); // Modal zum Body hinzufügen
  
  // Tooltip und Hilfe-Icon für jedes Label mit data-tooltip und data-fld-help
  $('[data-tooltip]').each(function () {
      const $label = $(this);
      const tooltipText = $label.data('tooltip');

      if (tooltipText === "") {
        return; // Tooltip wird ignoriert und nicht angezeigt
    }    

      if (tooltipText === "dd:") {
        return; // Tooltip wird ignoriert und nicht angezeigt
    }      
      
      // Tooltip erstellen
      $label.attr('title', tooltipText)
          .css('cursor', 'pointer')
          .tooltip({ 
              trigger: 'manual', 
              placement: 'top', 
              html: true 
          })
          .on('mouseenter', function () {
              $(this).tooltip('show');
          })
          .on('mouseleave', function () {
              $(this).tooltip('hide');
          });

      const helpUrl = $label.data('fld-help');
      if (helpUrl) {
          // Fragezeichen-Icon erstellen
          const helpIcon = $('<span class="help-icon">?</span>');
          if ($label.text().includes(":")) {
              $label.html($label.html().replace(":", "")).append(helpIcon).append(':');
          } else {
              $label.append(helpIcon);
          }

          // Klick-Event auf das Fragezeichen
          helpIcon.on('click', function (event) {
              event.stopPropagation(); // Verhindert unerwünschte Klicks

              // AJAX-Anfrage zur URL und Ergebnis in Modal einfügen
              $.ajax({
                  url: helpUrl,
                  method: 'GET',
                  success: function (data) {
                      $('#helpContent').html(data); // Inhalt in Modal einfügen
                      $('#helpModal').modal('show'); // Modal öffnen
                  },
                  error: function () {
                      $('#helpContent').html('<p>Der Hilfetext konnte nicht geladen werden.</p>');
                      $('#helpModal').modal('show');
                  }
              });
          });
      }
  });

  // Das Modal automatisch anzeigen (optional)
  //$('#dbxHelpModal').modal('show');
}





function dbx_callWithoutWaiting(url) {
  //alert("Print=" + url );
  $.ajax({
      url: url,
      method: 'GET',
      async: true,
      success: function(response) {
          console.log('Erfolg:', response); // Optional: Erfolgs-Callback
      },
      error: function(xhr, status, error) {
          console.log('Fehler:', error); // Optional: Fehler-Callback
      }
  });
  //console.log('AJAX-Aufruf wurde gesendet und wir warten nicht auf die Antwort');
}

function updateQueryParam(url, param, newValue) {
  let urlObj = new URL(url);
  urlObj.searchParams.set(param, newValue);
  return urlObj.toString();
}



function dbxCloseWindow() {
    window.opener = top;
    window.self.close();
}


function dbxGoTo(go) {
   //$("html, body").animate({ scrollTop: $("#dbx_end")[0].scrollHeight }, "slow");

   $('html, body').animate({
    scrollTop: $("#dbx_end").offset().top
   }, 100);
}

function dbxRedirect(url) {
  window.location.href = url;
}

function dbxRedirect_parent(url) {
  window.parent.location.replace(url);
}

function dbx_reload(mode) {
  if (mode == '?') mode='self';
  if (mode == 'self') {
    var url = window.location.href;
    if (url.endsWith("&dbx_go=dbx_end"))  url = url.slice(0, -15); // 16 is the length of "&dbx_go=dbx_end"
    dbxRedirect(url);
  }

  if (mode == 'parent') {
    var url = window.parent.location.href;
    if (url.endsWith("&dbx_go=dbx_end"))  url = url.slice(0, -15); // 16 is the length of "&dbx_go=dbx_end"
    dbxRedirect_parent(url);
  }  

  //alert('Mode=' + mode + " change=" + dbx_change + " url=" + url);
  if (mode==1) dbxRedirect(url);
  if (mode==2) { 
    url=url+'&dbx_go=dbx_end';
    dbxRedirect(url);
  }  
}  






function dbxScrollTop() {
    //$('a[href="#top"]').unbind();
    $('a[href="#top"]').on('click', function(e) {
      e.preventDefault();
      $("html, body").animate({ scrollTop: 0 }, "slow");
    });
}

function dbxNoHref() {
    $('a[href="#0"]').on('click', function(e) {
      e.preventDefault();
    });
}

function dbxMenuActivate() {
  //$('.dropdown-submenu > a').submenupicker();
}

function dbxAddLib(lib) {
  console.log(lib);
  var tag='';
  var haed=$('head').html();
  var find= haed.indexOf('="' + lib + '"');
  if  (find == -1) {
    if(lib.endsWith(".css")) {
      var tag='<link href="' + lib + '"  rel="stylesheet">'; 
      $('head').append(tag);
    } 
    if(lib.endsWith(".js")) {
      tag ='<script src="' + lib +'"></script>';
      $('head').append(tag); 
    } 
  }
}

function dbxReSendForm(id) {
   id=dbx_check_is_id(id);
   $(id).submit(); 
}


function dbxMenuActive() {
    var CurrentUrl= document.URL;
    var CurrentUrlEnd = CurrentUrl.split('/').filter(Boolean).pop();
    //console.log(CurrentUrlEnd);
    $( "#navbar li a" ).each(function() {
          var ThisUrl = $(this).attr('href');
          var ThisUrlEnd = ThisUrl.split('/').filter(Boolean).pop();
          var ThisPath   = CurrentUrlEnd.indexOf(ThisUrlEnd);
          //console.log('(' + CurrentUrlEnd + ') = (' + ThisUrlEnd + ') Pos=' + ThisPath);
          if(ThisUrlEnd == CurrentUrlEnd || ThisPath == 0) {
             //console.log('MAtch=' + ThisUrlEnd + ' Pos=' + ThisPath); 
             //alert(ThisUrlEnd + '== ' + CurrentUrlEnd); 
             $(this).addClass('active');
             $(this).closest('li').addClass('open');
             $(this).closest('ul').addClass('open');
             $(this).closest('ul').closest('li').addClass('open');            
          } else {
            $(this).removeClass('active');
          }
    });
    
   $( "#navbar li.dropdown.open " ).each(function() {
       //$(this).closest('a').addClass('active');
       $(this).addClass('open');  
       $(this).children().addClass('active');    
   });    



   // switch display iFrame (admin onley)
   $('a.set-device-max').unbind();
   $('a.set-device-max').on('click', function(e) {
      e.preventDefault();
      var page_url=$('#frame-admin').contents().get(0).location.href;
      $(location).attr('href',page_url);
      return false;
   });    

   $('a.set-device-desctop').unbind();
   $('a.set-device-desctop').on('click', function(e) {
      e.preventDefault(); 
      $('#hero').remove();
      var nav = $('#haeder').html();
      $('.admin-frame').remove();
      $('.show').hide('slow');
      $('body').html(nav);         
      $('body').append('<div class="admin-frame"><center><br><hr><br><iframe src="' + CurrentUrl +'" name="frame-admin" id="frame-admin"   height=800 width=1024></iframe></center></div>');
      return false;
   });    

   $('a.set-device-tablet').unbind();
   $('a.set-device-tablet').on('click', function(e) {
      e.preventDefault(); 
      $('#hero').remove();
      var nav = $('#haeder').html();
      $('.admin-frame').remove();
      $('.show').hide('slow');
      $('body').html(nav);               
      $('body').append('<div class="admin-frame"><center><br><br><hr><br><iframe src="' + CurrentUrl +'" name="frame-admin" id="frame-admin"   height=800 width=800></iframe></center></div>');
      $('.hide').toggle('slow');
      return false;
   });    

   $('a.set-device-mobile').unbind();
   $('a.set-device-mobile').on('click', function(e) {
      e.preventDefault(); 
      $('#hero').remove();
      var nav = $('#haeder').html();
      $('.admin-frame').remove();
      $('.show').hide('slow');
      $('body').html(nav);      
      $('body').append('<div class="admin-frame"><center><br><br><hr><br><iframe src="' + CurrentUrl +'" name="frame-admin" id="frame-admin"   height=680 width=380></iframe></center></div>');
      $('.hide').toggle('slow');
      return false;
   });       
    
}      





function dbx_check_is_id(id) {
  if (id > "" && id !== 'undefined') {
    var check=id.substr(0,1);
    if (check !== '#') id=id.substr(0, 0) + '#' + id.substr(0);
  }
  if (id === 'undefined') id='';
  return id;
}


function dbxCloseModal(modal_id) {
  modal_id=dbx_check_is_id(modal_id);
  var classList = $(modal_id).attr('class').split(/\s+/);
  $.each(classList, function(index, item) {
      if (item === 'modal') {
          $(modal_id).modal('hide');
      }
  });

}

var dbx_get_params = function(search_string) {
  var xX = search_string.slice(1);
  if (xX != '?') search_string='?' + search_string;

  var parse = function(params, pairs) {
    var pair  = pairs[0];
    var parts = pair.split('=');
    var key   = decodeURIComponent(parts[0]);
    var value = decodeURIComponent(parts.slice(1).join('='));
    // Handle multiple parameters of the same name
    if (typeof params[key] === "undefined") {
      params[key] = value;
    } else {
      params[key] = [].concat(params[key], value);
    }
    return pairs.length == 1 ? params : parse(params, pairs.slice(1))
  }
  return search_string.length == 0 ? {} : parse({}, search_string.substr(1).split('&'));
}




var dbxSendForm = function(xForm,xGet,xTarget,xAdd='',xNor=0) {
  xTarget=dbx_check_is_id(xTarget);
  xForm  =dbx_check_is_id(xForm);

  //alert("form=" + xForm + " Target=" + xTarget + " get=" + xGet);

  var $form = $(xForm);
  if (xTarget && xGet) {
    var serializedData = $form.serialize();
    var $inputs  = $form.find("input, select, button, textarea");
    $inputs.prop("disabled", true);
    
    $.ajax({
        url  : xGet,
        type : 'post',
        async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
        data : {
            dbx_ajax   : 1,
            dbx_target : xTarget,
            dbx_get    : xGet,
            dbx_add    : xAdd,   // ?
            dbx_nor    : xNor,   // ?
            dbx_post   : serializedData
        },

        success : function( response ) {
            //$("#dbx_ajax_success").fadeIn('slow').animate({opacity: 1.0}, 1500).effect("pulsate", { times: 2 }, 800).fadeOut('slow');
            $(xTarget).replaceWith(response); // best Way
            dbxAjaxInit();
        },
        error : function( response ) {
            //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText + ' Target=' + xTarget + ' Call=' + xGet);
            console.log(response);
        }
    });
    $inputs.prop("disabled", false);
  } // hase xTarget
}









var dbx_get_src_val = function(src,mode) {
  var param  ='undef';
  var char   ='';
  var lastpos=0;
  var url    ='';
  var file   ='';
  var len    = src.length;
  for (i = 0 ; i <= len; i++) {
    char = src.substr(i, 1);
    if (char == '/') lastpos=i;
  }
  if (lastpos > 0) {
    url = src.substr(0,lastpos+1);
    file= src.substr(lastpos+1,len-lastpos);
  }

  if (mode == 'file')  param = file;
  if (mode == 'url')   param = url;
  return param;
}








// var dbx_observ_store = [];

function dbxObserve(observer) {

  observer =dbx_check_is_id(observer);
  //alert("observer=(" + observer + ")");
  if ($(observer).length) {

    var self  =$(observer).attr('name');
    var observ=$(observer).data('dbx_observ');
    var form  =$(observer).data('dbx_form');;
    var old   =$(observer).data('dbx_old');
    var val   =$(observer).val();

    if (self != observ) {
       var observed = $("input[name='" + observ + "']").attr('id');
       observed =dbx_check_is_id(observed);
       val=$(observed).val();
       //alert("observed-id=" + observed + "val=" + val);
    }
    //alert("observer=(" + observer + ") observe=(" + observ + ") Self=(" + self + ")  Form=(" + form + ") Value=("+  val  + ")"  + " old=(" + old + ")");
    if (old != val) {
       //alert("observer=(" + observer + ") observe=(" + observ + ") Self=(" + self + ")  Form=(" + form + ") Value=("+  val  + ")"  + " old=(" + old + ")");

       dbxAjaxAutoSubmit(form,observ,val);
    }
  }
}







function dbxAjaxAutoSubmit(id,fld = 'no_obs',val = '') {
  id=dbx_check_is_id(id);

  var $form    = $(id);
  var xGet     = $(id).data( 'dbx_get' );
  var xTarget  = $(id).data( 'dbx_target' );


  if (!xGet) {
    xGet = $(id).attr('action');
  }
  if (!xTarget && xGet) {
     var params = dbx_get_params(xGet);
     var xTarget = params['dbx_target'];
  }
  xTarget=dbx_check_is_id(xTarget);

  //if (!xTarget) alert("Form=(" + id + ") Target=(" + xTarget + ") action=(" + xGet + ")");


  if (xTarget && xGet) {
    //if (fld) xGet=(xGet + '&' + fld + '=' + val);
    var serializedData = $form.serialize();
    var $inputs  = $form.find("input, select, button, textarea");
    $inputs.prop("disabled", true);
    $.ajax({
        url  : xGet,
        type : 'post',
        async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
        data : {
            dbx_obs_fld : fld,
            dbx_obs_val : val,
            dbx_ajax    : 1,
            dbx_target  : xTarget,
            dbx_get     : xGet,
            dbx_post    : serializedData
        },

        success : function( response ) {
            //alert(response);
            $inputs.prop("disabled", false);
            $(xTarget).replaceWith(response); // best Way
            dbxAjaxInit();
        },
        error : function( response ) {
            //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
            console.log(response);
        }
    });
  } // hase xTarget
}




function dbxAjaxFormAction() {
    $('a.dbxAjaxFormAction').unbind();
    $('a.dbxAjaxFormAction').on('click', function(e) {

      e.preventDefault();

      var xGet     = $(this).data( 'dbx_get' );
      var xForm    = $(this).data( 'dbx_form' );
      var xTarget  = $(this).data( 'dbx_target' );
      if (!xGet) {
        xGet = $(this).attr('href');
        xGet = xGet.substring(1);
      }
      if (!xForm) {
         var params = dbx_get_params(xGet);
         xForm = params['dbx_form'];
      }
      if (!xTarget) {
         var params = dbx_get_params(xGet);
         xTarget = params['dbx_target'];
      }
      xTarget=dbx_check_is_id(xTarget);

      dbxSendForm(xForm,xGet,xTarget);


    });
}

function dbxAjaxInitForm() {
  $('form.dbxAjax').unbind();
  $('form.dbxAjax').on('submit', function(e) {

      e.preventDefault();
      

      var $form    = $(this);
      var xGet     = $(this).data( 'dbx_get' );
      var xTarget  = $(this).data( 'dbx_target' );
      var xSync    = $(this).data( 'dbx_sync');
      var xInit    = $(this).data( 'dbx_ajaxinit') || true;
      var xDisable = $(this).data( 'dbx_input_disable') || true;
    


      
      var isSync   = true;
      if (xSync == 'off')       isSync = false;
      if (xSync == 'false')     isSync = false;   
      if (dbx_sync_form !== -1) isSync = dbx_sync_form;
      dbx_sync_form=-1;


      if (!xGet) {
        xGet = $(this).attr('action');
      }
      if (!xTarget) {
         var params = dbx_get_params(xGet);
         xTarget = params['dbx_target'];
         if (xTarget=='undefined') alert('Target not defined data-dbx_target');
      }
  
      xTarget=dbx_check_is_id(xTarget);

      

      if ( isSync) xGet=xGet + '&dbx_sync=1';
      if (!isSync) xGet=xGet + '&dbx_sync=0'; 

      //alert(xGet); 

      var serializedData = $form.serialize();
      if (isSync && xDisable) {
        var $inputs  = $form.find("input, select, button, textarea");
        $inputs.prop("disabled", true);
      }  
 
      //alert("call=" + xGet)

      var ajaxOptions = {
        url: xGet,
        method: $(this).attr('method') ,
        data : {
            dbx_ajax   : 1,
            dbx_target : xTarget,
            dbx_post   : serializedData
        },       
        async: !isSync, // Use async based on the data-sync attribute
      };

      // Execute the AJAX request
      var xhr = $.ajax(ajaxOptions);
      if (isSync) {
        var status = xhr.status;
        


        // Synchronous request - handle success directly
        if (status === 200) { // Check if the request was successful
            var response = xhr.responseText;
            alert("response Target=" + xTarget + " rsponse=" + response);

            //$inputs.prop("disabled", false);
            $(xTarget).replaceWith(response);    // best Way  
            dbxAjaxInit();
        } else {
            // Handle error if needed
            console.log('Error:', xhr.statusText);
        }
    } else {
       //alert("no-sync");
    }
    
  });
}





function dbxUploadImg(id) {
  id=dbx_check_is_id(id);

  var xGet     = $(id).data( 'dbx_get' );
  var xTarget  = $(id).data( 'dbx_target' );
  xTarget=dbx_check_is_id(xTarget);

	$(id).uploadFile({
      url: xGet + '&dbx_ajax=1',
      returnType:      0,
      cache:       false,
      multiple:    false,
      dragDrop:    false,
      maxFileCount: -1,
      maxFileSize: 8294400,
      acceptFiles: "image/*",
    	fileName: "upload_file",
        onSuccess: function(files,data,xhr,pd) {
        if (xTarget) $(xTarget).replaceWith(data);  // Change the div's contents to the result.
    }
	});
}

function dbxAjaxInitHref() {
  $('a.dbxAjax').unbind();
  $('a.dbxAjax').on('click', function(e) {
      e.preventDefault();

      var $this    = $(this);
      var xGet     = $this.data('dbx_get');
      var xTarget  = $this.data('dbx_target');
      var xInit    = $(this).data( 'dbx_ajaxinit') || true;
      var xReplace = true;

      if (!xGet) {
          xGet = $this.attr('href');
      }
      if (!xTarget) {
          var params = dbx_get_params(xGet);
          xTarget = params['dbx_target'];
      }
      xTarget = dbx_check_is_id(xTarget);

      // Check if the element has the dbxConfirm class
      if ($this.hasClass('dbxConfirm')) {
          // Get title and message for the modal from data attributes
          var confirmTitle = $this.data('confirm-title') || 'Confirm';
          var confirmMessage = $this.data('confirm') || 'Are you sure?';

          // Find and populate the existing modal with title and message
          $('#dbxConfirmModal .modal-title').html(confirmTitle);
          $('#dbxConfirmModal .modal-body').html(confirmMessage);

          // Show the modal
          $('#dbxConfirmModal').modal('show');

          // Handle the Yes button click
          $('#dbxConfirmYes').off('click').on('click', function() {
              $('#dbxConfirmModal').modal('hide');

              // Proceed with the AJAX call after modal is hidden
              $.ajax({
                  url: xGet,
                  type: 'post',
                  async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
                  data: {
                      dbx_target: xTarget,
                      dbx_get: xGet,
                      dbx_ajax: 1
                  },
                  success: function(response) {
                      if (!xReplace) {
                          $(xTarget).html(response);  // Change the div's contents to the result.
                      } else {
                          $(xTarget).replaceWith(response); // Replace the entire element.
                          dbxAjaxInit();
                      }
                      
                  },
                  error: function(response) {
                      //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                      console.log(response);
                  }
              });
          });

      } else {
          // No confirmation needed, proceed with AJAX
          $.ajax({
              url: xGet,
              type: 'post',
              async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
              data: {
                  dbx_target: xTarget,
                  dbx_get: xGet,
                  dbx_ajax: 1
              },
              success: function(response) {
                  if (!xReplace) {
                      $(xTarget).html(response);  // Change the div's contents to the result.
                  } else {
                      $(xTarget).replaceWith(response); // Replace the entire element.
                      dbxAjaxInit();
                  } 
              },
              error: function(response) {
                  //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                  console.log(response);
              }
          });
      }
  });
}


function old_dbxAjaxInitHref() {
  $('a.dbxAjax').unbind();
  $('a.dbxAjax').on('click', function(e) {
      e.preventDefault();

      var $this    = $(this);
      var xGet     = $this.data('dbx_get');
      var xTarget  = $this.data('dbx_target');
      var xInit    = $(this).data( 'dbx_ajaxinit') || true;
      var xReplace = true;

      if (!xGet) {
          xGet = $this.attr('href');
      }
      if (!xTarget) {
          var params = dbx_get_params(xGet);
          xTarget = params['dbx_target'];
      }
      xTarget = dbx_check_is_id(xTarget);

      // Check if the element has the dbxConfirm class
      if ($this.hasClass('dbxConfirm')) {
          // Get title and message for the modal from data attributes
          var confirmTitle = $this.data('confirm-title') || 'Confirm';
          var confirmMessage = $this.data('confirm') || 'Are you sure?';

          // Create the confirmation modal
          var confirmModal = `
              <div class="modal fade" id="dbxConfirmModal" tabindex="-1" role="dialog" aria-labelledby="dbxConfirmModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="dbxConfirmModalLabel">${confirmTitle}</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      ${confirmMessage}
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Nein</button>
                      <button type="button" class="btn btn-primary" id="dbxConfirmYes">Ja</button>
                    </div>
                  </div>
                </div>
              </div>`;

          // Append the modal to the body
          $('body').append(confirmModal);

          // Show the modal
          $('#dbxConfirmModal').modal('show');

          // Handle the Yes button click
          $('#dbxConfirmYes').on('click', function() {
              $('#dbxConfirmModal').modal('hide');
              $('#dbxConfirmModal').on('hidden.bs.modal', function () {
                  $('#dbxConfirmModal').remove(); // Remove the modal from the DOM

                  // Proceed with the AJAX call
                  $.ajax({
                      url: xGet,
                      type: 'post',
                      async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
                      data: {
                          dbx_target: xTarget,
                          dbx_get: xGet,
                          dbx_ajax: 1
                      },
                      success: function(response) {
                          if (!xReplace) {
                              $(xTarget).html(response);  // Change the div's contents to the result.
                          } else {
                              $(xTarget).replaceWith(response); // Replace the entire element.
                              dbxAjaxInit();
                          } 
                      },
                      error: function(response) {
                          // alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                          console.log(response);
                      }
                  });
              });
          });

          // Handle the No button click or modal close
          $('#dbxConfirmModal').on('hidden.bs.modal', function () {
              $('#dbxConfirmModal').remove(); // Remove the modal from the DOM
          });

      } else {
          // No confirmation needed, proceed with AJAX
          $.ajax({
              url: xGet,
              type: 'post',
              async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
              data: {
                  dbx_target: xTarget,
                  dbx_get: xGet,
                  dbx_ajax: 1
              },
              success: function(response) {
                  if (!xReplace) {
                      $(xTarget).html(response);  // Change the div's contents to the result.
                  } else {
                      $(xTarget).replaceWith(response); // Replace the entire element.
                      dbxAjaxInit();
                  }
              },
              error: function(response) {
                  //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                  console.log(response);
              }
          });
      }
  });
}





function dbxAjaxInitBtn() {
    $('dbxAjax_btn').unbind();
    $('dbxAjax_btn').on('click', function(e) {

        var xGet     = $(this).data( 'dbx_get' );
        var xTarget  = $(this).data( 'dbx_target' );
        var xInit    = $(this).data( 'dbx_ajaxinit') || true;
        xTarget=dbx_check_is_id(xTarget);
        //alert("Get=("  + xGet + " target=(" + xTarget +  ")");
        if (!xGet) {
           alert("data-dbx_get is undef!");
        }

        e.preventDefault();

        $.ajax({
            url : xGet,
            type : 'post',
            async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
            data : {
                 dbx_target : xTarget,
                 dbx_get    : xGet,
                 dbx_ajax   : 1
            },
            success : function( response ) {
                response=dbxTargetResponse(response,xTarget);
                //alert(response);
                $(xTarget).html(response);  // Change the div's contents to the result.
                dbxAjaxInit();  
            },
            error : function( response ) {
                //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                console.log(response);
            }
        });
    });


}

function dbxAjaxInitExpand() {
    $('a.dbxExpander').unbind();
    $('a.dbxExpander').on('click', function(e) {
        var xIsLoad  = $(this).attr('data-dbx_isload');
        var xLoad    = $(this).attr('data-dbx_load');
        var xOpen    = $(this).attr('data-dbx_open');
        var xGet     = $(this).attr('data-dbx_get');
        var xInit    = $(this).data( 'dbx_ajaxinit') || true;
        if (!xGet) xGet = $(this).attr('href');

        $(this).toggleClass( "dbx_isClose" );
        $(this).toggleClass( "dbx_isOpen" );

        $(xOpen).toggleClass( "dbxClose" );
        $(xOpen).toggleClass( "dbxOpen" );

        $(this).attr('data-dbx_isload', '1');
        xLoad=dbx_check_is_id(xLoad);

        e.preventDefault();

        if (xIsLoad=='0') {
          $.ajax({
              url : xGet,
              type : 'post',
              async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
              data : {
                  dbx_ajax   : 1,
                  dbx_target : xLoad,
                  dbx_get    : xGet
              },
              success : function( response ) {
                  $(xLoad).html(response);  // Change the content to the result.
                  dbxAjaxInit();
              },
              error : function( response ) {
                  //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                  console.log(response);
              }
          });

        }
    });
}


function dbxInitOpenClose() {
    $('.dbxIsCloseImg').unbind();
    $('.dbxIsCloseImg').on('click', function(e) {
        var xOpen;
        var xImgUrl = $(this).data( 'dbx_imgurl' );

        if ( $(this).is( ".dbxIsCloseImg" ) ) {
           $(this).toggleClass( "dbxIsCloseImg" );
           $(this).toggleClass( "dbxIsOpenImg" );
           $(this).attr("src", xImgUrl + "isOpen.png");
        }
        else {
           $(this).toggleClass( "dbxIsOpenImg" );
           $(this).toggleClass( "dbxIsCloseImg" );
           $(this).attr("src", xImgUrl + "isClose.png");

        }
    });
}


function dbxInitAutoload() {
  if ($('.dbxAutoload').length > 0) {
     $('.dbxAutoload')[0].click();
  }
  //$('.dbxAutoload').click();
}

function dbxCheckSelectAll() {
   $('#selectall').on('click',function(e){
        var dbx_not_selected='';
        var value ='';
        if(this.checked){
            $('.form-check-input-multi').each(function(){
                this.checked = true;
            });
        }else{
             $('.form-check-input-multi').each(function(){
                this.checked = false;
                value = this.value;
                dbx_not_selected += value + '|';
            });
        }


        // submit form
        var xForm    = $(this).data( 'dbx_form' );
        var xTarget  = $(this).data( 'dbx_target' );
        var xGet     = $(this).data( 'dbx_get' );
        var xInit    = $(this).data( 'dbx_ajaxinit') || true;

        var value =$(this).val();
        var check =$(this).prop('checked');

        xForm  =dbx_check_is_id(xForm);

        if (!xGet) {
           xGet = $(xForm).data( 'dbx_get' );
        }

        if (!xGet) {
          xGet = $(xForm).attr('action');
          if (xGet) xGet = xGet.substring(1);
        }

        if (xGet) {
          //alert(dbx_not_selected);
          xGet+='&dbx_mode=save_form_select&dbx_value='+value + '&dbx_checked=' + check ; // + '&dbx_not_selected=' + dbx_not_selected;
          dbxSendForm(xForm,xGet,xTarget,dbx_not_selected,1);
        }

    });

    $('.checkbox').on('click',function(e){
        if($('.checkbox:checked').length == $('.checkbox').length){
            $('#selectall').prop('checked',true);
        }else{
            $('#selectall').prop('checked',false);
        }
    });

}



function dbxAjaxInitBtn() {
    $('.dbxAjax_btn').unbind();
    $('.dbxAjax_btn').on('click', function(e) {

        var xGet     = $(this).data( 'dbx_get' );
        var xTarget  = $(this).data( 'dbx_target' );
        var xInit    = $(this).data( 'dbx_ajaxinit') || true;

        xTarget=dbx_check_is_id(xTarget);
        if (!xGet) { alert("data-dbx_get is undef!"); }

        //alert("Get=(" + xGet + ") target=" + xTarget +  ")");

        e.preventDefault();

        $.ajax({
            url : xGet,
            type : 'post',
            async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
            data : {
                 dbx_target : xTarget,
                 dbx_get    : xGet,
                 dbx_ajax   : 1
            },
            success : function( response ) {
                //alert(response);
                $(xTarget).html(response);  // Change the div's contents to the result.
                dbxAjaxInit(); 
            },
            error : function( response ) {
                //alert('Error retrieving the information: ' + response.status + ' ' + response.statusText);
                console.log(response);
            }
        });
    });
}



function dbxInitSelectable() {
    $('.dbxSelectable').unbind();
    $('.dbxSelectable').on('click', function(e) {
        $(this).toggleClass( "dbxSelected" );
    });
}


function dbxInitGetSelected() {
    $('.dbxGetSelected').unbind();
    $('.dbxGetSelected').on('click', function(e) {
      var xTarget   =  $(this).data('dbx_target');
      var xValue    =  $(this).data('dbx_value');

      xTarget=dbx_check_is_id(xTarget);
      $(xTarget).val(xValue);


      //alert("Img select target=" + xTarget + " Bild=" +xValue );

      if (xTarget == '#modal1_body')   $('#dbxmodal1').modal('toggle');
      if (xTarget == '#modal1b_body')  $('#dbxmodal1b').modal('toggle');

       $('#dbxmodal1').modal('toggle');
       $('#dbxmodal1b').modal('toggle');



    });
}


function dbxInitPopUp() {
  $('.openWin').unbind();
  $('.openWin').on('click', function(e) {
      //e.preventDefault();
      var width  = window.innerWidth * 0.88 ;
      var height = window.innerHeight * 0.88 ;
      var action = this.href;
      action += '&dbx_window=1';
      // define the height in
      //var height = width * window.innerHeight / window.innerWidth ;
      // var height = width * window.innerHeight - 100 ;
      // Ratio the hight to the width as the user screen ratio
      var xwidth =$(this).data('dbx_win_width');
      var xheight=$(this).data('dbx_win_height');
      //alert("xW=" + xwidth + " xH=" + xheight );

      if (xwidth  != 'undefined') width =xwidth;
      if (xheight != 'undefined') height=xheight;

      //alert("W=" + width + " H=" + height );
      window.open(action , 'newwindow', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' + width + ', height=' + height + ', top=' + ((window.innerHeight - height) / 2) + ', left=' + ((window.innerWidth - width) / 2));
      return false;
  });
}


function dbxInitModal() {
  $('.openModal').unbind();
  $('.openModal').on('click', function(e) {
      //e.stopPropagation();
      e.preventDefault();

      var target = $(this).data('dbx_target');
      var href   = $(this).data('dbx_get');
      var xInit  = $(this).data( 'dbx_ajaxinit') || true;
      if (!href) href=$(this).prop('href');

      //$(target).html('warte'); // full content
      //alert("href=" + href + " Target=" + target  );


      $.ajax({
           url : href,
           type : 'get',
           async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
           data : {
                dbx_target : target,
                dbx_ajax   : 1
           },
           success : function( response ) {
                $(target).html(response); // full content #todo
                dbxAjaxInit();           },
           error : function( response ) {
              //alert(response);
           }
       });
      return false;
  });
}




function dbxAutosaveMultiselect(id,form) {
  id  =dbx_check_is_id(id);
  form=dbx_check_is_id(form);
  $(id).change(function() {
    if ($(this).val() === null) {
        $(this).val(''); // Leeren Wert setzen, wenn nichts ausgewählt ist
    }
    //alert(form);
    //dbx_sync_form=0;
    $(form).submit(); // Formular absenden
  });
}


function dbxInitMultiRowSelect() {
    //console.log('dbxInitMultiRowSelect');
    //$('.form-check-input-multi').unbind();  // !important!
    $('input.form-check-input-multi').on('click', function(e) {
       //e.preventDefault();
       //e.stopPropagation();
       var formid  =$(this).data('dbx_form');
       var target  =$(this).data('dbx_target');
       var value   =$(this).val();
       var checked =$(this).prop('checked');
       var xInit   =$(this).data( 'dbx_ajaxinit') || true;
       if (checked) {
          checked=1;
       } else {
          checked=0;
       }
       
       var action=$(formid).prop('action');
       action+='&dbx_mode=save_form_select&dbx_value='+value + '&dbx_checked=' + checked;
       //alert('check=(' + checked + ') val=(' + value + ') Form=(' + formid + ') +action=(' + action + ')' + ' target=(' +target +  ')');

       $.ajax({
            url : action,
            type : 'post',
            async: true, // stellt sicher, dass die Anfrage asynchron ausgeführt wird
            data : {
                 dbx_target : target,
                 dbx_ajax   : 1
            },
            success : function( response ) {
               var len = response.length;
               if (len <= 8) $(target).html(response);  // Change to the result count select.
               if (len >  8) {
                 $(target).replaceWith(response); // full content
                 if (xInit) dbxAjaxInit();
               }
               //alert('return Len=(' + len + ')  target=(' + target +')');
            },
            error : function( response ) {
               //alert("error" + response);
            }
        });
        return true;
    });

}



function dbx_countdown_button_run(id,timer) {
    var type   = $(id).prop('nodeName'); 
    var sec    = $(id).data('dbx_counter_sec');
    var is     = $(id).data('dbx_counter_is');
    var label  = $(id).data('dbx_counter_label');
    is++;
    $(id).data('dbx_counter_is',is);
    var diff   = (sec -is);
    //alert(id + 'sec=(' + sec + ') is=(' + is + ') diff=(' + diff + ') type=(' + type + ')'); 

    if (diff >= 0) {
      label=(label + ' (' + diff + ')');
      //alert(label);
      if (type=='BUTTON') { $(id).html(label) };
      if (type=='INPUT')  { $(id).attr('value',label) };
      if (type=='SPAN')   { $(id).html(label) };
    } else {
      //alert(id + 'run sec=(' + sec + ') is=(' + is + ')'); 
      clearInterval(timer);
      var redir = $(id).data('dbx_redirect');
      var submi = $(id).data('dbx_submit');    
      if (redir) window.location.replace(redir);
      if (submi) dbxAjaxAutoSubmit(submi);
    }

}

function dbx_countdown_button(id) {
    id=dbx_check_is_id(id);
    var timer = setInterval(function() {

       dbx_countdown_button_run(id,timer);
     
       //console.log("This function runs every second");
    }, 1000); // 1000 milliseconds = 1 second
}














function dbxInitAutoload() {
  // Intercept click event for elements with the class "myConfirm"
  $('.myConfirm').off('click').on('click', function (e) {
      e.preventDefault(); // Prevent the default action

      var $element = $(this); // The clicked element
      var title = $element.data('confirm-title') || 'Confirmation'; // Get the title
      var message = $element.data('confirm') || 'Are you sure you want to proceed?'; // Get the message

      // Set the modal title and message
      $('#confirmModalLabel').text(title);
      $('#confirmMessage').text(message);

      // Show the modal
      $('#confirmModal').modal('show');

      // Handle the "Yes" button click
      $('#confirmYesBtn').off('click').on('click', function () {
          $('#confirmModal').modal('hide');

          // Trigger the original dbxAjax click event manually
          $element.removeClass('myConfirm'); // Temporarily remove the myConfirm class to avoid recursion
          $element.trigger('click'); // Trigger the original click event
          $element.addClass('myConfirm'); // Re-add the myConfirm class
      });
  });
}

function dbx_activate_goto() {
    // Event-Handler für Links, die mit '#' beginnen
    $('a[href^="#"]').on('click', function(event) {
        // Verhindern des Standardverhaltens (scrollen oder springen)
        event.preventDefault();
        
        // ID aus dem href-Attribut extrahieren
        var targetId = $(this).attr('href');
        
        // Überprüfen, ob ein Tab mit dieser ID existiert
        if ($(targetId).length) {
            // Tab aktivieren
            $('.nav-tabs a[href="' + targetId + '"]').tab('show');
            
            // Optional: zum Tab-Inhalt scrollen
            $('html, body').animate({
                scrollTop: $(targetId).offset().top
            }, 500);
        } else {
            // Andernfalls: zur Position des Elements scrollen
            $('html, body').animate({
                scrollTop: $(targetId).offset().top
            }, 500);
        }
    });
}


function dbxAjaxInit() {
    console.log('AjaxInit');
    dbxMenuActivate();
    dbxMenuActive();
    dbxAjaxInitForm();    
    dbxAjaxInitBtn();
    dbxAjaxInitHref();
    dbxAjaxFormAction();
    dbxAjaxInitExpand();
    dbxCheckSelectAll();
    dbxInitOpenClose();
    dbxInitSelectable();
    dbxInitMultiRowSelect();
    dbxInitGetSelected();
    dbxInitPopUp();
    dbxInitAutoload();
    dbxScrollTop();
    dbxNoHref();    
    dbxInitModal(); 
    dbxDragable ();
    dbxContexMenuModal();
    //dbx_tooltip();
    dbx_fld_help();
    dbx_activate_goto();
    if ( window.self !== window.top ) {
        $('.hide_in_iframe').hide();
    } 
    dbxRemovePreloader();     
    dbx_sync_form=1;

}


$(document).ready(function () {
    dbxAjaxInit();
});


$(document).ready(function() {
  // Seite neu laden, wenn F5 gedrückt wird
  $(document).on('keydown', function(e) {
    if (e.keyCode === 116) { // 116 ist der Keycode für F5
    e.preventDefault(); // Verhindert das Standard-Neuladen der Seite
    var url= window.location.href;
    dbxRedirect(url);
  }
  });
});
