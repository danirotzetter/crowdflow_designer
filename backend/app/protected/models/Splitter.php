<?php

/**
 * This is the model class for table "splitter".
 *
 * The followings are the available columns in table 'splitter':
 * @property integer $id
 * @property string $description
 * @property integer $pos_x
 * @property integer $pos_y
 * @property integer $workspace_id
 * @property string $name
 *
 * The followings are the available model relations:
 * @property Workspace $workspace
 * @property SplitterTask[] $splitterTasks
 * @property TaskSplitter[] $taskSplitters
 */
class Splitter extends CCsActiveRecord
{



	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Splitter the static model class
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
		return 'splitter';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('pos_x, pos_y, workspace_id', 'numerical', 'integerOnly'=>true),
			array('name', 'length', 'max'=>50),
			array('description, platform_data, input_queue, parameters, processed_flowitems', 'safe'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, description, pos_x, pos_y, workspace_id, name, platform_data', 'safe', 'on'=>'search'),
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
			'workspace' => array(self::BELONGS_TO, 'Workspace', 'workspace_id'),
			'splitterTasks' => array(self::HAS_MANY, 'SplitterTask', 'splitter_id'),
			'taskSplitters' => array(self::HAS_MANY, 'TaskSplitter', 'splitter_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'description' => 'Description',
			'pos_x' => 'Pos X',
			'pos_y' => 'Pos Y',
			'workspace_id' => 'Workspace',
			'name' => 'Name',
            'parameters' => 'Parameters',
            'platform_data' => 'Platform Data',
            'processed_flowitems' => 'Processed Assignments',
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
		$criteria->compare('description',$this->description,true);
		$criteria->compare('pos_x',$this->pos_x);
		$criteria->compare('pos_y',$this->pos_y);
		$criteria->compare('workspace_id',$this->workspace_id);
		$criteria->compare('name',$this->name,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

    //region Application-specific functions




    /**
     * See base class
     */
    public function processInputQueue(&$errors){
        Yii::log('Processing input queue for '.$this->getItemInfo(), 'debug', 'Splitter');

        /*
         * Each input queue item consists of a series of data elements. The splitter is responsible for separating them and adding them as single items to the subsequent item's input queue
         */
        $subsequentItem = Helper::getOutputItem($this);
        if($subsequentItem==null){
            Yii::log('Cannot process inputQueue for '.$this->getItemInfo().': no subsequent item available', 'info', 'Splitter');
            return;
        }
        /*
         * Must refresh this record, since in the meantime, more data could have been added to the input queue. Refreshing here is important since the input queue is emptied in this
         * method, which means that input queue items are deleted before even treating them (unless the input queue is updated here)
         */
        $this->refresh();
        $inputQueue = $this->input_queue;
        Yii::log('Processing the input queue of size '.sizeof($inputQueue).' for '.$this->getItemInfo().' by splitting each data item of each input queue item and adding them to the subsequent '.$subsequentItem->getItemInfo().', inputQueue: '.print_r($inputQueue, true), 'debug', 'Splitter');
        $splitItems = array();
        // Each form field input will serve as a new ('split') flowItem. E.g. a form with input0, input1 and input2 will produce three new flowItems.
        foreach($inputQueue as $inputQueueItem){
            $itemsArray = $inputQueueItem['data'];
            foreach($itemsArray as $formField){
                // Ignore metadata
                $id = $formField['id'];
                if($id=='flowItemId' || $id=='original' || $id==Helper::$ORDERKEY_FIELD){
                    /* The item of the input queue that is currently processed is just additional information/ metadata about the item.
                    * Sometimes, such metadata are sent along with the flowItem. For example, text transformations also contain the original values.
                     * Or the original flowItemId is also stored in the item. However, when splitting, these data can be ignored since they have no
                     * further meaning (for example the flowItemId will be overwritten anyway, since a new flowItem is generated by splitting the 'original'
                     * flowItem).
                     * Hence, we skip items in the queue that have no 'real' data
                     */
                    continue;
                }

                if(!array_key_exists('value', $formField) || strlen(trim($formField['value']))==0){
                    // Empty value or value not available
                    continue;
                }

                // Construction of the input queue item, but with the single data item instead of the array that was stored
                $splitItem = $inputQueueItem;

                // Assign a new flowItem identifier, since there are now multiple different flowItems originating the same 'base' flow item
                $splitItem['flowItemId']=uniqid();

                $splitItem['data']= array();
                $splitItem['data'][]=$formField;// Adds the array of the field id-value pair
                // Keep the index of the submitted id: if the id is 'input34', the orderKey is '34'
                // TODO handle multiple inputs
                $orderKey=substr($id, strlen('input'));
                $splitItem['data'][]=array('id'=>Helper::$ORDERKEY_FIELD, 'value'=>$orderKey);

                Yii::log('Adding split item '.print_r($splitItem, true), 'debug', 'Splitter');

                $splitItems[]=$splitItem;
            }// End for each individual data item in the input queue item
        }// End for each item in the input queue

        // Finally, we can add the individual (i.e. the split ) items to the input queue of the subsequent item
        $inputQueueItemsProcessed = $this->input_queue;
        FlowManagement::addToInputQueue($subsequentItem, $splitItems);

        // If the operation was successful (i.e. this code line is reached), we can remove all items from the input queue since they now are processed)
        $this->input_queue='[]';
        $this->saveAttributes(array('input_queue'));
        $this->input_queue=json_decode($this->input_queue, true);

        Yii::log('Successfully processed '.sizeof($inputQueueItemsProcessed).' items from the input queue - totally created and processed '.sizeof($splitItems).' split items out of the entire input queue. The input queue of '.$this->getItemInfo().' was emptied', 'debug', 'Splitter');

        return $inputQueueItemsProcessed;
    }

    //endregion Application-specific functions
}