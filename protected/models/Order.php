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
 * @property string $buy_id 
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
			array('buy_id,sell_id, type, id, create_dtm, price, count, fee, summ, status, close_dtm', 'safe', 'on'=>'search'),
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
			/*'buy' => array(self::HAS_ONE, 'Buy', 'order_id'),*/
			'buy'=>array(self::BELONGS_TO, 'Buy', 'buy_id'),
			'sell'=>array(self::BELONGS_TO, 'Sell', 'sell_id'),
			 			
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
	
	
	public function Close($dtm)
	{
		$this->close_dtm = $dtm;
		$this->status='close';
	}
	
	public function cancel()
	{
		$bot = Bot::get_Instance();
		
		$res = $bot->api->CancelOrder($this); 
		
		if ($res['success'] == 1)
		{
			
			$bot->setBalance($res['return']['funds']['usd']);
			$bot->setBalanceBtc($res['return']['funds']['btc']);
			
			$this->status = 'cancel';
			$this->close_dtm = $bot->current_exchange->dtm;
			$this->save();
			Dump::d($this->errors);
		}
	}
	
	public function assignObj($obj)
	{	
		
		
			if (get_class($obj) == 'Buy')
				$this->buy_id = $obj->id;
			elseif (get_class($obj) == 'Sell')
			{	
				$this->sell_id = $obj->id;				
			}
			
			
			
			
	}
	
}