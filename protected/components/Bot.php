<?php

/**
 * Аналитика на MySQL
 * @author Zaretskiy.E
 *
 * @todo Определение кол.ва закупа - когда курс падает от среднего за двое суток на 20%, пускай бот покупает 5 минимумов
 * 
 */
class Bot {
	
	public $current_exchange;
	public $curtime;
	public $balance;
	public $balance_btc;
	private $order_cnt;
	private $total_income;
	private $imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	
	private $avg_buy; // Средняя цена покупки
	private $avg_sell;// Средняя цена продажи
	
	private $sell_periods; // Определение периодов покупки
	private $buy_periods; // Определение периодов продажи
	
	private $real_trade = false;
	
	private static $self=false;
	
	private $api; 
	
	//const imp_dif = 0.015; // Видимые изменения @todo сделать расчетным исходя из желаемого заработка и тек. курса
	//const min_buy = 0.01; // Мин. сумма покупки
	const buy_value = 0.02; // Сколько покупать
	const fee = 0.002; // Комиссия
	const min_buy_interval = 86400; // 86400; // Мин. интервал совершения покупок = 1 сутки
	const min_sell_interval = 86400;// 12 часов // Мин. интервал совершения продаж = 1 сутки
	const min_income = 0.04; // Мин. доход - 4%
	const long_time =  86400; // Понятие долгосрочный период - больше 2 дней
	const order_ttl = 180; // 180
	
	
	const freeze_warning_income = 0.01; // доход при котором есть шанс вморозить деньги, считается при падении
	
	public function __construct($exchange=false)
	{
		if (!$exchange)
			$exchange = Exchange::getLast();			
			
		$this->current_exchange = $exchange;
		$this->curtime = strtotime($exchange->dtm);
		
		$this->balance = Status::getParam('balance');
		$this->balance_btc = Status::getParam('balance_btc');
		$this->total_income=0;
		$this->imp_dif = 200;//self::min_income*(1+2*self::fee)*1/self::buy_value/4; // Здесь по расчетам 1000 / 4, на столько должен измениться курс чтобы бот заметил отличия
		
		$this->order_cnt=0;		
		
		$from = date('Y-m-d H:i:s', time()-60*60*24*7);
		$this->avg_buy = Exchange::getAvg('buy', $from,  date('Y-m-d H:i:s', $this->curtime));
		$this->avg_sell = Exchange::getAvg('sell', $from,  date('Y-m-d H:i:s', $this->curtime));		
		
		
		// Периоды анализа графика для покупки и продажи (в сек.)
		$this->buy_periods = array(15*60, 30*60, 60*60, 2*60*60, 6*60*60, 24*60*60, 36*60*60);		
		$this->sell_periods = array(/*9*60, 15*60, 30*60,*/ 60*60, 2*60*60, 4*60*60, 24*60*60, 36*60*60);
		
		$this->api = APIProvider::get_Instance();
		
		
		self::$self = $this;
	}
	
