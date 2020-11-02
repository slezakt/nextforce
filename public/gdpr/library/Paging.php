<?php

/**
 *	Třída pro stránkování výstupu.
 *	Použití:
 *
 *	$paging = new Paging($this->getRequest(), new Zend_View_Helper_Url);
 *	$paging->setUrlHelperOptions(
 *		array('controller' => 'mailer', 'action' => 'mailing'));
 *
 *	// celkovy pocet zaznamu, celkovy pocet zaznamu by se mel nastavit jeste
 *	// pred volanim metody getOffset, v takovem pripade se muze zkontrolovat,
 *	// offset neni vetsi nez pocet zaznamu a upravit se hodnota offsetu.
 *	$paging->setNumRows($numRows);
 *
 *	// potom se daji vyuzit metody $paging->getOffset() a $paging->getLimit()
 *	// napr. $users = $users->getUsers($paging->getOffset(), $paging->getLimit());
 *
 *	// vygenerovani odkazu
 *	$this->view->paging = $paging->generatePageLinks();
 *
 *	@author Martin Krčmář
 */
class Paging
{
    /**
    *	Název proměnné z URL, ve které je číslo aktuální stránky.
    */
    private $recStartName = 'recstart';

    /**
    *	Aktuální číslo stránky. Defaultně první.
    */
    private $recStart = 1;

    /**
    *	Celkový počet stránek.
    */
    private $numHrefs = 1;

    /**
    *	Počet záznamů na stránku. Defaultně 20.
    */
    private $limit = 20;

    /**
    *	Objekt, který se stará o vytváření odkazů na další stránky.
    */
    private $paginator;

    /**
    *	Celkový počet záznamů.
    */
    private $numRows = '';

    /**
    *	Konstruktor.
    *
    *	@param Objekt request, objekt z neho ziskava aktualni cislo stranky.
    *	@param Zend_View_Helper_Url, používá se při vytváření odkazů na další
    *	stránky.
    */
    public function __construct($request, $urlHelper)
    {
        // ziska se aktualni cislo stranky
        if ($request->__isset($this->recStartName))
            $this->recStart = $request->getParam($this->recStartName);

        // od ktereho zaznamu z DB se budou data nacitat.
        $this->offset = ($this->recStart - 1) * $this->limit;

        // objekt pro vytvareni odkazu
        $this->paginator = new Paginator();
        $this->paginator->setUrlHelper($urlHelper);
    }

    /**
    * Nastaví se celkový počet záznamů. To potřebuje třída vědět aby vytvořila
    * správný počet odkazů.
    *
    * @param Počet záznamů.
    */
    public function setNumRows($num)
    {
        $this->numHrefs = ceil($num / $this->limit);
        $this->numRows = $num;
    }

    /**
    *	Vrátí offset, od kterého záznamu by se měli získávat data z DB.
    */
    public function getOffset()
    {
        // pokud uz uzivatel zadal celkovy pocet zaznamu, muzeme overit, zda
        // offset neni vetsi nez celkovy pocet zaznamu, v tom pripade se offset
        // nastavi na zacatek posledni stranky.
        if (!empty($this->numRows))
        {
            if ($this->numRows <= $this->offset)
            {
                // nebo se to da nastavit na prvni stranku
                //$this->offset = 0;
                //$this->recStart = 1;
                $this->offset = (ceil($this->numRows / $this->limit) - 1) * $this->limit;
                $this->recStart = floor($this->offset / $this->limit) + 1;
            }
        }
        return $this->offset;
    }

    /**
    *	Vrátí počet záznamů na stránku.
    */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
    *	Nastaví počet záznamů na stránku.
    *
    *	@param Počet záznamů na stránku.
    */
    public function setLimit($limit)
    {
        if ($limit >= 1)
        {
            $this->limit = $limit;
            $this->offset = ($this->recStart - 1) * $this->limit;	// prepocita se offset
        }
    }

    public function getRecstart()
    {
        return $this->recStart;
    }

    /*
    *	Vrátí index prvního záznamu, který se bude zobrazovat.
    */
    public function getFirstDisplayed()
    {
        return (($this->recStart - 1) * $this->limit) + 1;
    }

