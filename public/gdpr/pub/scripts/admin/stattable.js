//obsluha rozsirenych moznosti tabulek monitoringu
var stableAdmin = {
    forOnload: function() {
        if ($('[data-table-export]').length === 0 && $('[data-table-colopt]').length === 0) {
            return;
        }

        if ($('[data-table-export]').length !== 0) {
            stableAdmin.xexport.forOnload();
        }

        $('.table-stats').each(function() {
            var cols = ($(this).find('tr:last').children('td').length);
            if (cols) {
                var scol = "";
                if ($('[data-table-colopt]').length !== 0) {
                    scol += stableAdmin.col_optional.getColspicker($(this));
                }

                if ($('[data-table-export]').length !== 0) {
                    var php = "";
                    if (typeof $(this).data('table-export-php') !== "undefined") {
                        php = "-php";
                    }
                    scol += '<a class="xlsx-export'+php+' btn btn-mini btn-info" \n\
                            href="#"><span class="glyphicon glyphicon-save"></span>&nbsp;' + TEXT_EXPORT_XLSX + '</a>';
                }

                if (scol) {
                    $(this).append('<tfoot><tr><td colspan="' + cols + '" style="padding-left:20px;">' + scol + '</td></tr></tfoot>');
                }
            }
        });

        if ($('[data-table-colopt]').length !== 0) {
            this.col_optional.forOnload();
            $('.table-stats').each(function() {
                stableAdmin.col_optional.makeColsVisible($(this));
            });
        }

    },
    /**
     * Zobrazovani a skryvani sloupcu tabulky pomoci selectpickeru v zapati tabulky
     * Pro aktivovani je potreba do elementu tabulka doplnit data atribut data-table-colopt="1"
     * tabulka musi mit ID
     */
    col_optional: {
        forOnload: function() {
            $('.colspicker').selectpicker({
                container: 'body',
                width: '45px',
                header: TEXT_TABLE_COLOPT
            });
            $('.colspicker').css('margin-bottom', '0').css('margin-top', '1px');
            $('.colspicker').children('button').prepend('<span class="glyphicon glyphicon-cog"></span>');
            $('.colspicker .filter-option').css('display', 'none');

            $(document).on('change', 'select.colspicker', function() {
                stableAdmin.col_optional.showTableColumn($(this));
            });
        },
        getColspicker: function(table) {
            var opts = "";
            table.children('thead').children('tr:last').children('th').each(function(i) {
                if (!$(this).hasClass('no-data')) {
                    opts += '<option value="' + i + '">' + $(this).text() + '</option>';
                }
            });
            return '<select multiple data-style="btn-info btn-mini" class="colspicker">' + opts + '</select>&nbsp;&nbsp;';
        },
        makeColsVisible: function (table) {
            var colspicker = table.find('select.colspicker');
            var tablename = 'stattable-' + table.attr('id');
            colspicker.selectpicker('selectAll');

            var cookie = readCookie(tablename);
            if (cookie) {
                
                var hiddenCols = [];
                hiddenCols = cookie.split(",");

                var selectedCols = [];

                $('.colspicker option').each(function () {
                    if (hiddenCols.indexOf($(this).val()) < 0) {
                        selectedCols.push($(this).val());
                    }
                });
                colspicker.val(selectedCols);

            }

            colspicker.selectpicker('refresh');
            stableAdmin.col_optional.showTableColumn(colspicker);
        },
        showTableColumn: function(colspicker) {
            var table = colspicker.parents('table');

            var hiddenCols = [];
            
            colspicker.children().each(function() {
                var display = "none";
                
                if ($(this).is(':selected')) {
                    display = "table-cell";
                } else {
                    //skryte sloupce pridame do pole pro ulozeni do cookie
                    hiddenCols.push($(this).val());
                }
                
                var v = parseInt($(this).val());
                var chead = table.children('thead').children('tr:last').children('th:eq(' + v + ')');
                chead.css('display',display);
                var colIndex = 0;
                //zjistime poradi s ohledem na colspan
                chead.prevAll().each(function(){
                   if($(this).attr('colspan')) {
                    colIndex += parseInt($(this).attr('colspan'));
                   } else {
                     colIndex += 1;
                   }
                });

                var csp = 1;
                if (chead.attr('colspan')) {
                    csp = parseInt(chead.attr('colspan'));
                }
                table.children('tbody').children('tr').each(function() {
                    for (i=0;i<csp;i++) {
                       $(this).children('td:eq(' + (colIndex+i) + ')').css('display',display);
                    }
                });
            });
            
            if (typeof table.attr('id') !== undefined) {
                createCookie('stattable-'+table.attr('id'), hiddenCols, 365);
            }
        }

    },
    ////export tabulek pomocí xlsx.js (pozor je použita upravena verze z https://github.com/tbenbrahim/xlsx.js,
    //která umí background color, upravil jsem funkci autoWidth ve které byla chyba
    //k tabulkám které mají data atribut data-table-export="1" je doplněno tlačítko pro export do tfoot
    //tabulka musi mit ID
    //je vyžadována struktura thead-tbody
    //<td> označené class="no-data" budou vynechány
    //!!!pro atribut data-table-export-php=1 je tabulka exportována pomoci knihovny PHPExcel
    // - atribut (pro php export) data-table-export-force="1" tento parametr určuje, že sloupec přestože je skrytý je odeslán k exportu a po sestavení tabulky před uložení odebrán
    // - atribut data-table-format = "0% | m/d/yy h:mm | [h]:mm:ss" pouziva pro formatovani dat v excelu, ktere jsem implementovany procenta|čas|datum
    // pro format casu a datume, je treba vlozit do atributu data-table-export-value hodnotu v ms respektive v ISO-8601
    // - colspan a rowspan se v thead používají dle html atributu, v tbody podle posledniho řádku v thead
    // atribut data-table-export-colspan(rowspan) se pouzije, pokud se export neshoduje se zdrojovou tabulkou
    xexport: {
        forOnload: function() {
             $(document).on('click', '.xlsx-export-php', function(e) {
                 var table = $(this).parents('table').clone();
                 var slide_previews = null;
                 if (table.data('table-slide-previews-path')) {
                     slide_previews = table.data('table-slide-previews-path');
                 }
                 table.find('tfoot').remove();

                 //seznam column index ktere se po sestaveni xlsx odstrani např. pomocne sloupce
                 var afterremove = [];

                 //z tabulky pro export odebereme skryté položky
                 table.find('th').each(function(i){

                   if ($(this).css('display') === "none" && $(this).data('table-force-export') === undefined) {
                       $(this).remove();
                   };

                   if ($(this).css('display') === "none" && $(this).data('table-force-export') !== undefined) {
                       afterremove.push(i);
                   };
                 });
                 table.find('td').each(function(){
                   if ($(this).css('display') === "none" && $(this).data('table-force-export') === undefined) {
                       $(this).remove();
                   };
                 });
                 
                 //sestavime nazev souboru 
                var xfilename = "";
                var fdate = new Date();
                
                if (table.data('table-name')) {
                    xfilename = table.data('table-name');
                } else {
                    xfilename = table.attr('id');
                }
                
                xfilename += "_" + fdate.getFullYear() + ('00'+(fdate.getMonth()+ 1)).slice(-2) + fdate.getDate();

                 var form = $.form(PHP_EXPORT_XLSX_LINK,
                        {xtable: table.get(0).outerHTML,slide_previews:slide_previews,xname:xfilename,afterremove:afterremove}, method = 'POST');
                 form.submit();
                e.preventDefault();
             });

            $(document).on('click', '.xlsx-export', function(e) {

                var file = {
                    worksheets: [[]],
                    activeWorksheet: 0
                }, w = file.worksheets[0];

                var table = $(this).parents('table');
                w.data = [];
                //export thead
                table.children('thead').find('tr').each(function(x) {
                    w.data.push([]);
                    $(this).find('th:visible').each(function() {
                        if (!$(this).hasClass('no-data')) {
                            var hdata = [];
                            hdata['value'] = $(this).text();
                            hdata['bold'] = 1;
                            hdata['autoWidth'] = true;
                            if (typeof $(this).data('table-export-colspan') !== "undefined") {
                                hdata['colSpan'] = Number($(this).data('table-export-colspan'));
                            } else if (typeof $(this).attr('colspan') !== "undefined") {
                                hdata['colSpan'] = Number($(this).attr('colspan'));
                            }
                            if (typeof $(this).data('table-export-rowspan') !== "undefined") {
                                hdata['rowSpan'] = Number($(this).data('table-export-rowspan'));
                            } else if (typeof $(this).attr('rowspan') !== "undefined") {
                                hdata['rowSpan'] = Number($(this).attr('rowspan'));
                            }
                            w.data[x].push(hdata);
                        }
                    });
                });
                var l = w.data.length;
                //export thead
                table.children('tbody').find('tr').each(function(x) {
                    w.data.push([]);
                    var y = 0;
                    $(this).find('td:visible').each(function() {
                        //jestli je v hlavičce no
                        if (!table.children('thead').children('tr:last').children('th:eq(' + y + ')').hasClass('no-data')) {
                            var cdata = [];
                            //chybí-li hlavicka pridame podle prvniho radku autowidth
                            if (l === 0 && x === 0) {
                                cdata['autoWidth'] = true;
                            }
                            //formatovani dat na zakladae predanych formatu
                            if (!$(this).hasClass('no-data')) {
                                switch ($(this).data('table-export-format')) {
                                    case '0%':
                                        cdata['formatCode'] = $(this).data('table-export-format');
                                        var value = parseInt($(this).text());
                                        if (!isNaN(value)) {
                                            cdata['value'] = value/100;
                                        } else {
                                            cdata['value'] = $(this).text();
                                        }
                                        break;
                                    case 'm/d/yy h:mm':
                                        cdata['formatCode'] = $(this).data('table-export-format');
                                        cdata['value'] = new Date($(this).data('table-export-value'));
                                        break;
                                    case '[h]:mm:ss':
                                        cdata['formatCode'] = $(this).data('table-export-format');
                                        cdata['value'] = $(this).data('table-export-value')/86400000;
                                        break;
                                    
                                    default:
                                        cdata['value'] = $(this).text().trim();
                                }
                                
                                 w.data[x + l].push(cdata);
                            }

                           
                        }

                        //pri colspan posuneme poradi sloupce
                        var cspan = table.children('thead').children('tr:last').children('th:eq(' + y + ')').attr('colspan');
                        if (cspan !== "undefined") {
                            y = y + cspan;
                        } else {
                            y++;
                        }
                    });
                });

                var xlsxData = xlsx(file).base64;
                //window.location = xlsx(file).href();
                
                //sestavime nazev souboru 
                var xfilename = "";
                var fdate = new Date();
                
                if (table.data('table-name')) {
                    xfilename = table.data('table-name');
                } else {
                    xfilename = table.attr('id');
                }
                
                xfilename += "_" + fdate.getFullYear() + ('00'+(fdate.getMonth()+ 1)).slice(-2) + fdate.getDate();
                
                // Sestavime data pro Loopback controller
                var xlsxRequest = {data: xlsxData, base64: true, headers: {
                        "Content-Disposition":
                                "attachment; filename=" + xfilename + ".xlsx",
                        "Content-Type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    }
                };
                var xlsxRequestJson = JSON.stringify(xlsxRequest);

                var form = $.form(LOOPBACK_URL,
                        {json: xlsxRequestJson}, method = 'POST'
                        );
                form.submit();

                return false;
            });
        }
    }
};


jQuery(function($) {
    $.extend({
        form: function(url, data, method) {
            if (method == null)
                method = 'POST';
            if (data == null)
                data = {};

            var form = $('<form>').attr({
                method: method,
                action: url
            }).css({
                display: 'none'
            });

            var addData = function(name, data) {
                if ($.isArray(data)) {
                    for (var i = 0; i < data.length; i++) {
                        var value = data[i];
                        addData(name + '[]', value);
                    }
                } else if (typeof data === 'object') {
                    for (var key in data) {
                        if (data.hasOwnProperty(key)) {
                            addData(name + '[' + key + ']', data[key]);
                        }
                    }
                } else if (data != null) {
                    form.append($('<input>').attr({
                        type: 'hidden',
                        name: String(name),
                        value: String(data)
                    }));
                }
            };

            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    addData(key, data[key]);
                }
            }

            return form.appendTo('body');
        }
    });
});

addLoadEvent('stableAdmin.forOnload');