	public static function get_Instance()
	{
		if (!self::$self)
			self::$self = new Bot();
		return self::$self;
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
			
			if (!$val) 
			{
				//Log::AddText($this->curtime, 'Не нашел данных за период с'.$step_ut_f.' по '.$step_ut_t);
				return false;
			}
				
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
								//Log::Add($this->curtime, 'Замечено долгосрочное падение '.$track['track'].' в течении '.($track['period']/60).' мин., не покупаем');
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
	
	
	
	private function makeOrder($cnt, $type)
	{		
		// Цена покупки / продажи
		$price = $this->current_exchange->$type;
		
		
	
		// Пытаемся создать заказ на бирже
		$result = $this->api->makeOrder($cnt, 'btc_rur', $type, $price);

		// Если все ок, добавляем в базу созданный заказ
		$order = new Order();
		$order->id = $result['order_id'];
		$order->price = $price;		
		$order->summ = $cnt*$price;
		$order->count = $cnt;
		
		// Комиссия может быть в btc а может быть в rur
		if ($type == 'buy')
		{
			$order->fee = $cnt*self::fee;
			//$order->count = $cnt-$order->fee;	
		}
		else
		{
			$order->fee = $order->summ*self::fee;
			//$order->summ-=$order->fee;			
		}		
		
		$order->type = $type;
		$order->status = 'open';
		$order->create_dtm = $this->current_exchange->dtm;
		//if ($buy_id) $order->buy_id = $buy_id;
		
		// Заказ может быть сразу выполнене, в этом случае закрываем его в базе
		if($result['received'])
			$order->close($exchange->dtm);	
		
		$order->save();
		
		
		// Актуализируем баланс
		$this->setBalance($result['funds']['rur']);
		$this->setBalanceBtc($result['funds']['btc']);		
		
		$this->order_cnt++;
		
		return($order);
	}
	
	/**
	 * Подготовка к покупке (создание ордера, записей в бд)
	 * @return boolean
	 */
	public function startBuy()
	{
		
	//	if (!$this->real_trade) 
		//	return $this->virtualBuy(self::buy_value);
		
		// Создаем ордер
		$order = $this->makeOrder(self::buy_value, 'buy');		
		
		// Если создался
		if ($order)
		{				
			// Пишем в сводку
			Balance::add('rur', 'Создан ордер №'.$order->id.' на покупку '.$order->count.' btc', -1*$order->summ);			
			
			
			
			
			// Если создан ордер
			if ($order->status == 'open')				
				Log::Add($this->curtime, '<b>Создана сделка на покупку '.$order->count.' ед. за '.$order->price.' ('.($order->fee).' комиссия) на сумму '.$order->summ.' руб.</b>', 1);			
			// Если сразу куплено то закрыть ордер
			else 
				$this->completeBuy($order);			
			
			
		return(true);
		}
		
		return false;
	}
	
	/**
	 * Подготовка к продаже (создание ордера, записей в бд)
	 * @return boolean
	 */
	public function startSell($buy)
	{	

		// Создаем ордер
		$order = $this->makeOrder($buy->count, 'sell');
		
		if ($order)
		{	
			// Присваиваем BUY
			$buy->order_id = $order->id;
			$buy->update('order_id');					
			
			// Пишем в сводку
			Balance::add('btc', 'Создан ордер №'.$order->id.' на продажу '.$order->count.' btc', -1*$order->count);
			
			// Если не удалось сразу продать то заявка ждет 3 минуты своего покупателя
			if ($order->status == 'open')			
				Log::Add($this->curtime, '<b>Создал сделку на продажу (№'.$buy->id.')  '. $order->count.' ед. (куплено за '.$buy->summ.') за '.$order->price.', комиссия='.$order->fee.', доход = '.($order->summ-$buy->summ-$buy->fee-$order->fee).' руб.</b>', 1);			
			// Если сразу продали то закрываем позицию в бд
			else
			{			
				$sell = $this->completeSell($order);				
			}			
			
			
			return(true);
		}
	
		return false;
	}
	
	public function completeBuy($order)
	{
		// Закрываем ордер
		$order->close($this->current_exchange->dtm);
		$order->save();
		
		// Фиксируем в базе покупку
		$buy = Buy::make($order);		
		
		Log::Add($this->curtime, '<b>Совершена покупка №'.$buy->id.' '.$order->count.' ед. за '.$order->price.' ('.$order->fee.' btc комиссия) на сумму '.$order->summ.' руб.</b>', 1);
		$this->order_cnt++;
		
		// Для актуализации баланса при тесте
		$this->api = APIProvider::get_Instance();
		$this->balance_btc = $this->api->CompleteVirtualBuy($order);
		
		// Пишем в сводку
		Balance::add('btc', 'Закрыт ордер №'.$order->id.' на покупку '.$order->count.' btc', $order->count);
		Balance::add('btc', 'Начислена комиссия '.$order->fee.' btc', -1 * $order->fee);
		
		$this->order_cnt++;
	}
	
	public function completeSell($order)
	{
		// Закрываем ордер
		$order->close($this->current_exchange->dtm);
		$order->save();		
		
		$sell=Sell::make($order);	
		
		Log::Add($this->curtime, '<b>Совершена продажа (№'.$order->buy->id.')  '. $order->count.' ед. (купленых за '.$order->buy->summ.') за '.$sell->summ.', комиссия='.$sell->fee.', доход = '.($sell->income).' руб.</b>', 1);
		
		$this->order_cnt++;
		$this->total_income+=$sell->income;
		
		// Для актуализации баланса при тесте
		
		$this->balance = $this->api->CompleteVirtualSell($order);
		
		// Пишем в сводку
		Balance::add('rur', 'Закрыт ордер №'.$order->id.' на продажу '.$order->count.' btc', $order->summ);
		Balance::add('rur', 'Начислена комиссия '.$order->fee.' rur', -1*$order->fee);
		
		$this->order_cnt++;
	}
	
	
	public function NeedBuy()
	{		
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);
		
		
		// Есть ли деньги
		if ($this->balance<$this->current_exchange->buy*self::buy_value) 
		{
			Log::Add($this->curtime, 'Не хватает денег, осталось '.$this->balance.', нужно '.($this->current_exchange->buy*self::buy_value));
			return false;
		}
		
		// Если текущая цена выше средней не покупаем		
		if ($this->avg_buy && $this->avg_buy<$this->current_exchange->buy)
		{

			Log::Add($this->curtime, 'Цена выше средней за 7 дн. ('.$this->avg_buy.'<'.$this->current_exchange->buy.'), не покупаем.');
			return false;
		}
		
		// Проверяем была ли уже покупка за последнее время, если была и цена была более выгодная чем текущая то не покупаем
		$lastBuy = Buy::getLast();		
		if ($lastBuy)
		{
		$tm = strtotime($lastBuy->dtm)+self::min_buy_interval;		
		if ($tm>$this->curtime && $lastBuy->price - $this->current_exchange->buy < $this->imp_dif) return false;
		}		
		
		$tracks=array();
		foreach($this->buy_periods as $period)		
			$tracks[] = $this->getGraphImage($curtime, $period, 'buy');			
		
								// Log::AddText($this->curtime, 'Треки '.print_r($tracks, true));
								// Dump::d($tracks);
								
		//Анализируем треки
		$tracks = $this->getBuyTracks($tracks);
		if (!$tracks || sizeof($tracks) == 0) return false;		
							//	Log::AddText($this->curtime, "Выгодные треки ".print_r($tracks, true));
		
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
			if ($this->startBuy())			
			// Резервируем время покупки
				foreach($tracks as $track)	
				{
					//Log::AddText($this->curtime, 'Трек <b>'.$track['track'].'</b> за '.($track['period']/60).' мин.');
					//Dump::d($track);
					$this->ReservePeriod($track['period']);					
				}	
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
			//Log::AddText($this->curtime, 'Цена ниже средней за 7 дн. ('.$this->avg_sell.'>'.$this->current_exchange->buy.'), не продаем.');
			return false;
		}	
		
