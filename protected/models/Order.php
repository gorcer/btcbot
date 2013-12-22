<?php

/**
 * This is the model class for table "order".
 *
 * The followings are the available columns in table 'order':
 * @property integer $id
 * @property string $create_dtm
 * @property string $price
 * @property string $count
 * @property string $fee
 * @property string $summ
 * @property string $status
 * @property string $close_dtm
 * @property string $type
 * @property string $btc_id 
 */
class Order extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Order the static model class
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
		return 'order';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('price, count, summ, type', 'required'),
			array('price, count, fee, summ', 'length', 'max'=>30),
			array('status', 'length', 'max'=>50),
			array('close_dtm', 'safe'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('btc_id, type, id, create_dtm, price, count, fee, summ, status, close_dtm', 'safe', 'on'=>'search'),
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
			'id' => 'ID',
			'create_dtm' => 'Create Dtm',
			'price' => 'Price',
			'count' => 'Count',
			'fee' => 'Fee',
			'summ' => 'Summ',
			'status' => 'Status',
			'type' => 'Type',
			'close_dtm' => 'Close Stamp',
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

		$criteria->compare('id',$this->id);
		$criteria->compare('create_dtm',$this->create_dtm,true);
		$criteria->compare('price',$this->price,true);
		$criteria->compare('count',$this->count,true);
		$criteria->compare('fee',$this->fee,true);
		$criteria->compare('summ',$this->summ,true);
		$criteria->compare('status',$this->status,true);
		$criteria->compare('close_dtm',$this->close_dtm,true);
		$criteria->compare('type',$this->type,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	public static function makeOrder($exchange, $cnt, $type, $btc_id=false)
	{
		
		// -
		/* @todo - сделать актуализацию баланса и расчет комиссии исходя из разницы балансов
		 * array
				(
				    'success' => 1
				    'return' => array
				    (
				        'received' => 0
				        'remains' => 0.01
				        'order_id' => 87715140
				        'funds' => array
				        (
				            'usd' => 0
				            'btc' => 0.077844
				            'ltc' => 0
				            'nmc' => 0
				            'rur' => 4343.61904536
				            'eur' => 0
				            'nvc' => 0
				            'trc' => 0
				            'ppc' => 0
				            'ftc' => 0
				            'xpm' => 0
				        )
				    )
				) 
				
				
				array
				(
				    'success' => 1
				    'return' => array
				    (
				        'received' => 0.01
				        'remains' => 0
				        'order_id' => 0
				        'funds' => array
				        (
				            'usd' => 0
				            'btc' => 0.087824
				            'ltc' => 0
				            'nmc' => 0
				            'rur' => 4101.10904536
				            'eur' => 0
				            'nvc' => 0
				            'trc' => 0
				            'ppc' => 0
				            'ftc' => 0
				            'xpm' => 0
				        )
				    )
				) 

		 */
		$bot = Bot2::get_Instance();
		
		$BTCeAPI = new BTCeAPI();
		try {
			
			if (Bot2::real_trade)
			  $btce = $BTCeAPI->makeOrder($cnt, 'btc_rur', $type, $exchange->$type);
			else
			{
			$price = $cnt*$exchange->$type;
			$btce = array
				(
				    'success' => 1,
				    'return' => array
				    (
				        'received' => $cnt,
				        'remains' => 0,
				        'order_id' => 0,
				        'funds' => array
				        (				            
				            'btc' => $bot->balance_btc,
				            'rur' => $bot->balance-$price,				            
				        )
				    )
				) ;
			}
			
		
		} catch(BTCeAPIInvalidParameterException $e) {			
			Log::AddText(strtotime($exchange->dtm), 'Не удалось создать ордер '.$e->getMessage());
			return false;			
		} catch(BTCeAPIException $e) {
			Log::AddText(strtotime($exchange->dtm), 'Не удалось создать ордер '.$e->getMessage());
			return false;
		}
		
		// Ошибка создания ордера
		if($btce['success'] == 0)
		{
			Log::AddText(strtotime($exchange->dtm), 'Не удалось создать ордер '.$btce['error']);
			return false;
		}
		
		$order = new Order();
		$order->id = $btce['return']['order_id'];
		$order->price = $exchange->$type;
		$order->count = $cnt;
		$order->fee = Bot2::fee;
		$order->summ = $cnt*$exchange->$type;
		$order->type = $type;
		$order->status = 'open';
		$order->create_dtm = $exchange->dtm;
		
		
		if ($btc_id) $order->btc_id = $btc_id;
		
		// Если сразу купили
		//if($btce['return']['received'])
		//	$order->close($exchange->dtm);
		
		if (!$order->save()) return false;
		
		$bot->setBalance($btce['return']['funds']['rur']);
		$bot->setBalanceBtc($btce['return']['funds']['btc']);		
		
		return $order;		
	}
	
	public function Close($dtm)
	{
		$this->close_dtm = $dtm;
		$this->status='close';
	}
	
	public static function getActiveOrders()
	{
		$BTCeAPI = new BTCeAPI();
		
		/*
		 array
				(
				    'success' => 1
				    'return' => array
				    (
				        88287800 => array
				        (
				            'pair' => 'btc_rur'
				            'type' => 'buy'
				            'amount' => 0.01
				            'rate' => 19157.54
				            'timestamp_created' => 1387344412
				            'status' => 0
				        )
				    )
				) 
		 */
		
		
		try {
		$orders = $BTCeAPI->apiQuery('ActiveOrders', array('pair'=>'btc_rur'));
		} catch(BTCeAPIException $e) {
			Log::AddText(0, 'Не удалось получить список заказов '.$e->getMessage());
			return false;
		}
		
		
		return($orders['return']);
	}
	

	public function cancel()
	{
		$bot = Bot2::get_Instance();
		
		$BTCeAPI = new BTCeAPI();
		try {
		$res = $BTCeAPI->apiQuery('CancelOrder', array('order_id'=>$this->id));
		} catch(BTCeAPIException $e) {
			Log::AddText( $bot->curtime, 'Не удалось удалить ордер '.$e->getMessage());
			return false;
		}
		Dump::d($res);
		if ($res['success'] == 1)
		{
			
			$bot->setBalance($res['return']['funds']['rur']);
			$bot->setBalanceBtc($res['return']['funds']['btc']);
			
			$this->status = 'cancel';
			$this->close_dtm = $bot->current_exchange->dtm;
			$this->save();
			Dump::d($this->errors);
		}
	}
	
}