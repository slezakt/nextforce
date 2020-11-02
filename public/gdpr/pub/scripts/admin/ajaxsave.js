var ajaxsaveAdmin = {
    forOnload: function() {
        if (($('form').has('[data-ace-submit="ajax"]').length !== 0)) {
            $('form').on('submit', function(e) {
                e.preventDefault();
                var submit = $('[type="submit"]');
                var w = submit.width();
                submit.width(w);
                var l = submit.val();
                var target = $(this).attr('action');
                var formdata = $(this).serialize();
                //posilame i name submitu, vyzaduje ho CRUD
                var subname = submit.attr('name');
                $.ajax({
                    type: "POST",
                    url: target,
                    data: formdata + "&" + subname + "=1",
                    beforeSend: function() {
                        submit.val("");
                        submit.after('<img class="ajax-loader" style="position:relative;left:-' + (w + 6) + 'px;" src="pub/img/admin/ajax-load.gif" />');
                    },
                    success: function() {

                    },
                    error: function(jqXHR) {
                        alert(jqXHR.statusText);
                    },
                    complete: function() {
                        submit.val(l);
                        $('.ajax-loader').remove();
                    }

                });
            });
        }
    }
};
addLoadEvent('ajaxsaveAdmin.forOnload');


