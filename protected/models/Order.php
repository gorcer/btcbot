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
		/*
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
		
		
		$BTCeAPI = new BTCeAPI();
		try {		
		
		//$btce = $BTCeAPI->makeOrder($cnt, 'btc_rur', BTCeAPI::DIRECTION_BUY, $exchange->$type);
		$btce = array
				(
				    'success' => 1,
				    'return' => array
				    (
				        'received' => 0.01,
				        'remains' => 0,
				        'order_id' => 0,
				        'funds' => array
				        (				            
				            'btc' => 0.087824,
				            'rur' => 4101.10904536,				            
				        )
				    )
				) ;
			
		Dump::d($btce);
		} catch(BTCeAPIInvalidParameterException $e) {			
			Log::AddText(strtotime($exchange->dt), 'Не удалось создать ордер '.$e->getMessage());
			return false;			
		} catch(BTCeAPIException $e) {
			Log::AddText(strtotime($exchange->dt), 'Не удалось создать ордер '.$e->getMessage());
			return false;
		}
		
		// Ошибка создания ордера
		if($btce['success'] == 0)
		{
			Log::AddText(strtotime($exchange->dt), 'Не удалось создать ордер '.$btce['error']);
			return false;
		}
		
		$order = new Order();
		$order->id = $btce['return']['order_id'];
		$order->price = $exchange->$type;
		$order->count = $cnt;
		$order->fee = Bot2::fee;
		$order->summ = $cnt*$exchange->$type;
		$order->type = $type;
		$order->create_dtm = $exchange->dt;
		
		if ($btc_id) $order->btc_id = $btc_id;
		
		// Если сразу купили
		if($btce['return']['received'])
			$order->close($exchange->dt);
		
		if (!$order->save()) return false;	
		
		$result['order']=$order;
		$result['balance_btc']=$btce['return']['funds']['btc'];
		$result['balance']=$btce['return']['funds']['rur'];
		return $result;		
	}
	
	public function Close($dtm)
	{
		$this->close_dtm = $dtm;
		$this->status='close';
	}
	
	
}