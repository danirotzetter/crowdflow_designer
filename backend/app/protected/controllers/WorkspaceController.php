<?php

class WorkspaceController extends CsController
{

    private $defaultLoads = array('macrotask', 'user');


    /**
     * Displays a particular model.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionView($id)
    {
        $model = $this->loadModel($id);
        $data = $model->attributes;
        $data = $this->parsePosition($data);
        $model->attributes=$data;
        $this->apiView($model, $this->defaultLoads);
    }


    /**
     * Get the final results for the workspace
     * @param $id
     *@param string $clean Whether the data should be cleaned, i.e. only the 'final' results should be returned (as opposed to the result object, containing metadata and flowItem references etc.)
     */
    public function actionResults($id, $clean='false')
    {

        // Parse boolean parameter
        $clean = filter_var($clean, FILTER_VALIDATE_BOOLEAN);

        Yii::log('Preparing the results for workspace ' . $id.', cleaned? '.($clean? 'true':'false'), 'debug', 'WorkspaceController');
        $model = $this->loadModel($id);

        $errors = array();

        $metadata = $this->getFlowMetadata($model, $errors);

        $input_queue = $model->input_queue;

        $cleaned=array();
        if($clean){
            // Do only take the 'data' attribute of the inputQueue items
            if(is_string($input_queue))
                $input_queue=json_decode($input_queue, true);
            foreach($input_queue as $inputQueueItem){
                if(array_key_exists('data', $inputQueueItem)){
                    $data=$inputQueueItem['data'];
                    // If the data itself is already 'clean' (i.e. a string or a numeric value), then we can directly use it
                if(is_string($data) || is_numeric($data)){
                    Yii::log('Can use the string or numeric data directly: '.$data, 'debug', 'WorkspaceController');
                    $cleaned[]=$data;
                }
                    else if(is_array($data)){
                        Yii::log('Looking for resultfield in '.print_r($data, true), 'debug', 'WorkspaceController');
                        /*
                         * Else, the item consists of form fields. In order to find the 'actual' value,
                         * we will look for the 'original' field or the 'resultfield' indication to detect the actual to-be-used data field.
                         * Check the Merger class for more explanations
                         */
                        // Find the resultfield
                        $resultfield=null;
                        foreach($data as $field){
                            if($field['id']=='resultfield'){
                                $resultfield=$field['value'];
                            }
                        }
                        if ($resultfield!=null) {
                            $resultfieldValue=null;
                            $originalfieldValue=null;
                            foreach($data as $field){
                                if($field['id']==$resultfield){
                                    $resultfieldValue=$field['value'];
                                }
                                else if($field['id']=='original'){
                                    $originalfieldValue=$field['value'];
                                }
                            }
                            if($originalfieldValue!=null){
                                // Everything is okay
                                Yii::log('Reading data - using the \'original\' field', 'debug', 'WorkspaceController');
                                $cleaned[]=$originalfieldValue;
                            }
                            else if ($resultfieldValue!=null) {
                                // Everything is okay
                                Yii::log('Reading data - using the \'resultfield\' field (\''.$resultfield.'\')', 'debug', 'WorkspaceController');
                                $cleaned[]=$resultfieldValue;
                            } else {
                                // Invalid process - supplied 'resultfield' field is not available
                                $msg = 'An item\'s data does not have a field with resultfield id \'' . $resultfield . '\'';
                                Yii::log($msg, 'warning', 'Merger');
                                $errors[] = $msg;
                        continue;
                            }
                        } else {
                            // Invalid process - supplied no 'resultfield' field
                            $msg = 'The item has an array as \'data\' attribute, but there is no field with id \'resultfield\' (whose value must be of type \'string\' or \'numeric\') submitted in the data array that can be used in order to execute the majorty voting';
                            Yii::log($msg, 'warning', 'Merger');
                            $errors[] = $msg;
                        continue;
                        }
                    }// End data is array
                    else{
                        // Invalid format
                        $msg = 'Cannot clean data: has an invalid format';
                        Yii::log($msg, 'error', 'WorkspaceController');
                        $errors[]=$msg;
                        continue;
                    }
                }
            }
        }

        $result = array(
            'flowData' => $metadata,
            'results' => $clean? $cleaned:$input_queue
        );
        Yii::log('Got the results for ' . $model->getItemInfo() . ': ' . print_r($result, true), 'debug', 'WorkspaceController');

        echo $this->getNormalizedAnswerObject(sizeof($errors) == 0, $result, $errors);
    }

