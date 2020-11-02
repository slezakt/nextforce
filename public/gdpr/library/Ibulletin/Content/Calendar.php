<?php

/**
 * Ibulletin - Calendar
 *
 * @author Ondra Bláha <ondra@blahait.cz>
 */
class Ibulletin_Content_Calendar extends Ibulletin_Content_Abstract {

    /**
     * @var string Jmeno template pro dany content.
     */
    var $tpl_name_schema = "calendar_%d.phtml";

    /**
     *
     * @var bool Zpusob zobrazeni contentu
     */
    var $calendarView = false;

    /**
     * @var HTML kód obsahu.
     */
    var $html = "";

    public function prepare($req) {

        $config = Zend_Registry::get('config');

        $this->prepareSheetNumber();

        $this->calendarView = $req->getParam('calendarView', false);

        //nacteme vse potrebne pro fullcalendar.io
        Ibulletin_Js::addJsFile('fullcalendar/lib/moment.min.js');
        Ibulletin_Js::addJsFile('fullcalendar/fullcalendar.min.js');
        Ibulletin_Js::addJsFile('fullcalendar/lang-all.js');
        Ibulletin_HtmlHead::addFile('../scripts/fullcalendar/fullcalendar.min.css', 'css');

        //twb modal
        Ibulletin_Js::addJsFile('bootstrap-modal.min.js');
        Ibulletin_HtmlHead::addFile('bootstrap-modal.min.css', 'css');

        //aktualni jazyk pro fullcalendar
        $lang = $config->general->language;

        if ($lang == "cz") {
            $lang = "cs";
        }

        Ibulletin_Js::addPlainCode("var language = '" . $lang . "';");
    }

    public function getContent() {


        // Prelozime znacky
        $config = Zend_Registry::get('config');
        $urlHlpr = new Zend_View_Helper_Url();
        $baseUrl = $urlHlpr->url(array(), 'default', true);
        $path = $baseUrl . $config->content_article->basepath . $this->id . '/';
        $html = Ibulletin_Marks::translate($this->html, $path, $this);

        return array('html' => '?> ' . $html, 'calendarView' => $this->calendarView);
    }

    public function getData() {

        parent::getData();

        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();

        $config = Zend_Registry::get('config');

        $data = array();

        $tags = Calendar::getTags(true);

        $filter_tags = $request->getParam('tags',array());

        if ($filter_tags) {

            if (!is_array($filter_tags)) {
                $filter_tags = explode('|',$filter_tags);
            }

            $data['filter']['tags'] = $filter_tags;

        } else {
            $data['filter']['tags'] =  array_keys($tags);
        }

        $data['tags'] = $tags;

        //data pro list view
        if (!$this->calendarView) {

            $url_params = $request->getUrlParams();
            // Musime odstranit controller a action z url parametru
            unset($url_params['controller']);
            unset($url_params['action']);

            if ($filter_tags) {
                $url_params['tags'] = implode('|',$filter_tags);
            }

            $params = new stdClass();
            $params->urlParams = $url_params;
            $params->url_param = $this->getSheetNumberUrlName();

            $count = Calendar::getEventsCount($this->id, $filter_tags);

            if ($this->sheet_number == 0) {
                $this->sheet_number = Calendar::getCurrentEventPage($this->id,$filter_tags);
            }

            $paginator = new Zend_Paginator(new Zend_Paginator_Adapter_Null($count));
            $paginator->setItemCountPerPage($config->calendar->paging->perpage);
            $paginator->setCurrentPageNumber($this->sheet_number);

            $events = Calendar::getEvents($this->id, null, $paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage(),$filter_tags,'date_begin');

            $data['paginator'] = $paginator;
            $data['paginatorUrlParams'] = $params;
        } else {
            $events = Calendar::getEvents($this->id);
        }

        $calendarEvents = array();

        foreach ($events as $event) {

            $beginDate = new Zend_Date($event['date_begin']);
            $endDate = new Zend_Date($event['date_end']);

			$calDateFormat = "YYYY-MM-dd";

            //formatovani terminu udalosti
            if ($beginDate->compareHour(0) == 0) {
                $dateFormat = $config->general->dateformat->short;
            } else {
                $dateFormat = $config->general->dateformat->medium;
            }

            if ($beginDate->compare($endDate) == 0) {
                $date = $beginDate->toString($dateFormat);
                $start = $beginDate->toString($calDateFormat);
                $end = null;
            } else {
                $date = $beginDate->toString($dateFormat) . " - " . $endDate->toString($dateFormat);
                $start = $beginDate->toString($calDateFormat);
                //pridame den z duvodu chybneho vyznacovani eventu ve fullcalendar
                $endDate->addDay(1);
				$end = $endDate->toString($calDateFormat);
            }

			$diff = floor($beginDate->sub(Zend_Date::now())->toValue()/86400);

			if ($diff<0) {
				$validEvent = false;
			} else {
				$validEvent = true;
			}

			$additional = json_decode($event['additional'], true);

            $calendarEvents[] = array('id'=>$event['id'], 'title' => $event['title'], 'start' => $start, 'url_action' => $event['url_action'], 'description' => $event['description'],
                'end' => $end, 'place' => $event['place'], 'date' => $date, 'address' => $event['address'], 'image' => $event['image'], 'additional' => $additional,'allDay'=>true,
				'valid'=>$validEvent);
        }

        $data['events'] = $calendarEvents;

        return $data;
    }


     /**
     * Nastavi do $this->sheet_number cislo listu ke zobrazeni.
     *
     * @return string    Cislo listu pro tento content
     */
    public function prepareSheetNumber(){
        // Pokud vypisujeme vse do jednoho listu, nastavime cislo listu na 1
        if($this->allSheetsInOne){
            $this->sheet_number = 1;
            return;
        }

        $frontController = Zend_Controller_Front::getInstance();
        $request = $frontController->getRequest();

        $param_name = $this->getSheetNumberUrlName();

        // Ziskame z URL cislo aktualniho listu na strance
        $sheet_number_get = $request->getParam($param_name);
        if(is_numeric($sheet_number_get)){
            $this->sheet_number = $sheet_number_get;
        } else {
            $this->sheet_number = 0;
        }

        return;
    }

}
