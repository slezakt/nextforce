$(document).ready(function(){
    var userData = presentation.options.userData;
    if (!userData.gdpr_global || !userData.gdpr_project) {
      parent.postMessage('showPopup','*');  
    }
});

$(document).on('click','#close-inpopup',function(){
    parent.postMessage('closePopup','*'); 
});

