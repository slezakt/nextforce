<?php

/**
 * Check and reset PHP configuration.
 */
if (!defined('PHP_VERSION_ID')) {
	$tmp = explode('.', PHP_VERSION);
	define('PHP_VERSION_ID', ($tmp[0] * 10000 + $tmp[1] * 100 + $tmp[2]));
}

define('NETTE', TRUE);
define('NETTE_DIR', dirname(__FILE__));
define('NETTE_VERSION_ID', 20003); // v2.0.3
define('NETTE_PACKAGE', 'PHP 5.2 prefixed');

class NCFix
{
	static $vars = array();

	static function uses($args)
	{
		self::$vars[] = $args;
		return count(self::$vars)-1;
	}
}


require_once 'NDebugger/common/exceptions.php';
require_once 'NDebugger/common/Object.php';
require_once 'NDebugger/common/Framework.php';
require_once 'NDebugger/Diagnostics/Helpers.php';
require_once 'NDebugger/Diagnostics/shortcuts.php';
require_once 'NDebugger/Utils/Html.php';
require_once 'NDebugger/Utils/Strings.php';
require_once 'NDebugger/Diagnostics/Debugger.php';
require_once 'NDebugger/Diagnostics/Logger.php';
require_once 'NDebugger/Diagnostics/FireLogger.php';
require_once 'NDebugger/Diagnostics/BlueScreen.php';
require_once 'NDebugger/Diagnostics/Bar.php';
require_once 'NDebugger/Diagnostics/DummyBar.php';
require_once 'NDebugger/Diagnostics/IBarPanel.php';
require_once 'NDebugger/Diagnostics/DefaultBarPanel.php';
require_once 'NDebugger/Diagnostics/Stopwatch.php';

// zapneme ladenku
NDebugger::_init();
NDebugger::addPanel(new Stopwatch());
NDebugger::enable(NDebugger::DEVELOPMENT, APPLICATION_PATH  . '/log');

?>
