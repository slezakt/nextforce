var indetail = new Indetail(null, true, true);

var InTemplate = function (presentation, options) {

    this.presentation = presentation;
    this.currentSlide = null;
    this.lastSlide = null;
    this.slidesContainer = null;
    this.currentPopup = null;
    this.numberSlides = null;
    this.plugins = [];

    this._historyOff = false;
    this._subSlideSerie = 1000;
    this._popupSerie = 10000;
    this._allowControl = true;
    this.baseUrl = ".";

    this.indetail = null;
    this.answerPoints = {};
    this.answerPointsSum = 0;

    this.answerAgain = function (form) {
        if (form.attr('data-answer-again') === undefined) {
            return this.options.answerAgain;
        } else {
            return form.data('answer-again');
        }
    }

    // Defaults settings for slide
    this.slideDefaults = {
        leftNav: true,
        rightNav: true,
        upNav: true,
        downNav: true,
        visited: false,
        validFrom: null,
        showHeader: true,
        showFooter: true,
        setData: function (attr, val) {
            this[attr] = val;
            this.el.data(attr, val);
        }
    };

    // Defaults settings for popups
    this.popupDefaults = {
        modal: false,
        closeByEsc: true,
        closeByBgClick: true,
        showOverlay: true,
        validFrom: null,
        setData: function (attr, val) {
            this[attr] = val;
            this.el.data(attr, val);
        }
    };

    // Defaults setting for presentation
    this.defaults = {
        height: null,
        width: '100%',
        slidesFolder: 'slides',
        popupsFolder: 'popups',
        keyControl: true,
        numberSlides: null,
        mobileGestures: true,
        currentDate: null,
        history: true,
        rightArrow: '<a class="control next" href="#next"><span class="label">DALŠÍ</span> <span class="icon icon-navigate_next"></span></a>',
        leftArrow: '<a class="control prev" href="#prev"><span class="icon icon-navigate_before"></span> <span class="label">PŘEDCHOZÍ</span></a>',
        upArrow: '<a class="control prevsub" href="#prevsub"><span class="icon-expand_less"></span></a>',
        downArrow: '<a class="control nextsub" href="#nextsub"><span class="icon-expand_more"></a>',
        popupCloseButton: '<a class="close-popup" href="#"><span class="icon-close"></span></a>',
        allowIndetailLib: true,
        numberPreloadSlides: 1,
        errorSlide: true,
        mode: 'normal',
        slides: [],
        popups: [],
        slideAnimation: 'slide',
        slideAnimationDuration: 1,
        slideAnimationScenario: {},
        errorID: 9999,
        answers: {},
        userData: {},
        answerAgain: false,
        avgPoints: 0,
        maxpoints: 0,
        loadServerVars: true,
        vertical: false,
        scrollToTop: false,
        alwaysSendForm: true,
        checkboxStyle: 'square',
        checkboxColor: 'blue',
        orientationOverlay: '<span><img src="/gdpr/pub/content/1/html5/img/mobile-rotate.png" style="max-width: 95%; max-height: 95%; margin: 0 auto;"><br><br>Otočte prosím Vaše zařízení vodorovně.</span>',
        viewportWidth: 'device-width'
    };

    // Defaults slide animation
    var defaultsSlideAnimationScenario = {
        slide: {
            right: { in: "fadeInRight", out: 'fadeOutLeft' },
            left: { in: "fadeInLeft", out: 'fadeOutRight' },
            up: { in: "fadeInUp", out: 'fadeOutUp' },
            down: { in: "fadeInDown", out: 'fadeOutDown' }
        },
        fade: {
            right: { in: "fadeIn", out: 'fadeOut' },
            left: { in: "fadeIn", out: 'fadeOut' },
            up: { in: "fadeIn", out: 'fadeOut' },
            down: { in: "fadeIn", out: 'fadeOut' }
        },
        roll: {
            right: { in: "rollIn", out: 'rollOut' },
            left: { in: "rollIn", out: 'rollOut' },
            up: { in: "rollIn", out: 'rollOut' },
            down: { in: "rollIn", out: 'rollOut' }
        },
        zoom: {
            right: { in: "zoomIn", out: 'zoomOut' },
            left: { in: "zoomIn", out: 'zoomOut' },
            up: { in: "zoomIn", out: 'zoomOut' },
            down: { in: "zoomIn", out: 'zoomOut' }
        },
        slideZoom: {
            right: { in: "zoomInRight", out: 'zoomOutLeft' },
            left: { in: "zoomInLeft", out: 'zoomOutRight' },
            up: { in: "zoomInUp", out: 'zoomOutUp' },
            down: { in: "zoomInDown", out: 'zoomOutDown' }
        },
        slideRotate: {
            right: { in: "rotateInDownRight", out: 'fadeOut' },
            left: { in: "rotateInDownLeft", out: 'fadeOut' },
            up: { in: "rotateInUpLeft", out: 'fadeOut' },
            down: { in: "rotateInDownLeft", out: 'fadeOut' }
        },
        rotate: {
            right: { in: "rotateIn", out: 'rotateOut' },
            left: { in: "rotateIn", out: 'rotateOut' },
            up: { in: "rotateIn", out: 'rotateOut' },
            down: { in: "rotateIn", out: 'rotateOut' }
        }
    };

    this.options = $.extend({}, this.defaults, options, this.presentation.data());
    this.slideAnimationScenario = $.extend(defaultsSlideAnimationScenario, this.options.slideAnimationScenario);

    // Set current date
    if (!this.options.currentDate) {
        d = new Date();
        this.options.currentDate = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
    }

    this.numberSlides = this.options.numberSlides;
    this.slidesContainer = this.presentation.children('section');
    this.init();

};

