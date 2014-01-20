<?php

/**
 * This is the model class for table "postprocessor".
 *
 * The followings are the available columns in table 'postprocessor':
 * @property integer $id
 * @property string $name
 * @property integer $postprocessor_type_id
 * @property integer $workspace_id
 * @property integer $pos_x
 * @property integer $pos_y
 * @property string $description
 * @property string $date_created
 * @property integer $validation_type_id
 *
 * The followings are the available model relations:
 * @property Workspace $workspace
 * @property PostprocessorTask[] $postprocessorTasks
 * @property TaskPostprocessor[] $taskPostprocessors
 */
class Postprocessor extends CCsActiveRecord
{

    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Postprocessor the static model class
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
        return 'postprocessor';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('postprocessor_type_id, workspace_id, pos_x, pos_y, validation_type_id', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 50),
            array('description, date_created, platform_data, input_queue, parameters, processed_flowitems', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, name, postprocessor_type_id, workspace_id, pos_x, pos_y, description, date_created, validation_type_id, platform_data', 'safe', 'on' => 'search'),
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
            'postprocessorTasks' => array(self::HAS_MANY, 'PostprocessorTask', 'postprocessor_id'),
            'taskPostprocessors' => array(self::HAS_MANY, 'TaskPostprocessor', 'postprocessor_id'),
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
            'postprocessor_type_id' => 'Postprocessor Type',
            'workspace_id' => 'Workspace',
            'pos_x' => 'Pos X',
            'pos_y' => 'Pos Y',
            'description' => 'Description',
            'date_created' => 'Date Created',
            'validation_type_id' => 'Validation Type',
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

        $criteria = new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('postprocessor_type_id', $this->postprocessor_type_id);
        $criteria->compare('workspace_id', $this->workspace_id);
        $criteria->compare('pos_x', $this->pos_x);
        $criteria->compare('pos_y', $this->pos_y);
        $criteria->compare('description', $this->description, true);
        $criteria->compare('date_created', $this->date_created, true);
        $criteria->compare('validation_type_id', $this->validation_type_id);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }


    //region Item property specific methods
    /**
     * Check if the submitted item has a validation as a destination
     * @param $item
     * @return bool
     */
    public static function itemHasValidationDestination($item)
    {
        $type = get_class($item);


        $postprocessorIsPossibleTarget = in_array('Postprocessor', Helper::getAllPossibleTargetTypesOfSourceType($type));
        Yii::log('Check if ' . $item->getItemInfo() . ' is an item with validation postprocessor. Is the postprocessor a valid destination? ' . ($postprocessorIsPossibleTarget ? 'true' : 'false'), 'debug', 'FlowManagement');
        $result = false;
        if ($postprocessorIsPossibleTarget) {
            $connections = Helper::getConnection($type, $item->id, 'postprocessor', NULL, $item->workspace_id);
            $nb = sizeof($connections);
            Yii::log('Number of connections to postprocessors from ' . $item->getItemInfo() . ': ' . $nb, 'debug', 'FlowManagement');
            if ($nb > 0) {
                // At least one connection leads to a postprocessor
                foreach ($connections as $connection) {
                    $postprocessor = Postprocessor::model()->findByPk($connection['targetId']);
                    if (Postprocessor::isValidation($postprocessor)) {
                        // Meaning that this connection is leading to a postprocessor of type 'validation'
                        $result = true;
                    }
                }
            }
        }

        Yii::log($item->getItemInfo() . ' has ' . ($result ? 'a' : 'no') . ' validation post-processor as a destination', 'debug', 'FlowManagement');
        return $result;
    }


    /**
     * Checks if the submitted item is a validation postprocessor
     * @param $item The item to check
     * @return bool TRUE if the item is of type 'Postprocessor' and of postprocessor type 'validation'
     */
    public static function isValidation($item)
    {
        if (get_class($item) != 'Postprocessor')
            return false;
        else
            return $item->postprocessor_type_id == 11 || $item->postprocessor_type_id == 12;
    }
    //endregion Item property specific methods


    //region Application-specific functions


    /**
     * Render the data that is being validated, i.e. generate the form which must be accepted or rejected, filled with the data that was submitted by the crowd
     * @param $queueItem
     * @return mixed
     * @throws CHttpException
     */
    public static function renderDataToBeValidated($queueItem)
    {

        // Render the form that was crowd-sourced for the queue item
        $itemType=$queueItem['itemType'];
        $itemId=$queueItem['itemId'];
        $model = CrowdsourceFormsController::loadModel($itemType, $itemId);
        $itemInfo = $model->getItemInfo();
        // Find out, which url (i.e. which form) must be loaded/ rendered
        $urlToLoad = Helper::getUrlOfCsForm($model);

        // Get the original input data which is stored in the input queue item
        $inputData='';
        foreach($queueItem['data'] as $field){
            if($field['id']=='original'){
                $inputData=$field['value'];
                break;
            }
        }

        // For uniform processing (esp. form rendering), the inputData must be of type array
        if(!is_array($inputData)){
            $inputData=array('input0'=>$inputData);
        }

        // Prepare the answer object
        $result = array('platformResultIdToValidate'=>$queueItem['platformResultId']);
        $result['formToValidate'] = Yii::app()->controller->renderPartial(
            $urlToLoad,
            // Send the parameters from the request to the php file that is representing the form to be displayed
            array(
                'model'=>$model,
                'inputData'=>$inputData,
                'forValidation'=>true,
                'queueItem'=>$queueItem
            ),
            true // Return parsed form as a string
        );

        /*
         * At this point, the rendering should be completed.
         *  In the next step, the answer will be generated such that it can be displayed in the form
         * that will be crowd-sourced subsequently
         */

        if ($result['formToValidate'] == NULL) {
            // The result was not created in the code lines above - meaning, that the function is not implemented yet
            Yii::log('Unable to render input data for ' . $itemInfo . ': not implemented yet', 'debug', 'Postprocessor');

            throw new CHttpException(501, 'Cannot render input queue for ' . $itemInfo . ' - rendering method is not implemented yet');
        } else {
            // Could prepare the page for the worker's result that is being verified
            Yii::log('Could successfully render input queue ' . $itemInfo, 'debug', 'Postprocessor');

            return $result;
        }
    }


    //endregion Application-specific functions
}