    /**
     * @param $model The workspace model for which the metadata should be returned
     * @param $errors The array where errors are stored
     * @return array The array of metadata information
     */
    public function getFlowMetadata($model, &$errors)
    {

        // Get all items associated with this workspace
        $tasks = $this->getItems($model->id, 'Task', true);
        $mergers = $this->getItems($model->id, 'Merger', true);
        $splitters = $this->getItems($model->id, 'Splitter', true);
        $postprocessors = $this->getItems($model->id, 'Postprocessor', true);
        $datasources = $this->getItems($model->id, 'Datasource', true);


        // Perform some crowd-source counting
        $csItemsTotalCount = 0;
        $csItemsRunningCount = 0;
        $rewards = array();
        /**
         * Keeps track of the number of submitted assignments per reward amount. E.g. 0.10 => ('expected'=>10, 'total'=>5, 'approved'=>3, 'pending'=>1) means that ten assignments for the amount of 0.1 are expected, 5 were already processed, out of which 3 were paid and 1 was not paid; 1 assignment is still pending
         */
        foreach (array($tasks, $mergers, $splitters, $postprocessors, $datasources) as $typeItems) {
            Yii::log('Iterating over ' . sizeof($typeItems) . ' items', 'debug', 'WorkspaceController');
            foreach ($typeItems as $item) {
                Yii::log('Get data about ' . $item->getItemInfo(), 'debug', 'WorkspaceController');
                if (FlowManagement::isCrowdsourced($item)) {
                    // Fetch crowd-sourcing related data
                    try {
                        $pd = $item->platform_data;
                        $parameters = $item->parameters;

                        Yii::log('Analyzing '.$item->getItemInfo().': has platform data '.print_r($pd, true).', parameters '.print_r($parameters, true), 'debug', 'WorkspaceController');


                        $reward = $parameters['reward'];
                        if(!is_numeric($reward)){
                          // Invalid reward - not a number
                            throw new UnexpectedValueException('Invalid format for reward: \''.$reward.'\'');
                        }
                        else{
                            // Must stringify the variable in order to use it in an associative array
                            $reward=Helper::$NUM_PREFIX.$reward;
                        }

                        // Keep track of the running tasks
                        $tasksRunning=array_key_exists('tasks', $pd)? sizeof($pd['tasks']):0;
                        $csItemsRunningCount +=$tasksRunning;

                        // Make the breakdown of the assignments
                        $rejectedAssignments=array_key_exists('rejectedAssignments', $pd)? $pd['rejectedAssignments']:array();
                        $acceptedAssignments=array_key_exists('acceptedAssignments', $pd)?$pd['acceptedAssignments']:array();
                        $pendingAssignments=array_key_exists('pendingAssignments', $pd)?$pd['pendingAssignments']:array();

                        $totalCount=0;
                        $acceptedCount=0;
                        $pendingCount=0;
                        $rejectedCount=0;
                        foreach($rejectedAssignments as $assignmentsPerFlowItemId){
                            foreach($assignmentsPerFlowItemId as $flowItemId=>$assignments){
                                $totalCount+=sizeof($assignments);
                                $rejectedCount+=sizeof($assignments);
                            }
                        }
                        foreach($pendingAssignments as $assignmentsPerFlowItemId){
                            foreach($assignmentsPerFlowItemId as $flowItemId=>$assignments){
                                $totalCount+=sizeof($assignments);
                                $pendingCount+=sizeof($assignments);
                            }
                        }
                        foreach($acceptedAssignments as $assignmentsPerFlowItemId){
                            foreach($assignmentsPerFlowItemId as $flowItemId=>$assignments){
                                $totalCount+=sizeof($assignments);
                                $acceptedCount+=sizeof($assignments);
                            }
                        }

                        /*
                         * Along with the minimum number of accepted results (min_assignments), we have to take into account the rejected assignments which will not be part of the valid results
                         */
                        $expectedCount = $parameters['min_assignments']+$rejectedCount;
                    } catch (Exception $ex) {
                        // Unable to process this item - maybe invalid parameters were indicated. Skip this item and continue with the next one
                        $msg = 'Cannot create metadata - unable to create statistics for ' . $item->getItemInfo() . ': ' . $ex->getMessage();
                        Yii::log($msg, 'warning', 'WorkspaceController');
                        $errors[] = $msg;
                        continue;
                    }


                    Yii::log('Handling reward '.print_r($reward, true), 'debug', 'WorkspaceController');
                    // Add the fetched data to the array that keeps track of all crowd-sourcing data
                    if (!array_key_exists($reward, $rewards)) {
                        // This payment amount was not yet in the array
                        $rewards[$reward] = array(
                            'expected' => $expectedCount,
                            'total' => $totalCount,
                            'approved' => $acceptedCount,
                            'pending' => $pendingCount
                        );
                    } else {
                        // This payment amount was already in the array: increment the corresponding counters
                        $rewards[$reward]['expected'] += $expectedCount;
                        $rewards[$reward]['total'] += $totalCount;
                        $rewards[$reward]['approved'] += $acceptedCount;
                        $rewards[$reward]['pending'] += $pendingCount;
                    }

                    // Update the overall counters
                    $csItemsTotalCount++;
                }
                // End item is crowd-sourced
            }
            // End for each item
        }
        // End for each item type


        /**
         * Perform the calculations for assignments and rewards
         */
        $csAssignmentsExpectedCount = 0;
        $csAssignmentsTotalCount = 0;
        $csAssignmentsApprovedCount = 0;
        $csAssignmentsPendingCount = 0;
        $csRewardsExpectedActual = 0;
        $csRewardsTotalActual = 0;
        foreach ($rewards as $key => $value) {
            // Re-float the stringified key for calculation purposes
            $substring = substr($key, strlen(Helper::$NUM_PREFIX));
            Yii::log('Calculating with substring '.$substring, 'debug', 'WorkspaceController');
            $key = floatval($substring);
            Yii::log('Calculating with payment '.$key, 'debug', 'WorkspaceController');
            $expectedCount = $value['expected'];
            $totalCount = $value['total'];
            $approvedCount = $value['approved'];
            $pendingCount = $value['pending'];
            // Assignments count
            $csAssignmentsExpectedCount += $expectedCount;
            $csAssignmentsTotalCount += $totalCount;
            $csAssignmentsApprovedCount += $approvedCount;
            $csAssignmentsPendingCount += $pendingCount;

            // Payments
            $csRewardsTotalActual += ($key * $totalCount);
            $csRewardsExpectedActual += ($key * $expectedCount);
        }
        // Calculate the average
        $csRewardsExpectedAvg = $csAssignmentsExpectedCount==0? 0:$csRewardsExpectedActual / $csAssignmentsExpectedCount;
        $csRewardsTotalAvg = $csAssignmentsTotalCount==0? 0:$csRewardsTotalActual / $csAssignmentsTotalCount;


        // Rounding
        $csRewardsExpectedActual=round($csRewardsExpectedActual, 2);
        $csRewardsExpectedAvg=round($csRewardsExpectedAvg, 2);
        $csRewardsTotalActual=round($csRewardsTotalActual, 2);
        $csRewardsTotalAvg=round($csRewardsTotalAvg, 2);

        $metadata = array(
            'items_total_count' => array(
                'name' => 'Total items',
                'value' => sizeof($tasks) + sizeof($mergers) + sizeof($splitters) + sizeof($postprocessors) + sizeof($datasources)
            ),
            'items_task_count' => array(
                'name' => 'Number of tasks',
                'value' => sizeof($tasks)
            ),
            'items_merger_count' => array(
                'name' => 'Number of mergers',
                'value' => sizeof($mergers)
            ),
            'items_splitter_count' => array(
                'name' => 'Number of splittters',
                'value' => sizeof($splitters)
            ),
            'items_postprocessor_count' => array(
                'name' => 'Number of postprocessors',
                'value' => sizeof($postprocessors)
            ),
            'items_datasource_count' => array(
                'name' => 'Number of data sources',
                'value' => sizeof($datasources)
            ),
            'cs_running' => array(
                'name' => 'Number items currently running (active) on the crowd-sourcing platform',
                'value' => $csItemsRunningCount
            ),
            'cs_count' => array(
                'name' => 'Total number items published (active and inactive) on the crowd-sourcing platform',
                'value' => $csItemsTotalCount
            ),
            'cs_assignments_expected' => array(
                'name' => 'Total number of assignments that will be published on the crowd',
                'value' => $csAssignmentsExpectedCount
            ),
            'cs_assignments_submitted' => array(
                'name' => 'Total number of assignments that were submitted on the crowd-sourcing platform',
                'value' => $csAssignmentsTotalCount
            ),
            'cs_assignments_approved' => array(
                'name' => 'Total number of assignments that were approved on the crowd-sourcing platform',
                'value' => $csAssignmentsApprovedCount
            ),
            'cs_assignments_pending' => array(
                'name' => 'Total number of assignments that are pending on the crowd-sourcing platform',
                'value' => $csAssignmentsPendingCount
            ),
            'cs_reward_expected_avg' => array(
                'name' => 'Average amount paid per assignment so far when all planned assignments are executed',
                'value' => $csRewardsExpectedAvg
            ),
            'cs_reward_total_avg' => array(
                'name' => 'Average amount paid per assignment so far',
                'value' => $csRewardsTotalAvg
            ),
            'cs_reward_expected_actual' => array(
                'name' => 'Total amount paid for assignments when all expected assignments are published and approved',
                'value' => $csRewardsExpectedActual
            ),
            'cs_reward_total_actual' => array(
                'name' => 'Total amount paid for assignments',
                'value' => $csRewardsTotalActual
            ),

        );
        // End define metadata array

        Yii::log('Got metadata for ' . $model->getItemInfo() . ': ' . print_r($metadata, true), 'debug', 'WorkspaceController');
        return $metadata;
    }

