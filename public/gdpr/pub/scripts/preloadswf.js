var preloadswf =
{
    doneCookieName: 'preloadswf_doneSwfs',

    sandboxElemIdNum: 0,

    data: {},

    // DEPRECATED - pouzivame callback z preload swf
    checkDone: function(elemId){
        var elem = document.getElementById(elemId);
        if(elem.isSwfLoadDone){
            //alert('Here: '+elemId);
            if(elem.isSwfLoadDone()){
                clearInterval(this.data[elemId].interval);
                swfobject.removeSWF(elemId);

                cookie = readCookie(this.doneCookieName);
                if(cookie == 'null'){
                    var cookieWrite = this.data[elemId].source;
                }
                else{
                    var cookieWrite = cookie+'|'+this.data[elemId].source;
                }
                createCookie(this.doneCookieName, cookieWrite, 1000);
                //alert('Removed:'+elemId+' cookies:'+cookieWrite);
            }
        }
    },

    preloadDone: function(elemId){
        var elem = document.getElementById(elemId);

        cookie = readCookie(this.doneCookieName);
        if(cookie == 'null' || cookie == null){
            var cookieWrite = this.data[elemId].source;
        }
        else{
            var cookieWrite = cookie+'|'+this.data[elemId].source;
        }
        createCookie(this.doneCookieName, cookieWrite, 1000);
        //alert('Removed:'+elemId+' cookies:'+cookieWrite);
    },

    load: function(source)
    {
        var sandbox = document.getElementById('sandbox');

        if(!sandbox){
            return;
        }

        var newdiv = document.createElement('div');
        var newElemId = 'sandbox_div'+this.sandboxElemIdNum;
        newdiv.setAttribute('id', newElemId);
        sandbox.appendChild(newdiv);

        this.data[newElemId] = {
            //interval: setInterval('preloadswf.checkDone("'+newElemId+'");', 500), // jiz nepouzivame
            source: source
        };

        this.initialize(source, newElemId);


        this.sandboxElemIdNum++;
    },

    initialize: function(source, elemId)
    {
        var swfFilePath = source/*+'?'+new Date().getTime()*/;
        var prodInstall = 'pub/scripts/flashexpressInstall.swf';
        var flashVars =
        {
            // Zadavame kvlu PT Playeru - nepodarilo se vyresit, aby to slo bez zadani
            // prazdne nastaveni zpusobuje nacteni cele aktualni stranky (pomale)
            config: 'pub/',
            listener: 'pub/'
        };
        var params =
        {
            wmode: 'transparent',
            quality: 'high',
            play: 'false',
            loop: 'false',
            allowfullscreen: 'false',
            allowScriptAccess: 'always',
            pluginspage: 'http://www.adobe.com/go/getflashplayer'
        };

        // Callback pro zruseni nacitani
        elemLoadedFnc = function(event){
            if(event.success){
                preloadswf.preloadDone(event.id);
            }
            //swfobject.removeSWF(elemId); // Neodebirame, protoze flashe pouzivaji autoloader
        }

        swfobject.embedSWF(swfFilePath, elemId, '1px', '1px', '9.0.115', prodInstall, flashVars, params, null, elemLoadedFnc);
    }

}