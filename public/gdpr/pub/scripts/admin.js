adminBasic = {
	tablesorterSaveSettings: function(){
		$("table.tablesorter thead th").click(function(){
			var table = $(this).parents("table.tablesorter:first");
			var cookieName = 'tablesorterSort_'+ table.attr('id') + window.location.href;
			var configOld = readCookie(cookieName);
			var config = '';

			// Nachystame config do pole
			if(configOld){
				configOld = configOld.split(',');
				for(i=0; i<configOld.length; i++){
					configOld[i] = configOld[i].split(':');
				}
			}
			else{
				configOld = [];
			}

			// Provedeme se zpozdenim, protoze oni to taky delaj se zpozdenim...
			setTimeout(function() {
				// Nejdriv zjistime, jestli se jedna o rozsireni nebo nove zadani
				// vyhledavame nastavene hodnoty v minule konfiguraci
				var corresponding = 0;
				var newVals = [];
				var sortedBy = 0;
				var doneArray = [];
				var changed = false;
				for(i=0; i<configOld.length; i++){
					//alert(configOld[i][0]);
					var j=0;
					sortedBy = 0;
					$("thead th", table).each(function(){
						var val = null;
						if($(this).hasClass('headerSortDown')){
							val = 0;
							sortedBy++;
						}
						else if($(this).hasClass('headerSortUp')){
							val = 1;
							sortedBy++;
						}

						if(configOld[i][0] == j && val == configOld[i][1]){
							corresponding++;

							if(typeof(doneArray[j]) == 'number'){
								delete newVals[doneArray[j]];
							}
							doneArray[j] = true;
						}
						// Pokud se neshoduji, nahradime puvodni hodnotu v config old
						else if(configOld[i][0] == j && val != null){
							configOld[i][1] = val;
							changed = true;

							if(typeof(doneArray[j]) == 'number'){
								delete newVals[doneArray[j]];
							}
							doneArray[j] = true;
						}

						if(val != null && !doneArray[j] && typeof(doneArray[j]) != 'number'){
							//alert('co'+j + ':' + val + ' doneA: '+doneArray[j]);
							doneArray[j] = newVals.length;
							newVals[newVals.length] = ',' + j + ':' + val;
						}

						j++;
					});
				}
				newVals = newVals.join('');
				//alert('oldSorted: '+configOld.length + ' sorted:' + sortedBy + ' correspond: ' + corresponding +' new: ' + newVals);

				// Pokud je pocet sortu z minula vetsi nez aktualni, stare zahodime
				if(sortedBy < configOld.length || (sortedBy == configOld.length && !changed)){
					var i = 0;
					$("thead th", table).each(function(){
						if(config.length){
							var delimiter = ',';
						}
						else{
							var delimiter = '';
						}

						if($(this).hasClass('headerSortDown')){
							config = config + delimiter + i + ':0';
						}
						else if($(this).hasClass('headerSortUp')){
							config = config + delimiter + i + ':1';
						}
						i++;
					});
				}
				// Pouzijeme stary config rozsireny o nove prvky
				else{
					for(i=0; i<configOld.length; i++){
						configOld[i] = configOld[i].join(':');
					}
					configOld = configOld.join(',');

					config = configOld + newVals;
					//alert('final: '+configOld+' | '+newVals);
				}
				//alert('final: '+config);



				createCookie(cookieName,config,100);
			},2);

		});
	},

	tablesorterPrepareSettings: function(){
		$("table.tablesorter").each(function(){
			var table = $(this);
			var cookieName = 'tablesorterSort_'+ table.attr('id') + window.location.href;
			//eraseCookie(cookieName);
			var config = readCookie(cookieName);
			var sortList = [];


			if(config){
				// Nachystame config do pole
				sortList = config.split(',');
				for(i=0; i<sortList.length; i++){
					sortList[i] = sortList[i].split(':');
				}
			}

			/*
			if(config){
				for(i=0; i<config.length; i++){
					if(config[i] == '-'){
						continue;
					}
					else{
						sortList[sortList.length] = [i, config[i]];
					}
				}
			}
			*/

			table.tablesorter({sortList: sortList});
			//table.tablesorter();

		});
	},

	pagesAddPageId: function(){
		$("#pagesContentTable tbody tr").click(function(){
			var pagesInput = $("#pagesContentListInput");

			var valuesA = pagesInput.attr('value').split(' ');
			var newVal = parseInt($("td:first", this).text());

			if(isNaN(newVal)){
				return;
			}

			valuesA[valuesA.length] = newVal;
			pagesInput.attr('value', $.trim(valuesA.join(' ')));
		});
	},
    aceLoad: function() {
        if ($('div[id*="ace-editor"]').length !== 0) {
            //vrati poradi ace editoru na strance z id -> ace-editor_1 ...
            function getAceSeq(id) {
                var seq = id.split('_');
                if (seq[1])
                    return "_" + seq[1];
                return "";
            }

            $('div[id*="ace-editor"]').each(function() {
                //id pro pripad vice editoru na strance
                var ace_id = $(this).attr('id');
                var ace_mode;
                if ($(this).attr('mode') === "") {
                    ace_mode = "html";
                } else {
                    ace_mode = $(this).attr('mode');
                }
                var editor = ace.edit(ace_id);
                editor.getSession().setUseWrapMode(true);
                editor.setFontSize(12);
                var ace_height = $('#' + ace_id).height();
                var ace_width = $('#' + ace_id).width();
                ace.config.set("basePath", "pub/scripts/ace");
                ace.config.set("themePath", "pub/scripts/ace");
                ace.config.set("modePath", "pub/scripts/ace");
                ace.config.set("workerPath", "pub/scripts/ace");
                editor.setTheme("ace/theme/xcode");

                if (ace_mode) editor.getSession().setMode("ace/mode/"+ace_mode);

                ace.config.loadModule("ace/ext/language_tools", function() {
                    editor.setOptions({
                        enableSnippets: true,
                        enableBasicAutocompletion: true
                    });
                });

                var textarea = $(this).parent().prev('textarea');
                editor.getSession().setValue(textarea.val());
                editor.getSession().on('change', function() {
                    textarea.val(editor.getSession().getValue());
                });
                
                //prirazeni akce k checkboxu ACE editor
                $('#ace-show' + getAceSeq(ace_id)).on('change', function() {
                    var ace_editor = $('#ace-wrap' + getAceSeq(ace_id));
                    if ($(this).is(':checked')) {
                        ace_editor.show();
                        textarea.hide();
                        editor.getSession().setValue(textarea.val());
                    } else {
                        ace_editor.hide();
                        textarea.show();
                    }
                });

                //tlacitko toolbaru fullscreen
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-fullscreen').on('click', function(e) {
                    var ace_wrap = $('#ace-wrap' + getAceSeq(ace_id));
                    e.preventDefault();
                    if (!$(this).hasClass('active')) {

                        //ukládání ajaxem z fulscrenu, povoluje data atribut data-ace-submit u textarea
                        if (textarea.data('ace-submit') === "ajax") {
                            var submit = ace_wrap.parents('form').find('[type="submit"]').clone();
                            ace_wrap.parents('form').find('[type="submit"]').parent().addClass('submit-wrap');
                            ace_wrap.parents('form').find('[type="submit"]').remove();
                            submit.css('margin-left', '5px');
                            ace_wrap.children('.ace-toolbar').append(submit);
                        }

                        $(this).addClass('active');
                        ace_wrap.addClass('ace-max');
                        ace_resize($('#' + ace_id));
                        editor.resize(true);
                        $(window).bind('resize', function() {
                            ace_resize($('#' + ace_id));
                        });
                    } else {

                        //ukládání ajaxem z fulscrenu, povoluje data atribut data-ace-submit u textarea
                        if (textarea.data('ace-submit') === "ajax") {
                            var submit = ace_wrap.children('.ace-toolbar').find('[type="submit"]').clone();
                            ace_wrap.children('.ace-toolbar').find('[type="submit"]').remove();
                            submit.css('margin-left', '0px');
                            $('.submit-wrap').append(submit);
                        }

                        $(this).removeClass('active');
                        ace_wrap.removeClass('ace-max');
                        $('#' + ace_id).css('height', ace_height);
                        $('#' + ace_id).css('width', ace_width);
                        $('body').css('overflow', 'auto');
                        $(window).unbind('resize');
                        editor.resize(true);
                    }
                });

                //funkce ace editoru search na tlacitku toolbaru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-search').on('click', function(e) {
                    e.preventDefault();
                    ace.config.loadModule("ace/ext/searchbox", function(e) {e.Search(editor);});
                });

                //funkce ace editoru replace na tlacitku toolbaru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-replace').on('click', function(e) {
                   e.preventDefault();
                   ace.config.loadModule("ace/ext/searchbox", function(e) {e.Search(editor, true);});
                });

                //funkce ace editoru undo na tlacitku toolbaru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-undo').on('click', function(e) {
                    e.preventDefault();
                    editor.undo();
                    editor.focus();
                });

                //funkce ace editoru redo na tlacitku toolbaru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-redo').on('click', function(e) {
                   e.preventDefault();
                   editor.redo();
                    editor.focus();
                });

                //funkce ace editoru show settings na tlacitku toolbaru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-settings').on('click', function(e) {
                    e.preventDefault();
                    ace.config.loadModule("ace/ext/settings_menu", function(module) {
                        module.init(editor);
                        editor.showSettingsMenu();
                    });
                });

                //vklada tagy do editoru
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-tag').on('click', function(e) {
                    e.preventDefault();
                    var code = $(this).attr('tag');
                    var pos = code.search(/\$#\$/);
                    code = code.replace('$#$', '');
                    cursor = editor.getCursorPosition();
                    cursor.column += pos;
                    editor.insert(code);
                    editor.moveCursorTo(cursor.row, cursor.column);
                    editor.focus();
                });

               //vlozeni sablony do editoru a nastaveni kurzoru na oznacene misto $#$
                $('#ace-wrap' + getAceSeq(ace_id) + ' .ace-tpl').on('click', function(e) {
                    e.preventDefault();
                    var code = $(this).children('.ace-tpl-code').text();
                    var pos = code.search(/\$#\$/);
                    code = code.replace('$#$', '');
                    cursor = editor.getCursorPosition();
                    cursor.column += pos;
                    editor.insert(code);
                    editor.moveCursorTo(cursor.row, cursor.column);
                    editor.focus();
                });
            });

            //metoda resi zmenu velikosti ace editoru resp jeho maximalizaci
            var ace_resize = function(el) {
                 $('body').css('overflow', 'hidden');
                 el.css('height', $(window).height()-el.position().top);
                 el.css('width', $(window).width());
            };
        }
    },
    tooltipLoad: function() {
        $('.tip').tooltip({container: 'body',trigger:'hover', delay: {show: 600, hide: 50}}).on('show', function(e) {
            e.stopPropagation();
        }).on('hidden', function(e) {
            e.stopPropagation();
        });
    },
	filetreeLoad: function()
	{

		$('div.filetree').each(function(){
			$(this).fileTree({
				script: 'filetree/list/',
				multiFolder: true,
				dragAndDrop: false,
				dirExpandCallback: function(dir) {},
				dirCollapseCallback: function(dir) {return true; }
			});
		});

		//$('div.filetree a.link').live('dblclick',function(){
        // as of jQuery 1.7+
        $(document).on('dblclick','div.filetree a.link',function(){

			newname=prompt('Rename to: ',jQuery(this).data('basename'));
			//var match = /^[a-zA-Z0-9_-]*\.{0,1}[a-zA-Z0-9]*$/.test(newname);
			var match = true; // no restrictions for destination path
			if (newname && match) {
				from = jQuery(this).attr('rel');

                jQuery(this).attr('href', '?rename=' + encodeURIComponent(from) + '&'
				+ 'to=' + encodeURIComponent(newname));

				window.location.replace(jQuery(this).attr('href'));
			}
			return false;
		});

        var t_out;

        $(document).on('mouseenter','.popover_img',function() {
           var file =$(this).attr('href');
           var pop = $(this).popover({container: 'body',trigger: 'manual',placement: 'left',html:true,
               content:'<img class="pop_thumb" src="./'+file+'" />',title:'Obrázek',
               template: '<div class="popover popimg"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>'
            });
           t_out = setTimeout(function(){pop.popover('show');},1000);
        });

         $(document).on('mouseleave','.popover_img',function() {
           var pop = $(this).popover();
           clearTimeout(t_out);
           pop.popover('destroy');
        });
	},

    datePickLoad: function() {
        dformat = null;
        if (typeof datepicker !== "undefined") {
            dformat = datepicker;
        }
      $('.datetimepicker').datetimepicker({

          beforeShow: function(input,inst) {
             setTimeout(function(){
                 //oprava pozicovani datatimepickeru
                 inst.dpDiv.css('z-index',1040);
                  if(inst.dpDiv.height() > input.offsetTop) {
                     inst.dpDiv.css('top',input.offsetTop+input.offsetHeight);
                  }
              },10);

          },
          dateFormat: dformat,
          firstDay:1,
          timeFormat: "H:mm",
          pickerTimeFormat:"H:mm",
          showSecond:false
      });
    },

    dgAutocomplete: function()
    {
        var cache = {};
        $(".datagrid .autocomplete input").each(function() {
            $(this).autocomplete({
                // source:
                source: function(request, response) {

                    var term = request.term;
                    var id = this.element[0].id;

                    if ( term in cache ) {
                        response( cache[ term ] );
                        return;
                    }

                    jQuery.ajax({
                        url: "",
                        dataType: "json",
                        data: {
                            term : request.term,
                            hitlist : id
                        },
                        success: function(data) {
                            var array_data = [];
                            for (property in data) { array_data.push(data[property]); }
                            cache[ term ] = array_data;
                            response(array_data);
                        }
                    });
                }
                ,minLength: 2
                /*,select: function( event, ui ) {
                 console.log( ui.item ?
                 "Selected: " + ui.item.value + " aka " + ui.item.id :
                 "Nothing selected, input was " + this.value );
                 } */
            });
        });

    },

    dgDefaultButton: function()
    {
        $(".datagrid input").bind("keydown", function(event) {
            // track enter key
            var keycode = (event.keyCode ? event.keyCode : (event.which ? event.which : event.charCode));
            if (keycode == 13) { // keycode for enter key
                // force the 'Enter Key' to implicitly click the Update button
                $('.datagrid .default-action-button').click();
                return false;
            } else  {
                return true;
            }
        }); // end of function

    },

    dgTooltip: function()
    {
        //$( '.datagrid .rowFilters').tooltip();
        $( '.datagrid' ).each(function(){
            $(this).tooltip();
        });
    },

    dgjQueryUI: function() {
        $(".datagrid tfoot li").hover(
            function()
            {
                $(this).addClass("ui-state-hover");
            },
            function()
            {
                $(this).removeClass("ui-state-hover");
            }
        );

        $(".datagrid thead th.sortable").hover(
            function()
            {
                $(this).addClass("ui-state-hover");
            },
            function()
            {
                $(this).removeClass("ui-state-hover");
            }
        );

        $(".datagrid tbody tr").hover(
            function()
            {
                $(this).children("td").addClass("ui-state-hover");
            },
            function()
            {
                $(this).children("td").removeClass("ui-state-hover");
            }
        );


        /*$(".datagrid tbody tr").click(function(){

            $(this).children("td").toggleClass("ui-state-highlight");
        });*/

    },

    linkConfirm: function() {
        $(document).on('click', 'a[data-confirm], input[data-confirm]', function() {
            if (!confirm($(this).attr('data-confirm'))) {
                return false;
            }
        });
    },

    bootstrapSelect: function() {
        $('select.selectpicker').addClass('show-menu-arrow').selectpicker({
            container:'body',
            selectedTextFormat: 'count>1'
        });
    },

    //dialog pro vkladani linku do ace nebo wysiwigu
    linkPicker: function() {

      $(document).on('click','.linkpicker a',function(e) {
         e.preventDefault();
      });

      var formpick;
      var current_id;

        $('.linkpicker').modal({show: false}).on('shown', function() {
            //oprava bootstrap modal - aby se nescrolovala stranka
            $('body').css('overflow', 'hidden');
            current_id = $(this).attr('id');
            $('#' + current_id + '_do_links_find').focus();
            $('#' + current_id + ' input').keyup(do_find);
        }).on('hidden', function() {
            pickerClose();
            $('body').css('overflow', 'auto');
        });

        var do_find = function() {
            if ($(this).val().length > 2) {
                $('#' + current_id + '_linkpicker-select').hide();
                pickerClose();
                $('#' + current_id + '_linkpicker-find').show();
                var res = find(json_links, $(this).val());
                showFindResult(res);
            } else {
                $('#' + current_id + '_linkpicker-select').show();
                $('#' + current_id + '_linkpicker-find').hide();
            }
        };

        //odstrani hledani
        $('.linkpicker-find-close').click(function(){
            $(this).prev().val('');
             $('#' + current_id + '_linkpicker-select').show();
             $('#' + current_id + '_linkpicker-find').hide();
        });

        //nahradi select za odkaz na linkpicker
        if ($('.form-linkpicker').length !== 0) {

            $('.form-linkpicker').each(function() {
                $(this).parent().append('<div class="btn-group"><a class="formtext-linkpicker-open btn" href="#linkpicker">'+
                        $(this).find("option:selected").text()+'</a><a href="#" class="formtext-linkpicker-rmlink btn"><i class="icon-remove"></i></a></div>');
            });

            $(document).on('click', '.formtext-linkpicker-open', function(e) {
                formpick = $(this);
                $($(this).attr('href')).modal('show');
                e.preventDefault();
            });
            //odstranovac vybraneho linku ve formech
             $(document).on('click', '.formtext-linkpicker-rmlink', function(e) {
                $(this).parent().prev().val(0);
                $(this).prev().text($(this).parent().prev().find("option[value='0']").text());
                $('#'+current_id).modal('hide');
                e.preventDefault();
            });
         }

        //escapovani regularnich vyrazu
        RegExp.escape = function(string) {
            return string.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
        };

        //funkce pro nastaveni pocatecniho pismena na velke
        String.prototype.capitalize = function() {
            return this.charAt(0).toUpperCase() + this.slice(1);
        };

        //vyhledavani linku
        //obj prohlevany json
        //val hledany vyraz
        //parent aktualni rodic prohledane vetve
        var find = function(obj, val, parent,color) {
            var result = [];
            //barvy dle bootstrap btn- class
            var def_color = {
               categories:'danger',
               external:'success',
               issues: 'inverse',
               resources: 'info'
            };

            $.each(obj, function(i, v) {

                if (v !== null && typeof v === "object") {
                    if (typeof v.name !== 'undefined') {
                        var reg = new RegExp(RegExp.escape(val), 'i');
                        if (reg.test(v.name)) {
                            v.node = parent;
                            v.color = color;
                            result.push(v);
                        }
                        key = v.name;
                    } else {
                        key = i;
                    }
                    var current;
                    
                    if (parent) {
                        current = parent.capitalize() + ' > ' + String(key).capitalize();
                    } else {
                        color = def_color[key];
                        current = key.capitalize();
                    }

                    result = find(v,val,current,color).concat(result);
                }
            });

            return result;
        };


      $(document).on('click', '.linkpicker_sel', function() {

          if (typeof formpick !== 'undefined') {
                formpick.parent().prev().val($(this).attr('tag'));
                formpick.text($(this).text());
                $('#'+current_id).modal('hide');
                formpick = undefined;
             return;
          }

            if (typeof CKEDITOR !== 'undefined') {

                var ci = 'editarea';

                if (typeof CKEDITOR_CURRENT !== 'undefined') {
                    ci = CKEDITOR_CURRENT;
                }

                if (typeof CKEDITOR.instances[ci] !== 'undefined') {
                    var selection = CKEDITOR.instances[ci].getSelection();
                    if (selection.getSelectedElement()) {
                        sel_el = selection.getSelectedElement().$.outerHTML;
                    } else if (selection.getSelectedText()) {
                        sel_el = selection.getSelectedText();
                    } else {
                        sel_el = $(this).text();
                    }
                    CKEDITOR.instances[ci].insertHtml('<a href="%%link#' + $(this).attr('tag') + '%%">' + sel_el + '</a>');
                    $('#' + current_id).modal('hide');
                    return;
                }
            }

            var _id = current_id.split('_');
            var ace_id = "ace-editor";
            if(_id.length>1) {
                var ace_id = ace_id + '_' + _id[_id.length-1];
            }

            var editor = ace.edit(ace_id);
            editor.insert('%%link#'+$(this).attr('tag')+'%%');
            editor.focus();
            $('#'+current_id).modal('hide');

        });

      $(document).on('mouseenter','.exp',(function(){
          $(this).trigger('click');
      }));

      //otevre podokno linkpickeru
      $(document).on('click','.exp',(function(){
          $(this).parent().addClass('active').siblings().removeClass('active');
          var col = $(this).attr('href');
          $('#'+current_id+'_lcol_3').hide();
          $(col).show();
          popLinks(col);
          adminBasic.tooltipLoad();
      }));

      //zavre jednotlive casti linkpickeru
      var pickerClose = function() {
        $('#'+current_id+'_lcol_2').hide();
        $('#'+current_id+'_lcol_3').hide();
        $('#'+current_id+'_lcol_1 li').siblings().removeClass('active');
      };

      //zobrazi vysledky vyhledavani
      var showFindResult = function(res) {
          $('.linkpicker-find-result').html('');
          if (res.length === 0) {
              $('.linkpicker-find-noresult').show();
               return;
          } else {
               $('.linkpicker-find-noresult').hide();
          }
          $.each(res,function(i,v) {
              var tooltip;
              if (v.tooltip) {
                  tooltip = v.tooltip + ', ' + v.node;
              } else {
                  tooltip = v.node;
              }
              $('.linkpicker-find-result').append('<a href="#" data-original-title="'+tooltip+'" \n\
                    class="btn btn-small btn-'+v.color+' linkpicker_sel tip" tag="'+v.link_id+'">'+v.name+'</a>');
          });
           adminBasic.tooltipLoad();
      };

      //naplni aktualni okno linkpickeru linky
        var popLinks = function(col) {
            $('ul'+col).html('');
            var type = $('#'+current_id+'_lcol_1 li.active').attr('id');
            if (col === "#"+current_id+"_lcol_3") {
               var subtype = $('#'+current_id+'_lcol_2 li.active').attr('id').replace('sub','');
               links = json_links[type][subtype]['pages'];
            } else {
                links = json_links[type];
            }

            if (typeof links === 'undefined') {
                return '';
            }

            for (key in links) {
                if (links[key].link_id) {
                    var link = '';
                    var tooltip = '';
                    if (links[key].tooltip) {
                       tooltip = '('+links[key].tooltip+')';
                    }
                    if (typeof links[key].pages !== 'undefined') {
                        link = '<li id="sub'+key+'" ><a data-original-title="'+ links[key].name +' ' + tooltip +'" class="linkpicker_sel tip" tag="' + links[key].link_id + '" href="#">' + links[key].name +'</a>\n\
                                <a class="exp arw" href="#'+current_id+'_lcol_3"><i class="icon-chevron-right"></i></a></li>';
                    } else {
                        link = '<li><a style="width:90%;" data-original-title="'+ links[key].name +' ' + tooltip +'" class="linkpicker_sel tip" tag="' + links[key].link_id + '" href="#">' + links[key].name +'</a></li>'
                    }
                    $('ul'+col).append(link);
                }
            }
        };

    },

	forOnload: function(){
		/*$.tablesorter.defaults.widgets = ['zebra'];
		this.tablesorterPrepareSettings();
		this.tablesorterSaveSettings();*/


        this.dgAutocomplete();  // jquery.js, jquery-ui.js
        this.dgTooltip();       // jquery.js, jquery-ui.js
        this.dgDefaultButton(); // jquery.js, jquery-ui.js
        this.linkConfirm();     // jquery.js
        this.tooltipLoad();
		this.pagesAddPageId();
        this.bootstrapSelect(); // jquery.js, bootstrap.js
        this.aceLoad();         //ace/ace.js
	//	this.editareaLoad();    // editarea/edit_area_full.js
		this.filetreeLoad();    // jquery.filetree.js, jquery.hoverintent.js
        this.datePickLoad(); //datatimepicker
        this.linkPicker();// vybirac linku


	}


}
addLoadEvent('adminBasic.forOnload');
