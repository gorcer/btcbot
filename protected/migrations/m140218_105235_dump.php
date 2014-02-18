<?php

class m140218_105235_dump extends CDbMigration
{
	public function up()
	{
		$this->createTable('balance', array(
				'id' => 'pk',
				'dtm' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'description' => 'VARCHAR( 250 ) NOT NULL',
				'summ' => 'decimal(30,6)',
				'balance' => 'decimal(30,6)',
				'currency' => 'varchar(3)',
		));
		
		$this->createTable('buy', array(
				'id' => 'pk',				
				'price' => 'decimal(30,6)',
				'fee' => 'decimal(30,6)',
				'count' => 'decimal(30,6)',
				'summ' => 'decimal(30,6)',
				'dtm' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'sold' => 'decimal(30,6) DEFAULT 0',
		));
		
		$this->createTable('sell', array(
				'id' => 'pk',
				'buy_id' => 'int(11) NOT NULL',
				'price' => 'decimal(30,6)',
				'fee' => 'decimal(30,6)',
				'count' => 'decimal(30,6)',
				'summ' => 'decimal(30,6)',
				'income' => 'decimal(30,6)',				
				'dtm' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',				
		));
		
		$this->createTable('exchange', array(
				'dtm' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'buy' => 'decimal(30,6) NOT NULL',
				'sell' => 'decimal(30,6) NOT NULL',
				'pair' => 'varchar(7)',
				'PRIMARY KEY (`dtm`,`pair`)'				
		));
		
		$this->createTable('status', array(
				'param' => 'varchar(50) NOT NULL',
				'title' => 'varchar(150) NOT NULL',
				'value' => 'varchar(150) NOT NULL',		
				'PRIMARY KEY (`param`)'
		));
		
		$this->createTable('order', array(
				'id' => 'pk',
				'create_dtm' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
				'price' => 'decimal(30,6)',
				'count' => 'decimal(30,6)',				
				'fee' => 'decimal(30,6)',
				'summ' => 'decimal(30,6)',
				'status' => 'varchar(50) NOT NULL DEFAULT "open"',
				'type' => 'varchar(5) NOT NULL',
				'close_dtm' => 'timestamp NOT NULL',
				'description' => 'text',
				'buy_id' => 'int',
		));
	}

	public function down()
	{
		$this->dropTable('balance');
		$this->dropTable('buy');
		$this->dropTable('sell');
		$this->dropTable('exchange');
		$this->dropTable('status');
		$this->dropTable('order');
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