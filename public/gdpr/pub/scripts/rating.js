var rating = {
    stars: new Array(),
    
    hoveredStar: null,
    
    currReq: 0,
    
    disableRating: false,
    
    disable: function(stars){
		var i;
		for(i=0; i<stars.length; i++){
			stars.css('cursor', 'auto');
		}
		stars.unbind('mouseenter mouseleave');
		stars.unbind('click');
	},
    
    higlightStar: function(i, req){
		// Zastarale pozadavky nevykonavame
		if(this.currReq!=req){
			return;
		}
		
    	var star = this.stars[i].star;
    	if(!star[0].wcIsHighlighted){
    		//star.css('background-position', '0px -28px');
    		//star.removeClass('sel');
    		star.addClass('hover');
    		star[0].wcIsHighlighted = true;
    	}
    },
    
    unhiglightStar: function(i, req){
    	// Zastarale pozadavky nevykonavame
		if(this.currReq!=req){
			return;
		}
    	
    	var star = this.stars[i].star;
    	// Pokud narazime na hvezdu, ktera je prave hoverovana, zrusime unhighlight u vsech nize
    	if(i <= this.hoveredStar && !(this.hoveredStar === null)){
    		var key;
    		//Zrusime u vsech mensich hvezd unhoglight
    		for(key in this.stars){
    			if(key<=i){
    				clearTimeout(this.stars[key].unhighTim);
    			}
    		}
    		return;
		}
    	if(star[0].wcIsHighlighted){
    		star[0].wcIsHighlighted = false;
    		if(star.hasClass('sel')){
    			//star.css('background-position', '0px 0px');
    			star.removeClass('hover');
    		}
    		else{
    			//star.css('background-position', '0px -14px');
    			star.removeClass('hover');
    		}
    	}
    	// Pokud je ratovani skonceno, odebereme hover funkce
		if(this.disableRating && i==0){
			this.disable($(".rating .stars div"));
		}
    },
    
	forOnload: function (){
		var stars = $(".rating .stars div");
		var t = this; 
		var i;
		
		// Zjistime,jestli jiz nebylo hodnoceno, pokud ano, nepokracujeme
		for(i=0; i<stars.length; i++){
			if(stars.eq(i).hasClass('sel')){
				this.disable(stars);
				return;
			}
		}
		
		stars.hover(function(){
			var req = ++t.currReq;
			var i;
			var j = 0;
			var thisJq = $(this);
			thisJq[0].wcIsHovered = true;
			
			// Rozsvitime nektere hvezdy
			for(i=0; i<stars.length; i++){
				var star = stars.eq(i);
				if(!t.stars[i]){
					t.stars[i] = {star: star, highTim: null, unhighTim: null};
				}
				else{
					t.stars[i].star = star;
				}
				
				var highTim = t.stars[i].highTim;
		    	var unhighTim = t.stars[i].unhighTim;
		    	clearTimeout(highTim);
		    	clearTimeout(unhighTim);
		    	
				if(!star[0].wcIsHighlighted){
					j++;
					t.stars[i].highTim = setTimeout("rating.higlightStar("+i+", "+req+");", 5*(j*j));
				}
				
				if(star[0] == thisJq[0]){
					t.hoveredStar = i;
					break;
				}
			}
			var currKey = i;
			// Zhasneme zbytek
			for(i=stars.length-1; i>=0; i--){
				if(star[0].wcIsHighlighted){
		    		j++;
		    		t.stars[i].unhighTim = setTimeout("rating.unhiglightStar("+i+", "+req+");", 5*(j));
		    	}
			}
		},
		function(){
			var req = ++t.currReq;
			var i;
			var j = 0;
			var thisJq = $(this);
			thisJq[0].wcIsHovered = false;
			
			// Zhasneme nejake hvezdy
			for(i=stars.length-1; i>=0; i--){
				var star = stars.eq(i);
				if(!t.stars[i]){
					t.stars[i] = {star: star, highTim: null, unhighTim: null};
				}
				else{
					t.stars[i].star = star;
				}
				
				if(star[0] == thisJq[0]){
					if(t.hoveredStar == i){
						t.hoveredStar = null;
					}
				}
				
		    	var unhighTim = t.stars[i].unhighTim;
		    	
		    	if(star[0].wcIsHighlighted){
		    		j++;
		    		var highTim = t.stars[i].highTim;
			    	var unhighTim = t.stars[i].unhighTim;
			    	clearTimeout(highTim);
			    	clearTimeout(unhighTim);
		    		t.stars[i].unhighTim = setTimeout("rating.unhiglightStar("+i+", "+req+");", 3*(j*j));
		    	}
			}
		});
		
		// Onclick
		stars.click(function(){
			var i;
			var thisJq = $(this);
			var rating = 0;
			var higlighting = true;
			for(i=0; i<stars.length; i++){
				var star = stars.eq(i);
				
				if(higlighting){
					star.addClass('sel');
				}
				else{
					star.removeClass('sel');
					//star.css('background-position', '0px -14px');
				}
				
				if(star[0] == thisJq[0]){
					rating = i+1;
					higlighting = false;
				}
			}
			
			// Ulozime na server
			$.post(rating_savePageRatingUrl + '/?rating='+rating, {}, 
		    	function(newRating){
					newRating = parseInt(newRating);
					var i;
					higlighting = true;
					for(i=0; i<stars.length; i++){
						var star = stars.eq(i);
						
						if(higlighting){
							star.addClass('sel');
							//star.css('background-position', '0px 0px');
						}
						else{
							star.removeClass('sel');
							//star.css('background-position', '0px -14px');
						}
						
						if(i+1 == newRating){
							higlighting = false;
						}
					}
					$(".rating .request").css('display', 'none');
					$(".rating .overall").css('display', 'block');
			});
			
			t.disableRating = true;
		});
	}
};

addLoadEvent('rating.forOnload');