		// Проверяем была ли уже продажа за последнее время, если была и цена была более выгодная чем текущая то не продаем		
		$lastSell = Sell::getLast();
		if ($lastSell)
		{
			$tm = strtotime($lastSell->dtm)+self::min_buy_interval;			
			//Log::Add($curtime, 'Проверяем была ли продажа, ждем с '.$lastSell->dtm.' до '.date('Y-m-d H:i:s', $tm).' текущая цена '.$this->current_exchange->sell.' меньше текущей');
			if ($tm>$this->curtime && $this->current_exchange->sell - $lastSell->price < $this->imp_dif) 
			{
				//Log::Add($curtime, 'Уже была продажа, ждем до '.date('Y-m-d H:i:s', $tm).' текущая цена '.$this->current_exchange->sell.' меньше прошлой '.$lastSell->price);
				return false;
			}
		}
		
		//Перебираем периоды		
		$tracks=array();
		foreach($this->sell_periods as $period)
		{
			$tracks[] = $this->getGraphImage($curtime, $period, 'sell');
		}	
		//Log::AddText($this->curtime, 'Треки продажи'.print_r($tracks, true));
		//Dump::d($tracks);
		
		//Анализируем треки
		$tracks = $this->getSellTracks($tracks);
		
		if (sizeof($tracks) == 0) return false;
		
		//Log::AddText($this->curtime, 'Есть интересные треки для продажи'.print_r($tracks, true));
		
		
		//Смотрим что продать
		$bought = Buy::model()->with('sell')->findAll(array('condition'=>'sold=0 and order_id=0'));

		// Ищем выгодные продажи
		foreach($bought as $buy)
		{
			// Цена продажи
			$curcost = $buy->count*$this->current_exchange->sell*(1-self::fee);
									
			// Сколько заработаем при продаже (комиссия была уже вычтена в btc при покупке)
			$income = $curcost - $buy->summ;						
			
			// Достаточно ли заработаем
			if ($income/$buy->summ < self::min_income)
			{
				//if ($income>0) Log::Add($this->curtime, 'Не продали (№'.$buy->id.'), доход слишком мал '.$income.' < '.(self::min_income*$curcost).' купил за '.$buy->summ.' можно продать за '.$curcost.' sell='.$this->current_exchange->sell);							
				continue;
			}
			//else Log::Add($this->curtime, '$income='.$income.' $curcost='.$curcost.' self::min_income='.self::min_income);
			
			$this->startSell($buy);
			break;
			//Dump::d($tracks);
		}
		

