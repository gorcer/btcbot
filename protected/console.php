<?php

$yii=dirname(__FILE__).'/../../yii/framework/yii.php';
$config=dirname(__FILE__).'/config/console.php'; // конфиг для консолек который выше описан

// remove the following line when in production mode
defined('YII_DEBUG') or define('YII_DEBUG',true); // включаем дебаг
require_once($yii);
Yii::createConsoleApplication($config)->run(); // и вот тут внимательно  <b>createConsoleApplication</b>
