<?php

/**
 * This is the model class for table "exchange".
 *
 * The followings are the available columns in table 'exchange':
 * @property string $dt
 * @property string $buy
 * @property string $sell
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
			array('buy, sell', 'required'),
			array('buy, sell', 'length', 'max'=>30),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('dt, buy, sell', 'safe', 'on'=>'search'),
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
			'dt' => 'Dt',
			'buy' => 'Buy',
			'sell' => 'Sell',
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

		$criteria->compare('dt',$this->dt,true);
		$criteria->compare('buy',$this->buy,true);
		$criteria->compare('sell',$this->sell,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	
	public static function getDataFrom($dt)
	{
		$connection = Yii::app()->db;
		$sql = "
				select dt, buy, sell
				from exchange
				where
				dt>'".$dt."'
				order by dt 
				";
		
		$command = $connection->createCommand($sql);
		return($command->queryAll());
	}
	
	public static function getTestData()
	{
		
		Yii::app()->db->createCommand()->truncateTable(Btc::model()->tableName());
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
	
	public static function getLast()
	{
		$buy = Exchange::model()->find(array(
				'order' => 'dt desc'
		));
		return $buy;
	}
	
	public static function getAll()
	{
		return Exchange::model()->cache(60*60)->findAll(array('condition'=>'dt>"2013-12-09 10:00:02"', 'limit'=>10000000));
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
			
			if ($item->dt>=$from && $item->dt<=$to)
			{
				$sum+=$item->$name;
				$cnt++;
			}
			elseif ($item->dt > $to)
				break;
		}
		if ($cnt>0)
			$val = $sum/$cnt;
		else 
			$val=false;
		Yii::app()->cache->set($key, $val, 60*60);
		
		
		return ($val);
	}
	
}