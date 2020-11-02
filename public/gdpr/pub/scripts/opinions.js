/**
 * obsluha opinions vkládání komentaru a strankovani ajaxem
 */
var opinions = function() {
    //AJAX ADD COMMENT
    $(document).on('submit', '#frm_nazory', function(e) {
        e.preventDefault();
        var frm = $('#frm_nazory');
        $.ajax({
            type: frm.attr('method'),
            url: frm.attr('action'),
            data: frm.serialize()+"&contentPosition="+CONTENT_POSITION+"&ajaxform=1",
            dataType: 'xml',
            success: function(data) {
                if ($(data).find('ok').text() == 1) {
                    $('#nazory').get(0).outerHTML = $(data).find('renderCDATA').text();
                    frm.hide();
                   $('#add_opinion_form').show;
                   setTimeout(function(){$('.bubble').hide(1000);},5000);
                }
            }
        });
    });
    
    //AJAX PAGINATION
    $(document).on('click', '.navigation a', function(e) {
        e.preventDefault();
        var frm_display = $('#add_opinion_form').css('display');
        $.ajax({
            type: 'get',
            url: $(this).attr('href'),
            data: {ajax: 1, contentPosition: CONTENT_POSITION},
            dataType: 'xml',
            success: function(data) {
                $('#nazory').get(0).outerHTML = $(data).find('renderCDATA').text();
                $('#add_opinion_form').css('display',frm_display);
                if (frm_display !== "none") {
                    $('#show_opinion_form_button').toggle();
                }
            }
        });
    });
    
    //odkrytí formuláře
     $(document).on('click', '#show_opinion_form_button', function(e) {
        e.preventDefault();
        $('#add_opinion_form').show();
        $(this).hide();
    });
    
    //skrytí formuláře
     $(document).on('click', '#hide_opinion_form_button', function(e) {
        e.preventDefault();
        $('#add_opinion_form').hide();
        $('#show_opinion_form_button').show();
    });
    
    //tlacitko pod clankem - skok do opinion
     $(document).on('click', '#jump_to_opinion', function(e) {
        $('#show_opinion_form_button').trigger('click');
    });
    

};

addLoadEvent('opinions');

