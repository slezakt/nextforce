<?php

/**
 * Stopwatch debuger panel
 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
 * @see http://addons.nette.org/cs/nextensions/stopwatch
 */
final class Stopwatch extends NObject implements IBarPanel
{
	/** @var array $timers */
	private static $timers = array();

	/** @var array $description */
	private static $description = array();
    
	/**
	 * Constructor
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @param \Nette\Application\Application $application
	 * @return void
	 */
	public function __construct(){	}

    /**
	 * Html code for DebugerBar Tab
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @return string
	 */
	public function getTab()
	{
        ob_start();
		$sum = $this->getStopwatchesSummary();
        require dirname(__FILE__) . '/templates/bar.stopwatch.tab.phtml';
        return ob_get_clean();
	}

	/**
	 * Html code for DebugerBar Panel
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @return string
	 */
	public function getPanel()
	{	
        ob_start();
		$description = self::$description;
		$sum = $this->getStopwatchesSummary();		
		foreach (self::$timers as $k=>$t) {
			$timers[$k] = number_format(round($t * 1000, 1), 1);
		}
        require dirname(__FILE__) . '/templates/bar.stopwatch.panel.phtml';
        return ob_get_clean();
	}

	/**
	 * Return summary of all stopwatches
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @return string
	 */
	private function getStopwatchesSummary()
	{
		return number_format(round(array_sum(self::$timers)*1000, 1), 1);
	}

	/**
	 * Start stopwatch
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @param string $name
	 * @param string $description
	 * @return void
	 */
	public static function start($name = NULL, $description = NULL)
	{
		NDebugger::timer($name);
		self::$description[$name !== NULL ? $name : uniqid()] = $description;
	}

	/**
	 * Stop stopwatch
	 * @author Pavel Železný <pavel.zezlenzy@socialbakers.com>
	 * @param string $name
	 * @return string
	 */
	public static function stop($name = NULL)
	{
		$time = NDebugger::timer($name);
		self::$timers[$name !== NULL ? $name : uniqid()] = $time;
		
		return $time;
	}

}
