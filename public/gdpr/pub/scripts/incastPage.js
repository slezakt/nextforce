var incastPage = {
    emailValidator: /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/,

    validateRequired: function(fields, container){
        
        err = false;
        for(var name in fields){
            field = $("input[name='"+name+"']", container);
            // overime existenci inputu
            if (field.length) {
                // validujeme vyplnenost pro select,radio,text,checkbox 
                if ( (field.is(':checkbox') && !field.is(':checked')) || 
                     (field.jQuery.trim(field.val()) == '')) {
                    // zobrazime chybovou hlasku
                    $("label[for='"+name+"'] span.mandatory", container).show();
                    err = true;
                } else {
                    // schovame chybovou hlasku
                    $("label[for='"+name+"'] span.mandatory", container).hide(); 
                }
            }
        }

        return err;    
    },
    
    orderNews: function(){
        // Objednat newsletter
        $(".orderNewsletter").click(function(){
            var box = new Lightbox("#lightboxOrdernewsletter");
			box.show();
            
            return false;
        });
        
        // Potvrdit email
        var submitFunction = function(){
            var box = new Lightbox("#lightboxOrdernewsletter");
           
            var email = $("input[name='subscribeemail']", box.box);

            //vykoname click na zadavacim poli, kdyby tam byla hlaska
            email.click();
            
            // provedeme zakladni validaci
            var fields = ['subscribename','subscribeemail', 'subscribegroup', 'agreement'];
            if (!this.validateRequired(fields, box.box)) {
                return false;
            }

            // doplnujici validace
            if(jQuery.trim(email.val()) == '@'){
                $("label[for='subscribeemail'] span.mandatory", box.box).show();
                return false;
            }
            else{
                $("label[for='subscribeemail'] span.mandatory", box.box).hide();
            }

            // Odesleme data
            box.setWorking(true);
            var url = $("form", box.box).attr('action') + '?' + $("form", box.box).serialize();
            $.get(url, {}, function(data)
            {
                box.setWorking(false);
                if(!data){
                    $("label[for='subscribeemail'] span.wrong", box.box).hide();
                    // Vyplnime email do zadavaciho pole
                    $(".question .subscribeemail", box.box).val(email.val());
                    
                    box.showAnswer(new Array({pattern: '%%email%%', val: email.val()}));
                }
                else{
                    // Informace o chybe dame do zadavaciho pole
                    //lightbox.infoIntoInText(emailInput, data, $("#lightboxOrdernewsletter .question form .submit"));
                    
                    // Zobrazime info o chybe
                    $("label[for='subscribeemail'] span.wrong", box).show();
                    $("label[for='subscribeemail'] span.wrong", box).text(data);
                    
                    return false;
                }
            });
            return false;
        }
        $("#lightboxOrdernewsletter .question form .submit").click(submitFunction);
        $("#lightboxOrdernewsletter .question form").submit(submitFunction);
    
    },
    
    lightboxConsulting: function() {
        //var working = $("#lightboxForward .working");
        var box = new Lightbox('#lightboxConsulting');
        var t = this;

        // Otevreni lightboxu
        $(".consulting").click(function(){
            $("form",box.box).attr('action', $(this).attr('rel'));
            return t.lightboxConsultingShow();
        });

        // Odeslat
        $(".submit", box.box).click(function() {
            
            var username = $("input[name='username']", box.box).val();
            var email = $("input[name='email']", box.box).val();
            var message = $("input[name='message']", box.box).val();
            
           // provedeme zakladni validaci
            var fields = ['username','email', 'message'];
            if (!this.validateRequired(fields, box.box)) {
                return false;
            }
                        
            // doplnujici validace
            if(jQuery.trim(email) == '@'){
                $("label[for='email'] span.mandatory", box.box).show();
                return false;
            }
            else{
                $("label[for='email'] span.mandatory", box.box).hide();
            }

            // Zobrazime info o zpracovavani
            box.setWorking(true);

            // Posleme data
            var url = $("form", box.box).attr('action')+'/';

            jQuery.post(url, {username: username, email: email, message: message}, function(xml) {

                box.setWorking(false);

                if(jQuery("consulting wrongMail", xml).text()){
                    $("label[for='email'] span.wrong", box.box).show();
                }
                else if(jQuery("consulting message", xml).length){
                    $("label[for='email'] span.wrong", box.box).hide();

                    if(jQuery("consulting ok", xml).text()){
                        // Smazeme data ve formulari
                        // $("input[name='email']", box.box).attr('value', '@');
                        $("textarea[name='message']", box.box).attr('value', '');
                    }

                    // Zobrazeni vysledku
                    var message = jQuery("consulting message", xml).text();
                    message = message.replace(/%%email%%/, email);

                    box.showAnswer(new Array({pattern: '%%message_text%%', val: message}));
                }
            }, 'xml');

            return false;
        });

    },

    // Zobrazit lightbox pro consulting - mame zde, aby bylo mozne volat z Playeru
    lightboxConsultingShow: function() {
        var box = new Lightbox('#lightboxConsulting');
        return box.show();
    },


    lightboxForward: function() {
        //var working = $("#lightboxForward .working");
        var box = new Lightbox('#lightboxForward');
        var t = this;
        
        // Otevreni lightboxu
        $(".sendToFriend").click(function(){
            return t.lightboxForwardShow();
        });
        
        // Preposlat dalsimu
        $(".dynbutt.sendToNext", box.box).click(function(){
            box.hide();
            box.show();
            return false;
        });
        
        // Odeslat
        $(".submit", box.box).click(function() {
            // Overime vyplneni
            var username = $("input[name='username']", box.box).val();
            var email = $("input[name='email']", box.box).val();
            var message = $("textarea[name='message']", box.box).val();
            
            // provedeme zakladni validaci
            var fields = ['username','email', 'message'];
            if (!this.validateRequired(fields, box.box)) {
                return false;
            }
                        
            // doplnujici validace
            if(jQuery.trim(email) == '@'){
                $("label[for='email'] span.mandatory", box.box).show();
                return false;
            }
            else{
                $("label[for='email'] span.mandatory", box.box).hide();
            }
           
            // Zobrazime info o zpracovavani
            box.setWorking(true);
            
            // Posleme data
            var url = $("form", box.box).attr('action')+'/';
            jQuery.post(url, {subscribename: username, dontsendregistration: 'true', email: email, message: message, isAjaxForwardMail: 1}, function(xml) {
                box.setWorking(false);
                if(jQuery("forwardmail wrongMail", xml).text()){
                    $("label[for='email'] span.wrong", box.box).show();
                }
                else if(jQuery("forwardmail message", xml).text()){
                    $("label[for='email'] span.wrong", box.box).hide();
                    
                    if(jQuery("forwardmail ok", xml).text()){
                        // Smazeme data ve formulari
                        $("input[name='email']", box.box).attr('value', '@');
                        $("textarea[name='message']", box.box).attr('value', '');
                    }
                    
                  // Zobrazeni vysledku
                    var message = jQuery("forwardmail message", xml).text();
                    message = message.replace(/%%email%%/, email);
                    
                    box.showAnswer(new Array({pattern: '%%message_text%%', val: message}));
                }
            }, 'xml');
            
            return false;
        });
        
    },
    
    // Zobrazit lightbox pro forward - mame zde, aby bylo mozne volat z Playeru
    lightboxForwardShow: function() {
        var box = new Lightbox('#lightboxForward');
        return box.show();
    },
    
    lightboxOpinions: function(discussion_container) {
        
        discussion_container = typeof(discussion_container) != 'undefined' ? discussion_container : '.in';
        var box = new Lightbox('#lightboxOpinions');
        var t = this;
        
        var sendUrl = ''; // Url kam posleme data
        var opinionsWindowIn = null; // Okno v kterem jsou opinions, v nem nahradime obsah
        
        var scrollDownFunc = function(){
			//alert('scrollHeight:'+$(".opinions").parent().attr("scrollHeight"));
            $(".opinions").parent().attr({ scrollTop: $(".opinions").parent().attr("scrollHeight") });
            // Pro IE6, 7
            if ($.browser.msie && $.browser.version.substr(0,1)<=7){
                $(discussion_container + " .enter").focus();
                $(discussion_container + " .enter").blur();
            }
        }
        scrollDownFunc();
        
        // Navesime scrolldown function na tlacitko, ktere je dole, aby pri zvetsovani a zmensovani 
        // bylo narolovano dolu
        // NEFUNGUJE
        //$(".opinions button img").resize(scrollDownFunc);
        
        
        // Zobrazit lightbox
        var showOpinionsFunction = function(){
            // Zablokujeme tlacitko pokud je okno minimalizovane
            if($(this).parents('.window:first').hasClass('mini')){
                return false;
            }
            
            var opinionsForm = $(this).parents('form:first');
            opinionsWindowIn = $(this).parents(discussion_container + ':first');
            sendUrl = opinionsForm.attr('action');
            // Jmeno uzivatele pro pripadne predvyplneni
            var userName = $("input[name='opinions_name']", opinionsForm).attr('value');
            // Predvyplneni jmena uzivatele
            if(jQuery.trim($("input[name='username']", box.box).attr('value')) == ''){
                $("input[name='username']", box.box).attr('value', userName);
            }
            
            box.show();
            
            return false;
        };
        $(discussion_container + " .enter").click(showOpinionsFunction);
        
      // Tlacitko odeslat a odeslani dat
        $(".submit", box.box).click(function(){
            // Overime vyplneni
            var username = $("input[name='username']", box.box).attr('value');
            var message = $("textarea[name='message']", box.box).attr('value');

            // provedeme zakladni validaci
            var fields = ['username', 'message'];
            if (!this.validateRequired(fields, box.box)) {
                return false;
            }
                      
            // Zobrazime info o zpracovavani
            box.setWorking(true);
            
            // Posleme data
            var url = sendUrl+'/';
            jQuery.post(url, {opinions_name: username, opinions_text: message, opinions_send: 1}, function(xml)
			{
                box.setWorking(false);
                
                if(jQuery("opinions ok", xml) && jQuery("opinions ok", xml).text()){
                    // Smazeme data ve formulari
                    $("textarea[name='message']", box.box).attr('value', '');
                }
                
                // Nahradime obsah okna s diskuzi
                var renderData = jQuery("opinions renderCDATA", xml).text();
                if($.trim(renderData) != ''){
                    opinionsWindowIn.html(renderData);
                    // Reaktivace tlacitka
                    $(discussion_container + " .enter").click(showOpinionsFunction);
                    // Scroll
                    scrollDownFunc();
                }
                
              // Zobrazeni vysledku
                var message = jQuery("opinions message", xml).text();
                box.showAnswer(new Array({pattern: '%%message_text%%', val: message}));
            }, 'xml');
            return false;
        });
    },
    
    lightboxConfirm: function()
    {
        var box = new Lightbox('#lightboxLogin', true);
		//box.show();
         
        // Velikost pozadi lightboxu i pri resize okna
        box.bckResizer();
        $(window).bind("resize", function(){
			box.bckResizer();
			//box.center(); Zlobi - nastavuje spatnou pozici
		});
        
        
        // Potvrzeni odbornosti
        $("#boxconfirm_yes", box.box).click(function(){
            box.hide();
            $.post($(this).attr('href'), {}, function(data){});
            return false;
        });
        $("#boxconfirm_no", box.box).click(function(){
            box.showAnswer();
            return false;
        });
    },
        
    forOnload: function(){
        this.lightboxConfirm();
        this.orderNews();
        this.lightboxForward();
        this.lightboxConsulting();
        this.lightboxOpinions();
        
    }
}

addLoadEvent('incastPage.forOnload');
