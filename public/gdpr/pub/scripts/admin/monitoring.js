 var monitoringAdmin = {
	
	forOnload: function(){
		this.setDateRangeIndetail();

	},
	
	setDateRangeIndetail: function(e) {
        var datepickFrom = $("#datepickFrom");
        var datepickTo = $("#datepickTo");
		
        var dayMs = 3600000*24;
		var nowDate = new Date();
        nowDate.setHours(0,0,0,0);
		
		// Nahozeni datepickeru
        var datepickers = $( ".datepicker" ); 
        //datepickers.datepicker();
        datepickers.datepicker({dateFormat: "yy-mm-dd"});
        
		
        // Zjistime rozsah pro slider - pocet dni od prvniho pouziti contentu
		// Pocet dni je ulozen v select > option atribut title
		var firstDate = new Date($("#contentSel option[selected=selected]").attr('title'));
		firstDate.setHours(0,0,0,0);
		if(typeof firstDate == 'NaN'){
    		var range = -(nowDate - firstDate)/dayMs;
        }
        // Pokud neni v selectboxu, bereme range z obsahu elementu slideru (tam si ji nastavujeme v PHP)
        else{
            var range = -($("#slider-range").text());
            $("#slider-range").text('');
        }
		
		
		
        // Slider tak aby nastavoval datum pres datepicker
        $("#slider-range").slider({
            range: true,
            min: range,
            max: 0,
            values: [ range, 0 ],
            slide: function(event, ui){
                var fromDate = new Date();
                var toDate = new Date();
                
                fromDate.setDate(nowDate.getDate() + ui.values[0])
                toDate.setDate(nowDate.getDate() + ui.values[1]);
                
                datepickFrom.datepicker('setDate', fromDate);
                datepickTo.datepicker('setDate', toDate);
            }
        });
		
		
		// Datepicker - akce pro zmenu slideru pri zmene datumu
		var moveSliderCallback = function(dateText, inst, formField){
			if(formField == undefined){
                formField = $(this);
			}
			
            var daysOffset = -((nowDate - formField.datepicker('getDate'))/dayMs);
            //alert(daysOffset + ' | ' + formField.datepicker('getDate') + ' | ' + nowDate);
            
			var slider = 0;
			if(formField.attr('name') == 'from'){
				slider = 0;
			}
			else if(formField.attr('name') == 'to'){
				slider = 1;
			}
            
            $("#slider-range").slider('values', slider, daysOffset);
        }
		// Priradime pro kazdy datepicker
		datepickFrom.datepicker("option", {onSelect: moveSliderCallback});
		datepickTo.datepicker("option", {onSelect: moveSliderCallback});
		
		// Nastavime slider podle datumu ve formu
		moveSliderCallback('', null, datepickFrom);
		moveSliderCallback('', null, datepickTo);
		
	},
	
	
		
	 
 };
 		
addLoadEvent('monitoringAdmin.forOnload');		