// Set previous slide
InTemplate.prototype.prevSlide = function () {
    if (this.currentSlide.num > 1) {
        this.setSlide(this.currentSlide.num - 1);
    }
};

// Set next slide
InTemplate.prototype.nextSlide = function () {
    if (this.currentSlide.num < this.numberSlides) {
        this.setSlide(parseInt(this.currentSlide.num) + 1);
    }
};

// Set previous subSlide
InTemplate.prototype.prevSubSlide = function () {
    this.setSlide(this.currentSlide.num, (this.currentSlide.subNum > 1) ? this.currentSlide.subNum - 1 : 0);
};

// Set next subSlide
InTemplate.prototype.nextSubSlide = function () {
    if (this.currentSlide.subNum < this.currentSlide.slides.length) {
        this.setSlide(this.currentSlide.num, parseInt(this.currentSlide.subNum) + 1);
    }
};

// Find object by key in array
InTemplate.prototype.findByKey = function (arr, key, val) {

    if (arr === undefined) {
        return null;
    }

    var r = arr.filter(function (o) {
        if (o[key] == val) {
            return o;
        }
    });

    if (r[0] !== undefined) {
        return r[0];
    }

    return null;
}

InTemplate.prototype.getSlide = function (slideNum, subSlideNum) {

    if (slideNum === undefined) {
        slideNum = this.currentSlide.num;
    }

    if (subSlideNum === undefined) {
        subSlideNum = 0;
    }

    var coord = this.getSlideCoord(slideNum, subSlideNum);
    var el = this.slidesContainer.children('[data-coord="' + coord + '"]');

    if (subSlideNum) {

        var parentSlide = this.getSlide(slideNum);

        return $.extend({
            target: slideNum, source: coord + '.html', id: (slideNum * this._subSlideSerie) + subSlideNum, num: slideNum,
            subNum: subSlideNum, coord: coord, el: el
        }, this.slideDefaults,
            this.findByKey(parentSlide.slides, 'target', subSlideNum), el.data(), { slides: parentSlide.slides });

    } else if (slideNum) {

        return $.extend({
            target: slideNum, source: coord + '.html', slides: [], id: slideNum, num: slideNum, subNum: subSlideNum,
            coord: coord, el: el
        }, this.slideDefaults, this.findByKey(this.options.slides, 'target', slideNum), el.data());

    } else {
        return {};
    }

}

InTemplate.prototype.getPopup = function (name) {

    if (name === undefined) {
        name = this.currentPopup.name;
    }

    var el = this.presentation.find('.popups #popup-' + name).children('.popup');

    if (!el) {
        return {};
    }

    return $.extend({
        id: el.parent().index() + this._popupSerie, el: el, name: name
    }, this.popupDefaults, this.findByKey(this.options.popups, 'name', name),
        el.data());

}

InTemplate.prototype.getSlideCoord = function (slideNum, subSlideNum) {

    if (subSlideNum) {
        return slideNum + '_' + subSlideNum;
    } else {
        return slideNum;
    }

}

InTemplate.prototype.getSlideByCoord = function (coord) {

    var slides = coord.split('_');

    var slideNum = slides[0];
    var subSlideNum = 0;

    if (typeof slides[1] != "undefined") {
        subSlideNum = slides[1];
    }

    return { subSlideNum: subSlideNum, slideNum: slideNum };

}

InTemplate.prototype.preloadSlide = function (preloadNum) {

    var template = this;

    if (preloadNum === undefined && template.currentSlide) {
        preloadNum = (template.currentSlide.num - template.options.numberPreloadSlides) ? (template.currentSlide.num - template.options.numberPreloadSlides) : 1;
    } else if (preloadNum === undefined) {
        return;
    }

    if (preloadNum > template.numberSlides || (preloadNum > template.currentSlide.num + template.options.numberPreloadSlides)) {
        return;
    }

    if (template.slidesContainer.children('[data-coord="' + preloadNum + '"]').length > 0) {
        template.preloadSlide(preloadNum + 1);
        return;
    }

    this.loadSlide(preloadNum, 0, function () {
        template.preloadSlide(preloadNum + 1);
    });

}

InTemplate.prototype.preloadSubSlide = function (preloadSubNum) {

    var template = this;

    if (preloadSubNum === undefined && template.currentSlide) {
        preloadSubNum = (template.currentSlide.subNum - template.options.numberPreloadSlides) > 0 ? (template.currentSlide.subNum - template.options.numberPreloadSlides) : 1;
    } else if (preloadSubNum === undefined) {
        return;
    }

    var slide = template.getSlide(template.currentSlide.num, preloadSubNum);

    if (preloadSubNum > slide.slides.length || (preloadSubNum > template.currentSlide.subNum + template.options.numberPreloadSlides)) {
        return;
    }

    if (slide.el.length > 0) {
        template.preloadSubSlide(preloadSubNum + 1);
        return;
    }

    this.loadSlide(slide.num, preloadSubNum, function (data, error) {
        template.preloadSubSlide(preloadSubNum + 1);
    });

}

