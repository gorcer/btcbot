<?php

// change the following paths if necessary
//$yiic=dirname(__FILE__).'/../../yii/framework/yiic.php';
//$yii_path = '/var/lib/yii';
//$yii_path=dirname(__FILE__).'/../../../yii';

//$yiic=$yii_path.'/framework/yiic.php';

//$config=dirname(__FILE__).'/config/test.php';


require(dirname(dirname(__FILE__)) . '/vendor/autoload.php');
require(dirname(dirname(__FILE__)) . '/vendor/yiisoft/yii/framework/yiilite.php');


$config=dirname(__FILE__).'/config/main.php';


$app = Yii::createConsoleApplication($config);
$app->commandRunner->addCommands(YII_PATH . '/cli/commands');

$app->run();
