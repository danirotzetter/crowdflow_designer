<?php

/**
 * This is the model class for table "task".
 *
 * The followings are the available columns in table 'task':
 * @property integer $id
 * @property string $name
 * @property integer $user_id
 * @property string $parameters
 * @property string $date_created
 * @property integer $pos_x
 * @property integer $pos_y
 * @property integer $workspace_id
 * @property integer $task_type_id
 * @property integer $output_media_type_id
 * @property integer $output_determined
 * @property integer $output_mapping_type_id
 * @property integer $output_ordered
 * @property string $description
 * @property string $platform_data
 *
 * The followings are the available model relations:
 * @property DatasourceTask[] $datasourceTasks
 * @property MergerTask[] $mergerTasks
 * @property PostprocessorTask[] $postprocessorTasks
 * @property SplitterTask[] $splitterTasks
 * @property User $user
 * @property Workspace $workspace
 * @property TaskMerger[] $taskMergers
 * @property TaskPostprocessor[] $taskPostprocessors
 * @property TaskSplitter[] $taskSplitters
 * @property TaskTask[] $taskTasks
 * @property TaskTask[] $taskTasks1
 */
class Task extends CCsActiveRecord
{

    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Task the static model class
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
        return 'task';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('user_id, pos_x, pos_y, workspace_id, task_type_id, output_media_type_id, output_determined, output_mapping_type_id, output_ordered', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 50),
            array('parameters, date_created, description, platform_data, input_queue, processed_flowitems, data', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, name, user_id, parameters, date_created, pos_x, pos_y, workspace_id, task_type_id, output_media_type_id, output_determined, output_mapping_type_id, output_ordered, description, platform_data, data', 'safe', 'on' => 'search'),
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
            'datasourceTasks' => array(self::HAS_MANY, 'DatasourceTask', 'task_id'),
            'mergerTasks' => array(self::HAS_MANY, 'MergerTask', 'task_id'),
            'postprocessorTasks' => array(self::HAS_MANY, 'PostprocessorTask', 'task_id'),
            'splitterTasks' => array(self::HAS_MANY, 'SplitterTask', 'task_id'),
            'user' => array(self::BELONGS_TO, 'User', 'user_id'),
            'workspace' => array(self::BELONGS_TO, 'Workspace', 'workspace_id'),
            'taskMergers' => array(self::HAS_MANY, 'TaskMerger', 'task_id'),
            'taskPostprocessors' => array(self::HAS_MANY, 'TaskPostprocessor', 'task_id'),
            'taskSplitters' => array(self::HAS_MANY, 'TaskSplitter', 'task_id'),
            'taskTasks' => array(self::HAS_MANY, 'TaskTask', 'source_task_id'),
            'taskTasks1' => array(self::HAS_MANY, 'TaskTask', 'target_task_id'),
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
            'data' => 'Data',
            'user_id' => 'User',
            'parameters' => 'Parameters',
            'date_created' => 'Date Created',
            'pos_x' => 'Pos X',
            'pos_y' => 'Pos Y',
            'workspace_id' => 'Workspace',
            'task_type_id' => 'Task Type',
            'output_media_type_id' => 'Output Media Type',
            'output_determined' => 'Output Determined',
            'output_mapping_type_id' => 'Output Mapping Type',
            'output_ordered' => 'Output Ordered',
            'description' => 'Description',
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

        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('data', $this->data, true);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('parameters', $this->parameters, true);
        $criteria->compare('date_created', $this->date_created, true);
        $criteria->compare('pos_x', $this->pos_x);
        $criteria->compare('pos_y', $this->pos_y);
        $criteria->compare('workspace_id', $this->workspace_id);
        $criteria->compare('task_type_id', $this->task_type_id);
        $criteria->compare('output_media_type_id', $this->output_media_type_id);
        $criteria->compare('output_determined', $this->output_determined);
        $criteria->compare('output_mapping_type_id', $this->output_mapping_type_id);
        $criteria->compare('output_ordered', $this->output_ordered);
        $criteria->compare('description', $this->description, true);
        $criteria->compare('platform_data', $this->platform_data, true);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }
    //region Application-specific functions


    //endregion Application-specific functions

}