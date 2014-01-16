<?php


class BotTest extends CTestCase {
	
	public $fixtures=array(
			'exchange'=>'Exchange',			
	);
	
	/**
	 * @test virtualBuy
	 */
	public function testVirtualBuy()
	{
		$this->assertTrue(true);
	}
	
	/**
	 * @test getGraphImage
	 */
	public function testGetGraphImage()
	{
		
		$curtime = strtotime('2013-01-01 04:00:00');
		$period = 10800; // 3 часа
		$name = 'buy';
		$imp_dif=0.01;		
		
		$from = date('Y-m-d H:i:s',$curtime-$period);
		
		$bot = Bot::get_Instance();
		$img = $bot->getGraphImage($curtime, $period, $name, $imp_dif);
		$this->assertEquals('-0+', $img['track']);
		$this->assertEquals($from, $img['from']);
		$this->assertEquals('2013-01-01 01:00:00', $img['items'][0]['dtm']);
		
		return $img;
		
		
	}
	
	
}