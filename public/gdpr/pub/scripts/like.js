/**
 * Tento soubor nacitame pomoci Ibulletin_Js jako telo funkce pro onclick na like
 */
// Pripravime xmhlhttp pro cross browser
var ua = navigator.userAgent.toLowerCase();
if (!window.ActiveXObject)
 xmlhttp = new XMLHttpRequest();
else if (ua.indexOf('msie 5') == -1)
 xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
else
 xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");

xmlhttp.open("GET", rating_savePageRatingUrl + '/?rating=1&is_like=1');
xmlhttp.onreadystatechange = function(){
	if(xmlhttp.readyState == 4){
		var likeTextNode = xmlhttp.responseXML.getElementsByTagName('likeTextCDATA')[0]
		var likeHtml = likeTextNode.text || likeTextNode.textContent;
		var likeElem = document.getElementById("like");
		likeElem.innerHTML = likeHtml;
		//alert(likeHtml);
		return false;
	}
}
xmlhttp.send(null);
return false;