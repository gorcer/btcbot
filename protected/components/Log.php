<?php

class Log {
	
	public static function Add($dt, $data, $priority=0)
	{
		
		$fn = 'log.html';
		$text=  '<i>'.$dt.'</i> '.$data.'<br/>';
		if ($priority == 1)
		file_put_contents($fn, $text, FILE_APPEND);
				
		//if ($priority == 1)
		//echo '<i>'.$dt.'</i> '.$data.'<br/>';
	} 
}