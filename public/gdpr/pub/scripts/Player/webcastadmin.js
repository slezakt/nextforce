var webcastadmin = function() 
{
	this.videoPlayerElemId = 'swfAVPlayer';
	this.presPlayerElemId = 'swfPTPlayer';
	
	this.setWorking = function (state)
	{
		if(state){
			$("#workingIndicator").css('display', 'block');
		}
		else{
			$("#workingIndicator").css('display', 'none');
		}
	}
	
	this.formatTime = function (time)
	{
		var mins = Math.floor(time/1000/60);
		var secs = Math.floor(time/1000) - mins * 60;
		var msecs = Math.round(time - secs * 1000 - mins * 60 * 1000);
		var timeStr = mins + ':' + (secs < 10 ? '0'+secs : secs) + '.' +
			(msecs < 100 ? (msecs < 10 ? '00'+msecs : '0'+msecs) : msecs);
		return timeStr;
	}
	
	this.timeStrToMs = function (timeStr)
	{
		var timeA = timeStr.split(':');
		var time = parseInt(timeA[0]) * 60 * 1000 + parseFloat(timeA[1]) * 1000;
		return time;
	}
	
	this.insertRowToTable = function (time, slide, table, content)
	{
		var wa = this;
		var foundRow = null;
		$("#"+table+" > tbody > tr").each(function (){
			if(time == null){
				var rowSlide = $(this).children().eq(1).text();
				if(slide > rowSlide){
					foundRow = $(this);
					return;
				}
			}
			else{
				var rowTime = $(this).children().eq(2).find("button").attr('name');
				if(time > rowTime){
					foundRow = $(this);
					return;
				}
			}
		});
		
		
		if(!foundRow){
			$("#"+table+" > tbody").prepend(content);
		}
		else{
			foundRow.after(content);
		}
		this.prepareSyncTable();
	}
	
	// Smaze radek, ktery ma bud cas, nebo slide shodny se zadanym
	this.removeFromTable = function (time, slide){
		if(time == null){
			var table = "syncTabPres";
			$("#"+table+" > tbody > tr").each(function (){
				var rowSlide = $(this).children().eq(1).text();
				if(slide == rowSlide){
					$(this).empty();
				}
			});
		}
		else if(slide == null){
			var table = "syncTabVideo";
			$("#"+table+" > tbody > tr").each(function (){
				var rowTime = $(this).children().eq(2).find("button").attr('name');
				if(time == rowTime){
					$(this).empty();
				}
			});
		}
		else{
			return;
		}
	}
	
	
	this.prepareSyncTable = function ()
	{
		var wa = this;
		
		// Tlacitka na mazani boduu
		$(".syncButRemove").click(function (){
			var time = $(this).attr('name');
			var slide = $(this).parent().siblings().eq(1).text();
			var tableId = $(this).parent().parent().parent().parent().attr('id');
			
			if(tableId == 'syncTabVideo'){
				var media = 'vid';
			}
			else if(tableId == 'syncTabPres'){
				var media = 'pres';
			}
			
			wa.setWorking(true);
			var row = $(this).parent().parent();
			$.post(manipulateSyncpUrl+'?timeold='+time+'&slideold='+slide+'&media='+media, {}, 
		    	function(data){
				//alert(data);
					row.empty();
					wa.setWorking(false);
				}
			);
		});	
		
	}
	
	this.preparePlayerButtons = function ()
	{
		var wa = this;
		
		// Tlacitka na pridavani bodu
		$(".syncButAdd").click(function (){
			if(!wa.videoPlayer.getPlayhead || !wa.presPlayer.getSlide){
				return;
			}
			var time = wa.videoPlayer.getPlayhead();
			var slide = wa.presPlayer.getSlide();
			var timeStr = wa.formatTime(time);
			
			var html = '<tr>\
                <td>'+timeStr+'</td>\
                <td>'+slide+'</td>\
                <td>\
                    <button class="syncButRemove" name="'+time+'">'+butRemoveText+'</button>\
                </td>\
            </tr>'; 

            var butName = $(this).attr('name');    
            
            wa.setWorking(true);
			$.post(manipulateSyncpUrl+'?time='+time+'&slide='+slide+'&media='+butName, {}, 
		    	function(data){
					//alert(data);
					// Vyber kam se ma zapisovat
					if(butName == 'vid'){
						var table = "syncTabVideo";
						wa.removeFromTable(time, null);
						wa.insertRowToTable(time, null, table, html);
					}
					else if(butName == 'pres'){
						var table = "syncTabPres";
						wa.removeFromTable(null, slide);
						wa.insertRowToTable(null, slide, table, html);
					}
					else{
						var table = "syncTabVideo";
						wa.removeFromTable(time, null);
						wa.insertRowToTable(time, null, table, html);
						var table = "syncTabPres";
						wa.removeFromTable(null, slide);
						wa.insertRowToTable(null, slide, table, html);
					}
					
					wa.setWorking(false);
				}, 'text'); 
            
		});
		
		// Tlacitko pro znovunacteni konfigurace
		$("#reloadConfBut").click(function (){
			wa.videoPlayer.loadConfig(videoXmlConfigUrl+'?' + new Date().getTime());
			wa.presPlayer.loadConfig(presXmlConfigUrl+'?' + new Date().getTime());
			return false;
		});
		
		// Propojeni prehravacu pomoci eventuu...
		$("#connectPlayers, #connectPlayersLabel").click(function (){
			if($("#connectPlayers").attr('checked')){
				inCast.deliverEvents = true;
			}
			else{
				inCast.deliverEvents = false;
			}
		});
		
		// Nastaveni inCast.deliverEvents podle klikatka
		if($("#connectPlayers").attr('checked')){
			inCast.deliverEvents = true;
		}
		else{
			inCast.deliverEvents = false;
		}
		
	}
	
	this.forOnload = function ()
	{
		this.videoPlayer = document.getElementById(this.videoPlayerElemId);
		this.presPlayer = document.getElementById(this.presPlayerElemId);
		
		
		this.preparePlayerButtons();
		this.prepareSyncTable();
	};
};

webcastadminObj = new webcastadmin();
addLoadEvent('webcastadminObj.forOnload');