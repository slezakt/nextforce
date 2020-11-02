// querySelector, querySelectorAll
(function (){

    if (document.createStyleSheet) {
        var style = document.createStyleSheet("");
    } else {
        var style = document.createElement ("style");
        head = document.getElementsByTagName ("head")[0];
        head.appendChild(style);
    }

    var select = function (selector, maxCount) {
        var
            all = document.all,
            l = all.length,
            i,
            resultSet = [];

        style.addRule(selector, "foo:bar");
        for (i = 0; i < l; i += 1) {
            if (all[i].currentStyle.foo === "bar") {
                resultSet.push(all[i]);
                if (resultSet.length > maxCount) {
                    break;
                }
            }
        }
        style.removeRule(0);
        return resultSet;
    };

    //  be rly sure not to destroy a thing!
    if (document.querySelectorAll || document.querySelector) {
        return;
    } else {

        document.querySelectorAll = function (selector) {
            return select(selector, Infinity);
        };
        document.querySelector = function (selector) {
            return select(selector, 1)[0] || null;
        };
    }
}());

function newWin(addr){
    window.open(addr, 'MP','');
    
    return false;
}

function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+escape(value)+expires+"; path=/";
}

function readCookie(name) {
    name = name.replace(/([.\\+\-*:\/?!|^${}()\[\]])/g, '\\$1');
    var re = new RegExp('[; ]'+name+'=([^\\s;]*)');
    var match = (' '+document.cookie).match(re);
    if (name && match) return unescape(match[1]);
    return '';
}

function eraseCookie(name) {
	createCookie(name,"",-1);
}



function externalLinks() {
 	if (!document.getElementsByTagName) return;
	var anchors = document.getElementsByTagName("a");
	for (var i=0; i<anchors.length; i++) {
		var anchor = anchors[i];
			if (anchor.getAttribute("href") &&
				anchor.getAttribute("rel") == "external")
				anchor.target = "_blank";
	}
}

addLoadEvent('externalLinks');