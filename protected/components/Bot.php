<?php


class Bot {
	
	//Инициализируем переменные
	private $imp_div; 	   // Процент при котором считать подъем/падение = 1%	
	private $buy_value;
	private $buy_sum;
	private $fee; // Комиссия за операцию
	private $buystep_n; // Смотрим по 5 блоков
	private $analize_period; // Период за который анализируем график (6 часов)
	private $bought; // Список покупок
	private $order_cnt;
	private $balance;
	
	
	public function __construct()
	{
		//Инициализируем переменные
		$this->imp_div = 1/100; 	   // Процент при котором считать подъем/падение = 1%
		$this->buy_sum = 100; // Покупать на 100 руб.
		$this->fee = 0.2/100; // Комиссия за операцию
		$this->buystep_n = 5; // Смотрим по 5 блоков
		$this->analize_period = 60*60*6*10; // Период за который анализируем график (6 часов)
		$this->bought = array(); // Список покупок
		$this->order_cnt=0;		
		$this->bounght = Btc::model()->findAll();
	}
	
	private function AnalizeBuy($exdata)
	{
		$exlen = sizeof($exdata);
		$prev_stok_direction=0;// Предыдущее направление
		$stok_direction=0; 	   // Текущее направление		
		$lastbuy = Btc::getLastBuy(); // Получаем дату последней продажи
		
		for($i=0;$i<$exlen;$i++)
		{
		$exitem = $exdata[$i];
			
		// Если есть что анализировать
		if ($i<=$this->buystep_n+1) continue;
			
		// Определяем реальное отклонение за период
		$dif = $exdata[$i]['buy']-$exdata[$i-$this->buystep_n]['buy'];
		Log::Add($exitem['dt'], 'Сравниваем: ');
		Log::Add($exitem['dt'], 'Отличие '.$exdata[$i]['buy'].' - '.$exdata[$i-$this->buystep_n]['buy'].' = '.$dif);			
		$dif = round($dif / $exitem['buy'],4); // доля от последнего значения
		Log::Add($exitem['dt'], 'Средний % отличия: '.$dif*100);
			
		// Определяем направление кривой
		if ($dif<-1*$this->imp_div) $stok_direction=-1;
		elseif($dif>$this->imp_div) $stok_direction=1;
		else $stok_direction=0;
			
		Log::Add($exitem['dt'], 'Направление курса: '.$stok_direction);

		// Изменение курса
		if ($prev_stok_direction!=$stok_direction)
		$canbuy=true;

		// Если анализ до последней покупки - ничего не покупаем
		if ($exitem['dt']<=$lastbuy->dtm)
		$canbuy=false;
		
		// Проверяем покупку
		if ($stok_direction == 1 && $canbuy) // если график начал рост
		{
		

		// Если сумма покупки больше баланса то уменьшить до баланса		
		if ($this->buy_sum>$this->balance)
			$this->buy_sum=$this->balance;
		 
		$buy_value = floor($this->buy_sum / $exitem['buy']*(1+$this->fee)*1000)/10000;
		
		// Если 0 то поищем подешевле
		if ($buy_value == 0) continue;		
		
		$price = $exitem['buy']*$buy_value*(1+$this->fee);
		
		// Првоеряем остаток денег на балансе, если кончились - выходим
			if ($this->balance-$price<0) break;

			// Покупаем
			$btc = new Btc();
			$btc->dtm = $exitem['dt'];
			$btc->count = $buy_value;
			$btc->price = $exitem['buy'];
			$btc->summ = $price;
			if($btc->save())
			{
				$this->bought[]=$btc;				
					
				$this->total_buy+=$price; // Всего куплено
				$this->balance-=$price; // Актуализируем баланс RUB
				$this->balance_btc+=$buy_value; // Актуализируем баланс BTC
				$this->order_cnt++;   // Увеличиваем число сделок
				$canbuy=false; // Блокируем покупки до конца роста
				Log::Add($exitem['dt'], '<b>Купили '.$buy_value.' ед. за '.$exitem['buy'].' на сумму '.$price.'</b>');
			}
		}
		$prev_stok_direction=$stok_direction;
		}
	}
	
	/**
	 * Протестировать бота на старых данных
	 */
	public function runTest()
	{
		
				
		
		$this->balance = Status::getParam('balance');
		
		// Получаем статистику за период
		$period_from = date('Y-m-d H:i:s', time()-$this->analize_period);
		$exdata = Exchange::getDataFrom($period_from); // Получаем данные биржи
		
		$this->AnalizeBuy($exdata);
				
		Status::setParam('balance', $this->balance);
		
		echo '<br/>';
		echo '<br/>';
		
		Log::Add(0, 'Баланс (руб.): '.$this->balance);
		//Log::Add(0, 'Всего заработано: '.$total_income);
		Log::Add(0, 'Остаток btc: '.$this->balance_btc);
		
		
		
		
	}
	
}