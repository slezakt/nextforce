var xmlhttpx=false;

/*@cc_on @*/
/*@if (@_jscript_version >= 5)
// JScript gives us Conditional compilation, we can cope with old IE versions.
// and security blocked creation of the objects.
try {
    xmlhttpx = new ActiveXObject("Msxml2.XmlHttp.4.0");

} catch (e) {

  try {
   xmlhttpx = new ActiveXObject("Microsoft.XMLHTTP");
  } catch (E) {
   xmlhttpx = false;
  }
 }
@end @*/

if (!xmlhttpx && typeof XMLHttpRequest!='undefined') {
    try {
        xmlhttpx = new XMLHttpRequest();
    } catch (e) {
        xmlhttpx=false;
    }
}
if (!xmlhttpx && window.createRequest) {
    try {
        xmlhttpx = window.createRequest();
    } catch (e) {
        xmlhttpx=false;
    }
}

function getXMLHTTP(adr,func,id){
 xmlhttpx.open('GET', adr, true);
 //alert(adr);
 xmlhttpx.onreadystatechange=function() {
  if (xmlhttpx.readyState==4) {
   //alert(xmlhttpx.responseText);
   //eval(func+'(xmlhttpx.responseXML,id)');
  }
 }
 xmlhttpx.send(null)
}

// Provedeme postupne detekci jednotlivych funkcionalit
// var uzivatelove pocitaci

// Cookies enabled
var cookies=(navigator.cookieEnabled)? 1 : 0;

// Flash
if(FlashDetect.installed){
    var flmajor = FlashDetect.major;
    var flminor = FlashDetect.minor;
    if(flmajor < 0){
        //jedna se o chybu, flash je false
        var flash = 'false';
    }
    else{
        //oprava vadne minor verze
        if(flminor < 0){
            flminor = 0;
        }
        var flash = flmajor+'_'+flminor;
    }
}
else{
    var flash = 'false';
}

// Rozliseni
var res = screen.width + 'x' + screen.height;

// Timezone - UTC offset v minutach
var rightNow = new Date();
var temp = rightNow.toGMTString();
var rightNowGmtNoOffset = new Date(temp.substring(0, temp.lastIndexOf(" ")));
var rightNow = new Date(temp); // Prevedeme z temp, kde je presnost na vteriny, aby nedochazelo k nepresnostem a vysly hezky cele minuty offsetu
var utc_offset = (rightNow - rightNowGmtNoOffset) / (1000 * 60);
utc_offset = Math.round(utc_offset);


var addr = base_url+'/userbrwsrinfo/a/js/1/co/'+cookies+'/fl/'+flash+'/res/'+res+'/utcoffset/'+utc_offset+'/';
// Odesleme pozadavek obsahujici vysledek setreni
getXMLHTTP(addr);

//alert(addr);
