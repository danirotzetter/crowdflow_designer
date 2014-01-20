<?php

/**
 * This is the model class for table "merger".
 *
 * The followings are the available columns in table 'merger':
 * @property integer $id
 * @property string $description
 * @property integer $merger_type_id
 * @property string $data
 * @property integer $pos_x
 * @property integer $pos_y
 * @property string $name
 * @property integer $workspace_id
 *
 * The followings are the available model relations:
 * @property Workspace $workspace
 * @property MergerTask[] $mergerTasks
 * @property TaskMerger[] $taskMergers
 */
class Merger extends CCsActiveRecord
{


    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Merger the static model class
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
        return 'merger';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('merger_type_id, pos_x, pos_y, workspace_id', 'numerical', 'integerOnly' => true),
            array('name', 'length', 'max' => 50),
            array('description, data, platform_data, input_queue, parameters, processed_flowitems', 'safe'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, description, merger_type_id, data, pos_x, pos_y, name, workspace_id, platform_data', 'safe', 'on' => 'search'),
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
            'mergerTasks' => array(self::HAS_MANY, 'MergerTask', 'merger_id'),
            'taskMergers' => array(self::HAS_MANY, 'TaskMerger', 'merger_id'),
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
            'merger_type_id' => 'Merger Type',
            'data' => 'Data',
            'pos_x' => 'Pos X',
            'pos_y' => 'Pos Y',
            'name' => 'Name',
            'workspace_id' => 'Workspace',
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
        $criteria->compare('description', $this->description, true);
        $criteria->compare('merger_type_id', $this->merger_type_id);
        $criteria->compare('data', $this->data, true);
        $criteria->compare('pos_x', $this->pos_x);
        $criteria->compare('pos_y', $this->pos_y);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('workspace_id', $this->workspace_id);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }


//region Application-specific functions


