<?php

/**
 * Аналитика на MySQL
 * @author Zaretskiy.E
 *
 * @todo Определение кол.ва закупа - когда курс падает от среднего за двое суток на 20%, пускай бот покупает 5 минимумов
 * 
 */
class Bot2 {
	
	private $current_exchange;
	private $curtime;
	private $balance;
	private $balance_btc;
	private $order_cnt;
	private $total_income;
	private $imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	private $avg_buy; // Средняя цена покупки
	private $avg_sell;// Средняя цена продажи
	
	//const imp_dif = 0.015; // Видимые изменения @todo сделать расчетным исходя из желаемого заработка и тек. курса
	const min_buy = 0.01; // Мин. сумма покупки
	const buy_value = 0.01; // Сколько покупать
	const fee = 0; // Комиссия
	const min_buy_interval = 3600; // Мин. интервал совершения покупок = 60 мин.
	const min_income = 5; // Мин. доход в рублях
	const long_time =  1800; // Понятие долгосрочный период - больше 30 минут
	
	
	public function __construct($exchange)
	{
		$this->current_exchange = $exchange;
		$this->curtime = strtotime($exchange->dt);
		
		$this->balance = Status::getParam('balance');
		$this->balance_btc = Status::getParam('balance_btc');
		$this->total_income=0;
		$this->imp_dif = 100;//self::min_income*(1+2*self::fee)*1/self::buy_value/4; // Здесь по расчетам 1000 / 4, на столько должен измениться курс чтобы бот заметил отличия
		
		$this->order_cnt=0;		
		
		$from = date('Y-m-d H:i:s', time()-60*60*24*7);
		$this->avg_buy = Exchange::getAvg('buy', $from,  date('Y-m-d H:i:s', $this->curtime));
		$this->avg_sell = Exchange::getAvg('sell', $from,  date('Y-m-d H:i:s', $this->curtime));
		
	}
	
	/**
	 * Получает изображение графика за период - -0+
	 * @param  $period - период расчета в сек.
	 * @param $name - buy, sell
	 */
	public function getGraphImage($curtime, $period, $name)
	{		
		// @todo переделать - диапазоны расчитывать более точно
		
		$step = round($period/3);
		$from_tm = $curtime-$period;
		$from = date('Y-m-d H:i:s', $from_tm);		
		$to = date('Y-m-d H:i:s', $curtime);		
		
		
		$track="";
		$prev=false;
		for($i=0;$i<=3;$i++)
		{			 			
			$step_ut = $from_tm+$step*$i;
			$step_dt = date('Y-m-d H:i:s', $step_ut);	// Делим период на 4 точки
			$step_ut_f = date('Y-m-d H:i:s',$step_ut-$step/2); // Вокруг каждой точки отмеряем назад и вперед половину шага
			$step_ut_t = date('Y-m-d H:i:s',$step_ut+$step/2);
			
			//$val=Exchange::NOSQL_getAvg($name, $step_ut_f, $step_ut_t);
			$val=Exchange::getAvg($name, $step_ut_f, $step_ut_t);
			
			if (!$val) return false;
				
			$list[]=array(
					'dtm'=>$step_dt,
					'val'=>$val,
			);
				
			
			if (!$prev)
			{
				$prev = $val;
				continue;
			}		
			
			// Определяем направление
			$dif = ($val-$prev);			
			if ($dif<(-1*$this->imp_dif)) $track.="-";
			elseif ($dif>$this->imp_dif) $track.="+";
			else $track.="0";
			

			//if ($from == '2013-12-11 16:15:01')			
			//Log::AddText($this->curtime, 'тек='.$val.' пред='.$prev.' разн='.$dif.' => '.$track);
			
			$prev = $val;
		}
		
		// Восстанавливаем нумерацию ключей
		//$list = array_values($list);
		
		$result = array(
				'track'=>$track,
				'from' => $from,
				'step' => $step,
				'period'=>$period,			
				'items' =>$list,	
				);
		
		return($result);
	} 
	
