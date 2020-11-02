#!/usr/bin/php5
<?php
/*
 *	Skript pro resumable PUT upload videa na vimeo.
 *  https://developer.vimeo.com/api/upload#start-streaming
 *	spustitelne z CLI (cron, shell)
 *
 *  parametry skriptu jsou typ operace (upload/replace) a id contentu (typu Ibulletin_Video)
 *
 *	@author Andrej Litvaj
 */

//define('PHC_LEGACY_PATH','/home/mentor/projects/inbox_lib/');

// bootstraping
require(realpath(dirname(__FILE__) . '/../bootstrap.php'));

// define command-line arguments
$getopt = new Zend_Console_Getopt(array(
	'type|t=s' => 'type of operation on video [upload, replace]',
    'id|i=s' => 'content ID (expecting id of content of Ibulletin_Content_Video)',
    'verbose|v' => 'verbose output',
	'help|h' => 'help',
));

$options = array();

try {
	// parse command-line
	$getopt->parse();
	// if help requested, report usage message
	if ($getopt->getOption('h')) {
		echo $getopt->getUsageMessage();
		exit(0);
	}

	// get cli arguments
    if ($s = $getopt->getOption('t')) $options['type'] = $s;
	if ($s = $getopt->getOption('i')) $options['id'] = $s;
	if ($s = $getopt->getOption('v')) $options['verbose'] = (boolean)$s;

	//sanitize
	foreach ($options as $k => $v) {
		// (trim, lowercase strings in array)
		if (in_array($k,array('type', 'id'))) {
			$options[$k] = strtolower(trim($v));
		}
	}

    // default type is upload
    if (!isset($options['type'])) {
        $options['type'] = 'upload';
    }

    // required content id
    if (!isset($options['id'])) {
        throw new Zend_Console_Getopt_Exception('Content id is required');
    }

    if (!isset($options['verbose'])) {
        $options['verbose'] = false;
    }

} catch (Zend_Console_Getopt_Exception $e) {
	// Bad options passed: report usage
	echo $getopt->getUsageMessage(), PHP_EOL;
	exit(1);
}

