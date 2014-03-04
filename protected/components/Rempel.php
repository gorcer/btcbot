<?php


/**
 * Class to analize and prediction stock direction
 * @author Zaretskiy.E
 *
 */
class Rempel {
	
	private static $self=false;
	

	private $sell_periods; // Определение периодов покупки
	private $buy_periods; // Определение периодов продажи
		
	
	public $buy_imp_dif; // Видимость различий, при превышении порога фиксируются изменения
	public $sell_imp_dif; // Видимость различий, при превышении порога фиксируются изменения	
	
	public $long_time =  86400; // Понятие долгосрочный период - больше 2 дней
	
	public static function get_Instance()
	{
		if (!self::$self)
			self::$self = new Rempel();
		return self::$self;
	}
	
	public function __construct()
	{	
		// Периоды анализа графика для покупки и продажи (в сек.)
		$this->buy_periods = array(/*15*60,*/ 30*60, 60*60, 2*60*60, 6*60*60, 24*60*60, 36*60*60);
		//$this->sell_periods = array(			 60*60, 2*60*60, 6*60*60, 24*60*60, 36*60*60); 2127/51028
		$this->sell_periods = array(	  30*60, 60*60, 2*60*60,);
		
		$this->buy_imp_dif = 0.007;// Шаг при анализе покупки 7%;
		$this->sell_imp_dif = 0.007; // Шаг при анализе продажи 7%
		
	}	
	/**
	 * Get stock image, like -0+
	 * @param  $period - period of analize in sec
	 * @param $name - buy || sell
	 */
	public function getGraphImage($curtime, $period, $name, $imp_dif)
	{
		$step = round($period/3);
		$from_tm = $curtime-$period;
		$from = date('Y-m-d H:i:s', $from_tm);
	
	
		if ($step/2 <= 60*60)
			$smash = $step/2;
		else
			$smash = 60*60; // 10 min around each point, to average vibrations
	
		$track="";
		$prev=false;
		for($i=0;$i<=3;$i++)
		{
		$step_ut = $from_tm+$step*$i;
		$step_dt = date('Y-m-d H:i:s', $step_ut);	// Divide period to 4 parts
			
		$step_ut_f = date('Y-m-d H:i:s',$step_ut-$smash/*$step/2*/); // measure half step to forward and backward around each point
		$step_ut_t = date('Y-m-d H:i:s',$step_ut+$smash/*$step/2*/);
			
		$val=Exchange::getAvg($name, $step_ut_f, $step_ut_t);
			
			
		if (!$val)
		{
	
		$val = Exchange::getAvgByNear($name, $step_dt);
		if (!$val) continue;
		}
			
		$list[]=array(
		'dtm'=>$step_dt,
		'val'=>$val,
		);
			
		if (!$prev)
		{
		$prev = $val;
		continue;
		}
			
		// Determine direction
			$dif = (1-$prev/$val);
			if ($dif<(-1*$imp_dif)) $track.="-"; //down
			elseif ($dif>$imp_dif) $track.="+"; //up
			else $track.="0";

			$prev = $val;
		}
	
		$result = array(
				'track'=>$track,
				'from' => $from,
			'step' => $step,
			'period'=>$period,
			'items' =>$list,
			);
	
		return($result);
	}
	
	
	/**
	 * Filter tracks for buy
	 * @param array $tracks
	 * @return array
	 */
	public function getBuyTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
				
			switch($track['track']){
				case '-0+':								 // \_/
				case '--+':								 // \\/
					// If a track does not fall back to the starting point
					if ((1 - $track['items'][3]['val'] / $track['items'][0]['val']) > $this->buy_imp_dif)
					{
						$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
						$result[] = $track;
					}
					else
						Log::notbuy('Найден удачный трек '.$track['track'].', но покупать уже поздно т.к. цена на падении была '.$track['items'][0]['val'].', а сейчас уже '.$track['items'][3]['val']);
	
					break;
				case '00+':	// __/
					// Применяем только к длинным трекам
					if ($track['period']>$this->long_time)
					{
						$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
						$result[] = $track;
					}
					break;
				case '0-+':							   // _\/
					// If a track does not fall back to the starting point
					if((1 - $track['items'][3]['val'] / $track['items'][1]['val']) > $this->buy_imp_dif)
					{
						$track['pit']=Exchange::getPit($track['items'][0]['dtm'], $track['items'][3]['dtm']);
						$result[] = $track;
					}
					else
						Log::notbuy('Найден удачный трек '.$track['track'].', но покупать уже поздно т.к. цена на падении была '.$track['items'][1]['val'].', а сейчас уже '.$track['items'][3]['val']);
						
					break;
						
					// Если есть долгосрочное падение, не покупать
				case '---':								// \\\
				case '+--':								// /\\
				case '0--':								// /\\
					if ($track['period']>$this->long_time)
					{
						Log::notbuy('Замечено долгосрочное падение '.$track['track'].' в течении '.($track['period']/60/60).' ч., не покупаем');
						return false;
					}
					break;
			}
		}
		return $result;
	}
	
	/**
	 * Filter tracks for sell
	 * @param array $tracks
	 * @return array
	 */
	public function getSellTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
			switch($track['track']){
				case '+0-':	 // /-\
				case '++-':	 // //\
					$track['hill'] = Exchange::getHill($track['items'][0]['dtm'], $track['items'][3]['dtm']);
					$result[] = $track;
					break;
					//	case '00-':	$result[] = $track; break; // --\
					//	case '0+-':	$result[] = $track; break; // -/\
			}
				
				
		}
		return $result;
	}
	
	/**
	 * Filter tracks which neccessary sell can be happen
	 * @param Array $tracks
	 * @return multitype:unknown
	 */
	public function getNecessarySellTracks($tracks)
	{
		$result = array();
		foreach($tracks as $track)
		{
			switch($track['track']){
				case '---':	$result[] = $track; break;
				case '0--':	$result[] = $track; break;
				case '-0-':	$result[] = $track; break;
				/*
					case '+0-':	$result[] = $track; break;
				case '0+-':	$result[] = $track; break;
				*/
			}
		}
		return $result;
	}
	
	public function getAllTracks($curtime, $type)
	{
		if ($type == 'buy')
		{
			$imp_diff = $this->buy_imp_dif;
			$periods = $this->buy_periods;
		}
		else
		{
			$imp_diff = $this->sell_imp_dif;
			$periods = $this->sell_periods;
		}
		
		$all_tracks=array();
		foreach($periods as $period)
			$all_tracks[] = $this->getGraphImage($curtime, $period, $type, $imp_diff);
	
		return $all_tracks;
	}
	
}