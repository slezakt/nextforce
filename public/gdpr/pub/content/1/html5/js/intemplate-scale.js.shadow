InTemplate.prototype.__scale = function () {

    var template = this;

    var viewport = $('head').children('[name="viewport"]');

    if (viewport.length > 0) {
        viewport.detach();
    } else {
        viewport = $('<meta />');
        viewport.attr('name', 'viewport');
    }

    var viewportInit = function () {

        if (window.orientation == 0 || window.orientation == 180 || window.orientation == "portrait") {

            viewport.attr('content', 'width=device-width, initial-scale=1, shrink-to-fit=no');

        } else {

            var landscapeWidth = Math.max(window.screen.width,window.screen.height);

            if (landscapeWidth < template.options.viewportWidth) {
                var initScale = (landscapeWidth  / template.options.viewportWidth).toFixed(1);
            } else {
                var initScale = 1;
            } 

            viewport.attr('content', 'width=device-width, initial-scale=' + initScale + ', shrink-to-fit=no');

        }

        viewport.prependTo('head');

    };

    viewportInit();

    $(window).on('orientationchange', function (e) {
        viewportInit();
    });





}
