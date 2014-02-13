<?php

class SiteController extends Controller
{
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
			),
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
		);
	}

	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
	{
		// renders the view file 'protected/views/site/index.php'
		// using the default layout 'protected/views/layouts/main.php'
		$this->render('index');
	}

	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		if($error=Yii::app()->errorHandler->error)
		{
			if(Yii::app()->request->isAjaxRequest)
				echo $error['message'];
			else
				$this->render('error', $error);
		}
	}

	/**
	 * Displays the contact page
	 */
	public function actionContact()
	{
		$model=new ContactForm;
		if(isset($_POST['ContactForm']))
		{
			$model->attributes=$_POST['ContactForm'];
			if($model->validate())
			{
				$name='=?UTF-8?B?'.base64_encode($model->name).'?=';
				$subject='=?UTF-8?B?'.base64_encode($model->subject).'?=';
				$headers="From: $name <{$model->email}>\r\n".
					"Reply-To: {$model->email}\r\n".
					"MIME-Version: 1.0\r\n".
					"Content-type: text/plain; charset=UTF-8";

				mail(Yii::app()->params['adminEmail'],$subject,$model->body,$headers);
				Yii::app()->user->setFlash('contact','Thank you for contacting us. We will respond to you as soon as possible.');
				$this->refresh();
			}
		}
		$this->render('contact',array('model'=>$model));
	}

	/**
	 * Displays the login page
	 */
	public function actionLogin()
	{
		$model=new LoginForm;

		// if it is ajax validation request
		if(isset($_POST['ajax']) && $_POST['ajax']==='login-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}

		// collect user input data
		if(isset($_POST['LoginForm']))
		{
			$model->attributes=$_POST['LoginForm'];
			// validate user input and redirect to the previous page if valid
			if($model->validate() && $model->login())
				$this->redirect(Yii::app()->user->returnUrl);
		}
		// display the login form
		$this->render('login',array('model'=>$model));
	}

	/**
	 * Logs out the current user and redirect to homepage.
	 */
	public function actionLogout()
	{
		Yii::app()->user->logout();
		$this->redirect(Yii::app()->homeUrl);
	}
	

	public function actionUploadData()
	{
		// Сохраняем информацию по всем ценам		
			foreach (APIProvider::$pairs as $pair)
			Exchange::updatePrices($pair);
			
	}
	
	public function actionCron()
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
		
		// Првоеряем error_log
		$fn='error_log';
		
		if (file_exists($fn))
		{
				$headers  = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
				$text = file_get_contents($fn);
				mail('gorcer@gmail.com', 'Btcbot - ошибки', $text, $headers);					
		}
		
		
		
		// Сохраняем информацию по всем ценам
		/*
		foreach (APIProvider::$pairs as $pair)
			Exchange::updatePrices($pair);
			*/
		
		
		
	}
	
	public function actionRun()
	{
	
		if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		
		$BTCeAPI = new BTCeAPI();
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
	/*	
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell'];
		$exchange->dt = date('Y-m-d H:i:s', $ticker['updated']);		
		$exchange->save();
		
		$bot = new Bot($exchange);
		$bot->NeedBuy($ticker['updated']);
		$this->render('index');
		*/
	}
	
	public function actionClear() {
		
		if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Order::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Balance::model()->tableName());
		Status::setParam('balance', Bot::start_balance);
		Status::setParam('balance_btc', 0);
		
	}
	
	public function actionTest()
	{			
		
		if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		if (APIProvider::isVirtual == false) return;
			
		
		$start = time();
		
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Order::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Balance::model()->tableName());

		$exs = Exchange::getAll();
		
		$sell = new Sell();
		$sell->buy_id=0;
		$sell->price=35000;
		$sell->fee = 0;		
		$sell->summ = Bot::start_balance;;
		$sell->count=$sell->summ / $sell->price;
		$sell->income = 0;
		$sell->dtm = $exs[0]['dt'];
		$sell->buyed=0;
		$sell->save();
		
		Status::setParam('balance', $sell->summ);
		Status::setParam('balance_btc', 0);
		
		$min_balance = false;;
				
		
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

			if (!$min_balance || $min_balance > $bot->balance) $min_balance = $bot->balance;			
		}
		
		$end = time();
		
		echo '<b>Время выполнения: '.(($end-$start)/60).' мин.<br/>';
		echo '<b>Сделано шагов: '.($cnt).'<br/>';
		echo '<b>Мин. баланс: '.($min_balance).'<br/>';
		//$this->render('index');
	}
	
	public function actionBuy()
	{
		if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		
		$btc_rur = Exchange::updatePrices('btc_rur');			
				
		$bot = new Bot($btc_rur);
		$info = $bot->api->getInfo();
		
		if ($info)
		{
			$bot->setBalance($info['funds']['rur']);
			$bot->setBalanceBtc($info['funds']['btc']);			
				
			Status::setParam('balance', $info['funds']['rur']);
			Status::setParam('balance_btc', $info['funds']['btc']);
		
			Balance::actualize('rur', $bot->balance);
			Balance::actualize('btc', $bot->balance_btc);
		}	
			
			$bot->startBuy(array('test'=>'test'));			
	}
	
	public function actionSell()
	{
		if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		
		$btc_rur = Exchange::updatePrices('btc_rur');			
				
		$bot = new Bot($btc_rur);
		$info = $bot->api->getInfo();
		
		if ($info)
		{
			$bot->setBalance($info['funds']['rur']);
			$bot->setBalanceBtc($info['funds']['btc']);			
				
			Status::setParam('balance', $info['funds']['rur']);
			Status::setParam('balance_btc', $info['funds']['btc']);
		
			Balance::actualize('rur', $bot->balance);
			Balance::actualize('btc', $bot->balance_btc);
		}	
			$buy = Buy::model()->findByPk(1);	
		
			$bot->startSell($buy, array('test'=>'test'));
	}
	
	public function actionOrders()
	{
			if ($_SERVER['HTTP_HOST'] =='btcbot.gorcer.com') return;
		
		$btc_rur = Exchange::updatePrices('btc_rur');			
				
		$bot = new Bot($btc_rur);
		$info = $bot->api->getInfo();
		
		if ($info)
		{
			$bot->setBalance($info['funds']['rur']);
			$bot->setBalanceBtc($info['funds']['btc']);			
				
			Status::setParam('balance', $info['funds']['rur']);
			Status::setParam('balance_btc', $info['funds']['btc']);
		
			Balance::actualize('rur', $bot->balance);
			Balance::actualize('btc', $bot->balance_btc);
		}	
			
		$bot->checkOrders();
	}
	
	public function actionChartByTrack($dt)	
	{		
		
		$exch = Exchange::getAllByDt('btc_rur', date('Y-m-d H:i:s', strtotime($dt." - 2 days")),date('Y-m-d H:i:s', strtotime($dt." + 2 days")));
		
		foreach($exch as $item)
		{
			$tm = strtotime($item['dt'])*1000+4*60*60*1000;
			$data_buy[]=array((float)$tm, (float)$item['buy']);
			$data_sell[]=array((float)$tm, (float)$item['sell']);
		}
		
		$curtime = strtotime($dt);
		$bot = new Bot();
		$tracks = $bot->getAllTracks($curtime, 'buy', $bot->buy_imp_dif);
		
		$tracks_formed = array();
		foreach($tracks as $track)
		{
			$name = $track['period'].' '.$track['track'];
			foreach ($track['items'] as $point)
			{
				$tm = strtotime($point['dtm'])*1000+4*60*60*1000;				
				$tracks_formed[$name][]=array($tm, (float)$point['val']);				
			}
			$tracks_formedj[$name] = json_encode($tracks_formed[$name]);
		}
		
				$this->render('chart_by_tracks',
				array(
						'data_buy'	=> 	json_encode($data_buy),
						'data_sell'	=> 	json_encode($data_sell),
						'tracks' => $tracks_formedj,		
						'tracks_origin' => $tracks,				
						));		
	}
	
	public function actionChart($type='btc_rur')
	{	
		$buy = new Buy();
		//$exch = Exchange::getAll($type, '%Y-%m-%d %H:00:00');
		$exch = Exchange::getAll($type);
				
		$data_buy=array();
		$data_sell=array();
		$no_data=array();
		$last=false;
		
		$min_interval = 60 * 10 * 1000; 
	
		foreach($exch as $item)
		{
			$tm = strtotime($item['dt'])*1000+4*60*60*1000;
			$data_buy[]=array((float)$tm, (float)$item['buy']);
			$data_sell[]=array((float)$tm, (float)$item['sell']);
			
			// Заполняем информацию по пустым периодам
			$i=0;
			
			
			
			//Dump::d('tm='.date('Y-m-d H:i:s', $tm/1000).' last='.date('Y-m-d H:i:s', $last/1000).' diff='.($tm - $last));
			
			if ($last)
			while ($tm - $last > $min_interval)
			{
				//Dump::d('LOST='.date('Y-m-d H:i:s', $last/1000));
			//	echo $tm- $last.'<br/>';
				$i++;
				$last=(float)($last+$i*$min_interval);
				$no_data[] = array($last, (float)$item['buy']);				
			}
			
			$last = $tm;
		}
		
		
				
		if ($type == 'btc_rur')
		{
		
			// Покупки
			$orders = Order::model()->findAll(array('limit'=>'10', 'order'=>'id desc'));		
			$buys = Buy::model()->findAll();
			$sells = Sell::model()->findAll();
		}
		else
		{
			$orders = array();
			$buys = array();
			$sells = array();
			
		}
		
		$lastEx = Exchange::getLast();
		
		
		$status['total_income'] = Sell::getTotalIncome();
		$status['balance'] = Status::getParam('balance');
		$status['balance_btc'] = Status::getParam('balance_btc');
		$status['total_balance'] = $status['balance'] + $status['balance_btc']*$lastEx->sell;
		$status['start_balance'] = Status::getStartBalance();
		
		$this->render('chart',
				array(
						'data_buy'	=> 	json_encode($data_buy),
						'data_sell'	=> 	json_encode($data_sell),
						'no_data' => json_encode($no_data),
						'buys'	=>	$buys,
						'orders'	=>	$orders,
						'sells'	=>	$sells,
						'status'	=>	$status,
						));
	}
	
	
	/**
	 * Расчет годового дохода добытого на разных периодах
	 */
	public function actionPotencial()
	{
		
		$res = array();		
		
		foreach (APIProvider::$pairs as $pair)
		{
			$i=60*60;
			while ($i<60*60*24*7)
			{	
				if ($i<60*60)		
				$period = ($i/60).' мин.';
				else 
				$period = ($i/60/60).' ч.';
				
				$margin = (Bot::getAvgMargin($i, $pair)*100);
				$p = $margin/*/$i*60*24*360*/;
				//echo 'Потенциал периода '.$period.' = '.$p.'% в год <br/>';			
				
				$res[$pair][] = array($period, $p);
				
				if ($i<24 * 60 *60)
				$i=$i*2;
				else
					$i=$i+12*60*60;
	
			}
		}
		
		
		$this->render('potencial',
				array(
						'data'	=> 	$res,						
				));
		
	}
	
	public function actionViewOrder($id)
	{
		$order = Order::model()->findByPk($id);
		
		$order->description = json_decode($order->description, false);
		 
		$this->render('order',
				array(
						'data'	=> 	$order,
				));
	}
	
	
}