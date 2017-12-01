<?php

ini_set('max_execution_time', 4*60*60);
ini_set('memory_limit','256M');
	
ini_set('display_errors', true);
ini_set('html_errors', true);
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
	
// change the following paths if necessary

//$config=dirname(__FILE__).'/protected/config/main.php';
//defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');
require(dirname(dirname(__FILE__)) . '/vendor/yiisoft/yii/framework/yiilite.php');

if ($_SERVER['HTTP_HOST']=='btcbot.gorcer.com' || $_SERVER['HTTP_HOST']=='btcbot.gorcer.my')
{

	$config=dirname(__FILE__).'/protected/config/main.php';
	define('YII_DEBUG',false);
}
else
{	

	$config = dirname(__FILE__).'/protected/config/main_local.php';
	define('YII_DEBUG',true);
	defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);
}

// remove the following lines when in production mode
// defined('YII_DEBUG') or define('YII_DEBUG',true);
// specify how many levels of call stack should be shown in each log message
// defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

Yii::createWebApplication($config)->run();
