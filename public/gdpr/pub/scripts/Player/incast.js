var inCast =
{
	
};


var PlayersReporting = function() { };

    PlayersReporting.anticache = function ()
    {
        return '';
        return '?' + new Date().getTime();
    }
    
    PlayersReporting.prototype.reportUrl = null;
    PlayersReporting.prototype.deliverEvents = true;
    
    PlayersReporting.prototype.deliverEvent = function(event)
    {
        var target = document.getElementById(event.target);
        if(target && this.deliverEvents){
            target.processEvent(event);
        }
        else{
            this.reportEvent(event);
        }
    };
    
    PlayersReporting.prototype.reportEvent = function(event)
    {
        var position = null;
        var action = null;
        var embedName = null;
        
        if(!this.reportUrl){
            return;
        }
        switch(event.type){
            case 'player':
                position = event.playhead;
                action = event.action;
                break;
            case 'fullscreen':
                position = event.playhead;
                action = 'fullscreen_'+event.action;
                break;
            case 'embed':
                position = event.playhead;
                action = 'embed_'+event.action;
                embedName = event.data;
                break;
            case 'seek':
                position = event.playhead;
                action = 'seek';
                break;
            case 'slide':
                position = event.data;
                action = 'slide';
                break;
            case 'link':
                if(event.action == 'forward'){
                    // Nahodime dialog preposlani a vyskocime z obsluhy udalosti
                    // Aby to fungovalo, musi ve strance nekde existovat odkaz na preposlat kolegovi,
                    // na kterem je naveseny handler, klidne i skryty
                    // !! POZOR !! POUZIVAME JQUERY...
                    $("a.sendToFriend").eq(0).click();
                    return;
                }
                break;  
            default:
                break;
        }
        
        
        if(position && action){
            if(embedName){
                var embedNameUrl = '&embedfile='+escape(embedName);
            }
            else{
                var embedNameUrl = '';
            }
            
            $.post(this.reportUrl + '/?action='+action+'&position='+position+embedNameUrl, {}, 
                    function(data){});
        }
    };





var AVPlayerClass = function()
{
    this.playersReporting = new PlayersReporting();
}
	AVPlayerClass.prototype.elemId = null;
	AVPlayerClass.prototype.xmlConf = null;
	AVPlayerClass.prototype.source = null;
	AVPlayerClass.prototype.report = null;
	AVPlayerClass.prototype.autoPlay = false;
	AVPlayerClass.prototype.buffer = true;
	AVPlayerClass.prototype.userId = null;
	AVPlayerClass.prototype.self = null; // Cesta pro volani teto instance
	
	AVPlayerClass.prototype.autoplayManual = function(){
		inCast['autoplayManualInterval'] = 
			setInterval('inCast.obj_'+this.elemId+'.autoplayManualIntervaler()', 500);
	};
	
	AVPlayerClass.prototype.autoplayManualIntervaler = function(){
		var elem = document.getElementById(this.elemId);
		if(elem.commandPlay){
			elem.commandPlay();
		}
	};
	
	AVPlayerClass.prototype.initialize = function()
	{
	    this.playersReporting.reportUrl = this.report;
		var swfFilePath = this.source + PlayersReporting.anticache();
		var prodInstall = 'pub/scripts/flashexpressInstall.swf';
		var flashVars =
		{
			listener: this.self + '.playersReporting.deliverEvent',
			buffer: this.buffer,
			autoPlay: this.autoPlay,
			config: this.xmlConf,
			report: this.report,
			userId: this.userId,
			skin: this.skin
		};
		var params =
		{
			wmode: 'transparent',
			quality: 'high',
			play: 'true',
			loop: 'false',
			allowfullscreen: 'true',
			allowScriptAccess: 'always',
			pluginspage: 'http://www.adobe.com/go/getflashplayer'
		};
		
		swfobject.embedSWF(swfFilePath, this.elemId, '100%', '100%', '9.0.115', prodInstall, flashVars, params, null);
	};
	
	AVPlayerClass.prototype.release = function()
	{
		var player = document.getElementById(this.elemId);
		if(!player || !player.releasePlayer){
			return;
		}
		//player.releasePlayer();
		//swfobject.removeSWF(this.elemId);
	};
	
	
	


var PTPlayerClass = function ()
{
    this.playersReporting = new PlayersReporting();
    
	this.elemId = null;
	this.xmlConf = null;
	this.source = null;
	this.userId = null;
	
	this.initialize = function()
	{
	    this.playersReporting.reportUrl = this.report;
		var swfFilePath = this.source + PlayersReporting.anticache();
		var prodInstall = 'pub/scripts/flashexpressInstall.swf';
		var flashVars =
		{
			listener: this.self + '.playersReporting.deliverEvent',
			config: this.xmlConf,
			userId: this.userId,
			skin: this.skin
		};
		var params =
		{
			wmode: 'transparent',
			quality: 'high',
			play: 'true',
			loop: 'false',
			allowfullscreen: 'true',
			allowScriptAccess: 'always',
			pluginspage: 'http://www.adobe.com/go/getflashplayer'
		};
		
		swfobject.embedSWF(swfFilePath, this.elemId, '100%', '100%', '9.0.115', prodInstall, flashVars, params, null);
	};
	
	this.release = function()
	{
		var player = document.getElementById(this.elemId);
		if(!player || !player.releasePlayer){
			return;
		}
		//player.releasePlayer();
		//swfobject.removeSWF(this.elemId);
	};
	
};

