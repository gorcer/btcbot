<?php

/**
 * Аналитика на MySQL
 * @author Zaretskiy.E
 *
 * @todo Определение кол.ва закупа - когда курс падает от среднего за двое суток на 20%, пускай бот покупает 5 минимумов
 * @todo Бот должен искать прошлую вершину при анализе ямы и принятии решения о покупке. И от неё отсчитывать поздно или нет.
 */
class Bot {
	
	public $current_exchange;
	public $curtime;
	public $balance;
	public $balance_btc;
	private $order_cnt;
	private $total_income;
	private $buy_imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	private $sell_imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	
	private $sell_periods; // Определение периодов покупки
	private $buy_periods; // Определение периодов продажи
	
	private $real_trade = false;
	
	private static $self=false;
	
	public $api; 
	
	private $tomail=array(); // Собираем сюда то что нужно отправить на email;
	

	const min_order_val = 0.011; // Мин. сумма покупки
	const buy_value = 0.02; //0.02; // Сколько покупать
	const fee = 0.002; // Комиссия
	const min_buy_interval = 86400; // 86400; // Мин. интервал совершения покупок = 1 сутки
	const min_sell_interval = 86400;// 12 часов // Мин. интервал совершения продаж = 1 сутки
	const min_income = 0.04; // Мин. доход - 4%
	const income_per_day = 0.01; // доход в день для залежных покупок, в расчете на 400% в год
	const long_time =  86400; // Понятие долгосрочный период - больше 2 дней
	const order_ttl = 180; // 180
	const min_income_time = 900; // Минимальное время отведенное на рост курса
	
	const freeze_warning_income = 0.005; // доход при котором есть шанс вморозить деньги, считается при падении
	
