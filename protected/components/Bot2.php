<?php

/**
 * Аналитика на MySQL
 * @author Zaretskiy.E
 *
 */
class Bot2 {
	
	private $current_exchange;
	
	const imp_div = 0.01; // Видимые изменения
	const min_buy = 0.01; // Мин. сумма покупки
	const buy_value = 0.01; // Сколько покупать
	const fee = 0.002; // Комиссия
	const min_buy_interval = 120; // Мин. интервал совершения покупок = 2 мин. 
	
	public function __construct($exchange)
	{
		$this->current_exchange = $exchange;
	}
	
	/**
	 * Получает изображение графика за период - -0+
	 * @param  $period - период расчета в сек.
	 * @param $name - buy, sell
	 */
	public function getGraphImage($curtime, $period, $name)
	{
		$step = round($period/4);
		$from = date('Y-m-d H:i:s', $curtime-$period);
		$to = date('Y-m-d H:i:s', $curtime);
		
		$connection = Yii::app()->db;
		$sql = "
				SELECT 
					avg(".$name.") as val,
					from_unixtime(round(UNIX_TIMESTAMP(dt)/(".$step."))*".$step.", '%Y-%m-%d %H:%i:%s')as dtm 
				FROM `exchange`
				where
					dt between '".$from."' and '".$to."' 
				group by dtm
				order by dt
				";
		
		$command = $connection->createCommand($sql);
		$list=$command->queryAll();
		
		$track="";
		$prev=false;		
		foreach($list as $item)
		{
			// Откидываем мусор
			if ($item['dtm']<$from || $item['dtm']>$to) continue;
			
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
	
	private function isRealyNeedBuy($tracks)
	{
		foreach($tracks as $track)
		{
			$ret = false;
			switch($track['track']){
				case '-0+':	$ret = true; break; // \_/
				case '--+':	$ret = true; break; // \_/
				case '00+':	$ret = true; break; // __/
				case '0-+':	$ret = true; break; // _\/
				default: $ret = false; break;
			}
			
			if ($ret) 
			{
				Log::AddText(0, "Выгодный рисунок ".$track['track'].' начиная с '.$track['from'], 3);
				return $ret;
			}
		}
	}
	
	private function Buy()
	{
		$price = $this->current_exchange->buy*self::buy_value*(1+self::fee);	
		Log::AddText('<b>Создана сделка на покупку '.self::buy_value.' ед. за '.$this->current_exchange->buy.' ('.$this->current_exchange->buy*(self::fee).' комиссия) на сумму '.$price.' руб.</b>', 1);
	}
	
	public function NeedBuy($curtime)
	{
		//Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);
		Log::AddText($dt);
		// Защита от зациклившейся покупки
		$lastbuy = Btc::getLastBuy(); // Получаем дату последней продажи
		if (time()-strtotime($lastbuy->dtm)<self::min_buy_interval) return;
		
		//Перебираем периоды 15 мину, 30 мину, 1 час
		$periods = array(15*60, 30*60, 60*60);
		$tracks=array();
		foreach($periods as $period)
		{
			$tracks[] = $this->getGraphImage($curtime, $period, 'buy');			
		}
		Log::AddText('Треки'.print_r($tracks, true));
		//Dump::d($tracks);
		
		// @todo Решить проблему с дублированием покупок на одном подъеме
		
		//Анализируем картинки, если выгодное положение - покупаем
		if($this->isRealyNeedBuy($tracks))
		{
			$this->buy();
		}
		Log::AddText('Нет интересных покупок');
		
		
	}
	
}