	/**
	 * Формирует список треков на которых выгодно покупать
	 * @param unknown_type $tracks
	 * @return multitype:unknown
	 */
	private function getBuyTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
			$ret = false;
			switch($track['track']){
				case '-0+':								 // \_/
				case '--+':								 // \\/
							// Если трек при падении не вернулся в исходную точку
							if($track['items'][0]['val'] - $track['items'][3]['val']>$this->imp_dif)							
								$result[] = $track; 
							break; 
			//	case '00+':	$result[] = $track; break; // __/
				case '0-+':							   // _\/
							// Если трек при падении не вернулся в исходную точку
							if($track['items'][1]['val'] - $track['items'][3]['val']>$this->imp_dif)
								$result[] = $track; 
							//Log::AddText(0, $track['items'][1]['val'] - $track['items'][3]['val'].' > '.$this->imp_dif);
							break; 
			// Если есть долгосрочное падение, не покупать 
				case '---':								// \\\
				case '+--':								// /\\
				case '0--':								// /\\
							if ($track['period']>self::long_time) {
								Log::Add($this->curtime, 'Замечено долгосрочное падение '.$track['track'].' в течении '.($track['period']/60).' мин., не покупаем');
								return false;								
							}
							break;				
			}			
		}		
		return $result;		
	}
	
	/**
	 * Формирует список треков на которых выгодно продавать
	 * @param unknown_type $tracks
	 * @return multitype:unknown
	 */
	private function getSellTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
			$ret = false;
			switch($track['track']){
				case '+0-':	$result[] = $track; break; // /-\
				case '++-':	$result[] = $track; break; // //\
			//	case '00-':	$result[] = $track; break; // --\
			//	case '0+-':	$result[] = $track; break; // -/\
			}
		}
		return $result;
	}
	
	private function AlreadyBought($period)
	{
		$key = 'track.'.$period;
		$tm = Yii::app()->cache->get($key);
		if (!$tm || $tm<$this->curtime)
			return false;
		else 
			return true;			
	}
	
	private function ReservePeriod($period)
	{
		$key = 'track.'.$period;
		return Yii::app()->cache->set($key, $this->curtime+$period, $period);
	}
	
	public function Buy()
	{
		$result = Order::makeOrder($this->current_exchange, self::buy_value, 'buy');
		
		if ($result)
		{	 
		$price = $this->current_exchange->buy*self::buy_value*(1+self::fee);
		$this->balance=$result['balance'];
		$this->balance_btc=$result['balance_btc'];
		
		if ($result['order']->status == 'open')
		{
			Log::Add($this->curtime, '<b>Создана сделка на покупку '.self::buy_value.' ед. за '.$this->current_exchange->buy.' ('.$this->current_exchange->buy*(self::fee).' комиссия) на сумму '.$price.' руб.</b>', 1);
		}
		else {
			Btc::buy($result['order']);
			Log::Add($this->curtime, '<b>Совершена покупка '.self::buy_value.' ед. за '.$this->current_exchange->buy.' ('.$this->current_exchange->buy*(self::fee).' комиссия) на сумму '.$price.' руб.</b>', 1);
		}
		
		return(true);
		}
		
		return false;
	}
	
	private function Sell($btc)
	{	
		$result = Order::makeOrder($this->current_exchange, $btc->count, 'sell', $btc->id);
		
		
		if ($result)
		{	
			$price = $this->current_exchange->sell*$btc->count*(1-self::fee);	
			
			if ($result['order']->status == 'open')
				Log::Add($this->curtime, '<b>Создал сделку на продажу (№'.$btc->id.')  '. $btc->count.' ед. (куплено за '.$btc->summ.') за '.$price.', доход = '.($price-$btc->summ).' руб.</b>', 1);
			else
			{
				Btc::sell($result['order']);
				Log::Add($this->curtime, '<b>Совершена продажа (№'.$btc->id.')  '. $btc->count.' ед. (куплено за '.$btc->summ.') за '.$price.', доход = '.($price-$btc->summ).' руб.</b>', 1);
			}
			$this->total_income+=$price-$btc->summ;
			return(true);
		}
	
		return false;
	}
	
	public function NeedBuyRandom()
	{
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);
	
		// Проверяем была ли уже покупка за последнее время
		$key = 'last_buy';
		$tm = Yii::app()->cache->get($key);
		if ($tm && $tm>$this->curtime)	return false;
		Yii::app()->cache->set($key, $this->curtime+self::min_buy_interval, self::min_buy_interval);
		
		if ($this->balance<$this->current_exchange->buy*self::buy_value)
		{
			Log::AddText($this->curtime, 'Не хватает денег, осталось '.$this->balance.', нужно '.($this->current_exchange->buy*self::buy_value));
			return false;
		}
		
		if (rand(0, 100) == 1)
			$this->buy();
	}
	
	public function NeedBuy()
	{		
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);
		$lastBuy = Btc::getLastBuy();
		
		// Проверяем была ли уже покупка за последнее время, если была и цена была более выгодная чем текущая то не покупаем
		$cache_key = 'last_buy';
		$tm = Yii::app()->cache->get($cache_key);		
		
		if ($tm && $tm>$this->curtime && $lastBuy->price - $this->current_exchange->buy < $this->imp_dif) return false;
		
		
		// Есть ли деньги
		if ($this->balance<$this->current_exchange->buy*self::buy_value) 
		{
			Log::Add($this->curtime, 'Не хватает денег, осталось '.$this->balance.', нужно '.($this->current_exchange->buy*self::buy_value));
			return false;
		}
		
		// Если текущая цена выше средней не покупаем		
		if ($this->avg_buy && $this->avg_buy<$this->current_exchange->buy)
		{
			Log::Add($this->curtime, 'Цена выше средней за 7 дней ('.$this->avg_buy.'<'.$this->current_exchange->buy.'), не покупаем.');
			return false;
		}
		
		//Перебираем в статистике периоды 15 мину, 30 мину, 1 час, 2 часов
		$periods = array(15*60, 30*60, 60*60, 2*60*60, 6*60*60, 24*60*60);
		$tracks=array();
		foreach($periods as $period)		
			$tracks[] = $this->getGraphImage($curtime, $period, 'buy');			
		
								// Log::AddText($this->curtime, 'Треки '.print_r($tracks, true));
								// Dump::d($tracks);
								
		//Анализируем треки
		$tracks = $this->getBuyTracks($tracks);
		if (!$tracks || sizeof($tracks) == 0) return false;		
								//Log::AddText($this->curtime, "Выгодные треки ".print_r($tracks, true));
		
		//Удаляем треки по которым уже были покупки
		foreach($tracks as $key=>$track)		
			if ($this->AlreadyBought($track['period']))		
			{
								//	Log::AddText($this->curtime, 'Уже была покупка по треку '.print_r($track, true));
				unset($tracks[$key]);
			}
								//	Log::AddText($this->curtime, 'Оставшиеся после отсеивания треки '.print_r($tracks, true));
			
		// Если остались треки
		if (sizeof($tracks)>0)
		{
			// Покупаем
			if ($this->buy())			
			// Резервируем время покупки
				foreach($tracks as $track)	
				{
					//Log::AddText($this->curtime, 'Трек <b>'.$track['track'].'</b> за '.($track['period']/60).' мин.');
					//Dump::d($track);
					$this->ReservePeriod($track['period']);					
				}	
					//Log::AddText($this->curtime, 'Резерв времени до: '. date('Y-m-d H:i:s',$this->curtime+self::min_buy_interval));
				Yii::app()->cache->set($cache_key, $this->curtime+self::min_buy_interval, self::min_buy_interval);

				
		}				
		else
		Log::AddText($this->curtime, 'Нет интересных покупок');		
	}
	
	public function NeedSell()
	{
		//Log::AddText($this->curtime, 'ПРОДАЖИ');
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);		
		
		
		// Если текущая цена ниже средней не продаем
		if ($this->avg_sell>$this->current_exchange->sell)
		{
			//Log::AddText($this->curtime, 'Цена ниже средней за 7 дней ('.$this->avg_sell.'>'.$this->current_exchange->buy.'), не продаем.');
			return false;
		}		
		
		//Перебираем периоды 9, 15, 30 минут, 1 час
		$periods = array(9*60, 15*60, 30*60, 60*60);
		$tracks=array();
		foreach($periods as $period)
		{
			$tracks[] = $this->getGraphImage($curtime, $period, 'sell');
		}	
		//Log::AddText($this->curtime, 'Треки '.print_r($tracks, true));
		//Dump::d($tracks);
		
		//Анализируем треки
		$tracks = $this->getSellTracks($tracks);
		
		if (sizeof($tracks) == 0) return false;
		
		//Log::AddText($this->curtime, 'Есть интересные треки для продажи'.print_r($tracks, true));
		
		
		//Смотрим что продать
		$bought = Btc::model()->with('sell')->findAll(array('condition'=>'sold=0'));
		
		foreach($bought as $btc)
		{
			// Цена продажи
			$curcost = $btc->count*$this->current_exchange->sell*(1-self::fee);
						
			// Сколько заработаем при продаже
			$income = $curcost - $btc->summ;
						
			// Достаточно ли заработаем
			if ($income < self::min_income)
			{
				if ($income>0)
				Log::Add($this->curtime, 'Не продали (№'.$btc->id.'), доход слишком мал '.$income.' < '.self::min_income.' купил за '.$btc->summ.' можно продать за '.$curcost.' sell='.$this->current_exchange->sell);
				
				//Dump::d($btc->attributes);
				continue;
			}
			
			$this->sell($btc);
			//Dump::d($tracks);
		}
		
		
	}
	

	public function checkOrders()
	{
		$orders = Order::model()->findAll(array('condition'=>'status="open"'));
					
		foreach($orders as $order)
		{
			$order_status = 'closed';
			
			if ($order->type == 'buy')
			{
				// @todo - если не ОК - вернуть деньги, заказ закрыть
				// @todo - получить из ЛК реальный баланс
					
				// проверяем состояние заказа через API
				// допустим все ок			
				$order->status='close';
				$order->close_dtm=date("Y-m-d H:i:s", $this->curtime);
				$order->update(array('status', 'close_dtm'));
				
				Btc::buy($order);			
				
				$this->balance_btc+=$order->count; 
				$this->order_cnt++;
			} elseif ($order->type == 'sell')
			{
				$order->status='close';
				$order->close_dtm=date("Y-m-d H:i:s", $this->curtime);
				$order->update(array('status', 'close_dtm'));
				
				if ($order->btc_id)
				{					
					Btc::sell($order);
				}			
				
				$this->balance_btc-=$order->count;
				$this->balance+=$order->summ;
				$this->order_cnt++;
				
			}
		}
	}
	
	public function run()
	{
		$this->NeedBuy();
		$this->NeedSell();
		$this->checkOrders();
		
		Status::setParam('balance', $this->balance);
		Status::setParam('balance_btc', $this->balance_btc);
		
		if ($this->order_cnt>0)
		{
				
			Log::AddText($this->curtime, 'Баланс (руб.): '.$this->balance, 1);
			//Log::Add(0, 'Всего заработано: '.$this->total_income, 1);
			Log::AddText($this->curtime, 'Остаток btc: '.round($this->balance_btc, 5), 1);
		}
		
	}
	
	
}