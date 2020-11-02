//plugin pro vkladani link z inBox LinkPickeru
CKEDITOR.plugins.add('inboxlink', {
    icons: 'inboxlink',
    init: function(editor) {
        editor.ui.addButton('Inboxlink', {
            label: 'Insert inBox link',
            command: 'addlink'         
        });
        
         editor.addCommand( 'addlink',{exec: function() {
                 $('#linkpicker').modal('show');
         }});
    }
});

