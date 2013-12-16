<?php
ini_set('max_execution_time', 4*60*60);
error_reporting(E_ALL);
// change the following paths if necessary

//$config=dirname(__FILE__).'/protected/config/main.php';
defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);


if ($_SERVER['HTTP_HOST']=='btcbot.loc')
{
	$yii=dirname(__FILE__).'/../yii/framework/yii.php';
	$config = dirname(__FILE__).'/protected/config/main_local.php';
	defined('YII_DEBUG') or define('YII_DEBUG',true);
	defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);
}
else
{
	$yii=dirname(__FILE__).'/../../yii/framework/yii.php';
	$config=dirname(__FILE__).'/protected/config/main.php';
	
}

// remove the following lines when in production mode
// defined('YII_DEBUG') or define('YII_DEBUG',true);
// specify how many levels of call stack should be shown in each log message
// defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

require_once($yii);
Yii::createWebApplication($config)->run();
