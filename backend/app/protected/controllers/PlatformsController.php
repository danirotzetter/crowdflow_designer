<?php
/**
 * This class serves as a mediator between the WebApplication and the crowd-sourcing platforms.
 * Each call from/ for the WebApplication will be delegated through this controller.
 * Any call to a crowd-sourcing platform API is dynamically delegated to the appropriate component that implements the functionality. For example, when MTurk is used, a call to get the account balance
 * addresses the 'getAccountBalance()' method of the component 'MTurk', which implements the access to MTurk API.
 * Class PlatformsController
 */
class PlatformsController extends CsController
{
    //region Platform management functions
    /**
     * Gets the account balance for the currently selected platform
     */
    public function actionAccountBalance()
    {
        Yii::log('Getting account balance', 'debug', 'PlatformsController');
        $comp = Helper::getCurrentPlatformComponent();
        $result = $comp->getAccountBalance();
        $this->preparePlatformCallResult($result);
    }

    /**
     * Get all tasks that are crowd-sourced on the current platform
     */
    public function actionAllTasks()
    {
        Yii::log('Getting all tasks', 'debug', 'PlatformsController');
        $comp = Helper::getCurrentPlatformComponent();
        $result = $comp->getAllTasks();
        Yii::log('Got result ' . print_r($result, true), 'debug', 'PlatformsController');
        $this->preparePlatformCallResult($result);
    }

    /**
     * Delete all tasks that are crowd-sourced on the current platform
     * @param bool $onlyReferenced If set to TRUE, only those tasks on the platforms are deleted to which a reference from any WebApp-item exists. If set to FALSE, EVERY published task on the platform is deleted, including the tasks that were not created by this WebApplication
     * @param bool $returnRaw Indicates whether or not the publish result should be returned as raw. If true, then the result is returned as a default answer object (i.e. as an array with 'success', 'data' and 'errors' keys).
     *  Otherwise, the result item is presented as required for an external request (i.e. a JSON result with the required header information)
     * @return array The result of the deletion operation, if required so (i.e. if $returnRaw==true)
     */
    public function actionDeleteAllTasks($onlyReferenced = true, $returnRaw=false)
    {
        $onlyReferenced = ($onlyReferenced === true || $onlyReferenced === 'true');
        Yii::log('About to delete all tasks that are found in the WebApplication items - ' .
        ($onlyReferenced ? ' skipping tasks that are not found in models' : 'and then delete all tasks including the ones not from this WebApplication'), 'debug', 'PlatformsController');

        if(!Yii::app()->params['sandboxMode']){
            // This feature is disabled in non-sandbox mode
            $msg = 'For security reasons, the tasks on the crowd are not deleted in non-sandbox mode';
        Yii::log($msg, 'info', 'PlatformsController');
            $result = array('success'=>true,'data' => $msg, 'errors' => array());
            $this->preparePlatformCallResult($result);
        }

        $comp = Helper::getCurrentPlatformComponent();
        $result = array('data' => '', 'errors' => array());

        // Get all published tasks
        $allItems = FlowManagement::getAllItems();
        $platformTasks = array();
        foreach ($allItems as $task) {
            if(FlowManagement::isCrowdsourced($task))
                $platformTasks[] = $task;
        }

        $nbOrig = sizeof($platformTasks);
        Yii::log('Deleting ' . $nbOrig . ' tasks on the crowd', 'debug', 'PlatformsController');
        $tasksDeleted = array();

        // Then, delete each task separately
        foreach ($platformTasks as $platformTask) {
            $taskDeleteResult = $comp->deleteTask($platformTask);
            if ($taskDeleteResult['success']) {
                // Deletion successful for this HITId
                $tasksDeleted=array_merge($tasksDeleted, $taskDeleteResult['data']);
            } else {
                // Deletion failure for this HITId
                $errors = $taskDeleteResult['errors'];
                $errorMsg = 'Could not delete a task: ' . ($errors != NULL ? join(', ', $errors) : 'an undefined error occurred');
                $result['errors'][] = $errorMsg;
                Yii::log($errorMsg, 'warning', 'PlatformsController');
            }
        }

        // Prepare success message
        $msg = 'Deleted the '.sizeof($tasksDeleted) . ' published tasks from the ' . $nbOrig . ' crowd-sourced tasks';
        Yii::log($msg, 'debug', 'PlatformsController');
        $result['data'] = $msg;

        // Finally delete all non-webApplication tasks, if desired so
        if (!$onlyReferenced) {
            Yii::log('Also deleting tasks that were not published by this WebApplication', 'PlatformsController');
            $deleteAllNonWebAppTasksResult = $comp->deleteAllTasks();
            if ($deleteAllNonWebAppTasksResult['success']) {
                $result['data'] .= ', ' . $deleteAllNonWebAppTasksResult['data'];
            } else {
                $result['errors'] = array_merge($result['errors'], $deleteAllNonWebAppTasksResult['errors']);
            }
        }
        // End delete all non-webApplication tasks

        $result['success'] = sizeof($result['errors']) == 0;

        // Process the result of the operation as required
       if($returnRaw)
           return $result;
        else
            $this->preparePlatformCallResult($result);
    }


