<?php
/**
 * Ibulletin_DataGrid_DataSource_Abstract
 *
 * class to provide a DataSource implementation for datagrid
 *
 * @category   Ibulletin
 * @package    Ibulletin_DataGrid
 * @subpackage Ibulletin_DataGrid_DataSource
 * @author Andrej Litvaj <andrej.litvaj@pearshealthcyber.com>
 */

class Ibulletin_DataGrid_DataSource_Abstract implements Ibulletin_DataGrid_DataSource_Interface
{

    /**
     * @param int  $offset
     * @param null $size
     * @param bool $toArray
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function fetch($offset = 0, $size = null, $toArray = false) {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }

    /**
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function count() {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }

    /**
     * @param string $sortSpec
     * @param string $sortDir
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function sort($sortSpec, $sortDir = 'ASC') {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }

    /**
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function getColumns() {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }

    public function getColumnsMeta() {

        $col = array();
        foreach ($this->getColumns() as $k => $colname) {
            $col[$k]['name'] = $colname;
            $col[$k]['datatype'] = 'string';
            $col[$k]['alias'] = null;
        }

        return $col;
    }

    /**
     * @param array $filters
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function filter($filters) {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }


    /**
     * @param string $field
     * @param        $term
     * @throws Ibulletin_DataGrid_DataSource_Exception
     */
    public function hitlist($field, $term) {
        throw new Ibulletin_DataGrid_DataSource_Exception('method not implemented');
    }


    /**
     * @param      $format
     * @param null $filename
     */
    public function export($format, $filename = null)
    {
        $filename = $filename === null ? 'php://output' : $filename;
        
        $records = $this->fetch(/*0, null, true)*/);

        switch ($format) {

            case 'csv' :

                $delim=';'; $quote='"'; // csv parameters
                $fp = fopen($filename, 'w');

                $i = 0; foreach ($records as $v) {
                // header
                if (++$i == 1) fputcsv($fp, array_keys($v), $delim, $quote);
                // row
                fputcsv($fp, $v, $delim, $quote); // row
            }
                fclose($fp);
                break;

            case 'xlsx' :
                
                $header = array();
                
                if (isset($records[0])) {
                    $header = array_keys($records[0]);
                }
                
                Ibulletin_Excel::ExportXLSX(array_merge(array($header),$records), $filename,false,null,1);
                return;
        }

        if (is_readable($filename)) {
            ob_end_clean();
            readfile($filename);
        }

    }
}
