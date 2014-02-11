<?php

class m140211_031634_TestMigrate extends CDbMigration
{
	public function up()
	{
		$this->addColumn('buy', 'sell_id', 'int');
	}

	public function down()
	{
		$this->dropColumn('buy', 'sell_id');
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