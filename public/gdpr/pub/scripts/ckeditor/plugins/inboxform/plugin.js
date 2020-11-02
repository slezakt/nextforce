/**
 * Basic sample plugin inserting abbreviation elements into CKEditor editing area.
 *
 * Created out of the CKEditor Plugin SDK:
 * http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
 */

// Register the plugin within the editor.
CKEDITOR.plugins.add('inboxform', {
    // Register the icons.
    icons: 'inboxform',
    lang: 'en,cs',
    // The plugin initialization logic goes inside this method.
    init: function(editor) {

        // Define an editor command that opens our dialog.
        editor.addCommand('inboxform', new CKEDITOR.dialogCommand('inboxformDialog'));
        
        editor.addCommand('copyelement', {
            exec: function(editor) {
             var sel = editor.getSelection().getStartElement();
             var iel = CKEDITOR.dom.element.createFromHtml(sel.getParent().getOuterHtml());
             iel.insertAfter(sel.getParent());
             pmakerAdmin.checkFormNames(editor);
          }
        });

        // Create a toolbar button that executes the above command.
        editor.ui.addButton('inboxform', {
            // The text part of the button (if available) and tooptip.
            label: editor.lang.inboxform.title,
            // The command to execute on click.
            command: 'inboxform',
            // The button placement in the toolbar (toolbar group name).
            toolbar: 'insert'
        });

        if (editor.contextMenu) {
            editor.addMenuGroup('inboxformGroup');
            
            editor.addMenuItem('inboxformItem', {
                label: editor.lang.inboxform.duplicate, 
                icon: this.path + 'icons/inboxform.png',
                command: 'copyelement',
                group: 'inboxformGroup'
            });

            editor.contextMenu.addListener(function(el) {
                if (el.getParent().hasAttribute('data-phc-form-element')) {
                    if (el.getName() === 'img')
                    {
                        switch (el.data('cke-real-element-type'))
                        {
                            case 'select':
                                return {inboxformItem: CKEDITOR.TRISTATE_OFF};

                            case 'radio':
                                return {inboxformItem: CKEDITOR.TRISTATE_OFF};

                            case 'checkbox':
                                return {inboxformItem: CKEDITOR.TRISTATE_OFF};
                        }
                    } else if (el.getName() === "input" || el.getName() === "textarea") {
                        return {inboxformItem: CKEDITOR.TRISTATE_OFF};
                    }
                }
            });
        }

        // Register our dialog file. this.path is the plugin folder path.
        CKEDITOR.dialog.add('inboxformDialog', this.path + 'dialogs/inboxform.js');
    }
});

