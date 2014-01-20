<?php

class MergerController extends CsController
{

    private $defaultLoads = array('inputs');


    //region Entity access methods
    /**
     * Displays a particular model.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionView($id)
    {
        $model = $this->loadModel($id);
        $this->apiView($model, $this->defaultLoads);
    }

    /**
     * Creates a new model.
     */
    public function actionCreate()
    {
        $model = new Merger;


        $data = $this->readJsonData();
        $data = $this->parsePosition($data);
        $model->attributes = $data;

        if (!$model->validate()) {
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        } else {
            Yii::log('Saving object ' . print_r($model->attributes, true), 'debug', 'MergerController');
            $success = $model->save();
            $this->apiCreate($model, $success);
        }

    }

    /**
     * Updates a particular model.
     * @param integer $id the ID of the model to be updated
     */
    public function actionUpdate($id)
    {
        $model = $this->loadModel($id);

        $data = $this->readJsonData();
        Yii::log('Before positions parsed ' . print_r($data, true), 'debug', 'MergerController');
        $data = $this->parsePosition($data);
        Yii::log('After positions parsed ' . print_r($data, true), 'debug', 'MergerController');

        $model->attributes = $data;

        if (!$model->validate()) {
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        } else {
            Yii::log('Saving object ' . print_r($model->attributes, true), 'debug', 'MergerController');
            $success = $model->save();
            $this->apiUpdate($model, $success);
        }
    }


    /**
     * Entry point for 'general' rest functions of tasks
     */
    public function actionIndex()
    {
        // List all tasks
        $dataProvider = new CActiveDataProvider('Merger');
        $this->apiIndex($dataProvider->getData(), $this->defaultLoads);
    }

