<?php

class Ibulletin_FileUploader_Exception extends Exception {}

/**
 *  Třída pro uploadování souborů pře webové rozhraní. Vyvtáří formulář pro
 *  upload souborů. Zpracovává samotný upload.
 *
 *  @author Martin Krčmář.
 */
class Ibulletin_FileUploader
{
	/**
	 *    Adresář, kam se budou nahrávat uploadované soubory.
	 */
	protected $uploadDir = null;

	/**
	 *    Počet uploadovaných souborů najednou. = počet inputů ve formuláři.
	 */
	protected $numFiles = 5;

	/**
	 *    Akce pro formulář.
	 */
	protected $formAction = null;

	/**
	 *    Metoda pro formulář.
	 */
	protected $formMethod = 'POST';

	/**
	 *    Zda se má pokusit o vytvoření adresáře pro upload, pokud neexistuje.
	 */
	protected $createIfNotExists = true;

	/**
	 * @var Klic z pole $_FILES posledniho zpracovaneho soboru
	 */
	protected $_current_file = null;

	/**
	 * Flashmessenger - pro posilani zprav mezi requesty pomoci session
	 * @var Zend_Controller_Action_Helper_FlashMessenger
	 */
	protected $_flashMessenger = null;

	/**
	 * Pole jmen souboru po uploadovani - pokud dojdou jmena, budou pouzita originalni jmena.
	 * Jmena jsou pouzita v poradi v jakem jsou v poli, jiz pouzita jmena jsou odstranena.
	 * @var array
	 */
	public $fileNames = array();

	/**
	 * Request objekt pro pouziti v teto tride
	 * @var Zend_Controller_Request_Abstract
	 */
	public $req = null;

	/**
	 *    Většinou se budou soubory nahrávat například pro nějaký email, nebo pro
	 *    článek. Tohle představuje identifikátor takového emailu, vloží se do
	 *    formuláře jako element hidden.
	 * Soubor pak bude nahran do podadresare $this->uploadDir pojmenovanem timto id.
	 */
	protected $id = null;

	/**
	 * @var Udrzuje iterator k posledne vyzadanemu seznamu souboru k dane ceste
	 */
	protected $directoryIterator = array();

	/**
	 * @var cesta vevnitr adresare kam se maji ukladat uploadovane soubory
	 */
	protected $targetPath = '/';

    /**
     *
     * @var array callable, callback spouští funkci po uploadu souborů, pole dle definice call_user_func() -> ($trida,'funkce')
     */
    protected $postUploadCallback = array();

    /**
     *
     * @var string
     */
    protected static $shadowFileIdentifier = '.shadow';


    /**
     * @var array mime-type restriction
     */
    static private $_allowedMimeTypes = array('text/plain');

    /**
     * @var array file extension restriction
     */
    static private $_allowedFileExtensions = array('ini','xml','css','html', 'phtml', 'php','js','txt','srt');

    /**
     * @var array base path restrictions
     */
    static private $_allowedPaths = array('pub/content','pub/skins','pub/mails', 'pub/resources', 'views/scripts');

    /**
	 * Konstruktor. Objekt se smi vytvaret jen jednou, ne na nekolika mistech v programu, protoze
	 * v konstruktoru je volano zpracovani akci z formularu fileuploderu.
	 *
	 * @param int Viz. protected $id.
	 * @param string Uploadovací adresář. Pokud nezadán, načte se adresář pro emaily z
	 *           configu.
	 * @param Zend_Controller_Request_Abstract request objekt
	 * @param bool Neprovadet pri vztvareni objektu akce obsluhujici upload/smazani souboru.
	 *             Pokud je nastaveno na true, je treba v programu zavolat $this->doFormActions()
	 * @throws Ibulletin_FileUploader_Exception
	 */
	public function __construct($id = null, $dir = null, $req = null, $noUploadActions = false)
	{
		// request object
		if(!$req) {
			$fc = Zend_Controller_Front::getInstance();
			$req = $fc->getRequest();
		}

		$this->req = $req;

		// content_id
		if($id) {
			$this->id = $id;
		}
        
       // upload dir
		if(!$dir) {
			$config = Zend_Registry::get('config');
			if (!$config->mailer->get('imgsaddr'))
			throw new Ibulletin_FileUploader_Exception('Nenalezena cesta pro upload souborů.');
			$this->setBasePath($config->mailer->imgsaddr, $this->id);
		} else {
            $this->setBasePath($dir, $this->id);
            $this->checkUploadDir();
		}

		// kontrola direktiv
		if (!self::checkUpload())
		throw new Ibulletin_FileUploader_Exception('Není zapnuta podpora uploadu souborů v php-ini.');

        // update editable file restrictions
        $this->updateFileRestrictions();

		// flash zpravy
		$this->_flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

		// spracovani ajax akcii fileuploaderu
		switch ($req->getParam('filetree')) {
			case 'list':
				$html = $this->getHtmlFileList($req->getParam('dir'));
				$json_helper = new Zend_Json();
				$res = $json_helper->encode(array(
                'error' => ($html === false),
                'html' => $html
				));

				exit($res);
		}

		// formularove spracovani akcii
		if(!$noUploadActions){
			// Provedeme akce z formularu fileuploaderu pokud byl nejaky formular odeslan
			$this->doFormActions();
		}
	}


    /**
     * checks for mime type, file extension and base path of file
     *
     * @param string $path
     * @return string TRUE if file is valid for editing
     */
    public static function isValidFile($path) {

        $found = false;

        // base extension detection
        $fpath = pathinfo($path, PATHINFO_DIRNAME);
        if (!empty($fpath)) {
            foreach (self::$_allowedPaths as $v) {
                if (preg_match('#^' . $v . '#', $fpath)) $found = true;
            }
        }

        // if not allowed path skip other checks
        if (!$found) return false;

        $found = false;
        // mime-type detection
        $fmime = Utils::getMimeType($path);
        if (!empty($fmime)) {
            foreach (self::$_allowedMimeTypes as $v) {
                if (preg_match('#' . $v . '#', $fmime)) $found = true;
            }
        }

        // skip file extension check if is valid mime
        if ($found) return true;

        // file extension detection
        $fext = pathinfo($path, PATHINFO_EXTENSION);
        if (!empty($fext)) {
            foreach (self::$_allowedFileExtensions as $v) {
                if (preg_match('#' . $v . '#', $fext)) $found = true;
            }
        }

        return $found;

    }

