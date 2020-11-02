/**
 * Before calling this constructor, there should be defined global variable
 * "indetailvars" containing server URL where data are gong to be snet to - 
 * indetailvars.saver_url (setting this properly is task of inDetail system, so
 * you may set this only for testing by yourself).
 * 
 * @param   Prefix for the name of the slide element (slide num will be added to the end)
 *          if set to null, data are not gathered from slide at all.
 * @param   bool    Has this presentation sound?
 * @param   bool    Is sound turned on as defaultl? 
 * @param   boot    Is debug mode? (shows some debuging information in alert windows)
 */
var Indetail = function(slide_id_prefix, has_sound, sound_default, debug)
{
    this.slide_answers = {};
    
    if(debug !== undefined){
        this.debug = debug;
    }
    
    // Set slide ID prefix
    this.slide_id_prefix = slide_id_prefix;
    
    // Sound
    this.has_sound = has_sound;
    this.sound_on = sound_default;

    // Prepare unique string
    this.unique = this.randomString(10);

    // Initial time in ms
    var date = new Date();
    this.initial_time = date.getTime();
    this.slide_initial_time = this.initial_time;

    // Variables passed from the server
    if(typeof indetailvars !== "undefined"){
        this.server_vars = indetailvars;
    }
    else if(this.debug){
        //alert("Global variable indetailvars must be set before constructor of Indetail class is called.");
    }
};


/**
 * bool Debug mode on?
 */
Indetail.prototype.debug = false;

/**
 * Number of transitions between slides since load of presentation.
 */
Indetail.prototype.sequence = 0;

/**
 * Unique string for this load of presentation.
 */
Indetail.prototype.unique = '';

/**
 * Inicialization timestamp in MS
 */
Indetail.prototype.initial_time = 0;

/**
 * Slide inicialization timestamp in MS
 */
Indetail.prototype.slide_initial_time = 0;

/**
 * Prefix for the name of the slide element (slide num will be added to the end).
 */
Indetail.prototype.slide_id_prefix = '';

/**
 * Answers to questions from current slide 
 */
Indetail.prototype.slide_answers = {};

/**
 * Has this got presentation sound?
 */
Indetail.prototype.has_sound = true;

/**
 * Is sound turned on?
 */
Indetail.prototype.sound_on = true;

/**
 * Variables passed from server
 */
Indetail.prototype.server_vars = {};


/**
 * Number of points obtained in the presentation
 */
Indetail.prototype.points = 0;


/**
 * List of user atributtes for saving on server
 */
Indetail.prototype.userdata = {};

/**
 * Latest positions reached in videos and reported to server.
 * Structure is videoNum: latestPosition,
 */
Indetail.prototype.latestReportedVideoPositions = {};

/**
 * Sets whether the sound is turned on or off
 * This value is being held across slides, so when sound is turned "OFF" and on 
 * next slide it is "ON" again by default, you have to call this method and 
 * change sound_on to true again. 
 *
 * @param  bool Is the sound turned on?
 */
Indetail.prototype.setSoundOn = function(sound_on){
    this.sound_on = sound_on;
};

/**
 * Sets obtained points 
 * @param int points
 */
Indetail.prototype.setPoints = function(points){

    this.points = points;     

};

/**
 * Set user data for saving a sending, place before method sendData, which resets it after sending
 * @param string attrib
 * @param mixed value
 */
Indetail.prototype.setUserData = function(attrib, value) {
    
    this.userdata[attrib] = value;
    
};

/**
 * Should be called when slide is opened/started.
 *
 * this.slide_initial_time is reseted by sendData, so this method
 * should be recalled only if there is a reason.
 */
Indetail.prototype.slideStarted = function(){

    // Slide initial time
    var date = new Date();
    this.slide_initial_time = date.getTime();
};


/**
 * Gets object with data from server
 * DEPRECATED - data are going to be sent as part of main HTML file.
 */
Indetail.prototype.getServerData = function(){
    $.get(this.server_vars.xml_data_url, {}, function(data){});
};


/**
 * Sets answer for one question.
 * 
 * @param  string|int        Id of the question, should be number of question
 * @param  string|array[int] Answer to question (or answer number starting with 1)
 * REMOVED @param  bool              Optional - set only part of multiple anwer instaed of setting
 *                           an array in 'answer' parameter; To reset the answer
 *                           to unfilled set this parameter to null.
 */