    /**
     * Get all items related to this workspace
     * @param null $itemTypes A string or array of string indicating the item types that should be fetched. E.g. 'Task' or array('Task', 'Merger')
     * @param $id The workspace id whose items should be fetched
     * @return array The webRequest result
     */
    public function actionItems($id, $itemTypes = NULL)
    {
        $models = array(); // The result array of models

        if ($itemTypes == NULL) {
            // Return ALL item types for this workspace
            $itemTypes = array('Task', 'Merger', 'Splitter', 'Postprocessor', 'Datasource');
        } else if ($itemTypes != NULL && !is_array($itemTypes)) {
            // Only one item type to return
            $itemTypes = (array)$itemTypes;
        }

        Yii::log('Fetching all items for workspace \'' . $id . '\' and item types ' . print_r($itemTypes, true), 'debug', 'WorkspaceController');

        /* Fetch all model types that were required to be returned
        * For each type, a query must be defined that specifies, how all items of the requested type can be fetched.
        * Usually, this means that all connection tables that contain this item type must be searched.
         *
         */
        foreach ($itemTypes as $itemType) {
            $modelsOfThisType = $this->getItems($id, $itemType);

            // Combine the current result array with the models of the current model type
            $models = array_merge($models, $modelsOfThisType);
        }


        // Finally, return the result
        $this->prepareHeader();
        echo $this->getNormalizedAnswerObject(true, $models);
    }

