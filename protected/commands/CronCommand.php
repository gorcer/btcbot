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
	
	
		$exs = Exchange::getAllByDt('btc_rur','2013-12-16', '2014-01-06');
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
		$key = 'cron.bot.run.btc_rur';
		if(Yii::app()->cache->get($key)===false)
		{
			Yii::app()->cache->set($key, true, 60*3);
	
			// Запускаем бота для анализа и сделок
			$btc_rur = Exchange::updatePrices('btc_rur');
			$bot = new Bot($btc_rur);
			$bot->run();
		}
	}

	
}