		// Продаем то что может залежаться
		foreach($bought as $buy)
		{
			// Цена продажи
			$curcost = $buy->count*$this->current_exchange->sell*(1-self::fee);
			// Сколько заработаем при продаже
			$income = $curcost - $buy->summ*(1+self::fee);
			// Достаточно ли заработаем
			if ($income>0 && $income/$buy->summ < self::freeze_warning_income)
			{
				Log::Add($this->curtime, 'Вынужденная продажа, купили за '.$buy->summ.', текущая цена '.$curcost.', профит '.$income);
				$this->startSell($buy);
				continue;
			}
			//else Log::Add($this->curtime, 'Вынужденная продажа №'.$buy->id.' не состоялась $income='.$income.' $income/$buy->summ='.($income/$buy->summ).' self::freeze_warning_income='.self::freeze_warning_income);
				
			
		}
		
		
	}
	
	public function cancelOrder($order)
	{
	
		
		$res = $this->api->CancelOrder($order); 
		
		if ($res['success'] == 1)
		{
			
			$this->setBalance($res['return']['funds']['rur']);
			$this->setBalanceBtc($res['return']['funds']['btc']);
			
			$order->status = 'cancel';
			$order->close_dtm = $this->current_exchange->dtm;
			$order->save();
			
			// Пишем баланс
			if ($order->type == 'buy')			
				Balance::add('rur', 'Отмена ордера №'.$order->id.' на покупку', $order->summ);			
			else
				Balance::add('btc', 'Отмена ордера №'.$order->id.' на продажу', $order->count);
		}
	}

	public function checkOrders()
	{	
		
		// Получаем активные ордеры		
		$active_orders = $this->api->getActiveOrders();		
				
		// Получаем все открытые ордеры по бд 
		$orders = Order::model()->findAll(array('condition'=>'status="open"'));

		foreach($orders as $order)
		{			
			// Если ордер из базы найден среди активных
			if (isset($active_orders[$order->id]))
			{		
				// Если ордер висит более 3 минут - удаляем
				if ($active_orders[$order->id]['timestamp_created']<$this->curtime-self::order_ttl)
				{
					Log::AddText($this->curtime, 'Отменяем ордер №'.$order->id, 1);
					//Отменить ордер
					$this->cancelOrder($order);				
					continue;
				}
			}			
			
			// Если заказ не найден, значит он успешно выполнен (нужно это будет проверить)			
			if ($order->type == 'buy')
			{				
				$this->completeBuy($order);
				
			} elseif ($order->type == 'sell')
			{
				$this->completeSell($order);				
			}
		}
	}
	
	public function run()
	{
		
		$info = $this->api->getInfo();
		
		$start_balance = 0;
		$start_balance_btc = 0;
		
		if ($info)
		{
			$this->balance = $info['funds']['rur'];
			$this->balance_btc = $info['funds']['btc'];
			
			Status::setParam('balance', $info['funds']['rur']);
			Status::setParam('balance_btc', $info['funds']['btc']);

			$start_balance = $this->balance;
			$start_balance_btc = $this->balance_btc;
			
			Balance::actualize('rur', $this->balance);
			Balance::actualize('btc', $this->balance_btc);
		}	
		
		$this->NeedBuy();
		$this->NeedSell();
		$this->checkOrders();
		
		Status::setParam('balance', $this->balance);
		Status::setParam('balance_btc', $this->balance_btc);
		
		if ($this->order_cnt>0)
		{				
			Log::AddText($this->curtime, 'Баланс на начало');
			Log::AddText($this->curtime, 'Руб: '.$start_balance, 1);			
			Log::AddText($this->curtime, 'Btc: '.round($start_balance_btc, 5), 1);
			
			Log::AddText($this->curtime, 'Баланс на конец');
			Log::AddText($this->curtime, 'Руб: '.$this->balance, 1);
			Log::AddText($this->curtime, 'Btc: '.round($this->balance_btc, 5), 1);		
				
			Log::Add($this->curtime, 'Всего заработано: '.$this->total_income, 1);
		}
		
	}
	
	public function setBalance($summ)
	{
		$this->balance = round($summ, 5);
	}

	public function setBalanceBtc($summ)
	{
		$this->balance_btc = round($summ, 5);
	}
	
}