    /**
     * update allowed file restrictions from configuration object
     */
    private function updateFileRestrictions() {

        $config = Zend_Registry::get('config');

        // add allowed mime types from config
        if ($config->admin->editable_mime_types) {
            self::$_allowedMimeTypes = array_merge(
                self::$_allowedMimeTypes,
                array_map('trim',explode(',',$config->admin->editable_mime_types))
            );
        }

        // add allowed file extensions from config
        if ($config->admin->editable_file_extensions) {
            self::$_allowedFileExtensions = array_merge(
                self::$_allowedFileExtensions,
                array_map('trim',explode(',',$config->admin->editable_file_extensions))
            );
        }

        // add allowed file paths from config
        if ($config->admin->editable_paths) {
            self::$_allowedPaths = array_merge(
                self::$_allowedPaths,
                array_map('trim',explode(',',$config->admin->editable_paths))
            );
        }
    }

	/**
	 * overi jestli tvori cesta pro upload soucast cesty z parametru
	 * @param string cesta k adresari
	 * @return bool je cesta validni?
	 */
	private function isValidPath($abspath) {
		return strpos(rtrim($abspath,'\\/'), rtrim($this->uploadDir,'\\/')) !== false;
	}

	/**
	 *    Zjistí, zda je povolena moznost uploadovat soubory.
	 *    @return bool je povolen upload souboru?
	 */
	public static function checkUpload() {
		// zjisti se, zda je zapnuta moznost uploadovat soubory
		return ini_get('file_uploads');
	}

	/**
	 *    Metoda pro zkontrolování uploadovacího adresáře. Zda existuje a zda lze
	 *    do něho zapisovat.
	 */
	protected function checkUploadDir()
	{
		// zjisitme, zda adresar existuje
		if (!file_exists($this->uploadDir)) {
			if ($this->createIfNotExists) {
				if (!Utils::mkdir($this->uploadDir)) {
					throw new Ibulletin_FileUploader_Exception(
            'Nepodařilo se vytvořit adresář pro upload.'.$this->uploadDir);
				}
			} else {
				throw new Ibulletin_FileUploader_Exception(
          "Adresář $this->uploadDir pro upload neexistuje.");
			}
		}


		// otestujeme adresar pro upload, zda lze do neho zapisovat
		if (!is_writable($this->uploadDir))
		throw new Ibulletin_FileUploader_Exception(
        'Nepodařilo se otevřit adresář pro upload.'.$this->uploadDir);
	}

	/**
	 * set identifier
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * get identifier
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}
	/**
	 * nastavi relativni cestu k uploadovacimu adresari
	 * @param string $path
	 * @param int $id
	 */
	public function setBasePath($path, $id = null) {
		$this->uploadDir = rtrim($path,'\\/') . '/' . ($id !== null ? "$id/" : '');
	}

	/**
	 * vraci relativni cestu k uploadovacimu adresari
	 * @return string
	 */
	public function getBasePath() {
		return $this->uploadDir;
	}

	/**
	 * nastavuje relativni cestu zacinajici v upload adresari
	 * @param string
	 */
	public function setTargetPath($path) {
		$this->targetPath = $path;
	}

	/**
	 * vraci relativni cestu zacinajici v upload adresari
	 * @return string
	 */
	public function getTargetPath() {
		return $this->targetPath;
	}

    /**
     * nastavuje metodu spoustene po uploadu souboru
     * @param callback $callback
     */
    public function setPostUploadCallback($callback) {
        $this->postUploadCallback = $callback;
    }

    /**
     * vrací prirazenou metody pro callback
     * @return array
     */
    public function getPostUploadCallback() {
        return $this->postUploadCallback;
    }

    /**
     * @param $file
     * @return string
     */
    public static function getShadowFile($file) {
        return $file . self::$shadowFileIdentifier;
    }

    /**
     * @param $file
     * @return bool
     */
    public static function hasShadowFile($file) {
        return file_exists(self::getShadowFile($file));
    }

    /**
     * @param $file
     * @return bool
     */
    public static function createShadowFile($file) {

        $f = self::getShadowFile($file);
        if (!$f) return false;

        if (copy($file, $f)) {
            Utils::chmod($f, Utils::FILE_PERMISSION);
            return true;
        } else {
            return false;
        }
    }

	/**
	 * vytvoreni adresare relativne od upload adresare
	 * @param string $dir
	 * @throws Ibulletin_FileUploader_Exception
	 * @return bool pokud adresar neexistuje nebo jde mimo korene vyhodi vyjimku, jinak true
	 */
	public function mkdir($dir) {

		// expand path
		$expanded_path = rtrim($this->getBasePath(),'\\/') . '/'. ltrim($dir,'\\/');

		// validate against basepath
        if (!$this->isValidPath(realpath(dirname($expanded_path)))) {
            throw new Ibulletin_FileUploader_Exception(
				sprintf("Nelze vytvorit adresar '%s' mimo korene nebo do neexistujiciho adresare.", $expanded_path));

		// make directory
        } elseif (!Utils::mkdir($expanded_path)) {
            throw new Ibulletin_FileUploader_Exception(
				sprintf("Adresar '%s' se nepodařilo vytvořit nebo již existuje.", $expanded_path));
		}

		return true;
	}

