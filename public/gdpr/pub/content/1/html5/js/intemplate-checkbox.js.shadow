//formatovani checkboxu
InTemplate.prototype.__checkbox = function () {

    var template = this;

    $(template.presentation).on('slideLoaded', function (e) {
        var checkBoxColor = "";
        if (template.options.checkboxColor) {
            checkBoxColor = '-'+template.options.checkboxColor;
        }

        $('input').iCheck({
            checkboxClass: 'icheckbox_'+template.options.checkboxStyle+checkBoxColor,
            radioClass: 'iradio_'+template.options.checkboxStyle+checkBoxColor
        });

    });
    
}