InTemplate.prototype.isSlideValid = function (slideNum, subSlideNum) {

    //tests possibility open slide
    if (this.options.mode == 'continuous' && slideNum > 1) {

        var prevSlideSettings = this.getSlide(slideNum - 1);

        if (!prevSlideSettings.visited) {
            return false;
        }
    }

    var slide = this.getSlide(slideNum, subSlideNum);

    if (slide.validFrom) {
        var currentDate = new Date(this.options.currentDate);
        var validDate = new Date(slide.validFrom);
        currentDate.setHours(0, 0, 0, 0);
        validDate.setHours(0, 0, 0, 0);
        if (currentDate < validDate) {
            return false;
        }
    }

    return true;
}

InTemplate.prototype.isFormValid = function (formData, el) {

    var template = this;

    var validated = true;
    var validationMessage = "";

    jQuery.each(formData, function (i, v) {

        var input = el.find('[name="' + i + '"]');

        //validation of elements

        //required
        input.each(function () {
            if ($(this).data('required') == 1 && !v) {
                $(this).focus();
                validationMessage = $(this).data('validation-message');
                validated = false;
            }
        });

        if (!validated) {
            return false;
        }

        var validator = input.data('validator');

        if (!v) {
            return true
        }

        if (validator == 'email') {
            var re = /^[\w-\.]+@[\w-\.]+\.[A-Za-z]{2,}$/i;
            if (!re.test(v)) {
                validated = false;
                input.focus();
                validationMessage = input.data('validation-message');
                return false;
            }
        }

        if ($(this).data('validator') == 'num') {
            var re = /^[0-9]+$/i;
            if (!re.test(v)) {
                validated = false;
                input.focus();
                validationMessage = input.data('validation-message');
                return false;
            }
        }

    });

    if (!validated) {
        swal(validationMessage, '', 'error');
    }

    return validated;

}

InTemplate.prototype.populateForm = function (slide, form) {

    if (!slide) {
        return;
    }

    var template = this;

    if (!form) {
        form = slide.el.find('form');
    }

    $.each(template.options.userData, function (i, val) {

        var re = new RegExp('^ua_', 'i');

        var input = form.find(':input[name = "ua_' + i.replace(re, '') + '"]');

        if (input.length > 0 && !template.options.userData['ua_' + i] && val) {
            if (input.is(':checkbox')) {
                input.prop('checked', true);
            } else {
                input.val(val);
            }

        }

        input.prop('readonly', !template.answerAgain(form));
        input.prop('disabled', !template.answerAgain(form));
    });

    var answers = {};

    answers = template.options.answers[slide.id];

    $.each(answers, function (i, val) {

        var input = form.find(':input[name = "' + i + '"]');
        input.prop('readonly', !template.answerAgain(form));
        input.prop('disabled', !template.answerAgain(form));

        if (!val) {
            return true;
        }

        if (input.length > 1) {

            input.each(function (x, v) {

                if ($(this).is(':checkbox')) {

                    $(this).prop('checked', function () {

                        if ($.isArray(val)) {
                            return ($.inArray($(this).val(), val) >= 0 || $.inArray(parseInt($(this).val()), val) >= 0);
                        } else {
                            return $(this).val() == val;
                        }

                    });

                    $(this).trigger('populated');

                } else if ($(this).is(':radio')) {

                    $(this).prop('checked', function () {
                        return $(this).val() == val;
                    });

                } else {
                    $(this).val(val[x].join('|'));
                }

            });

        } else {

            if (input.is(':checkbox') || input.is(':radio')) {

                input.prop('checked', function () {
                    return input.val() == val;
                });

            } else {

                input.val(val);

            }

            input.trigger('populated');

        }

        input.trigger('populated');

    });


}

InTemplate.prototype.serializeForm = function (form) {

    //prepare formData
    var elements = jQuery.unique(form.find(':input').not('[type="submit"], button').map(function () {
        return this.name;
    }));

    var serData = form.serializeArray();
    var formData = {};

    jQuery.each(elements, function (ei, ev) {

        var items = [];
        var input = form.find(':input[name="' + ev + '"]');

        jQuery.each(serData, function (si, sv) {
            if (ev == sv.name) {
                items.push(sv.value)
            }
        });

        if (items.length == 0) {

            if (input.length > 1 && !input.is(':radio')) {
                items = [];
            } else {
                items = null;
            }

        } else if (items.length == 1) {

            var _items = items[0].split('|');

            if (_items.length > 1) {
                items = _items;
            } else if (input.length == 1 || input.is(':radio')) {
                items = items[0];
            }

        }

        formData[ev] = items;

    });

    return formData;

}

InTemplate.prototype.processForm = function (form, container, validation, sendStats) {

    var template = this;

    if (template.options.answers[container.id] && !template.answerAgain(form)) {
        return true;
    }

    var formData = template.serializeForm(form);

    if (!validation) {
        validation = false;
    }

    if (validation && !template.isFormValid(formData, container.el)) {
        return false;
    }

    //Answers
    var rightAnswer = null;

    if (container.rightAnswer) {
        rightAnswer = container.rightAnswer.toString().replace(/\s/g, '').split(',');
    }

    var allAnswersRight = true;

    jQuery.each(formData, function (i, v) {

        if (i.indexOf('ua_') == 0) {

            var ua = i.replace('ua_', '');

            // Send user data
            if (template.options.allowIndetailLib && template.indetail) {
                template.indetail.setUserData(ua, formData[i]);
            }


        } else {

            // Send answers
            if (template.options.allowIndetailLib && template.indetail) {
                template.indetail.setQuestionAnswer(i, formData[i]);
            }

            if (rightAnswer && rightAnswer[i - 1] != formData[i]) {
                allAnswersRight = false;
            }
        }

    });

    // Save points
    if (rightAnswer && allAnswersRight) {
        template.answerPointsSum = template.answerPointsSum + 1;
        template.indetail.setPoints(template.answerPointsSum);
    }

    // Send data
    if (template.options.allowIndetailLib && template.indetail && sendStats) {
        console.log("Send data: " + container.id + " - " + container.id);
        template.indetail.sendData(container.id, container.id);
    }

    template.options.answers[container.id] = formData;

    container.el.trigger('answersSent', [formData, rightAnswer, container.id]);
    container.setData('formSubmited', true);

    var form = container.el.find('form');

    if (form.data('submit-message')) {
        swal({
            title: form.data('submit-message'),
            text: "Dialog se zavře po 4s",
            timer: 4000,
            type: 'success'
        }).catch(swal.noop);
    }

    template.populateForm(container, form);

    if (form.data('submit-transition')) {
        template.nextSlide();
    }

    return true;

}

