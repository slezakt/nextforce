/*
 vimeo.js
 Vimeo embedding using iframe append
 vimeo event hooking using froogaloop library
 */
var vimeoPlayer = {};

var vimeoWrapper = {
    /**
     * function for embedding all vimeo videos on page
     */
    embed: function () {

        // search all vimeo video players
        _players = document.querySelectorAll('.vimeo_player');

        for (var i = 0; i < _players.length; i++) {

            var id = _players[i].id;

            //register player and event
            vimeoPlayer[id] = new Vimeo.Player(id);
            vimeoWrapper.eventHook(id, vimeoWrapper.reportEvent);

        }
    },

    /**
     * player event hooking using player.js
     */
    eventHook: function (id, callback) {

        var latesttime = 0;
        var interval = 5; // seconds

        vimeoPlayer[id].on('play', function (data) {
            callback(id, 'play', data.seconds);
        });

        vimeoPlayer[id].on('timeupdate', function (data) {
            if (latesttime + interval < data.seconds) {
                latesttime = data.seconds;
                callback(id, 'latesttime', latesttime);
            }
        });

        vimeoPlayer[id].on('pause', function (data) {
            callback(id, 'pause', data.seconds);
        });

        vimeoPlayer[id].on('seeked', function (data) {
            callback(id, 'seek', data.seconds);
        });

    },

    /**
     * reporting function, actual POST request
     *
     * @param player_id
     * @param action
     * @param position
     */
    reportEvent: function (player_id, action, position, videoNum, data) {

        videoNum === null ? videoNum = 1 : null;
        typeof data != 'object' ? data = {} : null;

        // convert seconds to ms
        position = position * 1000;
        var baseReportUrl;

        // globally set by inbox platform
        if (typeof videoReportingUrl === 'undefined') {
            baseReportUrl = document.getElementById(player_id).getAttribute('data-reporting_url');
        } else {
            baseReportUrl = videoReportingUrl;
        }
        if (baseReportUrl === null) {
            baseReportUrl = './';
        }

        var videoNumber = player_id.match(/\d/g);
        videoNumber = videoNumber.join("");

        var reportUrl = baseReportUrl + '?videoNum=' + parseInt(videoNumber).toString() + '&action=' + action + '&position=' + position;
        $.post(reportUrl, data, function (result) {});
    }

}

// run embeding (addLoadEvent, $(document).ready(), addEventListener(), native call)
if (typeof addLoadEvent == 'function') {
    addLoadEvent('vimeoWrapper.embed');
} else if (typeof jQuery == 'function') {
    jQuery(document).ready(function () {
        vimeoWrapper.embed();
    });
} else {
    if (document.addEventListener) {
        document.addEventListener("DOMContentLoaded", function () {
            vimeoWrapper.embed();
        });
    } else if (document.attachEvent) {// IE 8
        document.attachEvent("onreadystatechange", function () {
            if (document.readyState === "complete") {
                document.detachEvent("onreadystatechange", arguments.callee);
                vimeoWrapper.embed();
            }
        });
    } else {
        vimeoWrapper.embed();
    }
}