<?php
/**
 * Layouts.php
 *
 * @author Mgr. Petr Skoda
 */

/**
 * Codes:
 * 1 - Nebyly zadany vsechny potrebne parametry.
 *
 */
class Layouts_Exception extends Exception {}

/**
 * Obsahuje funkce pro praci s Layouty webu.
 *
 * @author Mgr. Petr Skoda
 */
class Layouts
{
    /**
     * Nastavi do view veci potrebne pro renderovani v zadanem layoutu (css, top skript, bottom skript
     * view skript controlleru).
     *
     * @param string $name                          Nazev layoutu
     * @param Zend_Controller_Action $controller    Controller ve kterem chceme zmenit layout. Jednotlive polozky
     *                                              (view, request, viewRenderer) mohou byt nastaveny misto Controlleru.
     *                                              Pokud je nastaven controller i nektery z dalsich parametru, maji pozdejsi
     *                                              parametry prednost pred Controllerem.
     * @param bool $removeOrigCss                   Odebrat puvodni css.css?
     * @param Zend_View $view
     * @param Zend_Controller_Request $req
     * @param Zend_Controller_Action_Helper_ViewRenderer $viewRenderer
     */
    public static function setLayout($name, Zend_Controller_Action $controller, $removeOrigCss = true, $view = null, $req = null, $viewRenderer = null)
    {
        // Zkontrolujeme jestli byly spravne zadany vsechny potrebne parametry
        if(!($controller instanceof Zend_Controller_Action) && ($view === null || $req === null || $viewRenderer === null)
            || empty($name)){
            throw new Layouts_Exception('Nebyly zadany vsechny potrebne parametry - pokud neni zadan objekt controlleru, '.
                'je treba zadat view, request i view renderer.');
        }
        if($view === null){
            $view = $controller->view;
        }
        if($req === null){
            $req = $controller->getRequest();
        }
        if($viewRenderer === null){
            $viewRenderer = $controller->getHelper('viewRenderer');
        }

        // Postupne se pokusime nastavit vse potrebne
        $layoutFileTop = $name.'_top.phtml';
        $layoutFileBottom = $name.'_bottom.phtml';

        $view->layoutFileTop = $layoutFileTop;
        $view->layoutFileBottom = $layoutFileBottom;

        // Pokusime se nahradit css.css, pokud existuje pro dany layout
        if(file_exists('pub/css/'.$name.'.css')){
            if($removeOrigCss){
                Ibulletin_HtmlHead::removeFile('css.css', 'css');
            }
            Ibulletin_HtmlHead::addFile($name.'.css', 'css');
        }

        // Pokusime se nahradit view skript pro tuto akci, pokud existuje odpovidajici skript
        //$action = $req->getActionName();
        $controller = $req->getControllerName();
        $action = $req->getActionName();
        $scriptAction = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->getScriptAction();
        $viewScriptName = $controller.'/'.$name.'-'.$action.'.phtml';
        $viewScriptName1 = $controller.'/'.$name.'-'.$scriptAction.'.phtml';
        // Jen podle akce 
        if($view->getScriptPath($viewScriptName)){
            $viewRenderer->setScriptAction($name.'-'.$action);
        }
        // Zkusime to same s prave nastavenou scriptAction (view skriptem)
        elseif($view->getScriptPath($viewScriptName1)){
            $viewRenderer->setScriptAction($name.'-'.$scriptAction);
        }
    }


    /**
     * Vrati seznam layoutu pro selectbox.
     * Na zacatek seznamu pridat polozku ----- jejiz hodnota je ''
     *
     * @return  Array   Pole layoutu vhodne pro Zend_Form_Select
     */
    public static function getListForSel()
    {
        $config = Zend_Registry::get('config');

        $files = array();
        $default_layout = $config->general->layout->default;
        $path = $config->paths->layout_templates;
        if(file_exists($path)){
            $handle = opendir($path);
            while (($file = readdir($handle)) !== false){
                if ($file != '.' && $file != '..' && !is_dir($path.'/'.$file)
                    && preg_match('/\.phtml$/i', $file))
                {
                    // Ziskame nazev template
                    $matches = array();
                    if(preg_match('/(.*)\_top\.phtml/i', $file, $matches)){
                        $layoutName = $matches[1];
                    }

                    // skip default layout, add it as last item
                    if(!empty($layoutName) && ($layoutName != $default_layout)){
                        $files[$layoutName] = $layoutName;
                    }
                }
            }
        }
        $layouts = array_merge(array('' => Ibulletin_Texts::get('layouts.global.sameAsBulletin'), $default_layout => $default_layout), $files);

        return $layouts;
    }
}