	public function __construct($exchange=false)
	{
		if (!$exchange)
			$exchange = Exchange::getLast();			
			
		$this->current_exchange = $exchange;
		$this->curtime = strtotime($exchange->dtm);
		
		$this->balance = Status::getParam('balance');
		$this->balance_btc = Status::getParam('balance_btc');
		$this->total_income=0;
		$this->buy_imp_dif = 0.005;// Шаг при анализе покупки 5% //150;
		$this->sell_imp_dif = 0.007; // Шаг при анализе продажи 7%
		
		$this->order_cnt=0;		
		
		
		// Периоды анализа графика для покупки и продажи (в сек.)
		$this->buy_periods = array(15*60, 30*60, 60*60, 2*60*60, 6*60*60, 24*60*60, 36*60*60);		
		$this->sell_periods = array(			 60*60, 2*60*60, 6*60*60, 24*60*60, 36*60*60);
		
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
	public function getGraphImage($curtime, $period, $name, $imp_dif)
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
			
			
			
			$val=Exchange::getAvg($name, $step_ut_f, $step_ut_t);
			
			
			if (!$val) 
			{
				
				$val = Exchange::getAvgBuyNear($name, $step_dt);
				//Log::Add('Не нашел данных за период с'.$step_ut_f.' по '.$step_ut_t.' использую ближайшее значение '.$val);
				if (!$val) continue;
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
			$dif = (1-$prev/$val);			
			if ($dif<(-1*$imp_dif)) $track.="-";
			elseif ($dif>$imp_dif) $track.="+";
			else $track.="0";		
			
			$prev = $val;
		}
		
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
							if ((1 - $track['items'][3]['val'] / $track['items'][0]['val']) > $this->buy_imp_dif)	
							{						
								$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
								$result[] = $track;
							} 
							else 
								Log::notbuy('Найден удачный трек '.$track['track'].', но покупать уже поздно т.к. цена на падении была '.$track['items'][0]['val'].', а сейчас уже '.$track['items'][3]['val']);
										
							break; 
				case '00+':	// __/
							$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
							$result[] = $track; 
							break; 
				case '0-+':							   // _\/
							// Если трек при падении не вернулся в исходную точку
							if((1 - $track['items'][3]['val'] / $track['items'][1]['val']) > $this->buy_imp_dif)
							{
								$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
								$result[] = $track;
							}
							else
								Log::notbuy('Найден удачный трек '.$track['track'].', но покупать уже поздно т.к. цена на падении была '.$track['items'][1]['val'].', а сейчас уже '.$track['items'][3]['val']);
							
							break; 
							
			// Если есть долгосрочное падение, не покупать 
				case '---':								// \\\
				case '+--':								// /\\
				case '0--':								// /\\
							if ($track['period']>self::long_time) {
								Log::notbuy('Замечено долгосрочное падение '.$track['track'].' в течении '.($track['period']/60).' мин., не покупаем');
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
				case '+0-':	 // /-\				
				case '++-':	 // //\
							$track['hill'] = Exchange::getHill($track['items'][0]['dtm'], $track['items'][3]['dtm']);
							$result[] = $track; 
							break;
			//	case '00-':	$result[] = $track; break; // --\
			//	case '0+-':	$result[] = $track; break; // -/\				
			}
			
			
		}
		return $result;
	}
	
	/**
	 * Формирует список треков на которых может быть вынужденная продажа
	 * @param unknown_type $tracks
	 * @return multitype:unknown
	 */
	private function getNecessarySellTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
			$ret = false;
			switch($track['track']){
				case '---':	$result[] = $track; break;
				case '0--':	$result[] = $track; break;
				case '-0-':	$result[] = $track; break;
				/*
				case '+0-':	$result[] = $track; break;
				case '0+-':	$result[] = $track; break;
				*/
			}
		}
		return $result;
	}
	
	
	// Создание отложенного ордера (если сразу не купили)
	private function createOrderRemains($result, $price, $type, $reason, $buy=false)
	{
		$order = new Order();
		$order->price = $price;
		$order->count = $result['remains'];
		$order->summ = $order->count * $price;
		
		
		// Комиссия может быть в btc а может быть в rur
		if ($type == 'buy')
			$order->fee = $order->count*self::fee;
		else
			$order->fee = $order->summ*self::fee;
		
		if ($buy) $order->buy_id = $buy->id;
		
		$order->description = json_encode($reason);
		$order->type = $type;
		$order->status = 'open';
		$order->create_dtm = $this->current_exchange->dtm;
		
		$order->id = $result['order_id'];
		$order->save();
		
		return ($order);
	}
	
	// Создание закрытого ордера на покупку(если сразу купили)
	private function createOrderReceived($result, $price, $type, $reason, $buy=false)
	{
		$order = new Order();
		$order->price = $price;
		$order->count = $result['received'];
		$order->summ = $order->count * $price;
		
		
		// Комиссия может быть в btc а может быть в rur
		if ($type == 'buy')
			$order->fee = $order->count*self::fee;
		else
			$order->fee = $order->summ*self::fee;
		
		if ($buy) $order->buy_id = $buy->id;
		
		$order->id = null;
		$order->description = json_encode($reason);
		$order->type = $type;		
		$order->status = 'close';
		$order->create_dtm = $this->current_exchange->dtm;
		$order->close_dtm = $this->current_exchange->dtm;
		$order->save();
		
		return ($order);
	}
	
	private function makeOrder($cnt, $price, $type, $reason='', $buy=false)
	{		
		// Цена покупки / продажи
	//	$price = $this->current_exchange->$type;
		
		// Пытаемся создать заказ на бирже
		$result = $this->api->makeOrder($cnt, 'btc_rur', $type, $price);

		if (!$result) return false;
		
		$orders = array();
		if ($result['remains']>0) $orders['remains'] = $this->createOrderRemains($result, $price, $type, $reason, $buy);
		if ($result['received']>0) $orders['received'] = $this->createOrderReceived($result, $price, $type, $reason, $buy);
		
		
		// Актуализируем баланс
		$this->setBalance($result['funds']['rur']);
		$this->setBalanceBtc($result['funds']['btc']);		
		
		$this->order_cnt+=sizeof($orders);
		
		return($orders);
	}
	
	/**
	 * Подготовка к покупке (создание ордера, записей в бд)
	 * @return boolean
	 */
	public function startBuy($reason)
	{		
		// Создаем ордер		
		$orders = $this->makeOrder(self::buy_value, $this->current_exchange->buy, 'buy', $reason);		
		
		// Если создался
		if (sizeof($orders)>0)
		{	
			// Если сразу купили
			if (isset($orders['received']))
			{
				// Пишем в сводку
				Balance::add('rur', 'Создан ордер №'.$orders['received']->id.' на покупку '.$orders['received']->count.' btc', -1*$orders['received']->summ);
				$this->completeBuy($orders['received']);
			}
			
			// Если отложенная покупка
			if (isset($orders['remains']))
			{
				// Пишем в сводку
				Balance::add('rur', 'Создан ордер №'.$orders['remains']->id.' на покупку '.$orders['remains']->count.' btc', -1*$orders['remains']->summ);
				Log::Add('<b>Создан ордер на покупку '.$orders['remains']->count.' ед. за '.$orders['remains']->price.' ('.($orders['remains']->fee).' комиссия) на сумму '.$orders['remains']->summ.' руб.</b>', 1);
			}			
			
		return(true);
		}
		
		return false;
	}
	
	/**
	 * Подготовка к продаже (создание ордера, записей в бд)
	 * @return boolean
	 */
	public function startSell($buy, $reason)
	{	

		// Создаем ордер
		$orders = $this->makeOrder($buy->count, $this->current_exchange->sell, 'sell', $reason, $buy);
		
		if (sizeof($orders)>0)
		{	
			// Если сразу продали
			if (isset($orders['received']))
			{
				// Присваиваем BUY номер заказа по которому оно будет продано
				$orders['received']->buy_id = $buy->id;
				$orders['received']->update('buy_id');
				
				// Пишем в сводку
				Balance::add('btc', 'Создан ордер №'.$orders['received']->id.' на продажу '.$orders['received']->count.' btc', -1*$orders['received']->count);
				
				$sell = $this->completeSell($orders['received']);
			}
			
			// Если отложенная покупка
			if (isset($orders['remains']))
			{
				// Присваиваем BUY номер заказа по которому оно будет продано
				$orders['remains']->buy_id = $buy->id;
				$orders['remains']->update('buy_id');				
				
				// Пишем в сводку
				Balance::add('btc', 'Создан ордер №'.$orders['remains']->id.' на продажу '.$orders['remains']->count.' btc', -1*$orders['remains']->count);
				Log::Add('<b>Создал сделку на продажу (№'.$buy->id.')  '. $orders['remains']->count.' ед. (куплено за '.$buy->summ.') за '.$orders['remains']->price.', комиссия='.$orders['remains']->fee.', доход = '.($orders['remains']->summ - ($buy->summ / $buy->count) * $orders['remains']->count  - $buy->fee - $orders['remains']->fee).' руб.</b>', 1);
			}		
			
			
			return(true);
		}
	
		return false;
	}
	
	public function completeBuy($order)
	{
		
		if ($order->status == 'open')
		{
			$order->close($this->current_exchange->dtm);
			$order->save();
		}
		
		// Фиксируем в базе покупку
		$buy = Buy::make($order);		
		
		// Для актуализации баланса при тесте с задержкой		
		if (APIProvider::isVirtual)
			$this->balance_btc = $this->api->CompleteVirtualBuy($order);
		
		// Пишем в сводку
		Balance::add('btc', 'Закрыт ордер №'.$order->id.' на покупку '.$order->count.' btc', $order->count);
		Balance::add('btc', 'Начислена комиссия '.$order->fee.' btc', -1 * $order->fee);
		Log::Add('<b>Совершена покупка №'.$buy->id.' '.$order->count.' ед. за '.$order->price.' ('.$order->fee.' btc комиссия) на сумму '.$order->summ.' руб.</b>', 1);
				
		$this->tomail[]='<b>Совершена покупка №'.$buy->id.' '.$order->count.' ед. за '.$order->price.' ('.$order->fee.' btc комиссия) на сумму '.$order->summ.' руб.</b>';
		
		$this->order_cnt++;
	}
	
	public function completeSell($order)
	{

		if ($order->status == 'open')
		{
			$order->close($this->current_exchange->dtm);
			$order->save();
		}
	
		$sell=Sell::make($order);
		
		// Для актуализации баланса при тесте покупок с задержкой		
		if (APIProvider::isVirtual)
			$this->balance = $this->api->CompleteVirtualSell($order);
		
		// Пишем в сводку
		Balance::add('rur', 'Закрыт ордер №'.$order->id.' на продажу '.$order->count.' btc', $order->summ);
		Balance::add('rur', 'Начислена комиссия '.$order->fee.' rur', -1*$order->fee);
		Log::Add('<b>Совершена продажа (№'.$order->buy->id.')  '. $order->count.' ед. (купленых за '.$order->buy->summ.') за '.$sell->summ.', комиссия='.$sell->fee.', доход = '.($sell->income).' руб.</b>', 1);
		$this->tomail[]='<b>Совершена продажа (№'.$order->buy->id.')  '. $order->count.' ед. (купленых за '.$order->buy->summ.') за '.$sell->summ.', комиссия='.$sell->fee.', доход = '.($sell->income).' руб.</b>';
		
		$this->total_income+=$sell->income;
		$this->order_cnt++;
	}
	
	
	public function NeedBuy()
	{		
		
		$reason = array(); // Фиксируем причину покупки
		
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);		
		
		// Есть ли деньги
		if ($this->balance<$this->current_exchange->buy*self::buy_value) 
		{
			Log::notbuy('Не хватает денег, осталось '.$this->balance.', нужно '.($this->current_exchange->buy*self::buy_value));
			return false;
		}
		else
			$reason['balance']='Хватает денег '.$this->balance.'>'.($this->current_exchange->buy*self::buy_value); 
		/*
		// Если текущая цена выше средней не покупаем
		$from = date('Y-m-d H:i:s',$this->curtime-60*60*24*7);
		$avg_buy = Exchange::getAvg('buy', $from,  date('Y-m-d H:i:s', $this->curtime));
		if ($avg_buy && $avg_buy<$this->current_exchange->buy)
		{
			Log::notbuy('Цена выше средней за 7 дн. ('.$avg_buy.'<'.$this->current_exchange->buy.'), не покупаем.');
			return false;
		}
		else
			$reason['avg_price']='Цена ниже средней за 7 дн. '.('.$avg_buy.'>'.$this->current_exchange->buy.');
			*/
		
		$lastBuy = Buy::getLast();
		$lastSell = Sell::getLast();
				
		if ($lastBuy)
		{
			$tm = strtotime($lastBuy->dtm)+self::min_buy_interval;			
			$diff_buy = (1 - $this->current_exchange->buy / $lastBuy->price);
			
			if ($lastSell) 
				$diff_sell = (1 - $this->current_exchange->buy / $lastSell->price);

			if ( $tm > $this->curtime 								// была ли уже покупка за последнее время 
				&& $diff_buy < $this->buy_imp_dif  					// и цена была более выгодная
				&&  (!$lastSell  || $lastSell->dtm < $lastBuy->dtm	// и небыло до этого продажи
						|| $diff_sell < $this->buy_imp_dif	// или была но цена была ниже текущей цены покупки
					)
				)
			{	
					// Не покупаем		
					Log::notbuy('Уже была покупка '.(($this->curtime-strtotime($lastBuy->dtm))/60).' мин. назад (допустимы покупки раз в '.(self::min_buy_interval/60).' мин. при отсутствии ощутимого падения цены), прошлая цена '.$lastBuy->price.' руб., текущая '.$this->current_exchange->buy.' руб., разница '.$diff_buy.'% , мин. порог для покупки '.($this->sell_imp_dif*100).'%.');
					if ($lastSell) Log::notbuy('Прошлая продажа была '.$lastSell->dtm.', это до последней покупки '.$lastBuy->dtm);
					return false;
				
			}
			else {
				$reason['last_buy'] = 'Прошлая покупка была '.(($this->curtime-strtotime($lastBuy->dtm))/60).' мин. назад (допустимы покупки раз в '.(self::min_buy_interval/60).' мин. при отсутствии ощутимого падения цены), прошлая цена '.$lastBuy->price.' руб., текущая '.$this->current_exchange->buy.' руб., разница '.$diff_buy.'% , мин. порог для покупки '.($this->sell_imp_dif*100).'% ';
				if ($lastSell) $reason['last_sell'] = 'Прошлая продажа была '.$lastSell->dtm.', это после последней покупки '.$lastBuy->dtm.' и цена последней покупки '.$lastSell->price.' выше текущей '.$this->current_exchange->buy;
			}
		}		
		
		
		$all_tracks=array();		
		foreach($this->buy_periods as $period)		
			$all_tracks[] = $this->getGraphImage($curtime, $period, 'buy', $this->buy_imp_dif);

		
		//Анализируем треки
		$tracks=array();
		$tracks = $this->getBuyTracks($all_tracks);
		
		
		
		if (!$tracks || sizeof($tracks) == 0) 
		{
			Log::notbuy('Не найдено подходящих для покупки треков'/*.Dump::d($all_tracks, true)*/);			
			return false;
		}
		

		foreach($tracks as $key=>$track)	
		{	
			//Удаляем треки по которым уже были покупки			
			if (Exchange::AlreadyBought_period($track['period'], $this->curtime))		
			{
				Log::notbuy('Уже была покупка PERIOD назад по треку '.print_r($track, true));
				unset($tracks[$key]);
			}
			
			// Удаляем треки которые происходят из ям по которым уже были покупки
			$last_pit = Exchange::getLastPit($track['period']);
			if ($last_pit == $track['pit']['dtm'])
			{
				Log::notbuy('Уже была покупка в яме '.$track['pit']['dtm'].' по треку '.print_r($track, true));
				unset($tracks[$key]);
			}
			
		}
			
								//	Log::AddText($this->curtime, 'Оставшиеся после отсеивания треки '.print_r($tracks, true));
			
		// Если остались треки
		if (sizeof($tracks)>0)
		{
			// Треки
			$reason['tracks']=$tracks;
			$reason['all_tracks'] = $all_tracks;
			
			// Берем первый удачный трек и по нему проводим покупку
			$first_track = array_pop($tracks);
			$reason['period'] = $first_track['period'];
			
			// Покупаем
			if ($this->startBuy($reason))	
			{				
				
				// Резервируем время покупки по резерву 
				Exchange::ReservePeriod($first_track['period'], $this->curtime);
				// Резервируем яму				
				Exchange::ReservePit($first_track['pit']['dtm'], $first_track['period']);
			}
			else
				Log::notbuy('Ошибка, не удалось начать покупку');
		}				
		else
		Log::notbuy('Нет интересных покупок');		
	}
	
	public function NeedSell()
	{
		// Составляем причину покупки
		$reason=array();
		$curtime = $this->curtime; //Дата операции
		$dt = date('Y-m-d H:i:s', $curtime);		

		//Смотрим, что продать
		//$bought = Buy::model()->findAll(array('condition'=>'sold=0 and order_id=0'));
		$bought = Buy::getNotSold();
		
		// Если нечего продавать
		if (sizeof($bought) == 0) return false;
		
		/*
		// Если текущая цена ниже средней не продаем
		$from = date('Y-m-d H:i:s',$this->curtime-60*60*24*7);
		$avg_sell = Exchange::getAvg('sell', $from,  date('Y-m-d H:i:s', $this->curtime));
		if ($avg_sell>$this->current_exchange->sell)
		{
			Log::notsell('Цена ниже средней за 7 дн. ('.$avg_sell.'>'.$this->current_exchange->buy.'), не продаем.');
			return false;
		}
		else
			$reason['avg_price'] = 'Текущая цена выше средней за 7 дней '.('.$this->avg_sell.'>'.$this->current_exchange->buy.'); 
		*/		
		
		//Перебираем периоды		
		$all_tracks=array();
		foreach($this->sell_periods as $period)		
			$all_tracks[] = $this->getGraphImage($curtime, $period, 'sell', $this->sell_imp_dif);		
		
		//Анализируем треки
		$tracks = $this->getSellTracks($all_tracks);		
		
		if (sizeof($tracks) == 0)
		{	
			Log::notsell('Нет подходящих треков для продажи');
			return false;
		}
		
		// Совершаем вынужденные продажи
		$this->NecesarySell($all_tracks, $bought);
		
		// Проверка прошлой продажи
		$lastSell = Sell::getLast();
		if ($lastSell)
		{
			$tm = strtotime($lastSell->dtm)+self::min_sell_interval;
			$diff = (1-$lastSell->price / $this->current_exchange->sell);				
			$last_hill = Exchange::getLastSellHill();
			$first_track = array_pop($tracks);
			$lastBuy = Buy::getLast();
			
			if ($tm>$this->curtime // Если с прошлой покупки не вышло время
					&& $diff < $this->sell_imp_dif // и цена не лучше
					&& (!$last_hill || $last_hill == $first_track['hill']['dtm'])// и прошлая продажа была на той же горке
					&& (!$lastBuy || $lastBuy->dtm < $lastSell->dtm) // и с последней продажи небыло покупок
			)
			{
				Log::notsell('Уже была продажа, ждем до '.date('Y-m-d H:i:s', $tm).' текущая цена '.$this->current_exchange->sell.' меньше прошлой '.$lastSell->price);
				if ($last_hill) Log::notsell('Была уже продажа на этой ('.$last_hill.') горке');
				if ($lastBuy) Log::notsell('С последней продажи небыло покупок');
				
				return false;
			}
			else
			{
				$reason['last_sell'] = 'Прошлая продажа была '.(($this->curtime-strtotime($lastSell->dtm))/60).' мин. назад (допустимы продажи раз в '.(self::min_sell_interval/60).' мин. при отсутствии ощутимого роста цены), цена отличалась от текущей на '.($diff*100).'%, минимальное отличие должно быть '.($this->sell_imp_dif*100).'% ';
				if ($last_hill) $reason['last_hill'] = 'Прошлая продажа была на горке '.$last_hill.', а текущая на горке '.$first_track['hill']['dtm']; 
			}
		}		
		
		$reason['tracks']=$tracks;
		$reason['all_tracks']=$all_tracks;
		
		// Ищем выгодные продажи
		foreach($bought as $key=>$buy)
		{
			
			// Цена продажи
			$curcost = $buy->count*$this->current_exchange->sell*(1-self::fee);
									
			// Сколько заработаем при продаже (комиссия была уже вычтена в btc при покупке)
			$income = $curcost - $buy->summ;						
			
			// Определяем мин. доход
			$life_days = ceil( (time() - strtotime($buy->dtm))/60/60/24 ); // Число прошедших дней с покупки
			$days_income = $life_days * self::income_per_day; // Ожидаемый доход
			if ($days_income < self::min_income) $days_income = self::min_income; // Если меньше мин. дохода то увеличиваем до мин.
			$need_income =  $buy->summ * $days_income; // Требуемый доход в рублях
			
			// Достаточно ли заработаем
			if ($income < $need_income)
			{
				if ($income>0) Log::notsell('Не продали (№'.$buy->id.'), доход слишком мал '.$income.' < '.$need_income.'. Купил за '.$buy->summ.' можно продать за '.$curcost.' цена продажи='.$this->current_exchange->sell.', дней с момента покупки='.$days_income.', % ожидаемой прибыли '.($days_income*100));							
				continue;
			}			
			
					
			// Записываем причину покупки
			$reason['buy'] = 'Найдена подходящая продажа №'.$buy->id.' с доходом от сделки '.$income.' руб., что составляет '.($income/$buy->summ*100).'% от цены покупки'; 
			Log::Add('Начало продажи №'.$buy->id);
			
			$first_track = array_pop($tracks);
			$reason['period'] = $first_track['period'];
			if ($this->startSell($buy, $reason))
			{				
				
				Exchange::ReserveLastSellHill($first_track['hill']['dtm']); // резервируем холм
				break; 	// не более одной продажи по расчету за раз
			}
			
			
			//unset($bought[$key]);

			
		}
		
	}
	
	// Вынужденная продажа, совершается когда купленный btc может залежаться
	private function NecesarySell($all_tracks, $bought)
	{
		$reason = array();
		//Анализируем треки
		$tracks = $this->getNecessarySellTracks($all_tracks);
		if (sizeof($tracks) == 0)
		{
			return false;
			Log::notsell('Нет подходящих треков для вынужденной продажи');
		}
		
		
		// Продаем то что может залежаться
		foreach($bought as $buy)
		{
			// Если с покупки прошло мало времени, то не продаем
			if ($this->curtime - strtotime($buy->dtm) < self::min_income_time) 
			{	
				Log::notsell('Не совершили вынужденную продажу №'.$buy->id.' так как не вышло время с покупки. Купили '.$buy->dtm);
				continue;
			} 
			
			// Цена продажи
			$curcost = $buy->count*$this->current_exchange->sell*(1-self::fee);
			// Сколько заработаем при продаже
			$income = $curcost - $buy->summ*(1+self::fee);
			// Достаточно ли заработаем
			
			if ($income>0 && $income/$buy->summ < self::freeze_warning_income)
			//if (abs($income/$buy->summ) < self::freeze_warning_income)				
			{
				
				$reason['sale'] = 'Вынужденная продажа №'.$buy->id.', купили за '.$buy->summ.', текущая цена '.$curcost.', доход '.$income.' ('.($income/$buy->summ*100).'% < '.(self::freeze_warning_income*100).'%)';
				$reason['tracks']=$tracks;
				$reason['all_tracks']=$all_tracks;
				
				$first_track = array_pop($tracks);
				$reason['period'] = $first_track['period'];
				
				$this->startSell($buy, $reason);
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
		
		// Получаем все открытые ордеры по бд
		$orders = Order::model()->findAll(array('condition'=>'status="open"'));
		
		// Если нет заказов ничего не проверяем
		if (sizeof($orders) == 0) return false;
		
		// Получаем активные ордеры		
		$active_orders = $this->api->getActiveOrders();		
				
		//Log::Add('Найдены активные ордеры '.Dump::d($active_orders, true));

		foreach($orders as $order)
		{			
			// Если ордер из базы найден среди активных
			if (isset($active_orders[$order->id]))
			{		
				// Если ордер висит более 3 минут - удаляем
				if ($active_orders[$order->id]['timestamp_created']<$this->curtime-self::order_ttl)
				{
					Log::Add('Отменяем ордер №'.$order->id, 1);
					//Отменить ордер
					$this->cancelOrder($order);			
				}
				else
					Log::Add('Ордер висит менее 3 минут. '.date('Y-m-d H:i:s', $active_orders[$order->id]['timestamp_created']).'<'.date('Y-m-d H:i:s', $this->curtime-self::order_ttl));
				
				// Переходим к следующему ордеру
				continue;
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
		
		
		$this->tomail=array();
		
		$this->NeedBuy();
		$this->NeedSell();		
		$this->checkOrders();
		
		if (sizeof($this->tomail)>0) $this->sendMail();
		
		Status::setParam('balance', $this->balance);
		Status::setParam('balance_btc', $this->balance_btc);
		
		if ($this->order_cnt>0)
		{				
			Log::Add('Баланс на начало');
			Log::Add('Руб: '.$start_balance, 1);			
			Log::Add('Btc: '.$start_balance_btc, 1);
			
			Log::Add('Баланс на конец');
			Log::Add('Руб: '.$this->balance, 1);
			Log::Add('Btc: '.$this->balance_btc, 1);		
				
			Log::Add('Всего заработано: '.$this->total_income, 1);
		}
		
	}
	
	private function sendMail()
	{
		$text='';
		foreach ($this->tomail as $item)
			$text.=$item.' <br/>';

		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
		
		mail('gorcer@gmail.com', 'Btcbot - Новые сделки', $text, $headers);
	}
	
	public function setBalance($summ)
	{
		$this->balance = round($summ, 5);
	}

	public function setBalanceBtc($summ)
	{
		$this->balance_btc = round($summ, 5);
	}
	
	
	public static function getAvgMargin($period, $pair='btc_rur')
	{
		$connection = Yii::app()->db;
		$sql = "
				SELECT AVG( t.val ) 
				FROM (				
					SELECT ABS( MIN( buy ) - MAX( sell ) )/MIN( buy ) AS val,
					from_unixtime(round(UNIX_TIMESTAMP(dtm)/(".$period."))*".$period.", '%Y.%m.%d %H:%i:%s')as dt
					FROM `exchange`
					where
					pair = '".$pair."'
					group by dt
				) as t
				";
		
				$command = $connection->createCommand($sql);
				$val=$command->queryScalar();
		
		return round($val,2);
	}
	
}