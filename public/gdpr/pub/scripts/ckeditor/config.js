/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here.
	// For the complete reference:
	// http://docs.ckeditor.com/#!/api/CKEDITOR.config
    
    //nastaveni jazyka ckeditoru
    var ck_lang = 'en';
    //cz -> cs
    if (language === 'cz') {
        ck_lang = 'cs';
    }  
    
    var relative = 0;
   
   //specifikaci pro elfinder filemanager, zda ma vrátit pouze relativni adresu
    if (typeof CKEDITOR_ELF_RELATIVE !== "undefined") {
        relative = CKEDITOR_ELF_RELATIVE;
    }
    
    config.contentsCss = CKEDITOR.basePath + 'contents.css';  
    config.height=500;
    config.language = ck_lang;

    if (typeof CKEDITOR_ELFINDER_URL !== "undefined") {
        config.filebrowserBrowseUrl = CKEDITOR_ELFINDER_URL+'?relative='+relative;
    }
	// The toolbar groups arrangement, optimized for two toolbar rows.
	// Toolbar configuration generated automatically by the editor based on config.toolbarGroups.
    config.toolbar = [
        {name: 'clipboard', items: ['Source','Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo']},
        {name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll']},
        {name: 'links', items: ['Inboxlink','Link', 'Unlink', 'Anchor']},
        {name: 'insert', items: ['Image', 'SpecialChar','MediaEmbed','Youtube']},
        {name: 'tools', items: ['Maximize', 'ShowBlocks']},
        {name: 'colors', items: ['TextColor', 'BGColor']},
       '/',
        {name: 'basicstyles', items: ['Bold', 'Italic', 'Strike', 'Subscript', 'Superscript', '-', 'RemoveFormat']},
        {name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock']},
        {name: 'styles', items: ['Styles', 'Format', 'FontSize']},
        {name: 'forms', items: ['inboxform']}
    ];

	// Remove some buttons, provided by the standard plugins, which we don't
	// need to have in the Standard(s) toolbar.
	//config.removeButtons = '';

	// Se the most common block elements.
    config.format_h1 = { element : 'h2'};
    config.format_h2 = { element : 'h4'};
	config.format_tags = 'p;h1;h2;pre';
    
    config.entities = false;
    config.entities_latin = false;
    config.basicEntities = false;
    config.disableObjectResizing= true;

	// Make dialogs simpler.
	//config.removeDialogTabs = 'image:advanced;link:advanced';
    config.extraPlugins = 'inboxlink,floatingspace,contextmenu,tabletools,liststyle,mediaembed,youtube';
    
    config.allowedContent = true;
};

CKEDITOR.on('dialogDefinition', function(ev)
{
    // Take the dialog name and its definition from the event data.
    var dialogName = ev.data.name;
    var dialogDefinition = ev.data.definition;

    // Check if the definition is from the dialog we're
    // interested in (the 'link' dialog).
    if (dialogName == 'link')
    {
        // Remove the 'Target' and 'Advanced' tabs from the 'Link' dialog.
//        dialogDefinition.removeContents('target')
          dialogDefinition.removeContents('advanced');
//
        // Get a reference to the 'Link Info' tab.
        var infoTab = dialogDefinition.getContents('info');
//        infoTab.remove('protocol');
        infoTab.remove('browse');
    }
    
    if ( dialogName == 'image' )
		{
			dialogDefinition.removeContents( 'Link' );
		}
 
});