	/**
	 * rename adresare/souboru relativne od upload adresare
	 * @param string $from
	 * @param string $to
	 * @throws Ibulletin_FileUploader_Exception
	 * @return bool pokud se nbepodarilo prejmenovani vraci false, jinak true
	 */
	public function rename($from, $to) {
		$from = trim($from);
		$to=trim($to);

		if ($from =='' || $from =='/') {
			throw new Ibulletin_FileUploader_Exception(
				sprintf("Korenovy adresar '%s' nelze přejmenovat.", $from));
		}

		// expand path
		$from = rtrim($this->getBasePath(), '\\/') . '/' . ltrim($from,'\\/');

        $to = rtrim(dirname($from),'/\\') . '/' . ltrim($to, '/\\');

        $res = false;
        $real_from = realpath($from);
        $real_to = realpath(dirname($to));

        if ($this->isValidPath($from) && $this->isValidPath(realpath(dirname($to)))) {

            if (!is_dir($real_from)) {
                if ($this->hasShadowFile($real_from)) {
                    $res = rename($this->getShadowFile($from), $to.'.shadow');
                    touch($to.'.shadow');
                    if (!$res) return false;
                }
            }
            
            // Rename
            $res = rename($from, $to);
            
            // Provedeme touch
            touch($to);
        }

        if (!$res) {
			throw new Ibulletin_FileUploader_Exception(
				sprintf("Adresar/soubor '%s' se nepodařilo přejmenovat na '%s' !", $from,$to));
		}

		return $res;
	}

	/**
	 * odstraneni souboru relativne od upload adresare
	 * @param string $path
	 * @throws Ibulletin_FileUploader_Exception
	 * @return bool pokud soubor neexistuje vyhodi vyjimku, jinak true
	 */
	public function rmfile($path) {
		// expand path
		$expanded_path = rtrim($this->getBasePath(), '\\/') . '/' . ltrim($path,'\\/');

		// cesta neexistuje nebo neni soubor, nebo je nevalidni - jde za hranice basePath
		if (!realpath($expanded_path) || !is_file($expanded_path) || !$this->isValidPath($expanded_path)) {
			throw new Ibulletin_FileUploader_Exception(
			sprintf("Soubor '%s' neexistuje.",$expanded_path));
		}
		if ($this->hasShadowFile($expanded_path)) {
            unlink($this->getShadowFile($expanded_path));
        }
        if(!unlink($expanded_path)){
			throw new Ibulletin_FileUploader_Exception(
			sprintf("Soubor '%s' se nepodařilo smazat.",$expanded_path));
		}

		return true;
	}

	/**
	 * rekurzivni smazani adresare a vsech souboru a slozek vevnitr
	 * @param string relativni cesta od korene upload adresare
	 * @throws Ibulletin_FileUploader_Exception
	 * @return bool pokud adresar neexistuje vyhodi vyjimku, jinak true
	 */

	public function rrmdir($dir = null) {
		// expand path
		$expanded_path = rtrim($this->getBasePath(),'\\/') . '/' . ltrim($dir,'\\/');

		// cesta neexistuje nebo neni adresar, nebo je nevalidni - jde za hranice basePath
		if (!realpath($expanded_path) || !is_dir($expanded_path) || !$this->isValidPath($expanded_path)) {
			throw new Ibulletin_FileUploader_Exception(
			sprintf("Adresar '%s' neexistuje.", $expanded_path));
		}

		// get file and directories list in reverse order
		$res = array($expanded_path); // add directory to delete stack

		$it = $this->getDirectoryIterator($dir);
		if ($it) {
			while ($it->valid()) {
				// full path
				$realpath = rtrim($expanded_path, '\\/') . '/' . ltrim($it->getSubPathname(), '\\/');
				// add directories to stack
				if (!$it->isDot() && $it->isDir()) {
					$res[] = $realpath;
				// delete files immediately
				} elseif ($it->isFile()) {
					if (!unlink($realpath)) throw new Ibulletin_FileUploader_Exception(
							sprintf("Soubor '%s' nelze smazat.", $realpath));
				}
				$it->next();
			}
			// natural case string sort
			natcasesort($res);
			// reverse order, deepest directories get deleted first
			$res = array_reverse($res);
		}

		foreach ($res as $v) {
			if (!rmdir($v)) throw new Ibulletin_FileUploader_Exception(
				sprintf("Adresar '%s' nelze smazat.", $v));
		}

		return true;
	}

	/**
	 * rekurzivni nastaveni prav pro plnou cestu danou prvnim parametrem
	 * @param string $dir
	 * @param int $fmask [optional] octal representation
	 * @param int $dmask [optional] octal representation
	 * @return bool
	 */
	public function rchmod($path, $fmask = null, $dmask = null ) {
		// default values
		$fmask = ($fmask === null) ? Utils::FILE_PERMISSION: $fmask;
		$dmask = ($dmask === null) ? Utils::DIR_PERMISSION: $dmask;

		// expand path
		$expanded_path = rtrim($this->getBasePath(), '\\/') .'/'. ltrim($path,'\\/');

		if (!realpath($expanded_path) || !is_dir($expanded_path) || !$this->isValidPath($expanded_path)) {
			return false;
		}

		$it = $this->getDirectoryIterator($path);
		if ($it) {
			while ($it->valid()) {
				// full path on FS
				$realpath = rtrim($expanded_path, '\\/') . '/' . ltrim($it->getSubPathname(), '\\/');
				// chmod files & directories
				if (!$it->isDot()) {
					if ($it->isFile()) {
						if (!Utils::chmod($realpath, $fmask)) return false;
					} elseif ($it->isDir()) {
						if (!Utils::chmod($realpath, $dmask)) return false;
					}
				}
				$it->next();
			}
		}
        return true;

	}

	/**
	 * rekurzivni skopirovani adresaru
	 * pokud neexistuje zdrojovy adresar nebo selze operace copy() vraci FALSE, jinak TRUE
	 *
	 * @param string $path cesta ke zdrojovemu adresari
	 * @param string $dest cesta k cilovemu adresari
	 * @return bool
	 */
	public function rcopy($path, $dest) {

		// expand path
		$expanded_path = rtrim($this->getBasePath(), '\\/') .'/'. ltrim($path,'\\/');
		$expanded_dest = rtrim($this->getBasePath(), '\\/') .'/'. ltrim($dest,'\\/');
		if (is_dir($expanded_path)) {

			Utils::mkdir($expanded_dest, Utils::DIR_PERMISSION, false);

			$objects = scandir($expanded_path);
			if (sizeof($objects) > 0) {
				foreach ($objects as $file) {

					if (in_array($file, array('.', '..'))) {
						continue;
					}

					if (is_dir($expanded_path.'/'.$file)) {
						$this->rcopy($path.'/'.$file, $dest.'/'.$file);
					} else {
						copy($expanded_path.'/'.$file, $expanded_dest.'/'.$file);
					}
				}
			}

			return true;

		} elseif (is_file($expanded_path)) {
			return copy($expanded_path, $expanded_dest);
		} else {
			return false;
		}

	}

