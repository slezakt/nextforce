<?php
/**
 * Controller pro FileManager
 * @author Ondra Blaha <ondra@kvarta.cz>
 */
class Admin_FilemanagerController extends Zend_Controller_Action
{


    /**
     * Connector pro elFinder
     */
    public function elfinderConnectorAction() {
        
        $config = Zend_Registry::get('config');
        
        //kontrola prav 
        if (!Ibulletin_AdminAuth::hasPermission('privilege_filemanager')) {
             return;
         }
        
        $id = $this->_getParam('id');
        $this->_helper->viewRenderer->setNoRender(true);
        
        if (!$id) {
            return;
        }
        
        //parametr pro vyber uloziste
        $cpath = $this->_getParam('cpath',null);

        if ($cpath) {
            $path = $_SERVER['DOCUMENT_ROOT'].$this->view->baseUrl() . DIRECTORY_SEPARATOR . rtrim(rawurldecode($cpath),'\\/');
            $url = $this->view->baseUrl() . DIRECTORY_SEPARATOR . rtrim(rawurldecode($cpath),'\\/');
        } else {
            $path = $_SERVER['DOCUMENT_ROOT'].$this->view->baseUrl() . DIRECTORY_SEPARATOR . rtrim($config->content_article->basepath,'\\/').DIRECTORY_SEPARATOR.$id;
            $url = $this->view->baseUrl() . DIRECTORY_SEPARATOR . rtrim($config->content_article->basepath,'\\/').DIRECTORY_SEPARATOR.$id;
        }

        include_once 'elfinder/elFinderConnector.class.php';
        include_once 'elfinder/elFinder.class.php';
        include_once 'elfinder/elFinderVolumeDriver.class.php';
        include_once 'elfinder/elFinderVolumeLocalFileSystem.class.php';
       
        /**
         * Simple function to demonstrate how to control file access using "accessControl" callback.
         * This method will disable accessing files/folders starting from '.' (dot)
         *
         * @param  string  $attr  attribute name (read|write|locked|hidden)
         * @param  string  $path  file path relative to volume root directory started with directory separator
         * @return bool|null
         **/
        function access($attr, $path, $data, $volume) {
            return strpos(basename($path), '.tmb') === 0       // if file/folder begins with '.' (dot)
                ? !($attr == 'read' || $attr == 'write')    // set read+write to false, other (locked+hidden) set to true
                :  null;                                    // else elFinder decide it itself
        }


        // Documentation for connector options:
        // https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options
        $opts = array(
            //debug' => true,
            'roots' => array(
                array(
                    'driver' => 'LocalFileSystem', // driver for accessing file system (REQUIRED)
                    'path' => $path, // path to files (REQUIRED)
                    'URL' => $url, // URL to files (REQUIRED)
                    'accessControl' => 'access', // disable and hide dot starting files (OPTIONAL)
                    'uploadOverwrite' => false,
                    'icon' => 'pub/scripts/elfinder/img/volume_icon_local.png',
                    'attributes' => array(
                        array(// hide anything else
                            'pattern' => '/\.shadow$/',
                            'hidden' => true
                        )
                    )
                )
            )
        );

        // run elFinder
        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
    
    }
    
    
    /**
     * Akce pro zobrazeni spravce souboru ve wysiwyg editoru
     */
    public function elfinderCkeAction() { 
        
 	          
 	} 
    
    

}