InTemplate.prototype.isPopupValid = function (name) {

    var popup = this.getPopup(name);

    if (popup.validFrom) {
        var currentDate = new Date(this.options.currentDate);
        var validDate = new Date(popup.validFrom);
        currentDate.setHours(0, 0, 0, 0);
        validDate.setHours(0, 0, 0, 0);
        if (currentDate < validDate) {
            return false;
        }
    }

    return true;
}

//open slide by hash (anchor) in url
InTemplate.prototype.slideOpenByHash = function (hash) {

    var anchor = window.location.hash;
    var re = new RegExp('^#slide-([0-9_]+)$', 'i');
    var match = anchor.match(re);

    if (match && match.length > 0) {
        slide = this.getSlideByCoord(match[1]);
        this.setSlide(parseInt(slide.slideNum), parseInt(slide.subSlideNum));

    } else {
        this.setSlide(1);
    }

}

InTemplate.prototype.setSlide = function (slideNum, subSlideNum) {

    var template = this;

    if (subSlideNum === undefined) {
        subSlideNum = 0;
    }

    if (!template._allowControl || (this.currentSlide && this.currentSlide.num == slideNum
        && this.currentSlide.subNum == subSlideNum)) {
        return true;
    }

    this.loadSlide(slideNum, subSlideNum, function (slide, error) {

        if (slide) {

            if (!template.isSlideValid(slideNum, subSlideNum)) {
                template.presentation.trigger('error', ['Slide is not valid.']);
                return false;
            }

            var allowed = true;

            if (subSlideNum === undefined) {
                subSlideNum = 0;
            }

            //check the form, if it is valid, submitted ...
            if (template.currentSlide && template.currentSlide.el.find('form').length &&
                ((template.currentSlide.num < slideNum) || (template.currentSlide.num == slideNum
                    && template.currentSlide.subNum < subSlideNum) || template.options.alwaysSendForm)) {

                var form = template.currentSlide.el.find('form');
                var submitButton = form.find('.form-submit, [type="submit"], button');

                if (submitButton.length && form.data('required') && !template.currentSlide.formSubmited) {
                    swal('Formulář nebyl odeslán', '', 'warning');
                    return false;
                } else if (!submitButton.length) {

                    if (!template.processForm(form, template.currentSlide, true, false)) {
                        return false;
                    }

                }

            }

            template.lastSlide = template.currentSlide;
            template.currentSlide = template.getSlide(slideNum, subSlideNum);

            template.preloadSlide();
            template.preloadSubSlide();
            template.renderControl();

            template.transitionSlide();

            if (template.lastSlide) {
                template.presentation.trigger('slideOff', [template.lastSlide]);
            }
            template.presentation.trigger('slideOn', [template.lastSlide, template.currentSlide]);

        } else if (error) {
            template.presentation.trigger('error', [error]);
        }

    })
}

InTemplate.prototype.loadSlide = function (slideNum, subSlideNum, callback) {

    var template = this;

    var slide = template.getSlide(slideNum, subSlideNum);

    if (template.slidesContainer.children('[data-coord="' + slide.coord + '"]').length > 0) {
        return callback($('[data-coord="' + slide.coord + '"]'), null);
    }

    $.ajax({
        url: template.baseUrl + '/' + template.options.slidesFolder + "/" + slide.source + "?" + Date.now(),
        type: 'GET',
        success: function (data) {
            var d = $(data).filter('.slide');

            var el = template.appendSlide(d, slide.coord);

            slide = template.getSlide(slideNum, subSlideNum);

            template.preloadPopup();

            template.populateForm(slide);

            return callback(el, null);
        },
        error: function (data) {
            return callback(null, "Slide does not exists");
        }
    });

};

InTemplate.prototype.transitionSlide = function () {

    var template = this;

    var lastSlide = template.lastSlide;
    var currentSlide = template.currentSlide;

    currentSlide.el.show(0);

    if (template.options.scrollToTop) {
        window.scrollTo(0, 0);
    }

    if (lastSlide && (currentSlide.num != lastSlide.num
        || currentSlide.subNum != template.lastSlide.subNum)) {

        var direction = null;

        if (lastSlide.num < currentSlide.num) {

            if (template.options.vertical) {
                direction = "up";
            } else {
                direction = "right";
            }

        } else if (lastSlide.num > currentSlide.num) {

            if (template.options.vertical) {
                direction = "down";
            } else {
                direction = "left";
            }

        } else if (lastSlide.subNum > currentSlide.subNum) {

            if (template.options.vertical) {
                direction = "left";
            } else {
                direction = "down";
            }

        } else if (lastSlide.subNum < currentSlide.subNum) {

            if (template.options.vertical) {
                direction = "right";
            } else {
                direction = "up";
            }


        } else {
            if (template.options.vertical) {
                direction = "up";
            } else {
                direction = "right";
            }
        }

        currentSlide.el.removeClass(currentSlide.el.data('animation'));
        lastSlide.el.removeClass(lastSlide.el.data('animation'));

        currentSlide.el.css('animation-duration', template.options.slideAnimationDuration + 's');
        lastSlide.el.css('animation-duration', template.options.slideAnimationDuration + 's');

        animationIn = template.slideAnimationScenario[template.options.slideAnimation][direction].in;
        animationOut = template.slideAnimationScenario[template.options.slideAnimation][direction].out;

        currentSlide.el.addClass('animated ' + animationIn);
        currentSlide.el.data('animation', animationIn)

        lastSlide.el.addClass('animated ' + animationOut);
        lastSlide.el.data('animation', animationOut)

    } else {
        currentSlide.el.trigger('transitionOn', [currentSlide]);
    }

}

