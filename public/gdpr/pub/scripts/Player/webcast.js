var webcast = function ()
 {
	this.windowMoveSpeed = 300;
	this.currentSchema = 'main';
	
	this.schemas = {
			main: {
				// Pozice maximalizovanych oken, lze pouzit treba pro reset pracovni plochy
				// aktualne nepouzivame
				norm: new Array(
					{
						position: {top: 191, left: 26},
						size: {width: 442, height: 310}
					},
					{
						position: {top: 191, left: 493},
						size: {width: 442, height: 310}
					},
					{
						position: {top: 525, left: 493},
						size: {width: 442, height: 155}
					}
				),
				// Pozice pro minimalizovana okna
				// V podstate se pregenerovava metodou prepareSchema podle CSS
				mini:  new Array(
					{
						position: {top: 605, left: 26},
						size: {width: 208, height: 155},
						lastPos: null,
						lastSize: null,
						used: false
					},
					{
						position: {top: 605, left: 259},
						size: {width: 208, height: 155},
						lastPos: null,
						lastSize: null,
						used: false
					},
					{
						position: {top: 605, left: 493},
						size: {width: 208, height: 155},
						lastPos: null,
						lastSize: null,
						used: false
					},
					{
						position: {top: 605, left: 727},
						size: {width: 208, height: 155},
						lastPos: null,
						lastSize: null,
						used: false
					}
				)
			}
	}
	
	// Obsahuje informace o oknech na strance
	this.windows = new Array();
	
	// Pripravi podle CSS schema s pozicemi minimalizovanych oken
	this.prepareSchema = function(){
		var div = $("<div></div>");
		$('#windows').append(div);
		div.addClass('minimised_wins');
		var val;
		
		
		// Upravime kazdou polozku schema podle stylu
		for(i=0; i<this.schemas.main.mini.length; i++){
			//div.addClass('min'+(i+1));
			
			// Snazime se zjistit, ktere pozice jsou nastavene
			hasChanged = false;
			
			valOld = parseInt(div.css('top'));
			div.addClass('min'+(i+1));
			val = parseInt(div.css('top'));
			div.removeClass('min'+(i+1));
			if(!isNaN(val)){
				this.schemas.main.mini[i].position.top = val;
				if(valOld != val){
					hasChanged = true;
				}
			}
			valOld = parseInt(div.css('left'));
			div.addClass('min'+(i+1));
			val = parseInt(div.css('left'));
			div.removeClass('min'+(i+1));
			if(!isNaN(val)){
				this.schemas.main.mini[i].position.left = val;
				if(valOld != val){
					hasChanged = true;
				}
			}
			valOld = parseInt(div.css('width'));
			div.addClass('min'+(i+1));
			val = parseInt(div.css('width'));
			div.removeClass('min'+(i+1));
			if(!isNaN(val)){
				this.schemas.main.mini[i].size.width = val;
				if(valOld != val){
					hasChanged = true;
				}
			}
			valOld = parseInt(div.css('height'));
			div.addClass('min'+(i+1));
			val = parseInt(div.css('height'));
			div.removeClass('min'+(i+1));
			if(!isNaN(val)){
				this.schemas.main.mini[i].size.height = val;
				if(valOld != val){
					hasChanged = true;
				}
			}
			
			if(!hasChanged){
				delete this.schemas.main.mini[i];
			}
			
			//alert(div.position().left + ' ' + div.css('top') + ' ' + parseInt(div.css('left')) + ' ' + div.css('width') + ' ' + div.css('height'));
			
			
			
			//div.removeClass('min'+(i+1));
		}
		
		div.remove();
	}
	
	// Pripravi okna s obsahy pro pouzivani
	// minimalizuje okna, ktera maji byt minimalizovana a zobrazi vsechny okna
	// naplni pole this.windows s informacemi o oknech na strance
	this.prepareWindows = function (){
		var windowsJq = $("#windows");
		
		for(i=0; i<windowsJq.children().size(); i++){
			var win = windowsJq.children().eq(i);
			
			// Pridame okno do pole oken
			this.windows[this.windows.length] = {
					objJq: win, 
					id: win.attr('id'), 
					posKey: null,
					beingMinimalized: false,
					beingMaximalized: false
				};
			
			win.css('display', 'block');
			
			// pokud je okno oznacene jako minimalizovane, minimalizujeme
			if(win.hasClass('mini')){
				this.minimalizeWin(win);
			}
			
			/*
			//win.css('z-index', '0');
			// Toto je silena vec, ktera vyresi problem se z-indexem v IE7
			$(function() {
				var zIndexNumber = 0;
				$('div').each(function() {
					//$(this).css('zIndex', zIndexNumber);
					//zIndexNumber += 10;
				});
			});
			*/
		}
	}
	
	
	// Minimalizuje okno do jedne z pozic pro minimalizovana okna
	this.minimalizeWin = function (win){
		var i;
		// Najdeme v this.windows zaznam prave zmensovaneho okna a nastavime mu priznak beingMinimalized
		for(i=0; i<this.windows.length; i++){
			if(this.windows[i].id == win.attr('id')){
				this.windows[i].beingMinimalized = true;
				break;
			}
		}
		
		//alert('min:'+win.attr('id'));
		
		var positions = this.schemas[this.currentSchema].mini;
		var emptyPos = this.findEmptyPos(win);
		var posKey = emptyPos.key;
		//alert(posKey + ' ' +win.attr('id'));
		
		// Pokud nebyla nalezena prazdna pozice, musime nejprve zvetsit/zmensit nejaky kolidujici obsah
		if(emptyPos.position.used || emptyPos.collidingRec){
			if(emptyPos.position.used){
				// Najdeme v this.windows okno, ktere je prave na dane pozici
				for(i=0; i<this.windows.length; i++){
					if(this.windows[i].posKey == posKey){
						break;
					}
				}
				if(!emptyPos.position.beingMaximalized){
					this.maximalizeWin(this.windows[i].objJq, posKey);
				}
			}
			else if(!emptyPos.collidingRec.beingMinimalized && !emptyPos.collidingRec.beingMaximalized){
				// Nastavime pozici jako pouzivanou
				positions[posKey].used = true;
				// Jde o okno, ktere je maximalizovane, minimalizujeme
				this.minimalizeWin(emptyPos.collidingRec.objJq);
				// Musime znovu nalezt prazdnou pozici
				posKey = emptyPos.key;
			}
		}
		
		// Ulozime puvodni rozmery a pozici okna k dane zmensene pozici
		var lastPos = win.position();
		var lastSize = {width: win.outerWidth(), height: win.outerHeight()};
		
		// Pridame tridu pro zmenu tlacitka na maximalizaci
		$(".winHead .buttResize", win).addClass('max');
		win.addClass('mini');
		
		/*
		win.css('left', emptyPos.position.position.left);
		win.css('top', emptyPos.position.position.top);
		//*/
		win.animate({left: emptyPos.position.position.left, top: emptyPos.position.position.top}, this.windowMoveSpeed);
		
		this.resizeWin(win, emptyPos.position.size);

		// Nastavime pozici jako pouzivanou
		positions[posKey].used = true;
		
		// Nastavime klic pozice v this.windows a lastPos s lastSize
		for(i=0; i<this.windows.length; i++){
			if(this.windows[i].id == win.attr('id')){
				this.windows[i].posKey = posKey;
				this.windows[i].lastPos = lastPos;
				this.windows[i].lastSize = lastSize;
				this.windows[i].beingMinimalized = false;
			}
		}
	}
	
	// Maximalizuje okno do jeho puvodni pozice a priadne minimalizuje okno na danem miste
	this.maximalizeWin = function (win, posKey){
		var position = this.schemas[this.currentSchema].mini[posKey];
		var t = this;
		
		// Najdeme zaznam v this.windows
		var i;
		for(i=0; i<this.windows.length; i++){
			if(this.windows[i].id == win.attr('id')){
				var winRec = this.windows[i];
				break;
			}
		}
		
		//alert('max: '+(i+1));
		
		// Nastavime u zaznamu okna priznak beingMaximalized
		winRec.beingMaximalized = true;
		
		// Zrusime zabrani dane pozice
		position.used = false;
		
		// Zjistime, jestli pri zvetseni nebude okno s nejakym jinym kolidovat, pokud ano
		// to jine minimalizujeme
		var colliding = this.isCollising({position: winRec.lastPos, size: winRec.lastSize}, win);
		// Najdeme zaznam kolidujiciho v this.windows
		if(colliding){
			for(i=0; i<this.windows.length; i++){
				if(this.windows[i].id == colliding.attr('id')){
					var collidingRec = this.windows[i];
					break;
				}
			}
			if(collidingRec.posKey === null){
				// Minimalizujeme okno pouze pokud uz se tak nedeje
				if(!collidingRec.beingMinimalized && !collidingRec.beingMaximalized){
					this.minimalizeWin(colliding);
				}
			}
			// Jde o jiz minimalizovane okno, provedeme maximalizaci
			else{
				if(!collidingRec.beingMinimalized && !collidingRec.beingMaximalized){
					this.maximalizeWin(colliding, collidingRec.posKey);
				}
			}
		}
		
		// Odebereme tridu, aby tlacitko bylo jako minimalizace
		$(".winHead .buttResize", win).removeClass('max');
		win.removeClass('mini');
		
		// Provedeme zvetseni okna na puvodni rozmery a do puvodni pozice
		/*
		win.css('left', winRec.lastPos.left);
		win.css('top', winRec.lastPos.top);
		//*/
		var winLastSize = winRec.lastSize;
		win.animate({left: winRec.lastPos.left, top: winRec.lastPos.top}, this.windowMoveSpeed, null, 
				function(){
					//t.resizeWin(win, winLastSize);
				});
		this.resizeWin(win, winLastSize);
		
		// Smazeme minule pozice a velikost
		winRec.lastPos = null;
		winRec.lastSize = null;
		winRec.posKey = null;
		
		// konec maximalizace
		winRec.beingMaximalized = false;
	}
	
	// Najde volnou pozici pro minimalizovani okna a vrati ji
	this.findEmptyPos = function (winJq){
		var positions = this.schemas[this.currentSchema].mini;
		
		var emptyPos = null;
		var emptyPosKey = null;
		var i;
		var j;
		for(var i in positions){
			var position = positions[i];
			var colliding = this.isCollising(position, winJq);
			if(i == 0){
				var firstColliding = colliding;
				var firstPosition = position;
				var firstPosKey = i;
			}
			
			// Najdeme zaznam colliding v this.windows
			if(colliding){
				for(j=0; j<this.windows.length; j++){
					if(colliding.attr('id') == this.windows[j].id){
						var collidingRec = this.windows[j];
						break;
					}
				}
			}
			// Najdeme zaznam pozice v this.windows
			for(j=0; j<this.windows.length; j++){
				if(i == this.windows[j].posKey){
					var winRec = this.windows[j];
					break;
				}
			}
			
			// Alternativni zaznam, ktery pouzijeme pokud se nenajde zadna prazdna pozice
			if(!position.used){
				var firstColliding = colliding;
				var firstPosition = position;
				var firstPosKey = i;
			}
			
			if(!colliding || (collidingRec && collidingRec.posKey != null) || 
						(collidingRec && collidingRec.beingMinimalized) || (winRec && winRec.beingMaximalized))
			{
				if(!emptyPos){
					emptyPos = position;
					emptyPosKey = i;
					if(!emptyPos.used){
						break;
					}
				}
				else if(!position.used || (winRec && winRec.beingMaximalized) || 
						(collidingRec && collidingRec.beingMinimalized))
				{
					//if(collidingRec)alert(collidingRec.beingMinimalized + ' '+collidingRec.id + ' ' + i);
					emptyPos = position;
					emptyPosKey = i;
					break;
				}
			}
		}
		
		// Najdeme zaznam pozice vpripadneho kolidujiciho okna
		if(firstColliding){
			for(j=0; j<this.windows.length; j++){
				if(this.windows[j].objJq == firstColliding){
					var collidingRec = this.windows[j];
					break;
				}
			}
		}
		else{
			var collidingRec = null;
		}
		
		if(!emptyPos){
			emptyPos = firstPosition;
			emptyPosKey = firstPosKey;
		}
		
		//if(collidingRec) alert(collidingRec.id);
		
		return {position: emptyPos, key: emptyPosKey, collidingRec: collidingRec};
	}
	
	// Zjisti, jestli dana pozice okna nekoliduje s nejakym existujicim objektem okna z pole
	// this.windows, v pripade kolize vraci objekt okna v jQ
	this.isCollising = function (position, selfWin){
		var windows = this.windows;
		var i;
		var j;
		var k;
		
		var rect2 = new Array(
				{x: position.position.left, y: position.position.top},
				{x: position.position.left+position.size.width, y: position.position.top},
				{x: position.position.left+position.size.width, y: position.position.top+position.size.height},
				{x: position.position.left, y: position.position.top+position.size.height}
				);
		
		
		for(i=0; i<windows.length; i++){
			var colliding = false;
			var winJq = windows[i].objJq;
			var rect1 = new Array(
					{x: winJq.position().left, y: winJq.position().top},
					{x: winJq.position().left+winJq.outerWidth(), y: winJq.position().top},
					{x: winJq.position().left+winJq.outerWidth(), y: winJq.position().top+winJq.outerHeight()},
					{x: winJq.position().left, y: winJq.position().top+winJq.outerHeight()}
					);
			
			//alert(winJq.attr('id'));
			for(j=0; j<4; j++){
				if(this.isInsideRect(rect1, rect2[j])){
					colliding = winJq;
					break;
				}
			}
			if(!colliding){
				for(j=0; j<4; j++){
					if(this.isInsideRect(rect2, rect1[j])){
						colliding = winJq;
						break;
					}
				}
			}
			
			if(colliding && colliding[0] != selfWin[0]){
				//alert('colliding: '+winJq.attr('id'));
				return colliding;
			}
		}
		
		return false;
	}
	
	// Zjisti, jestli je bod uvnitr obdelniku
	this.isInsideRect = function (rect, point){
		//alert(rect[0].x +'<='+ point.x +' '+ rect[1].x +'>='+ point.x + '\n' + rect[0].y +'<='+ point.y +' '+ rect[2].y +'>='+ point.y);
		if(rect[0].x <= point.x && rect[1].x >= point.x){
			if(rect[0].y <= point.y && rect[2].y >= point.y){
				return true;
			}
		}
		return false;
	}
	
	this.resizeWin = function (winJq, size){
		var winHeight = winJq.outerHeight();
		
		// Velikost vseho unitr v prvku s class .in tedy mimo hlavicek
		var headerHeight = winJq.contents().filter(".winHead").outerHeight();
		
		// Resizery
		var widthResize = ((size.width) / (winJq.outerWidth()));
		var heightResize = ((size.height) / (winHeight));
		
		// Velikost vnejsku okna
		var resizer_win = this.resizer(widthResize, heightResize, true);
		winJq.each(resizer_win);
		// Velikost hlavicky
		var resizer_head = this.resizer(widthResize, 1, true);
		$(".winHead", winJq).each(resizer_head);
		//alert($(".winHead", winJq).outerWidth());
		
		
		/*
		var width = winJq.outerWidth() * widthResize;
		var height = winJq.outerHeight() * heightResize;
		winJq.height(height);
		winJq.height(height - (winJq.outerHeight() - height));
		winJq.width(width);
		winJq.width(width - (winJq.outerWidth() - width));
		//*/
		
		
		// Pozice scrollu v pomerne delce
		var inJq = winJq.contents().filter(".in");
		var scrollTop = inJq[0].scrollTop;
		var scrollHeight = inJq[0].scrollHeight - inJq.innerHeight();
		var scrollPos = scrollTop > 0 ? scrollTop / scrollHeight : 0;

		var heightResize_in = ((size.height - headerHeight) / (winHeight - headerHeight));
		//alert(winHeight + ' ' + (size.height-headerHeight) + ' ' + (winHeight-headerHeight));
		//alert( heightResize_in + ' ' +heightResize_in * (winHeight-headerHeight) + ' ' + headerHeight);
		// Element .in
		inJq.each(this.resizer(widthResize, heightResize_in, true));
		// Vse uvnitr elementu .in
		var resizer_in = this.resizer(widthResize, heightResize_in);
		inJq.children().each(resizer_in);
		
		
		// Pokud mame ulozenou scrollPos v objektu, tak ji pouzijeme
		if(inJq[0].wcResizeLastScrollPos){
			scrollPos = inJq[0].wcResizeLastScrollPos;
			inJq[0].wcResizeLastScrollPos = null;
		}
		// Pokusime se nastavit zpet scroll elementu .in
		if(scrollPos > 0){
			scrollHeight = inJq[0].scrollHeight - inJq.innerHeight();
			inJq[0].scrollTop = scrollHeight * scrollPos;
		}
		// Pokud je po zmene velikosti okno bez scrollu, ulozime do elementu
		// scrollPos k pouziti pri opetovne zmene velikosti
		if((inJq.innerHeight() - inJq[0].scrollHeight) >= 0){
			inJq[0].wcResizeLastScrollPos = scrollPos;
		}
		
		return;
	}
	
	this.resizer = function (widthP, heightP, plainResize){
		if(typeof widthP == 'object'){
			heightP = widthP.height;
			widthP = widthP.width;
		}
		var t = this;
		
		// Funkce zvetsovadla ke spusteni na kazdem zmensovanem elementu 
		return function (){
			var thisJq = $(this);
			
			// Pokud v tomto prvku nejsou ulozeny informace o jeho velikosti pred zvetsenim, projdeme 
			// cely jeho strom i s nasledniky a ulozime jejich puvodni velikosti
			if(!thisJq[0].wcResizeOrigSizeRedy){
				//alert('writedown width: '+thisJq[0].wcResizeOrigWidth);
				t.writeDownOriginalSize(thisJq);
			}
			
			var height = thisJq[0].wcResizeOrigHeight;
			var width = thisJq[0].wcResizeOrigWidth;
			
			var sizerW = widthP;
			var sizerH = widthP;
			if(plainResize || thisJq.hasClass('proportional')){
				// plainResize pouzivame u rozmeru okna, proto bude zvetsovadlo vysky 
				// jine nez sirky
				var sizerH = heightP;
			}
			
			if(!plainResize){
				// Ruzne CSS
				t.resizeCssProp(thisJq, sizerW, 'font-size');
				t.resizeCssProp(thisJq, sizerW, 'margin-top');
				t.resizeCssProp(thisJq, sizerW, 'margin-bottom');
				t.resizeCssProp(thisJq, sizerW, 'margin-left');
				t.resizeCssProp(thisJq, sizerW, 'margin-right');
				t.resizeCssProp(thisJq, sizerW, 'padding-top');
				t.resizeCssProp(thisJq, sizerW, 'padding-bottom');
				t.resizeCssProp(thisJq, sizerW, 'padding-left');
				t.resizeCssProp(thisJq, sizerW, 'padding-right');
			}
			
			if(!(thisJq.attr('id') == 'swfPTPlayer')){
				thisJq[0].wcResizeOrigSizeRedy = false;
				// Upravujeme jen rozmery prvkuu, ktere jsou v CSS nastavene, vyjimkou jsou obrazky
				//if(thisJq.css('width') != 'auto' || thisJq[0].tagName == 'IMG'){
				if((thisJq[0].wcResizeOrigWidth == thisJq.outerWidth() && width > 0) || thisJq[0].tagName == 'IMG'){
					var newWidth = Math.round(t.prepareNewPropSize(thisJq, sizerW, 'width', width));
					// Rozmery - nemenime rozmery, ktere se zmenily sami zmenou nadrazeneho elementu
					//if(thisJq[0].wcResizeOrigWidth == width){
						
						thisJq.width(newWidth);
						thisJq.width(newWidth - (thisJq.outerWidth() - newWidth));
						thisJq[0].wcResizeOrigWidth = null;
					//}
				}
				
				// Upravujeme jen rozmery prvkuu, ktere jsou v CSS nastavene, vyjimkou jsou obrazky
				//if(thisJq.css('height') != 'auto' || thisJq[0].tagName == 'IMG'){
				if((thisJq[0].wcResizeOrigHeight == thisJq.outerHeight() && height > 0) || thisJq[0].tagName == 'IMG'){
					var newHeight = Math.round(t.prepareNewPropSize(thisJq, sizerH, 'height', height));
					// Rozmery - nemenime rozmery, ktere se zmenily sami zmenou nadrazeneho elementu
					//if(thisJq[0].wcResizeOrigHeight == height){
						thisJq.height(newHeight);
						thisJq.height(newHeight - (thisJq.outerHeight() - newHeight));
						thisJq[0].wcResizeOrigHeight = null;
					//}
				}
			}
			
			// Pokud se nema provadet kompletni zmensovani, ale jen vnesi dimenze, tady skoncime
			if(plainResize){
				return;
			}
			
			
			/*
			t.resizeCssProp(thisJq, widthP, 'font-size');
			t.resizeCssProp(thisJq, heightP, 'margin-top');
			t.resizeCssProp(thisJq, heightP, 'margin-bottom');
			t.resizeCssProp(thisJq, widthP, 'margin-left');
			t.resizeCssProp(thisJq, widthP, 'margin-right');
			t.resizeCssProp(thisJq, heightP, 'padding-top');
			t.resizeCssProp(thisJq, heightP, 'padding-bottom');
			t.resizeCssProp(thisJq, widthP, 'padding-left');
			t.resizeCssProp(thisJq, widthP, 'padding-right');
			//*/
			
			// Provedeme zmenseni do hloubky, pokud objekt nema class, ktera to zakazuje
			if(!thisJq.hasClass('noDeepResize')){
				var resizer = t.resizer(widthP, heightP);
				thisJq.children().not('.winHead').each(resizer);
			}
		}
	}
	
	// Poznamena do elementu a kazdeho jeho naslednika (rekurzivne) jeho velikost
	// pro porovnani pred odeslanim (zaznamenava i velikost fontu)
	this.writeDownOriginalSize = function (elemsJq, notFirstRound){
		
		// callback funkce pro provedeni zapisu na kazdem elementu
		var writer = function (){
			var thisJq = $(this);
			
			thisJq[0].wcResizeOrigSizeRedy = true;
			
			// Alespon pro IE optimalizujeme a neukladame zbytecne velikosti prvku, ktere nemusime
			// velikost = auto vraci jen IE a Chrome
			if(thisJq.css('width') != 'auto' || thisJq[0].tagName == 'IMG'){
				thisJq[0].wcResizeOrigWidth = thisJq.outerWidth();
			}
			if(thisJq.css('height') != 'auto' || thisJq[0].tagName == 'IMG'){
				thisJq[0].wcResizeOrigHeight = thisJq.outerHeight();
			}
			if(thisJq.css('font-size') != 'auto'){
				thisJq[0].wcResizeOrigFontSize = parseInt(thisJq.css('font-size'));
			}
		}
		
		if(!notFirstRound){
			elemsJq.each(writer);
		}
		
		var childrenJq = elemsJq.children();
		childrenJq.each(writer);
		
		if(childrenJq.length){
			this.writeDownOriginalSize(childrenJq, true);
		}
	}
	
	// Zmeni velikost dane css properity
	this.resizeCssProp = function (objJq, sizer, propName){
		var propSize = (objJq.css(propName)+"" || "").replace( /[^0-9]/g, "");
		// Pokud je velikost nula, muzeme to preskocit
		if(propSize == '0' || propSize == '' || (propName == 'font-size' && propSize > 100)){
			return;
		}
		// Pro font-size zabranime rekurzi porovnanim s velikosti fontu pred zmensovanim
		//if(propName == 'font-size')alert(objJq[0].wcResizeOrigFontSize);
		if(propName == 'font-size' && objJq[0].wcResizeOrigFontSize != propSize){
			return;
		}
		
		var newPropSize = this.prepareNewPropSize(objJq, sizer, propName, propSize);
		
		// Zmenime velikost
		objJq.css(propName, Math.round(newPropSize));
	}
	
	// Pripravi velikost css vlastnosti a ulozi potrebna data do elementu
	this.prepareNewPropSize = function(objJq, sizer, propName, propSize){
		// Velikost pro vypocet se snazime brat z ulozeneho cisla a ne ze zaokrouhlene velikosti
		if(!objJq[0].wcwincssprops){
			objJq[0].wcwincssprops = {};
		}
		if(!objJq[0].wcwincssprops[propName]){
			objJq[0].wcwincssprops[propName] = {};
		}
		if(!objJq[0].wcwincssprops[propName]['origSize']){
			// Ulozime si presnou puvodni velikost a aktualni sizer properity pro naslednou manipulaci
			objJq[0].wcwincssprops[propName]['origSize'] = propSize;
			objJq[0].wcwincssprops[propName]['sizer'] = sizer;
			var newPropSize = propSize * sizer;
		}
		else{
			var overallSizer = objJq[0].wcwincssprops[propName]['sizer'] * sizer; 
			// Provedeme pokus se zaokrouhlenim, jestli se nevracime na puvodni velikost
			if(Math.round(overallSizer * 1000) == 1000){
				var newPropSize = objJq[0].wcwincssprops[propName]['origSize'];
				objJq[0].wcwincssprops[propName]['sizer'] = 1;
			}
			else{
				var newPropSize = objJq[0].wcwincssprops[propName]['origSize'] * overallSizer;
				objJq[0].wcwincssprops[propName]['sizer'] = sizer;
			}
		}
		/*
		// Ladeni zmensovani a zvetsovani
		if(1 || propName == 'width' || propName == 'height'){
			objJq.css('border', '#0f0 1px solid');
			alert(propName+': '+propSize + ' -> '+newPropSize +' \n cssSize: ' + objJq.css(propName));
			objJq.css('border', 'none');
		}
		//*/
		
		return newPropSize;
	}
	
	this.forOnload = function ()
	{
		var t = this;
		
		this.prepareSchema();
		this.prepareWindows();
		
		$(".buttResize").click(function () {
			
			var winJq_pom = $(this).parent();
			var winJq = null;  
			var thisJq = $(this);
			var elemId = null;
			
			// Dohledame samotne okno - je nekterym z rodicu this
			for(i=0; i<10; i++){
				elemId = winJq_pom.attr("id");
				if(elemId && elemId.match(/win[0-9]+/)){
					winJq = winJq_pom;
					break;
				}
				winJq_pom = winJq_pom.parent();
			}
			if(!winJq){
				// Nenaslo se okno, konec
				return;
			}
			
			if(thisJq.filter(".max").size()){
				var winId = winJq.attr('id');
				
				// Najdeme zaznam pro toto okno v this.windows
				var i;
				for(i=0; i<t.windows.length; i++){
					if(t.windows[i].id == winId){
						var winRec = t.windows[i];
						break;
					}
				}
				
				t.maximalizeWin(winJq, winRec.posKey);
			}
			else{
				t.minimalizeWin(winJq);
			}
			
		});
			
			
	}
}

webcastObj = new webcast();
addLoadEvent('webcastObj.forOnload');