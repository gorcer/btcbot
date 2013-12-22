<?php

/**
 * This is the model class for table "buy".
 *
 * The followings are the available columns in table 'buy':
 * @property integer $id
 * @property string $count
 * @property string $price
 * @property string $summ
 * @property string $dtm
 * @property integer $sold
 */
class Buy extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Buy the static model class
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
		return 'buy';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('count, price, summ, dtm', 'required'),
			array('id', 'numerical', 'integerOnly'=>true),
			array('count, price, summ', 'length', 'max'=>30),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, count, price, summ, dtm, sold', 'safe', 'on'=>'search'),
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
			'sell'=>array(self::HAS_MANY, 'Sell', 'btc_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'count' => 'Count',
			'price' => 'Price',
			'summ' => 'Summ',
			'dtm' => 'Dtm',
			'sold' => 'Sold',
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
		$criteria->compare('count',$this->count,true);
		$criteria->compare('price',$this->price,true);
		$criteria->compare('summ',$this->summ,true);
		$criteria->compare('dtm',$this->dtm,true);
		$criteria->compare('sold',$this->sold,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	
	public static function getLast()
	{
		$buy = Buy::model()->find(array(
				'order' => 'dtm desc'			
		));
		return $buy;		
	}
	
	public function SellIt($price, $cnt, $income)
	{
		$sell = new Sell();
		$sell->btc_id = $this->id;
		$sell->price=$price;
		$sell->count = $cnt;
		$sell->summ = $price*$cnt;
		$sell->income = $income;
		$sell->save();			
		
		$this->sold = 1;
		$this->update(array('sold'));
	}
	
	// Совершение покупки
	public static function make($order)
	{
		// Покупаем
		$buy = new Buy();
		$buy->dtm = $order->close_dtm;
		$buy->count = $order->count;
		$buy->price =$order->price;
		$buy->summ = $order->summ;
		$buy->save();
	}
	

}