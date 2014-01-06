<?php

class Log {
	
	public static function Add($data, $priority=0)
	{
		$bot = Bot::get_Instance();
		$dt = $bot->curtime;
		
		$cdt = date('Y-m-d H:i:s');
		
		$fn = 'log.html';
		$fn_all = 'log_all.html';
		
		$text=  '<i>'.$cdt.'</i> : ['.date('Y-m-d H:i:s', $dt).'] '.$data.'<br/>';
		
		if (!YII_DEBUG)
		{
			if ($priority == 1)
			file_put_contents($fn, $text, FILE_APPEND);
					
			file_put_contents($fn_all, $text, FILE_APPEND);
		}
		else
			echo '<i>['.date('Y-m-d H:i:s', $dt).']</i> '.$data.'<br/>';
	} 
	
	
	public static function Error($data)
	{
		$bot = Bot::get_Instance();
		$cdt = date('Y-m-d H:i:s', $bot->curtime);	
		
		$fn = 'logs/error-'.date('Y-m-d', $bot->curtime).'.html';
		$text=  '<i>'.$cdt.'</i> '.$data.'<br/>';			
		
		if (!YII_DEBUG)
			file_put_contents($fn, $text, FILE_APPEND);
		else			
			echo '<i>'.$cdt.'</i> : '.$text.' <br/>';
	}
	
	public static function notbuy($reason)
	{
		$bot = Bot::get_Instance();
				
		// Не пишем файл при дебаге
		if (YII_DEBUG) return;
		
		$dtm = date('Y-m-d H:i:s', $bot->curtime);
		
		$fn = 'logs/not-buy-'.date('Y-m-d', $bot->curtime).'.html';
		
		$text=  '<i>'.$dtm.'</i> '.$reason.'<br/>';
		file_put_contents($fn, $text, FILE_APPEND);
	}
	

	public static function notsell($reason)
	{
		$bot = Bot::get_Instance();
		
		// Не пишем файл при дебаге
		if (YII_DEBUG) return;
		
		$dtm = date('Y-m-d H:i:s', $bot->curtime);
	
		$fn = 'logs/not-sell-'.date('Y-m-d', $bot->curtime).'.html';	
		$text=  '<i>'.$dtm.'</i> '.$reason.'<br/>';
		file_put_contents($fn, $text, FILE_APPEND);
	}
}