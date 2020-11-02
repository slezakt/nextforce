// Lightbox constructor
// @param String id     Cast selectoru pro box - napriklad #lightboxOrdernewsletter, nebo cely selector
// @param Bool   fullSelector Bool  Jedna se o cely sleector (true) nebo jen cast uvnitr #lightboxpos (false)?
var Lightbox = function(id, fullSelector)
{
    var selector = id;
    if(!fullSelector){
        selector = '#lightboxpos ' + selector;
    }
    this.box = $(selector);
	
	this.working = $('.working', this.box);
	
	Lightbox.forOnload();
};

    // Naplneni globalnich vlastnosti, musi byt az po nacteni stranky, protoze obsahuje odkazy na elementy
    Lightbox.forOnload = function(){
		this.prototype.back = $('#lightboxback');
		this.prototype.lightboxpos = $('#lightboxpos');
		this.prototype.hideCallback = null; // Callback, ktery se provede pri zavreni tohoto lightboxu
		this.prototype.workingTimeout = 7000; // Timeout pro skryti working a jeho lightboxu
		this.prototype.originalTexts = {}; // Texty pro navraceni zpet do elementu pred prepsanim
		this.workingCallbacks = {}; // Callbacky pro timery - museji byt k dispozici v globalnim prostoru
	}
	
    // Zobrazi lightbox a provede vsechny potrebne ukony
    Lightbox.prototype.show = function()
    {
        var t = this;
        
        this.hideAnswer();
        this.back.show();
        this.lightboxpos.show();
        this.box.show('fast', function(){
            t.center();
            t.bckResizer();
			// Provedeme focus na close button nahore, aby se pripadne prescrollovalo
	        $(".close", t.box).eq(0).focus();
        });
        
        // Zavirani boxu pri kliknuti na pozadi
        this.back.click(function(){
			return t.hide();
		});
        
        // Tlacitka close (cokoliv s css class .close) 
        $('.close', this.box).click(function(){
			return t.hide();
		});
		//alert($('.close', this.box).eq(1).click());
        
        return false;
    };
    
    // Skryje lightbox a pripravi k dalsimu pouziti, callback pro zavolani po provedeni vseho
    Lightbox.prototype.hide = function(callback)
    {
        this.setWorking(false);
        this.back.hide();
        this.box.hide();
        this.lightboxpos.hide();
        this.hideAnswer();
        //this.back.unbind('click');
        $('.close', this.box).unbind('click');
        if(callback){
            callback();
        }
        if(this.hideCallback){
            this.hideCallback();
        }
        return false;
    };
    
    // Callback se zavola po provedeni kliku na pozadi
    // DEPRECATED - presunuto do this.show()
    Lightbox.prototype.clikableBack = function(callback)
    {
        var t = this; 
        this.back.click(function(){
            t.back.hide();
            t.back.unbind('click');
            if(callback){
                callback(this);
            }
        });
    };
    
    // Prohodi obsahy boxu - zobrazi element .answer a skryje .question
    // Umi v HTML answer nahradit retezce (znacky) jinym textem a nasledne vse vrati zpet
    // @param Array replaces  Pole objektu {pattern:, val:}, pattern bude nahrazen val
    // @param String cssclass Upresnujici nazev class (v pripade vice otazek a odpovedi v jednom LB)
    Lightbox.prototype.showAnswer = function(replaces, cssclass)
    {
		var t = this;
        if(!cssclass){
            cssclass = '';
        }
        
        var question = $(".question", this.box);
        var answer = $(".answer" + cssclass, this.box);
        
        if(replaces){
            //var answerHtmlOrig = answer.html();
            var originals = {};
            var text, newText, node, newNode;
            // Nahrazujeme texty ve vsech textovych nodech
            var textnodeFilter =  function(){
                return this.nodeType == 3 && $.trim($(this).text()) != '';
            };
			var j = 0;
            answer.find('*').contents().filter(textnodeFilter).each(function(){
                node = $(this);
                text = node.text();
				for(i in replaces){
					newText = text.replace(new RegExp(replaces[i].pattern), replaces[i].val);
				}
                // Nahradime text (je az prekvapujici, jaky je problem nahradit text v textnode...)
				// pokud neni jen text musi byt vlozen element ne jen text node
				newNode = $('<div>'+newText+'</div>').contents();
				node.replaceWith(newNode);
				
                // Ulozime original
                originals[j] = {node: newNode, text: text};
				j++;
            });
            
            this.originalTexts['answer_' + cssclass] = originals;
        }
        
        question.hide();
        answer.show();
    };
    
    // Prohodi obsahy boxu - zobrazi element .question a skryje .answer
    // @param String cssclass Upresnujici nazev class (v pripade vice otazek a odpovedi v jednom LB)
    Lightbox.prototype.hideAnswer = function(cssclass)
    {
        if(!cssclass){
            cssclass = '';
        }
        
        // Nahradime vnitrnosti odpovedi originalama
		var text; 
        for(i in this.originalTexts){
            if(i.match(/^answer_/)){
                var selectorPart = i.replace(/^_answer/, '');
				// Vratime podle ulozenych dat originalni texty, ktere jsme si ulozili
				for(j in this.originalTexts[i]){
					text = this.originalTexts[i][j].text;
					// Nahradime text - je nutne vlozit novy textnode a puvodni nody odstranit
                    this.originalTexts[i][j].node.eq(0).replaceWith(text);
					this.originalTexts[i][j].node.remove();
				}
				
                this.originalTexts[i] = null;
            }
        }
        
        $(".answer", this.box).hide();
        $(".question" + cssclass, this.box).show();
    };
    
    // Zobrazi nebo skryje working a nastavi casovac na skryti.
    // Skryva jen samotny LB a pozadi, neprovadi cele this.hide - tedy vse se jen schova
    // v pripade uspechu (zavolani this.setWorking(false) se zase vse zobrazi)
    // @param Bool state   Zapnout/vypnout
    // @param function unSuccessCallback   Callback v pripade vyprseni casovace
    Lightbox.prototype.setWorking = function(state, unSuccessCallback)
    {
        if(state){
            if(this.workingTimer){
                clearTimeout(this.workingTimer);
            }
            
            this.working.show();
            this.back.show();
            this.lightboxpos.show();
            this.box.show();
            
            var t = this;
            // Vytvorime callback protimer, ktery ulozime do globalniho prostoru
            var timerCallback = function(){
                t.box.hide();
                t.lightboxpos.hide();
                t.back.hide();
                t.working.hide();
                if(unSuccessCallback){
                    unSuccessCallback();
                }
                // Zrusime callback po jeho provedeni
                Lightbox.workingCallbacks[t.box.selector] = null;
                return false;
            }
            Lightbox.workingCallbacks[this.box.selector] = timerCallback;
            
            this.workingTimer = setTimeout('Lightbox.workingCallbacks[\''+this.box.selector+'\']', this.workingTimeout);
        }
        else{
            if(this.workingTimer){
                clearTimeout(this.workingTimer);
            }
            Lightbox.workingCallbacks[this.box.selector] = null;
            this.working.hide();
            this.back.show();
            this.lightboxpos.show();
            this.box.show();
        }
    }
    
    Lightbox.prototype.center = function() {
        //return; // Pro nove lightboxy neni potreba a hlavne nefunguje!
         if(window.innerHeight){
            var windowH = window.innerHeight;
            var windowW = window.innerWidth;
        }
        else if(document.documentElement){
                var windowH = document.documentElement.offsetHeight;
                var windowW = document.documentElement.offsetWidth;
        }
        else{
            var windowH = null;
            var windowW = null;
        }
		
		var boxW = this.box.outerWidth();
		var boxH = this.box.outerHeight();
		// nastavime rozmery boxu jeho elementu pos
		this.lightboxpos.css({position: 'absolute', width: boxW, height: boxH});
		
        if(windowH && windowW){
            var pos = {};
            pos.left = windowW/2 - this.box.outerWidth()/2;
            pos.top = windowH/2 - this.box.outerHeight()/2 + $(window).scrollTop();
            
            if(pos.top < 0){
                pos.top = 0;
            }
            if(pos.left < 0){
                pos.left = 0;
            }
        }
        else{
            var pos = {top: 50, left:50};
        }
		
		/*
		alert('box: '+this.box.outerWidth()+' x '+this.box.outerHeight() + '\n' + 
		  'win: '+windowW+' x '+windowH +'\n' + 'posL: '+pos.left + ' posT:' + pos.top);
		//*/
		
        this.lightboxpos.css(pos);
		this.box.css({marginTop: 0});
    };
    
    // Informacni hlaska v input text - input je JQ input text, text je hlaska
    // cleaners je callback funkce pro uklizeni vseho do puvodniho stavu
    // TODO: Neni upravena pro nove principy pouziti
    Lightbox.prototype.infoIntoInText = function(input, text, cleaners){
        input[0].infoIntoInTextOldVal = input.attr('value');
        input.blur();
        input.val(text);
        input.addClass('error');
        var form = input.parents("form:first");
        
        var getBack = function(){
            if(input[0].infoIntoInTextOldVal){
                input.attr('value', input[0].infoIntoInTextOldVal);
                input[0].infoIntoInTextOldVal = null;
                input.removeClass('error');
            }
            
            input.unbind('click focus', getBack);
            form.unbind('submit', getBack);
            if(cleaners){
                cleaners.unbind('click', getBack);
            }
        };
        
        
        input.click(getBack);
        input.focus(getBack);
        input.submit(getBack);
        if(cleaners){
            cleaners.click(getBack);
        }
    };
    
    // Velikost pozadi lightboxu
    Lightbox.prototype.bckResizer = function(){
        var lightboxH = this.back.outerHeight();
        
        if(document.documentElement){
            var windowH = document.documentElement.offsetHeight;
            var scrollH = document.documentElement.scrollHeight;
        }
        else if(window.innerHeight){
            var windowH = window.innerHeight;
            var scrollH = 0;
        }
        else{
            var windowH = 0;
            var scrollH = 0;
        }
        

        var htmlH = $("html").outerHeight();
        
        /*
        var bodyJq = $("body");
        var bodyH = bodyJq.outerHeight() + parseInt(bodyJq.css('margin-top'), 10)
            + parseInt(bodyJq.css('padding-top'), 10) + parseInt(bodyJq.css('margin-bottom'), 10)
            + parseInt(bodyJq.css('padding-bottom'), 10);
        */
        
        
        //alert(lightboxH + ' ' + htmlH + ' '+ windowH + ' '+bodyH + ' ' + scrollH);
        newheight = Math.max(lightboxH, htmlH, windowH, scrollH);
        this.back.height(newheight);
    };
    
    // Zobrazeni lightboxu se zpravou ziskanou z elementuu ve strance, ktery obsahuje elementy
    // .head a .text
    // h1 a text zadavame misto msgClassName (msgClassName = null)
    // TODO: Netestovano - aktualne neni na cem...
    Lightbox.prototype.showMessage = function(msgClassName, head, text){
        if(!head){
            var head = $(msgClassName+".head").html();
        }
        if(!text){
            var text = $(msgClassName+".text").html();
        }
                
        var originalHead = $('.headingTxt', this.box).html();        
        var originalText = $('.messageTxt', this.box).html();
                
        $('.headingTxt', this.box).html(head);
        $('.messageTxt', this.box).html(text);
        
        this.addHideCallback(function(){
            $('.headingTxt', this.box).html(originalHead);
            $('.messageTxt', this.box).html(originalText);
        });
    };
    
    // Prida na konec stavajiciho callback volani po zavreni tohoto LB dalsi funkci
    Lightbox.prototype.addHideCallback = function(callback){
       if(callback){
           this.hideCallback = function(){
              this.hideCallback();
              callback();
           }
       }
    };

addLoadEvent('Lightbox.forOnload');
