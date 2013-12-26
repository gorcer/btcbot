<?php

/**
 * Первая версия бота
 * Работает с циклами
 * @author Zaretskiy.E
 *
 */
class Bot_old1 {
	
	//Инициализируем переменные
	private $imp_div; 	   // Процент при котором считать подъем/падение = 1%	
	private $buy_value;
	//private $buy_sum;
	private $fee; // Комиссия за операцию
	private $buystep_n; // Число просматриваемых блоков при анализе покупки
	private $sellstep_n; // Число просматриваемых при анализе продажи 
	private $analize_period; // Период за который анализируем график (6 часов)
	private $bought; // Список покупок
	private $order_cnt;
	private $balance; // Текущий баланс
	private $balance_btc; // Текущий баланс
	private $total_income; // Всего заработано
	private $min_income; // Мин. доход
	private $min_buy;
	
	public $virtual = 1; // 0 - реальная работа, 1 - виртуальная работа, 2 - расчет по статистике
	
	
	public function __construct()
	{
		//Инициализируем переменные
		$this->fee = 0.2/100; // Комиссия за операцию
		$this->imp_div = 1/100; 	   // Процент при котором считать подъем/падение = 1%
		//$this->buy_sum = 350; // Покупать на 300 руб.
		$this->buy_value = 0.01; // Сколько покупать
		$this->buystep_n = 5; // Смотрим по ... блоков
		$this->sellstep_n = 4; // Смотрим по ... блоков
		$this->analize_period = 60*60*1; // Период за который анализируем график (6 часов)
		
		$this->min_income = 10; // Мин. доход
		$this->balance = Status::getParam('balance');
		$this->balance_btc = Status::getParam('balance_btc');
		
		$this->order_cnt=0;		
		$this->bought = Buy::model()->with('sell')->findAll();	
		$this->total_income=Sell::getTotalIncome();
	}
	
	private function getDirection($exdata, $type)
	{
		
		$len = sizeof($exdata);
		$last = $exdata[$len-1];
		
		// Определяем отклонение за период
		$dif = $last[$type]-$exdata[0][$type];
		Log::Add($last['dt'], 'Сравниваем: ');
		Log::Add($last['dt'], 'Отличие '.$last[$type].' - '.$exdata[0][$type].' = '.$dif);
		$dif = round($dif /$last[$type],4); // доля от последнего значения
		Log::Add($last['dt'], 'Средний % отличия: '.$dif*100);
			
		// Определяем направление кривой
		if ($dif<-1*$this->imp_div) $stok_direction=-1;
		elseif($dif>$this->imp_div) $stok_direction=1;
		else $stok_direction=0;
		
		return $stok_direction;
	}
	
	private function AnalizeSell($exdata)
	{
		$exlen = sizeof($exdata);
		$prev_stok_direction=0;// Предыдущее направление
		$stok_direction=0; 	   // Текущее направление
		$cansell=false;
		
		
		
		for($i=0;$i<$exlen;$i++)
		{
		$exitem = $exdata[$i];
		
		// Если есть что анализировать
		if ($i<=$this->buystep_n+1) continue;
		
		//Определяем направление
		$exstep = array_slice($exdata, $i-$this->sellstep_n, $this->sellstep_n+1);
		$stok_direction = $this->getDirection($exstep, 'sell');
		Log::Add($exitem['dt'], 'Направление курса: '.$stok_direction);

		// Изменение курса
		if ($prev_stok_direction!=$stok_direction)
			$cansell=true;
		
		
		// Если виртуальная работа
		if ($this->virtual == 1) {
			// Пока не дошли до последнего (актуального) элемента не продаем
			if ($i < $exlen-1)
				$cansell=false;
		}
		
		
		// Проверяем продажу
		if ($stok_direction == -1 && $cansell) // если график начал падение
		{
			// Ищем что продать
			foreach($this->bought as &$item)
			{
				
				
				if ($item->sold == 1) continue;				
				if ($item->dtm>$exitem['dt']) continue;

				// Цена продажи			
				$curcost = $item->count*$exitem['sell']*(1-$this->fee);
				
				// Сколько заработаем при продаже
				$income = $curcost - $item->summ;
				 
				// Если доход устраивает продаем
				if ($income>$this->min_income)
				{
					
					$this->balance+=$curcost; // Актуализируем баланс RUB
					$this->balance_btc-=$item->count; // Актуализируем баланс BTC					
					$this->total_income+=$income; // Актуализируем доход
					$item->SellIt($exitem['sell']*(1-$this->fee), $item->count, $income);
					$this->order_cnt++;
					$cansell=false; // блокируем продажи до конца падения
					Log::Add($exitem['dt'], '<b>Продал (№'.$item->id.')  '. $item->count.' ед. (куплено за '.$item->summ.') за '.$curcost.', доход = '.$income.' руб.</b>', 1);
				}
				elseif($income>0)
					Log::Add($exitem['dt'], 'Не продали (№'.$item->id.'), доход слишком мал '.$income.' < '.$this->min_income, 1);
			}
		}
		
		$prev_stok_direction=$stok_direction;
		}
	}
	
