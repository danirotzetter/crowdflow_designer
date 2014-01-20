<?php

/**
 * This is the model class for table "datasource".
 *
 * The followings are the available columns in table 'datasource':
 * @property integer $id
 * @property string $name
 * @property string $data
 * @property integer $datasource_type_id
 * @property integer $output_determined
 * @property integer $output_media_type_id
 * @property integer $output_ordered
 * @property integer $pos_x
 * @property integer $pos_y
 * @property integer $workspace_id
 *
 * The followings are the available model relations:
 * @property Workspace $workspace
 * @property DatasourceTask[] $datasourceTasks
 */
class Datasource extends CCsActiveRecord
{
    public function beforeSave()
    {
        Yii::log('Called beforeSave for datasource ' . $this->id, 'debug', 'Datasource');
        $this->data = $this->fix_encoding($this->data);
        return parent::beforeSave();
    }

    public function afterFind()
    {
        parent::afterFind();
        Yii::log('Called afterFind for datasource ' . $this->id, 'debug', 'Datasource');
        $this->data = $this->fix_encoding($this->data);
    }

    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Datasource the static model class
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
        return 'datasource';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('datasource_type_id, output_determined, output_media_type_id, output_ordered, pos_x, pos_y, workspace_id, items_count', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 50),
            array('data, platform_data, items_count, name, description', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, name, data, datasource_type_id, output_determined, output_media_type_id, output_ordered, pos_x, pos_y, workspace_id, platform_data', 'safe', 'on' => 'search'),
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
            'datasourceTasks' => array(self::HAS_MANY, 'DatasourceTask', 'datasource_id'),
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
            'datasource_type_id' => 'Datasource Type',
            'output_determined' => 'Output Determined',
            'output_media_type_id' => 'Output Media Type',
            'output_ordered' => 'Output Ordered',
            'pos_x' => 'Pos X',
            'pos_y' => 'Pos Y',
            'workspace_id' => 'Workspace',
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
        $criteria->compare('datasource_type_id', $this->datasource_type_id);
        $criteria->compare('output_determined', $this->output_determined);
        $criteria->compare('output_media_type_id', $this->output_media_type_id);
        $criteria->compare('output_ordered', $this->output_ordered);
        $criteria->compare('pos_x', $this->pos_x);
        $criteria->compare('pos_y', $this->pos_y);
        $criteria->compare('workspace_id', $this->workspace_id);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }

    //region Application-specific functions

    /**
     * Get a very short information about the item in a human-readable way
     * @return The identifier and type of the item
     */
    public function getItemInfo()
    {
        return 'item ' . $this->id . ' of type \'' . get_class($this) . '\'';
    }


    /**
     * See base class
     */
    public function processInputQueue(&$errors)
    {
        Yii::log('Generating items for datasource '.$this->getItemInfo(), 'debug', 'Datasource');

        // Get the number of items that must be generated for this Datasource
        $totalCount = $this->items_count;

        if(!$this->workspace->publish){
            Yii::log('Abort generating items for '.$this->getItemInfo().' - workspace is not active', 'debug', 'Datasource');
            return array();
        }

        // Get the platform data
        $pd = $this->platform_data;
        if (is_string($pd))
            $pd = json_decode($pd, true);

        // First, make sure that the items have not yet been generated
        $subsequentItem = Helper::getOutputItem($this);
        if ($subsequentItem == null) {
            // No subsequent item found
            $msg = 'Cannot generate datasources - no subsequent item available for ' . $this->getItemInfo();
            Yii::log($msg, 'warning', 'DataSource');
            $errors[] = $msg;
            return;
        }
        // Calculate how many remaining items must be generated
        $processed_flowitems = $subsequentItem->processed_flowitems;
        if (is_string($processed_flowitems))
            $processed_flowitems = json_decode($processed_flowitems, true);
        $processedCount = sizeof($processed_flowitems);
        $remainingCount = $totalCount - $processedCount;

        Yii::log('Generating ' . $totalCount . ' items for ' . $this->getItemInfo() . ' - ' . $processedCount . ' items have been generated so far, remaining ' . $remainingCount, 'debug', 'DataSource');

        // Generate as many items as needed
        $itemsGenerated = array();
        for ($i = 0; $i < $remainingCount; $i++) {
            Yii::log('Generating item number ' . $i . ' out of ' . $remainingCount, 'debug', 'Datasource');

            // Prepare the result object
            $flowItem = array(
                'flowItemId' => uniqid(),
                'data' => array(),
                'itemType' => 'datasource',
                'itemId' => $this->id,
                'platformResultId' => -1,
            );

            // Read the needed attributes
            $type = $this->datasource_type_id;
            $determined = $this->output_determined;
            $media = $this->output_media_type_id;
            $ordered = $this->output_ordered;
            $data = $this->data;

            $inputDataParameters = sprintf('datasource_type=%d, media_type_id=%d, determined=%d, ordered=%d', $type, $media, $determined, $ordered);
            $dataExtract = NULL;
            if (strlen($data) > 20)
                $dataExtract = substr($data, 0, 20) . '...';
            else
                $dataExtract = $data;
            Yii::log('Getting input data with parameters ' . $inputDataParameters . ': data would be: \'' . $dataExtract . '\'', 'debug', 'Datasource');

            if ($type == 5 && $media == 2 && $determined == 1) {
                // Simple text input. Ignore order parameter - does not matter in this case
                $flowItem['data'] = $this->fix_encoding($data);
            } else if ($type == 3 && $media == 5 && $determined == 0) {
                // URL of a web service returning an image. Ignore order parameter - does not matter in this case
                Yii::log('Get url for the image by evaluating the web-service ' . $data, 'debug', 'Datasource');
                $imageUrl = file_get_contents($data);
                Yii::log('Found image url: ' . $imageUrl, 'debug', 'Datasource');
                $flowItem['data'] = '<img src="' . $imageUrl . '">';
            } else {
                throw new CHttpException(501, 'Cannot render datasource with the parameters ' . $inputDataParameters);
            }

            // Now, as the inputItem has been generated, we can forward it to the subsequent item
            $this->forwardAssignmentsToSubsequentItem(array($flowItem));

        $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItem['flowItemId'], $flowItem['platformResultId']);
            $itemsGenerated[]=$flowItem;
        }// End for each remaining item

        $this->platform_data = json_encode($pd);
        $this->saveAttributes(array('platform_data'));
        $this->platform_data = $pd;

        // Return the generated items
        Yii::log('Datasources:generated '.sizeof($itemsGenerated).' new flowItems', 'debug', 'Datasource');
        return $itemsGenerated;
    }


    //endregion Application-specific functions

    //region Auxiliary functions
    /**
     * Makes sure the input string is utf8-encoded.
     * @param $in_str
     * @return string
     */
    function fix_encoding($in_str)
    {
        if($in_str==null || $in_str=='')
            return '';
        $isUtf8=preg_match("//u", $in_str);
        if($isUtf8){
            Yii::log('Already UTF-8-encoded', 'debug', 'Datasource');
            return $in_str;
        }
        else{
            Yii::log('Encoding to UTF-8', 'debug', 'Datasource');
            return utf8_encode($in_str);
        }
    } // fixEncoding
    //endregion
}