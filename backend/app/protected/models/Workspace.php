<?php

/**
 * This is the model class for table "workspace".
 *
 * The followings are the available columns in table 'workspace':
 * @property integer $id
 * @property string $name
 * @property integer $user_id
 * @property integer $macrotask_id
 * @property string $description
 *
 * The followings are the available model relations:
 * @property Datasource[] $datasources
 * @property DatasourceTask[] $datasourceTasks
 * @property Merger[] $mergers
 * @property MergerTask[] $mergerTasks
 * @property Splitter[] $splitters
 * @property SplitterTask[] $splitterTasks
 * @property Task[] $tasks
 * @property TaskMerger[] $taskMergers
 * @property TaskSplitter[] $taskSplitters
 * @property TaskTask[] $taskTasks
 * @property Macrotask $macrotask
 * @property User $user
 */
class Workspace extends CActiveRecord
{
    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Workspace the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'workspace';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('user_id, pos_x, pos_y, macrotask_id', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 50),
            array('description, input_queue, processed_flowitems, publish, pos_x, pos_y', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, name, user_id, macrotask_id, description, input_queue, pos_x, pos_y, processed_flowitems, publish', 'safe', 'on' => 'search'),
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
            'datasources' => array(self::HAS_MANY, 'Datasource', 'workspace_id'),
            'datasourceTasks' => array(self::HAS_MANY, 'DatasourceTask', 'workspace_id'),
            'mergers' => array(self::HAS_MANY, 'Merger', 'workspace_id'),
            'mergerTasks' => array(self::HAS_MANY, 'MergerTask', 'workspace_id'),
            'splitters' => array(self::HAS_MANY, 'Splitter', 'workspace_id'),
            'splitterTasks' => array(self::HAS_MANY, 'SplitterTask', 'workspace_id'),
            'tasks' => array(self::HAS_MANY, 'Task', 'workspace_id'),
            'taskMergers' => array(self::HAS_MANY, 'TaskMerger', 'workspace_id'),
            'taskSplitters' => array(self::HAS_MANY, 'TaskSplitter', 'workspace_id'),
            'taskTasks' => array(self::HAS_MANY, 'TaskTask', 'workspace_id'),
            'macrotask' => array(self::BELONGS_TO, 'Macrotask', 'macrotask_id'),
            'user' => array(self::BELONGS_TO, 'User', 'user_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'name' => 'Name',
            'user_id' => 'User',
            'macrotask_id' => 'Macrotask',
            'description' => 'Description',
            'input_queue' => 'Result',
            'processed_flowitems' => 'Processed flowItems',
            'publish' => 'Publish the tasks',
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

        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('macrotask_id', $this->macrotask_id);
        $criteria->compare('description', $this->description, true);
        $criteria->compare('input_queue', $this->result, true);
        $criteria->compare('processed_flowitems', $this->result, true);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }



    /**
     * Hook
     */
    public function beforeSave()
    {
        // Parse associative arrays to JSON strings
        $this->input_queue = json_encode($this->input_queue);
        $this->processed_flowitems = json_encode($this->processed_flowitems);

        return parent::beforeSave();
    }

    /**
     * Hook
     */
    public function afterFind()
    {
        parent::afterFind();


        // Decode the attributes stored as JSON strings
        $this->input_queue = json_decode($this->input_queue, true);
        if ($this->input_queue == NULL || $this->input_queue == 'null')
            $this->input_queue = array();
        $this->processed_flowitems = json_decode($this->processed_flowitems, true);
        if ($this->processed_flowitems== NULL || $this->processed_flowitems == 'null')
            $this->processed_flowitems = array();

    }



    /**
     * Get a description of the item
     * @return string
     */
    public function getItemInfo(){
        return 'workspace '.$this->id;
    }

}