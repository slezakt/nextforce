//js sprava udalosti kalendare
var calendarAdmin = {
    forOnload: function () {

        //datepicker pro filtr
        $(".datepicker").datepicker({dateFormat: "yy-mm-dd"});

        $('.eventtable tfoot th.text-search').each(function () {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="' + title + '" />');
        });

        //tabulka ulozenych udalosti
        var eventTable = $('#events').DataTable({
            ajax: $('#events').data('url'),
            language: {url: 'pub/scripts/datatables/' + language + '.lang'},
            pageLength: 10,
            dom: 'tip',
            autoWidth: false,
            order: [[0, 'desc']],
            columnDefs: [
                {targets: 0, width: '50px'},
                {targets: 2, width: '110px'},
                {targets: 3, width: '110px'},
                {targets: 4, width: '200px'},
                {targets: 5, width: '100px'},
                {targets: 6, width: '50px', orderable: false}
            ],
            initComplete: function () {
                calendarAdmin.loadTableFilter(eventTable);

                eventTable.columns([0, 1]).every(function (index) {
                    calendarAdmin.applyTextSearch(index, this);
                });
            }
        });

        //tabulka dostupnych udalosti
        var table = $('#availableEvents').DataTable({
            ajax: $('#availableEvents').data('url'),
            language: {url: 'pub/scripts/datatables/' + language + '.lang'},
            pageLength: 10,
            dom: 'tip',
            autoWidth: false,
            order: [[0, 'desc']],
            columnDefs: [
                {targets: 0, width: '50px'},
                {targets: 2, width: '100px'},
                {targets: 3, width: '100px'},
                {targets: 4, width: '200px'},
                {targets: 5, width: '100px'},
                {targets: 6, width: '50px', orderable: false}
            ],
            initComplete: function () {
                calendarAdmin.loadTableFilter(table);
                //text search
                table.columns([0, 1]).every(function (index) {
                    calendarAdmin.applyTextSearch(index, this);
                });
            }
        });


        //nacteni dat dostupnych udalosti do modalu
        $(document).on('click', '.availableEventDetail', (function (e) {
            e.preventDefault();
            var data = $(this).parents('table').DataTable().ajax.json();
            var id = $(this).data('id');
            var event = data.raw[id];
            $('#eventDetailLabel').text(event['title']);
            $('#eventDetailInfo .desc').children('div').html(event['description']);
            $('#eventDetailInfo .date').children('span').html(event['helper']['formattedDateBegin'] + ' - ' + event['helper']['formattedDateEnd']);
            $('#eventDetailInfo .place').children('span').html(event['place']);
            $('#eventDetailInfo .address').children('span').html(event['address']);
            $('#eventDetailInfo .url_action').children('span').html(event['url_action'] ? '<a target="_blank" href="' + event['url_action'] + '">' + event['url_action'] + '</a>' : '');
            $('#eventDetailInfo .img').attr('src', event['image_abspath']);
            $('#eventDetailInfo .specs').children('span').html(event['helper']['specs_str']);
            $('#eventDetailInfo .tags').children('span').html(event['helper']['tags_str']);
            $('#eventDetail').modal('show');

        }));


        //nacteni dat ulozenych udalosti do modalu
        $(document).on('click', '.eventDetail', (function (e) {
            e.preventDefault();
            var data = $(this).parents('table').DataTable().ajax.json();
            var id = $(this).data('id');
            var event = data.raw[id];
            $('#eventDetailLabel').text(event['title']);
            $('#eventDetailInfo .desc').children('div').html(event['description']);
            $('#eventDetailInfo .date').children('span').html(event['formattedDateBegin'] + ' - ' + event['formattedDateEnd']);
            $('#eventDetailInfo .place').children('span').html(event['place']);
            $('#eventDetailInfo .address').children('span').html(event['address']);
            $('#eventDetailInfo .url_action').children('span').html(event['url_action'] ? '<a target="_blank" href="' + event['url_action'] + '">' + event['url_action'] + '</a>' : '');
            $('#eventDetailInfo .img').attr('src', event['image']);
            $('#eventDetailInfo .specs').children('span').html(event['specializations']);
            $('#eventDetailInfo .tags').children('span').html(event['tags']);
            $('#eventDetail').modal('show');

        }));


        $(document).on('click', '.eventSave', function (e) {
            e.preventDefault();
            $('.infoMsg').html('');
            var data = table.ajax.json();
            var id = $(this).data('id');
            var event = data.raw[id];
            event['content_id'] = $('#availableEvents').data('contentid');
            table.row($(this).parents('tr')).remove().draw();
            $.post(null, event, function (data) {
                $('.infoMsg').html('<div class="alert alert-' + data.status + '"><button type="button" class="close" data-dismiss="alert">&times;</button>' + data.msg + '</div>');
                eventTable.ajax.reload(null, false);
            });
        });

        $(document).on('click', '.eventDelete', function (e) {
            e.preventDefault();
            $('.infoMsg').html('');
            if (confirm($(this).data('confirmtext')) === true) {
                var url = $(this).attr('href');
                eventTable.row($(this).parents('tr')).remove().draw();
                $.get(url, null, function (data) {
                    $('.infoMsg').html('<div class="alert alert-' + data.status + '"><button type="button" class="close" data-dismiss="alert">&times;</button>' + data.msg + '</div>');
                    eventTable.ajax.reload();
                });

            }


        });

    },
    applyTextSearch: function (index, datatable) {

        if (index === 0) {
            $('input', datatable.footer()).width(50);
        }

        $('input', datatable.footer()).on('keyup change', function () {
            if (datatable.search() !== this.value) {
                datatable.search(this.value).draw();
            }
        });
    },
    loadTableFilter: function (datatable) {

        var data = datatable.ajax.json();
        var specs = data.enum.specializations;
        var tags = data.enum.tags;

        //filtr specializace
        if (typeof specs !== "undefined") {

            var specOptions = "";

            $.each(specs, function (key, val) {
                specOptions += '<option value="' + val + '">' + val + '</option>';
            });

            if (specOptions) {
                var specsColumn = datatable.columns(4);
                $('<select style="width:200px"><option value=""></option>' + specOptions + '</select>')
                        .appendTo($(specsColumn.footer()).empty())
                        .on('change', function () {
                            specsColumn.search($(this).val() ? $(this).val() : '', true, false).draw();
                        });
            }

        }

        //filtr tagy
        if (typeof tags !== "undefined") {

            var tagOptions = "";

            $.each(tags, function (key, val) {
                tagOptions += '<option value="' + val + '">' + val + '</option>';
            });

            if (tagOptions) {
                var tagsColumn = datatable.columns(5);
                $('<select style="width:100px"><option value=""></option>' + tagOptions + '</select>')
                        .appendTo($(tagsColumn.footer()).empty())
                        .on('change', function () {
                            tagsColumn.search($(this).val() ? $(this).val() : '', true, false).draw();
                        });
            }

        }
    }

};

addLoadEvent('calendarAdmin.forOnload');
