var bulletinsAdmin = {

    forOnload: function(){

        $('#bp_sortable').sortable({
           items: 'tr.item',
          axis: 'y'
        });
        //$('#bp_sortable').disableSelection();
    }

};

addLoadEvent('bulletinsAdmin.forOnload');