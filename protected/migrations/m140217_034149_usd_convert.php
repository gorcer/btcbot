<?php

class m140217_034149_usd_convert extends CDbMigration
{
	public function up()
	{
		$this->execute("
				insert into exchange(dtm, buy, sell, pair)
				select dtm, buy / 30, sell / 30, 'btc_usd'
				from exchange
				where
				dtm < '2014-02-17 14:00:00'
				and pair = 'btc_rur'
				");
	}

	public function down()
	{
		$this->execute("
				delete from exchange				
				where
				dtm < '2014-02-17 14:00:00'
				and
				pair = 'btc_usd'
				");
	}

	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
	}

	public function safeDown()
	{
	}
	*/
}