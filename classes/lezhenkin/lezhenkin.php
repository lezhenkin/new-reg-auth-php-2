<?php
defined('MYSITE') || exit("Прямой доступ к файлу запрещен");

class Lezhenkin
{
    static protected $_aMyIp = [
		'95.174.98.212',
	];
	
	static public function debug($mData, string $sHeader = "", $vardump = FALSE, $debug = TRUE, $ip_filter = FALSE)
	{
		!$debug && ob_start();
		$bIpFilter = TRUE;
		
		if ($ip_filter && !in_array($_SERVER['REMOTE_ADDR'], static::$_aMyIp))
		{
			$bDebug = FALSE;
		}
		
		!$debug && ob_start();
		
		if (is_array($mData))
		{
			!empty($sHeader) && print "<br />{$sHeader}";
			
			print "<pre>";
			
			if (!$vardump)
			{
				print_r($mData);
			}
			else
			{
				var_dump($mData);
			}
			
			print "</pre>";
		}
		elseif (is_null($mData))
		{
			!$vardump && print "<p class='fw-bold'>NULL</p>";
			$vardump && print "<p class='fw-bold'>"; var_dump($mData); print "</p>";
		}
		else
		{
			print (!$vardump) ? "<p class=\"fw-bold\">{$mData}</p>" : var_dump($mData);
		}
		
		if (!$debug) { return ob_get_clean(); }
	}
	
	static public function checkMe()
	{
		return in_array($_SERVER['REMOTE_ADDR'], static::$_aMyIp);
	}
}
?>