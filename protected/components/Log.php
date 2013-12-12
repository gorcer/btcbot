<?php

class Log {
	
	public static function Add($dt, $data, $priority=0)
	{
		$cdt = date('Y-m-d H:i:s');
		
		$fn = 'log.html';
		$fn_all = 'log_all.html';
		
		$text=  '<i>'.$cdt.'</i> : ['.$dt.']'.$data.'<br/>';
		
		if ($priority == 1)
		file_put_contents($fn, $text, FILE_APPEND);
				
		file_put_contents($fn_all, $text, FILE_APPEND);
		//if ($priority == 1)
		//echo '<i>'.$dt.'</i> '.$data.'<br/>';
	} 
	
	public static function AddText($tm, $data)
	{
		$cdt = date('Y-m-d H:i:s', $tm);		
		$text=  '<i>'.$cdt.'</i> :'.$data.'<br/>';
		echo $text.'<br/>';
	}
}