    public function actionInputs($id)
    {
        $model = $this->loadModel($id);
        $inputs = $model->inputs;
        Yii::log('Get inputs of merger "' . $id . '": ' . print_r($inputs, true), 'debug', 'MergerController');
        $this->apiIndex($inputs);
    }


    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = Merger::model()->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }

    //endregion Entity access methods

    //region Application-specific functions
    /**
     * Executes a merging. This service may be used for web service calls of mergers in the applications's front-end.
     * For example: concatenating translated text passages is implemented in this function. Hence, in the frontend's
     * merger definition, this service is indicated to be used in the merging process.
     */
    public function actionProcess($type)
    {
        Yii::log('Processing merge of type \'' . $type . '\'', 'debug', 'MergerController');

        // Read and parse the payload
        $itemsToFilter = $this->readJsonData();


        // Handle the threshold, if any
        if (isset($_GET['threshold'])) {
            $threshold = $_GET['threshold'];
            if (is_array($itemsToFilter)) {
                $nbItems = sizeof($itemsToFilter);
                if ($nbItems < $threshold) {
                    // Not enough items available to process
                    $result = array('processed' => array(), 'notprocessed' => $itemsToFilter);
                    $msg = 'Not processing merging: threshold of ' . $threshold . ' items not yet reached - got only ' . $nbItems;
                    Yii::log($msg, 'info', 'MergerController');
                    echo $this->getNormalizedAnswerObject(true, $result, array($msg));;
                    return;
                } else {
                    // Enough items available
                    Yii::log('Can process merging, as the threshold of ' . $threshold . ' was reached - received ' . $nbItems . ' items', 'debug', 'MergerController');
                }
            } else {
                // Cannot apply threshold if not an array is delivered
                $msg = 'Not processing merging: cannot handle threshold of ' . $threshold . ', as not an array of items is sent';
                Yii::log($msg, 'info', 'MergerController');
                echo $this->getNormalizedAnswerObject(false, null, array($msg));;
                return;
            }
        }


        switch ($type) {
            case 'concatenate':
                Yii::log('Processing concatenation', 'debug', 'MergerController');

                // Concatenate each input item
                $result = array('processed' => array(), 'notprocessed' => array());
                if ($itemsToFilter == null || !is_array($itemsToFilter)) {
                    // Invalid format - webservice is unable to process this type of input
                    $msg = 'Cannot merge an item using a filter - invalid format (must be of type array). Item content: ' . print_r($itemsToFilter, true);
                    Yii::log($msg, 'warning', 'MergerController');
                    $this->sendError(500, $msg);
                    return;
                }

                //region Handle sorting
                // Check if there is an order to be respected
                $orderBy = Helper::$ORDERKEY_FIELD;
                Yii::log('Processing merge, ordering the ' . sizeof($itemsToFilter) . ' items by \'' . $orderBy . '\'', 'debug', 'MergerController');

                // Create a list of all to-be-ordered attribute values
                $orderedItems = array();
                foreach ($itemsToFilter as $itemToFilter) {
                    if (!array_key_exists('data', $itemToFilter)) {
                        // Wrong format
                        Yii::log('Cannot order an item - does not contain the \'data\' attribute', 'error', 'MergerController');
                        continue;
                    }
                    $dataToFilter = $itemToFilter['data'];
                    if (!is_array($dataToFilter)) {
                        Yii::log('Cannot order an item - does not contain an array of data values', 'error', 'MergerController');
                        continue;
                    }
                    Yii::log('Processing inputQueueItem ' . print_r($dataToFilter, true), 'debug', 'MergerController');

                    $sortValue = null;
                    // Go through each submitted field in order to find the one on which the items must be ordered
                    foreach ($dataToFilter as $field) {
                        $fieldId = $field['id'];
                        $fieldValue = $field['value'];
                        if (strtolower($fieldId) == strtolower($orderBy)) {
                            // Found the filter attribute: add the item with the orderBy key in the associative array of the inputQueues
                            if (array_key_exists($fieldValue, $orderedItems)) {
                                $orderedItems[$fieldValue] = array();
                            }
                            $orderedItems[$fieldValue][] = $itemToFilter;
                            continue 2; // Move to next item (instead of next field)
                        }
                    }
                    Yii::log('Cannot order an item - key \'' . $orderBy . '\' not found', 'debug', 'MergerController');
                    // End for each field
                }
                // End for each item to merge

                // Now, we can order the items by the to-be-sorted-on attribute
                ksort($orderedItems);
                Yii::log('Ordered items by attribute ' . $orderBy . ': ' . print_r($orderedItems, true), 'debug', 'MergerController');
                $itemsToFilter = $orderedItems;
                //endregion Handle sorting


                Yii::log('About to merge ' . sizeof($itemsToFilter) . ' items', 'debug', 'MergerController');
                foreach ($itemsToFilter as $key => $itemsToFilterThisKey) {
                    Yii::log('Concatenating '.sizeof($itemsToFilter).' items with key ' . $key, 'debug', 'MergerController');
                    foreach($itemsToFilterThisKey as $itemToFilter){
                    Yii::log('Concatenating item with key ' . $key . ': ' . print_r($itemToFilter, true), 'debug', 'MergerController');

                    if (!array_key_exists('data', $itemToFilter)) {
                        // Wrong format
                        Yii::log('Cannot concatenate an item - does not contain the \'data\' attribute', 'error', 'MergerController');
                        $result['notprocessed'][] = $itemToFilter;
                        continue;
                    }
                    $dataToFilter = $itemToFilter['data'];

                    if (!is_array($dataToFilter)) {
                        // Invalid format - webservice is unable to process this type of input
                        $msg = 'Cannot concatenate an item - invalid format (must be of type array). Item content: ' . print_r($dataToFilter, true);
                        Yii::log($msg, 'warning', 'MergerController');
                        $this->sendError(500, $msg);
                    }

                    Yii::log('Concatenating data array ' . print_r($dataToFilter, true), 'debug', 'MergerController');
                    /*
                     * This is the case where a flowItem was submitted which contains additional meta information like flowItemId
                     * but it also contains one data field that must be merged, i.e. the field with id 'input0'.
                     * In this case, all input0 values are merged.
                     */
                    foreach ($dataToFilter as $itemAttribute) {
                        if ($itemAttribute['id'] == 'input0') {
                            $valToConcatenate = $itemAttribute['value'];
                            Yii::log('Concatenating input0 content ' . $valToConcatenate, 'debug', 'MergerController');
                            if (sizeof($result['processed']) == 0) {
                                // First result - the list of result items is an array containing one single item (which again is represented as an array)
                                $result['processed'] = array(
                                    array(
                                        'flowItemId' => uniqid(), // Create a new flowItemId
                                        'platformResultId' => -1, // Not assigned to an assignment of the crowd-sourcing platform
                                        'data' => $valToConcatenate,
                                        'itemId' => $itemToFilter['itemId'],
                                        'itemType' => $itemToFilter['itemType'],
                                    )
                                );
                            } else {
                                // The result flowItem has already been initialized
                                $result['processed'][0]['data'] .= $valToConcatenate;
                            }
                        }
                    }
                    // End for each form field
                }// End for each item in the list of items for this key
                }// End for each array of items for a given key
                //End for each item to concatenate
                Yii::log('Successfully concatenated ' . sizeof($itemsToFilter) . ' strings, now returning the result ' . print_r($result, true), 'debug', 'MergerController');
                echo $this->getNormalizedAnswerObject(true, $result);
                break;
            case
            'filter':
                if (!isset($_GET['filterattribute'])) {
                    $msg = 'Cannot merge by filter - must supply \'filterattribute\' parameter';
                    Yii::log($msg, 'warning', 'MergerController');
                    $this->sendError(500, $msg);
                }
                if (!isset($_GET['filtervalue'])) {
                    $msg = 'Cannot merge by filter - must supply \'filtervalue\' parameter';
                    Yii::log($msg, 'warning', 'MergerController');
                    $this->sendError(500, $msg);
                }
                if (!isset($_GET['dataattribute'])) {
                    $msg = 'Cannot merge by filter - must supply \'dataattribute\' parameter';
                    Yii::log($msg, 'warning', 'MergerController');
                    $this->sendError(500, $msg);
                }
                $filterAttribute = $_GET['filterattribute'];
                $filterValue = $_GET['filtervalue'];
                $dataAttribute = $_GET['dataattribute'];


                Yii::log('About to filter the submitted items by condition ' . $filterAttribute . '==\'' . $filterValue . '\', thereby collecting the data supplied in the dataAttribute \'' . $dataAttribute . '\'', 'debug', 'MergerController');

                // Collect all matching items
                $result = array('processed' => array(), 'notprocessed' => array());
                if ($itemsToFilter == null || !is_array($itemsToFilter)) {
                    // Invalid format - webservice is unable to process this type of input
                    $msg = 'Cannot merge an item using a filter - invalid format (must be of type array). Item content: ' . print_r($itemsToFilter, true);
                    Yii::log($msg, 'warning', 'MergerController');
                    $this->sendError(500, $msg);
                    return;
                }

                Yii::log('About to filter content ' . print_r($itemsToFilter, true), 'debug', 'MergerController');
                foreach ($itemsToFilter as $itemToFilter) {
                    Yii::log('Filtering item ' . print_r($itemToFilter, true), 'debug', 'MergerController');

                    if (!array_key_exists('data', $itemToFilter)) {
                        // Wrong format
                        Yii::log('Cannot filter item - does not contain the \'' . data . '\' attribute', 'error', 'MergerController');
                        $result['notprocessed'][] = $itemToFilter;
                        continue;
                    }
                    $dataToFilter = $itemToFilter['data'];

                    if (!is_array($dataToFilter)) {
                        // Invalid format - webservice is unable to process this type of input
                        $msg = 'Cannot merge an item using a filter - invalid format (must be of type array). Item content: ' . print_r($dataToFilter, true);
                        Yii::log($msg, 'warning', 'MergerController');
                        $this->sendError(500, $msg);
                    }

                    Yii::log('Filtering data array ' . print_r($dataToFilter, true), 'debug', 'MergerController');
                    /*
                     * Go through every attribute found in order to check if it is the attribute on which the item must be filtered
                     */
                    $filterAttributeValue = null;
                    $dataAttributeValue = null;
                    foreach ($dataToFilter as $itemAttribute) {
                        $id = $itemAttribute['id'];
                        $val = $itemAttribute['value'];
                        if (strtolower($id) == strtolower($filterAttribute)) {
                            $filterAttributeValue = $val;
                        }
                        if (strtolower($id) == strtolower($dataAttribute)) {
                            $dataAttributeValue = $val;
                        }
                    }

                    // Check if the filter and data attribute were found
                    if ($filterAttributeValue == null) {
                        // Filter attribute value unavailable
                        Yii::log('Cannot process item, as the filter attribute is not present', 'warning', 'MergerController');
                        $result['notprocessed'][] = $itemToFilter;
                        continue;
                    }
                    if ($dataAttributeValue == null) {
                        // Data attribute unavailable
                        Yii::log('Cannot process item, as the data attribute is not present', 'warning', 'MergerController');
                        $result['notprocessed'][] = $itemToFilter;
                        continue;
                    }

                    // Data attribute available
                    $conditionMet = strtolower((string)$filterValue) == strtolower((string)$filterAttributeValue);
                    Yii::log('Can process item. Verifying that ' . $filterAttribute . '==' . $filterValue . ': value found is ' . $filterAttributeValue . ': ' . ($conditionMet ? 'Condition met - collecting data ' . print_r($dataAttributeValue, true) : 'Condition not met, item is discarded'), 'debug', 'MergerController');
                    if ($conditionMet) {
                        $result['processed'][] = $itemToFilter;
                    } else {
                        /**
                         * Discard the item by not returning it in the web request
                         * (do not add it to the 'notprocessed' array, since in this case it would be kept in
                         * the items' inputQueue, which is not what we want since we want it to be removed)
                         */
                    }

                }
                //End for each item that must be filtered

                Yii::log('Successfully filtered ' . sizeof($itemsToFilter) . ' items, now returning the result ' . print_r($result, true), 'debug', 'MergerController');
                echo $this->getNormalizedAnswerObject(true, $result);
                break;

            default:
                throw new CHttpException(501, 'Merging procedure of type \'' . $type . '\' is not implemented');
                break;


        }
    }


//endregion
}