    /**
    *	Nastaví URL helper. Standardně se nastavuje už v konstruktoru, je ale
    *	možné ho předefinovat.
    *
    *	@param Zend_View_Helper_Url.
    */
    public function setUrlHelper($urlHelper)
    {
        $this->paginator->setUrlHelper($urlHelper);
    }

    /**
    *	Nastaví nastavení pro URL helper, na jaký kontrolér a akci (případně i s
    *	parametry) budou stránkované odkazy směřovat.
    *
    *	@param Nastavení.
    */
    public function setUrlHelperOptions($options)
    {
        $this->paginator->setUrlHelperOptions($options);
    }

    /**
    *	Vygeneruje kód odkazů.
    */
    public function generatePageLinks()
    {
        return $this->paginator->generateLinkString($this->numHrefs, $this->recStart);
    }
}






/**
 *	Třída pro generování odkazů při stránkování. stáhnuto někde z webu a
 *	upraveno pro potřeby použití v Zend_Frameworku. Tzn. používání
 *	Zend_View_Helper_Url objektu pro vytváření odkazů.
 *	Nepoužívá se přímo, ale přes objekt Paging.
 */
class Paginator
{

    protected $linkHTML = '<a href="%URL%">%PAGE%</a>';
    protected $currentHTML = '<b>%PAGE%</b>';
    protected $linkAllPages = false;
    protected $pageSeparator = '&nbsp;';
    protected $urlHelper;
    protected $urlHelperOptions;

    public function setUrlHelper($urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }
    public function setUrlHelperOptions($options)
    {
        $this->urlHelperOptions = $options;
    }

    public function getLinkHTML()
    {
        return $this->linkHTML;
    }
    public function setLinkHTML($linkHTML)
    {
        $this->linkHTML = $linkHTML;
    }

    public function getCurrentHTML()
    {
        return $this->currentHTML;
    }
    public function setCurrentHTML($html)
    {
        $this->currentHTML = $html;
    }

    public function getLinkAllPages()
    {
        return $this->linkAllPages;
    }
    public function setLinkAllPages($bool)
    {
        $this->linkAllPages = $bool;
    }

    public function getPageSeparator()
    {
        return $this->pageSeparator;
    }
    public function setPageSeparator($str)
    {
        $this->pageSeparator = $str;
    }

    private function generateCurrentStringForPage($page)
    {
        return str_replace('%PAGE%', $page, $this->getCurrentHTML());
    }

    private function generateLinkStringForPage($page)
    {
        $urlOptions = $this->urlHelperOptions;
        $urlOptions['recstart'] = $page;

        return str_replace(
            array('%URL%', '%PAGE%'),
            array($this->urlHelper->url($urlOptions), $page),
            $this->getLinkHTML());
    }

    private function generate($totalPages, $currentPage = 1)
    {
        $pages = array();

            //just one page
        if ($totalPages <= 1)
        {
            /*
            if ($this->getLinkAllPages())
            {
                $pages[] = $this->generateLinkStringForPage(1);
            }
            else
            {
                $pages[] = $this->generateCurrentStringForPage(1);
            }
            */
        }
            //more than one page
        else
        {
            if ($currentPage > 17)
            {
                $pages[] = $this->generateLinkStringForPage(1);

                if ($currentPage != 18)
                    $pages[] = '&hellip;';
            }

            for ($index = $currentPage - 16, $stop = $currentPage + 18; $index < $stop; ++$index)
            {
                if ($index < 1 || $index > $totalPages)
                    continue;
                else if ($index != $currentPage || $this->getLinkAllPages())
                    $pages[] = $this->generateLinkStringForPage($index);
                else
                    $pages[] = $this->generateCurrentStringForPage($index);
            }

            if ($currentPage <= ($totalPages - 18))
            {
                if ($currentPage != ($totalPages - 18))
                    $pages[] = '&hellip;';

                $pages[] = $this->generateLinkStringForPage($totalPages);
            }
        }

        return $pages;
    }

    public function generateLinkString($totalPages, $currentPage = 1)
    {
        $pages = $this->generate($totalPages, $currentPage);
        return implode($this->getPageSeparator(), $pages);
    }

}
?>
