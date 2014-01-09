<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.


return CMap::mergeArray(
		require(dirname(__FILE__).'/main.php'),
		array(
	'timeZone' => 'Europe/Moscow',

	'modules'=>array(		
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'159357',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			//	'ipFilters'=>array('127.0.0.1','::1'),
			),
		
		),
	'components'=>array(
		/*'cache'=>array(
					'class'=>'system.caching.CDummyCache',
			),*/
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=btcbot',
			'emulatePrepare' => true,
			'username' => 'root',
			'password' => '159357',
			'charset' => 'utf8',
			'tablePrefix' => 'aim_',
			'enableProfiling' => true,
			'enableParamLogging' => true,
			'schemaCachingDuration' => 60*60*24,			
		),
		
		
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
		/*		array(
						'class'=>'ext.yii-debug-toolbar.YiiDebugToolbarRoute',
						'ipFilters'=>array('85.95.152.244', '95.154.72.96', 'localhost', '127.0.0.1'),
				),*/
				array(
						'class' => 'CFileLogRoute',
						'logFile' => 'yii-error.log',						
						'levels' => 'error, warning, info',
				),			
			),
		),
	),
	
	'params'=>array(
		'server_id'=>1
	),	
	
));
