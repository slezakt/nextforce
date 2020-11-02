//export tabulek pomocí xlsx.js (pozor je použita upravena verze z https://github.com/tbenbrahim/xlsx.js,
//která umí background color, uprail jsem funkci autoWidth ve které byla chyba
//k tabulkám které mají data atribut data-table-export="nazev" je doplněno tlačítko pro export do tfoot
//je vyžadována struktura thead-tbody
//<td> označené class="no-xlsx" budou vynechány
//colspan a rowspan se v thead používají dle html atributu, v tbody podle posledniho řádku v thead
var xexportAdmin = {
    forOnload: function() {
        if ($('[data-table-export]').length === 0) {
            return;
        }
        $('[data-table-export]').each(function() {
            var table = $(this).data('table-export');
            var cols = ($(this).find('tr:last').children('td').length);
            if (cols > 0) {
            $(this).append('<tfoot><tr><td colspan="' + cols + '" style="padding-left:20px;"><a class="xlsx-export btn btn-mini btn-info" id="' + table + '" \n\
                            href="#"><span class="glyphicon glyphicon-save"></span>&nbsp;'+TEXT_EXPORT_XLSX+'</a></tr></td></tfoot>');
            }
         });

        $(document).on('click', '.xlsx-export', function(e) {

            var id =  $(this).attr('id');
            var file = {
                worksheets: [[]],
                activeWorksheet: 0
            }, w = file.worksheets[0];

            w.data = [];
            //export thead
            $('[data-table-export="' + id + '"]').find('thead').find('tr').each(function(x) {
                w.data.push([]);
                $(this).find('th').each(function() {
                    if (!$(this).hasClass('no-xlsx')) {
                        var hdata = [];
                        hdata['value'] = $(this).text();
                        hdata['bold'] = 1;
                        hdata['autoWidth'] = true;
                        if(typeof $(this).attr('colspan') !== "undefined") {
                            hdata['colSpan'] = Number($(this).attr('colspan'));
                        }
                        if(typeof $(this).attr('rowspan') !== "undefined") {
                            hdata['rowSpan'] = Number($(this).attr('rowspan'));
                        }
                        w.data[x].push(hdata);
                    }
                });
            });
            var l = w.data.length;
            //export thead
            $('[data-table-export="' + id + '"]').find('tbody').find('tr').each(function(x) {
                w.data.push([]);
                var y = 0;
                $(this).find('td').each(function() {
                    //jestli je v hlavičce no
                    if (!$('[data-table-export="' + id + '"]').children('thead').children('tr:last').children('th:eq('+y+')').hasClass('no-xlsx')) {
                        var cdata = [];
                        //chybí-li hlavicka pridame podle prvniho radku autowidth
                        if (l === 0 && x === 0) {
                            cdata['autoWidth'] = true;
                        }

                        if (!$(this).hasClass('no-xlsx')) {
                        cdata['value'] = $(this).text().trim();
                        }

                        w.data[x + l].push(cdata);
                    }

                    //pri colspan posuneme poradi sloupce
                    var cspan = $('[data-table-export="' + id + '"]').children('thead').children('tr:last').children('th:eq('+y+')').attr('colspan');
                    if (cspan !== "undefined") {
                      y = y + cspan;
                    } else {
                      y++;
                    }
                });
            });

            var xlsxData = xlsx(file).base64;
            //window.location = xlsx(file).href();

             // Sestavime data pro Loopback controller
             var xlsxRequest = {data:xlsxData, base64:true, headers: {
                    "Content-Disposition":
                        "attachment; filename=" + id+"" + new Date().getTime() +".xlsx",
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
};

jQuery(function($) { $.extend({
    form: function(url, data, method) {
        if (method == null) method = 'POST';
        if (data == null) data = {};

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
}); });
addLoadEvent('xexportAdmin.forOnload');


