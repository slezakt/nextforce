<?php
/**
 * ClientException.php
 *  
 * @author  Bc. Petr Skoda
 */

/**
 * Codes: 
 * 0 - Volana neexistujici metoda.
 * 1 - Nepodarilo se provest vzdaleny pozadavek chybou prenosoveho protokolu.
 * 2 - Chyba pri vykonavani vzdalene metody.
 */
class Communicator_ClientException extends Exception{};