InTemplate.prototype.showError = function (errorMsg) {

    var template = this;

    if (!template.currentSlide) {
        template.setSlide(1);
    }

    template.loadPopup("error", function (el) {

        if (el) {

            template.presentation.find('.popups #popup-error').children('.popup').data('id', template.options.errorID);

            var errorPopup = template.getPopup("error");
            template.presentation.find('.popups #overlay').show(0);
            el.find('.error-msg').text(errorMsg);

            el.show(0);
            template.currentPopup = errorPopup;
            template.presentation.trigger('popupOn', [errorPopup]);

        }
    });

};

InTemplate.prototype.preloadPopup = function () {

    var template = this;

    //load popup
    template.presentation.find('a[href|="#popup"]').each(function () {
        var btn = $(this);
        var name = btn.attr('href').replace('#popup-', '');
        template.loadPopup(name, function (el) {
            if (el && template.isPopupValid(name)) {
                btn.removeClass('inactive');
            } else {
                btn.addClass('inactive');
            }
        });
    });

}

InTemplate.prototype.loadPopup = function (name, callback) {

    var template = this;

    //add popup container
    var popupContainer = null;
    if (!template.presentation.children('.popups').length) {
        popupContainer = $('<div />');
        popupContainer.attr('class', 'popups');
        popupContainer.appendTo(template.presentation);
        $('<div id="overlay"></div>').appendTo(popupContainer);
    } else {
        popupContainer = template.presentation.children('.popups')
    }

    //if element exists return its
    if (popupContainer.children('#popup-' + name).length > 0) {
        var el = $('.popups #popup-' + name);
        return callback(el, null);
    }

    $.ajax({
        url: template.baseUrl + '/' + template.options.popupsFolder + "/" + name + ".html?" + Date.now(),
        type: 'GET',
        success: function (data) {
            var el = $('<div />');
            el.attr('id', 'popup-' + name);
            el.addClass('popup-wrap')
            el.hide(0);
            $(template.options.popupCloseButton).appendTo(el);
            el.appendTo(popupContainer);
            el.append(data);
            template.vimeoEmbed(el.children('.popup'));

            return callback(el, null);
        },
        error: function (data) {
            return callback(null, "Popup does not exists");
        }
    });

};

InTemplate.prototype.showPopup = function (name) {

    var template = this;

    //exit when another window is open
    if (template.currentPopup && !template._allowControl) {
        return true;
    }

    template.loadPopup(name, function (el, error) {

        if (el) {

            if (!template.isPopupValid(name)) {
                return true;
            }

            var currentpopup = template.getPopup(name);

            if (currentpopup.modal) {
                el.children('.close-popup').remove();
            }

            if (currentpopup.showOverlay) {
                template.presentation.find('.popups #overlay').show(0);
            } else {
                template.presentation.find('.popups #overlay').hide(0);
            }

            el.show(0);
            template.currentPopup = currentpopup;
            template.presentation.trigger('popupOn', [currentpopup]);

        } else if (error) {

            template.presentation.trigger('error', [error]);

        }

    });

};

InTemplate.prototype.closePopup = function () {

    var popup = this.getPopup();

    //check the form, if it is valid, submitted ...
    var form = popup.el.find('form');
    var submitButton = form.find('.form-submit, [type="submit"], button');

    if (submitButton.length && form.data('required') && !popup.formSubmited) {

        swal('Formulář nebyl odeslán', '', 'warning');
        return false;

    } else if (!submitButton.length) {

        if (!this.processForm(form, popup, true, false)) {
            return false;
        }

    }

    this.presentation.find('.popups #overlay').hide(0);
    this.presentation.find('.popups #popup-' + this.currentPopup.name).hide(0);
    this.presentation.trigger('popupOff', [popup]);
    this.currentPopup = null;

}

InTemplate.prototype.setContainerHeight = function (slide) {

    if (this.options.slideHeight) {
        return;
    }

    if (!slide && !this.currentSlide) {
        return;
    } else if (!slide) {
        slide = this.currentSlide;
    }

    height = 0;

    slide.el.find('.row, [class|="row"]').each(function () {
        height += $(this).outerHeight(true);
    });

    var shiftTop = (slide.el.outerHeight(true) - slide.el.height()) + parseInt(slide.el.css('top')) + parseInt(slide.el.css('bottom'));

    this.slidesContainer.height(height + shiftTop);

}

