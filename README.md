Trading bot for btc-e.com
=========================

This bot can analize stock chart and determine when buy and when sell.
The strategy is buy bitcoins in a grow and sell in a fall, bitcoins sells only with some profit, if no profit, then bot waiting and not selling.
In this version it's work only with btc_rur pair.

How to try it
===============
1. Clone repository to your system
2. Create database with utf8_general ci collation 
3. Go to config/main.php, set your database setting for production and btc-e key info (take it in your profile in btc-e.com)
4. Go to config/main_local.php, set your database setting for test and develop on local
5. Start console and go to /protected/
6. Start migration - yiic migrate --interactive=0
7. Start bot to demonstrate work on last period - yiic cron test
8. Configure virtual hosts to profect folder from btcbot.loc
9. Go to http://btcbot.loc/index.php?r=site/chart to see result. It's looks like this:
![](demo.png)

 How to start earn money
 =======================
 1. In protected/components/APIProvider.php set constant isVirtual = false;
 2. Public code on server
 3. Run migrations
 4. In cron add job which every 3 minutes executes - yiic cron run
 5. Enjoy
 
 ATTENTION: You can lose all your money, and author doesn't guarantee you anything.
 
 
 It's not last version
 =====================
 
 In private repo we have enchanced version of this bot and use it to earn more money $) 
 If you want to get last version, you can join to our team.
 But we have some regulations:
 1) Max count of developers in project limited by 5
 2) If you do not contribute a project for a long time, you are kicked out from the project and you have a version of the bot which was last downloaded.
 3) To join to our team you need to fork this project and make some enchance. 
  
  
 P.S.: Sorry for bad code and russian comments, in future i'll fix it.
