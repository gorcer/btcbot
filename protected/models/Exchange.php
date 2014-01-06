<?php

/**
 * This is the model class for table "exchange".
 *
 * The followings are the available columns in table 'exchange':
 * @property string $dtm
 * @property string $buy
 * @property string $sell
 * @property string $pair
 */
class Exchange extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Exchange the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'exchange';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('pair, buy, sell', 'required'),
			array('buy, sell', 'length', 'max'=>30),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('pair, dtm, buy, sell', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'dtm' => 'Dt',
			'buy' => 'Buy',
			'sell' => 'Sell',
			'pair' => 'Pair',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('dtm',$this->dtm,true);
		$criteria->compare('buy',$this->buy,true);
		$criteria->compare('sell',$this->sell,true);
		$criteria->compare('pair',$this->pair,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	
	public static function getDataFrom($dt)
	{
		$connection = Yii::app()->db;
		$sql = "
				select dtm, buy, sell
				from exchange
				where
				dtm>'".$dt."'
				order by dtm 
				";
		
		$command = $connection->createCommand($sql);
		return($command->queryAll());
	}
	
	public static function getTestData()
	{
		
		Yii::app()->db->createCommand()->truncateTable(Buy::model()->tableName());
		Yii::app()->db->createCommand()->truncateTable(Sell::model()->tableName());
		Status::setParam('balance', 1000);
		
		
		$result[]=array('dt'	=>	'2013-01-01',		'buy'	=>	5000,		'sell'	=>	5000);
		$result[]=array('dt'	=>	'2013-01-02',		'buy'	=>	5000,		'sell'	=>	5000);
		$result[]=array('dt'	=>	'2013-01-03',		'buy'	=>	5000,		'sell'	=>	5000);
		$result[]=array('dt'	=>	'2013-01-04',		'buy'	=>	5000,		'sell'	=>	5000);
		$result[]=array('dt'	=>	'2013-01-05',		'buy'	=>	5000,		'sell'	=>	5000);
		$result[]=array('dt'	=>	'2013-01-06',		'buy'	=>	5110,		'sell'	=>	5100);
		$result[]=array('dt'	=>	'2013-01-07',		'buy'	=>	5210,		'sell'	=>	5200);
		$result[]=array('dt'	=>	'2013-01-08',		'buy'	=>	5310,		'sell'	=>	5300);
		$result[]=array('dt'	=>	'2013-01-09',		'buy'	=>	5410,		'sell'	=>	5400);
		$result[]=array('dt'	=>	'2013-01-10',		'buy'	=>	5510,		'sell'	=>	5500); //+1
		$result[]=array('dt'	=>	'2013-01-11',		'buy'	=>	6010,		'sell'	=>	6000); //0
		$result[]=array('dt'	=>	'2013-01-11',		'buy'	=>	6010,		'sell'	=>	6000); //0
		$result[]=array('dt'	=>	'2013-01-12',		'buy'	=>	5910,		'sell'	=>	5900); //-1
		$result[]=array('dt'	=>	'2013-01-13',		'buy'	=>	5810,		'sell'	=>	5800); 
		$result[]=array('dt'	=>	'2013-01-14',		'buy'	=>	5710,		'sell'	=>	5700);
		$result[]=array('dt'	=>	'2013-01-15',		'buy'	=>	5610,		'sell'	=>	5600);
		return($result);
	}
	
	public static function getLast($pair='btc_rur')
	{
		$buy = Exchange::model()->find(array(
				'condition' => 'pair="'.$pair.'"',				
				'order' => 'dtm desc'
		));
		return $buy;
	}
	
	public static function getAll($pair='btc_rur', $period = '%Y-%m-%d %H:%i:%s')
	{
		$connection = Yii::app()->db;
		$sql = "
					SELECT
						DATE_FORMAT(dtm, '".$period."') as dt, avg(buy) as buy, avg(sell) as sell		
					FROM `exchange`
					where
						/*dtm >= '2013-12-09 09:00:00'*/
						/*dtm >= '2014-01-05 00:00:00'*/
						dtm >= '2013-12-21 00:00:00'
						and
						pair = '".$pair."'
					group by dt
					order by dtm
					limit 50000000
					";
		//if ($curtime == '2013-12-11 16:42:00')
		
		$command = $connection->createCommand($sql);
		$list=$command->queryAll();
		return($list);
		
		//return Exchange::model()->cache(60*60)->findAll(array('condition'=>'dt>"2013-12-09 10:00:02"', 'limit'=>10000000));
	}
	
	public static function NOSQL_getAvg($name, $from, $to)
	{
		$key = 'nosql.exchange.getavg.'.$name.'-'.$from.'-'.$to;		
		$val = Yii::app()->cache->get($key);
		
		if ($val) return $val;
		
		$list = Exchange::getAll();
		$sum=0;
		$cnt=0;
		foreach($list as $item)
		{
			
			if ($item['dt']>=$from && $item['dt']<=$to)
			{
				$sum+=$item[$name];
				$cnt++;
			}
			elseif ($item['dt'] > $to)
				break;
		}
		if ($cnt>0)
			$val = $sum/$cnt;
		else 
			$val=false;
		
		Yii::app()->cache->set($key, $val, 60*60);
		
		return ($val);
	}
	
	public static function getAvg($name, $from, $to, $pair='btc_rur')
	{
		$connection = Yii::app()->db;
		$sql = "
					SELECT
						avg(".$name.") as val
		
					FROM `exchange`
					where
						dtm >= '".$from."' and dtm <= '".$to."'
						and
								pair = '".$pair."'
					order by dtm
					limit 1
					";
		//if ($curtime == '2013-12-11 16:42:00')
		//Dump::d($sql);
		$command = $connection->createCommand($sql);
		$val=$command->queryScalar();
		
		
		return($val);
	}
	
	public static function updatePrices($pair='btc_rur')
	{
		$BTCeAPI = BTCeAPI::get_Instance();
		
		$ticker = $BTCeAPI->getPairTicker($pair);
		$ticker = $ticker['ticker'];
		$exchange = new Exchange();
		$exchange->buy = $ticker['buy'];
		$exchange->sell = $ticker['sell'];
		$exchange->dtm = date('Y-m-d H:i:s', $ticker['updated']/*+9*60*60*/);
		$exchange->pair = $pair;
		$exchange->save();
		
		return ($exchange);
	}
	
}