    /**
     * Get all models of the specified type that are used in the specified workspace
     * @param $workspaceId The workspace in which the items are present
     * @param $modelType The item type to be retrieved
     * @param bool $rawModels Whether the models should be returned as is. If this parameter is set to FALSE, then the readModelData() function will be called to 'fill' each model with the desired associated relations. Note that in this case, the Yii-related model functions will not be available anymore.
     * @return array The models of the modelType assigned to the workspace
     */
    private function getItems($workspaceId, $modelType, $rawModels = false)
    {
        // Generate the ActiveDataProvider that will serve us to retrieve the models
        $itemsADP = new CActiveDataProvider($modelType, array(
            'criteria' =>
            array(
                'condition' => 'workspace_id = ' . $workspaceId,
            ),
            'pagination' => false,
        ));
        $itemsOfThisType = $itemsADP->data;
        Yii::log('Generating items ADP for model \'' . $modelType . '\' with ' . sizeof($itemsOfThisType) . ' items', 'debug', 'WorkspaceController');

        if ($rawModels) {
            // Return the models unchanged
            return $itemsOfThisType;
        } else {
            // Models need further treatment: append the related models
            Yii::log('Fetched models: there are now ' . sizeof($itemsOfThisType) . ' unique models of type \'' . $modelType . '\'', 'debug', 'WorkspaceController');
            $outputModels = array();
            // For each model of the specified type: read additional data that is valid in this workspace
            foreach ($itemsOfThisType as $item) {
                $modelData = $this->readModelData($item);
                $outputModels[] = $modelData;
            }
            return $outputModels;
        }
    }


