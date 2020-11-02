// stazeni XLSX sablony pomocí xlsx.js
// musi existovat JS globalni promenna 'xlsx_template_columns'
// click target je trida 'xlsx-generate'

var usersAdmin = {
    forOnload: function() {

        $(document).on('click', '.xlsx-generate', function(e) {

            var id =  $(this).attr('id');
            var file = {
                worksheets: [[]],
                activeWorksheet: 0
            }, w = file.worksheets[0];

            w.data = [];
            //export thead

                w.data.push([]);
                $(xlsx_columns).each(function(k,v) {
                        var hdata = [];
                        hdata['value'] = v;
                        hdata['bold'] = 1;
                        hdata['autoWidth'] = false;
                        hdata['colSpan'] = 1;
                        hdata['rowSpan'] = 1;
                        w.data[0].push(hdata);
                });
                w.data.push([]);
                $(xlsx_example_user).each(function (k, v) {
                    var hdata = [];
                    hdata['value'] = v;
                    hdata['bold'] = 0;
                    hdata['autoWidth'] = false;
                    hdata['colSpan'] = 1;
                    hdata['rowSpan'] = 1;
                    w.data[1].push(hdata);
                });

            var xlsxData = xlsx(file).base64;

            // Sestavime data pro Loopback controller
            var xlsxRequest = {data:xlsxData, base64:true, headers: {
                "Content-Disposition":
                    "attachment; filename=users_import_template.xlsx",
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
addLoadEvent('usersAdmin.forOnload');
