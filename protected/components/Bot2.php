<?php

/**
 * Аналитика на MySQL
 * @author Zaretskiy.E
 *
 */
class Bot2 {
	
	private $last_ex;
	
	const imp_div = 0.01; // Видимые изменения
	
	
	public function __construct($last_ex)
	{
		$this->last_ex = $last_ex;
	}
	
	/**
	 * Получает изображение графика за период - \_/
	 * @param  $period - период расчета в сек.
	 * @param $name - buy, sell
	 */
	public function getGraphImage($period, $name)
	{
		$step = round($period/4);
		
		$connection = Yii::app()->db;
		$sql = "
				SELECT 
					avg(".$name.") as val,
					from_unixtime(round(UNIX_TIMESTAMP(dt)/(".$step."))*".$step.", '%Y.%m.%d %H:%i:%s')as dtm 
				FROM `exchange`
				where
					UNIX_TIMESTAMP(dt)>UNIX_TIMESTAMP('".$this->last_ex->dt."')-".$period."
				group by dtm
				order by dt
				";
		
		$command = $connection->createCommand($sql);
		$list=$command->queryAll();
		
		$track="";
		$prev=false;
		Dump::d($list);
		foreach($list as $item)
		{
			if (!$prev)
			{
				$prev = $item['val'];
				continue;
			}
			
			// Определяем направление
			$dif = $item['val']-$prev;			
			if ($dif<(-1*self::imp_div)) $track.="-";
			elseif ($dif>self::imp_div) $track.="+";
			else $track.="0";			
		}
		
		$result = array(
				'track'=>$track,
				'from' => date('Y-m-d H:i:s', time()-$period),
				'step' => $step,
				);
		
		return($result);
	} 
	
	public function NeedBuy()
	{
		$periods = array(15*60, 30*60, 60*60);
		foreach($periods as $period)
		{
			$track = $this->getGraphImage($period, 'buy');
			Dump::d($track);
		}
		
	}
	
}