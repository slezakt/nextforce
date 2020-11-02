<?php
/**
 * Modul pro spravu a pridavani slidu s otazkami a odpovedmi.
 *
 * @author Andrej Litvaj, <andrej.litvaj@pearshealthcyber.com>
 */
class Admin_SlidesController extends Ibulletin_Admin_BaseControllerAbstract
{
	
	/**
	 * typy otazek
	 * @var array
	 */
	private $question_types;
	
	/**
     * Flashmessenger - pro posilani zprav mezi requesty pomoci session
     * @var Zend_Controller_Action_Helper_FlashMessenger
     */
    protected $_flashMessenger = null;
	
	/**
     * URL helper - view helper na vytvareni URL adres
     * @var Zend_View_Helper_Url
     */	
	 protected $_url;



	/**
	 *	Spusti se pri vytvoreni teto tridy
	 */
	public function init()
	{
		parent::init();
		$this->submenuAll = array(
				'index' => array('title' => $this->texts->submenu_list, 'params' => array('action' => null), 'noreset' => false),                
		);
		
        $this->question_types = Questions::getTypes();
                
		//Zend_Loader::loadClass('Zend_View_Helper_Url');
		$this->_flashMessenger = $this->_helper->getHelper('FlashMessenger');
		$this->_url = new Zend_View_Helper_Url();		
		
		// registrace akcii volanych Ajaxem z formulare 
		if ($this->getRequest()->isXmlHttpRequest()) {
			$this->_helper->viewRenderer->setNoRender();
		    $ajaxContext = $this->_helper->getHelper('AjaxContext');
		    $ajaxContext->addActionContext('addQuestion', 'json')
						->addActionContext('addAnswer', 'json')
						->addActionContext('addSlide', 'json')
		    			->initContext();
		}
	}
	

	/**
	 * Zajistuje zobrazeni formulare pro vyber content_id .	  
	 */
	public function indexAction()
	{
	    $req = $this->getRequest();
			
	
		// vytvorime form pro vyber content_id
		$contentData = array(0 => '---') + $this->getContentData();
		$contentForm = $this->getEditContentForm($contentData);		

		// prisel POST?		
		if ($req->isPost() && $req->getParam('content_submit')) {				
				if ($contentForm->isValid($req->getPost())) {
					$formData=$contentForm->getValues();					 
					// redirect na edit podle content_id
					$params = array('content_id' => $formData['content_id']);										
					$this->_helper->redirector('edit', NULL, NULL, $params);
				} else {
					$this->view->errMessage = 'Vyberte obsah.';										
				}
			}
			
		// view
		
		$this->view->form = $contentForm;		
		
	}
	
	/**
	 * Zajistuje zobrazeni formulare pro spravu/editaci slidu.	  
	 */
	public function editAction() {

        $req = $this->getRequest();
		$content_id = $req->getParam('content_id', NULL);
		
		//validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {
			// redirect na index								
			$this->_helper->redirector('index');
		}
        
        $this->moduleMenu->addItem($this->texts->edit->submenu_edit, array('action'=>'edit','content_id'=>$content_id), null, true,'edit'); 
        $this->moduleMenu->setCurrentLocation('edit');
		
		// contentform - doupravime action,	nastavime default hodnotu		
		$contentForm = $this->getEditContentForm($contentData);		
		$contentForm->setDefaults(array('content_id'=>$content_id));		
		
		$contentForm->setAction($this->_url->url(
			array('action' => 'index', 'content_id' => NULL)) );
		
		// slideform - naplnime datami z db				
		$dbData = Slides::getSlidesData($content_id);
		$slideForm = $this->getSlideForm($dbData);
		// prisel POST?
		if ($req->isPost() && $req->__isset('slide_submit')) {
			if ($slideForm->isValid($req->getPost())) { // overime nan POST
				// ulozime do DB
				$is_saved = $this->saveForm($slideForm);
                if ($is_saved) {
                    $this->infoMessage($this->texts->edit->saved,'success');
                } else {
                    $this->infoMessage($this->texts->edit->notsaved,'error');
                }

				// redirect pres content_id
				$params = array('content_id' => $content_id);
				$this->_helper->redirector('edit',NULL, NULL, $params);
			} else {				
				$this->infoMessage($this->texts->edit->notvalid,'warning');
			}
		}
		