	/**
	 * download souboru nebo adresare relativne od upload adresare
	 * nastavi hlavicky a na vystup posle attachment ze souborem
	 * v pripade adresare vraci zip
	 *
	 * @param string $path relativni cesta
	 * @throws Ibulletin_FileUploader_Exception, Zend_Controller_Action_Exception
	 *
	 * @return void
	 */
	public function download($path) {

        Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->setNeverRender(true);
        Env::disableOutput();

		$full_uri = rtrim($this->getBasePath(),'\\/') . '/' . ltrim($path,'\\/');
        $shadow_uri = $this->hasShadowFile($full_uri) ? $this->getShadowFile($full_uri) : $full_uri;

		if (!file_exists($full_uri)) {
			throw new Zend_Controller_Action_Exception('This path does not exist', 404);
			return;
		}

		if (is_file($full_uri)) { // file
			header('Content-Description: File Transfer');
			//header('Content-Type: application/octet-stream');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Disposition: attachment; filename='.basename($path));

			$mime = Utils::getMimeType($full_uri);
			header('Content-Type: '.$mime);
			header('Content-Length: ' . filesize($shadow_uri));

            ob_end_clean();
			readfile($shadow_uri);  // same as full_uri if shadow is not present

		} else { // directory

			$zip = new ZipArchive();

			$zip_file = (basename($path) != '' ? basename($path) : 'directory') . '.zip';
			$zip_tempfile = $tmpfname = tempnam(sys_get_temp_dir(), "zipdir");

			if ($zip->open($zip_tempfile, ZIPARCHIVE::CREATE) !== true) {
				throw new Ibulletin_FileUploader_Exception(
					sprintf("Could not open archive %s.",$zip_tempfile));
			}

			// list of files to add

			$it = $this->getDirectoryIterator($path);
			if ($it) {
				while ($it->valid()) {

					// relative path in zip
					$name = $it->getSubPathname();
					// full path on FS
					$realpath = rtrim($full_uri, '\\/') . '/' . ltrim($it->getSubPathname(), '\\/');

                    if (preg_match('/'.preg_quote(self::$shadowFileIdentifier).'$/',$name)) {
                        $it->next();
                        continue;
                    }

                    if ($this->hasShadowFile($realpath)) {
                        $realpath = $this->getShadowFile($realpath);
                    }

					// add files & directories to zip
					if (!$it->isDot()) {
						if ($it->isFile()) {
							if (!$zip->addFile($realpath, $name)) throw new Ibulletin_FileUploader_Exception(
								sprintf("Could not add file '%s' to archive '%s'.",$realpath, $zip_tempfile));
						} elseif ($it->isDir()) {
							if ($name && !$zip->addEmptyDir($name)) throw new Ibulletin_FileUploader_Exception(
								sprintf("Could not add directory '%s' to archive '%s'.",$name, $zip_tempfile));
						}
					}
					$it->next();
				}
			}

			// close and save archive
			$zip->close();

			header('Content-Description: File Transfer');
			//header('Content-Type: application/octet-stream');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');

			//header('Content-Type: application/octet-stream'); //application/zip
			header('Content-Length: ' . filesize($zip_tempfile));
			header('Content-Disposition: attachment; filename='.$zip_file);

            ob_end_clean();
			readfile($zip_tempfile);
			unlink($zip_tempfile);

		}
		exit;

	}

	public function getDirectoryList($path = null) {
		// Pokud jeste neexistuje adresar pro uploady, vytvorime ho
		$this->checkUploadDir();

		$res = array('/' => '/');

		$it = $this->getDirectoryIterator($path);
		if ($it) {
			while ($it->valid()) {
				if (!$it->isDot() && $it->isDir()) {
					$rel_path = '/' . ltrim($it->getSubPathname(), '\\/');
					$res[$rel_path] = $rel_path;
				}
				$it->next();
			}
			// natural case string order
			natcasesort($res);
		}

		return $res;
	}

	/**
	 *    Vrátí formulář pro vytvoreni adresare.
	 */
	public function getAddDirForm()
	{

		$texts = Ibulletin_Texts::getSet('admin.file_uploader.add');

		$form = new Form();
		$form->setMethod($this->formMethod);

		if ($this->formAction !== null)
		$form->setAction($this->formAction);

		$text_input = new Zend_Form_Element_Text("name");
		$text_input->setLabel($texts->name);

		$select_input = new Zend_Form_Element_Select('dir');
		$select_input->addMultiOptions($this->getDirectoryList());
		$select_input->setAttrib('style', 'width:155px');
		$select_input->setLabel($texts->dir);

		// hidden ID
		if ($this->id !== null)
		{
			$id = new Zend_Form_Element_Hidden('id');
			$id->setValue($this->id);
			$form->addElement($id);
		}

		$send = new Zend_Form_Element_Submit('add_dir');
		$send->setLabel($texts->submit);
        $send->setAttrib('class','btn-primary');

		$form->addElements(array(
			$text_input,
			$select_input,
			$send
		));

		return $form;
	}