    /**
     * Resets a workspace with the specified identifier: clears the input queue of all items and truncates the processed flowItem ids
     * @param $id
     */
    public function actionReset($id)
    {
        $itemsReset = 0;
        $items = FlowManagement::getAllItems();
        foreach ($items as $item) {
            if ($item->workspace_id == $id) {
                Yii::log('Deleting input queue and processed flow items from ' . $item->getItemInfo(), 'debug', 'PlatformsController');
                $item->input_queue = '[]';
                $item->processed_flowitems = '[]';
                $item->saveAttributes(array('input_queue', 'processed_flowitems'));
                $item->input_queue=json_decode($item->input_queue, true);
                $item->processed_flowitems = json_decode($item->processed_flowitems, true);
                $itemsReset++;
            }
        }
        echo $this->getNormalizedAnswerObject(true, 'Reset the input queue and processed flowItems for ' . $itemsReset . ' items in workspace ' . $id);
    }


    /**
     * Get all connections for this workspace
     */
    public function actionConnections($id)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            Yii::log('Get connections of workspace ' . $id, 'debug', 'WorkspaceController');
            $connections = Helper::getConnection(NULL, NULL, NULL, NULL, $id);
            echo $this->getNormalizedAnswerObject(true, $connections);
        } else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            Yii::log('Delete connections of workspace ' . $id, 'debug', 'WorkspaceController');
            $connections = Helper::getConnection(NULL, NULL, NULL, NULL, $id);
            foreach ($connections as $connection) {
                $this->deleteConnection($id, $connection['sourceType'], $connection['sourceId'], $connection['targetType'], $connection['targetId']);
            }
            Yii::log('Deleted all ' . sizeof($connections) . ' connections of workspace ' . $id, 'debug', 'WorkspaceController');
            echo $this->getNormalizedAnswerObject(true, 'Deleted ' . sizeof($connections) . ' connections');
        }

    }

    /**
     * Creates a new model.
     */
    public function actionCreate()
    {
        $model = new Workspace();
        // Uncomment the following line if AJAX validation is needed
        // $this->performAjaxValidation($model);

        Yii::log('About to read json data for workspace', 'debug', 'WorkspaceController');
        $data = $this->readJsonData();
        $data = $this->parsePosition($data);
        Yii::log('Json data read for workspace', 'debug', 'WorkspaceController');
        $model->attributes = $data;
        Yii::log('Json data set to model for workspace: ' . print_r($model->attributes, true), 'debug', 'WorkspaceController');

        if (!$model->validate()) {
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        } else {
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
        $data = $this->parsePosition($data);
        $model->attributes = $data;

        if (!$model->validate()) {
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        } else {
            $success = $model->save();
            $this->apiUpdate($model, $success);
        }
    }


    /**
     * Entry point for 'general' rest functions of workspaces
     */
    public function actionIndex()
    {
        // List all workspaces
        $dataProvider = new CActiveDataProvider('Workspace');
        $this->apiIndex($dataProvider->getData(), $this->defaultLoads);
    }


    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Workspace the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = Workspace::model()->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }

}