InTemplate.prototype.renderControl = function () {

    var template = this;

    //add arrows 
    var leftArrow = $(template.options.leftArrow);
    leftArrow.attr('data-role', 'leftArrow');

    if (!template.presentation.find('[data-role="leftArrow"]').length) {
        template.slidesContainer.append(leftArrow);
        template.presentation.find(leftArrow).css('display', 'none');
    }

    var rightArrow = $(template.options.rightArrow);
    rightArrow.attr('data-role', 'rightArrow');

    if (!template.presentation.find('[data-role="rightArrow"]').length) {
        template.slidesContainer.append(rightArrow);
        template.presentation.find(rightArrow).css('display', 'none');
    }

    if (template.currentSlide.num > 1 && template.currentSlide.leftNav) {
        template.presentation.find('[data-role="leftArrow"]').css('display', '');
    } else {
        template.presentation.find('[data-role="leftArrow"]').css('display', 'none');
    }

    if (template.currentSlide.num < template.numberSlides && template.currentSlide.rightNav) {
        template.presentation.find('[data-role="rightArrow"]').css('display', '');
    } else {
        template.presentation.find('[data-role="rightArrow"]').css('display', 'none');
    }

    if (template.currentSlide.slides.length > 0) {
        var downArrow = $(template.options.downArrow);
        downArrow.attr('data-role', 'downArrow');

        if (!template.presentation.find('[data-role="downArrow"]').length && template.currentSlide.subNum < template.currentSlide.slides.length && template.currentSlide.downNav) {
            template.slidesContainer.append(downArrow);
        } else if (template.currentSlide.subNum >= template.currentSlide.slides.length || !template.currentSlide.downNav) {
            template.presentation.find('[data-role="downArrow"]').remove();
        }

        var upArrow = $(template.options.upArrow);
        upArrow.attr('data-role', 'upArrow');

        if (!template.presentation.find('[data-role="upArrow"]').length && template.currentSlide.subNum > 0 && template.currentSlide.upNav) {
            template.slidesContainer.append(upArrow);
        } else if (template.currentSlide.subNum == 0 || !template.currentSlide.upNav) {
            template.presentation.find('[data-role="upArrow"]').remove();
        }

    } else {
        template.presentation.find('[data-role="upArrow"]').remove();
        template.presentation.find('[data-role="downArrow"]').remove();
    }

    //inactive slide links
    template.presentation.find('a[href|="#slide"]').each(function () {
        var coord = $(this).attr('href').replace('#slide-', '');
        var s = template.getSlideByCoord(coord);
        if (template.isSlideValid(s.slideNum, s.subSlideNum)) {
            $(this).removeClass('inactive');
        } else {
            $(this).addClass('inactive');
        }
    });

}

InTemplate.prototype.prepareAnimateElement = function (el) {

    var delay = null;

    if (el.attr('data-animation-global')) {
        el.data('animation', el.data('animation-global'));
    }

    if (this.lastSlide) {
        delay = parseFloat(this.options.slideAnimationDuration);
    } else {
        delay = 0;
    }

    var data = el.data();

    if (data.animationDelay) {
        delay += parseFloat(data.animationDelay);
    }

    el.css('animation-delay', delay + 's');

    if (data.animationDuration !== undefined) {
        el.css('animation-duration', data.animationDuration + 's');
    }

    if (data.animationIteration !== undefined) {
        el.css('animation-iteration-count', data.animationIteration);
    }

    if (!el.hasClass('animated') || el.data('animation-repeat')) {
        el.addClass('animated ' + el.data('animation'));
    }

}

InTemplate.prototype.animateElements = function (slide) {

    var template = this;

    $(slide.el.find('[data-animation]')).each(function () {
        template.prepareAnimateElement($(this));
    });

    $(template.presentation.find('[data-animation-global]')).each(function () {
        template.prepareAnimateElement($(this));
    });


}

InTemplate.prototype.toggleOrientationOverlay = function (orientation) {

    if (!this.isMobile() || this.options.orientationOverlay == '') {
        return true;
    }

    var orientationOverlay = $('body').children('#orientationOverlay');

    if (orientation == 0 || orientation == 180 || orientation == "portrait") {
        if (orientationOverlay.length > 0) {
            orientationOverlay.show();
        } else {
            orientationOverlay = $('<div />');
            orientationOverlay.attr('id', 'orientationOverlay');
            orientationOverlay.html(this.options.orientationOverlay);
            orientationOverlay.appendTo('body');
        }
        this.presentation.hide();
    } else {

        if (orientationOverlay.is(':visible')) {
            this.presentation.show();
            this.setContainerHeight();
            orientationOverlay.hide();
        }

    }

}

