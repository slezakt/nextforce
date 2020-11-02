var wysiwigAdmin = {
    forOnload: function() {
        
        $('.editarea').each(function(){
           
           var id  = $(this).attr('id');
           var r = readCookie('wysiwig_'+id);
           
           if(r) {
               createWysiwig(r);
           }
     
        });

        
        $('.wysiwigSwitch').click(function(e) {
            e.preventDefault(true);  
            
            var rank = $(this).data('rank');
            var editor = ace.edit('ace-editor'+rank);
            
            var wysiwig = CKEDITOR.instances['editarea'+rank];

            if (wysiwig) {
                eraseCookie('wysiwig_editarea'+rank);
                //preda content ace + prelozi %%static%%               
                editor.getSession().setValue(CKEDITOR.instances['editarea'+rank].getData().replace(new RegExp(EDITOR_CONTENT_BASEPATH,'g'),'%%static%%'));
                removeWysiwig(rank);
                $(this).removeClass('active');
            } else {
                //ulozi aktualni zvoleny editor -> wysiwig
                createCookie('wysiwig_editarea'+rank, rank, 30);
                createWysiwig(rank);
                $(this).addClass('active');  
                CKEDITOR.instances['editarea'+rank].setData(editor.getSession().getValue().replace(new RegExp('%%static%%','g'),EDITOR_CONTENT_BASEPATH));
            }
        });

        //zapne wysiwig
        function createWysiwig(rank) {
            $('#ace-wrap'+rank).hide();
            $('#ace-check'+rank).hide();
            CKEDITOR.replace('editarea'+rank);           
        }

        //vypne wysiwig
        function removeWysiwig(rank) {
            $('#ace-wrap'+rank).show();
            $('#ace-check'+rank).show();
            $('#ace-check'+rank+' input').prop('checked', true);           
            CKEDITOR.instances['editarea'+rank].destroy();
        }
    }
};

addLoadEvent('wysiwigAdmin.forOnload');

