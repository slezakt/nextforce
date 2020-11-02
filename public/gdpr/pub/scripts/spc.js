/*
 * Obsluha SPC baneru, kdy je potreba zobrazit pribalovy letak pri hoveru pres baner
 * HTML sablona baneru:
 * <div class="spc"><a class="spc-banner"><img src="" alt="" /></a><div style="display:none;" class="spc-box">Content</div></div>
 */
var SPC = function (spc) {

    var stimer = null;
    var htimer = null;

    var content = spc.children('.spc-box');
    var banner = spc.children('.spc-banner');

    //defaultni vyska a sirka lightboxu
    var contentBoxHeight = 400;
    var contentBoxWidth = 980;

    spc.children().hover(
            function () {
                clearTimeout(htimer);
                stimer = setTimeout(function () {
                    if (!content.is(':visible')) {
                        showSPC();
                    }
                }, 750);

            },
            function () {
                clearTimeout(stimer);
                htimer = setTimeout(function () {
                    hideSPC();
                }, 500);
            }
    );

    //zobrazení SPC
    var showSPC = function () {
        content.appendTo('body');
        content.show();

        content.css('left', function () {
            var left = ($(window).width() - $(this).outerWidth()) / 2;
            if (left > 0) {
                return left;
            } else {
                return 0;
            }
        });

        content.css('top', function () {
            var bannerHeight = 0;
            if (banner.outerHeight() == 0) {
                bannerHeight = banner.parent().outerHeight();
            } else {
                bannerHeight = banner.outerHeight();
            }
            var top = (banner.offset().top + (bannerHeight / 2)) - ($(this).outerHeight() / 2);
            var bottom = $('body').height() - (top + $(this).outerHeight());
            if (bottom > 0) {
                return top;
            } else {
                return top + bottom;
            }

        });
    };

    var hideSPC = function () {
        if (content.is(':visible')) {
            content.hide();
        }
    };

    var setStyleSPC = function () {

        //priradime styly tak aby neprepisovali inline styly
        if (!content.get(0).style['font-size']) {
            content.css('font-size', '10px');
        }

        if (!content.get(0).style['background-color']) {
            content.css('background-color', '#fff');
        }

        if (!content.get(0).style['border']) {
            content.css('border', 'solid 2px #000');
        }

        if (!content.get(0).style['width']) {
            content.css('width', contentBoxWidth);
        }

        if (!content.get(0).style['height']) {
            content.css('height', contentBoxHeight);
        }

        if (!content.get(0).style['padding']) {
            content.css('padding', '20px');
        }

        if (!content.get(0).style['text-align']) {
            content.css('text-align', 'left');
        }

        content.css({
            display: 'none',
            'overflow-y': 'scroll',
            position: 'absolute',
            'z-index': 1000,
            '-webkit-box-shadow': '0px 0px 10px 0px rgba(0,0,0,0.5)',
            '-moz-box-shadow': '0px 0px 10px 0px rgba(0,0,0,0.5)',
            'box-shadow': '0px 0px 10px 0px rgba(0,0,0,0.5)'
        });
    };

    setStyleSPC();

};

SPC.onload = function () {
    $('.spc').each(function () {
        SPC($(this));
    });
};

addLoadEvent('SPC.onload');
