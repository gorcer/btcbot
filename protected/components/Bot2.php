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
	
	const imp_dif = 0.02; // Видимые изменения
	const min_buy = 0.01; // Мин. сумма покупки
	const buy_value = 0.01; // Сколько покупать
	const fee = 0.002; // Комиссия
	const min_buy_interval = 120; // Мин. интервал совершения покупок = 2 мин.
	const min_income = 10; // Мин. доход в рублях
	 
	
	public function __construct($exchange)
	{
		$this->current_exchange = $exchange;
		$this->curtime = strtotime($exchange->dt);
		
		$this->balance = Status::getParam('balance');
		$this->balance_btc = Status::getParam('balance_btc');
		$this->total_income=0;
		
		$this->order_cnt=0;
		
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
			$dif = ($item['val']-$prev)/$item['val'];			
			if ($dif<(-1*self::imp_dif)) $track.="-";
			elseif ($dif>self::imp_dif) $track.="+";
			else $track.="0";
			
			if ($name == 'sell' && $track=='00-')
			Log::AddText($this->curtime, 'тек='.$item['val'].' пред='.$prev.' разн='.$dif.' => '.$track);
			
		}
		
		$result = array(
				'track'=>$track,
				'from' => date('Y-m-d H:i:s', time()-$period),
				'step' => $step,
				'period'=>$period,
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
				case '-0+':	$result[] = $track; break; // \_/
				case '--+':	$result[] = $track; break; // \\/
				case '00+':	$result[] = $track; break; // __/
				case '0-+':	$result[] = $track; break; // _\/				
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
				case '00-':	$result[] = $track; break; // --\
				case '0+-':	$result[] = $track; break; // -/\
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
	
	private function Buy()
	{
		

		if ($order=Order::makeOrder($this->current_exchange, self::buy_value, 'buy'))
		{	 
		$price = $this->current_exchange->buy*self::buy_value*(1+self::fee);
		$this->balance-=$price;
		Log::AddText($this->curtime, '<b>Создана сделка на покупку '.self::buy_value.' ед. за '.$this->current_exchange->buy.' ('.$this->current_exchange->buy*(self::fee).' комиссия) на сумму '.$price.' руб.</b>');
		return(true);
		}
		
		return false;
	}
	
	private function Sell($btc)
	{	
		if ($order=Order::makeOrder($this->current_exchange, $btc->count, 'sell', $btc->id))
		{
			$price = $this->current_exchange->buy*$btc->count*(1-self::fee);			
			Log::AddText($this->curtime, '<b>Создал сделку на продажу (№'.$btc->id.')  '. $item->count.' ед. (куплено за '.$btc->summ.') за '.$price.', доход = '.($price-$btc->summ).' руб.</b>');
			$this->total_income+=$price-$btc->summ;
			return(true);
		}
	
		return false;
	}
	
	public function NeedBuy()
	{		
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);
		
		// Есть ли деньги
		if ($this->balance<$this->current_exchange->buy*self::buy_value) 
		{
			Log::AddText($this->curtime, 'Не хватает денег, осталось '.$this->balance.', нужно '.($this->current_exchange->buy*self::buy_value));
			return false;
		}
		
		//Перебираем периоды 15 мину, 30 мину, 1 час
		$periods = array(15*60, 30*60, 60*60);
		$tracks=array();
		foreach($periods as $period)
		{
			$tracks[] = $this->getGraphImage($curtime, $period, 'buy');			
		}
		// Log::AddText($this->curtime, 'Треки '.print_r($tracks, true));
		// Dump::d($tracks);
		
		//Анализируем треки
		$tracks = $this->getBuyTracks($tracks);
		if (sizeof($tracks) == 0) return false;		
		//Log::AddText($this->curtime, "Выгодные треки ".print_r($tracks, true));
		
		//Удаляем треки по которым уже были покупки
		foreach($tracks as $key=>$track)		
			if ($this->AlreadyBought($track['period']))		
			{
			//	Log::AddText($this->curtime, 'Уже была покупка по треку '.print_r($track, true));
				unset($tracks[$key]);
			}
		//Log::AddText($this->curtime, 'Оставшиеся после отсеивания треки '.print_r($tracks, true));
			
		// Если остались треки
		if (sizeof($tracks)>0)
		{
			// Покупаем
			if ($this->buy())			
			// Резервируем время покупки
				foreach($tracks as $track)	
				{
					Log::AddText($this->curtime, 'Трек <b>'.$track['track'].'</b> за '.($track['period']/60).' мин.');
					$this->ReservePeriod($track['period']);
				}			
		}				
		else
		Log::AddText($this->curtime, 'Нет интересных покупок');		
	}
	
	public function NeedSell()
	{
		Log::Add($this->curtime, 'ПРОДАЖИ');
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);		
		
		//Перебираем периоды 15 мину, 30 мину, 1 час
		$periods = array(15*60, 30*60, 60*60);
		$tracks=array();
		foreach($periods as $period)
		{
			$tracks[] = $this->getGraphImage($curtime, $period, 'sell');
		}		
		
		
		//Анализируем треки
		$tracks = $this->getSellTracks($tracks);
		
		if (sizeof($tracks) == 0) return false;
		
		Log::Add($this->curtime, 'Есть интересные треки для продажи'.print_r($tracks, true));
		
		
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
				//if ($income>0)
				Log::Add($this->curtime, 'Не продали (№'.$btc->id.'), доход слишком мал '.$income.' < '.self::min_income);
				continue;
			}
			
			Log::Add($this->curtime, 'Выгодная цена, пробуем купить и получить доход'.$income);
			
			$this->sell($btc);
		}
		
		
	}
	

	public function checkOrders()
	{
		$orders = Order::model()->findAll(array('condition'=>'status="open"'));
	
		foreach($orders as $order)
		{
			
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