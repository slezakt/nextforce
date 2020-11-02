/**
 * The Inbox form dialog definition.
 *
 * Created out of the CKEditor Plugin SDK:
 * http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
 */

// Our dialog definition.
CKEDITOR.dialog.add( 'inboxformDialog', function( editor ) {
	
    return {

		// Basic properties of the dialog window: title, minimum size.
		title: editor.lang.inboxform.dialog_title,
		minWidth: 200,
		minHeight: 100,

		// Dialog window contents definition.
		contents: [
			{
				// Definition of the Basic Settings dialog tab (page).
				id: 'tab-basic',
				label: 'Basic Settings',

				// The tab contents.
				elements: [
                    {
                        type: 'select',
                        id: 'elementlist',
                        label: editor.lang.inboxform.element_list,
                        items: getFormItems()
                    },

                    {
						type: 'select',
						id: 'numberlist',
						label: editor.lang.inboxform.number_elements,
                        default: 1,
                        items: getNumberItems()   
					}
				]
			}
		],

        onOk: function() {
            var dialog = this;
            var el_name = dialog.getValueOf('tab-basic', 'elementlist');
            var el = formitems[el_name];
            if (!el) {
                return;
            }

            var n = dialog.getValueOf('tab-basic', 'numberlist');

            var iel = "";

            var input = CKEDITOR.dom.element.createFromHtml(el);

            for (var i = 0; i < n; i++) {
                if (input.findOne('[type="checkbox"],[type="radio"]')) {
                    input.findOne('[type="checkbox"],[type="radio"]').setAttribute('value',(i+1));
                }
                iel += input.getOuterHtml();
            }

            editor.insertHtml(iel);
            pmakerAdmin.checkFormNames(editor);

        }
	};
});

var formitems = [];

function getFormItems() {
    var items = [];
    formitems = pmakerAdmin.getFormElements();
    for (var e in formitems) {
        items.push([[e]]);
    }
    return items;
}

function getNumberItems() {
    var items = [];
   
    for (var i = 1;i < 6;i++) {
        items.push([[i]]);
    }
    
    return items;
}