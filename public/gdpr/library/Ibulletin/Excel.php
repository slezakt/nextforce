<?php

Zend_Loader::loadClass('PHPExcel');

/**
 * 	Pomocná třída pro exportování souborů do formátu XLS.
 *  
 *  
 * 	@author Martin Krčmář.
 */
class Ibulletin_Excel {


    /**
     *    Vytvoří XLSX soubor podle vysledku dotazu.
     *
     *    Funkce je staticka, protoze se pouziva i z jinych trid.
     *
     *    @param oTable.
     *    @param $fileName      String
     *    @param $noHtmlHeaders Bool
     *    @param $colWidths     Array   Pole obsahujici jako klic poradi sloupce od 0 a hodnotu
     *                                  jeho sirku ve znacich. DEFAULT null
     *    @param $rowsAsHead    Int     Pocet prvnich radku ktere budou slouzit jako hlavicka.
     *                                  Pokud je null, vypisi se nazvy sloupcu. DEFAULT: null
     *    @param $saveToFile    String  Cesta pro ulozeni vystupniho souboru - pouzivame predevsim pro generovani
     */
    public static function ExportXLSX($oTable, $fileName = 'export', $noHtmlHeaders = false, $colWidths = null, $rowsAsHead = null, $saveToFile = null) {

        $excel = new PHPExcel();
        //$excel->createSheet();
        $sheet = $excel->getActiveSheet();
        
        //neni-li predana hlavicka, doplnemi ji z klicu prvniho radku
        if ($rowsAsHead === null && isset($oTable[0]) ) {
            $oTable = array_merge(array(array_keys($oTable[0])),$oTable);
            $rowsAsHead = 1;
        }
        
        $x = 1;
        foreach ($oTable as $rows) {
            $y = 0;
            foreach ($rows as $col) {
                
                //zvyrazneni hlavicky
                if ($x <= $rowsAsHead) {
                    $sheet->getStyleByColumnAndRow($y, $x)->getFont()->setBold(true);
                }


                if (preg_match('/^[a-z]{2,6}\:\/\//i', $col)) {
                    $sheet->setCellValueByColumnAndRow($y, $x, $col)->getHyperlink()->setUrl($col);
                } else {
                    $sheet->setCellValueByColumnAndRow($y, $x, $col);
                }

                $y++;
            }
            $x++;
        }
        
        if (!preg_match('/^.*\.xlsx$/i', $fileName)) {
            $fileName .= ".xlsx";
        }

        $i = 0;
        foreach ($oTable[0] as $col) {
            if (isset($colWidths[$i])) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(false);
                $sheet->getColumnDimensionByColumn($i)->setWidth($colWidths[$i]);
            } else {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }
            $i++;
        }

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        if ($saveToFile) {
            $writer->save($saveToFile);
        } else {
            ob_end_clean();
            if (!$noHtmlHeaders) {
               self::createXLSXHeader($fileName);
            }
            $writer->save('php://output');
        }

        
    }

    /**
     * 	Vytvoří hlavičku pro XLSX soubor.
     *
     * 	@param Název souboru do hlavičky.
     */
    public static function createXLSXHeader($fileName) {

        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Type: application/force-download');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Type: application/download');
        header("Content-Disposition: attachment;filename=$fileName");
        header('Content-Transfer-Encoding: binary');
    }

}
