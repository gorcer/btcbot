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
	private $buy_imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	private $sell_imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	
	private $sell_periods; // Определение периодов покупки
	private $buy_periods; // Определение периодов продажи
	
	private $real_trade = false;
	
	private static $self=false;
	
	private $api; 
	

	//const min_buy = 0.01; // Мин. сумма покупки
	const buy_value = 0.02; //0.02; // Сколько покупать
	const fee = 0.002; // Комиссия
	const min_buy_interval = 86400; // 86400; // Мин. интервал совершения покупок = 1 сутки
	const min_sell_interval = 86400;// 12 часов // Мин. интервал совершения продаж = 1 сутки
	const min_income = 0.04; // Мин. доход - 4%
	const long_time =  86400; // Понятие долгосрочный период - больше 2 дней
	const order_ttl = 180; // 180
	const min_income_time = 900; // Минимальное время отведенное на рост курса
	
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
			
			//$val=Exchange::NOSQL_getAvg($name, $step_ut_f, $step_ut_t);
			$val=Exchange::getAvg($name, $step_ut_f, $step_ut_t);
			
			if (!$val) 
			{
				Log::Add($this->curtime, 'Не нашел данных за период с'.$step_ut_f.' по '.$step_ut_t);
				continue;
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
								$result[] = $track; 
							else 
								Log::notbuy('Найден удачный трек '.$track['track'].', но покупать уже поздно т.к. цена на падении была '.$track['items'][0]['val'].', а сейчас уже '.$track['items'][3]['val']);
										
							break; 
			//	case '00+':	$result[] = $track; break; // __/
				case '0-+':							   // _\/
							// Если трек при падении не вернулся в исходную точку
							if((1 - $track['items'][3]['val'] / $track['items'][1]['val']) > $this->buy_imp_dif)
								$result[] = $track;
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
				case '+0-':	$result[] = $track; break; // /-\				
				case '++-':	$result[] = $track; break; // //\
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
	
	
	private function makeOrder($cnt, $type, $reason='')
	{		
		// Цена покупки / продажи
		$price = $this->current_exchange->$type;
		
		// Пытаемся создать заказ на бирже
		$result = $this->api->makeOrder($cnt, 'btc_rur', $type, $price);

		if (!$result) return false;
		
		// Если все ок, добавляем в базу созданный заказ
		$order = new Order();
		$order->id = $result['order_id'];
		$order->price = $price;		
		$order->summ = $cnt*$price;
		$order->count = $cnt;
		$order->description = json_encode($reason);
		
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
	public function startBuy($reason)
	{		
		// Создаем ордер
		$order = $this->makeOrder(self::buy_value, 'buy', $reason);		
		
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
	public function startSell($buy, $reason)
	{	

		// Создаем ордер
		$order = $this->makeOrder($buy->count, 'sell',$reason);
		
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
		
		// Проверяем была ли уже покупка за последнее время, если была и цена была более выгодная чем текущая то не покупаем
		$lastBuy = Buy::getLast();		
		if ($lastBuy)
		{
			$tm = strtotime($lastBuy->dtm)+self::min_buy_interval;
			$diff = (1 - $this->current_exchange->buy / $lastBuy->price);		
			if ($tm>$this->curtime && $diff < $this->buy_imp_dif)
			{
				Log::notbuy('Уже была покупка '.(($this->curtime-strtotime($lastBuy->dtm))/60).' мин. назад (допустимы покупки раз в '.(self::min_buy_interval/60).' мин. при отсутствии ощутимого падения цены), прошлая цена '.$lastBuy->price.' руб., текущая '.$this->current_exchange->buy.' руб., разница '.$diff.'% , мин. порог для покупки '.($this->sell_imp_dif*100).'% ');
				return false;
			}
			else
				$reason['avg_price'] = 'Прошлая покупка была '.(($this->curtime-strtotime($lastBuy->dtm))/60).' мин. назад (допустимы покупки раз в '.(self::min_buy_interval/60).' мин. при отсутствии ощутимого падения цены), прошлая цена '.$lastBuy->price.' руб., текущая '.$this->current_exchange->buy.' руб., разница '.$diff.'% , мин. порог для покупки '.($this->sell_imp_dif*100).'% ';
		}		
		
		
		$all_tracks=array();		
		foreach($this->buy_periods as $period)		
			$all_tracks[] = $this->getGraphImage($curtime, $period, 'buy', $this->buy_imp_dif);

		
		//Анализируем треки
		$tracks=array();
		$tracks = $this->getBuyTracks($all_tracks);
		if (!$tracks || sizeof($tracks) == 0) 
		{
			Log::notbuy('Не найдено подходящих для продажи треков');
			return false;
		}
		
		
		//Удаляем треки по которым уже были покупки
		foreach($tracks as $key=>$track)		
			if ($this->AlreadyBought($track['period']))		
			{
				Log::notbuy('Уже была покупка PERIOD назад по треку '.print_r($track, true));
				unset($tracks[$key]);
			}
								//	Log::AddText($this->curtime, 'Оставшиеся после отсеивания треки '.print_r($tracks, true));
			
		// Если остались треки
		if (sizeof($tracks)>0)
		{
			// Треки
			$reason['tracks']=$tracks;
			$reason['all_tracks'] = $all_tracks;
			
			// Покупаем
			if ($this->startBuy($reason))	
			{		
			// Резервируем время покупки
				foreach($tracks as $track)	
				{
					//Log::AddText($this->curtime, 'Трек <b>'.$track['track'].'</b> за '.($track['period']/60).' мин.');
					//Dump::d($track);
					$this->ReservePeriod($track['period']);					
				}
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
		
		// Проверяем была ли уже продажа за последнее время, если была и цена была более выгодная чем текущая то не продаем		
		$lastSell = Sell::getLast();
		if ($lastSell)
		{
			$tm = strtotime($lastSell->dtm)+self::min_sell_interval;		
			$diff = (1-$lastSell->price / $this->current_exchange->sell);	
			
			if ($tm>$this->curtime && $diff < $this->sell_imp_dif) 
			{
				Log::notsell('Уже была продажа, ждем до '.date('Y-m-d H:i:s', $tm).' текущая цена '.$this->current_exchange->sell.' меньше прошлой '.$lastSell->price);
				return false;
			}
			else
			$reason['avg_price'] = 'Прошлая продажа была '.(($this->curtime-strtotime($lastSell->dtm))/60).' мин. назад (допустимы покупки раз в '.(self::min_sell_interval/60).' мин. при отсутствии ощутимого роста цены), цена отличалась от текущей на '.($diff*100).'%, минимальное отличие должно быть '.($this->sell_imp_dif*100).'% ';
		}
		
		//Перебираем периоды		
		$all_tracks=array();
		foreach($this->sell_periods as $period)		
			$all_tracks[] = $this->getGraphImage($curtime, $period, 'sell', $this->sell_imp_dif);		
		
		// Совершаем вынужденные продажи
		$this->NecesarySell($all_tracks);
		
		
		//Анализируем треки
		$tracks = $this->getSellTracks($all_tracks);		
		
		if (sizeof($tracks) == 0)
		{			
			return false;		
			Log::notsell('Нет подходящих треков для продажи');
		}
		
		$reason['tracks']=$tracks;
		$reason['all_tracks']=$all_tracks;
				
		//Смотрим, что продать
		$bought = Buy::model()->with('sell')->findAll(array('condition'=>'sold=0 and order_id=0'));

		// Ищем выгодные продажи
		foreach($bought as $key=>$buy)
		{
			// Цена продажи
			$curcost = $buy->count*$this->current_exchange->sell*(1-self::fee);
									
			// Сколько заработаем при продаже (комиссия была уже вычтена в btc при покупке)
			$income = $curcost - $buy->summ;						
			
			// Достаточно ли заработаем
			if ($income/$buy->summ < self::min_income)
			{
				if ($income>0) Log::notsell('Не продали (№'.$buy->id.'), доход слишком мал '.$income.' < '.(self::min_income*$curcost).' купил за '.$buy->summ.' можно продать за '.$curcost.' sell='.$this->current_exchange->sell);							
				continue;
			}			
			
			// Записываем причину покупки
			$reason['buy'] = 'Найдена подходящая продажа №'.$buy->id.' с доходом от сделки '.$income.' руб., что составляет '.($income/$buy->summ*100).'% от цены покупки'; 
			Log::Add($this->curtime, 'Начало продажи №'.$buy->id);
			$this->startSell($buy, $reason);
			//unset($bought[$key]);
			break; // не более одной продажи по расчету за раз
			
		}
		
	}
	
	// Вынужденная продажа, совершается когда купленный btc может залежаться
	private function NecesarySell($all_tracks)
	{
		$reason = array();
		//Анализируем треки
		$tracks = $this->getNecessarySellTracks($all_tracks);
		if (sizeof($tracks) == 0)
		{
			return false;
			Log::notsell('Нет подходящих треков для вынужденной продажи');
		}
		
		$bought = Buy::model()->with('sell')->findAll(array('condition'=>'sold=0 and order_id=0'));
		
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
			{
				
				$reason['sale'] = 'Вынужденная продажа №'.$buy->id.', купили за '.$buy->summ.', текущая цена '.$curcost.', доход '.$income.' ('.($income/$buy->summ*100).'% < '.(self::freeze_warning_income*100).'%)';
				$reason['tracks']=$tracks;
				$reason['all_tracks']=$all_tracks;
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