        Ibulletin_Js::addJsFile('admin/slides.js'); // custom client script
        
		// view
		$this->view->contentForm = $contentForm;
		$this->view->slideForm = $slideForm;
		$this->view->messages = $this->_flashMessenger->getMessages();
		
	}

	/**
	 * Ajax akce ktera vraci novy zaznam pro odpoved
	 */
	public function addanswerAction() {

		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		$slide_id = $req->getParam('slide_id', NULL);
		$question_id = $req->getParam('question_id', NULL);
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->_helper->viewRenderer->setNoRender();
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//store to db
		$model = new Questions($content_id);		
		$nearestNum = $model->getNearestAnswerNum($question_id);	
		$answer_id = $model->editAnswerTitle($question_id, $nearestNum, NULL);		
		
		// non ajax method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}
		
		// ajax method
		// get subform and fill it with 'new' values
		$sub = $this->getAnswerSubForm(array('num' => $nearestNum, 'id' => $answer_id, 'text' => ''), $slide_id, $question_id);

        $sub->setAttrib('id', 'answer_'.$answer_id);
        $sub->setElementsBelongTo('slide_'.$slide_id.'[slide_'.$slide_id.'][question_'.$question_id.'][answer_'.$answer_id.']');

		// What is returned thru ajax
		$this->_helper->viewRenderer->setNoRender();
		echo Zend_Json::encode(array('question_id' => $question_id, 'answer_id' => $answer_id, 'data' => $sub->__toString()));

	}
	
	/**
	 * Ajax akce ktera maze odpoved
	 */
	public function deleteanswerAction() {

		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		$answer_id = $req->getParam('answer_id', NULL);		
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//delete from db
		$model = new Questions($content_id);		
		$res = $model->deleteAnswer($answer_id);	
		
		// non ajax method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}		
		// ajax method
		$this->_helper->viewRenderer->setNoRender();
		if ($res === FALSE) {
			echo Zend_Json::encode(array('deleted' => FALSE));
		} else {
			// on success
			echo Zend_Json::encode(array('deleted' => TRUE, 'answer_id' => $answer_id));			
		} 

	}			

	/**
	 * Ajax akce ktera vraci novy zaznam pro otazku
	 */
	public function addquestionAction() {
	
		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		$slide_id = $req->getParam('slide_id', NULL);
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->_helper->viewRenderer->setNoRender();
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//store to db
		$model = new Questions($content_id);		
		$nearestNum = $model->getNearestQuestionNum($slide_id);
		$question_id = $model->editQuestionTitle($content_id, $slide_id, $nearestNum, 'r');		
		
		// non ajax  method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}
		
		// ajax method
		
		// get subform and fill it with 'new' values		
		$sub = $this->getQuestionSubForm(array('num' => $nearestNum, 'id' => $question_id, 'text' => ''), $slide_id);

        //$sub->setElementsBelongTo('slide_'.$slide_id);
        $sub->setAttrib('id', 'question_'.$question_id);
        $sub->setElementsBelongTo('slide_'.$slide_id.'[slide_'.$slide_id.'][question_'.$question_id.']');

		 // What is returned thru ajax
		 $this->_helper->viewRenderer->setNoRender();
		 echo Zend_Json::encode(array('slide_id' => $slide_id, 'question_id' => $question_id, 'data' => $sub->__toString()));
		 		
	}
	
	/**
	 * Ajax akce ktera maze odpoved
	 */
	public function deletequestionAction() {

		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		$question_id = $req->getParam('question_id', NULL);		
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//delete from db
		$model = new Questions($content_id);		
		$res = $model->deleteQuestion($question_id);	
		
		// non ajax method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}		
		// ajax method
		$this->_helper->viewRenderer->setNoRender();
		if ($res === FALSE) {
			echo Zend_Json::encode(array('deleted' => FALSE));
		} else {
			// on success
			echo Zend_Json::encode(array('deleted' => TRUE, 'question_id' => $question_id));			
		} 

	}
		
	/**
	 * Ajax akce ktera vraci novy zaznam pro slide
	 */
	public function addslideAction() { 
	
		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->_helper->viewRenderer->setNoRender();
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//store to db
		$model = new Questions($content_id);		
		$nearestNum = $model->getNearestSlideNum();
        $slideName = 'slide #'.$nearestNum;
		$slide_id = $model->editSlide($nearestNum, $slideName, FALSE);

		// non ajax  method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}
		
		// get subform and fill it with 'new' values
		$sub = $this->getSlideSubForm(array('num' => $nearestNum, 'id' => $slide_id, 'text' => '', 'name' => $slideName));
        $sub->getDecorator('Fieldset')->setOption('class', 'well slide');
        $sub->setAttrib('id', 'slide_'.$slide_id);
        $sub->setElementsBelongTo('slide_'.$slide_id);
		
		 // What is returned thru ajax		 
		 $this->_helper->viewRenderer->setNoRender();
		 echo Zend_Json::encode(array('slide_id' => $slide_id, 'data' => $sub->__toString()));
		 		
	}
		
	/**
	 * Ajax akce ktera maze slide
	 */
	public function deleteslideAction() {

		$req = $this->getRequest();				
		$content_id = $req->getParam('content_id', NULL);
		$slide_id = $req->getParam('slide_id', NULL);		
		
		// validate content_id
		$contentData = $this->getContentData();
		if (array_search($content_id, array_keys($contentData)) === FALSE) {											
			$this->getResponse()->setHttpResponseCode(500)
            	 ->appendBody("requested content_id not found");
			return;
		}
		
		//delete from db
		$model = new Questions($content_id);		
		$res = $model->deleteSlide($slide_id);	
		
		// non ajax method
		if (!$req->isXmlHttpRequest()) {	
			$params = array('content_id' => $content_id);
			$this->_helper->redirector('edit', NULL, NULL, $params);
			return;
		}		
		// ajax method
		$this->_helper->viewRenderer->setNoRender();
		if ($res === FALSE) {
			echo Zend_Json::encode(array('deleted' => FALSE));
		} else {
			// on success
			echo Zend_Json::encode(array('deleted' => TRUE, 'slide_id' => $slide_id));			
		}

	}	
		
	/**
	 * Vraci seznam contentu typu Indetail a Questionnaire pro combobox
	 * 
	 * @return array
	 */
	public function getContentData() {
		// Nacteme si seznam contentuu
		$content_list = Contents::getList('Ibulletin_Content_Indetail') + 
					    Contents::getList('Ibulletin_Content_Questionnaire');
		
		$data = array();
		foreach($content_list as $item){
			// deserializujeme ulozeny objekt, vytahneme jmeno
			Zend_Loader::loadClass($item['class_name']);
			$object = unserialize(stripslashes($item['serialized_object']));
			$data[$item['id']] = $object->name;
		}
		return $data;
	}
	
	/**
	 * Formularik na vyber contentu
	 * @param array pole multioptions
	 * @return Zend_Form
	 */	
	public function getEditContentForm($data) {
		
		$form = new Form(array('id' => 'content_list'));		
        $form->setFormInline(true);
		// content select box
		$select = new Zend_Form_Element_Select('content_id');
		$select->setMultiOptions($data)
				->setLabel($this->texts->content)
				->setRequired(true);				
			
		// tlacitko na potvrzeni vyberu contentu
		$submit = new Zend_Form_Element_Submit('content_submit');
		$submit->setLabel($this->texts->content_submit)
                ->setAttrib('class','btn-primary');        
        $form->addElements(array($select, $submit));
              
		return $form;
	}
	
	
	public function getSlideSubForm($data) {
		
		$form = new Form_SubForm();		
	
		// num 
		$num = new Zend_Form_Element_Text('num');
		$num->setOptions(array('readonly' => 'readonly'));			
		$num->setValue($data['num']);
		$num->setLabel($this->texts->slide_num);
	
		// name
		$name = new Zend_Form_Element_Text('name');		
		$name->setValue($data['name']);
		$name->setLabel($this->texts->slide_name);

		
		// mandatory
		$mandatory = new Zend_Form_Element_Checkbox('mandatory');			
		$mandatory->setValue(!empty($data['mandatory'])?$data['mandatory']:false);
		$mandatory->setLabel($this->texts->slide_mandatory);		
		 
		
		//ID
		$id = new Zend_Form_Element_Hidden('id');			
		$id->setValue($data['id']);	
				
		// add question & delete slide
		$id->getDecorator('label')->setOption('escape', false);
		$id->setLabel('<a class="btn btn-success addq" href="'.$this->_url->url(array('action' => 'addQuestion',
																	  'slide_id' => $data['id'])).
								   '">'.$this->texts->addq.'</a> '.
								   '<a class="btn btn-inverse dels" href="'.$this->_url->url(array('action' => 'deleteSlide', 
								   												  'slide_id' => $data['id'])).
									'">'.$this->texts->dels.'</a>');
	
		$form->setAttrib('id', 'slide_'.$data['id']);
		$form->setElementsBelongTo('slide_'.$data['id']);
		
		$form->addElements(array($num, $name, $mandatory,$id));
        
        $form->addDisplayGroup(
                        array($num,$name,$mandatory,$id), 'grp1', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
       
        $mandatory->getDecorator('HtmlTag')->setOption('class','span inline-no-label');
        $id->getDecorator('HtmlTag')->setOption('class','span inline-no-label');
		$id->getDecorator('label')->setOption('escape',false);
        $form->getDecorator('Fieldset')->setOption('class', 'slide');		

		return $form;						
	}
	
	public function getQuestionSubForm($data, $slide_id) {
		
		$form = new Form_SubForm();
		
		// num 
		$num = new Zend_Form_Element_Text('num');
		$num->setOptions(array('readonly' => 'readonly'));
		$num->setValue($data['num']);
		$num->setAttrib('class','span1');
		$num->setLabel($this->texts->question_num);

		// type 
		$type = new Zend_Form_Element_Select('type');				
		//radio, checkbox, jen text, bool, integer, double
		$type->setMultiOptions($this->question_types);
				  
		$type->setValue(!empty($data['type'])?$data['type']:'r');
		$type->setRequired(TRUE);				
		$type->setLabel($this->texts->question_type);
		
		// text
		$text = new Zend_Form_Element_Text('text');			
		$text->setValue($data['text']);
		$text->setLabel($this->texts->question_text);
		$text->setAttrib('size', '50');
		
		//ID
		$id = new Zend_Form_Element_Hidden('id');
		$id->setValue($data['id']);
		
		// add answer & delete question
		$id->getDecorator('label')->setOption('escape', false);
		$id->setLabel('<a class="btn btn-success adda" href="'.$this->_url->url(array('action' => 'addAnswer', 
																	   'slide_id' => $slide_id, 
																    'question_id' => $data['id'])).
								   	  '">'.$this->texts->adda.'</a> '.
								      '<a class="btn btn-info delq" href="'.$this->_url->url(array('action' => 'deleteQuestion', 
									  									 'question_id' => $data['id'])).
									  '">'.$this->texts->delq.'</a>');
									  
		$form->setAttrib('id', 'question_'.$data['id']);
		$form->setElementsBelongTo('slide_'.$slide_id.'[question_'.$data['id'].']');
											  
		$form->addElements(array($num,$type,$text,$id));
        $form->addDisplayGroup(
                        array($num,$type,$text,$id), 'grp1', array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
        $form->getDisplayGroup('grp1')->getDecorator('Fieldset')->setOption('class','well question');
        
        $id->getDecorator('HtmlTag')->setOption('class','span inline-no-label');
		$id->getDecorator('label')->setOption('escape',false);        		
		$form->getDecorator('Fieldset')->setOption('class','question');
		//echo($form->__toString());exit;
		return $form;
	}
	
	public function getAnswerSubForm($data, $slide_id=NULL,$question_id=NULL) {
		
		$form = new Form_SubForm();	
		
		// num 
		$num = new Zend_Form_Element_Text('num');
		$num->setOptions(array('readonly' => 'readonly'));
		$num->setValue($data['num']);
		$num->setAttrib('class','span1');
		$num->setLabel($this->texts->answer_num);
	
		// text
		$text = new Zend_Form_Element_Text('text');	
		$text->setAttrib('size', '50');
		
		$text->setValue($data['text']);
		$text->setLabel($this->texts->answer_text);
	
		//ID
		$id = new Zend_Form_Element_Hidden('id');					
		$id->setValue($data['id']);		
		
		// delete answer 
		$id->getDecorator('label')->setOption('escape', false);
		$id->setLabel(
		'<a class="btn btn-info dela" href="'.$this->_url->url(array('action' => 'deleteAnswer','answer_id' => $data['id'])).'">'.$this->texts->dela.'</a>');
		
		$form->setAttrib('id', 'answer_'.$data['id']);
		if ($slide_id != NULL && $question_id != NULL)
			$form->setElementsBelongTo('slide_'.$slide_id.'[question_'.$question_id.'][answer_'.$data['id'].']');
		
		$form->addElements(array($num, $text,$id));
        
        $form->addDisplayGroup(
           array($num,$text,$id), 'grp_answer_'.$data['id'], array('displayGroupClass' => 'Form_DisplayGroup_Inline'));
        $form->getDisplayGroup('grp_answer_'.$data['id'])->getDecorator('Fieldset')->setOption('class','well answer');
        
        $id->getDecorator('HtmlTag')->setOption('class','span inline-no-label');
		$id->getDecorator('label')->setOption('escape',false);
        $form->getDecorator('Fieldset')->setOption('class','answer');
	
		return $form;
	} 
	
	/**
	 * Formularik na upravu slidu - otazek a odpovedi
	 */
	public function getSlideForm($data){
		
		$form = new Form(array('id' => 'slide_form'));		
		
		$content_id = $data['content_id'];
		unset($data['content_id']);
		
		// traverse nested form data and set values
		if ($data)
		foreach ($data as $k => $slide) {
			// slide subform
			$slideForm = $this->getSlideSubForm($slide);			
			
			if (array_key_exists('questions', $slide))
			foreach ($slide['questions'] as $question) {
				// question subform
				$questionForm = $this->getQuestionSubForm($question, $slide['id']);				
				
				if (array_key_exists('answers', $question))
				foreach ($question['answers'] as $answer) {
					//answer subform
					$answerForm = $this->getAnswerSubForm($answer);					
					
					// add to stack
					$questionForm->addSubForm($answerForm,'answer_'.$answer['id']);
				}
				
				// add to stack
				$slideForm->addSubForm($questionForm, 'question_'.$question['id']);	
			}			
			$slideForm->getDecorator('Fieldset')->setOption('class', 'well slide');
			// add to stack
			$form->addSubForm($slideForm, 'slide_'.$slide['id']);
						
		}
		
		//ID
		$id = new Zend_Form_Element_Hidden('content_id');			
		$id->setValue($content_id);		

        // TODO: super ugly hack! odstranit ASAP. duvod je, ze se musi excludovat tenhle prvek pri traverzu POSTu aby se nespracovaval
        $id->getDecorator('label')->setOption('escape', false);
        $id->setLabel('<a class="btn btn-inverse adds" style="margin-top:10px" href="'.$this->_url->url(array('action' => 'addSlide')).'">'.$this->texts->adds.'</a>');
		$form->addElement($id);
		
		// submit button		
		$submit = new Zend_Form_Element_Submit('slide_submit');	
		$submit->setLabel($this->texts->submit);
        $submit->setAttrib('class','btn-primary');
		
		$form->addElement($submit);
		
		$id->getDecorator('label')->setOption('escape',false);
		
		return $form;			
	}
	
	
	
	// TODO: hodit do models/Question ?
	/**
	 *	Vrati seznam slidu rozsireny o otazky a odpovedi.
	 *	@param int id contentu
	 *	@return array slidy daneho contentu
	 */
	public function getSlidesData($content_id)
	{

		$db = $this->db;		

		if(!is_numeric($content_id)){
			return FALSE;
		}		

		// populating data from db select to nested array
		/*
			SELECT s.id as slide_id, s.slide_num, s.mandatory,
				   q.id as question_id, q.question_num, q.text as question_text, 
				   a.id as answer_id, a.answer_num, a.text as answer_text
			FROM slides s 
			LEFT JOIN questions q ON (q.slide_id = s.id)
			LEFT JOIN answers a ON (a.question_id = q.id)
			WHERE s.content_id = $content_id
		 */ 
		 
		$q = new Zend_Db_Select($db);
        $q->from(array('s' => 'slides'), 
				 array('slide_id' => 'id', 'slide_num', 'name', 'mandatory'))
          ->joinLeft(array('q' => 'questions'), 'q.slide_id = s.id',
		  		 array('question_id' => 'id', 'question_num', 'question_type' => 'type', 'question_text' => 'text'))
		  ->joinLeft(array('a' => 'answers'), 'a.question_id = q.id',
		  		 array('answer_id' => 'id', 'answer_num', 'answer_text' => 'text'))                   
          ->where('s.content_id = ?', $content_id)		  
		  ->order(array('s.slide_num', 'q.question_num', 'a.answer_num'));
		
		$rows = $db->fetchAll($q);

		$data = array('content_id' => $content_id);
		$prev = array('slide_id' => NULL, 'question_id' => NULL, 'answer_id' => NULL);
		$sk=$qk=$ak=0;
		foreach($rows as $row){
			/*var_dump($row); echo '<br/><br/>';
			var_dump($prev);echo '<br/><br/>';*/
			//print_r($row);echo '<br/>';
			if ($row['slide_id'] != $prev['slide_id']) {
				$item = array();
				$item['id'] = $row['slide_id'];
				$item['num'] = $row['slide_num'];
				$item['name'] = $row['name'];
				$item['mandatory'] = $row['mandatory'];								
				$sk++; $qk=$ak=0;
				$data[$sk] = $item; 
				
			}
			//echo 'sk: '.$sk;echo '<br/>';
			
			if ($row['question_id'] != $prev['question_id'] && $row['question_id'] !== NULL) {
				$item = array();				
				$item['id'] = $row['question_id'];
				$item['num'] = $row['question_num'];
				$item['type'] = $row['question_type'];
				$item['text'] = $row['question_text'];							 
				$qk++; $ak=0;				
				$data[$sk]['questions'][$qk] = $item; 
				
			}
			//echo 'qk: '.$qk;echo '<br/>';
			
			if ($row['answer_id'] != $prev['answer_id'] && $row['answer_id']  !== NULL) {
				$item = array();
				$item['id'] = $row['answer_id'];
				$item['num'] = $row['answer_num'];
				$item['text'] = $row['answer_text'];
				$ak++; 
				$data[$sk]['questions'][$qk]['answers'][$ak] = $item;
				
			}			
			//echo 'ak: '.$ak;echo '<br/>';
			$prev['slide_id']= $row['slide_id'];
			$prev['question_id']= $row['question_id'];
			$prev['answer_id']= $row['answer_id'];
		}
		//dump($data);exit;
		return $data;
		
	}


	/**
	 * Ulozi formular do DB
	 *
	 * @param Zend_Form formular k ulozeni
	 * @return bool podarilo se ulozit?
	 */
	public function saveForm($form)
	{		
		$data = $form->getValues();

		$content_id = $data['content_id'];
		$model = new Questions($content_id);
		
		$this->db->beginTransaction();
		
		$saved = TRUE;
		unset($data['content_id'],$data['slide_submit']);				
		foreach ($data as $slide) { //slides: id, num, mandatory, questions_*
			$slide_id = $slide['id'];
			$slide_num = $slide['num'];
			$slide_name = $slide['name'];
			$slide_mandatory = $slide['mandatory'];
			
			// edit slide
			$is_saved = $model->editSlide($slide_num, $slide_name, $slide_mandatory);
			if (!$is_saved) $saved = FALSE;				
			
			unset($slide['id'],$slide['num'],$slide['mandatory']);
			if (!empty($slide['slide_'.$slide_id])) {
                foreach ($slide['slide_'.$slide_id] as $question) { //questions: id, num, type, text, slide_{id} => answers_*
                    $question_id = $question['id'];
                    $question_num = $question['num'];
                    $question_type = $question['type'];
                    $question_text = $question['text'];

                    // edit question
                    $is_saved = $model->editQuestionTitle($content_id, $slide_id, $question_num, $question_type, $question_text);
                    if (!$is_saved) $saved = FALSE;
                    unset($question['id'],$question['num'],$question['type'],$question['text']);
                    foreach ($question as $answer) { //answers: id, num, text
                        $answer_id = $answer['id'];
                        $answer_num = $answer['num'];
                        $answer_text = $answer['text'];

                        //edit answer
                        $is_saved = $model->editAnswerTitle($question_id, $answer_num, $answer_text);
                        if (!$is_saved) $saved = FALSE;
                    }

                }
            }
			
		}
		
		if ($saved) $this->db->commit(); else $this->db->rollback();
		return $saved;

	}
	
}
