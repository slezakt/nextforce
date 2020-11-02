var pagesAdmin = {
    //drzi info o zmenach na strance, kvuli filtrovani a strankovani datagridu, ktery reloaduje stranku, paklize je stranka zmenena a 
    //dochazi k reloadu, nabidne uzivatele save formu
    pages_change: false,
    //obsluha modálního okna
    forModalOnload: function() {
        var row = "";
        var t = this;
        
        $(document).on('click', '.delcont', function(event) {
            event.preventDefault();
            t.delCont($(this));
        });

        $('#edit-dialog').modal({show: false}).on('shown', function() {
            $('body').css('overflow', 'hidden');
        }).on('hidden', function() {
            $('body').css('overflow', 'auto');
        });
        $('a.editpage').click(function(e) {
            $(document).one('click', '#save_page', function(event) {
                event.preventDefault();
                save_page();
            });
            row = $(this).parent().parent('tr').children('td');
            e.preventDefault();
            $('#modal-form').load($(this).attr('href'), function() {
                t.sortOnLoad();
                $('.selectpicker').selectpicker('render');
                $('#edit-dialog').modal().show();
            });
        });
       
       //ulozi form v modální okně
        var save_page = function() {
            var url = $('#editPageForm').attr('action');
            $.post(url, $('#editPageForm').serialize(), function(data) {
                try {
                    var page_data = jQuery.parseJSON(data);
                    var i = 0;
                    $.each(page_data, function(key, value) {
                        row.eq(i).children('span').html(value);
                        row.eq(i).children('span').attr('title', value);
                        if (value)
                            row.eq(i).children('span').removeClass('null-text');
                        i++;
                    });
                    $('#edit-dialog').modal('hide');

                    return;
                }
                catch (err) {
                }
                ;
                $('#modal-form').html(data);
            });
        };
    },
    //jq ui sortable
    sortOnLoad: function() {       
        $('#bp_sortable').sortable({
            items: 'tr.item',
            axis: 'y',
            forceHelperSize: true,
            change: function(){pagesAdmin.pages_change = true;}
        });
        $('#bp_sortable').disableSelection(); 
    },
    //remove content
    delCont: function(cont) {        
        cont.parent().parent('tr').remove();
        
        pagesAdmin.pages_change = true;        
    },
            
    //add page      
    addpageOnLoad: function() {
       
       //kontroluje zmeny ve formulari
        $('#editPageForm').change(function() {pagesAdmin.pages_change = true;});
        
        $('.addpage').click(function(e) {
            e.preventDefault();            
            var cols = $(this).parents('tr').children('td');
            console.log(cols.eq(0).text());
            $('#bp_sortable').children('tbody').append('<tr class="item"><td class="desc">' + cols.eq(0).text() + '</td>\n\
            <td>' + cols.eq(1).text() + '</td><td>' + cols.eq(5).text() + '</td><td>' + cols.eq(6).text() + '</td>\n\
            <td><input type="hidden" name="cont[' + cols.eq(0).text() + ']" value="' + cols.eq(0).text() + '"></td>\n\
            <td><a class="delcont" id="' + cols.eq(0).text() + '" href="#"><img src="pub/img/admin/cross.png" alt="remove"></a></td>\n\
            </tr>');
            $(this).parents('tr').remove();            
            pagesAdmin.pages_change = true;
        });
        
        //filtrovani reloaduje page, nabidneme uzivateli save
        $('table.datagrid').parent('form').submit(function(e) {           
            if (pagesAdmin.pages_change === true) {
                if(confirm(confirm_save_pages_text)) {
                    return save_page();
                }            
            }
        });        

        //strankovani reloaduje page, nabidneme uzivateli save
        $('ul.paginator a,tr.rowHead a').click(function() {           
            if (pagesAdmin.pages_change === true) {
                if(confirm(confirm_save_pages_text)) {                   
                    return save_page();
                }
            }
        });
        
        //ulozi ajaxem form
        var save_page = function() {
           var isSave = true;
           $.ajax({type: 'POST',cache:false,url: $('#editPageForm').attr('action')+'?format=html',data: $('#editPageForm').serialize(),
               async:false,success:function(data){
                   try {var json = $.parseJSON(data);}catch(err){alert(error_save_pages_text);isSave=false;}        
               }}); 
           return isSave;
        };
    }
};

addLoadEvent('pagesAdmin.forModalOnload');
addLoadEvent('pagesAdmin.sortOnLoad');
addLoadEvent('pagesAdmin.addpageOnLoad');

