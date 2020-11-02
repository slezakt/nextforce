InTemplate.prototype.__progressBar = function () {

    var template = this;

    $(template.presentation).on('slideOn', function (e, prevSlide, currentSlide) {

        var progressBar = null;

        //add progress
        if (!template.presentation.find('.progress-bar').length) {
            progressBar = $('<div />');
            progressBar.addClass('progress-bar');
            progressBar.appendTo(template.presentation);
            progressBar.html('<div class="progress-bar-line"></div>');
        } else {
            progressBar = $('.progress-bar');
        }


        progressBar.children('.progress-bar-line').css({
            'width': ((currentSlide.num / template.numberSlides) * 100) + '%',
            transition: 'width 1s ease-in-out'
        });

    });

}