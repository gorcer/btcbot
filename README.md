Trading bot for btc-e.com
=========================

This bot can analize stock chart and determine buy and sell moment.
The strategy is buys bitcoins in a grow and sells in a fall. Bitcoins sells only with some profit, if no profit, then bot is waiting and is't selling.
In this version bot works only with btc_rur pair.
Powered by Yii.

Requirements
============
- PHP 5.3
- MySQL 5.5
- Yii 1.14

How to try it?
===============
* Clone repository to your system
* Create database with utf8_general_ci collation
* Go to config/main_local.php, set your database setting for test and develop on local
* Start console and go to /protected/
* Start migration - yiic migrate --interactive=0
* Start bot to demonstrate work on last period - yiic cron test
* Configure virtual hosts to project folder from btcbot.loc
* Go to http://btcbot.loc/index.php?r=site/chart to see result. It's looks like this:
![](demo.png)

How to start earn money?
=======================
* In protected/components/APIProvider.php set constant isVirtual = false;
* Go to config/main.php, set your database setting for production and btc-e key info (take it in your profile in btc-e.com)
* Publish code to server
* Run migrations
* In the crontab add job which executes every 3 minutes - yiic cron run
* Enjoy
 
WARNING: You can lose all your money, and author doesn't guarantee you anything.