InTemplate.prototype.init = function () {

    var template = this;

    //nastaveni citlivost swipe
    $.event.special.swipe.horizontalDistanceThreshold = 250;

    //nastavi vysku a sirku prezentace
    if (this.options.height) {
        template.presentation.css('height', this.options.height);
        template.presentation.css('min-height', this.options.height);
    } else {
        template.presentation.css('min-height', '100%');
    }

    template.presentation.css('width', this.options.width);

    if (template.options.slideHeight) {
        template.slidesContainer.height(template.options.slideHeight);
        template.slidesContainer.css('flex', '0 0 auto');
    }

    //init inbox url
    if (typeof incampaign_base_url != "undefined") {
        template.baseUrl = incampaign_base_url;
    }

    $(window).load(function () {
        template.setContainerHeight();
    });

    //init indetail_lib
    $(document).ready(function () {

        if (template.options.loadServerVars && indetail.server_vars) {
            var stats_data = indetail.server_vars.stats_data;
            if (stats_data) {
                template.options.avgPoints = stats_data['avgPoints'];
            }

            if (indetail.server_vars.answers) {
                template.options.answers = indetail.server_vars.answers;
            }

            template.options.userData = indetail.server_vars.user;

            template.populateForm(template.currentSlide);

        }

        if (template.options.allowIndetailLib) {
            template.indetail = indetail;

            //je-li slide jiz nacten odesleme statistiky, jinak se pouzije odeslani ve slideOn
            if (template.currentSlide) {
                template.indetail.sendData(template.currentSlide.id, template.currentSlide.id);
                console.log("Send data: " + template.currentSlide.id + " - " + template.currentSlide.id);
            }

        }

    });


    //turn off history during init 
    template._historyOff = true

    //inicializace udalostí
    //swipe
    if (template.options.mobileGestures) {
        this.presentation.on('swipeleft', function () {

            if (template.options.vertical) {
                template.nextSubSlide();
            } else {
                template.nextSlide();
            }

        });

        this.presentation.on('swiperight', function () {

            if (template.options.vertical) {
                template.prevSubSlide();
            } else {
                template.prevSlide();
            }


        });


    }

    //zmena kotvy
    $(window).on('hashchange', function (e) {
        template.slideOpenByHash(window.location.hash);
    });

    //click na link slidu
    $(document).on('click', 'a[href|="#slide"]', function (e) {
        var slideNum = $(this).attr('href').replace('#slide-', '');
        e.preventDefault();
        template.setSlide(slideNum);
    });

    $(document).on('click', 'a[href|="#next"]', function (e) {
        e.preventDefault();
        template.nextSlide();
    });

    $(document).on('click', 'a[href|="#prev"]', function (e) {
        e.preventDefault();
        template.prevSlide();
    });

    $(document).on('click', 'a[href|="#nextsub"]', function (e) {
        e.preventDefault();
        template.nextSubSlide();
    });

    $(document).on('click', 'a[href|="#prevsub"]', function (e) {
        e.preventDefault();
        template.prevSubSlide();
    });

    //tooltip
    $(document).on('click', '[data-tooltip]', function (e) {
        template.showTooltip($(this), false);
    });

    $(document).on('mouseenter', '[data-tooltip]', function (e) {
        template.showTooltip($(this), true);
    });

    $(document).on('mouseleave', '[data-tooltip]', function (e) {
        template.closeTooltip($(this), true);
    });

    $(template.presentation).on('click', function (e) {
        template.closeTooltip($(this), false);
    });


    //click na link popupu
    $(document).on('click', 'a[href|="#popup"]', function (e) {
        var popupNum = $(this).attr('href').replace('#popup-', '');
        e.preventDefault();
        template.showPopup(popupNum);
    });

    //close popup
    $(document).on('click', '.close-popup', function (e) {
        e.preventDefault();
        template.closePopup();
    });

    $(document).on('click', '#overlay', function (e) {
        e.preventDefault();
        if (template.currentPopup.closeByBgClick && !template.currentPopup.modal) {
            template.closePopup();
        }

    });

    //close popup
    $(document).on('click', '.form-submit', function (e) {

        e.preventDefault();
        $(this).closest('form').trigger('submit');

    });

    $(template.presentation).on('submit', 'form', function (e) {

        e.preventDefault();

        if (template.currentPopup) {
            var container = template.currentPopup;
        } else {
            var container = template.currentSlide;
        }

        var form = $(this);

        template.processForm(form, container, true, true);

    });

    //detect back and forward button for turn off history
    $(window).on('popstate', function (event) {
        template._historyOff = true;
    });

    //handle orientation
    if (window.orientation != undefined) {
        template.toggleOrientationOverlay(window.orientation);
    }

    $(window).on('orientationchange', function (e) {
        template.toggleOrientationOverlay(e.orientation);
    });

    $(template.presentation).on('slideOn', function (e, prevSlide, currentSlide) {

        //set container height by slide
        template.setContainerHeight(currentSlide);

        //show|hide header|footer
        if (currentSlide.showHeader) {
            template.presentation.children('header').show();
        } else {
            template.presentation.children('header').hide();
        }

        if (currentSlide.showFooter) {
            template.presentation.children('footer').show();
        } else {
            template.presentation.children('footer').hide();
        }

        //set to history
        if (!template._historyOff && currentSlide.hasOwnProperty('coord') && template.options.history) {
            history.pushState(currentSlide.coord, null, '#slide-' + currentSlide.coord);
        }

        template.animateElements(currentSlide);

        //end of temporary turn off history
        template._historyOff = false;

        if (template.options.allowIndetailLib && template.indetail) {

            var previd = null;
            if (prevSlide) {
                previd = prevSlide.id;
            } else {
                previd = currentSlide.id;
            }

            console.log("Send data: " + previd + " - " + currentSlide.id);
            template.indetail.sendData(previd, currentSlide.id);

        }

    });

    $(template.presentation).on('transitionOn', function (e, slide) {

        slide.setData('visited', 1);

        //autoplay vimeo
        if (slide.playVimeo && typeof vimeoPlayer != "undefined") {
            vimeoPlayer[slide.playVimeo].play();
            slide.playVimeo = null
            slide.el.removeData('play-vimeo');
        }

    });

    $(template.presentation).on('slideOff', function (e, prevSlide, currentSlide) {

        //clear all timeouts
        var id = window.setTimeout(function () { }, 0);

        while (id--) {
            window.clearTimeout(id);
        }

        prevSlide.el.find('[data-animation]').each(function () {

            var animation = $(this).data('animation');

            $(this).removeClass(animation);

            //odebrani elementu s "out" animace bez opakovani
            if (animation.indexOf('Out') > 0 && !$(this).data('animation-repeat')) {
                $(this).remove();
            }

        });

        $(template.presentation.find('[data-animation-global]')).each(function () {
            $(this).removeClass($(this).data('animation'));
            var newone = $(this).clone(true);
            $(this).before(newone).remove();
        });

        if (typeof vimeoPlayer != "undefined") {
            for (player in vimeoPlayer) {
                vimeoPlayer[player].pause();
            }
        }

    });

    $(template.presentation).on('popupOn', function (e, popup) {

        if (popup.playVimeo && typeof vimeoPlayer != "undefined") {
            vimeoPlayer[popup.playVimeo].play();
            popup.playVimeo = null
            template.presentation.find('#' + popup.id).children('.popup').removeData('play-vimeo');
        }

        var slide = template.getSlide(template.currentSlide.num, template.currentSlide.subNum)
        if (popup.id && template.options.allowIndetailLib && template.indetail) {
            console.log("Send data: " + slide.id + " - " + popup.id);
            template.indetail.sendData(slide.id, popup.id);
        }


        template._allowControl = false;
    });

    $(template.presentation).on('popupOff', function (e, popup) {

        if (typeof vimeoPlayer != "undefined") {
            for (player in vimeoPlayer) {
                vimeoPlayer[player].pause();
            }
        }

        if (popup.id && template.options.allowIndetailLib && template.indetail) {
            var slide = template.getSlide(template.currentSlide.num, template.currentSlide.subNum);
            console.log("Send data: " + popup.id + " - " + slide.id);
            template.indetail.sendData(popup.id, slide.id)
        }
        template._allowControl = true;
    });


    $(template.presentation).on("webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend", function (e) {
        var currentSlide = template.currentSlide;
        if (currentSlide.el.data('coord') == $(e.target).data('coord')) {
            template.lastSlide.el.hide();
            currentSlide.el.removeClass('animated');
            currentSlide.el.trigger('transitionOn', [currentSlide]);
        }
    });


    $(template.presentation).on('error', function (e, error) {

        if (template.options.errorSlide) {
            template.showError(error);
        }

    });

    //control by key
    $(document).on('keydown', function (e) {

        if (template._allowControl && template.options.keyControl) {

            if (e.keyCode == 37) {
                return template.prevSlide();
            }

            if (e.keyCode == 39) {
                return template.nextSlide();
            }

        }

        //close popup by ESC
        if (e.keyCode == 27) {
            if (template.currentPopup) {
                if (template.currentPopup.closeByEsc && !template.currentPopup.modal) {
                    return template.closePopup();
                }
            }
        }
    });

    //inicializace uvodni stranky
    if (window.location.hash) {

        template.slideOpenByHash(window.location.hash);

    } else {

        template.setSlide(1);

    }

};

