
function dbx_set_print_ids(ids) {
  dbx_print_ids=ids;
}



jQuery.fn.extend({
  printElem: function() {
    //$('body').remove('.hero');
    var cloned = this.clone();

    var printSection = $('#printSection');

    if (printSection.length == 0) {

      printSection = $('<div id="printSection"></div>')

      $('body').append(printSection);

    }
    printSection.append(cloned);

    var toggleBody = $('body *:visible');
    toggleBody.hide();


    $('#printSection, #printSection *').show();

    window.print();
    printSection.remove();
    toggleBody.show();
  }
});

$(document).ready(function() {
  $(document).on('click', '#btnPrint', function(e) {
    e.stopPropagation();
    e.preventDefault();
    $('.printMe').printElem();
  });
});

$(window).on('afterprint', function () {
  var id =dbx_print_ids;
  if (id > "") {
    var xurl=window.location.href + '&reload=1'; 
    var url=window.location.href  + '&' + id;
    dbx_print_ids='';
    var updatedUrl = updateQueryParam(url, 'dbx_work', 'prn_save'); 
    dbx_callWithoutWaiting(updatedUrl); // Ersetze durch die tatsächliche URL
  }
  // Zusätzlicher Code nach dem Druck
});