	/**
     *    Vrátí formulář pro upload souborů.
     */
    public function getUploadForm() {

        $texts = Ibulletin_Texts::getSet('admin.file_uploader.upload');

        $form = new Form_Inline();

        $form->setMethod($this->formMethod);
        $form->setAttrib('class','dropzone');
        $form->setAction('#');
        $form->setAttrib('id', 'fileuploader-dropzone');
        $form->setOptions(array(
            'enctype' => 'multipart/form-data'
        ));

        if ($this->formAction !== null)
            $form->setAction($this->formAction);

        $maxUploadSize = min(
                self::letToNum(ini_get('post_max_size')), self::letToNum(ini_get('upload_max_filesize'))
        );
        $maxUploadSizeReadable = (int) ($maxUploadSize / 1048576);

        $form->setDescription(sprintf($texts->legend, $maxUploadSizeReadable));

        /*
          $maxSize = new Zend_Form_Element_Hidden('MAX_FILE_SIZE');
          $maxSize->setValue($maxUploadSize);
         */

        $file = new Zend_Form_Element_File("0");
        $file->setBelongsTo("file");
        $file->setAttrib('id','file');
        $file->setLabel($texts->file);
        // TODO chce to validator, aby byl zadan alespon jeden soubor
        /*
          $emptyValidator = new Zend_Validate_NotEmpty();
          $emptyValidator->setMessage(
          'Musíte zadat soubor.',
          Zend_Validate_NotEmpty::IS_EMPTY
          );
          $file->addValidator($emptyValidator);
          $file->setRequired(true);
         */
        $file->setOptions(array('size' => 50));

        $form->addElement($file);


        $file->setDecorators(array(
            'File',
            'Label',
            'Errors',
            array('HtmlTag', array('tag' => 'div', 'class' => 'fallback'))
        ));

        $select_input = new Zend_Form_Element_Select('upl_dir');
        $select_input->addMultiOptions($this->getDirectoryList());
        $select_input->setAttrib('style', 'width:155px');
        $select_input->setLabel($texts->upl_dir);

        $form->addElements(array($select_input));

        // hidden ID
        if ($this->id !== null) {
            $id = new Zend_Form_Element_Hidden('id');
            $id->setValue($this->id);
            $form->addElement($id);
        }

        $drop = new Zend_Form_Element_Note('dropzone_container');
        $drop->setValue('<div class="dropzone-previews"><span class="drop-label">'.$texts->dropfiles.'</span></div>');


        $addfiles = new Zend_Form_Element_Button('addfiles');
        $addfiles->setIgnore(true)
                ->setAttrib('class', 'btn-success')
                ->setAttrib('style', 'margin-bottom:10px;')
                ->setAttrib('id', 'dropzone_add_files')
                ->setAttrib('escape', false)
                ->setLabel('<i class="icon-plus icon-white"></i><span>&nbsp;'.$texts->addfiles.'</span>');

        $send = new Zend_Form_Element_Submit('file_send');
        $send->setLabel($texts->submit);
        $send->setAttrib('style', 'margin-top:0px;margin-bottom:10px;');
        $send->setAttrib('class', 'btn-primary');

        $form->addElements(array($addfiles, $send, $drop));

        return $form;
    }

    /**
	 *    Provede upload souborů. Jejich přemístění z temp adresáře do adresáře,
	 *    kam patří.
	 *
	 * Pokud je nejaky zaznam v $this->fileNames, pouzije jej jako jmeno dalsiho souboru a odstrani.
	 *
	 *    @param Pole $_FILES, které obsahuje informace o uploadovaných souborech.
	 */
	public function uploadFiles($files)
	{
		$this->checkUploadDir();    // zkontroluje se adresar pro upload
		$errors = Array();          // pole, do ktereho se ukladaji chyby

		while(1){
			$filename = null;
			// Pokusime se pouzit zadane jmeno pro soubor
			if(!empty($this->fileNames)){
				reset($this->fileNames);
				$key = key($this->fileNames);
				$filename = array($this->fileNames[$key]);
				unset($this->fileNames[$key]);
			}
			$out = $this->uploadFile($filename);
			if($out === false){
				break;
			}
			elseif($out === true){
				continue;
			}
			else{
				array_push($errors,$out);
			}
		}

		/*

		foreach ($_FILES['files']['error'] as $key => $error)
		{
		switch ($error)
		{
		case UPLOAD_ERR_OK :
		$tmpName = $_FILES['files']['tmp_name'][$key];
		$name = basename($_FILES['files']['name'][$key]);

		if (!is_uploaded_file($tmpName))
		throw new Ibulletin_FileUploader_Exception(
		"Soubor $name se nepodařilo uploadovat.");

		if (!move_uploaded_file($tmpName, "$this->uploadDir/$name"))
		throw new Ibulletin_FileUploader_Exception(
		"Soubor $name se nepodařilo uploadovat.");
		break;

		case UPLOAD_ERR_INI_SIZE :
		case UPLOAD_ERR_FORM_SIZE :
		array_push($errors,
		'Soubor '.$_FILES['files']['name'][$key].' překročil
		maximální povolenou velikost.');

		case UPLOAD_ERR_NO_FILE :
		break;

		default :
		throw new Ibulletin_FileUploader_Exception(
		'Soubor '.$_FILES['files']['name'][$key].' se nepodařilo
		uploadovat, chyba: '.$error);
		}
		}
		*/

		return $errors;
	}

