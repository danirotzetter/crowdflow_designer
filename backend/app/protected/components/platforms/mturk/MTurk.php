<?php
class MTurk extends CApplicationComponent
{

    private $tools;

    /**
     * Initializes the component
     */
    public function init()
    {
        Yii::log('Initializing MTurk component - class exists? ' . ((class_exists('MTurkApiTools')) ? 'true' : 'false'), 'debug', 'MTurk');
        $this->tools = new MTurkApiTools();
        Yii::log('Initializing MTurk component completed', 'debug', 'MTurk');
    }


    //region Platform-related operations

    //region Helper functions
    public function getFormBaseUrl()
    {
        return $this->tools->getFormBaseUrl();
    }

    //endregion Helper functions

    /**
     * Get the currently available balance on the crowd-sourcing platform
     * @return mixed
     */
    public function getAccountBalance()
    {
        Yii::log('About to get balance', 'debug', 'MTurk');
        $result = $this->tools->sendRequest('GetAccountBalance');
        if ($result['success']) {
            $result['data'] = $result['data']['GetAccountBalanceResult']['AvailableBalance']['FormattedPrice'];
            return $result;
        } else
            return $result;
    }


    /**
     * Get all tasks that are currently published on this crowd-sourcing platform
     * @return array
     */
    public function getAllTasks()
    {
        Yii::log('About to get all hits', 'debug', 'MTurk');
        $parameters = array();
        $parameters['Operation'] = 'SearchHITs';
        $parameters['SortProperty'] = 'Title'; // 'Title, Reward, Expiration, CreationTime, Enumeration' (Enumeration guarantees no duplicates, but does not order the tasks - use it if there are more than 100 results)
        $parameters['SortDirection'] = 'Ascending'; // 'Ascending, Descending'
        $parameters['PageSize'] = 100; // 10-100
        $parameters['PageNumber'] = 1;
        $parameters['ResponseGroup'] = 'HITDetail'; // Get more detailed HIT information

        $allHits = array(); // Keeps track of all hits that were fetched so far
        $totalHitsCount = -1; // The total number of hits that must be fetched


        while ($totalHitsCount < sizeof($allHits)) {
            // Get the next bunch of hits

            Yii::log('Got ' . sizeof($allHits) . ' HITs so far, out of a total of ' . ($totalHitsCount == -1 ? 'None' : $totalHitsCount) . ' - now asking for the next bunch of hits (page number ' . $parameters['PageNumber'] . ')', 'debug', 'MTurk');

            $currentRequestResult = $this->tools->sendRequest($parameters);

            if ($currentRequestResult['success']) {
                if (!array_key_exists('HIT', $currentRequestResult['data']['SearchHITsResult'])) {
                    // No hits available
                    return array('success' => true, 'data' => array());
                } else {
                    // There are hits available
                    $numResults = $currentRequestResult['data']['SearchHITsResult']['NumResults'];

                    // In the first request: set the number of total hits
                    if ($totalHitsCount == -1)
                        $totalHitsCount = $currentRequestResult['data']['SearchHITsResult']['TotalNumResults'];


                    if ($numResults == 1) {
                        // Single HIT: is stored directly in the key 'HIT'
                        $hitsReturnedThisTime = array($currentRequestResult['data']['SearchHITsResult']['HIT']);
                    } else {
                        // Multiple HITs: are stored as an array in the key 'HIT'
                        $hitsReturnedThisTime = $currentRequestResult['data']['SearchHITsResult']['HIT'];
                    }
                    $allHits = array_merge($allHits, $hitsReturnedThisTime);

                    Yii::log('Adding the next ' . sizeof($hitsReturnedThisTime) . ' HITs to the list of all ' . $totalHitsCount . ' HITs: ' . print_r($hitsReturnedThisTime, true), 'debug', 'MTurk');


                }
            } else {
                // Request failure
                Yii::log('Could not fetch hits', 'debug', 'MTurk');
                return $currentRequestResult;
            }
            // Increment the page number for the next iteration
            $parameters['PageNumber'] = $parameters['PageNumber'] + 1;
        }
        // End while not all HITs fetched


        $result = array('success' => true, 'data' => $allHits);

        Yii::log('All hits could be fetched - returning result ' . print_r($result, true), 'debug', 'MTurk');
        return $result;
    }

    //endregion Platform-related operations


    //region Task-related operations

