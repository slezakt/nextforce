InTemplate.prototype.__swipeupdown = function () {

    var template = this;
    template.lastScrollPosition = 0;
    template.lastPageYPosition = 0;

    if (template.options.mobileGestures) {

        $(template.presentation).on('scrollstart', function (event) {
            template.lastScrollPosition = $(window).scrollTop();
            template.lastPageYPosition = event.pageY;
        });

        $(template.presentation).on('scrollstop', function (event) {

            var currentScrollPosition = $(window).scrollTop();
            if ((template.lastScrollPosition <  currentScrollPosition) &&
                (currentScrollPosition + window.innerHeight - $(document).height() >= 0) && 
                Math.abs(template.lastPageYPosition - event.pageY) > 50) {
                $(this).trigger('swipeup')
            } else if ((template.lastScrollPosition >  currentScrollPosition) &&
                (currentScrollPosition <= 0) && 
                Math.abs(template.lastPageYPosition - event.pageY) > 50) {
                $(this).trigger('swipedown')
            }

        });

        template.presentation.on('swipeup', function () {

            if (template.options.vertical) {
                template.nextSlide();
            } else {
                template.nextSubSlide();
            }

        });

        template.presentation.on('swipedown', function () {

            if (template.options.vertical) {
                template.prevSlide();
            } else {
                template.prevSubSlide();
            }

        });

    }



}