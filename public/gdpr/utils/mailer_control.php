#!/usr/bin/php
<?php
/*
 *	Skript pro spouštění maileru z CRONU.
 *	Skript se spouští php mailer_control.php --config <config.ini>,
 *	kde config.ini je konfigurační soubor. Za přepínačem --config může 
 *	následovat libovolný počet konfiguračních souborů. Většinou bude například 
 *	nastavení DB v souboru config_db.ini, nastavení maileru v config_mailer.ini 
 *	atd. Takže lze zde zadat všechny konfigurační soubory, které mailer 
 *	potřebuje. Hlavně musí mít informace o DB, nastavení maileru a tabulkách. Za 
 *	každým konfiguračním souborem musí následovat pojmenování sekce z 
 *	konfiguračního souboru.
 *	Příklad:
 *	php mailer_control.php --config config_db.ini database config_mailer.in	mailer config_tables.ini tables
 *	
 *	Je možné použít i xml konfigurační	soubor. Tímto je možné na jednom stroji
 *	spuštět mailer pro více webů, pokaždé se spustí	s jiným konfiguračním 
 *	souborem.
 *
 *	@author Martin Krčmář
 */

define('PHC_LEGACY_PATH', '/home/mentor/projects/inbox_lib/');

require('../bootstrap.php');



// zpracovani argumentu prikazoveho radku
$configFile = '';				// nazev konfiguracniho souboru
$configFiles = array();

for ($i = 1; $i < $_SERVER['argc']; $i++)
{
	// nactou se vsechny konfiguracni soubory
	if (!strcmp($_SERVER['argv'][$i], '--config') && !empty($_SERVER['argv'][$i + 1]))
	{
		$j = $i + 1;
		while (strcmp($_SERVER['argv'][$j], '--web') && !empty($_SERVER['argv'][$j]))
		{
			$configFiles[$_SERVER['argv'][$j]] = $_SERVER['argv'][++$j];
			$j++;
		}
	}
}

if (!empty($configFiles))			// byl zadan konfiguracni soubor
{
	$i = 0;
	foreach ($configFiles as $cf => $key)
	{
		try
		{
			if ($i == 0)
			{
				$options = array('allowModifications' => true);
				$ext = pathinfo($cf, PATHINFO_EXTENSION);		// zjisti priponu konfiguracniho souboru
				if (!strcmp($ext, 'xml'))							// jedna se o xml soubor
				{
					$config = new Zend_Config_Xml($cf, $key, $options);
				}
				else if (!strcmp($ext, 'ini'))						// nebo ini konfiguracni soubor
				{
					$config = new Zend_Config_Ini($cf, $key, $options);
				}
			}
			else
			{
				$ext = pathinfo($cf, PATHINFO_EXTENSION);		// zjisti priponu konfiguracniho souboru
				if (!strcmp($ext, 'xml'))							// jedna se o xml soubor
				{
					$config->merge(new Zend_Config_Xml($cf, $key));
				}
				else if (!strcmp($ext, 'ini'))
				{
					$config->merge(new Zend_Config_Ini($cf, $key));
				}
			}
			$i++;
		}
		catch (Zend_Config_Exception $e)
		{
			Phc_ErrorLog::error('Mailer, CRON', "Neplatný formát konfiguračního souboru: $cf");
			exit();
		}
	}
	
	try
	{
		Zend_Registry::set('config', $config);
		$mailer = new Ibulletin_Mailer($config);		// vytvoreni instance maileru
		//$mailer->setCancelAllEH();						
		$mailer->sendEmails();							// odeslani mailu
		if ($mailer->anyErrors())
		{
			foreach ($mailer->getErrorsDescribed() as $id => $reason)
			{
				echo $reason;
			}
		}
	}
	catch (IBulletinMailerException $e)
	{
		Phc_ErrorLog::error('Mailer, CRON', $e->getMessage());
		exit();
	}
}
else
{
	Phc_ErrorLog::error('Mailer, CRON', 'Nebyl zadán konfigurační soubor');
}
?>