    /**
     * Get crowd-sourcing related information about the specified platformTask identifier
     * @param $platformTaskId
     * @return array
     */
    public function getExecutionInformation($platformTaskId)
    {
        Yii::log('About to get execution information', 'debug', 'MTurk');

        $parameters = array();
        $parameters['Operation'] = 'GetHIT';
        $parameters['ResponseGroup'] = 'HITDetail';
        if ($platformTaskId != NULL) {
            $parameters['HITId'] = $platformTaskId;
            $reqResult = $this->tools->sendRequest($parameters);
        } else {
            // Task not yet published
            $reqResult['success'] = false;
            $reqResult['errors'] = array('Task is not yet published');
        }

        if ($reqResult['success']) {
            $result = array();
            $data = array();
            // TODO check method implementation :is this correct?
            if (!array_key_exists('CreationTime', $reqResult['data']['HIT'])) {
                Yii::log('No platform status found', 'debug', 'MTurk');
                // Task is not yet published on the platform
                $result['success'] = false;
            } else {
                $result['success'] = true;
                $data['CreationDate'] = $reqResult['data']['HIT']['CreationTime'];
                $data['ExpirationDate'] = $reqResult['data']['HIT']['Expiration'];

                // Convert the MTurk status to the application status ('unpublished','disabled','deleted','running')
                $status = $reqResult['data']['HIT']['HITStatus'];
                if ($status == 'Assignable')
                    $status = 'running';
                else if ($status == 'Unassignable')
                    $status = 'disabled';
                else if ($status == 'Reviewable')
                    $status = 'disabled';
                else if ($status == 'Reviewing')
                    $status = 'disabled';
                else if ($status == 'Disabled')
                    $status = 'deleted'; // NOT WebApplication state 'disabled'
                else if ($status == 'Disposed')
                    $status = 'deleted';
                else if ($status == 'None')
                    $status = 'unpublished';
                $data['status'] = $status;
                $data['ReviewStatus'] = $reqResult['data']['HIT']['HITReviewStatus'];
                $data['MaxAssignments'] = $reqResult['data']['HIT']['MaxAssignments'];

            }
            $result['data'] = $data;
            return $result;
        } else
            return $reqResult;
    }

    /**
     * Publish the task on the crowd-sourcing platform
     * @param $model
     * @param $flowItemId
     * @param $maxAssignmentsOverride The number of assignments required/ accepted for this task. If not set, the model's max_assignments value is used.
     * @return mixed
     */
    public function publishTask($model, $flowItemId, $maxAssignmentsOverride=null)
    {
        Yii::log('About to publish task ' . $model->getItemInfo(), 'info', 'MTurk');

        // Get the parameters requested to publish a task
        $pars = $this->tools->getParamsToCreateHit($model, $flowItemId, $maxAssignmentsOverride);

        $maxAssignments = $pars['MaxAssignments'];
        Yii::log('Got all parameters to create HIT - now publishing task ' . $model->getItemInfo().', accepting up to '.$maxAssignments.' assignments', 'debug', 'MTurk');

        $result = $this->tools->sendRequest($pars);
        if ($result['success']) {
            $hitData = array('HITId' => $result['data']['HIT']['HITId'], 'HITTypeId' => $result['data']['HIT']['HITTypeId']);
            Yii::log('Published task - hit data: ' . print_r($hitData, true), 'debug', 'MTurk');
            $pd = $model->platform_data;
            if (is_string($pd))
                $pd = json_decode($pd, true);

            // Create a new 'task' object that represents the currently on MTurk running HIT
            $npd = array();
            $npd['provider'] = 'mturk';
            $npd['data'] = $hitData;
            $npd['status'] = 'running';
            $npd['flowItemId']=$flowItemId;
            $npd['max_assignments']=$maxAssignments;



            // Add the newly published task to the 'tasks' attribute, which keeps track of all on MTurk published HITs
            if (!array_key_exists('tasks', $pd))
                $pd['tasks'] = array();
            $pd['tasks'][] = $npd;
            $model->platform_data = json_encode($pd);
            $model->saveAttributes(array('platform_data'));
            $model->platform_data = $pd;

            $result['data'] = $model->attributes;
            Yii::log('Platform data updated for ' . $model->getItemInfo() . ', returning result ' . print_r($result, true), 'debug', 'MTurk');
            return $result;
        } else
            return $result;
    }


