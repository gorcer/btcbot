<?php

class ExchangeTest extends CTestCase {

	public $fixtures=array(
			'exchange'=>'Exchange',
	);
	
	public function testGetDataFrom($dt)
	{
		$list = $this->exchange->getDataFrom('2013-01-01 02:55:00');
		$this->assertEquals('2013-01-01 03:00:00', $list[0]['dtm']); 
	}
	
}