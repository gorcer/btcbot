<?php

class ExchangeTest extends CDbTestCase {

	public $fixtures=array(
			'exchange'=>'Exchange',
	);
	
	/**
	 * @test getDataFrom
	 */
	public function testGetDataFrom()
	{
		$list = Exchange::getDataFrom('2013-01-01 02:55:00');
		$this->assertEquals('2013-01-01 03:00:00', $list[0]['dtm']); 
	}

	
	/**
	 * @test getLast
	 */	
	public function testGetLast()
	{
		$last = Exchange::getLast('btc_rur');
		$this->assertEquals('2014-01-06 04:00:00', $last['dtm']);
	}
	
	/**
	 * @test getAll
	 */
	public function testGetAll()
	{
		$all = Exchange::getAll();
		$this->assertEquals(1, sizeof($all));
	}
	
	
	/**
	 * @test getAvg
	 */
	public function testGetAvg()
	{
		$name ='buy';
		$from='2013-01-01 01:00:00';
		$to='2013-01-01 04:00:00';
		
		
		$avg = Exchange::getAvg($name, $from, $to);
		$this->assertEquals(29425, $avg);
	}
	
	
	/**
	 * @test getAvgByNear
	 */
	public function testGetAvgByNear()
	{
		$name ='buy';
		$dt='2013-01-01 01:30:00';
	
		$avg = Exchange::getAvgByNear($name, $dt);
		$this->assertEquals(29500, $avg);
	}
	
	
	/**
	 * @test updatePrices
	 */
	public function testUpdatePrices()
	{
		$exch = Exchange::updatePrices();
		$this->assertTrue($exch->buy > 0);
	}
	
	/**
	 * @test getPit
	 */
	public function testGetPit()
	{
		$from='2013-01-01 01:00:00';
		$to='2013-01-01 04:00:00';
		
		$pit = Exchange::getPit($from, $to, 'sell');
		$this->assertEquals(28900, $pit['sell']);
	}
	
	/**
	 * @test getHill
	 */
	public function testGetHill()
	{
		$from='2013-01-01 01:00:00';
		$to='2013-01-01 04:00:00';
	
		$hill = Exchange::getHill($from, $to, 'sell');
		$this->assertEquals(29901, $hill['sell']);
	}
	
}