/**
 * metoda nahrazuje mezery pred jednopismeny predlozkami a spojkami za nedelitelne
 * @returns {undefined}
 */
var nbsp = function() {
    
    //jestlize neni element #clanek metodu ukoncime
    if(!document.getElementById('clanek')) {
        return;
    }
    
    var clanek = document.getElementById('clanek');
    
    var odst = clanek.getElementsByTagName('p');

    var reg_predlozky = new RegExp('((\\s|&nbsp;)[vszkouai])\\s', 'gi');
    var reg_jednotky = new RegExp('\\s[%]', 'g');

    for (var i=0;i<odst.length;i++) { 
        odst[i].innerHTML = odst[i].innerHTML
                .replace(reg_predlozky, function(m, m1){return m1+'&nbsp;';})
                .replace(reg_predlozky, function(m, m1){return m1+'&nbsp;';}) // Provadime 2 x kvuli dvema vyskytum vedle sebe 
                .replace(reg_jednotky, function(m){return '&nbsp;'+m.trim();});
    }    

};

addLoadEvent('nbsp');

