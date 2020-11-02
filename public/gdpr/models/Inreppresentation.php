<?php

/**
 * iBulletin - InRepPresentation.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

//class Exception extends Exception {}

/**
 * Model slouzici pro manipulaci se soubory prezentaci inRepa.
 * Instance tridy je vazana na content.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Inreppresentation
{
    /**
     * @var int ID of the content containing the presentation
     */
    private $contentId = null;
    
    /**
     * @var bool Is this test version of the package (for test users)?
     */
    private $isTest = null;
    
    /**
     * @var string Path to zip package of the presentation.
     */
    private $zipPackagePath = null;
    
    /**
     * @var string Path to directory of the presentation
     */
    private $directoryPath = null;
    
    /**
     * @var string  Path to directory of inRep data in content and base to zip package name
     */
    private $packagePathInContent = 'ipad_package';
    
    /**
     * @var string  Suffix for the name of the directory and zip package for testing users.
     */
    private $testNameSuffix = '-testers';
    
    /**
     * @var string  Path to zip package from old versions of inRep
     */
    private $oldVersionsPackagePath = null;
    
    /**
     * Creates an instance with given ID and test status.
     * 
     *  @param int      Content ID
     *  @param bool     Is this tester version of the package? (DEFAULT false)
     */
    public function __construct($contentId, $isTest = false)
    {
        $this->contentId = $contentId;
        $this->isTest = $isTest;
        
        $this->preparePaths();
    }
    
    /**
     * Creates a zip package of the presentation.
     * Leaves the package untouched if the files of the package were not changed.
     * 
     * @return string|null   Path to the package. Null if the package was not prepared.
     */
    public function createZipPackage()
    {
        // If the presentation is not modified, we don't need to zip again
        if(!$this->isPresentationModified($timestamp)){
            return;
        }
        
        // If there are no files for given presentation, just return false (not modified)
        if(!$this->isSourceReady()){
            return;
        }
        
        // Remove the old zip file
        if(file_exists($this->zipPackagePath)){
            unlink($this->zipPackagePath);
        }
        
        $pathInfo = pathInfo($this->directoryPath);
        $dirName = $pathInfo['basename'];
        
        // Create a new zip package
        $z = new ZipArchive();
        $z->open($this->zipPackagePath, ZIPARCHIVE::CREATE);
        // Add whole folder to zip package...
        Utils::folderToZip($this->directoryPath, $z, strlen("$this->directoryPath/"));
        $z->close();
        
        return $this->zipPackagePath;
    }
    
    /**
     * Checks the modification time of the presentation's files.
     * 
     * @return bool True if the files were modified after given timestamp.
     */
    public function isPresentationModified()
    {
        $zipPackagePath = $this->zipPackagePath;
        
        // If package does not exits, we just say that files are newer...
        if(!file_exists($zipPackagePath)){
            return true;
        }
        
        // If there are no files for given presentation, just return false (not modified)
        if(!is_readable($this->directoryPath) || Utils::isDirEmpty($this->directoryPath)){
            return false;
        }
        
        $packageMTime = filemtime($zipPackagePath);

        // Number of files in directory 
        $dirNumFiles = 0;
        
        // Check each file in presentation's directory for modified time
        $path = $this->directoryPath;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
        foreach($iterator as $name => $fileObj){
            // Skip current and parent dir
            if($fileObj->getFilename() == '.' || $fileObj->getFilename() == '..'){
                continue;
            }
            // If time of the file is higher, return true
            if($fileObj->getMTime() > $packageMTime){
                return true;
            }
            $dirNumFiles ++;
        }
        
        // Compare the number of files in dir and in zip (because of deleted files)...
        $zip = new ZipArchive();
        $zip->open($zipPackagePath);
        if($zip->numFiles != $dirNumFiles){
            return true;
        }
        return false;
    }
    
    /**
     * Checks if the zip package is ready.
     * (Does not check the modification of files)
     * 
     * @return bool     Is the presentation zip package ready?
     */
    public function isReady()
    {
        return is_readable($this->getZipPackagePath());
    }
    
    /**
     * Checks if the source files of the presentation are ready.
     * Checks the existence of the index.html and preview.png
     * (Does not check the modification of files)
     *
     * @return bool     Is the directory of presentation package ready?
     */
    public function isSourceReady()
    {
        
        if(
            !is_readable($this->directoryPath) ||
            !is_dir($this->directoryPath) ||
            !is_readable($this->directoryPath.'/index.html') ||
            !is_readable($this->directoryPath.'/preview.png')
        )
        {
            return false;
        }

        return true;
    }
    
    
    /**
     * Prepares paths to directory and package of the presentation.
     * Takes in account the testing version. 
     */
    private function preparePaths()
    {
        $config = Zend_Registry::get('config');
        
        $contentPath = $config->content_article->basepath.'/'.(int)$this->contentId.'/';
        
        $test = $this->isTest ? $this->testNameSuffix : '';
        
        $this->zipPackagePath = $contentPath.$this->packagePathInContent.$test.'.zip';
        $this->directoryPath = $contentPath.$this->packagePathInContent.$test;
        
        // Fallback for old version of presentations
        $this->oldVersionsPackagePath = $contentPath.'/html5/ipad_package.zip';
    }
    
    /**
     * Gives the path of the presentation's zip file taking into account
     * the testing or non testing version and possibly old version of the inRep (html5/ipad_package.zip).
     * 
     * @return string   Path of the presentation zip package according to 
     *                  testing or non testing preset.
     */
    public function getZipPackagePath()
    {
        // Fallback to old versions of the presentation
        if(!is_readable($this->zipPackagePath) && is_readable($this->oldVersionsPackagePath)){
            return $this->oldVersionsPackagePath;
        }
        
        return $this->zipPackagePath;
    }
    
    /**
     * Gives the path of the presentation's source directory taking into account
     * the testing or non testing version.
     *
     * @return string   Path of the presentation's source directory according to
     *                  testing or non testing preset.
     */
    public function getDirectoryPath()
    {
        return $this->directoryPath;
    }
}