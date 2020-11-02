function getElementXPath(elt) {
    var path = "";
    for (; elt && elt.nodeType == 1; elt = elt.parentNode) {
        idx = getElementIdx(elt);
        xname = elt.tagName;
        xid = (elt.id ? '#' + elt.id : '');
        if (elt.className) {            
            arr = elt.className.split(' ');
            arr.sort();
            xclass = '.' + arr.join('.')
        } else {
            xclass = '';
        }
        // nezapisujeme poradi divu, kvuli moznosti zprehazet jejich poradi a  
        // pritom mit moznost trackovat "logicky stejnou" oblast 
        //if (idx > 1) xname += "[" + idx + "]";
        path = "/" + xname + xid + xclass + path;
    }    
    return path;   
}

function getElementIdx(elt) {
    var count = 1;
    for (var sib = elt.previousSibling; sib ; sib = sib.previousSibling) {
        if(sib.nodeType == 1 && sib.tagName == elt.tagName) count++
    }    
    return count;
}

function watchLinks() {
    if (!document.getElementsByTagName) return;
    var anchors = document.getElementsByTagName("a");
    var links = '';
    for (var i = 0; i < anchors.length; i++) {
        var anchor = anchors[i];        
        if (anchor.getAttribute("href") && anchor.onclick == null) {
            anchor.onclick = function(e) {
                createCookie('link_xpath_cookie',getElementXPath(this),1);
                // v pripade onclick v html tohle nefunguje a povesi se dany onclick na vsechny elementy
                //if (typeof orig_handler == 'function' && orig_handler != null) {                    
                //    orig_handler.call(e);
                //}
            }
        }
    }
}

addLoadEvent('watchLinks');