Indetail.prototype.setQuestionAnswer = function(question_id, answer/*, setOnlyPart*/){
    /*
    if(setOnlyPart != undefined && setOnlyPart == true || setOnlyPart == null){
        // Set new answer of multiple choice or change existing
        if(typeof this.slide_answers[question_id] != 'Array'){
            this.slide_answers[question_id] = [answer,9];
        }
        else{
            // Create new array of answers without 
            var newAnswer = [];
            for(var i=0; i<this.slide_answers[question_id].length; i++){
                if(i != answer){
                    newAnswer[newAnswer.length] = this.slide_answers[question_id][i];
                }
            }
            this.slide_answers[question_id] = newAnswer;
            if(setOnlyPart != null){
                this.slide_answers[question_id][this.slide_answers[question_id].length] = answer;
            }
        }
    }
    else{
        this.slide_answers[question_id] = answer;
        //alert(this.slide_answers[question_id] + ' ' + question_id);
    }
    */
    this.slide_answers[question_id] = answer;
    //alert(this.slide_answers[question_id] + ' ' + question_id);
}


/**
 * Gathers all data from any form on the given slide.
 * 
 * Slide form with data to be sent to the server is HTML element with 
 * id = this.slide_id_prefix + slide_id. Data from all input elements of this form
 * are automatically gathered
 * 
 * @return  object|null Object containing name of question 
 *          as a key and answer as a value; for multiple choices there is an array as a value
 */
Indetail.prototype.gatherData = function(slide_id){
    if(this.slide_answers === undefined){
        this.slide_answers = {};
    }
    
    var data = this.slide_answers;
    
    // Find element containing actual slide
    var slide = document.getElementById(this.slide_id_prefix + slide_id);

    var isEmpty = true;
    var lastElemName = null;
    $(":input", slide).each(function(){
        input = $(this);
        // Workaround for radios and checkboxes
        if(input.attr('type') == "radio" || input.attr('type') == "checkbox"){
            if(lastElemName != input.attr("name")){
                lastElemName = input.attr("name");
                if(data[input.attr('name')] == undefined){
                    data[input.attr('name')] = [];
                }
            }
            data[input.attr('name')][data[input.attr('name')].length] = input.val();
        }
        else{
            data[input.attr('name')] = input.val();
        }
        isEmpty = false;
    });
    
    this.slide_answers = data;
    
    if(isEmpty){
        return null;
    }
    else{
        return this.slide_answers;
    }
};

/**
 * Creates a set of data to be sent to the server and sends them.
 * 
 * Data are prepared to be sent in inDetail internal format. They are passed 
 * to server by HTTP POST method.
 * 
 * Before this method is called, you should eaither set answers for questions 
 * on current slide using indetail.setQuestionAnswer() or there should be
 * set correct id prefix of form element containing data to be sent to the 
 * server (this data are automatically gathered by indetail.gatherData() during
 * current method). 
 */
Indetail.prototype.sendData = function(slide_id, slide_on_id){
    // Increment sequence
    this.sequence++;

    // Data to be sent in format of indetail module
    var data = {};

    // Prepare data
    if(this.slide_id_prefix != null){ // Checks if prefix is not empty
        this.gatherData(slide_id);
    }
    var gathered = this.slide_answers;

    // Sort keys, because we need to correctly number answers
    var keys = []; 
    for(var key in gathered){ 
        keys.push(key); 
    } 
    keys.sort();
    

    // Prepare answers
    var key;
    var val;
    var counter = 1;
    var question_ids = new Array();
    var answers = new Array();
    for(var key1 = 0; key1<keys.length; key1++){
        key = keys[key1]; // Get the key of gathered
        val = gathered[key];
        // Arrays are encoded into values string by joining with #
        if((typeof(val) == 'object') && (val instanceof Array)){
            // URLEncode
            for(i in val){
                val[i] = encodeURIComponent(val[i]);
            }
            val = "#" + val.join('#') + "#";
        }
        else{
            // URLEncode of simple value
            val = encodeURIComponent(val);
        }
        
        // For questions with numbers as keys we use their numbers
        if(!isNaN(Number(key))){
            counter = Number(key);
        }

        question_ids[question_ids.length] = counter;
        answers[answers.length] = val;

        counter++;
    }
    if(gathered){
        data.answer = answers.join('|');
        data.question_id = question_ids.join('|');
    }

    // Type of presentation
    data.presentationType = 'html';

    // Unique string
    data.unique = this.unique;

    // Sequence number
    data.sequence = this.sequence;

    // Slide ID, slide on, slide off, slide group, steps
    data.slide_id = slide_id;
    data.slide_group_id = slide_id;
    data.slideoff = slide_id;
    data.slideon = slide_on_id;
    data.steps = slide_id;

    // Sound
    data.sound = this.has_sound;
    data.sound_on = this.sound_on;
    
    //Points
    data.points = this.points;

    // Times
    var date = new Date();
    data.time_slide = date.getTime() - this.slide_initial_time;
    data.time_total = date.getTime() - this.initial_time;
    
    //User data
    data.userdata =  JSON.stringify(this.userdata);
    this.userdata = {};

    // Reset slide initial time for next slide
    //this.slide_initial_time = date.getTime();
    this.slideStarted();


    //$.post(this.server_vars.saver_url, data, function(){});
    
    // Because we must not use processData (| and # must not be url encoded)
    // we have to create uri-string by iterating data object
    var uri_stringArray = [];
    for(key in data){
        uri_stringArray[uri_stringArray.length] = key + "=" + data[key];
    }
    $.ajax({
      type: 'POST',
      url: this.server_vars.saver_url,
      data: uri_stringArray.join('&'),
      processData: false
      //success: success,
      //dataType: dataType
    });
    
    if(this.debug){
        alert("POST data sent to server: \n" + uri_stringArray.join('&'));
    }
    
    // Reset answers to save
    this.slide_answers = {};
};