	/**
	 * Ulozi z uploadovanych souboru jeden soubor, je mozne nastavit mu jmeno.
	 *
	 * TODO: V pripade zmeny implementace pres Zend_File_Transfer je potreba rollbacknout
	 * zmenu v Zend_Form_Decorator_File
	 *
	 * @param  array   array(jmeno_souboru, pripona_souboru), pokud je zadano
	 *                 jen jmeno, pripona zustane shodna s puvodni, pokud je null,
	 *                 je pouzito puvodni jmeno
	 * @return mixed   - false pokud uz neexistuje dalsi soubor pro ulozeni
	 *                 - true pokud ulozeni probehlo v poradku
	 *                 - string s popisem chyby pokud nastala chyba
	 * @throws Ibulletin_FileUploader_Exception v pripade chyby
	 */
	public function uploadFile($name = null){
		if($this->_current_file === true){
			// Vsechny soubory jsou hotove, koncime
			return false;
		}
		if($this->_current_file === null){
			reset($_FILES['file']['error']);
		}
		$this->_current_file = key($_FILES['file']['error']);

		$key = $this->_current_file;
		$error = $_FILES['file']['error'][$key];
		$error_out = null;

		if(empty($name) || !is_array($name) || count($name) > 2){
			$name = basename($_FILES['file']['name'][$key]);
		}
		elseif(count($name) == 2){
			if(!empty($name[1])){
				$name = join('.', $name);
			}
			else{
				$name = $name[0];
			}
		}
		elseif(count($name) == 1){
			// Ziskame priponu originalniho souboru a pridame ji k zadanemu jmenu
			$orig_name = basename($_FILES['file']['name'][$key]);
			$orig_name = explode('.', $orig_name);
			end($orig_name);
			$ext = current($orig_name);
			$name = current($name).'.'.$ext;
		}

		//get extension
		$arr=explode('.', $name);
		$ext = end($arr);

		// Musime zaridit aby po fixpath zustalo pocatecni lomitko tak jak bylo
		$targetDir = rtrim($this->getBasePath(),'\\/') . '/' .ltrim($this->getTargetPath(), '\\/');
		$targetFile = rtrim($targetDir,'\\/') . '/' . ltrim($name,'\\/');

		switch ($error)
		{
			case UPLOAD_ERR_OK :
				$tmpName = $_FILES['file']['tmp_name'][$key];

				if (!is_uploaded_file($tmpName))
				throw new Ibulletin_FileUploader_Exception(
                        "Soubor $name se nepodařilo uploadovat, zdrojový soubor není uploadovaným souborem.");

                // remove shadow file if exists prior to uploading
                if ($this->hasShadowFile($targetFile)) {
                    unlink($this->getShadowFile($targetFile));
                }                
				if (!copy($tmpName, $targetFile)){
					throw new Ibulletin_FileUploader_Exception(
                        "Soubor $name se nepodařilo okopírovat z '$tmpName' do umisteni '$targetFile' {$this->getBasePath()}.");
				}

                // set permissions
                Utils::chmod($targetFile, Utils::FILE_PERMISSION);
                touch($targetFile);

				// auto-extract from .zip
				if (strtolower($ext) == 'zip') {
					$zip = new ZipArchive;
					if ($zip->open($targetFile) === true) {

                        // get file list before extraction
                        for( $i = 0; $i < $zip->numFiles; $i++ ){
                            $stat = $zip->statIndex( $i );
                            // skip directories
                            if (preg_match('/\/$/',$stat['name'])) continue;

                            $p = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $stat['name'];
                            // delete corresponding shadow files if exists
                            if ($this->hasShadowFile($p)) {
                                unlink($this->getShadowFile($p));
                            }
                        }

                        // continue with extraction
						$zip->extractTo($targetDir);
						$zip->close();
						unlink($targetFile);
						$this->rchmod($this->getTargetPath());
					} else {
						unlink($targetFile);
						throw new Ibulletin_FileUploader_Exception(
                        "Soubor $name se nepodařilo rozbalit.");
					}
				}
				// auto-extract from .tar.gz
				elseif (strtolower($ext) == 'gz') {
                    // list content of archive | exclude directories | strip './'
                    $cmd = 'tar tf '.$targetFile.' | grep -v /$ | cut -c 3-';
                    exec($cmd, $op);

                    // get file list before extraction
                    foreach ($op as $f){
                        $p = rtrim($targetDir, '\\/') . DIRECTORY_SEPARATOR . $f;
                        // delete corresponding shadow files if exists
                        if ($this->hasShadowFile($p)) {
                            unlink($this->getShadowFile($p));
                        }
                    }

                    $cmd = 'gunzip -c '.$targetFile.' | tar xf - -C '.$targetDir;
					$cmd_res = system($cmd);
					unlink($targetFile);
					if ($cmd_res != 0) {
						throw new Ibulletin_FileUploader_Exception(
                        "Soubor $name se nepodařilo rozbalit. Chyba: $cmd_res");
					} else {
						$this->rchmod($this->getTargetPath());
					}

				}

				break;

			case UPLOAD_ERR_INI_SIZE :
			case UPLOAD_ERR_FORM_SIZE :
				$error_out =
                    'Soubor '.$_FILES['file']['name'][$key].' překročil
                    maximální povolenou velikost.';

			case UPLOAD_ERR_NO_FILE :
				break;

			default :
				throw new Ibulletin_FileUploader_Exception(
                    'Soubor '.$_FILES['file']['name'][$key].' se nepodařilo
                    uploadovat, chyba: '.$error);
		}

		// Posuneme se na dalsi prvek
		if(next($_FILES['file']['error']) === false){
			// Uz neni co zpracovavat, nastavime info do _current_file
			$this->_current_file = true;
		}

		if($error_out === null){
			return true;
		}
		else{
			return $error_out;
		}
	}

	/**
	 * Provede akce vyvolane odeslanim formulare uploadu soubouru nebo pridani adresare pokud
	 * jsou potreba.
	 *
	 * file_send | add_dir | deldir | delfile | rename
	 */
	public function doFormActions()
	{

		$texts = Ibulletin_Texts::getSet('admin.file_uploader');
		$req = $this->req;

		// Ulozeni nove uploadovanych souboru
		if($req->__isSet('file_send')){
			$dir = $req->getParam('upl_dir', '');

			// zkontrolovat platnost formulare, tj. zda vyplnen nazev souboru
			$this->setTargetPath($dir);
            
			$fileForm = $this->getUploadForm();
			$allEmpty = true;

			foreach ($_FILES['file']['name'] as $name) {
                if (!empty($name)) {
                    $allEmpty = false;
                }
            }
            
			if(!$allEmpty)
			{
				try
				{
					$errors = $this->uploadFiles($_FILES);
                    if (!empty($errors) && is_array($errors))
					{
						$this->infoMessage($texts->notuploaded, 'warning');
						foreach ($errors as $error) {
							$this->infoMessage($error,'error');
						}
					}
					else
					{
						$this->infoMessage($texts->uploaded);

                        //callback po uploadu
                        if ($this->getPostUploadCallback()) {
                            call_user_func($this->getPostUploadCallback());
                        }
					}
				}
				catch (Ibulletin_FileUploader_Exception $e)
				{
					$this->infoMessage($texts->error, 'error');
					Phc_ErrorLog::error('ContentarticleController, editAction, file.', $e);
				}
			}
			else
			{
				// vypsat chybu, ze musi byt zadan alespon jeden soubor
				$this->infoMessage($texts->empty,'warning');
			}

			$this->performRedirect(array('dir' => null, 'file_send' => null));
		}
		// Vytvoreni noveho adresare
		elseif($req->__isSet('add_dir')){
			$name = $req->name;
			$dir = $req->dir;
			$path = rtrim($dir,'\\/') . '/' . ltrim($name,'\\/');

			try {
				$this->mkdir($path);
				$this->infoMessage($texts->adddir_success, 'success', array($path));

			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->adddir_error, 'error');
				Phc_ErrorLog::error('addcontentdirAction, Nepodarilo se vytvořit adresář. Puvodni vyjimka:'."\n", $e);
			}

			$this->performRedirect(array('name' => null, 'upl_dir' => null, 'add_dir' => null));
		}
		// Smazani souboru
		elseif($req->__isSet('delfile')){
			$filename = $req->getParam('delfile');

			try {
				$this->rmfile($filename);
				$this->infoMessage($texts->delfile_success, 'success', array($filename));
			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->delfile_error, 'error');
				Phc_ErrorLog::error('removecontentfileAction, Nepodařilo se mazání souboru. Puvodni vyjimka:'."\n", $e);
			}

            //callback po delete
            if ($this->getPostUploadCallback()) {
                call_user_func($this->getPostUploadCallback());
            }
			$this->performRedirect(array('delfile' => null));
		}
		// Prejmenovani/move adresaru/souboru
		elseif($req->__isSet('rename')){
			$filename = $req->getParam('rename');
			$newname = $req->getParam('to');

			try {
				$this->rename($filename, $newname);
				$this->infoMessage($texts->rename_success,'success',array($filename,$newname));
			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->rename_error, 'error');
				Phc_ErrorLog::error('renamecontentfileAction, Nepodařilo se přejmenovat soubor. Puvodni vyjimka:'."\n", $e);
			}
            //callback po rename
            if ($this->getPostUploadCallback()) {
                call_user_func($this->getPostUploadCallback());
            }
			$this->performRedirect(array('to' => null, 'rename' => null));
		}
		// Smazani adresare
		elseif($req->__isSet('deldir')){
			$filename = $req->getParam('deldir');

			try {
				$this->rrmdir($filename);
				$this->infoMessage($texts->deldir_success, 'success', array($filename));

			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->deldir_error, 'error');
				Phc_ErrorLog::error('removecontentdirAction, Nepodařilo se smazat adresář. Puvodni vyjimka:'."\n",
				$e);
			}
			$this->performRedirect(array('deldir' => null));
		}
		elseif($req->__isSet('downloadfile')){
			$name = $req->downloadfile;

			try {
				$this->download($name);
				$this->infoMessage($texts->download_success, 'success', array($name));

			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->download_error, 'error');
				Phc_ErrorLog::error('downloadfileAction, Nepodařilo se stáhnout soubor. Puvodni vyjimka:'."\n", $e);
			}

			$this->performRedirect(array('name' => null, 'downloadfile' => null));
		}
		elseif($req->__isSet('downloaddir')){
			$name = $req->downloaddir;
			try {
				$this->download($name, false);
				$this->infoMessage($texts->download_success, 'success', array($name));

			} catch (Ibulletin_FileUploader_Exception $e) {
				$this->infoMessage($texts->download_error, 'error');
				Phc_ErrorLog::error('downloaddirAction, Nepodarilo se stáhnout zip soubor. Puvodni vyjimka:'."\n", $e);
			}

			$this->performRedirect(array('name' => null, 'downloaddir' => null));
		}
	}

	/**
	 * Vraci iterator k adresarove strukture
	 * parametr cesty je relativni
	 *
	 * @return RecursiveIteratorIterator | array()
	 */
	public function getDirectoryIterator($path = null, $flag = RecursiveIteratorIterator::SELF_FIRST) {

		if ($path == null) {
			$path = $this->getBasePath();
		} else {
			$path = rtrim($this->getBasePath(),'\\/') . '/' . ltrim($path,'\\/');
		}

		if (is_readable($path)) {
			// cached result
			if (empty($this->directoryIterator[$path])) {
				$this->directoryIterator[$path] = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path), $flag);
			}
			$this->directoryIterator[$path]->rewind();
			return $this->directoryIterator[$path];
		} else {
			return array();
		}
	}

	/**
	 * Vraci HTML obal pro filetree na ktery je zaveseny javascript
	 * @param string $elem_id [optional] id atribut obalovaciho tagu
	 * @return string
	 */
	public function getFileTree($elem_id = 'fileTree') {
		return '<div class="filetree" id="'.$elem_id.'"></div>';
	}

	/**
	 * Vraci HTML ul-li blok vsech souboru a adresaru v hloubce 1
	 * @return string
	 */
	public function getHtmlFileList($dir = '') {
		// expand path
		$real_dir = rtrim($this->getBasePath(), '\\/') .'/'. ltrim($dir,'\\/');

        $id_arr = ($this->id) ? array('id' => $this->id): array();
		$res_dir = '';
		$res_file = '';
		$urlHlpr = new Zend_View_Helper_Url();

		$texts = Ibulletin_Texts::getSet('admin.file_uploader.delete');

		if (!$this->isValidPath($real_dir) || !is_dir($real_dir)) throw new Ibulletin_FileUploader_Exception(
		sprintf("Cesta '%s' je nevalidni nebo neexistuje.", $real_dir));

		$files = scandir($real_dir); /* use scandir() instead of getDirectoryIterator() iterator (we need one level deep in hierarchy ) */

		natcasesort($files);
		if( count($files) > 2 ) {
            // build link list TODO: view script for fileuploader filetree list
			foreach( $files as $file ) {
				$real_file = rtrim($real_dir,'\\/') . '/' . ltrim($file,'\\/');
                // file, excluding shadow files
				if ( file_exists($real_file) && $file != '.' && $file != '..' &&
                    !preg_match('/'.preg_quote(self::$shadowFileIdentifier).'$/', $file)) {

					$link_rel =  htmlspecialchars(rtrim($dir,'\\/') . '/' . ltrim($file,'\\/'), ENT_QUOTES, 'UTF-8');
                    $edit_url = $urlHlpr->url(array_merge(array('controller' => 'edit','module' => 'admin','control'=>$this->req->getControllerName()),$id_arr),'default', true).'?path='.urlencode($real_file);

                    $title='('.filesize($real_file).' bytes) '.'modified '.date('H:i:s d/m/Y',filemtime($real_file));
					// directories
					if (is_dir($real_file)) {
                        $title='';
						//$urlHlpr->url(array('get_filetree' => NULL, 'remove_dir' => 'doit', 'name' => urlencode($dir . $file) ))
						$res_dir .= '<li class="directory collapsed">
              <a title="'.$title.'" data-basename="'.$file.'" class="link" href="#"  rel="' .$link_rel. '">' .$file. '</a>
              <div class="toolbar">
              	<a title="download" class="icon-download" style="margin-top:-16px; margin-right:16px;float:right"  href="?downloaddir='.urlencode($link_rel).'" rel="' .$link_rel. '"></a>
              	<a onclick="return confirm(\''.sprintf($texts->confirm, $file).'\');" title="delete" class="icon-remove" style="margin-top:-16px;float:right" href="?deldir='.urlencode($link_rel).'" rel="' .$link_rel. '"></a>
              </div>
              </li>';
					}
					// files
					elseif (is_file($real_file)) {
						$ext = preg_replace('/^.*\./', '', $file);
                        $popover = '';
                        if (in_array(strtolower($ext), array('jpg', 'png', 'gif'))) {
                            $res_file .= '<li class="file ext_' . $ext . '">
                            <a href="' . $real_file . '" title="'. $file . ' ' . $title . '" data-basename="' . $file . '" class="link popover_img" rel="' . $link_rel . '">' . $file . '</a>';
                        } else {
                            $res_file .= '<li class="file ext_' . $ext . '">
                            <a href="' . $real_file . '" title="' . $title . '" data-basename="' . $file . '" class="link" rel="' . $link_rel . '">' . $file . '</a>';
                        }
                        if ($this->isValidFile($real_file)) {
                            $res_file .= '
              <div class="toolbar">
              	<a target="_blank" title="edit" class="icon-edit" style="margin-top:-16px; margin-right:32px;float:right"  href="' . $edit_url . '" rel="' . $link_rel . '"></a>';
                        }
                        $res_file.='<a title="download" class="icon-download" style="margin-top:-16px; margin-right:16px;float:right"  href="?downloadfile=' . urlencode($link_rel) . '" rel="' . $link_rel . '"></a>
              	<a onclick="return confirm(\'' . sprintf($texts->confirm, $file) . '\');" title="delete" class="icon-remove" style="margin-top:-16px; float:right" href="?delfile=' . urlencode($link_rel) . '" rel="' . $link_rel . '"></a>
              </div></li>';
//<a title="edit" class="icon-edit" style="margin-top:-16px; margin-right:16px;float:right"  href="'.$real_file.'" target="_blank"></a>
					}
				}

			}

			// prepare html result
			return "<ul class=\"jqueryFileTree\" style=\"display: none;\">" . $res_dir . $res_file . '</ul>';
		} else {
			return '';
		}

	}



	/**
	 * Redirectuje bud na url zadanou pomoci setRedirectUrl, nebo na adresu bez parametru,
	 * ktere byly potreba pro provedeni dane akce (vytvorit adresar, smazat soubor, smazat adresar)
	 * url si v tomto pripade zada akce sama
	 */
	public function performRedirect($urlParams = null, $route = null)
	{
		$redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
		$urlHlpr = new Zend_View_Helper_Url();

		if(!is_array($urlParams) && empty($route)){
			// Redirectujeme na $this->redirectUrl
			$redirector->gotoUrlAndExit($this->redirectUrl);
		}
		else{
			if(empty($route)){
				$redirector->gotoRouteAndExit($urlParams);
			}
			else{
				$redirector->gotoRouteAndExit($urlParams, $route);
			}
		}

	}


	/**
	 * Nastavi URL pro redirect po akcich formulare jako je nahrani souboru, smazani souboru atd.
	 *
	 * @param string    $url
	 */
	public function setRedirectUrl($url)
	{
		$this->redirectUrl = $url;
	}


	/**
	 *    Metoda pro nastavení počtu souborů, které se mohou uploadovat.
	 *
	 *    @param Počet.
	 */
	public function setNumFiles($num)
	{
		if (is_numeric($num) && $num >= 1)
		$this->numFiles = $num;
	}

	/**
	 *    Vrátí počet souborů k uploadu.
	 */
	public function getNumFiles()
	{
		return $this->numRows;
	}

	/**
	 *    Nastaví akci pro formulář.
	 */
	public function setFormAction($url)
	{
		$this->formAction = $url;
	}

	/**
	 *    Vrátí akci pro formulář.
	 */
	public function getFormAction()
	{
		return $this->formAction;
	}

	public function setCreateIfNotExists($flag)
	{
		$this->createIfNotExists = $flag;
	}

	public function getCreateIfNotExists()
	{
		return $this->createIfNotExists;
	}

	public function infoMessage($message, $type = 'success', $params = array()) {

		if(!is_array($params)){
			$params = array($params);
		}

		$msg = vsprintf($message, $params);

		$data = array('type' => $type, 'message' => $msg);
		$this->_flashMessenger->addMessage($data);

		return $msg;

	}
	/**
	 *    Metoda pro převední velikosti souboru, které je použito v php-ini na
	 *    čitelnější výstup.
	 *
	 *    @param Velikost souboru.
	 */
	protected static function letToNum($v)
	{
		$l = substr($v, -1);
		$ret = substr($v, 0, -1);
		switch(strtoupper($l))
		{
			case 'P':
				$ret *= 1024;
			case 'T':
				$ret *= 1024;
			case 'G':
				$ret *= 1024;
			case 'M':
				$ret *= 1024;
			case 'K':
				$ret *= 1024;
				break;
		}

		return $ret;
	}


}
?>