    /**
     * See base class
     */
    public function processInputQueue(&$errors)
    {
        Yii::log('Merger: Processing input queue for ' . $this->getItemInfo(), 'debug', 'Merger');
        $type = $this->merger_type_id;


        // Get the item to which the data will be forwarded
        $subsequentItem = Helper::getOutputItem($this);
        if ($subsequentItem == null) {
            Yii::log('Merger: Cannot process inputQueue yet for ' . $this->getItemInfo() . ': no subsequent item available', 'info', 'Merger');
            return;
        }

        // Get the min_assignments value
        //region MinAssignments handling
        $inputItem = Helper::getInputItem($this);
        if ($inputItem == null) {
            // Invalid process - cannot treat data of the merger if no input item was found
            $msg = 'Merger: Cannot perform merging for ' . $this->getItemInfo() . ': the input item for this merger could not be found';
            Yii::log($msg, 'warning', 'Merger');
            $errors[] = $msg;
            return;
        }
        $inputPar = $inputItem->parameters;
        if (is_string($inputPar))
            $inputPar = json_decode($inputPar, true);
        if (!array_key_exists('min_assignments', $inputPar)) {
            // Invalid process - cannot apply majority voting when no threshold was defined
            $msg = 'Merger: Cannot perform merging for ' . $this->getItemInfo() . ': no minimum number of assignments is indicated for the merger\'s input item ' . $inputItem->getItemInfo() . ' ';
            Yii::log($msg, 'warning', 'Merger');
            $errors[] = $msg;
            return;
        }
        $minAssignmentsPreviousItem = $inputPar['min_assignments'];
        //endregion MinAssignments handling

        /*
         * Must refresh this record, since in the meantime, more data could have been added to the input queue. Refreshing here is important since the input queue is emptied in this
         * method, which means that input queue items are deleted before even treating them (unless the input queue is updated here)
         */
        $this->refresh();
        $inputQueue = $this->input_queue;
        if (is_string($inputQueue))
            $inputQueue = json_decode($inputQueue, true);

        // Initialize the platform data
        $pd = $this->platform_data;
        if (is_string($pd))
            $pd = json_decode($pd, true);

        if (sizeof($inputQueue) == 0) {
            Yii::log('Merger: No need to process the input queue of ' . $this->getItemInfo() . ' - the input queue is empty.', 'debug', 'Merger');
            return;
        }

        switch ($type) {
            //region Type 31
            case 31:
                // Merger consists of calling an external WebService with the inputQueue as data


                // Prepare the array of data that are sent along with the request
                $itemsPerFlowItemId = array();
                $itemsMerged = array();
                $itemsNotMerged = array();
                foreach ($inputQueue as $inputQueueItem) {
                    Yii::log('Merger: Adding item to merge ' . print_r($inputQueueItem, true), 'debug', 'Merger');
                    $flowItemId = $inputQueueItem['flowItemId'];
                    if (!array_key_exists($flowItemId, $itemsPerFlowItemId))
                        $itemsPerFlowItemId[$flowItemId] = array();
                    $itemsPerFlowItemId[$flowItemId][] = $inputQueueItem;
                }
                // Take into account the minimum number of assignments
                foreach ($itemsPerFlowItemId as $flowItemId => $items) {
                    $itemsCount = sizeof($items);
                    $thresholdReached = $itemsCount >= $minAssignmentsPreviousItem;
                    Yii::log('Merger: processing input queue for flowItemId ' . $flowItemId . ' that contains ' . $itemsCount . ' items: minimum of ' . $minAssignmentsPreviousItem . ' items has ' . ($thresholdReached ? '' : 'not ') . ' been reached', 'debug', 'Merger');
                    if ($thresholdReached)
                        $itemsMerged = array_merge($itemsMerged, $items);
                    else
                        $itemsNotMerged = array_merge($itemsNotMerged, $items);
                }

                // Prepare the request
                $url = $this->data;
                if (strpos($url, 'http') === false) {
                    // relative url: prepend the base URL
                    // TODO remove conversion
                    // Avoid heroku limitations of one simultaneous connection
//                $url = Yii::app()->params['backendBaseUrl'] . $url;
                    $url = Yii::app()->params['backendBaseAlternativeUrl'] . $url;
                }


                // Encoding erroneous since new lines are not escaped properly
                //$content = http_build_query($arrayToMerge);

                $content = json_encode($itemsMerged);
                // JSON requires new line characters be escaped
                $content = str_replace("\n", "\\n", $content);


                $options = array(
                    'http' => array( // use key 'http' even if you send the request to https://...
                        'header' => "Content-type: application/json\r\n",
                        'method' => 'POST',
                        'content' => $content,
                    ),
                );

                try {
                    // Read the request payload
                    $context = stream_context_create($options);
                    Yii::log('Merger: About to send a request to \'' . $url . '\' with a payload of size ' . sizeof($itemsMerged) . ': ' . $content, 'info', 'Merger');
                    $result = @file_get_contents($url, false, $context); // The @ suppresses the warning (errors are handled in the subsequent code)
                    $result = json_decode($result, true);

                    if ($result === FALSE) {
                        // Could not access the web service
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed';
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    }
                    Yii::log('Merger: Got HTTP request result from \'' . $url . '\': ' . print_r($result, true), 'info', 'Merger');

                    if ($result == null) {
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed, returned null';
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    } else if (!array_key_exists('success', $result)) {
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed - no success variable returned';
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    } else if (!$result['success']) {
                        $errs = '';
                        if (array_key_exists('errors', $result) && is_array($result['errors']))
                            $errs = ' (' . join(', ', $result['errors']) . ')';
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed - web service has reported errors' . $errs;
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    }

                    // Request successful: fetch the data

                    // Verify that the answer format is correct
                    if (!array_key_exists('processed', $result['data'])) {
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed - no array of processed items returned';
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    }
                    if (!array_key_exists('notprocessed', $result['data'])) {
                        $msg = 'Merger: Failed to process the input queue for ' . $this->getItemInfo() . ': call to web service \'' . $url . '\' has  failed - no array of not processed items returned';
                        Yii::log($msg, 'error', 'Merger');
                        $errors[] = $msg;
                        return array();
                    }

                    // Answer has the correct format
                    $itemsMerged = $result['data']['processed'];
                    $itemsNotMerged = $result['data']['notprocessed'];

                    Yii::log('Merger: call to web service succeeded, got ' . sizeof($itemsMerged) . ' items that have been processed and ' . sizeof($itemsNotMerged) . ' items that should remain in the inputQueue of ' . $this->getItemInfo(), 'debug', 'Merger');

                    // If the operation was successful (i.e. this code line is reached), we can update the input queue
                    Yii::log('Merger: About to replace the input queue of ' . $this->getItemInfo() . ': had ' . sizeof($inputQueue) . ' items, ' . sizeof($itemsMerged) . ' items were processed, has now new inputQueue of size ' . sizeof($itemsNotMerged), 'debug', 'Merger');
                    $this->input_queue = json_encode($itemsNotMerged);
                    $this->saveAttributes(array('input_queue'));
                    $this->input_queue = json_decode($this->input_queue, true);

                    // Finally, add the result to the input queue of the subsequent item
                    Yii::log('Merger: About to add the result (' . sizeof($itemsMerged) . ' to the subsequent item\'s input queue', 'debug', 'Merger');

                    FlowManagement::addToInputQueue($subsequentItem, $itemsMerged);


                    return $itemsMerged;

                } catch (Exception $ex) {
                    $msg = 'Merger: Could not process inputQueue for merger ' . $this->getItemInfo() . ': ' . $ex->getMessage();
                    Yii::log($msg, 'error', 'Merger');
                    $errors[] = $msg;
                    return array();
                }
                break;
            //endregion Type 31
            //region Type 25
            case 25:
                // Majority voting
                // Input item must be crowd-sourced
                if (!FlowManagement::isCrowdsourced($inputItem)) {
                    // Invalid process - cannot apply majority voting on non-crowdsourced input items
                    $msg = 'Merger: Cannot perform merging for ' . $this->getItemInfo() . ': the input item ' . $inputItem->getItemInfo() . ' is not crowd-sourced';
                    Yii::log($msg, 'warning', 'Merger');
                    $errors[] = $msg;
                    return;
                }

                $thresholdAgreement = intval($this->data);
                $processedFlowItems = array(); // The processed flowItems (that will be returned as a result of the function call)


                Yii::log('Merger: Executing majority voting for ' . $this->getItemInfo() . ' with a threshold (minimum agreement) of ' . $thresholdAgreement . '%, take into account the minimum of ' . $minAssignmentsPreviousItem . ' items submitted before doing the breakdown', 'debug', 'Merger');

                // Generate a list of all items for each flowItemId
                $associatedItems = array();
                foreach ($inputQueue as $inputQueueItem) {
                    if (array_key_exists('flowItemId', $inputQueueItem)) {
                        $flowItemId = $inputQueueItem['flowItemId'];
                        // Create a new key for the flowItemId, if needed
                        if (!array_key_exists($flowItemId, $associatedItems))
                            $associatedItems[$flowItemId] = array();

                        // Make sure the data is available
                        if (array_key_exists('data', $inputQueueItem)) {
                            $data = $inputQueueItem['data'];
                            // Check the data type for validity
                            if (is_string($data) || is_numeric($data)) {
                                // Valid data type

                                // Prefix integer values, since they cannot serve as key in associative arrays
                                if (is_numeric($data))
                                    $data = Helper::$NUM_PREFIX . $data;

                                // Create a new key for the data, if needed
                                if (!array_key_exists($data, $associatedItems[$flowItemId]))
                                    $associatedItems[$flowItemId][$data] = array();
                                $associatedItems[$flowItemId][$data][] = $inputQueueItem;
//                                Yii::log('Merger: Successfully analyzed inputQueueItem for flowItemId ' . $flowItemId . ' with result value ' . $data, 'debug', 'Merger');
                            } else {
                                /**
                                 * There are two options to execute majority voting. The first one was handled just above: if the 'data', which is the information available for each flowItem, is
                                 * of type string or is numeric, then this is the value that is used and compared.
                                 * However, there might be some more complex crowd-sourced tasks. In this case, the crowd-sourced form must contain a (hidden) field 'resultfield' which indicates
                                 * the attribute that is used for majority voting.
                                 * For example, in a categorization task, among others, a field named 'categoryId' is submitted that indicates the categoryId that a worker has selected. This form
                                 * must contain a hidden field named 'resultfield' with a constant string value 'categoryId'. By doing so, the web application knows that the majority voting must
                                 * not be executed on the flowItem's 'data' attribute (which now contains a series of fields). Instead, the value of the submitted field 'categoryId' must be evaluated
                                 * and compared. In the end, the 'categoryId' which was most often indicated in the crowd-sourced assignments will win the majority voting process.
                                 */
                                Yii::log('Merger: looking for resultfield in inputQueueItem\'s data array' . print_r($inputQueueItem['data'], true), 'debug', 'Merger');
                                if (is_array($data)) {
                                    // Find the resultfield
                                    $resultfield = null;
                                    foreach ($data as $field) {
                                        if ($field['id'] == 'resultfield') {
                                            $resultfield = $field['value'];
                                        }
                                    }
                                    if ($resultfield != null) {
                                        $resultfieldValue = null;
                                        foreach ($data as $field) {
                                            if ($field['id'] == $resultfield) {
                                                $resultfieldValue = $field['value'];
                                            }
                                        }
                                        if ($resultfieldValue != null) {
                                            if (is_string($resultfieldValue) || is_numeric($resultfieldValue)) {
                                                // Valid data
                                                // Prefix integer values, since they cannot serve as key in associative arrays
                                                if (is_numeric($resultfieldValue))
                                                    $resultfieldValue = Helper::$NUM_PREFIX . $resultfieldValue;

                                                Yii::log('Merger: Executing majority voting on inputQueue item field \'' . $resultfield . '\'', 'debug', 'Merger');
                                                // Create a new array entry for this data value, if necessary
                                                if (!array_key_exists($resultfieldValue, $associatedItems[$flowItemId]))
                                                    $associatedItems[$flowItemId][$resultfieldValue] = array();
                                                // Add the item itself
                                                $associatedItems[$flowItemId][$resultfieldValue][] = $inputQueueItem;
//                                                Yii::log('Merger: Successfully analyzed inputQueueItem for flowItemId ' . $flowItemId . ' with result value ' . $resultfieldValue.', is now '.print_r($associatedItems, true), 'debug', 'Merger');
                                            } else {
                                                // Invalid process - supplied 'resultfield' field has invalid type
                                                $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ': the item\'s resultfield \'' . $resultfield . '\' field is not of a valid type - must be of type \'string\' or \'numeric\'';
                                                Yii::log($msg, 'warning', 'Merger');
                                                $errors[] = $msg;
                                                continue;
                                            }
                                        } else {
                                            // Invalid process - supplied 'resultfield' field is not available
                                            $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ': the item\'s data does not have a field with resultfield id \'' . $resultfield . '\'';
                                            Yii::log($msg, 'warning', 'Merger');
                                            $errors[] = $msg;
                                            continue;
                                        }
                                    } else {
                                        // Invalid process - supplied no 'resultfield' field
                                        $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ': the item has an array as \'data\' attribute, but there is no field with id \'resultfield\' (whose value must be of type \'string\' or \'numeric\') submitted in the data array that can be used in order to execute the majority voting';
                                        Yii::log($msg, 'warning', 'Merger');
                                        $errors[] = $msg;
                                        continue;
                                    }
                                } else {
                                    // Invalid process - cannot treat data of this type
                                    $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ': the item contains data that cannot be treated. Only data of type \'string\' and \'numeric\' can be processed, or an array that contains a majority-voting-compatible field \'result\'. But the data is: ' . print_r($data, true);
                                    Yii::log($msg, 'warning', 'Merger');
                                    $errors[] = $msg;
                                    continue;
                                }
                            }

                        } else {
                            // Invalid process - no data available
                            $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ': the item does not contain the \'data\' attribute, which must be set in order to find the most-often selected answer';
                            Yii::log($msg, 'warning', 'Merger');
                            $errors[] = $msg;
                            return;
                        }
                    } else {
                        // Invalid process - no flowItemId available
                        $msg = 'Merger: Cannot perform majority voting on an item of the queue in ' . $this->getItemInfo() . ': the item does not contain the \'flowItemId\' attribute, which must be set in order to identify the flowItem this inputQueueItem is referring to';
                        Yii::log($msg, 'warning', 'Merger');
                        $errors[] = $msg;
                        return;
                    }
                }
                // End for each inputQueue item

                if (sizeof($associatedItems) == 0) {
                    // Nothing to do yet - no data found for which the majorityVoting could be executed
                    Yii::log('Merger: skip majority voting: no queueItems available yet', 'debug', 'Merger');
                } else {
                    // Nothing to do yet - no data found for which the majorityVoting could be executed
                    Yii::log('Merger: majority voting on items for ' . sizeof($associatedItems) . ' distinct flowItems in the inputQueue', 'debug', 'Merger');
                }

                // Check for every flowItemId, if the threshold has been reached and thus a result can be accepted
                foreach ($associatedItems as $flowItemId => $flowItemsPerData) {
                    // Order the inputQueueItems by the number of items for each data value
                    @uksort($flowItemsPerData, array($this, 'compareByCount')); // Suppress the warning 'uksort(): Array was modified by the user comparison function'
                    Yii::log('Merger: ordered ' . sizeof($flowItemsPerData) . ' items for flowItemId ' . $flowItemId, 'debug', 'Merger');

                    // Log results
                    $breakdown = '';

                    // Count the total number of items available for a flowItemId
                    $totalItemsForThisFlowItemId = 0;
                    $agreementScoreByResult = array(); // The data that reached the minimum votes
                    $rejectedAgreementsByResult = array(); // The data that has not reached the minimum votes
                    foreach ($flowItemsPerData as $data => $items) {
                        // Count the total number of items for this flowItemId
                        $totalItemsForThisFlowItemId += sizeof($items);
                    }
                    // Make the breakdown for each data/ for each result submitted for this flowItemId
                    foreach ($flowItemsPerData as $data => $items) {
                        $itemsCount = sizeof($items);
                        $percentageReached = round(($itemsCount * 100) / $totalItemsForThisFlowItemId, 2);
                        $percentageReachedAllVotes = round(($itemsCount * 100) / $minAssignmentsPreviousItem, 2);

                        // logging
                        $breakdown .= $data . ' (' . $itemsCount . ' results/ ' . $percentageReached . '% of submitted votes/ ' . $percentageReachedAllVotes . '% of expected votes) ';

                        // Handle the case where the threshold was reached, even though the minAssignmentsPreviousItem was not yet achieved
                        $percentageReachedIsLargeEnough = $percentageReachedAllVotes >= $thresholdAgreement;
                        Yii::log('Merger: Majority voting on flowItemId ' . $flowItemId . '  - the topmost answer got ' . $percentageReachedAllVotes . '% of all votes including not-submitted votes, which is ' . ($percentageReachedIsLargeEnough ? 'above' : 'below') . ' the defined threshold (' . ($percentageReachedIsLargeEnough ? '>=' : '<') . $thresholdAgreement . '%)', 'debug', 'Merger');
                        if (!$percentageReachedIsLargeEnough) {
// We only add the result to the list of assignments for each submitted results if the threshold was reached
                            if (!array_key_exists($data, $rejectedAgreementsByResult))
                                $rejectedAgreementsByResult[$data] = array();
                            $rejectedAgreementsByResult[$data] = $percentageReachedAllVotes;
                        } else {
// We only add the result to the list of assignments for each submitted results if the threshold was reached
                            if (!array_key_exists($data, $agreementScoreByResult))
                                $agreementScoreByResult[$data] = array();
                            $agreementScoreByResult[$data] = $percentageReachedAllVotes;
                        }


                    }

                    Yii::log('Merger: executing majority voting for flowItemId ' . $flowItemId . ' for ' . $totalItemsForThisFlowItemId . ' inputQueueItems - breakdown: ' . $breakdown, 'debug', 'Merger');


                    // Evaluate which item must be accepted and forwarded in the flow
                    $topResultValue = null; // The topmost result
                    $topResultAgreementScore = null; // The percentage of the topmost result
                    // Check if results have reached the threshold
                    if (sizeof($agreementScoreByResult) == 0) {
                        // No result has reached the threshold
                        if ($totalItemsForThisFlowItemId == $minAssignmentsPreviousItem) {
                            // All results have been submitted, but the threshold was not reached  - accept the topmost answer instead
                            asort($rejectedAgreementsByResult);
                            reset($rejectedAgreementsByResult);
                            $topResultValue = key($rejectedAgreementsByResult);
                            $topResultAgreementScore = $rejectedAgreementsByResult[$topResultValue];
                            Yii::log('Merger: Majority voting on flowItemId ' . $flowItemId . ' - all ' . $minAssignmentsPreviousItem . ' items have been submitted, but no item has reached the threshold of ' . $thresholdAgreement . '%. Taking the topmost answer of the submitted results instead (result ' . $topResultValue . ' which has reached ' . $topResultAgreementScore . '% of the votes)', 'debug', 'Merger');
                        } else {
                            // Not all results have been submitted - wait for further results
                            $pendingResults = $minAssignmentsPreviousItem - $totalItemsForThisFlowItemId;
                            Yii::log('Merger: Majority voting on flowItemId ' . $flowItemId . ' - aborted, since the threshold of ' . $thresholdAgreement . '%) was not reached for any result and there are still ' . $pendingResults . ' results pending out of '.$minAssignmentsPreviousItem, 'debug', 'Merger');
                            continue;
                        }
                    } else {
                        /**
                         * We now have made the analysis and can accept the most-voted answer.
                         * By now, we know that the top-voted answer has reached the agreement threshold and that the minimum number
                         * of items have been submitted for this flowItemId
                         */
                        asort($agreementScoreByResult);
                        reset($agreementScoreByResult);
                        $topResultValue = key($agreementScoreByResult);
                        $topResultAgreementScore = $agreementScoreByResult[$topResultValue];

                        if (sizeof($agreementScoreByResult) > 1) {
                            // Got multiple data that have reached the required agreement threshold
                            Yii::log('Merger: Majority voting on flowItemId ' . $flowItemId . ' - Multiple items have reached the minimum of ' . $thresholdAgreement . '% - taking the most voted answer with data \'' . $topResultValue . '\' (which got ' . $topResultAgreementScore . '% of all votes)', 'debug', 'Merger');
                        } else {
                            // Got exactly one value that has reached the minimum
                            Yii::log('Merger: Majority voting on flowItemId ' . $flowItemId . ' -Got exactly one value that has reached the minimum of ' . $thresholdAgreement . '%: data \'' . $topResultValue . '\' has reached ' . $topResultAgreementScore . '% of all votes', 'debug', 'Merger');
                        }
                    }

                    /*
                     * TODO in this implementation, all assignments that were forwarded to this merger have already been approved. Another option would be to either accept or
                     * reject the assignment on the crowd-sourcing platform, depending on the merger's result: all assignments that match the top-voted answer would then be approved,
                     * and the others rejected.
                     */

                    // Now, we can finally process the items
                    foreach ($flowItemsPerData as $data => $flowItems) {
                        if ($data == $topResultValue) {
                            Yii::log('Merger: Accepting data ' . $data, 'debug', 'Merger');
                            if (sizeof($flowItems) < 1) {
                                // Make sure at least one item contains this value
                                $msg = 'Merger: cannot forward top voted answer - no items submitted with this value';
                                Yii::log($msg, 'warning', 'Merger');
                                $errors[] = $msg;
                                return;
                            }

                            // Can take any item that matches the most-voted answer and add it to the subsequent item
                            Yii::log('Merger: Moving the top-voted answer in ' . $this->getItemInfo() . ' to the subsequent item ' . $subsequentItem->getItemInfo(), 'debug', 'Merger');
                            FlowManagement::addToInputQueue($subsequentItem, array($flowItems[0]));

                            if ($subsequentItem->hasAttribute('platform_data')) {
                                // If the subsequent item keeps track of all pending assignments: add it to the appropriate list
                                $spd = $subsequentItem->platform_data;
                                if (is_string($spd))
                                    $spd = json_decode($spd, true);
                                $spd = Helper::modifyAssignmentsList('pending', $spd, true, $flowItemId, $flowItems[0]['platformResultId']);
                                $subsequentItem->platform_data = json_encode($spd);
                                $subsequentItem->saveAttributes(array('platform_data'));
                                $subsequentItem->platform_data = $spd;
                            }


                            // Update the list of processed items
                            foreach ($flowItems as $flowItem) {
                                $processedFlowItems[] = $flowItem;
                                $assignmentId = $flowItem['platformResultId'];
                                $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItemId, $assignmentId);
                                $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $assignmentId);
                            }
                            // Remove the processed flowItem
                            FlowManagement::removeFromInputQueue($this, $flowItems);
                            //End for each flowItem that also contained the result of the top voted answer
                        } // End the result was the top-voted one
                        else {
                            // Data was not top-voted one
                            // Update the list of processed items, adding the non-top-voted assignments to the list of rejected assignments
                            foreach ($flowItems as $flowItem) {
                                $processedFlowItems[] = $flowItem;
                                $assignmentId = $flowItem['platformResultId'];
                                $pd = Helper::modifyAssignmentsList('rejected', $pd, true, $flowItemId, $assignmentId);
                                // The assignment was possibly added to the 'accepted' list in a previous flow step - remove it from there
                                $pd = Helper::modifyAssignmentsList('accepted', $pd, false, $flowItemId, $assignmentId);

                                $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $assignmentId);
                            }
                            // Remove the processed flowItem
                            FlowManagement::removeFromInputQueue($this, $flowItems);
                            //End for each non-topvoted answer item
                        }
                        // End the result was not top-voted one
                    }
                    // End for all data=>assignments pair for the current flowItemId
                }
                // End for each flowItemId

                // Must update the platform data
                $this->platform_data = json_encode($pd);
                $this->saveAttributes(array('platform_data'));
                $this->platform_data = $pd;

                Yii::log('Merger: Majority voting has processed ' . sizeof($processedFlowItems) . ' inputQueue items', 'debug', 'Merger');
                return $processedFlowItems;

                break;
            //endregion Type 25
            default:
                // Other types are not implemented explicitly
                return parent::processInputQueue($errors);
                break;
        }

    }


    /**
     * Compares two arrays by their size - needed for sorting
     * @param $arrayA
     * @param $arrayB
     * @return int
     */
    private static function compareByCount($arrayA, $arrayB)
    {
        $sizeA = sizeof($arrayA);
        $sizeB = sizeof($arrayB);
        return $sizeA - $sizeB;
    }
    //endregion Application-specific functions
}