/**
 * Sends action and position of the video player to the server.
 * 
 * @param action    string  Name of the action - latesttime, play, pause, seek, embed_start, embed_end
 * @param position  int     Position of playhead in miliseconds (ms)
 * @param videoNum  int     Number of video in presentation; default 1
 * @param data      object  Key: value pairs of another data to be sent to server, 
 *                          for embed_ we can send 'embedfile' key as a name of embeded content (for example a question number)
 */
Indetail.prototype.reportVideoAction = function(action, position, videoNum, data){
    videoNum == null ? videoNum = 1 : null;
    typeof data != 'object' ? data = {} : null;
    
    var reportUrl = videoReportingUrl; // is set globally by inBox platform
    $.post(reportUrl + '?videoNum='+parseInt(videoNum).toString()+'&action='+action+'&position='+position, data, function(result){});
};

/**
 * Should be called periodically (at least once a second) when video is playing.
 * Reports to the server in periodic interval thle latest position of playhead the user
 * reached. The interval is kept internally no matter how often this event is called.
 * 
 * @param position  int     Position of playhead in miliseconds (ms)
 * @param videoNum  int     Number of video in presentation; default 1
 */
Indetail.prototype.videoStillPlayingEventHandler = function(position, videoNum){
    if(videoNum == null){
        videoNum = 1;
    }
    var interval = 10000; // interval between reports in ms
    typeof this.latestReportedVideoPositions.videoNum == 'undefined' ? this.latestReportedVideoPositions.videoNum = 0 : null;
    if(this.latestReportedVideoPositions.videoNum + interval <= position){
        this.latestReportedVideoPositions.videoNum += interval;
        this.reportVideoAction('latesttime', position, videoNum);
    }
};

/**
 * Event handler for play action.
 * 
 * @param position  int     Position of playhead in miliseconds (ms)
 * @param videoNum  int     Number of video in presentation; default 1
 */
Indetail.prototype.videoPlayEventHandler = function(position, videoNum){
    if(videoNum == null){
        videoNum = 1;
    }
    this.reportVideoAction('play', position, videoNum);
};

/**
 * Event handler for pause action.
 * 
 * @param position  int     Position of playhead in miliseconds (ms)
 * @param videoNum  int     Number of video in presentation; default 1
 */
Indetail.prototype.videoPauseEventHandler = function(position, videoNum){
    if(videoNum == null){
        videoNum = 1;
    }
    this.reportVideoAction('pause', position, videoNum);
};

/**
 * Event handler for seeked action.
 * 
 * @param position  int     Position of playhead in miliseconds (ms)
 * @param videoNum  int     Number of video in presentation; default 1
 */
Indetail.prototype.videoSeekedEventHandler = function(position, videoNum){
    if(videoNum == null){
        videoNum = 1;
    }
    this.reportVideoAction('seek', position, videoNum);
};



/**
 * Random string generator.
 */
Indetail.prototype.randomString = function(length){
    var chars = 'abcdefghiklmnopqrstuvwxyz'.split('');

    if (! length) {
        length = Math.floor(Math.random() * chars.length);
    }

    var str = '';
    for (var i = 0; i < length; i++) {
        str += chars[Math.floor(Math.random() * chars.length)];
    }
    return str;
};

/**
 * Return link to user certificate
 */
Indetail.prototype.getCertificate = function(callback) {

    $.getJSON(this.server_vars.certificate_url, function(data){
        if (typeof data.certPath !== "undefined") {
            return callback(data.certPath);
        } else {
            return callback(null, data.error);
        }
    });

};