    /**
     * Deletes all tasks on the crowd-sourcing platform and removes all flow information from all items in the application
     */
    public function actionResetAllTasks()
    {

        $items = FlowManagement::getAllItems();

        // Also include the workspaces
        $workspaces = Workspace::model()->findAll();
        $items = array_merge($items, $workspaces);

        Yii::log('Resetting '.sizeof($items).' items', 'debug', 'PlatformsController');

        foreach ($items as $item) {
            $save = array();
            if ($item->hasAttribute('platform_data')) {
                $item->platform_data = null;
                $save[] = 'platform_data';
            }
            if ($item->hasAttribute('input_queue')) {
                $item->input_queue = null;
                $save[] = 'input_queue';
            }
            if ($item->hasAttribute('processed_flowitems')) {
                $item->processed_flowitems = null;
                $save[] = 'processed_flowitems';
            }
            $item->saveAttributes($save);
        }


        $this->preparePlatformCallResult(array('success' => true, 'data' => 'All tasks reset'));
    }

    //endregion Platform management functions


    //region Flow management functions
    /**
     * Executes a 'step' in the data flow: processes all items
     * @param bool $forceReload Forces reloading all items after each step. This is a very costly operation,
     * but has the advantage that the subsequent method is called with up-to-date data.
     * For example, if in a step, the input queue of an item is modified, and the $forceReload parameter is set to FALSE,
     * then the subsequent processing of that very input queue will be based on the old, possibly empty queue, whereas $forceReload=TRUE
     * leads to a re-load of the data (i.e. the input queue) and thus the method would rely on the up-to-date data (and hence could process
     * the newly added item immediately). The workaround of setting this value to  FALSE is to call the actionUpdateFlow method more regularly.
     *
     */
    public function actionUpdateFlow($forceReload = false)
    {
        // Be careful - must be synchronized with UpdateFlowAction->run()!

        try {
            Yii::log('UpdateFlow: Updating the flow of all items, forcing reload? ' . ($forceReload ? 'true' : 'false'), 'debug', 'PlatformsController');

            /*
             * Each time an item is fetched from the database, some operations are executed (like getting crowd-sourcing data etc.).
             * In order to not execute these methods several times, in this method, all items are retrieved at once and then used
             * in the subsequent operations.
             */
            $items = FlowManagement::getAllItems();
            $errors = array(); // Keeping track of all errors that occurred

            // Automatically accept assignments that are not validated
            Yii::log('UpdateFlow: About to accept non-postprocessed crowd answers', 'debug', 'PlatformsController');
            $autoAcceptedAssignments = FlowManagement::acceptNonValidatedItems($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();

            // Forward crowd-sourced assignments that require validation to the validation item
            Yii::log('UpdateFlow: Forwarding crowd-sourced assignments to validators', 'debug', 'PlatformsController');
            $assignmentsForwardedToValidators = FlowManagement::forwardAssignmentsToValidators($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();

            // Accept and reject assignments that were validated by the crowd
            Yii::log('UpdateFlow: About to accept and reject crowd-validated assignments', 'debug', 'PlatformsController');
            $crowdValidatedAssignments = FlowManagement::processAssignmentValidations($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();


            // Forward crowd-sourced items to the input queue of the following item
            Yii::log('UpdateFlow: Now forwarding the crowd-sourced items', 'debug', 'PlatformsController');
            /*
             * Optionally, the first argument could contain the variable $autoAcceptedAssignments. In this case, only the assignments that were accepted
             * within this step/ this method call would be forwarded to the input queue of the subsequent item. When doing so, however, any assignment that
             * could not be added to the input queue would be 'lost' as in the next method call, that very assignment would not be treated again. This is
             * why it is advised to submit NULL, which forces the method to fetch ALL assignments from the crowd-sourcing platform, instead of just the assignments
             * that were approved in this call.
             */
            $forwardedFlowItems = FlowManagement::forwardAcceptedCrowdSourceResultsToSubsequentItem(null, $items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();

            // Re-activate or deactivate items that have become invalid due to an empty queue
            Yii::log('UpdateFlow: About to enable tasks on the crowd-sourcing platform, depending on their input queue', 'debug', 'PlatformsController');
            $enabledTasks = FlowManagement::enableTasksWithInputQueue($items, $errors);


            // Process the input queue for items that are not crowd-sourced
            Yii::log('UpdateFlow: About to process input-queue items', 'debug', 'PlatformsController');
            $processedInputQueue = FlowManagement::processNonCsInputQueue($items, $errors);

            Yii::log('UpdateFlow: All operations completed', 'debug', 'PlatformsController');

            // Log the result
            $autoAcceptedAssignmentsText = '';
            $analysisAutoAcceptedAssignmentsText= '';
            foreach($autoAcceptedAssignments as $assignment){
                $autoAcceptedAssignmentsText.='assignment '.$assignment['platformResultId'].' for '.$assignment['itemId'].' of type '.$assignment['itemType'].'; ';
                $analysisAutoAcceptedAssignmentsText.=$assignment['itemType'].$assignment['itemId'].'('.$assignment['platformResultId'].'),';
            }

            $assignmentsForwardedToValidatorsText = '';
            foreach($assignmentsForwardedToValidators as $assignment){
                $assignmentsForwardedToValidatorsText.='assignment '.$assignment['platformResultId'].' for '.$assignment['itemId'].' of type '.$assignment['itemType'].'; ';
            }
            $assignmentsApprovedByValidatorsText = '';
            $analysisApprovedByValidatorsText= '';
            foreach($crowdValidatedAssignments['approve'] as $assignment){
            $validatedAssignmentId = $assignment['validatedAssignmentId'];
            $subsequentItem = $assignment['subsequentItem'];
            $flowItemId = $assignment['flowItemId'];
                $assignmentsApprovedByValidatorsText.='assignment '.$validatedAssignmentId.' of flowItemId '.$flowItemId.' for '.$subsequentItem->getItemInfo().'; ';
            $analysisApprovedByValidatorsText.=strtolower(get_class($subsequentItem)).$subsequentItem->id.'('.$validatedAssignmentId.'),';
            }
            $assignmentsRejectedByValidatorsText = '';
            $analysisRejectedByValidatorsText= '';
            foreach($crowdValidatedAssignments['reject'] as $assignment){
            $validatedAssignmentId = $assignment['validatedAssignmentId'];
            $subsequentItem = $assignment['subsequentItem'];
            $flowItemId = $assignment['flowItemId'];
                $assignmentsRejectedByValidatorsText.='assignment '.$validatedAssignmentId.' of flowItemId '.$flowItemId.' for '.$subsequentItem->getItemInfo().'; ';
            $analysisRejectedByValidatorsText.=strtolower(get_class($subsequentItem)).$subsequentItem->id.'('.$validatedAssignmentId.'),';
            }
            $forwardedFlowItemsText= '';
            foreach($forwardedFlowItems as $forwardedFlowItem){
                $forwardedFlowItemsText.='flowItemId '.$forwardedFlowItem['flowItemId'].' for item '.$forwardedFlowItem['itemId'].' of type \''.$forwardedFlowItem['itemType'].'\'; ';
            }
            $itemsEnabledText = '';
            $analysisItemsEnabledText = '';
            foreach($enabledTasks as $enabledTask){
                $itemsEnabledText.=$enabledTask->getItemInfo().'; ';
            $analysisItemsEnabledText.=strtolower(get_class($enabledTask)).$enabledTask->id.',';
            }
            $processedInputQueueItemsText='';
            foreach($processedInputQueue as $inputQueueItem){
                if(array_key_exists('itemId', $inputQueueItem)&& array_key_exists('itemType', $inputQueueItem))
                    $processedInputQueueItemsText.='related to item '.$inputQueueItem['itemId'].' of type '.$inputQueueItem['itemType'].'; ';
                if(array_key_exists('platformResultId', $inputQueueItem)){
                    if($processedInputQueueItemsText!='')
                        $processedInputQueueItemsText.=', ';
                    $processedInputQueueItemsText.='assignment '.$inputQueueItem['platformResultId'].'; ';
                }
                if(array_key_exists('flowItemId', $inputQueueItem)){
                    if($processedInputQueueItemsText!='')
                        $processedInputQueueItemsText.=', ';
                    $processedInputQueueItemsText.='flowItemId '.$inputQueueItem['flowItemId'].'; ';
                }
            }


            $res = array(
                'nonValidatedAcceptedAssignments'=>array(
                    'total'=>sizeof($autoAcceptedAssignments),
                    'text'=>$autoAcceptedAssignmentsText,
                    'label'=>'Assignments requiring no validation that were automatically accepted',
                ),
                'assignmentsSentToValidators'=>array(
                    'total'=>sizeof($assignmentsForwardedToValidators),
                    'text'=>$assignmentsForwardedToValidatorsText,
                    'label'=>'Assignments that were submitted by the crowd were forwarded to their validators',
                ),
                'assignmentsApprovedByValidators'=>array(
                    'total'=>sizeof($crowdValidatedAssignments['approve']),
                    'text'=>$assignmentsApprovedByValidatorsText,
                    'label'=>'Assignments that were approved as a result of a validator postprocessor',
                ),
                'assignmentsRejectedByValidators'=>array(
                    'total'=>sizeof($crowdValidatedAssignments['reject']),
                    'text'=>$assignmentsRejectedByValidatorsText,
                    'label'=>'Assignments that were rejected as a result of a validator postprocessor',
                ),
                'itemsForwarded'=>array(
                    'total'=>sizeof($forwardedFlowItems),
                    'text'=>$forwardedFlowItemsText,
                    'label'=>'FlowItems that were treated from the inputQueue and forwarded to the subsequent item in the flow',
                ),
                'itemsEnabled'=>array(
                    'total'=>sizeof($enabledTasks),
                    'text'=>$itemsEnabledText,
                    'label'=>'Crowd-sourced items that were enabled because their input queue is no longer empty and they are ready for the crowd',
                ),
                'processedInputQueueItems'=>array(
                    'total'=>sizeof($processedInputQueue),
                    'text'=>$processedInputQueueItemsText,
                    'label'=>'Input queue items which are not crowd-sourced that were processed',
                ),
            );

            // Concatenate all information got so far
            $allMessages=array();
            foreach($res as $resPart){
                $allMessages[]=$resPart['label'].': '.$resPart['total'].' in total ('.$resPart['text'].')';
            };
            $msg=join(', ', $allMessages);
            Yii::log($msg, 'info', 'PlatformsController');

            //region Analysis
            // Log for analysis purposes
            if($analysisItemsEnabledText!=''){
                Yii::log('published:'.$analysisItemsEnabledText, 'info', 'Analysis');
            }
            if($analysisApprovedByValidatorsText!=''){
                Yii::log('approvedByValidator:'.$analysisApprovedByValidatorsText, 'info', 'Analysis');
            }
            if($analysisRejectedByValidatorsText!=''){
                Yii::log('approvedByValidator:'.$analysisRejectedByValidatorsText, 'info', 'Analysis');
            }
            if($analysisAutoAcceptedAssignmentsText!=''){
                Yii::log('approvedAutomatically:'.$analysisAutoAcceptedAssignmentsText, 'info', 'Analysis');
            }
            //endregion

            // Prepare the result of the action
            echo $this->getNormalizedAnswerObject(true, $msg, $errors);
        } catch (Exception $e) {
            $msg = 'Could not forward crowd-sourced task results: ' . $e->getMessage() . '. Stack trace: ' . $e->getTraceAsString();
            echo $this->getNormalizedAnswerObject(false, null, $msg);
        }
    }

    //endregion Flow management functions


    //region Task-specific functions

    /**
     * Deletes a task on the platform
     * @param null $platformTaskIdentifier
     */
    public function actionDeleteTask($platformTaskIdentifier)
    {
        Yii::log('About to delete ' . $platformTaskIdentifier, 'debug', 'PlatformsController');
        $comp = Helper::getCurrentPlatformComponent();
        $result = $comp->deleteTask($platformTaskIdentifier);
        $this->preparePlatformCallResult($result);
    }

    /**
     * Get current task information
     * @param null $platformTaskIdentifier
     * @throws CHttpException
     */

    public function actionTaskExecutionInformation($platformTaskIdentifier)
    {
        Yii::log('About get task execution information for ' . $platformTaskIdentifier, 'debug', 'PlatformsController');

        $comp = Helper::getCurrentPlatformComponent();
        $result = $comp->getExecutionInformation($platformTaskIdentifier);
        $this->preparePlatformCallResult($result);
    }

    /**
     * Publishes the task for ALL currently pending flowItemIds
     * @param $itemId
     * @param $itemType
     * @param int $publishCount
     */
    public function actionPublishTask($itemId, $itemType, $publishCount=1)
    {
        $model = $this->getModel($itemId, $itemType);
        Yii::log('About to explicitly publish task ' . $model->getItemInfo(), 'debug', 'PlatformsController');

        $comp = Helper::getCurrentPlatformComponent();
        $publishedTasks = array();

        // Loop through the inputQueue in order to publish the task for each of the pending items
        $iq = $model->input_queue;
        if(is_string($iq))
            $iq =json_decode($iq, true);
        $iqCount = sizeof($iq);
        Yii::log('About to publish task ' . $model->getItemInfo().' for all of its '.$iqCount.' pending inputQueue items', 'debug', 'PlatformsController');
        foreach($iq as $inputQueueItem){
            // Must have a flowItemId
            $flowItemId=null;
        if(!array_key_exists('flowItemId', $inputQueueItem)){
                // Search for the flowItemId in the fields list
            if(array_key_exists('data', $inputQueueItem) && is_array($inputQueueItem['data'])){
            foreach($inputQueueItem['data'] as $inputQueueItemField)
                if($inputQueueItemField['id']=='flowItemId')
                    $flowItemId=$inputQueueItemField['value'];
            }
            if($flowItemId==null)
        Yii::log('Cannot publish a task - no flowItemId found for an inputQueueItem of '.$model->getItemInfo().': skipping this item', 'error', 'PlatformsController');
            continue;
        }
            else{
                // FlowItemId directly accessible
            $flowItemId=$inputQueueItem['flowItemId'];
            }
            $publishTaskResult = $comp->publishTask($model, $flowItemId, $publishCount);
            if($publishTaskResult['success'])
                $publishedTasks[]=$publishTaskResult['data'];
        }// End for each inputQueueItem
        $this->preparePlatformCallResult(array('result'=>true, 'data'=>sizeof($publishedTasks).' tasks were published on the crowd'));
    }

    //endregion Task-specific functions


    //region Review-specific functions
    /**
     * Validates an assessment
     * @param $assignmentId The assignment to validate
     * @param $approve Whether the assessment should be approved
     * @param null $message
     * @return array
     */
    public function actionValidateAssignment($assignmentId, $approve, $message = NULL)
    {
        Yii::log('Validate assessment ' . $assignmentId . ': approve? ' . ($approve ? 'true' : 'false') . ' ' . ($message != NULL ? 'With message \"' . $message . '"' : ' Without any message'), 'debug', 'PlatformsController');

        if ($assignmentId == null) {
            $this->sendError(500, 'Must provide assignment id');
        }
        if ($approve == null) {
            $this->sendError(500, 'Must provide parameter to approve or reject the assignment');
        }
        if (!is_numeric($assignmentId)) {
            $this->sendError(500, 'Must provide assignment id as integer - received \'' . $assignmentId . '\'');
        }


        $comp = Helper::getCurrentPlatformComponent();
        $result = $comp->validateAssignment($assignmentId, $approve, $message);
        $this->preparePlatformCallResult($result);
    }


    /**
     * Get the task results for a HIT
     * @param $platformTaskId The model or HIT id
     * @param $assignmentStatus Filter the reviews based on their status. Possible values are: 'Submitted', 'Approved', 'Rejected'. If not set, all assignments are fetched
     * @throws CHttpException
     */
    public function actionTaskResults($platformTaskId, $assignmentStatus = NULL)
    {
        Yii::log('Get task results with assignmentStatus ' . $assignmentStatus, 'debug', 'PlatformsController');

        $comp = Helper::getCurrentPlatformComponent();

        $tasksResults = $comp->getTaskResults($platformTaskId, $assignmentStatus);

        $methodsResult = NULL;
        if ($tasksResults['success']) {
            // Parse the results
            $methodsResult['success'] = true;

                Yii::log('Got ' . sizeof($tasksResults['data']) . ' answers - cannot parse them. To parse the results, the task\'s id must be provided instead of the platform-specific id', 'info', 'PlatformsController');
                $methodsResult['data'] = $tasksResults['data'];
        } else {
            // Error happened - cannot further treat results
            $methodsResult = $tasksResults;
        }

        $this->preparePlatformCallResult($methodsResult);
    }


    //endregion Review-specific functions

    //region Platform-Controller specific helper functions


    /**
     * Prepares the answer to the platform-specific API call. Meaning: checks if there are errors, and if so, send the corresponding header. Then, display the data in a readable JSON format, such
     *that the consumer/ the frontend may process the message
     * @param $result
     */
    private function preparePlatformCallResult($result)
    {
        Yii::log('About to prepare result ' . print_r($result, true), 'debug', 'PlatformsController');
        $success = $result['success'];
        if ($success) {
            // Call is successful
            $this->prepareHeader(200);
        } else {
            // Call was not successful
            if (array_key_exists('errors', $result) && sizeof($result['errors']) > 0) {
                // Error details available
                $errors = $result['errors'];
            } else {
                // Error details not available
                $errors = array('Error not further specified');
            }

            // Errors must be array to add another error message
            if ($errors == NULL)
                $errors = array();
            if ($errors != NULL && !is_array($errors))
                $errors = array($errors);

            //Push a default message on top of the errors list
            array_unshift($errors, 'API call to the crowd-sourcing platform has failed');
            $result['errors'] = $errors;
        }
        // Set empty values in case nothing was returned by the query
        if (!array_key_exists('data', $result)) {
            $result['data'] = array();
        }
        if($success)
        echo $this->getNormalizedAnswerObject($success, $result['data']);
        else
        echo $this->getNormalizedAnswerObject($success, $result['data'], $result['errors']);
    }


    /**
     * Returns the model that is either identified through id and type or through the identifier of a platformTask that was published for the item
     * @param $itemId The integer identifying the item in this web application
     * @param $itemType The type indicating what kind of item that should be found
     * @return int|null|string The web-app model instance, if the web-app task id is given. Otherwize the platform related task identifier (E.g. the HIT id for Amazon MTurk). If no valid parameter is supplied, NULL is returned.
     */
    private function getModel($itemId, $itemType)
    {
        $identifier = null;
        if ($itemId == null || $itemType==null) {
            $this->sendError(500, 'Must provide the web application\'s item id and type');
        }
            // Use a webapp item as identifier
            if (!is_numeric($itemId)) {
                $this->sendError(500, 'Must provide task id as integer - received \'' . $itemId . '\'');
                return null;
            } else if ($itemType == NULL) {
                $this->sendError(500, 'Must provide the type of the item with id \'' . $itemId . '\' such that the corresponding model can be retrieved from the database');
                return null;
            } else {
                $model = NULL;
                $itemInfo = 'item ' . $itemId . ' of type \'' . $itemType . '\'';
                $eval = '$model = ' . ucfirst($itemType) . '::model()->findByPk(' . $itemId . ');';
                Yii::log('About to find the model for ' . $itemInfo . ' by evaluating the expression \'' . $eval . '\'', 'debug', 'PlatformController');
                eval($eval);
                if ($model == NULL) {
                    $this->sendError(404, 'Cannot find task with the given id: \'' . $itemId . '\'');
                } else {
                    Yii::log('GetModel has returned the model ' . print_r($model->attributes, true), 'debug', 'PlatformsController');
                    return $model;
                }
                // End retrieved model is not null
            }
            // End parameters for webapp identifier are valid
    }

    //endregion Platform-Controller specific helper functions


}