	private function AnalizeBuy($exdata)
	{
		$exlen = sizeof($exdata);
		$prev_stok_direction=0;// Предыдущее направление
		$stok_direction=0; 	   // Текущее направление		
		$lastbuy = Buy::getLast(); // Получаем дату последней продажи
		$canbuy=false;
		
		for($i=0;$i<$exlen;$i++)
		{
		$exitem = $exdata[$i];
			
		// Если есть что анализировать
		if ($i<=$this->buystep_n+1) continue;
			

		//Определяем направление
		$exstep = array_slice($exdata, $i-$this->buystep_n, $this->buystep_n+1);
		$stok_direction = $this->getDirection($exstep, 'buy');
		Log::Add($exitem['dt'], 'Направление курса: '.$stok_direction);

		// Изменение курса
		if ($prev_stok_direction!=$stok_direction)
			$canbuy=true;
		
		// Если анализ до последней покупки - ничего не покупаем
		if ($exitem['dt']<=$lastbuy->dtm)
			$canbuy=false;
		
		if ($this->virtual == 1) {
			// Пока не дошли до последнего (актуального) элемента не покупаем
			if ($i < $exlen-1)
				$canbuy=false;			
		}		
		
		// Для логов
		if ($this->virtual == 1 && $i == $exlen-1 && !$canbuy) 
			Log::Add($exitem['dt'], 'Не купил, курс не меняется: '.$prev_stok_direction.' => '.$stok_direction, 1);
		
		// Проверяем покупку
		if ($stok_direction == 1 && $canbuy) // если график начал рост
		{
		
			// Если сумма покупки больше баланса то уменьшить до баланса		
			/*if ($this->buy_sum>$this->balance)
				$this->buy_sum=$this->balance;
				
				$buy_value = floor(($this->buy_sum / $exitem['buy']*(1+$this->fee))*10000)/10000;

			// Если 0 то поищем подешевле
			if ($buy_value == 0) continue;
						*/		 
			
						
			$price = $exitem['buy']*$this->buy_value*(1+$this->fee);		
			// Првоеряем остаток денег на балансе, если кончились - выходим
				if ($this->balance-$price<0) break;
	
				// Покупаем
				$btc = new Buy();
				$btc->dtm = $exitem['dt'];
				$btc->count = $this->buy_value;
				$btc->price = $exitem['buy'];
				$btc->summ = $price;
				if($btc->save())
				{
					$this->bought[]=$btc;				
						
					$this->total_buy+=$price; // Всего куплено
					$this->balance-=$price; // Актуализируем баланс RUB
					$this->balance_btc+=$this->buy_value; // Актуализируем баланс BTC				
					$this->order_cnt++;   // Увеличиваем число сделок
					$canbuy=false; // Блокируем покупки до конца роста
					Log::Add($exitem['dt'], '<b>Купил (№'.$btc->id.') '.$this->buy_value.' ед. за '.$exitem['buy'].' ('.$exitem['buy']*($this->fee).' комиссия) на сумму '.$price.' руб.</b>', 1);
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
		// Получаем статистику за период
		$period_from = date('Y-m-d H:i:s', time()-$this->analize_period);
		$exdata = Exchange::getDataFrom($period_from); // Получаем данные биржи
		
		//$this->balance = 1000;
		//$exdata = Exchange::getTestData();
		
		Log::Add(0, 'ПОКУПАЕМ: ');
		$this->AnalizeBuy($exdata);
		Log::Add(0, 'ПРОДАЕМ: ');
		$this->AnalizeSell($exdata);
				
		Status::setParam('balance', $this->balance);
		Status::setParam('balance_btc', $this->balance_btc);
		
		if ($this->order_cnt>0)
		{		
			
			Log::Add(0, 'Баланс (руб.): '.$this->balance, 1);
			Log::Add(0, 'Всего заработано: '.$this->total_income, 1);
			Log::Add(0, 'Остаток btc: '.round($this->balance_btc, 5), 1);		
		}
		
		
		
		
	}
	
}