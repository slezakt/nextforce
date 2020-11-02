<?php
/**
 * iBulletin - View/Text.php
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */

/**
 * View helper ktery umoznuje v templatech snadno pridavat texty ze souboru textuu
 * pomoci tridy Ibulletin_Texts.
 *
 * @author Petr Skoda, <petr.skoda@pearshealthcyber.com>
 */
class Ibulletin_View_Helper_Text {

    /**
     * @var Zend_View Instance
     */
    public $view;

    /**
     * Vrati podle zadaneho identifikatoru text ze souboru textuu.
     * V pripade vadneho identifikatoru, nebo nenalezeni textu vraci prazdny retezec.
     *
     * @access public
     *
     * @param  string Identifikator textu
     *                pro obecne texty nepatrici pod konkretni akci pridame . prefix
     *                pro ostatni pouzivame nejkratsi suffix z:
     *                "module.controller.action.identifikator_textu"
     *                (minimalne vsak "identifikator_textu")
     * @return string Text podle identifikatoru ze souboru textuu
     */
    public function text($ident)
    {
        if(empty($ident)) {
            return "";
        }
        
        return Ibulletin_Texts::get($ident);
    }

    /**
     * Set the view object
     *
     * @param Zend_View_Interface $view
     * @return void
     */
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
}