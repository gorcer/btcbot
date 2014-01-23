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

	
}