    /**
     * In this method, the task is deleted completely and cannot be re-activated!
     * In this method,
     * @param $modelOrHitId
     * @return mixed
     */
    public function deleteTask($modelOrHitId)
    {
        Yii::log('About to delete task', 'debug', 'MTurk');

        $hitIds = $this->tools->getHitIdFromModelOrId($modelOrHitId);

        // Initialize the result
        $result = array('success' => true, 'data' => array());

        foreach ($hitIds as $hitId) {
            $parameters = array();
            $parameters['Operation'] = 'DisableHIT';
            $parameters['HITId'] = $hitId;

            $requestResult = $this->tools->sendRequest($parameters);
            if ($requestResult['success']) {
                Yii::log('Delete task result: ' . print_r($requestResult['data'], true), 'debug', 'MTurk');
                $success = $requestResult['data']['DisableHITResult']['Request']['IsValid'] == true;
                if (!$success) {
                    // Get the errors that occurred
                    $requestResult['success'] = false;
                    if (array_key_exists('errors', $result))
                        $result['errors'] = array();
                    foreach ($requestResult['errors'] as $error) {
                        $result['errors'][] = $error;
                    }
                } else {
                    // Update the model's data
                    $model = null;
                    if (is_object($modelOrHitId)) {
                        $model = $modelOrHitId;
                    } else {
                        // Did not submit the model: must find it first
                        $items = FlowManagement::getAllItems();
                        foreach ($items as $item) {
                            if ($item->hasAttribute('platform_data')) {
                                $itemPd = $item->platform_data;
                                if (is_string($itemPd))
                                    $itemPd = json_decode($itemPd, true);
                                if (array_key_exists('tasks', $itemPd)) {
                                    $tasks = $itemPd['tasks'];
                                    foreach ($tasks as $task) {
                                        if (array_key_exists('data', $task) && array_key_exists('HITId', $task['data'])) {
                                            if ($modelOrHitId == $task['data']['HITId']) {
                                                // Found the item for which the platformTask with the submitted id was published on the crowd-sourcing platform
                                                Yii::log('DeleteTask: Success, found the model for which the platformTaskIdentifier ' . $modelOrHitId . ' was published: ' . $item->getItemInfo(), 'debug', 'MTurk');
                                                $model = $item;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($model == null) {
                        // Model not found
                        Yii::log('DeleteTask: Cannot remove the platformTask from the webApplication\'s tasks list: could not find the item for which the platformTaskIdentifier ' . $modelOrHitId . ' was published', 'warning', 'MTurk');
                    } else {
                        // Model found
                        $pd = $model->platform_data;
                        // Remove the task that could be deleted from the list of published tasks for this item
                        if (array_key_exists('tasks', $pd) && $pd['tasks'] != null && is_array($pd['tasks'])) {
                            //Browse through all registered tasks in order to find the task with the given HITId
                            foreach ($pd['tasks'] as $index => $task) {
                                if ($task == null)
                                    continue;
                                $taskHitId = (array_key_exists('data', $task) && array_key_exists('HITId', $task['data'])) ? $task['data']['HITId'] : -1;
                                if ($taskHitId == $hitId) {
                                    unset($pd['tasks'][$index]);
                                }
                            }
                            $model->platform_data = json_encode($pd);
                            $model->saveAttributes(array('platform_data'));
                            $model->platform_data = json_decode($model->platform_data, true);
                        }
                    }

                    $result['data'][] = $hitId;
                }
                // End success
            }
            // End MTurk replied success
        }
        // End for each hitId
        return $result;
    }

    /**
     * Deletes all tasks on the platform, not only the tasks stored in the WebApplication
     * @return array
     */
    public function deleteAllTasks()
    {
        Yii::log('About to delete all tasks', 'debug', 'MTurk');


        // Get all tasks from the platform
        $tasksResult = $this->getAllTasks();

        if ($tasksResult['success']) {
            // Successfully could retrieve tasks
            $hitIds = array();

            // Get the HIT Ids which will be deleted
            foreach ($tasksResult['data'] as $taskOnCrowd) {
                $hitIds[] = $taskOnCrowd['HITId'];
            }
            $nbOrig = sizeof($hitIds);
            Yii::log('Deleting ' . $nbOrig . ' tasks on the crowd', 'debug', 'MTurk');
            $nbDeleted = 0;

            // Then, delete each HIT separately
            foreach ($hitIds as $hitId) {
                $taskDeleteResult = $this->deleteTask($hitId);
                if ($taskDeleteResult['success']) {
                    // Deletion successful for this HITId
                    Yii::log('Successfully deleted task with HIT id \'' . $hitId . '\'', 'debug', 'MTurk');
                    $nbDeleted++;
                } else {
                    // Deletion failure for this HITId
                    $errors = $taskDeleteResult['error'];
                    Yii::log('Could not delete task with HIT id \'' . $hitId . '\': ' . ($errors != NULL ? join(', ', $errors) : 'an undefined error occurred'), 'warning', 'MTurk');
                }
            }

            // Prepare success message
            $msg = $nbDeleted . ' out of ' . $nbOrig . ' tasks published on the crowd-sourcing platform were deleted';
            Yii::log($msg, 'debug', 'MTurk');
            return array('success' => true,
                'data' => $msg);
        } else {
            // Could not get the tasks from the crowd
            $errors = $tasksResult['error'];
            $msg = 'Unable to delete tasks on the platform - could not retrieve the tasks: ' . ($errors != NULL ? join(', ', $errors) : 'an undefined error occurred');
            return array('success' => false,
                'errors' => $msg);
        }
    }

    //endregion Task-related operations


    //region Review-related operations

    /**
     * Validate an assignment on the platform
     * @param $assignmentId
     * @param $approve
     * @param null $message
     * @return array
     * @throws CHttpException
     */
    public function validateAssignment($assignmentId, $approve, $message = NULL)
    {
        Yii::log('About to validate assignment ' . $assignmentId . '. Approve or reject? ' . ($approve ? 'Approve' : 'Reject') . '. Using message? ' . ($message == NULL ? 'No' : 'Yes : \'' . $message . '\''), 'debug', 'MTurk');

        // Verify the parameters
        if ($assignmentId == NULL || !is_string($assignmentId)) {
            throw new CHttpException(500, 'Cannot validate assignment - must provide assignment id of type string');
        }


        // Check if the assignment has already been validated
        $assignmentStatusParameters = array(
            'Operation' => 'GetAssignment',
            'AssignmentId' => $assignmentId
        );
        $assignmentStatusResult = $this->tools->sendRequest($assignmentStatusParameters);
        Yii::log('Checking if the assignment ' . $assignmentId . ' has status \'Submitted\'', 'debug', 'MTurk');
        if ($assignmentStatusResult['success']) {
            $assignmentStatus = $assignmentStatusResult['data']['GetAssignmentResult']['Assignment']['AssignmentStatus'];
            if ($assignmentStatus != 'Submitted') {
                Yii::log('Cannot validate assignment - assignment with id ' . $assignmentId . ' has already been validated with status \'' . $assignmentStatus . '\'', 'info', 'MTurk');
                return array('success' => true);
            } else {
                Yii::log('Ok, can validate assignment with id ' . $assignmentId . ', since it has status \'' . $assignmentStatus . '\'', 'debug', 'MTurk');
            }
        } else {
            // An error occurred while retrieving the assignment status
            throw new CHttpException(500, 'Cannot validate assignment - failed to get the current assignment status for assignment ' . $assignmentId);
        }


        // Prepare the request to call the MTurk API
        $parameters = array();
        $parameters['Operation'] = $approve ? 'ApproveAssignment' : 'RejectAssignment';
        $parameters['AssignmentId'] = $assignmentId;
        if ($message != NULL)
            $parameters['RequesterFeedback'] = $message;

        // Finally, send the request
        $reqResult = $this->tools->sendRequest($parameters);
        return $reqResult;
    }

    /**
     * Get the assignment status ('pending', 'accepted', 'rejected', 'notfound')
     * @param $assignmentId
     * @return array
     * @throws CHttpException
     */
    public function getAssignmentStatus($assignmentId)
    {
        Yii::log('GetAssignmentStatus for assignmentId ' . $assignmentId, 'debug', 'MTurk');
        $assignmentStatusParameters = array(
            'Operation' => 'GetAssignment',
            'AssignmentId' => $assignmentId
        );
        $result = null;
        $assignmentStatusResult = $this->tools->sendRequest($assignmentStatusParameters);
        if ($assignmentStatusResult['success']) {
            $assignmentStatus = $assignmentStatusResult['data']['GetAssignmentResult']['Assignment']['AssignmentStatus'];
            if ($assignmentStatus == 'Submitted')
                $result = 'pending';
            else if ($assignmentStatus == 'Approved')
                $result = 'accepted';
            else if ($assignmentStatus == 'Rejected')
                $result = 'rejected';
        } else {
            // An error occurred while retrieving the assignment status
            Yii::log('Cannot get assignment status for assignment ' . $assignmentId, 'info', 'MTurk');
            $result = 'notfound';
        }
        Yii::log('GetAssignmentStatus for assignmentId ' . $assignmentId . ': status is \'' . $result . '\'', 'debug', 'MTurk');
        return $result;
    }

    //endregion Review-related operations

    //region Results-related operations

    /**
     * Be careful - this method returns the result wrapped in an answer object (and NOT the assignments directly)
     * @param $modelOrHitId
     * @param null $assignmentStatus
     * @param bool $zeroResultsWhenNotFound Whether an empty array should be returned and the request should defined successful if the results could not be retrieved from the database
     * @return array The result object
     * @throws CHttpException
     */
    public function getTaskResults($modelOrHitId, $assignmentStatus = NULL, $zeroResultsWhenNotFound = true)
    {
$itemInfo = 'GetTaskResults for '.(is_object($modelOrHitId)? $modelOrHitId->getItemInfo():(' hitId '.$modelOrHitId)).': ';

        // Adjust status capitalization such that it can be read by the MTurk API
        if ($assignmentStatus != NULL)
            $assignmentStatus = ucfirst($assignmentStatus);

        Yii::log($itemInfo.'About to get task results with assignment status \'' . ($assignmentStatus == NULL ? '<NULL>' : $assignmentStatus) . '\', return zero results when HIT on crowd not found? ' . ($zeroResultsWhenNotFound ? 'true' : 'false'), 'debug', 'MTurk');

        // Verify parameter validity
        $allowedStatus = array('', 'Submitted', 'Approved', 'Rejected');
        if (!in_array($assignmentStatus, $allowedStatus)) {
            $msg = $itemInfo.'Cannot get task results with assignmentStatus \'' . $assignmentStatus . '\' - allowed values are: ' . implode(', ', $allowedStatus);
            Yii::log($msg, 'warning', 'MTurk');
            throw new CHttpException(500, $msg);
        }

        $hitIds = $this->tools->getHitIdFromModelOrId($modelOrHitId);


        $allAssignmentsAllHits = array(); // Keeps track of all reviews that were fetched so far

        foreach ($hitIds as $hitId) {
            $allAssignmentsThisHit=array();
            if ($hitId == NULL) {
                $msg = $itemInfo.'No task results found - no HIT id available for WebApplication item ' . $modelOrHitId->id . ' of type \'' . lcfirst(get_class($modelOrHitId)) . '\'';
                Yii::log($msg, $zeroResultsWhenNotFound ? 'info' : 'warning', 'MTurk');
                return array('success' => $zeroResultsWhenNotFound, 'data' => array(), 'errors' => array($msg));
            } else {
                Yii::log($itemInfo.'Getting results for HIT id \'' . $hitId . '\'', 'debug', 'MTurk');
            }

            $parameters = array();
            $parameters['Operation'] = 'GetAssignmentsForHIT';
            $parameters['HITId'] = $hitId;
            if ($assignmentStatus != NULL) {
                // Filter assignments
                $parameters['AssignmentStatus'] = $assignmentStatus; // 'Submitted, Approved, Rejected'
            }
            $parameters['SortProperty'] = 'AssignmentStatus'; // 'AcceptTime, SubmitTime, AssignmentStatus')
            $parameters['SortDirection'] = 'Ascending'; // 'Ascending, Descending'
            $parameters['PageSize'] = 100; // 10-100
            $parameters['PageNumber'] = 1;
            $parameters['ResponseGroup'] = 'AssignmentFeedback';

            if ($assignmentStatus != NULL) {
                // Filter task results
                $parameters['AssignmentStatus'] = $assignmentStatus;
            } else {
                // No task results filter: get all results, independently of the assignmentStatus
            }


            $totalReviewsCount = -1; // The total number of reviews that must be fetched


            while ($totalReviewsCount < sizeof($allAssignmentsThisHit)) {
                // Get the next bunch of hits

                Yii::log($itemInfo.'got ' . sizeof($allAssignmentsThisHit) . ' reviews for hitId '.$hitId.' so far, now asking for the next bunch of hits (page number ' . $parameters['PageNumber'] . ')', 'debug', 'MTurk');

                $currentRequestResult = $this->tools->sendRequest($parameters);

                if ($currentRequestResult['success']) {
                    if (!array_key_exists('Assignment', $currentRequestResult['data']['GetAssignmentsForHITResult'])) {
                        // No hits available
                        Yii::log($itemInfo.'no assignments found for HITId ' . $hitId, 'debug', 'MTurk');
                        break;
                    } else {
                        // There are hits available
                        $numResults = $currentRequestResult['data']['GetAssignmentsForHITResult']['NumResults']; // Assignments returned in this query


                        if ($numResults == 1) {
                            // Single review
                            $reviewsReturnedThisTime = array($currentRequestResult['data']['GetAssignmentsForHITResult']['Assignment']);
                        } else {
                            // Multiple reviews
                            $reviewsReturnedThisTime = $currentRequestResult['data']['GetAssignmentsForHITResult']['Assignment'];
                        }

                        Yii::log($itemInfo.' got '.sizeof($reviewsReturnedThisTime).' assignments for HITId ' . $hitId, 'debug', 'MTurk');
                        $allAssignmentsThisHit= array_merge($allAssignmentsThisHit, $reviewsReturnedThisTime);

                        Yii::log($itemInfo.'Adding the next ' . sizeof($reviewsReturnedThisTime) . ' reviews to the list of all ' . (($totalReviewsCount == -1) ? '' : ($totalReviewsCount.' ')) . 'reviews', 'debug', 'MTurk');

                        // In the first request: set the number of total reviews
                        if ($totalReviewsCount == -1)
                            $totalReviewsCount = $currentRequestResult['data']['GetAssignmentsForHITResult']['TotalNumResults'];


                        // Increment the page number for the next iteration
                        $parameters['PageNumber'] = $parameters['PageNumber'] + 1;
                    }
                } else {
                    // Request failure
                    $errs = $currentRequestResult['errors'];
                    $msg = $itemInfo.'Could not fetch reviews: ' . (is_array($errs) ? implode(', ', $errs) : $errs);
                    Yii::log($msg, $zeroResultsWhenNotFound ? 'info' : 'warning', 'MTurk');
                    return array('success' => $zeroResultsWhenNotFound, 'data' => array(), 'errors' => array($msg));
                }// End request failure

            }
            // End while not all assignments fetched for this hitId
            YIi::log($itemInfo.'finished retrieving all results for hitId '.$hitId.', got a total of '.sizeof($allAssignmentsThisHit).' assignments which are now added to the list of the '.sizeof($allAssignmentsAllHits).' assignments', 'debug', 'MTurk');
            $allAssignmentsAllHits=array_merge($allAssignmentsThisHit, $allAssignmentsAllHits);
        }
        // End for each hitId
        $result = array('success' => true, 'data' => $allAssignmentsAllHits);

        Yii::log($itemInfo.' All results could be fetched - ' . sizeof($allAssignmentsAllHits) . ' assignments in total found', 'debug', 'MTurk');
        return $result;

    }

    /**
     * All results of a platform's task are represented in a special, platform-specific way. When retrieving results, we must not loose those data (like for example a reference to a HITId in MTurk).
     * However, to further process the results in a uniform way, we have to somehow bring the platform-specific results to a general format such that the input may be further treated independently of the
     * crowdsourcing platform that was actually used. And this transformation is done in this method.
     * @param $model The model of the item that was crowd-sourced. This is required to set a reference to this item in the parsed result
     * @param $platformResult
     * @throws CHttpException
     * @return Array
     */
    public function parseResult($model, $platformResult)
    {

        Yii::log('Parsing platform result for ' . $model->getItemInfo(), 'debug', 'MTurk');
        $type = strtolower(get_class($model));


        //region Fetch the results from the crowd-sourcing platform
        $platformResultXml = new SimpleXMLElement($platformResult['Answer']);
        $platformResultXml->registerXPathNamespace('ns', 'http://mechanicalturk.amazonaws.com/AWSMechanicalTurkDataSchemas/2005-10-01/QuestionFormAnswers.xsd');
        $xmlAnswers = $platformResultXml->xpath('//ns:QuestionFormAnswers');
        $jsonAnswers = json_encode($xmlAnswers);
        $arrayAnswers = json_decode($jsonAnswers, TRUE);
        $arrayAnswers = $arrayAnswers[0];
        /*
         * If there is only one form fields are available, MTUrk sends the result directly in the 'Answer' element.
         * If there are multiple fields available, MTurk sends them as an array below the 'Answer' element.
         * In order to be able to process the data in a uniform way, we will thus embed a single-answer object in an array
         * for further processing, such that in any case the answer object is an array of answers.
         */
        if (sizeof($arrayAnswers) > 0 && array_key_exists('QuestionIdentifier', $arrayAnswers['Answer'])) {
            /*
            * The QuestionIdentifier is available directly in the XML answer, meaning that there was just one single assignment.
             * Thus, wrap in in an array
            */
            $arrayAnswers['Answer'] = array($arrayAnswers['Answer']);
            Yii::log('Only one form result was set: after wrapping it in an array: ' . print_r($arrayAnswers, true), 'debug', 'MTurk');
        }
        //endregion Fetch the results from the crowd-sourcing platform


        //region Read general metadata that are valid for all item types

        // Make sure that the assignment points to the item it has been submitted for
        $parsedAssignment = array('itemId' => $model->id, 'itemType' => strtolower(get_class($model)));
        $parsedAssignment['platformTaskId'] = $platformResult['HITId'];
        $parsedAssignment['platformResultId'] = $platformResult['AssignmentId'];
        $parsedAssignment['assignmentStatus'] = strtolower($platformResult['AssignmentStatus']);

        if ($parsedAssignment['assignmentStatus'] == 'approved' && Yii::app()->params['payRejectedAnswers']) {
            /*
             * If rejected arguments are paid anyway, then we need another logic to read the assignment status. We do
             * so by checking whether the assignment is listed in the item's array of rejected assignments
             */
            $platformData = $model->platform_data;
            if (is_string($platformData))
                $platformData = json_decode($platformData, true);
            if (array_key_exists('rejectedAssignments', $platformData)) {
                if (in_array($parsedAssignment['platformResultId'], $platformData['rejectedAssignments'])) {
                    // Yes, the assignment was rejected, but does have an approved state because the requester has chosen to also pay rejected assignments
                    $parsedAssignment['assignmentStatus'] = 'rejected';
                }
            }

            // Logging
            $isPaidButRejected = $parsedAssignment['assignmentStatus'] == 'rejected';
            Yii::log('Parsing result - checking if the approved assignment ' . $parsedAssignment['platformResultId'] . ' for item ' . $model->getItemInfo() . ' was paid, but rejected by the validator: ' . ($isPaidButRejected ? 'Yes, the assignment was paid but the result is discarded' : 'No, the assignment was accepted \'truly\''), 'debug', 'MTurk');
        }

        //endregion Read general metadata that are valid for all item types

        //region Handle the different types of task

        Yii::log('Got general assignment data - about to parse it for type ' . $type . ': ' . print_r($parsedAssignment, true), 'debug', 'MTurk');
        switch ($type) {
            case 'task':
                $parsedAssignment['data'] = $this->parseTaskResult($model, $arrayAnswers);
                break;
            case 'merger':
                $parsedAssignment['data'] = $this->parseMergerResult($model, $arrayAnswers);
                break;
            case 'splitter':
                $parsedAssignment['data'] = $this->parseSplitterResult($model, $arrayAnswers);
                break;
            case 'postprocessor':
                $parsedAssignment['data'] = $this->parsePostprocessorResult($model, $arrayAnswers);
                break;
            default:
                throw new CHttpException(501, 'Cannot parse data for type ' . $type . ' - not implemented yet');
        }
        // End switch the item's type

        //endregion Handle the different types of task

        return $parsedAssignment;

    }

    //region Results parsing

    //region Parse tasks
    /**
     * Read the answers coming from the crowd and make them WebApp-readable, i.e. parse all its information such that it can be treated by the web application
     * @param $model
     * @param $formFields
     * @return array
     * @throws CHttpException
     */
    private function parseTaskResult($model, $formFields)
    {
        Yii::log('Parsing task result for ' . $model->getItemInfo(), 'debug', 'MTurk');
        //region Read task-related general data
        $media = $model->output_media_type_id;
        $determined = $model->output_determined;
        $mapping = $model->output_mapping_type_id;
        $ordered = $model->output_ordered;
        $taskType = $model->task_type_id;
        //endregion Read task-related general data


        // For each type of the model, a different parsing technique must be applied
//        if ($taskType == 2 && $media == 2 && $determined == 0 && $mapping == 2 && $ordered == 0) {
        // Currently, all tasks are treated equally, meaning that there are no different MTurk formats for different task types
        if (true) {
            // Splitting a text into multiple parts
            $allAnswers = array();

            foreach ($formFields as $formField) {
                Yii::log('Parsing task answer ' . print_r($formField, true), 'debug', 'MTurk');
                // Read all answers
                foreach ($formField as $questionAnswer) {
                    // Read the form field's identifier
                    $id = $questionAnswer['QuestionIdentifier']; // Note that every form input is accessed by 'QuestionIdentifier'
                    // Then read the value that was submitted
                    $val = $questionAnswer['FreeText'];
                    if ($val == NULL || (is_array($val) && sizeof($val) == 0) || $val == '') {
                        // Empty answers are submitted by MTurk as an empty array
                        // Do not process empty values
                        // TODO is it appropriate to ignore empty values?
                        //$val=NULL;
                        continue;
                    }
                    $allAnswers[] = array(
                        'id' => $id,
                        'value' => $val,
                    );
                }

                // Order matters! Sorting the inputs
                Yii::log('About to sort result fields', 'debug', 'MTurk');
                usort($allAnswers, array($this, 'sortById'));

                Yii::log('Parsed task: ' . print_r($allAnswers, true), 'debug', 'MTurk');
                // End for each question
            }
            // End for each answer

            return $allAnswers;
        } // End for this task type
        else {
            // All other task types
            throw new CHttpException(501, sprintf('Cannot parse this task type (TaskType %d, MediaType %d, determined %d, MappingType %d, ordered %d): not implemented yet', $taskType, $media, $determined, $mapping, $ordered));
        }
    }

    //endregion Parse tasks
    //region Parse mergers
    /**
     * Read the answers coming from the crowd and make them WebApp-readable, i.e. parse all its information such that it can be treated by the web application
     * @param $model
     * @param $formFields
     * @return array
     * @throws CHttpException
     */
    private function parseMergerResult($model, $formFields)
    {
        $mergerType = $model->merger_type_id;

        Yii::log('Parsing merger of merger type ' . $mergerType, 'debug', 'MTurk');

        // Parse the assignment depending on the merger type
        if ($mergerType == 12) {
            // Select best option
            $allAnswers = array();

            foreach ($formFields as $formField) {
                Yii::log('Parsing merger answer ' . print_r($formField, true), 'debug', 'MTurk');
                // Read all answers
                foreach ($formField as $questionAnswer) {
                    // Read the form field's identifier
                    $id = $questionAnswer['QuestionIdentifier']; // Note that every form input is accessed by 'QuestionIdentifier'
                    // Then read the value that was submitted
                    $val = $questionAnswer['FreeText'];
                    if ($val == NULL || (is_array($val) && sizeof($val) == 0) || $val == '') {
                        // Empty answers are submitted by MTurk as an empty array
                        // Do not process empty values
                        continue;
                    }
                    $allAnswers[] = array(
                        'id' => $id,
                        'value' => $val,
                    );
                }

                Yii::log('Parsed merger: ' . print_r($allAnswers, true), 'debug', 'MTurk');
                // End for each question
            }
            // End for each answer
            return $allAnswers;
        }
        throw new CHttpException(501, 'Cannot parse this merger type - not implemented yet');

    }

    //endregion Parse mergers
    //region Parse splitters
    /**
     * Read the answers coming from the crowd and make them WebApp-readable, i.e. parse all its information such that it can be treated by the web application
     * @param $model
     * @param $formFields
     * @return array
     * @throws CHttpException
     */
    private function parseSplitterResult($model, $formFields)
    {
        throw new CHttpException(501, 'Cannot parse this splitter type - not implemented yet');

    }

    //endregion Parse splitters
    //region Parse postprocessors
    /**
     * Read the answers coming from the crowd and make them WebApp-readable, i.e. parse all its information such that it can be treated by the web application
     * @param $model
     * @param $formFields
     * @return array
     * @throws CHttpException
     */
    private function parsePostprocessorResult($model, $formFields)
    {

        //region Read postprocessor-related general data
        $validationType = $model->validation_type_id;
        $postprocessorType = $model->postprocessor_type_id;
        //endregion Read task-related general data


        // For each type of the model, a different parsing technique must be applied
        if ($postprocessorType == 12 && $validationType != NULL && $validationType == 1) {
            // Validation of type Accept-Reject
            $allAnswers = array();

            foreach ($formFields as $formField) {
                Yii::log('Parsing task answer ' . print_r($formField, true), 'debug', 'MTurk');
                // Read all answers
                foreach ($formField as $questionAnswer) {
                    Yii::log('Parsing form field ' . print_r($questionAnswer, true), 'debug', 'MTurk');
                    // Read the form field's identifier
                    $id = $questionAnswer['QuestionIdentifier']; // Note that every form input is accessed by 'QuestionIdentifier'
                    // Then read the value that was submitted
                    $val = $questionAnswer['FreeText'];
                    if ($val != NULL && is_array($val) && sizeof($val) == 0) {
                        // Empty answers are submitted by MTurk as an empty array
                        $val = NULL;
                    }
                    $allAnswers[] = array(
                        'id' => $id,
                        'value' => $val,
                    );
                }
                // End for each answer
            }
            // End for each answer

            return $allAnswers;
        } // End for this task type
        else {
            // All other task types
            throw new CHttpException(501, 'Not implemented');
        }
    }

    //endregion Parse postprocessors

    //region Helper methods
    function sortById($a, $b)
    {
        $val1 = $a['id'];
        $val2 = $b['id'];
        $res = strcmp($val1, $val2);
        return $res;

    }
    //endregion

    //endregion Results parsing


    //endregion Results-related operations
}

?>