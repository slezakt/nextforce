var Mails = {

    testMailSelectPickerLoad : function() {

        var selector = '#testmail_target';
        var $target = $(selector);

        // disable select
        $target.prop('disabled',true);
        // Add live search
        $target.attr('data-live-search', 'true');
        // reinitialize selectpicker
        $target.selectpicker({width: '500px'});
        $target.selectpicker('refresh');

        // on radio change update select
        $('input[name="testmail_send"]').change(function() {
            var parent = null;
            var a = null;
            switch (this.value){
                case '0':
                    $target.prop('disabled',true);
                    break;
                case '1':
                    // set multiple to false
                    $target.prop('disabled',false).prop('multiple', false);
                    // reinitialize selectpicker
                    parent = $target.parent();
                    a = $target.clone();
                    $target.selectpicker('destroy');
                    $(parent).append(a);
                    $target = $(selector); // update target
                    $target.selectpicker({width: '500px'});
                    break;
                case '2':
                    // set multiple true
                    $target.prop('disabled',false).prop('multiple', true);
                    // reinitialize selectpicker
                    parent = $target.parent();
                    a = $target.clone();
                    $target.selectpicker('destroy');
                    $(parent).append(a);
                    $target = $(selector); // update target
                    $target.selectpicker({width: '500px'});

                    // read selected values from cookie
                    var c = readCookie('mails-testmail');
                    if (c) {
                        var data = JSON.parse(c);
                        $target.selectpicker('val', data);
                    }

                    // update cookie on select change
                    $target.change(function(){
                        createCookie('mails-testmail', JSON.stringify($(this).val()), 365);
                    });
                    break;
            }
            // refresh selectpicker
            $(selector).selectpicker('refresh');
        });
    }
}

addLoadEvent('Mails.testMailSelectPickerLoad');