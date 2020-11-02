﻿/**
 * @license Copyright (c) 2003-2012, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

/**
 * @fileOverview Forms Plugin
 */

CKEDITOR.plugins.add( 'nid_forms', {
	requires: 'dialog,fakeobjects,image',
	lang: 'cs,en', // %REMOVE_LINE_CORE%
	onLoad: function() {
		CKEDITOR.addCss( '.cke_editable form' +
			'{' +
				'border: 1px dotted #FF0000;' +
				'padding: 2px;' +
			'}\n' );
    

		CKEDITOR.addCss( 'img.cke_hidden' +
			'{' +
				'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/hiddenfield.gif' ) + ');' +
				'background-position: center center;' +
				'background-repeat: no-repeat;' +
				'border: 1px solid #a9a9a9;' +
				'width: 16px !important;' +
				'height: 16px !important;' +
			'}' );
		
		CKEDITOR.addCss(
                                'img.cke_radio, img.cke_radio_checked' +
                                '{' +
                                        'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/radio_off.gif' ) + ');' +
                                        'background-position: center center;' +
                                        'background-repeat: no-repeat;' +
                                        'width: 16px !important;' +
                                        'height: 16px !important;' +
                                        'display: block !important;' +
                                        'float: left !important;' +
                                        'margin-top: 4px !important;' +
                                '}'+
                                'img.cke_radio_checked' +
                                '{' +
                                        'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/radio_on.gif' ) + ');' +
                                '}' );
		
		CKEDITOR.addCss(
                                'img.cke_checkbox, img.cke_checkbox_checked' +
                                '{' +
                                        'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/checkbox_off.gif' ) + ');' +
                                        'background-position: center center;' +
                                        'background-repeat: no-repeat;' +
                                        'width: 16px !important;' +
                                        'height: 16px !important;' +
                                        'display: block !important;' +
                                        'float: left !important;' +
                                        'margin-top: 4px !important;' +
                                '}'+
                                'img.cke_checkbox_checked' +
                                '{' +
                                        'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/checkbox_on.gif' ) + ');' +
                                '}' );
                
                CKEDITOR.addCss(
                                'img.cke_select' +
                                '{' +
                                        'background-image: url(' + CKEDITOR.getUrl( this.path + 'images/select.gif' ) + ');' +
                                        'background-position: center center;' +
                                        'background-repeat: no-repeat;' +
                                        'border: 1px solid #a9a9a9;' +
                                        'width: 16px !important;' +
                                        'height: 16px !important;' +
                                        'display: inline !important;' +
                                '}' );

	},
	init: function( editor ) {
		var lang = editor.lang,
			order = 0;

		// All buttons use the same code to register. So, to avoid
		// duplications, let's use this tool function.
		var addButtonCommand = function( buttonName, commandName, dialogFile ) {
				var def = {};
				commandName == 'form' && ( def.context = 'form' );

				editor.addCommand( commandName, new CKEDITOR.dialogCommand( commandName, def ) );

				editor.ui.addButton && editor.ui.addButton( buttonName, {
					label: lang.common[ buttonName.charAt( 0 ).toLowerCase() + buttonName.slice( 1 ) ],
					command: commandName,
					toolbar: 'forms,' + ( order += 10 )
				});
				CKEDITOR.dialog.add( commandName, dialogFile );
			};

		var dialogPath = this.path + 'dialogs/';
		!editor.blockless && addButtonCommand( 'Form', 'form', dialogPath + 'form.js' );
		addButtonCommand( 'Checkbox', 'checkbox', dialogPath + 'checkbox.js' );
		addButtonCommand( 'Radio', 'radio', dialogPath + 'radio.js' );
		addButtonCommand( 'TextField', 'textfield', dialogPath + 'textfield.js' );
		addButtonCommand( 'Textarea', 'textarea', dialogPath + 'textarea.js' );
		addButtonCommand( 'Select', 'select', dialogPath + 'select.js' );
		addButtonCommand( 'Button', 'button', dialogPath + 'button.js' );

		// If the "image" plugin is loaded.
		var imagePlugin = CKEDITOR.plugins.get( 'image' );
		imagePlugin && addButtonCommand( 'ImageButton', 'imagebutton', CKEDITOR.plugins.getPath( 'image' ) + 'dialogs/image.js' );

		addButtonCommand( 'HiddenField', 'hiddenfield', dialogPath + 'hiddenfield.js' );

		// If the "menu" plugin is loaded, register the menu items.
		if ( editor.addMenuItems ) {
			var items = {
				checkbox: {
					label: lang.forms.checkboxAndRadio.checkboxTitle,
					command: 'checkbox',
					group: 'checkbox'
				},

				radio: {
					label: lang.forms.checkboxAndRadio.radioTitle,
					command: 'radio',
					group: 'radio'
				},

				textfield: {
					label: lang.forms.textfield.title,
					command: 'textfield',
					group: 'textfield'
				},

				hiddenfield: {
					label: lang.forms.hidden.title,
					command: 'hiddenfield',
					group: 'hiddenfield'
				},

				imagebutton: {
					label: lang.image.titleButton,
					command: 'imagebutton',
					group: 'imagebutton'
				},

				button: {
					label: lang.forms.button.title,
					command: 'button',
					group: 'button'
				},

				select: {
					label: lang.forms.select.title,
					command: 'select',
					group: 'select'
				},

				textarea: {
					label: lang.forms.textarea.title,
					command: 'textarea',
					group: 'textarea'
				}
			};

			!editor.blockless && ( items.form = {
				label: lang.forms.form.menu,
				command: 'form',
				group: 'form'
			});

			editor.addMenuItems( items );

		}

		// If the "contextmenu" plugin is loaded, register the listeners.
		if ( editor.contextMenu ) {
			!editor.blockless && editor.contextMenu.addListener( function( element, selection, path ) {
				var form = path.contains( 'form', 1 );
				if ( form && !form.isReadOnly() )
					return { form: CKEDITOR.TRISTATE_OFF };
			});

			editor.contextMenu.addListener( function( element ) {
				if ( element && !element.isReadOnly() ) {
					var name = element.getName();

					if ( name == 'select' )
						return { select: CKEDITOR.TRISTATE_OFF };

					if ( name == 'textarea' )
						return { textarea: CKEDITOR.TRISTATE_OFF };

					if ( name == 'input' ) {
						switch ( element.getAttribute( 'type' ) ) {
							case 'button':
							case 'submit':
							case 'reset':
								return { button: CKEDITOR.TRISTATE_OFF };
							/*
							case 'checkbox':
								return { checkbox: CKEDITOR.TRISTATE_OFF };

							case 'radio':
								return { radio: CKEDITOR.TRISTATE_OFF };

							case 'image':
								return imagePlugin ? { imagebutton: CKEDITOR.TRISTATE_OFF } : null;

							default:
								return { textfield: CKEDITOR.TRISTATE_OFF };
							*/
							case 'text':
							case 'password':
								return { textfield : CKEDITOR.TRISTATE_OFF };
						}
					}

					//if ( name == 'img' && element.data( 'cke-real-element-type' ) == 'hiddenfield' )
					//	return { hiddenfield: CKEDITOR.TRISTATE_OFF };
					if ( name == 'img' )
					{       
						switch(element.data( 'cke-real-element-type' ))
						{
							case 'hiddenfield':
								return { hiddenfield : CKEDITOR.TRISTATE_OFF };
								
							case 'select':
								return { select : CKEDITOR.TRISTATE_OFF };
							
							case 'radio':
								return { radio : CKEDITOR.TRISTATE_OFF };
								
							case 'checkbox':
								return { checkbox : CKEDITOR.TRISTATE_OFF };
						}
					}
				}
			});
		}

		editor.on( 'doubleclick', function( evt ) {
			var element = evt.data.element;

			if ( !editor.blockless && element.is( 'form' ) )
				evt.data.dialog = 'form';
			//else if ( element.is( 'select' ) )
			//	evt.data.dialog = 'select';
			else if ( element.is( 'textarea' ) )
				evt.data.dialog = 'textarea';
			//else if ( element.is( 'img' ) && element.data( 'cke-real-element-type' ) == 'hiddenfield' )
			//	evt.data.dialog = 'hiddenfield';
			else if ( element.is( 'img' ) )
			{
				switch(element.data( 'cke-real-element-type' ))
				{
					case 'hiddenfield':
						evt.data.dialog = 'hiddenfield';
						break;
					case 'select':
						evt.data.dialog = 'select';
						break;
					case 'radio':
						evt.data.dialog = 'radio';
						break;
					case 'checkbox':
						evt.data.dialog = 'checkbox';
						break;
				}
			
			}
			else if ( element.is( 'input' ) ) {
				switch ( element.getAttribute( 'type' ) ) {
					case 'button':
					case 'submit':
					case 'reset':
						evt.data.dialog = 'button';
						break;
					/*
					case 'checkbox':
						evt.data.dialog = 'checkbox';
						break;
					case 'radio':
						evt.data.dialog = 'radio';
						break;
					*/
					case 'image':
						evt.data.dialog = 'imagebutton';
						break;
					/*
					default :
						evt.data.dialog = 'textfield';
						break;
					*/
					case 'text':
					case 'password':
						evt.data.dialog = 'textfield';
						break;
				}
			}
		});
	},

	afterInit: function( editor ) {
		var dataProcessor = editor.dataProcessor,
			htmlFilter = dataProcessor && dataProcessor.htmlFilter,
			dataFilter = dataProcessor && dataProcessor.dataFilter;

		// Cleanup certain IE form elements default values.
		if ( CKEDITOR.env.ie ) {
			htmlFilter && htmlFilter.addRules({
				elements: {
					input: function( input ) {
						var attrs = input.attributes,
							type = attrs.type;
						// Old IEs don't provide type for Text inputs #5522
						if ( !type )
							attrs.type = 'text';
						if ( type == 'checkbox' || type == 'radio' )
							attrs.value == 'on' && delete attrs.value;
					}
				}
			});
		}

		if ( dataFilter ) {
			dataFilter.addRules({
				elements: {
					input: function( element ) {
						//if ( element.attributes.type == 'hidden' )
						//	return editor.createFakeParserElement( element, 'cke_hidden', 'hiddenfield' );
						switch(element.attributes.type)
                                                {
                                                        case 'hidden':
                                                                return editor.createFakeParserElement( element, 'cke_hidden', 'hiddenfield' );
                                                        case 'radio':
                                                                if(element.attributes.checked)
                                                                {
                                                                        return editor.createFakeParserElement( element, 'cke_radio_checked', 'radio' );
                                                                }else
                                                                {
                                                                        return editor.createFakeParserElement( element, 'cke_radio', 'radio' );
                                                                }
                                                        case 'checkbox':
                                                                
                                                                if(element.attributes.checked)
                                                                {
                                                                        return editor.createFakeParserElement( element, 'cke_checkbox_checked', 'checkbox' );
                                                                }else
                                                                {
                                                                        return editor.createFakeParserElement( element, 'cke_checkbox', 'checkbox' );
                                                                }
                                                                break;
                                                }
					},
                                        select : function ( element )
                                        {
                                                return editor.createFakeParserElement( element, 'cke_select', 'select' );
                                        }
				}
			});
		}
	}
});

if ( CKEDITOR.env.ie ) {
	CKEDITOR.dom.element.prototype.hasAttribute = CKEDITOR.tools.override( CKEDITOR.dom.element.prototype.hasAttribute, function( original ) {
		return function( name ) {
			var $attr = this.$.attributes.getNamedItem( name );

			if ( this.getName() == 'input' ) {
				switch ( name ) {
					case 'class':
						return this.$.className.length > 0;
					case 'checked':
						return !!this.$.checked;
					case 'value':
						var type = this.getAttribute( 'type' );
						return type == 'checkbox' || type == 'radio' ? this.$.value != 'on' : this.$.value;
				}
			}

			return original.apply( this, arguments );
		};
	});
}
