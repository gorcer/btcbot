<?php 

function logit($dt, $data)
{
	echo '<i>'.$dt.'</i> '.$data.'<br/>';
}

/*
 * Хранилище всех данных
 * dt, sum
 */
$stok=array();

/* Купленные
 * dt, cnt, summ, price
 * 
 */
$bought = array();

// Процент при котором считать подъем/падение
$imp_div = 1/100;

// Число шагов для просмотра
$step_cnt = 2;

// Направление графика (-1,0,1)
$stok_direction=0;
$prev_stok_direction=0;

// Сколько покупать
$buy_value=0.01;

// Комиссия за операцию
$fee = 0.2/100; //0.2%

// Мин. заработок (руб.)
$min_income = 1;

// Всего заработано
$total_income=0;

// Всего вложено
$total_buy=0;

// Доступный баланс
$balance = 5000;
$fbalance = $balance;
$balance_btc=0;

// Даты работы
$dt_from=$dt_to=false;

// Число сделок
$order_cnt=0;

// Разрешено покупать/продавать
$canbuy = true;
$cansell = true;

if (($handle = fopen("data.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 100, ";")) !== FALSE) {
       
    	$stok[]=$data;
    	
    	// Запоминаем дату начала и конца
    	if (!$dt_from) $dt_from=$data[0];
    	$dt_to = $data[0];
    	
    	
    	// Если есть что анализировать
    	if (sizeof($stok)<=$step_cnt+1) continue;    	
    	
    	
    	
    	/*
    	// Определяем среднее отклонение
    	$pos = sizeof($stok)-1;    	
    	logit($data[0], 'Проверяем период '.$stok[$pos][0].' - '.$stok[$pos-$step_cnt][0]);
    	
    	logit($data[0], 'Рассматриваем значения:');
    	$sum_dif=0;
    	for ($i=$step_cnt;$i>0;$i--)
    	{	
    		$sum_dif+= $stok[$pos-$i][1]-$stok[$pos-$i-1][1];    		
    		logit($data[0], 'Cумма отличий '.$sum_dif.' = '.$stok[$pos-$i][1].' - '.$stok[$pos-$i-1][1]);
    	}
    	$dif = $sum_dif / $step_cnt; // номинальное отличие
    	logit($data[0], 'Среднее номинальное отличие: '.$dif);
    	*/
    	
    	// Определяем реальное отклонение за период
    	$pos = sizeof($stok)-1;
    	$dif = $stok[$pos][1]-$stok[$pos-$step_cnt][1];    	
    	logit($data[0], 'Сравниваем: ');
    	logit($data[0], 'Отличие '.$stok[$pos][1].' - '.$stok[$pos-$step_cnt][1].' = '.$dif);
    	
    	
    	$dif = round($dif / $stok[$pos][1],4); // доля от последнего значения
    	logit($data[0], 'Среднее % отличие: '.$dif*100);
    	
    	// Определяем направление кривой
    	if ($dif<-1*$imp_div) $stok_direction=-1;
    	elseif($dif>$imp_div) $stok_direction=1;
    	else $stok_direction=0;
    	logit($data[0], 'Направление курса: '.$stok_direction);
    	
    	// Изменение курса
    	if ($prev_stok_direction!=$stok_direction)
    	{
    		$canbuy=true;
    		$cansell=true;
    	}
    	
    	
    	
    	// Проверяем покупку
    	if ($stok_direction == 1 && $canbuy) // если график начал рост
    	{
    		$price = $data[1]*$buy_value*(1+$fee);
    		
    		// Првоеряем остаток денег на балансе
    		if ($balance-$price<0) continue;   		
    			
    		// Покупаем
    		$bought[]=array(
    				'dt'=>$data[0],
    				'cnt'=>$buy_value,
    				'price'=>$data[1],
    				'summ'=>$price
    				);
    		
    		
    		$total_buy+=$price; // Всего куплено
    		$balance-=$price; // Актуализируем баланс RUB
    		$balance_btc+=$buy_value; // Актуализируем баланс BTC
    		$order_cnt++;   // Увеличиваем число сделок
    		$canbuy=false; // Блокируем покупки до конца роста
    		logit($data[0], '<b>Купили '.$buy_value.' ед. за '.$data[1].' на сумму '.$price.'</b>');
    	}
    	
    	// Првоеряем продажу
    	if ($stok_direction == -1 && $cansell) // если график начал падение
    	{
    		// Ищем что продать
    		foreach($bought as &$item)
    		{
    			if (isset($item['sell'])) continue;
    			
    			$curcost = $item['cnt']*$data[2]*(1-$fee);
    			// Сколько заработаем при продаже
    			$income = $curcost - $item['summ'];
    			
    			// Если доход устраивает продаем
    			if ($income>$min_income)
    			{
    				$balance+=$income; // Актуализируем баланс RUB
    				$balance_btc-=$item['cnt']; // Актуализируем баланс BTC
    				$total_income+=$income; // Актуализируем доход
    				$item['sell']=$income;
    				$cansell=false; // блокируем продажи до конца падения
    				logit($data[0], '<b>Продали '. $item['cnt'].' ед. (куплено за '.$item['summ'].') за '.$curcost.', доход = '.$income.'</b>');    				
    			}
    		}
    	}
    	
    	$prev_stok_direction = $stok_direction;
    }
    fclose($handle);
}


echo '<br/>';
echo '<br/>';

logit(0, 'Исходный баланс (руб.): '.$fbalance);
logit(0, 'Всего заработано: '.$total_income);
logit(0, 'Остаток btc: '.$balance_btc);
logit(0, 'Период: '.$dt_from.' - '.$dt_to);




?>