$('#presentation').on('slideLoaded', function (e, el) {
    if (el.attr('id') == "slide2") {
        
        $('[name="ua_gdpr_email"],[name="ua_gdpr_postal_address"],[name="ua_gdpr_billing_address"],[name="ua_gdpr_phone"]').iCheck('check');
        $('[name="ua_gdpr_email_timestamp"],[name="ua_gdpr_postal_address_timestamp"],[name="ua_gdpr_billing_address_timestamp"],[name="ua_gdpr_phone_timestamp"]').val(indetail.server_vars.date);
        
    }
});

$(document).on('ifChanged', '[name="ua_gdpr_email"],[name="ua_gdpr_postal_address"],[name="ua_gdpr_billing_address"],[name="ua_gdpr_phone"]', function () {
   
    var name = $(this).attr('name') + '_timestamp';
    if (indetail.server_vars.date) {
        var date = indetail.server_vars.date;
    } else {
         var date = new Date();
         date = date.toISOString().slice(0,10);
    }
    $("[name='" + name + "']").val(date);
});

$(document).on('answersSent', function (e, formData, rightAnswer, id) {
    for (var data in formData) {
        var qid = $("[name='" + data + "']").data('qname');
        presentation.indetail.setQuestionAnswer(qid, formData[data]);
    }
});