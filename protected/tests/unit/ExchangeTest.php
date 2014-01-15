<?php

class ExchangeTest extends CDbTestCase {

	public $fixtures=array(
			'exchange'=>'Exchange',
	);
	
	public function testGetDataFrom()
	{
		$list = Exchange::getDataFrom('2013-01-01 02:55:00');
		$this->assertEquals('2013-01-01 03:00:00', $list[0]['dtm']); 
	}
	
}