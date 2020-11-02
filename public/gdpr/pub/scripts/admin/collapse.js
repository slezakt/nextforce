var collapseAdmin = {
    forOnload: function() {
        $(".accordion-body").on('shown', function() {

            var id = $(this).attr('id');            
            $("a[href='#" + id + "']").children().attr('class', 'icon-chevron-up');
            var colset = {};
            var c = readCookie('collapse');
            if (c)
                colset = JSON.parse(c);
            colset[id] = 1;
            createCookie('collapse', JSON.stringify(colset), 365);
        });
        $(".accordion-body").on('hidden', function() {
            var id = $(this).attr('id');
            $("a[href='#" + id + "']").children().attr('class', 'icon-chevron-down');

            var c = readCookie('collapse');
            if (c) {
                var colset = JSON.parse(c);
                if (colset[id]) {
                    delete(colset[id]);
                }
            }
            createCookie('collapse', JSON.stringify(colset), 365);
        });
    }
};
addLoadEvent('collapseAdmin.forOnload');


