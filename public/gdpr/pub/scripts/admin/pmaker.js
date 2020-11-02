//trida pro spravu prezentaci
//prezentace umístěna v iframe, který se edituje z parent window, template umístěn ve skrytém iframe pro načítání vzorových slidů, inputů
//v prezentaci je nutné označit container rodičovský element ve, které jsou umístěny slidy data atributem data-phc-slides="1"
//data atribut data-phc-editable="1" slouží k označení editovatelných částí prezentace, některé prvky nejsou ckeditory povoleny k editování např. ul, ol
//data atribut data-phc-form="Název inputu" označuje inputy respektive kus jejich html kódu, které jsou načteny do pluginu ckeditoru pro vkládání inputů
//jednotlivé snímky respektive elementy snímku je možné označi data atributem data-phc-slide-name = "Název snímku" podle kterého se pojmenuje šablona pro vložení snímku - nejdřivě se podívá do textů podle pmaker.template.<<nazev_snimku>>, nenajde-li použije název přímo z atributu, není-li atribut pojmenuje se jako EMPTY
//některé metody je třeba volat přímo z javascriptu prezentace, ty jsou v prezentaci umístěny do objektu PhcEditablePressentation? v pmaker.js je třeba doplnit volání
var pmakerAdmin = {
    /**
     * Iframe prezentace
     * @type @call;$@call;get@pro;contentWindow
     */
    frame: null,
    /**
     * Iframe template
     * @type @call;$@call;get@pro;contentWindow
     */
    template_frame: null,
    /**
     * Základní barva zástupců šablon
     * @type String
     */
    basic_template_color: "#CCEDF6",
    /**
     * vygenerované barvy
     * @type Array
     */
    color: [],
    /**
     * current ckeditor instance
     * @type type
     */
    ckeditor_current: null,
    /**
     * Scale presentation
     * @type Number|@exp;scale@call;toFixed|v
     */
    scale: 1,
    /**
     * Aktuální pozice slidu
     * @type Number
     */
    currentSlidePosition: 1,
    /**
     *Default height presentation
     * @type @exp;pmakerAdmin@pro;frame@call;$@call;height
     */
    default_height: null,
    /**
     * Rezim editace sablony
     * @type Boolean
     */
    modeEditTemplate: false,
    forOnload: function() {
            pmakerAdmin.frame = $('#presentation').get(0).contentWindow;
            pmakerAdmin.default_height = pmakerAdmin.frame.$('body').get(0).scrollHeight;
            pmakerAdmin.frameHeight(false);
            pmakerAdmin.slidesEvent();
            if ($('#pmaker-template').length !== 0) {
                //odebereme nastaveni layoutu pokud needitujeme primo sablonu
                pmakerAdmin.frame.$('[data-phc-settings]').remove();
                pmakerAdmin.template_frame = $('#pmaker-template').get(0).contentWindow;
                pmakerAdmin.templateLoad();
            } else {
                this.modeEditTemplate = true;
            }
            //prepiseme default styly ckeditoru
            CKEDITOR.stylesSet.add('default', pmakerAdmin.getStylesSet());
            pmakerAdmin.slideslineLoad();
            pmakerAdmin.loadWysiwyg();
            pmakerAdmin.zoomer();
    },
    /**
     * Priradi ke slidu nazvy z textů
     * @param {string} slide_name
     * @returns {TEXTS_TPL_NAME.empty}
     */
    templateGetName: function(slide_name) {
        if (slide_name) {
            if (TEXTS_TPL_NAME[slide_name]) {
                return TEXTS_TPL_NAME[slide_name];
            } else {
                return slide_name;
            }
        }
        else {
            return TEXTS_TPL_NAME['empty'];
        }
    },
    /**
     * Načte vzorové slajdy to listu
     * @returns {undefined}
     */
    templateLoad: function() {
        
        //jsou k dispozice nejake slidy?
        if(pmakerAdmin.template_frame.$('[data-phc-slides="1"]').children().length <= 0) {
            return;
        }
        var cont_sl = "";

        var color = $.xcolor.analogous(pmakerAdmin.basic_template_color, pmakerAdmin.template_frame.$('[data-phc-slides="1"]').children().length, 5);

        pmakerAdmin.template_frame.$('[data-phc-slides="1"]').children().each(function(i) {
            var slide_name = $(this).data('phc-slide-name');
            pmakerAdmin.color[slide_name] = color[i];
            cont_sl += '<li style="background-color:' + color[i] + ';" data-template-slide="' + i + '"><a href="#">' + pmakerAdmin.templateGetName(slide_name) + '</a>';
        });

        $('.slides-template-list').html(cont_sl);
        $("#draggable li").draggable({
            connectToSortable: "#sortable",
            helper: "clone",
            revert: "true"
        });
        $("#draggable li").disableSelection();

    },
    /**
     * Načte slidy do slide line listu
     * @param {int} step poradove cislo slidu, ktery se nastavi je aktualni 
     * @returns {undefined}
     */
    slideslineLoad: function(step) {
        if (!step) {
            step = 0;
        }
        var cont_sl = "";
        pmakerAdmin.frame.$('[data-phc-slides="1"]').children().each(function(i) {
            var slide_name = "";
            if ($(this).data('phc-slide-name')) {
                slide_name = $(this).data('phc-slide-name');
            } else {
                slide_name = "empty";
            }

            cont_sl += '<li style="background-color:' + pmakerAdmin.color[slide_name] + '" ><a href="#" class="slides-line-num">' + (i + 1) + '</a>\n\
                    <span class="slides-line-sbar">\n\
                    <a class="slides-line-remove" href="#"><span class="glyphicon glyphicon-trash"></span></a>\n\
                    <a class="slides-line-duplicate" href="#"><span class="glyphicon glyphicon-plus-sign"></span></a>\n\
                    </span></li>';
        });
        $('.slides-line-list').html(cont_sl);
        pmakerAdmin.slideslineSetWidth();

        if (pmakerAdmin.frame.$('[data-phc-slides="1"]').children().length > 1) {
            $('.slides-line-remove').css('display', 'inline');
        } else {
            $('.slides-line-remove').css('display', 'none');
        }
        pmakerAdmin.slideslineSortable(false);
        pmakerAdmin.slideslineSetActive(step);
    },
    /**
     * Nastavi sirku slide line listu 
     * @returns {undefined}
     */
    slideslineSetWidth: function() {
        var c = $('.slides-line-list').children().length;
        var v = $('.slides-line-list').children().outerWidth(true);
        $('.slides-line-list').width(c * v);
    },
    /**
     * Nastavi vysku iframu podle vysky prezentace
     * @param {int} forcescale nastavi scale podle parametru
     * @returns {undefined}
     */
    frameHeight: function(forcescale) {

        if (!forcescale) {
            var scale = ($(window).height() - $('#presentation').offset().top - 100) / pmakerAdmin.default_height;
            if (scale < 1) {
                pmakerAdmin.scale = scale.toFixed(1);
            }
        }
        $('#presentation').height(pmakerAdmin.default_height);
        $('#presentation').css('transform', 'scale(' + pmakerAdmin.scale + ')');
        $('.cke').css('transform', 'scale(' + pmakerAdmin.scale + ')');
        $('#presentation').css('transform-origin', '0 0');
        $('#presentation').parent().height((pmakerAdmin.default_height * pmakerAdmin.scale));

    },
    /**
     * 
     * @param {int} startposition puvodni poradi snimku
     * @param {int} newposition nove poradi snimku
     * @returns {undefined}
     */
    frameSlideChangePosition: function(startposition, newposition) {
        if (startposition > newposition) {
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(startposition).insertBefore(pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(newposition));
        } else {
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(startposition).insertAfter(pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(newposition));
        }
        pmakerAdmin.slideslineSortable(true);
        pmakerAdmin.slideslineChangeNumbers(newposition);
    },
    /**
     * Razeni slide line listu - jQuery UI sortable
     * @param {boolean} disabled povolit řazení
     * @returns {undefined}
     */
    slideslineSortable: function(disabled) {
        $('#sortable').sortable({
            placeholder: "slide-placeholder",
            disabled: disabled,
            start: function(e, ui) {
                ui.placeholder.css("border", "dashed 1px #888");
                ui.placeholder.height(ui.item.height());
                ui.placeholder.width(ui.item.width());
                pmakerAdmin.slideslineSetWidth();
            },
            update: function(event, ui) {
                var startposition = Number(ui.item.text() - 1);
                var newposition = ui.item.index();
                if (typeof ui.item.data('template-slide') !== "undefined") {
                    ui.item.text("");
                    pmakerAdmin.frameSlideInsert(ui.item.data('template-slide'), newposition);
                } else {
                    pmakerAdmin.frameSlideChangePosition(startposition, newposition);
                }
            }
        });
        $("#sortable").disableSelection();
    },
    /**
     * Zmena poradovych cisel slidu ve slideline listu
     * @param {int} curposition soucasne pozice presunuteho slidu, nastaví se jako aktualni v iframu
     * @returns {undefined}
     */
    slideslineChangeNumbers: function(curposition) {
        if (curposition < 0) {
            curposition = 0;
        }
        $('.slides-line-list li').children('.slides-line-num').animate({'opacity': 0}, 2000, function() {
            $(this).text($(this).parent().index() + 1);
        }).animate({'opacity': 1}, 1000).promise().done(function() {
            pmakerAdmin.slideslineSortable(false);
            pmakerAdmin.slidesSave(curposition);
        });
    },
    /**
     * Prirazeni udalosti 
     * @returns {undefined}
     */
    slidesEvent: function() {
        $(document).on('click', '.slides-line-remove', function(e) {
            e.preventDefault();
            var step = $(this).parent().parent().index();
            if (confirm(TEXTS['del_slide'])) {
                pmakerAdmin.frameSlideRemove(step);
            }
        });
        $(document).on('click', '.slides-line-duplicate', function(e) {
            var step = $(this).parent().parent().index();
            pmakerAdmin.frameSlideDuplicate(step);
            e.preventDefault();
        });

        $(document).on('click', '#slides-save', function(e) {
            e.preventDefault();
            pmakerAdmin.slidesSave(pmakerAdmin.currentSlidePosition, true);
        });

        $(window).on('resize', function(e)
        {
            window.resizeEvt;
            $(window).resize(function()
            {
                clearTimeout(window.resizeEvt);
                window.resizeEvt = setTimeout(function()
                {
                    pmakerAdmin.destroyWysiwyg();
                    pmakerAdmin.loadWysiwyg();
                    //code to do after window is resized
                }, 250);
            });
        });

        $(document).on('mouseenter', '.slides-line-list li', function(e) {
            $(this).siblings().children('.slides-line-sbar').hide();
            $(this).parent().children('.active').children('.slides-line-sbar').css('display', 'block');
            $(this).children('.slides-line-sbar').css('display', 'block');
        });

        $(document).on('mouseleave', '.slides-line-list li', function(e) {
            if (!$(this).hasClass('active')) {
                $(this).children('.slides-line-sbar').hide();
            }

        });

        $(document).on('click', '.slides-line-list a.slides-line-num', function(e) {
            e.preventDefault();
            var step = $(this).parent().index();
            pmakerAdmin.frameSetSlideActive(step + 1);
           
            for (var i in CKEDITOR.instances) {
                $('#cke_' + CKEDITOR.instances[i].name).css('display', 'none');
            }

        });

        $(document).on('click', '#slides-settings-show', function(e) {
            e.preventDefault();
            pmakerAdmin.addLayoutSettings();
        });

        
         $(document).on('click', '#slides-preview', function(e) {
            e.preventDefault();
            if ($(this).hasClass('active')) {
                pmakerAdmin.loadWysiwyg();
                $(this).removeClass('active');
            } else {
                pmakerAdmin.destroyWysiwyg();
                $(this).addClass('active');
            }
        });
        
        //tlacitko select editable
        $(document).on('click', '#slides-select-editable', function(e) {
            e.preventDefault();
             
            pmakerAdmin.editTemplate.unload();
            if ($(this).hasClass('active')) {
                $(this).siblings('a').removeClass('active');
                return;
            }
            
            if ($(this).hasClass('active')) {
                $(this).removeClass('active');
            } else {
                pmakerAdmin.editTemplate.load();
                pmakerAdmin.editTemplate.loadSelectEditableEvents();
                $(this).siblings('a').removeClass('active');
                $(this).addClass('active');
            }
        });
        
        //tlacitko editace sablony
          $(document).on('click', '#slides-edit-template', function(e) {
            e.preventDefault();
            
            pmakerAdmin.editTemplate.unload();
             if ($(this).hasClass('active')) {
                
                $(this).siblings('a').removeClass('active');
                return;
            }
            
            if ($(this).hasClass('active')) {
                $(this).removeClass('active');
            } else {
               pmakerAdmin.editTemplate.load();
               pmakerAdmin.editTemplate.loadEditAllEvents();
               $(this).siblings('a').removeClass('active');
               $(this).addClass('active');
            }
        });

    },
    /**
     * Označí aktivní slide na pásu
     * @param {type} step
     * @returns {undefined}
     */
    slidesLineMarkActive: function(step) {
        pmakerAdmin.currentSlidePosition = step;
        var slidesline = $('.slides-line-list').children('li');
        slidesline.siblings().removeClass('active');
        //skryjeme toolbar slidu
        slidesline.siblings().children('.slides-line-sbar').hide();
        slidesline.eq(step).addClass('active');
        //zobrazime toolbar
        slidesline.eq(step).children('.slides-line-sbar').css('display', 'block');
    },
    /**
     * Odstrani slide
     * @param {int} step poradove cislo slidu
     * @returns {undefined}
     */
    frameSlideRemove: function(step) {
        $('.slides-line-list li:eq(' + step + ')').fadeOut(1500, function() {
            $(this).remove();
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(step).remove();
            pmakerAdmin.slidesSave(step);
        });
    },
    /**
     * Zkopiruje slide
     * @param {int} step poradove cislo slidu
     * @returns {undefined}
     */
    frameSlideDuplicate: function(step) {
        pmakerAdmin.destroyWysiwyg();
        pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(step).clone().insertAfter(pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(step));
        pmakerAdmin.checkFormNames(null, step + 1);
        pmakerAdmin.slidesSave(step + 1);
    },
    /**
     * Vlozi slide z template
     * @param {int} source poradove cislo slidu sablon
     * @param {int} target poradove cislo mista umisteni
     * @returns {undefined}
     */
    frameSlideInsert: function(source, target) {
        if (target === 0) {
            pmakerAdmin.template_frame.$('[data-phc-slides="1"]').children().eq(source).clone().insertBefore(pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(target));
        } else {
            pmakerAdmin.template_frame.$('[data-phc-slides="1"]').children().eq(source).clone().insertAfter(pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(target - 1));
        }
        pmakerAdmin.checkFormNames(null, target);
        pmakerAdmin.slidesSave(target);
    },
    /**
     * Nastavi aktualni snimek na slide line
     * @param {int} step poradove cislo slidu
     * @returns {undefined}
     */
    slideslineSetActive: function(step) {
        $('.slides-line-list li:eq(' + step + ')').children('.slides-line-num').trigger('click');
    },
    /**
     * Nastavi aktualni snimek v prezentaci
     * @param {int} step poradove cislo slidu
     * @returns {undefined}
     */
    frameSetSlideActive: function(step) {
        if (typeof (pmakerAdmin.frame.PhcEditablePressentation) !== "undefined") {
            if (typeof (pmakerAdmin.frame.PhcEditablePressentation.pmakerGoToSlide) !== "undefined") {
                pmakerAdmin.frame.PhcEditablePressentation.pmakerGoToSlide(step);
            }
        }
    },
    /**
     * Odstrani strankovani prezentace, prezentace si jej muze dynamicky nacitat
     * @returns {undefined}
     */
    frameRemoveStep: function() {
        if (typeof (pmakerAdmin.frame.PhcEditablePressentation) !== "undefined") {
            if (typeof (pmakerAdmin.frame.PhcEditablePressentation.pmakerRemoveStep) !== "undefined") {
                pmakerAdmin.frame.PhcEditablePressentation.pmakerRemoveStep();
            }
        }
    },
    /**
     * Ulozi prezentaci
     * @param {int} step poradove cislo snimku, ktery se ulozeni a reloadu nastavi jako aktualni
     * @param {boolean} infomsg zobrazi informacni hlasku po ulozeni
     * @returns {undefined}
     */
    slidesSave: function(step, infomsg) {
        if ($('#pmaker-template').length !== 0) {
            //odebereme nastaveni layoutu pokud needitujeme primo sablonu
            pmakerAdmin.frame.$('[data-phc-settings]').remove();
            $('#slides-settings-show').removeClass('active');
        }
        pmakerAdmin.frameRemoveStep();
        pmakerAdmin.destroyWysiwyg();

        pmakerAdmin.frame.$('[data-phc-slides]').removeAttr('style');

        var tmp = $('#presentation').attr('src').split('?');
        var save_file = tmp[0];

        $.post($('#slides-save').attr('href'), {data: $('#presentation').contents().find("html").html(), file: save_file}, function(data) {
            data = $.parseJSON(data);
            //jeli error vyhodime alert nebo infomsg paklize ji chceme
            if (data.error) {
                alert(data.error);
            } else if (infomsg) {
                alert(data.result);
            }

            //zmenil-li se zdrojovy file prezentace nacteme ho
            if (data.file_reload) {
                $('#presentation').attr('src', data.file_reload);
            } else {
                pmakerAdmin.frame.location.reload(true);
            }


            $('#presentation').one('load', function() {
                pmakerAdmin.slideslineLoad(step);
                pmakerAdmin.loadWysiwyg();
            });
        }
        ).fail(function(xhr) {
            alert(xhr.statusText);
        });
    },
    /**
     * Přidá wysiwig editor k editovatelným položkám prezentace
     * @returns {undefined}
     */
    loadWysiwyg: function() {
        
        //pri editaci sablony wysiwig nenacitame
        if (this.modeEditTemplate) {
            return;
        }

        $('#slides-preview').removeClass('active');

        pmakerAdmin.editableHover();
        var ipos = $('#presentation').offset();

        pmakerAdmin.frame.$('[for]').each(function() {
            $(this).attr('data-cke-form-for', $(this).attr('for'));
            $(this).removeAttr('for');
        });

        pmakerAdmin.frame.$('[data-phc-editable="1"]').each(function(i) {
            $(this).attr('contenteditable', true);

            var config = {
                floatSpaceDockedOffsetX: ipos.left,
                floatSpaceDockedOffsetY: -ipos.top,
                //ToDo sjednotit s pluginy v configu ckeditoru
                extraPlugins: 'inboxlink,floatingspace,contextmenu,tabletools,liststyle,inboxform,nid_forms,mediaembed,youtube',
                removePlugins: 'magicline',
                disableObjectResizing: true,
                disableAutoInline: true,
                on: {
                    instanceReady: function(e) {
                        //hack, úprava alt a title u fakeobjectu formu
                        pmakerAdmin.frame.$('[data-cke-realelement]').each(function() {
                            $(this).attr('title', 'Form element placeholder');
                            $(this).attr('alt', 'Form element placeholder');
                        });
                    },
                    focus: function(e) {
                        e.editor.element.setAttribute('data-phc-ckeditor-id', e.editor.name);
                        CKEDITOR_CURRENT = e.editor.name;
                    },
                    blur: function(e) {
                        e.editor.element.removeAttribute('data-phc-ckeditor-id');
                    }
                }
            };
            var toolbar = $('#presentation').data('wysiwyg-toolbar');
            //paklize ziskame z configu toolbar full, vynulujeme config toolbaru, v tomto pripade ckeditor zobrazi vsechny hodnoty
            if (toolbar === "full") {
                config.toolbar = null;
            }
            CKEDITOR.inline(pmakerAdmin.frame.$(this).get(0), config);
        });


    },
    /**
     * Destroyí wysiwyg
     * @returns {undefined}
     */
    destroyWysiwyg: function() {
        
        //pri editaci sablony destrojíme jinak
        if (this.modeEditTemplate) {
            return this.editTemplate.unload();
        }
        pmakerAdmin.frame.$('[data-phc-editable="1"]').blur();
        pmakerAdmin.frame.$('[data-phc-editable="1"]').removeAttr('contenteditable');
        
        //odstranime hover editovatelnych boxu
         pmakerAdmin.frame.$('[data-phc-editable="1"]').unbind('mouseenter mouseleave');

        pmakerAdmin.frame.$('[data-cke-form-for]').each(function() {
            $(this).attr('for', $(this).attr('data-cke-form-for'));
            $(this).removeAttr('data-cke-form-for');
        });

        for (var i in CKEDITOR.instances) {
            CKEDITOR.instances[i].destroy();
        }
    },
    /**
     * Aktivuje hover na editovatelne polozky prezentace
     * @returns {undefined}
     */
    editableHover: function() {
        pmakerAdmin.frame.$('[data-phc-editable="1"]').hover(
                function() {
                    $(this).css({
                        "-webkit-box-shadow": "inset 0 0 8px rgba(82, 168, 236, 0.6)",
                        "-moz-box-shadow": "inset 0 0 8px rgba(82, 168, 236, 0.6)",
                        "box-shadow": "inset 0 0 8px rgba(82, 168, 236, 0.6)"});
                },
                function() {
                    $(this).css({
                        "-webkit-box-shadow": "none",
                        "-moz-box-shadow": "none",
                        "box-shadow": "none"});
                });
    },
    /**
     * Získá formulářové prvky ze šablony
     * @returns {Array}
     */
    getFormElements: function() {

        var forms = [];

        pmakerAdmin.template_frame.$('[data-phc-form]').each(function() {
            var el = $(this).clone();
            el.attr('data-phc-form-element', 1);
            el.removeAttr('data-phc-form');
            forms[$(this).data('phc-form')] = el[0].outerHTML;
        });

        return forms;
    },
    /**
     * Uklízí formulářové na slidu, nastaví name s poradim, unikatni pro skupinu
     * @param {type} editor instance ckeditoru
     * @param {int} slide_id ID slidu
     * @returns {undefined}
     */
    checkFormNames: function(editor, slide_id) {
        var d = new Date();

        Number.prototype.leftZeroPad = function(numZeros) {
            var n = Math.abs(this);
            var zeros = Math.max(0, numZeros - Math.floor(n).toString().length);
            var zeroString = Math.pow(10, zeros).toString().substr(1);
            if (this < 0) {
                zeroString = '-' + zeroString;
            }

            return zeroString + n;
        };

        var slide;
        if (typeof slide_id !== 'undefined') {
            slide = pmakerAdmin.frame.$('[data-phc-slides="1"]').children().eq(slide_id);
        } else {
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children().each(function() {
                if ($(this).find('[data-phc-ckeditor-id="' + editor.name + '"]').length !== 0) {
                    slide = $(this);
                }
            });
        }

        var names = new Array();

        slide.find('[data-cke-realelement], :input').each(function() {
            var fake = $(this).data('cke-realelement');
            if (typeof fake !== "undefined") {
                names[editor.restoreRealElement($(this)).getAttribute('name')] = editor.restoreRealElement($(this)).getAttribute('name');
            } else {
                names[$(this).attr('name')] = $(this).attr('name');
            }
        });
        var x = 0;
        for (n in names) {
            x++;
            var v = 1;
            var tstamp = d.getTime().toString();
            slide.find('[data-cke-realelement], :input').each(function(y) {
                var cname;
                var r_el;
                if ($(this).is(':input')) {
                    cname = $(this).attr('name');
                } else if ($(this).is('[data-cke-realelement]')) {
                    r_el = editor.restoreRealElement($(this));
                    cname = r_el.getAttribute('name');
                }

                if (n == cname) {
                    var name = x.leftZeroPad(3) + '_form_' + tstamp;
                    if (typeof r_el !== "undefined") {
                        r_el.setAttribute('data-cke-saved-name', name);
                        r_el.setAttribute('id', name + '_' + (y + 1));
                        $(this).attr('data-cke-realelement', editor.createFakeElement(r_el).getAttribute('data-cke-realelement'));
                        $(this).parent().children('label').attr('for', name + '_' + (y + 1));

                    } else {
                        $(this).attr('data-cke-saved-name', name);
                        $(this).attr('id', name + '_' + (y + 1));
                        $(this).parent().children('label').removeAttr('for');
                        $(this).parent().children('label').attr('data-cke-form-for', name + '_' + (y + 1));
                    }
                    $(this).attr('title', 'POKUS');

                }
            });
        }

    },
    /**
     * Umístí do prezentace setting panel
     * @returns {undefined}
     */
    addLayoutSettings: function() {

        if (pmakerAdmin.frame.$('[data-phc-settings]').is(':visible')) {
            pmakerAdmin.frame.$('[data-phc-settings]').hide('slow', function() {
                if ($('#pmaker-template').length !== 0) {
                    pmakerAdmin.frame.$('[data-phc-settings]').remove();
                }
                $('#slides-settings-show').removeClass('active');
            });

            return;
        }

        //editujeme-li sablonu nacitame settings jinak
        if ($('#pmaker-template').length !== 0) {
            var settings_control = pmakerAdmin.template_frame.$('[data-phc-settings]').clone();
        } else {
            var settings_control = pmakerAdmin.frame.$('[data-phc-settings]').clone();
        }

        $('#slides-settings-show').addClass('active');

        settings_control.css({
            position: 'absolute', 'z-index': 101, 'background-color': 'rgba(0,0,0,0.8)',
            bottom: 0, padding: "10px 20px"
        });
        pmakerAdmin.frame.$('#page').prepend(settings_control);

        pmakerAdmin.frame.$('.settings_close').on('click', function(e) {
            pmakerAdmin.addLayoutSettings();
            e.preventDefault();
        });

        pmakerAdmin.frame.PhcEditablePressentation.loadActiveSettings();
        pmakerAdmin.frame.$('[data-phc-settings]').show("slow");

    },
    /**
     * Slider pro priblizovani framu prezentace
     * @returns {undefined}
     */
    zoomer: function() {
        $('#pslider').slider({
            min: 1,
            max: 100,
            value: pmakerAdmin.scale * 100,
            change: function(event, ui) {
                var v = ui.value / 100;
                pmakerAdmin.scale = v;
                pmakerAdmin.frameHeight(true);
                pmakerAdmin.destroyWysiwyg();
                pmakerAdmin.loadWysiwyg();
            }
        }
        );
    },
    /**
     * Default ckeditor, přepisuje styles.js 
     * @type Array
     */
    stylesSet: [
        {name: 'Italic Title', element: 'h2', styles: {'font-style': 'italic'}},
        {name: 'Subtitle', element: 'h3', styles: {'color': '#aaa', 'font-style': 'italic'}},
        {
            name: 'Special Container',
            element: 'div',
            styles: {
                padding: '5px 10px',
                background: '#eee',
                border: '1px solid #ccc'
            }
        },
        {name: 'Big', element: 'big'},
        {name: 'Small', element: 'small'},
        {name: 'Typewriter', element: 'tt'},
        {name: 'Computer Code', element: 'code'},
        {name: 'Keyboard Phrase', element: 'kbd'},
        {name: 'Sample Text', element: 'samp'},
        {name: 'Variable', element: 'var'},
        {name: 'Deleted Text', element: 'del'},
        {name: 'Cited Work', element: 'cite'},
        {name: 'Inline Quotation', element: 'q'},
        {name: 'Language: RTL', element: 'span', attributes: {'dir': 'rtl'}},
        {name: 'Language: LTR', element: 'span', attributes: {'dir': 'ltr'}},
        {
            name: 'Styled image (left)',
            element: 'img',
            attributes: {'class': 'left'}
        },
        {
            name: 'Styled image (right)',
            element: 'img',
            attributes: {'class': 'right'}
        },
        {
            name: 'Compact table',
            element: 'table',
            attributes: {
                cellpadding: '5',
                cellspacing: '0',
                border: '1',
                bordercolor: '#ccc'
            },
            styles: {
                'border-collapse': 'collapse'
            }
        },
        {name: 'Borderless Table', element: 'table', styles: {'border-style': 'hidden', 'background-color': '#E6E6FA'}}
    ],
    getStylesSet: function() {
        //nacteme styly ze sablony
        if (pmakerAdmin.template_frame) {
            pmakerAdmin.template_frame.$('[data-phc-styleset]').each(function() {
                pmakerAdmin.stylesSet.push({name: $(this).data('phc-styleset'), element: $(this).prop('tagName').toLowerCase(), attributes:{class:$(this).attr('class')}});
            });
        }
        
        return pmakerAdmin.stylesSet;
    },
    /**
     * Editace šablony
     */
    editTemplate: {
        /**
         * Seznam editovatelných elementů, dle výchozí konfigurace Ckeditoru
         * @type @exp;CKEDITOR@pro;dtd@pro;$editable
         */
        editable: CKEDITOR.dtd.$editable,
        /**
         * Mode edit - výběr editovatelných bloků (elementů)
         * @type Boolean|Boolean
         */
        modeSelectEditable: false,
        /**
         * Barva označení editovatelných bloků
         * @type String
         */
        color_editable:"#E8CDF5",
        /**
         * Barva hoveru
         * @type String
         */
        color_select_hover: "#DDF4FB",
        /**
         * Aktivní/editovaný element 
         * @type object
         */
        active_element: null,
        load: function() {
            this.loadHoverEvents();
        },
        unload: function() {
            $('.slides-control').find('a').removeClass('active');
            this.destroyWysiwyg();
            this.unmarkEditableELements();
            this.unloadEvents();
        },
        loadHoverEvents: function() {
            var self = this;
            pmakerAdmin.frame.$('body').css('cursor', 'pointer');

            pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').on('mouseover', function (e) {
                var el = pmakerAdmin.editTemplate.getSelectableElements(pmakerAdmin.frame.$(e.target));
                if(el === null) {
                    return;
                }
                if((el.parents('[contenteditable = "true"]').length)||(el.attr('contenteditable') !== undefined)) {
                    return;
                }

                //odlozime puvodni styl, aby nedoslo ke konfliktu background-color
                if (el.attr('data-temp-style') === undefined) {
                    self.backupStyle(el);
                }
                el.css('background-color',pmakerAdmin.editTemplate.color_select_hover);
            });
            
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').on('mouseout', function (e) {
                var el = pmakerAdmin.editTemplate.getSelectableElements(pmakerAdmin.frame.$(e.target));
                if(el === null) {
                    return;
                }
                 if (el.attr('contenteditable') !== undefined) {
                    return;
                }
                if (self.modeSelectEditable && el.attr('data-phc-editable') == 1) {
                     el.css('background-color',pmakerAdmin.editTemplate.color_editable); 
                     return;
                }
                self.renewStyle(el);
            });
        },
        destroyWysiwyg: function () {
            for (var i in CKEDITOR.instances) {
                pmakerAdmin.frame.$('[data-cke-form-for]').each(function () {
                    $(this).attr('for', $(this).attr('data-cke-form-for'));
                    $(this).removeAttr('data-cke-form-for');
                });
                CKEDITOR.instances[i].element.removeAttribute('data-phc-ckeditor-id');
                CKEDITOR.instances[i].element.removeAttribute('contenteditable');
                CKEDITOR.instances[i].destroy();
            }
        },
        loadEditAllEvents: function() {
            pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').on('click', function (e) {

                var el = pmakerAdmin.editTemplate.getSelectableElements(pmakerAdmin.frame.$(e.target));
                if (el === null) {
                    return;
                }
                
                if((el.parents('[contenteditable = "true"]').length)||(el.attr('contenteditable') !== undefined)) {
                    return;
                }
                
                pmakerAdmin.editTemplate.destroyWysiwyg();
                
                var ipos = $('#presentation').offset();
                pmakerAdmin.editTemplate.renewStyle(el);
                
                el.attr('contenteditable', true);
                
                var config = {
                startupFocus : true,
                floatSpaceDockedOffsetX: ipos.left,
                floatSpaceDockedOffsetY: -ipos.top,
                //ToDo sjednotit s pluginy v configu ckeditoru
                extraPlugins: 'floatingspace,contextmenu,tabletools,liststyle,nid_forms,mediaembed,youtube',
                removePlugins: 'magicline',
                disableObjectResizing: true,
                on: {
                    instanceReady: function(e) {
                        //hack, úprava alt a title u fakeobjectu formu
                        pmakerAdmin.frame.$('[data-cke-realelement]').each(function() {
                            $(this).attr('title', 'Form element placeholder');
                            $(this).attr('alt', 'Form element placeholder');
                        });
                    },
                    focus: function(e) {
                        e.editor.element.setAttribute('data-phc-ckeditor-id', e.editor.name);
                        CKEDITOR_CURRENT = e.editor.name;
                    },
                    blur: function(e) {
                        pmakerAdmin.editTemplate.destroyWysiwyg();
                    }
                }
            };
            
            config.toolbar = null;

            CKEDITOR.inline(el.get(0), config);


            });  
        },
        loadSelectEditableEvents: function () {
            var self = this;
            self.modeSelectEditable = true;
            this.markEditableELements();

            pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').on('click', function (e) {

                var el = pmakerAdmin.editTemplate.getSelectableElements(pmakerAdmin.frame.$(e.target));
                if (el === null) {
                    return;
                }

                self.active_element = el; 
                
                if(e.originalEvent !== undefined) {
                    self.dialogEdiatableElement(e.pageX,e.pageY,el.parent());
                }
               
                if (el.attr('data-phc-editable') === undefined) {
                    el.parents('[data-phc-editable]').each(function () {
                        $(this).removeAttr('data-phc-editable');
                        $(this).css('background-color', '');
                        self.renewStyle(el);
                    });
                    el.find('[data-phc-editable]').each(function () {
                        $(this).removeAttr('data-phc-editable');
                        $(this).css('background-color', '');
                        self.renewStyle(el);
                    });
                    el.attr('data-phc-editable', 1);
                    el.css('background-color', pmakerAdmin.editTemplate.color_editable);
                } else {
                    el.removeAttr('data-phc-editable');
                    self.renewStyle(el);
                }
            });
            pmakerAdmin.frame.$('body').on('mouseleave','#dialog-select-editable',function(){
                $(this).remove();
            });
            
             pmakerAdmin.frame.$('body').on('click','#dialog-select-editable>span',function(){
                self.active_element.trigger('click');
            });
            
            pmakerAdmin.frame.$('body').on('mouseenter','#dialog-select-editable>a',function(){
               var i = $(this).index();
               var el = self.getSelectableElements(self.active_element.parent(),i);
               $(this).css('background-color','#e6e6e6');
               if (el) {
                 el.trigger('mouseover'); 
               }
            });
            
            pmakerAdmin.frame.$('body').on('mouseleave','#dialog-select-editable>a',function(){
               var i = $(this).index();
               var el = self.getSelectableElements(self.active_element.parent(),i);
               $(this).css('background-color','#f5f5f5');
               if (el) {
                  el.trigger('mouseout'); 
               }
            });
            
            pmakerAdmin.frame.$('body').on('click','#dialog-select-editable>a',function(){
               var i = $(this).index();
               var el = self.getSelectableElements(self.active_element.parent(),i);
               if (el) {
                el.trigger('click');
               }
               $(this).parent().remove();
            });
        },
        unloadEvents: function () {
           pmakerAdmin.frame.$('body').css('cursor', 'auto');
           this.unloadHoverEvents();
           pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').off('click');
        },
        unloadHoverEvents: function() {
           pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').off('mouseover');
           pmakerAdmin.frame.$('[data-phc-slides="1"]').children('li').off('mouseout');  
        },
       /**
        * Otestuje a existuje-li ziska editovatelný element dle konfigurace CKeditoru, pripadne prohleda parenty
        * @param {object} element
        * @param {int} vratí n-tý element
        * @returns {object} editovatelný element | null
        */
       getSelectableElements: function(el,n) {
           if (n === undefined) {
               n = 1;
           }
           var i = 1;
           while (!el.data('phc-slides')) {
                if ((this.editable[el.prop('tagName').toLowerCase()] !== undefined) && (n == i)) {
                   return el; 
                }
                el = el.parent();
                i++;
           }
           return null;
       },
       markEditableELements: function() {
           pmakerAdmin.frame.$('[data-phc-editable = "1"]').css('background-color',function(){
             $(this).attr('data-temp-style',$(this).attr('style')||'');
             return pmakerAdmin.editTemplate.color_editable;  
           });
       },
       unmarkEditableELements: function() {
           pmakerAdmin.frame.$('[data-phc-editable = "1"]').css('background-color','');
           pmakerAdmin.frame.$('[data-phc-slides="1"]').find('[data-temp-style]').each(function() {
               pmakerAdmin.editTemplate.renewStyle($(this));
               if ($(this).attr('style') === "") {
                  $(this).removeAttr('style');
               }
               $(this).removeAttr('data-temp-style');
           });
           this.modeSelectEditable = false;
       },
       renewStyle: function(el) {
           el.attr('style',el.attr('data-temp-style')||'');
       },
       backupStyle: function(el) {
           el.attr('data-temp-style',el.attr('style')||'');
       },
       dialogEdiatableElement: function (posX, posY, el) {
            el = this.getSelectableElements(el);
            if (!el) {
                return;
            }
            var c = '<span style="display:block;float:left;width:10px;">&nbsp;</span>';
            var x = posX-9;
            var y = posY - 14;
            while (el !== null) {
                el.prop('tagName');
                c += '<a style="border-left:#9b9b9b 1px solid;border-top:#9b9b9b 1px solid;border-bottom:#9b9b9b 1px solid;background-color:#f5f5f5;display:block;float:left;padding:6px 14px;text-decoration:none;color:#363636">'+el.prop('tagName')+'</a>';
                el = this.getSelectableElements(el.parent());
            }

            dialog = $('<div id="dialog-select-editable"></div>');
            dialog.html(c);
            dialog.offset({left: x, top: y});
            dialog.css({position: 'absolute','border-right':'#9b9b9b 1px solid'});
            pmakerAdmin.frame.$('body').append(dialog);
         
        }
    }
};

addLoadEvent('pmakerAdmin.forOnload');		