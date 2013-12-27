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
	
	public function actionCron()
	{
		
		$BTCeAPI = new BTCeAPI();		
		
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
		
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell'];
		$exchange->dtm = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		
		if ($exchange->save())
		{
			return;
			$bot = new Bot($exchange);
			$bot->run();
		}
		
	}
	
	public function actionRun()
	{
	
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
		
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Order::model()->tableName());
		Status::setParam('balance', 5000);
		Status::setParam('balance_btc', 0);
		
	}
	
	public function actionTest()
	{	
		
		$start = time();
		
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Order::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Balance::model()->tableName());
		
		Status::setParam('balance', 5000);
		Status::setParam('balance_btc', 0);
		
				
		$exs = Exchange::getAll();
		$cnt=0;
		foreach($exs as $exchange)
		{
			$obj = new stdClass;
			$obj->dtm = $exchange['dtm'];
			$obj->buy = $exchange['buy'];
			$obj->sell = $exchange['sell'];
			
			$cnt++;
			$bot = new Bot($obj);
			$bot->run();			
		}
		
		$end = time();
		
		echo '<b>Время выполнения: '.(($end-$start)/60).' мин.<br/>';
		echo '<b>Сделано шагов: '.($cnt).'<br/>';
		//$this->render('index');
	}
	
	public function actionBuy()
	{
		
		$BTCeAPI = new BTCeAPI();
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
		
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy']-10000;
		$exchange->sell = $ticker['sell'];
		$exchange->dtm = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		
		$bot = new Bot($exchange);
		$bot->startBuy();
	}
	
	public function actionSell()
	{
	
		$BTCeAPI = new BTCeAPI();
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
	
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell']+10000;
		$exchange->dtm = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		$btc = Buy::getLast();
		$bot = new Bot($exchange);
		$bot->startSell($btc);
	}
	
	public function actionOrders()
	{
		$BTCeAPI = new BTCeAPI();
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
	
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell']+10000;
		$exchange->dtm = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		$btc = Buy::getLast();
		$bot = new Bot($exchange);
		
		$bot->checkOrders();
	}
	
	public function actionChart()
	{	
		$buy = new Buy();
		$exch = Exchange::getAll();
		
		
		$data_buy=array();
		$data_sell=array();
		
		
		foreach($exch as $item)
		{
			$tm = strtotime($item['dtm'])*1000+4*60*60*1000;
			$data_buy[]=array($tm, (float)$item['buy']);
			$data_sell[]=array($tm, (float)$item['sell']);
		}
		
				
		// Покупки
		$orders = Order::model()->findAll();
		
		$lastEx = Exchange::getLast();
		$status['total_income'] = Sell::getTotalIncome();
		$status['balance'] = Status::getParam('balance');
		$status['balance_btc'] = Status::getParam('balance_btc');
		$status['total_balance'] = $status['balance'] + $status['balance_btc']*$lastEx->sell;
		
		$this->render('chart',
				array(
						'data_buy'	=> 	json_encode($data_buy),
						'data_sell'	=> 	json_encode($data_sell),						
						'orders'	=>	$orders,
						'status'	=>	$status,
						));
	}
}