InTemplate.prototype.vimeoEmbed = function (el) {

    if (!el.find('.vimeo_player').length) {
        return true;
    }

    //vimeo prepare
    el.find('.vimeo_player').each(function () {
        if ($(this).data('vimeo-autoplay')) {
            $(this).removeAttr('data-vimeo-autoplay');
            el.data('play-vimeo', $(this).attr('id'));
            return false;
        }
    });

    if (typeof vimeoWrapper != "undefined") {
        vimeoWrapper.embed();
    }

}

//append slide to presentation
InTemplate.prototype.appendSlide = function (el, coord) {

    el.hide(0);
    el.attr('data-coord', coord);
    el.attr('id', 'slide' + coord);
    el.appendTo(this.slidesContainer);

    this.vimeoEmbed(el);
    this.presentation.trigger('slideLoaded', [el]);

    return el;

};

//Tooltip
InTemplate.prototype.showTooltip = function (el, delay) {

    var tooltip = el.find('.tooltip');
   
    if (tooltip.length == 0) {
        tooltip = $('<span class="tooltip">' + el.data('tooltip') + '</span>');
        var marginBottom = el.outerHeight(true)*1.25;
        tooltip.css('bottom', '0px');
        tooltip.css('margin-bottom',marginBottom + 'px');
        tooltip.appendTo(el);
    }

    if (tooltip.hasClass('active')) {
        tooltip.clearQueue();
        return true;
    }

    tooltip.css('overflow','visible');
    
    if (delay) {
        tooltip.delay(800).queue(function(next){
            tooltip.addClass('active');
            next();
        });
        tooltip.slideDown();
    } else {
        tooltip.addClass('active').slideDown()
    }

};

InTemplate.prototype.isMobile = function () {
    var check = false;
    (function (a) { if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|android|ipad|playbook|silk/i.test(a) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0, 4))) check = true; })(navigator.userAgent || navigator.vendor || window.opera);
    return check;
}

InTemplate.prototype.closeTooltip = function (el, delay) {

    var tooltip = el.find('.tooltip');

    if (!tooltip.hasClass('active')) {
        tooltip.clearQueue();
        return true;
    }

    if (delay) {
        tooltip.delay(800).slideUp(400, function(){
            tooltip.removeClass('active');
        });
    } else {
        tooltip.slideUp(400, function(){
            tooltip.removeClass('active');
        });
    }
};

(function ($) {

    $.fn.inTemplate = function (options) {

        var inTemplate = new InTemplate(this, options);

        Object.getOwnPropertyNames(InTemplate.prototype).forEach(function (o) {
            if (o.substring(0, 2) == "__") {
                inTemplate[o]();
            }
        });

        return inTemplate;

    }

}(jQuery));