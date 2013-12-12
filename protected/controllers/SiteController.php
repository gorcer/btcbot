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
		die();
		$BTCeAPI = new BTCeAPI(
				/*API KEY: */ 'A6D0N5N2-MADY6TR3-4P3HYPAK-IQTZ8AOH-ILUSEX8H',
				/*API SECRET: */ 'f5175557ba8e6ec598a2a8d1d1ff97695e244670119c5098a406bfbd091b8b66'
		);
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
		
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell'];
		$exchange->dt = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		
		$exchange->save();

		$bot = new Bot();
		$bot->runTest();
	}
	
	public function actionRun()
	{
	
		$BTCeAPI = new BTCeAPI(
				/*API KEY: */ 'A6D0N5N2-MADY6TR3-4P3HYPAK-IQTZ8AOH-ILUSEX8H',
				/*API SECRET: */ 'f5175557ba8e6ec598a2a8d1d1ff97695e244670119c5098a406bfbd091b8b66'
		);
		$ticker = $BTCeAPI->getPairTicker('btc_rur');
		$ticker = $ticker['ticker'];
		
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell'];
		$exchange->dt = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);		
		$exchange->save();
		
		$bot = new Bot2($exchange);
		$bot->NeedBuy($ticker['updated']);
		$this->render('index');
	}
	
	public function actionTest()
	{
		
		
		
		
		Yii::app()->cache->flush();
		Yii::app()->db->createCommand()->truncateTable(Btc::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Status::setParam('balance', 5000);
		Status::setParam('balance_btc', 0);
		
				
		$exs = Exchange::model()->findAll(array('condition'=>'dt>"2013-12-10 00:00:01"'));
		foreach($exs as $exchange)
		{
			$bot = new Bot2($exchange);
			$bot->run();
			
		}
		
		//$this->render('index');
	}
	
	public function actionBuy()
	{
	die();
	
		$BTCeAPI = new BTCeAPI(
				/*API KEY: */ 'A6D0N5N2-MADY6TR3-4P3HYPAK-IQTZ8AOH-ILUSEX8H',
				/*API SECRET: */ 'f5175557ba8e6ec598a2a8d1d1ff97695e244670119c5098a406bfbd091b8b66'
		);
		
		// Making an order
		try {
			/*
			 * CAUTION: THIS IS COMMENTED OUT SO YOU CAN READ HOW TO DO IT!
			*/
			// $BTCeAPI->makeOrder(---AMOUNT---, ---PAIR---, BTCeAPI::DIRECTION_BUY/BTCeAPI::DIRECTION_SELL, ---PRICE---);
			// Example: to buy a bitcoin for $100
			 $BTCeAPI->makeOrder(0.01, 'btc_rur', BTCeAPI::DIRECTION_BUY, 29298.99);
			 
		} catch(BTCeAPIInvalidParameterException $e) {
			echo $e->getMessage();
		} catch(BTCeAPIException $e) {
			echo $e->getMessage();
		}
	}
}