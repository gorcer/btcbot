<?php

// change the following paths if necessary

$yii_path = '/var/lib/yii';
//$yii_path=dirname(__FILE__).'/../../../yii';

$yiit=$yii_path.'/framework/yiit.php';

$config=dirname(__FILE__).'/../config/test.php';

require_once($yiit);
require_once(dirname(__FILE__).'/WebTestCase.php');

Yii::createWebApplication($config);
