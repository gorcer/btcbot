<?php
class CronCommand extends CConsoleCommand {

	public function actionIndex() {
				
		// Сохраняем информацию по всем ценам
		foreach (APIProvider::$pairs as $pair)
		{
			$exch = Exchange::updatePrices($pair);
			echo $pair.': '.$exch->dtm.' => '.$exch->buy.', '.$exch->sell.'
';
		}	
		
	}		
	// Тестируем бота на текущих данных
	public function actionTest() {
	
		$start = time();
	
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Order::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Balance::model()->tableName());
			
		// Тест на 10 000 руб.
		Status::setParam('balance', 10000);
		Status::setParam('balance_btc', 0);
	
	
		$exs = Exchange::getAllByDt('btc_usd','2014-01-01', '2015-01-06');

		$sell = new Sell();
		$sell->buy_id=0;
		$sell->price=1000;
		$sell->fee = 0;
		$sell->summ = Bot::start_balance;;
		$sell->count=$sell->summ / $sell->price;
		$sell->income = 0;
		$sell->dtm = $exs[0]['dt'];
		$sell->buyed=0;
		$sell->save();
		
		
		$cnt=0;
		foreach($exs as $exchange)
		{
			$obj = new stdClass;
			$obj->dtm = $exchange['dt'];
			$obj->buy = $exchange['buy'];
			$obj->sell = $exchange['sell'];
				
			$cnt++;
			$bot = new Bot($obj);
			$bot->run();
		}
	
		$end = time();
	
		echo '<b>Elapsed time: '.(($end-$start)/60).' min.<br/>';
		echo '<b>Steps count: '.($cnt).'<br/>';
	
	}
	
	public function actionRun()
	{
		// Пересчитываем рейтинги
		$key = 'cron.bot.run.btc_usd';
		if(Yii::app()->cache->get($key)===false)
		{
			Yii::app()->cache->set($key, true, 60*3);
	
			// Запускаем бота для анализа и сделок
			$btc_usd = Exchange::updatePrices('btc_usd');
			$bot = new Bot($btc_usd);
			$bot->run();
		}
		
		// Првоеряем error_log
		$key = 'cron.email.error-logs';
		if(Yii::app()->cache->get($key)===false)
		{
			Yii::app()->cache->set($key, true, 60*60);
			$fn='error.log';
			if (file_exists($fn))
			{
				$headers  = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
				$text = file_get_contents($fn);
				mail('gorcer@gmail.com', 'Btcbot - ошибки', $text, $headers);
			}
		}

	}

	
}
