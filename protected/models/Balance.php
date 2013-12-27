<?php

/**
 * This is the model class for table "balance".
 *
 * The followings are the available columns in table 'balance':
 * @property string $dtm
 * @property string $description
 * @property string $summ
 * @property string $balance
 * @property string $currency
 */
class Balance extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Balance the static model class
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
		return 'balance';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('description, summ, balance, currency', 'required'),
			array('description', 'length', 'max'=>250),
			array('summ, balance', 'length', 'max'=>30),
			array('currency', 'length', 'max'=>3),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('dtm, description, summ, balance, currency', 'safe', 'on'=>'search'),
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
			'dtm' => 'Dtm',
			'description' => 'Description',
			'summ' => 'Summ',
			'balance' => 'Balance',
			'currency' => 'Currency',
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
		$criteria->compare('description',$this->description,true);
		$criteria->compare('summ',$this->summ,true);
		$criteria->compare('balance',$this->balance,true);
		$criteria->compare('currency',$this->currency,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	
	// Актуализация баланса
	public static function actualize($currency, $summ)
	{
		$last = Balance::model()->find(array(
				'condition'=>'currency = "'.$currency.'"',
				'order' => 'dtm desc'
		));
		
		if (!$last)
			self::add($currency, 'Инициализация баланса', $summ);
		elseif ($last['balance'] - $summ === 0)
		{
			echo '=корректировка '.$summ.', '.$last['balance'].'=';
			self::add($currency, 'Корректировка баланса', ($summ - $last['balance']));
		}
		
	}
	
	public static function add($currency, $desc, $summ)
	{
		$last = Balance::model()->find(array(
				'condition'=>'currency = "'.$currency.'"',
				'order' => 'id desc'
		));
		
		$last_balance=0;
		if ($last)
			$last_balance = $last->balance;
		
			$bot = Bot::get_Instance();
		
			$b = new Balance();
			$b->dtm = $bot->current_exchange->dtm;
			$b->description = $desc;
			$b->summ = $summ;
			$b->balance = $last_balance+$summ;
			$b->currency = $currency;			
			$b->save() || die(print_r($b->errors, true));			
		
		
		return $last;
		
	}
}