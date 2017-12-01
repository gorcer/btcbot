<?php

class m140211_113417_buyed extends CDbMigration
{
public function up()
	{
		$this->dropColumn('buy', 'sell_id');
		
		$this->addColumn('sell', 'buyed', 'DECIMAL( 30, 6 ) default 0');
		$this->addColumn('order', 'sell_id', 'int');
		
		$this->alterColumn('sell', 'dtm', 'timestamp CURRENT_TIMESTAMP');
		$this->alterColumn('order', 'create_dtm', 'timestamp CURRENT_TIMESTAMP');
	}

	public function down()
	{
		$this->addColumn('buy', 'sell_id', 'int');
		
		$this->dropColumn('sell', 'buyed');
		$this->dropColumn('order', 'sell_id');
		
		$this->alterColumn('sell', 'dtm', 'timestamp ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
		$this->alterColumn('order', 'create_dtm', 'timestamp ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
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