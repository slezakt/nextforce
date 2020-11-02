var virtualrepPage = {
	subscribeemail: null,
	subscribename: null,
	emailValidator: /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/,
	
	lightbTimer: null,
	lightbWorkingAutohide: 2000,
		
	headTextSpeedMs: 7000,
	headTextsLastDisplayed: null,
	headTexts: function(){
		//var pictures = headerPicturesUrls.split('|');
		var count = $(".header .uvodnik .fotka a").length;
		//var count = pictures.length;
		var randNum = null;
		while(randNum === null || randNum === this.headTextsLastDisplayed){
			randNum = Math.floor(Math.random()*count);
		}
		this.headTextsLastDisplayed = randNum;
		
		
		$(".header .uvodnik .fotka a").fadeOut('slow');
		$(".header .uvodnik .fotka a").eq(randNum).fadeIn('slow');
		
		/*
		$(".header .uvodnik .annot").hide('normal');
		$(".header .uvodnik .annot").eq(randNum).show('normal');
		*/
		
	},
	
	lightbForward: function() {
		var working = $("#lightbForward .boxBck .working");
		var t = this;
		
		// Zobrazit lightbox
		$(".video-odkazy .preposli").click(function() {
			$("#lightboxback").show();
			var box = $("#lightbForward");
			lightbox.centerIt(box);
			box.show('fast');
			lightbox.clikableBack(function(){
				box.hide();
				working.hide();
			});
			//Tlacitko close
			$("#lightbForward .close").click(function() {
				box.hide();
				working.hide();
				$("#lightboxback").hide();
				return false;
			});
			
			return false;
		});
		
		// Odeslat
		$("#lightbForward .submit").click(function() {
			// Overime vyplneni
			var username = $("#lightbForward input[name='username']").attr('value');
			var email = $("#lightbForward input[name='email']").attr('value');
			var message = $("#lightbForward textarea[name='message']").attr('value');
			var err = false;
			if(jQuery.trim(username) == ''){
				$("#lightbForward label[for='username'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbForward label[for='username'] span.mandatory").hide();
			}
			if(jQuery.trim(email) == '' || jQuery.trim(email) == '@'){
				$("#lightbForward label[for='clegueemail'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbForward label[for='clegueemail'] span.mandatory").hide();
			}
			if(jQuery.trim(message) == ''){
				$("#lightbForward label[for='message'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbForward label[for='message'] span.mandatory").hide();
			}
			if(err){
				return false;
			}
			
			// Zobrazime info o zpracovavani
			working.show();
			// Nastavime casovac na skryti LB
			t.lightbTimer = setTimeout('$("#lightbForward").hide();$("#lightbForward .boxBck .working").hide();$("#lightboxback").hide();', t.lightbWorkingAutohide);
			
			// Posleme data
			var url = $("#lightbForward form").attr('action');
			
			jQuery.get(url, {subscribename: username, email: email, message: message, isAjaxForwardMail: 1,
				dontsendregistration: 1}, function(xml) 
			{
				working.hide();
				if(t.lightbTimer){
					clearTimeout(t.lightbTimer);
				}
				$("#lightboxback").show();
				if(jQuery("forwardmail wrongMail", xml).text()){
					$("#lightbForward").show();
					$("#lightbForward label[for='clegueemail'] span.wrong").show();
				}
				else if(jQuery("forwardmail message", xml).text()){
					$("#lightbForward label[for='clegueemail'] span.wrong").hide();
					
					if(jQuery("forwardmail ok", xml).text()){
						// Smazeme data ve formulari
						$("#lightbForward input[name='email']").attr('value', '@');
						$("#lightbForward textarea[name='message']").attr('value', '');
					}
					
					var message = jQuery("forwardmail message", xml).text();
					message = message.replace(/%%email%%/, email);
					// Nastavime vse potrebne do lightboxu se zpravou
					$("#lightbMessage .headingTxt").html($("#lightbForward h2").html());
					$("#lightbMessage .messageTxt").html(message);
					$("#lightbMessage button.close").click(function() {
						// Skryjeme
						$("#lightbMessage").hide();
						$("#lightboxback").hide();
						return false;
					});
					lightbox.centerIt($("#lightbMessage"));
					
					$("#lightbForward").hide();
					$("#lightbMessage").show();
					
				}
			}, 'xml');
			
			return false;
		});
		
	},
	
	lightbAsk: function() {
		var working = $("#lightbAsk .boxBck .working");
		var t = this;
		
		// Zobrazit lightbox
		$(".video-odkazy .poslat-dotaz").click(function() {
			$("#lightboxback").show();
			var box = $("#lightbAsk");
			lightbox.centerIt(box);
			box.show('fast');
			lightbox.clikableBack(function(){
				working.hide();
				box.hide();
			});
			//Tlacitko close
			$("#lightbAsk .close").click(function() {
				working.hide();
				box.hide();
				$("#lightboxback").hide();
				return false;
			});
			
			return false;
		});
		
		// Odeslat
		$("#lightbAsk .submit").click(function() {
			// Overime vyplneni
			var username = $("#lightbAsk input[name='username']").attr('value');
			var email = $("#lightbAsk input[name='email']").attr('value');
			var question = $("#lightbAsk textarea[name='question']").attr('value');
			var err = false;
			if(jQuery.trim(username) == ''){
				$("#lightbAsk label[for='username'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbAsk label[for='username'] span.mandatory").hide();
			}
			if(jQuery.trim(email) == '' || jQuery.trim(email) == '@'){
				$("#lightbAsk label[for='myemail'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbAsk label[for='myemail'] span.mandatory").hide();
			}
			if(jQuery.trim(question) == ''){
				$("#lightbAsk label[for='question'] span.mandatory").show();
				err = true;
			}
			else{
				$("#lightbAsk label[for='question'] span.mandatory").hide();
			}
			if(err){
				return false;
			}
			
			// Zobrazime info o zpracovavani
			working.show();
			// Nastavime casovac na skryti LB
			t.lightbTimer = setTimeout('$("#lightbAsk").hide();$("#lightbAsk .boxBck .working").hide();$("#lightboxback").hide();', t.lightbWorkingAutohide);
			
			// Posleme data
			var url = $("#lightbAsk form").attr('action');
			// Do zpravy doplnime zadane jmeno uzivatele
			question = username + '\r\n' + question;
			jQuery.get(url, {subscribename: username, subscribeemail: email, subscribeemailnotchange: 1, 
					subscribeemailsubmit: 1, dontsendregistration: 1, dontregister: 1,email: email, dotaz: question, 
					isAjaxConsulting: 1}, function(xml) 
			{
				working.hide();
				if(t.lightbTimer){
					clearTimeout(t.lightbTimer);
				}
				$("#lightboxback").show();
				
				if(jQuery("consulting wrongMail", xml).text()){
					$("#lightbAsk").show();
					$("#lightbAsk label[for='myemail'] span.wrong").show();
				}
				else if(jQuery("consulting message", xml).text()){
					$("#lightbAsk label[for='myemail'] span.wrong").hide();
					
					if(jQuery("consulting ok", xml).text()){
						// Smazeme data ve formulari
						//$("#lightbAsk input[name='email']").attr('value', '@');
						$("#lightbAsk textarea[name='question']").attr('value', '');
					}
					
					var message = jQuery("consulting message", xml).text();
					message = message.replace(/%%email%%/, email);
					// Nastavime vse potrebne do lightboxu se zpravou
					$("#lightbMessage .headingTxt").html($("#lightbAsk h2").html());
					$("#lightbMessage .messageTxt").html(message);
					$("#lightbMessage button.close").click(function() {
						// Skryjeme
						$("#lightbMessage").hide();
						$("#lightboxback").hide();
						return false;
					});
					lightbox.centerIt($("#lightbMessage"));
					
					$("#lightbAsk").hide();
					$("#lightbMessage").show();
					return false;
					
				}
			}, 'xml');
			
			return false;
		});
	},
		
	
	
	
	keStazeni: function(){
		var arrowDownSrc = 'pub/img/arrow-down.png';
		var arrowRightSrc = 'pub/img/arrow-right.png';
		var preloadArrow = new Image();
		preloadArrow.src = arrowRightSrc;
		
		$(".ke-stazeni > ul > li").click(function(){
			var thisJq = $(this);
			var name = thisJq.children("a").attr("href").substring(1);
			var descs = $(".pole .pole-m");
			var arrow = thisJq.children(".sipkaD");
			//var arrowD = thisJq.children(".sipkaD");
			//var arrowR = thisJq.children(".sipkaR");
			
			// Vse skryjeme
			descs.each(function() {
				$(this).hide();
			});
			
			// Pokud je kliknut vybrany prvek, skryjeme vse zpet
			if(thisJq.hasClass('vybrano')){
				thisJq.removeClass('vybrano');
				arrow.attr('src', arrowDownSrc);
				//arrowR.hide();
				//arrowD.show();
				
				$(".pole").hide('fast', function(){
					// Vse skryjeme
					descs.each(function() {
						$(this).hide();
					});
				});
				return false;
			}
			
			// Odstranime vybrani odkazu
			$(".ke-stazeni > ul > li").removeClass('vybrano');
			$(".ke-stazeni > ul > li > .sipkaD").attr('src', arrowDownSrc);
			//$(".ke-stazeni > ul > li > .sipkaR").hide();
			//$(".ke-stazeni > ul > li > .sipkaD").show();
			
			// Zobrazime co je treba
			descs.each(function() {
				if($(this).children().filter('a').attr('name') == name){
					$(this).show('fast');
					thisJq.addClass('vybrano');
					arrow.attr('src', arrowRightSrc);
					//arrowD.hide();
					//arrowR.show();
				}
				else{
					$(this).hide('fast');
				}
			});
			$(".pole").show('fast');
			$(".pole").focus();
			
			return false;
		});
		
		// Zobrazeni prvku podle anchoru v URL
		//var name = 'anchor';
		//name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");  
		//var regexS = "[\\?&]"+name+"=([^&#]*)";
		var regexS = "#([^#]*$)";  
		var regex = new RegExp( regexS );  
		var results = regex.exec( window.location.href );  
		if( results == null )    
			var anchor = "";  
		else    
			var anchor = results[1];
		
		$(".ke-stazeni > ul > li").each(function() {
			var arrow = $(this).children(".sipkaD");
			//var arrowD = $(this).children(".sipkaD");
			//var arrowR = $(this).children(".sipkaR");
			
			if($(this).children().filter('a').eq(0).attr('href') === '#'+anchor){
				$(this).addClass('vybrano');
				//arrowD.hide();
				//arrowR.show();
				arrow.attr('src', arrowRightSrc);
				// Zobrazime pole
				$(".pole .pole-m a[name='"+anchor+"']").parent().show().addClass('vybrano');
				$(".pole").show('fast');
				$(".pole").focus();
			}
		});
		
		
	},
	
	kalendarAkci: function(){
		$(".cal-box a.cal-vice,.cal-box a.nazevKongr").click(function(){
			var thisJq = $(this);
			var popis;
			var anchor = thisJq.attr('href');
			
			if(thisJq.parent().hasClass('cal-box')){
				popis = thisJq.parent().children().filter(".pole");
			}
			else{
				popis = thisJq.parent().parent().children().filter(".pole");
			}
			
			// Skryti, nebo zobrazeni?
			if(popis.hasClass('displayed')){
				popis.removeClass('displayed');
				popis.hide('fast');
			}
			else{
				popis.addClass('displayed');
				popis.show('fast');
			}
			// Zmenime url v prohlizeci
			if(window.location.replace){
				var regexS = "^(.*)#([^#]*$)";
				var regex = new RegExp( regexS );
				var urlParts = regex.exec( window.location.href );
				if(urlParts == null){
					var url = window.location.href;
				}
				else{
					var url = urlParts[1];
				}
				window.location.replace(url + anchor);
			}
			
			return false;
		});
		
		// Zobrazeni prvku podle anchoru v URL
		//var name = 'anchor';
		//name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");  
		//var regexS = "[\\?&]"+name+"=([^&#]*)";
		var regexS = "#([^#]*$)";  
		var regex = new RegExp( regexS );  
		var results = regex.exec( window.location.href );  
		if( results == null )    
			var anchor = "";  
		else
			var anchor = results[1];
		
		$(".pole a[name='"+anchor+"']").parent().show('fast').addClass('displayed');;
	},
	
	aktuality: function(){
		$(".aktual-box a.aktual-vice,.aktual-box a.nazevAkt").click(function(){
			var thisJq = $(this);
			var popis;
			
			if(thisJq.parent().hasClass('aktual-box')){
				popis = thisJq.parent().children().filter(".pole");
			}
			else{
				popis = thisJq.parent().parent().children().filter(".pole");
			}
			
			// Skryti, nebo zobrazeni?
			if(popis.hasClass('displayed')){
				popis.removeClass('displayed');
				popis.hide('fast');
			}
			else{
				popis.addClass('displayed');
				popis.show('fast');
			}
			
			return false;
		});
		
		// Zobrazeni vsech
		$("a.aktual-all").click(function(){
			var thisJq = $(this);
			
			if(thisJq.hasClass('displayed')){
				$(".aktual-box .pole").hide('fast').removeClass('displayed');
				thisJq.removeClass('displayed');
				// Zmenime text
				thisJq.text('ZOBRAZIT VŠECHNY ZPRÁVY');
			}
			else{
				$(".aktual-box .pole").show('fast').addClass('displayed');
				thisJq.addClass('displayed');
				// Zmenime text
				thisJq.text('SKRÝT VŠECHNY ZPRÁVY');
			}
			
			return false;
		});
	},
		
	forOnload: function (){
		// Zobrazeni nabidky registrace
		$(".foot-01 .foot-info a").click(function(){
			$(".registruj").show('fast', function(){
				$(".registruj #jmeno").focus();
				// Velikost pozadi lightboxu
				lightbox.bckResizer();
			});
			
			//$(this).parent().css({display:'block'});
			return false;
		});
		var t = this;
		// Overeni formulare registrace
		$(".registruj input[name='subscribeemailsubmit']").click(function() {
			$(".registruj input[name='subscribeemail']").blur();
			$(".registruj input[name='subscribename']").blur();
			
			var nameClear = function(el) {
				var el = $(".registruj input[name='subscribename']");
				el.attr('value', virtualrepPage.subscribename);
				el.removeClass('error');
				virtualrepPage.subscribename = null;
				el.unbind('focus');
			}; 
			var emailClear = function(){
				var el = $(".registruj input[name='subscribeemail']");
				el.attr('value', virtualrepPage.subscribeemail);
				el.removeClass('error');
				virtualrepPage.subscribeemail = null;
				el.unbind('focus');
			};
			
			if($(".registruj input[name='subscribename']").hasClass('error')){
				//nameClear();
				var konec = true;
			}
			if($(".registruj input[name='subscribeemail']").hasClass('error')){
				//emailClear();
				var konec = true;
			}
			if(konec){
				return false;
			}
			
			// Nacteme zadane hodnoty
			if(!$(".registruj input[name='subscribename']").hasClass('error')){
				var username = $(".registruj input[name='subscribename']").attr('value');
			}
			else{
				var username = virtualrepPage.subscribename;
			}
			if(!$(".registruj input[name='subscribeemail']").hasClass('error')){
				var email = $(".registruj input[name='subscribeemail']").attr('value');
			}
			else{
				var email = virtualrepPage.subscribeemail;
			}
			
			
			if(jQuery.trim(username) == ''){
				virtualrepPage.subscribename = username;
				$(".registruj input[name='subscribename']").unbind('focus');
				var nameErr = $(".registruj label[for='subscribename'] span.mandatory").text();
				$(".registruj input[name='subscribename']").attr('value', nameErr);
				$(".registruj input[name='subscribename']").addClass('error');
				$(".registruj input[name='subscribename']").focus(nameClear);
				$(".registruj input[name='subscribename']").blur();
			}
			if(jQuery.trim(email) == '' || jQuery.trim(email) == '@'){
				var emailErr = $(".registruj label[for='subscribeemail'] span.mandatory").text();
			}
			else if(!t.emailValidator.test(email)){
				var emailErr = $(".registruj label[for='subscribeemail'] span.wrong").text();
			}
			if(emailErr){
				virtualrepPage.subscribeemail = email;
				$(".registruj input[name='subscribeemail']").attr('value', emailErr);
				$(".registruj input[name='subscribeemail']").addClass('error');
				$(".registruj input[name='subscribeemail']").focus(emailClear);
				$(".registruj input[name='subscribeemail']").blur();
			}
			if(emailErr || nameErr || konec){
				return false;
			}
		});
		// Vypsani chyby mailu registrace, pokud nastane po odeslani
		if($(".registruj input[name='subscribeemail']").hasClass('wrong')){
			$(".registruj input[name='subscribeemail']").blur();
			var email = $(".registruj input[name='subscribeemail']").attr('value');
			virtualrepPage.subscribeemail = email;
			var emailErr = $(".registruj label[for='subscribeemail'] span.wrong").text();
			$(".registruj input[name='subscribeemail']").attr('value', emailErr);
			$(".registruj input[name='subscribeemail']").addClass('error');
			$(".registruj input[name='subscribeemail']").unbind('focus');
			$(".registruj input[name='subscribeemail']").focus(function(){
				$(this).attr('value', virtualrepPage.subscribeemail);
				$(this).removeClass('error');
				virtualrepPage.subscribeemail = null;
				$(this).unbind('focus');
			});
		}
		
		
		
		
		
		// Spusteni ostatnich veci
		this.keStazeni();
		this.kalendarAkci();
		this.aktuality();
		this.lightbForward();
		this.lightbAsk();
		if($(".header .uvodnik .fotka a").length > 1){
			this.headTexts();
			setInterval('virtualrepPage.headTexts()', this.headTextSpeedMs);
		}
		
		
		
		window.onbeforeunload = function() {
			//alert('beforeunload');
			if($("#swfAVPlayer").length && $("#swfAVPlayer")[0] && $("#swfAVPlayer")[0].releasePlayer){
				$("#swfAVPlayer")[0].releasePlayer();
			}
		};
		
		$("a.kill").click(function() {
			return true;
			if($("#swfAVPlayer").length && $("#swfAVPlayer")[0] && $("#swfAVPlayer")[0].releasePlayer){
				//$("#swfAVPlayer")[0].releasePlayer();
			}
			//swfobject.removeSWF("swfAVPlayer");
		});
		
		// Pokud byla prave provedena registrace, zobrazime podekovani v LB
		if($(".registerThanks").length){
			lightbox.showMessage(".registerThanks");
		}
	}
		
};
addLoadEvent('virtualrepPage.forOnload');