/* *************************************************************** */
/* *************************************************************** */
/* *************************************************************** */
try {

    // initialize configuration
    $config = Env::initConfig();
    $logger = Phc_ErrorLog::initialize();
    $db = Env::initDatabase();

    // search for content with given ID
    $content = Contents::get((int)$options['id']);

    if (!$content || $content['class_name'] != 'Ibulletin_Content_Video') {
        throw new InvalidArgumentException('Content id '.$options['id'].' does not exists or is not of type Ibulletin_Content_Video!');
    }

    $obj = $content['object'];

    if (!$obj->vimeo_file) {
        throw new InvalidArgumentException('Missing video file name in Ibulletin_Content_Video!!');
    }

    // initialize vimeo api
    $vimeo_api = new Vimeo($config->video->vimeo_client_id,
        $config->video->vimeo_client_secret,
        $config->video->vimeo_access_token);

    // set path to content video file
    $fullpath = 'pub/content/'.$obj->id.'/'.$obj->vimeo_file;
    $video_uri = null;

    vimeo_state($fullpath, $options['type'], 0, 'uploading');

    switch ($options['type']) {

        case 'upload' :
            if (!file_exists($fullpath)) {
                throw new Exception('video file not found! ' . $filepath);
            }
            $upload_path = $fullpath;
            /*tempnam('/tmp','vimeo_');
            copy($fullpath, $upload_path);
*/
            // notify upload start
            vimeo_state($fullpath, $options['type'], 10, 'uploading');

            $video_uri = $vimeo_api->upload($upload_path, $obj->quality1080p, NULL);

            // notify upload finish
            vimeo_state($fullpath, $options['type'], 50, 'uploading');

            // set video album
            $album_name = 'inbox_'.$config->database->params->dbname;
            $album_description = 'Album for project "'.$config->general->project_name.'" of website '. APPLICATION_URL;

            $response = $vimeo_api->setVideoAlbum($video_uri, $album_name, $album_description);
            if (!in_array($response['status'], array(200, 201, 204))) {
                throw new Exception('video album update failed! ' . print_r($response, true));
            }

            // notify album finish
            vimeo_state($fullpath, $options['type'], 60, 'uploading');

            // set preset
            $preset_id = $config->video->vimeo_preset_id;
            $response = $vimeo_api->setVideoPreset($video_uri, $preset_id);
            if (!in_array($response['status'], array(200, 201, 204))) {
                throw new Exception('video preset update failed! ' . print_r($response, true));
            }
            // notify metadata finish
            vimeo_state($fullpath, $options['type'], 70, 'uploading');

            break;

        case 'replace' :
            /*$upload_path = tempnam('/tmp','vimeo_');
            copy($fullpath, $upload_path);*/
            $upload_path = $fullpath;
            // notify replace start
            vimeo_state($fullpath, $options['type'], 10, 'replacing');

            $video_uri = $vimeo_api->replace('/videos/'.$obj->vimeo_id, $upload_path, $obj->quality1080p, NULL);

            // notify replace finish
            vimeo_state($fullpath, $options['type'], 70,'replacing');
            break;

        default:
            throw new InvalidArgumentException('Type of operation "'.$options['type'].'" is invalid. [upload, replace]');
    }


    // we have vimeo_id, update metadata with latest object data
    if ($video_uri) {
        $vimeo_id = basename($video_uri);


        $content = Contents::get((int)$options['id']);
        $obj = $content['object'];

        // update vimeo metadata
        $response = $vimeo_api->setVideoMetadata($video_uri, $obj->name, $obj->annotation);
        if (!in_array($response['status'], array(200, 201, 204))) {
            throw new Exception('video metadata update failed! ' . print_r($response, true));
        }

        // notify metadata finish
        vimeo_state($fullpath, $options['type'], 80,'transcoding');

        // query API until available is found, must be followed after trancoding status
        // sometimes vimeo api leave status available immediately after upload PUT finished
        while ($response = $vimeo_api->request($video_uri)) {
            sleep(5);
            //echo $response['body']['status'],PHP_EOL;
            if ($response['body']['status'] == 'transcoding') break;
        }

        // wait for vimeo to finish transcoding
        while ($response = $vimeo_api->request($video_uri)) {
            sleep(5);
            //echo $response['body']['status'],PHP_EOL;
            if ($response['body']['status'] == 'available') break;
        }

        // notify transcoding finish
        vimeo_state($fullpath, $options['type'], 100, 'available');

        // give UI enough room to catch file change before unlinking
        sleep(5);

        unlink($fullpath.'.vimeo');

        // finally video is ready and we can clear worker and update object
        $obj->vimeo_worker = null;

        // update object
        $obj->vimeo_id = $vimeo_id;
        $obj->vimeo_video = $response;
        $obj->tpl_name_schema = 'vimeo_%d.phtml';
        Contents::edit($obj->id, $obj);

    } else {
        throw new Exception('vimeo_id was not provided by vimeo!');
    }

} catch (Exception $e) {


    $content = Contents::get((int)$options['id']);
    $obj = $content['object'];
    $f = 'pub/content/'.$obj->id.'/'.$obj->vimeo_file.'.vimeo';
    if (file_exists($f)) {
        unlink($f);
    }

    // finally video is ready and we can clear worker and update object
    $obj->vimeo_worker = null;
    $obj->vimeo_id = null;
    $obj->vimeo_file = null;
    Contents::edit($obj->id, $obj);

    Phc_ErrorLog::error('vimeo worker: ', $e->getMessage());
    echo $e, PHP_EOL;
	exit(2);
}

// return vimeo_id to stdout
echo $vimeo_id;
// successfully exit
exit(0);

function vimeo_state($path, $type, $progress, $status) {
    // notify upload finish
    file_put_contents($path . '.vimeo', Zend_Json::encode(array(
        'file' => $path,
        'operation' => $type,
        'progress' => $progress,
        'status' => $status
    )));
};

?>