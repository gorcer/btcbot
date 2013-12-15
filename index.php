<?php
ini_set('max_execution_time', 40*60);
// change the following paths if necessary

//$config=dirname(__FILE__).'/protected/config/main.php';

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
