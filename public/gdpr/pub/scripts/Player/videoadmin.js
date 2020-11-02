var videoadmin = function() 
{
	this.plainPlayerElemId = 'plainAvPlayer';
	this.fullPlayerElemId = 'fullAvPlayer';
	
	this.setWorking = function (state)
	{
		if(state){
			$("#workingIndicator").css('display', 'block');
		}
		else{
			$("#workingIndicator").css('display', 'none');
		}
	};
	
	this.formatTime = function (time)
	{
		var mins = Math.floor(time/1000/60);
		var secs = Math.floor(time/1000) - mins * 60;
		var msecs = Math.round(time - secs * 1000 - mins * 60 * 1000);
		var timeStr = mins + ':' + (secs < 10 ? '0'+secs : secs) + '.' +
			(msecs < 100 ? (msecs < 10 ? '00'+msecs : '0'+msecs) : msecs);
		return timeStr;
	};
	
	this.timeStrToMs = function (timeStr)
	{
		var timeA = timeStr.split(':');
		var time = parseInt(timeA[0]) * 60 * 1000 + parseFloat(timeA[1]) * 1000;
		return time;
	};
	
	this.insertEmbedRowAfterBiggerTime = function (time, content)
	{
		var va = this;
		var foundRow = null;
		$("#swfsEmbeded > tbody > tr").each(function (){
			var rowTimeStr = $(this).children().eq(1).text();
			var rowTime = va.timeStrToMs(rowTimeStr);
			if(time > rowTime){
				foundRow = $(this);
				return;
			}
		});
		
		if(foundRow == content){
			return;
		}
		if(!foundRow){
			$("#swfsEmbeded > tbody").prepend(content);
			return;
			/*
			foundRow = $("#swfsEmbeded > tbody > tr:first");
			if(!foundRow || !foundRow.length){
				$("#swfsEmbeded > tbody").append(content);
			}
			else{
				foundRow.before(content);
			}
			//*/
		}
		else{
			foundRow.after(content);
		}
	};
	
	
	this.prepareSwfFilesTable = function ()
	{
		var va = this;
		
		// Zavreni seznamu dotaznikuu
		var questList = $(".hiddenList.questionnaires");
		$(".hiddenList.questionnaires .closeButt").unbind('click');
		$(".hiddenList.questionnaires .closeButt").click(function(){
			questList.hide();
			return false;
		});
		
		var addButtons = $("#swfsForEmbed .swfAddNew");
		
		addButtons.unbind('click');
		addButtons.click(function (){
			var time = Math.round(va.plainPlayer.getPlayhead());
			if(time < 0){
				return;
			}
			var file = this.name;
			
			va.setWorking(true);
			data = {time: time, file: file};
			if($(this).hasClass('addRating')){
				data.isAddRating = 1;
			}
			$.get(manipulateEmbedUrl, data, 
		    	function(data){
		    		var key = data;
		    		var timeStr = va.formatTime(time);
		            
		    		var html = 
						'<tr>\
			                <td>'+file+'</td>\
			                <td>'+timeStr+'</td>\
			                <td class="questName"></td>\
			                <td>\
				                <button class="swfAddQuestionnaire" name="'+key+'">Napojit dotazník</button>\
			                    <button class="swfMove" name="'+key+'">'+swfMoveButText+'</button>\
			                    <button class="swfRemove" name="'+key+'">'+swfRemoveButText+'</button>\
			                </td>\
			            </tr>';
		    		
		    		va.insertEmbedRowAfterBiggerTime(time, html);
	                va.prepareSwfEmbedsTable();
	                va.prepareSwfFilesTable();
	                va.setWorking(false);
		    	}, 'text');
			
            
		});
		
		// Pripojeni dotazniku
		var addQuestButtons = $("#swfsEmbeded .swfAddQuestionnaire");
		addQuestButtons.unbind('click');
		addQuestButtons.click(function (){
			var thisJq = $(this);
			var button = thisJq;
			var key = this.name;
			
			// Zobrazime nabidku dotaznikuu
			questList.show();
			questList.css({position:'absolute'});
			var pos = thisJq.position();
			questList.css({top: pos.top + thisJq.outerHeight() + 5, left: pos.left});
			
			//kliky na jednotlive dotazniky
			$("a:not(.closeButt)", questList).unbind('click');
			$("a:not(.closeButt)", questList).one('click', function(){
				va.setWorking(true);
				var name = $(this).text();
				var questId = $(this).attr('href');
				// Pro odstraneni pripojeni nevyplnujeme jmeno
				if(questId == 'none'){
					name = '';
				}
				$.get(manipulateEmbedUrl, {key: key, questionnaire: questId}, function(data){
					button.parents("tr:first").children("td.questName").text(name);
					va.setWorking(false);
					questList.hide();
				});
				return false;
			});
			return false;
		});
	};
	
	this.prepareSwfEmbedsTable = function ()
	{
		var va = this;
		
		var moveButtons = $("#swfsEmbeded .swfMove");
		var removeButtons = $("#swfsEmbeded .swfRemove");
		
		
		moveButtons.click(function (){
			var time = Math.round(va.plainPlayer.getPlayhead());
			if(time < 0){
				return;
			}
			var key = this.name;
			
			var timeStr = va.formatTime(time);
			
			va.setWorking(true);
			$.post(manipulateEmbedUrl+'?time='+time+'&key='+key, {}, 
		    	function(data){
					va.setWorking(false);
		    	}, 'text');
			
			$(this).parent().parent().children().eq(1).text(timeStr);
			var elem = $(this).parent().parent();
			
			va.insertEmbedRowAfterBiggerTime(time, elem);
			
			//$(this).parent().parent().empty();
		});
		
		
		
		removeButtons.click(function (){
			var key = this.name;
			
			va.setWorking(true);
			$.post(manipulateEmbedUrl+'?key='+key, {}, 
		    	function(data){
					va.setWorking(false);
		    	}, 'text');
			
			$(this).parent().parent().remove();
		});
	};
	
	this.forOnload = function ()
	{
		this.plainPlayer = document.getElementById(this.plainPlayerElemId);
		this.fullPlayer = document.getElementById(this.fullPlayerElemId);
		
		va = this;
		
		$("#getPreviewPict").click(function() {
			
			if(!confirm(setPreviewPictureConfirmMessage)){
				return false;
			}
			
			var pos = va.plainPlayer.getPlayhead();
			if(pos < 0){
				return;
			}
			if(pos < 0){pos = 0;}
			
		    $.post(setPreviewPictureUrl+'?pos='+pos+'&anticache=' + new Date().getTime(), {}, 
		    	function(data){
		    		//alert(data);
		    	}, 'text');
		    return false;
		});
		
		$("#setPlainPlayerWide").click(function() {
			$("#"+va.plainPlayerElemId).parent().css('width', '100%');
			$("#"+va.plainPlayerElemId).css('width', '100%');

			$(this).hide();
			return false;
		});
		
		$("#reloadCfgBtn").click(function() {
			//alert(xmlConfigUrl);
			//va.fullPlayer.setPlayhead(7);
			va.fullPlayer.loadConfig(xmlConfigUrl+'?' + new Date().getTime());
			return false;
		});
		
		this.prepareSwfFilesTable();
		this.prepareSwfEmbedsTable();
	};
};

videoadminObj = new videoadmin();
addLoadEvent('videoadminObj.forOnload');
