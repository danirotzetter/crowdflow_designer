<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 08.08.13
 * Time: 21:26
 */

class Helper
{
// Static fields
    public static $NUM_PREFIX='val_'; // Prefix added to associative keys where the key is numeric. This is needed that PHP can deal with the keys
    public static $ORDERKEY_FIELD='orderKey'; // Name of the form field indicating the order of an assignment


    //region Crowdsourcing-related functions

    //region Component-related functions

    /**
     * Returns the component of the currently set platform
     * @return mixed
     */
    public static function getCurrentPlatformComponent()
    {
        $component = NULL;
        eval('$component = Yii::app()->' . Yii::app()->params['platform'] . ';');
        return $component;
    }

    //endregion Component-related functions


    /**
     * Modifies the list of assignments for a model
     * @param $assignmentStatus string Indicating which list must be modified 'pending', 'rejected', 'accepted'
     * @param $platformData array The platformData at which the list is stored
     * @param $add bool Whether the assignment should be added. FALSE means the assignment is removed from the list
     * @param $flowItemId string The flowItemId for which the assignment list is modified
     * @param $assignmentId string The assignment to add or remove
     * @return array The modified platform data
     */
    public static function modifyAssignmentsList($assignmentStatus, $platformData, $add, &$flowItemId, $assignmentId)
    {

        if ($flowItemId == 0)
            $flowItemId = -1;

        Yii::log('Modifying assignment list: ' . ($add ? 'Adding' : 'Removing') . ' assignment ' . $assignmentId . ' ' . ($add ? 'to' : 'from') . ' the list of ' . $assignmentStatus . ' assignments for flowItem ' . $flowItemId, 'debug', 'Helper');


        $listName = $assignmentStatus . 'Assignments';
        // Reduce the number of pending assignments
        if (!array_key_exists($listName, $platformData))
            $platformData[$listName] = array();
        if (!array_key_exists($flowItemId, $platformData[$listName]))
            $platformData[$listName][$flowItemId] = array();

        if ($add) {
            // Add the assignmentId to the list
            Yii::log('About to add assignment ' . $assignmentId . ' to the list of ' . $assignmentStatus . ' assignments for flowItem ' . $flowItemId . ': was ' . print_r($platformData[$listName][$flowItemId], true), 'debug', 'Helper');
            if (!in_array($assignmentId, $platformData[$listName][$flowItemId]))
                $platformData[$listName][$flowItemId][] = $assignmentId;
        } else {
            // Remove assignmentId from the list
            $new = array_diff($platformData[$listName][$flowItemId], array($assignmentId));
            Yii::log('About to remove assignment ' . $assignmentId . ' from the list of ' . $assignmentStatus . ' assignments for flowItem ' . $flowItemId . ': was ' . print_r($platformData[$listName][$flowItemId], true) . ', is new ' . print_r($new, true), 'debug', 'Helper');
            $platformData[$listName][$flowItemId] = $new;
        }
        return $platformData;
    }


    /**
     * Get the reasons why a task cannot be published
     * @param $model Task The model to check
     * @return Array An array of errors. NULL if the task can be published
     */
    public static function getPublishErrors($model)
    {
        $errs = array();

        try {

            Yii::log('Getting publish errors: check url of cs form for model ' . $model->getItemInfo(), 'debug', 'Helper');
            Helper::getUrlOfCsForm($model);
        } catch (Exception $e) {
            $errs[] = $e->getMessage();
        }

        // In order to publish the task, at least one input must be defined
        Yii::log('Getting publish errors: get connections to model ' . $model->getItemInfo(), 'debug', 'Helper');
        $connections = Helper::getConnection(NULL, NULL, strtolower(get_class($model)), $model->id, $model->workspace_id);
        $nb = sizeof($connections);
        if ($nb == 0) {
            Yii::log('Getting publish errors: cannot publish task, as no input is defined', 'debug', 'Helper');
            $errs[] = 'No input available for this task.';
        } else {
            Yii::log('Getting publish errors: can publish task - there are ' . $nb . ' inputs defined', 'debug', 'Helper');
        }


        if (sizeof($errs) > 0) {
            Yii::log('Found publish errors? Yes:' . print_r($errs, true), 'debug', 'Helper');
            return $errs;
        } else {
            Yii::log('Found publish errors? No', 'debug', 'Helper');
            return NULL;
        }
    }

    /**
     * Displays the URL that has to be loaded from the crowd-sourcing platform to execute the task with the submitted parameter.
     * Verifies that the task contains all necessary information to be crowd-sourced.
     * @param $model The task model
     * @return The URL to the task's form
     * @throws CHttpException
     */
    public static function getUrlOfCsForm($model)
    {
        Yii::log('Get Url of cs form for item ' . print_r($model->attributes, true), 'debug', 'Helper');

        // Define the url that points to the form that will be displayed in the end
        $className = get_class($model);


        $urlToLoad = NULL;
        switch ($className) {
            case 'Task':
                $urlBase = '/csforms/task-type%d-media%d-determined%d-mapping%d-ordered%d';
                $urlToLoad = sprintf($urlBase, $model->task_type_id, $model->output_media_type_id, $model->output_determined, $model->output_mapping_type_id, $model->output_ordered);
                break;
            case 'Merger':
                $urlBase = '/csforms/merger-type%d';
                $urlToLoad = sprintf($urlBase, $model->merger_type_id);
                break;
            case 'Postprocessor':
                $urlBase = '/csforms/postprocessor-type%d';
                $urlToLoad = sprintf($urlBase, $model->postprocessor_type_id);
                break;
            default:
                throw new CHttpException(500, 'Cannot get url of the crowd-sourcing form for an item of type \'' . $className . '\'');
        }

        $pathToView = 'protected/views' . $urlToLoad . '.php'; // Note that the path varies from the url used in 'renderPartial()', as for the latter, the base path and file ending is given implicitly/ programmed internally
        if (!file_exists($pathToView)) {
            // Form not available/ not defined
            Yii::log('No such form defined: ' . $pathToView, 'warning', 'Helper');
            return '/csforms/na';
        } else {
            Yii::log('Form is defined: returning url ' . $urlToLoad, 'debug', 'Helper');
            return $urlToLoad;
        }
    }


    /**
     * Get the item that serves as an input for the submitted item
     * @param $targetItem The item for which the input item is searched
     * @param bool $throwErrorIfNotFound Define, if an error should be thrown upon error. Otherwize, NULL will be returned
     * @return The input model if it was found. NULL if it could not be fetched and $throwErrorIfNotFound is set to false
     * @throws CHttpException If $throwErrorIfNotFound is set to true and the item could not be fetched
     */
    public static function getInputItem($targetItem, $throwErrorIfNotFound = false)
    {
        $type = lcfirst(get_class($targetItem));

        // Get the connections to this item in order to find the item that serves as an input for the current item
        $connections = Helper::getConnection(NULL, NULL, $type, $targetItem->id, $targetItem->workspace_id);
        // There must be exactly one connection for the task
        if (sizeof($connections) != 1) {
            // Invalid amount of incoming connections
            $msg = 'Invalid amount of incoming connections for ' . $targetItem->getItemInfo() . ': has ' . sizeof($connections);
            Yii::log($msg, 'error', 'Helper');
            if ($throwErrorIfNotFound)
                throw new CHttpException(500, $msg);
            else
                return NULL;
        }

        // Prepare the information that will serve as input data
        $inputConnection = $connections[0];
        $sourceType = $inputConnection['sourceType'];
        $inputId = $inputConnection['sourceId'];


        // Fetch the model from the database
        $inputItem = null;
        $inputItemInfo = 'input item ' . $inputId . ' of type \'' . $sourceType . '\'';
        try {
            // Evaluate the expression that renders the input of the specified input item
            $stringToGetInputItem = '$inputItem = ' . ucfirst($sourceType) . '::model()->findByPk(' . $inputId . ');';
            Yii::log('Get the ' . $inputItemInfo . ' by evaluating the expression \'' . $stringToGetInputItem . '\'', 'debug', 'Helper');
            eval($stringToGetInputItem);
        } catch (Exception $e) {
            $msg = 'Failed to get input item for item ' . $targetItem->getItemInfo() . ' (i.e. ' . $inputItemInfo . '): ' . $e->getMessage();
            Yii::log($msg, 'error', 'Helper');
            if ($throwErrorIfNotFound)
                throw new CHttpException(500, $msg);
            else
                return NULL;
        }

        Yii::log('Finished get inputItem for item ' . $targetItem->getItemInfo() . ' - found ' . $inputItem->getItemInfo(), 'debug', 'Helper');
        return $inputItem;
    }

    /**
     * Get the item for which the submitted item serves as an input
     * @param $inputItem The item for which the subsequent item is searched
     * @param bool $throwErrorIfNotFound Define, if an error should be thrown upon error. Otherwize, NULL will be returned
     * @return The input model if it was found. NULL if it could not be fetched and $throwErrorIfNotFound is set to false
     * @throws CHttpException If $throwErrorIfNotFound is set to true and the item could not be fetched
     */
    public static function getOutputItem($inputItem, $throwErrorIfNotFound = false)
    {
        $type = lcfirst(get_class($inputItem));
        // Get the connections to this item in order to find the item that serves as an input for the current item
        $connections = Helper::getConnection($type, $inputItem->id, NULL, NULL, $inputItem->workspace_id);
        Yii::log('Loaded all connections: ' . print_r($connections, true), 'debug', 'Helper');
        // There must be exactly one connection
        if (sizeof($connections) != 1) {
            // Invalid amount of incoming connections
            $msg = 'Invalid amount of outgoing connections: has ' . sizeof($connections);
            Yii::log($msg, 'info', 'Helper');
            if ($throwErrorIfNotFound)
                throw new CHttpException(500, $msg);
            else
                return NULL;
        }

        // Prepare the information that will serve as input data
        $inputConnection = $connections[0];
        $targetType = $inputConnection['targetType'];
        $targetId = $inputConnection['targetId'];


        // Fetch the model from the database
        $outputItem = null;
        $outputItemInfo = 'output item ' . $targetId . ' of type \'' . $targetType . '\'';
        try {
            // Evaluate the expression that renders the output item
            $stringToGetOutputItem = '$outputItem = ' . ucfirst($targetType) . '::model()->findByPk(' . $targetId . ');';
            Yii::log('Get the ' . $outputItemInfo . ' by evaluating the expression \'' . $stringToGetOutputItem . '\'', 'debug', 'Helper');
            eval($stringToGetOutputItem);
        } catch (Exception $e) {
            $msg = 'Failed to get output item for item ' . $inputItem->getItemInfo() . ' (i.e. ' . $outputItemInfo . '): ' . $e->getMessage();
            Yii::log($msg, 'error', 'Helper');
            if ($throwErrorIfNotFound)
                throw new CHttpException(500, $msg);
            else
                return NULL;
        }

        Yii::log('Finished get outputItem for item ' . $inputItem->getItemInfo() . ' - found ' . $outputItem->getItemInfo(), 'debug', 'Helper');
        return $outputItem;
    }

    /**
     * Reads the input data for an item model
     * @param $model The item model for which the input data must be retrieved
     * @param int $flowItemId
     * @param bool $previewMode Defines if the data is rendered only for a preview of the to-be-crowdsourced form instead of a 'true, actually published' form
     * @return The input data, or NULL, if the request is invalid.
     * @throws CHttpException if the corresponding parameter is set and an error occurred
     */
    public static function renderInputData($model, $flowItemId, $previewMode = true)
    {
        // Get model information
        Yii::log('About to read input data for ' . $model->getItemInfo() . ' and flowItemId ' . $flowItemId . ', is in preview mode? ' . ($previewMode ? 'true' : 'false'), 'debug', 'Helper');

        // Initialize the answer object. If no orderKey is specified explicitly, the default will be used
        $inputData = array(Helper::$ORDERKEY_FIELD => 0);

        // Find the item that serves as an input for this item
        $inputItem = Helper::getInputItem($model, true);
        $sourceType = strtolower(get_class($inputItem));

        // Verify that the input item is of a valid type, i.e. that it can be used to render the input
        if ($sourceType != 'task' && $sourceType != 'datasource' && $sourceType != 'postprocessor' && $sourceType != 'merger' && $sourceType != 'splitter') {
            // Invalid type of the incoming connection's source item
            $msg = 'Input must be of type task, datasource, postprocessor, merger or splitter, but is of type \'' . $sourceType . '\'';
            Yii::log($msg, 'warning', 'Helper');
            throw new CHttpException(404, $msg);
        }


        Yii::log('Generating input data for ' . $model->getItemInfo() . ': the input queue for the current item is used to render the input data', 'debug', 'Helper');

        $inputQueue = $model->getInputQueueItemsForFlowItem($flowItemId);

        // Make sure elements are available
        if (sizeof($inputQueue) == 0) {
            $msg = 'Cannot render input data for ' . $model->getItemInfo() . ': no item available in the inputQueue for flowItemId ' . $flowItemId;
            Yii::log($msg, 'error', 'Helper');
            throw new CHttpException(404, $msg);
        }

        // Verify that the maximum number of assignments is not exceeded yet
        $parameters = $model->parameters;
        $platformData = $model->platform_data;
        $minAssignments = $parameters['min_assignments'];
        $maxAssignments = $parameters['max_assignments'];
        Yii::log('About to verify whether the maximum of assignments has already been reached for parameters ' . print_r($parameters, true) . ', platformData ' . print_r($platformData, true), 'debug', 'Helper');

        $pending = (array_key_exists('pendingAssignments', $platformData) && array_key_exists($flowItemId, $platformData['pendingAssignments'])) ? sizeof($platformData['pendingAssignments'][$flowItemId]) : 0;
        $accepted = (array_key_exists('acceptedAssignments', $platformData) && array_key_exists($flowItemId, $platformData['acceptedAssignments'])) ? sizeof($platformData['acceptedAssignments'][$flowItemId]) : 0;
        $rejected = (array_key_exists('rejectedAssignments', $platformData) && array_key_exists($flowItemId, $platformData['rejectedAssignments'])) ? sizeof($platformData['rejectedAssignments'][$flowItemId]) : 0;
        $total = $pending + $accepted + $rejected;
        if ($total >= $maxAssignments) {
            // Exceeded maximum number of crowd-sourced tasks
            $msg = 'Cannot display another form - the maximum number of published items (' . $maxAssignments . ') has been reached, as already ' . $total . ' assignments have been submitted by the crowd for the current ' . $model->getItemInfo();
            Yii::log($msg, 'warning', 'Helper');
            throw new CHttpException(500, $msg);
        }
        Yii::log('Proceeding generating input data for ' . $model->getItemInfo() . ' - maximum number of published item was not yet reached. Item data: ' . print_r($platformData, true), 'debug', 'Helper');


        // Data is available - the input queue is not empty
        $queueItemToRender = $inputQueue[0];
        Yii::log('Input queue to parse: ' . print_r($queueItemToRender, true), 'debug', 'Helper');
        try {
            // Generate the input data

            if (Postprocessor::isValidation($model)) {
                // Special treatment: generate the to-be-validated form
                $inputData = Postprocessor::renderDataToBeValidated($queueItemToRender);
            } else {
                // Can take the submitted data as is. Fetch the actual string data, which is stored in the data's id='inputXY' field
                $inputDataVal = $queueItemToRender['data'];
                Yii::log('Render input data for item ' . $model->getItemInfo() . ' and flowItemId ' . $flowItemId . ': queue item to render has data '.print_r($inputDataVal, true), 'debug', 'Helper');
                if (is_array($inputDataVal)) {
                    // Go through each field in order to check if the Helper::$ORDERKEY_FIELD or the input data is stored
                    foreach ($inputDataVal as $inputDataField) {
                        $fieldId = $inputDataField['id'];
                        $fieldValue = $inputDataField['value'];
                        // TODO handle multiple inputs
                        $isInput = !strncmp($fieldId, 'input', strlen('input'));
                        if ($isInput) {
                            $inputData['input0'] = $fieldValue;
                        }
                        if ($fieldId == Helper::$ORDERKEY_FIELD) {
                            $inputData[Helper::$ORDERKEY_FIELD] = $fieldValue;
                        }
                    }
                } else {
                    // Data is not available as an array - take string isntead
                    Yii::log('Render input data for item ' . $model->getItemInfo() . ' and flowItemId ' . $flowItemId . ': the queue item to render\'s data is not an array, taking string instead ('.$inputDataVal.')', 'debug', 'Helper');
                    $inputData['input0']=$inputDataVal;
                }
                if (!array_key_exists('input0', $inputData)) {
                    // No data input string available
                    $msg = 'Failed to render input data for item ' . $model->getItemInfo() . ' and flowItemId ' . $flowItemId . ' - the queue item to render\'s data did not contain an \'input\' value that can be used as a task input: ' . print_r($inputDataVal, true);
                    Yii::log($msg, 'error', 'Helper');
                    throw new CHttpException(500, $msg);
                }
            }

            // Verify that actually an input was created
            if ($inputData == NULL) {
                // An error has occurred - the render function returned NULL
                $msg = 'Failed to render input data for item ' . $model->getItemInfo() . ' and flowItemId ' . $flowItemId . ' - rendering function returned NULL';
                Yii::log($msg, 'error', 'Helper');
                throw new CHttpException(500, $msg);
            }

            // Finally, return the rendered input
            return $inputData;

        } catch (Exception $e) {
            $msg = 'Failed to render input data for item ' . $model->getItemInfo() . ' : ' . $e->getMessage();
            Yii::log($msg, 'error', 'Helper');
            throw new CHttpException(500, $msg);
        }
        //End catch exception

    }

    // End function renderInputData

    //endregion Crowdsourcing-related functions

    //region Connection-related functions
    /**
     * Get the connection or connections with the specified parameter
     * @param $sourceTypeArg - Optional. If not set: get all connections, not just for a specific source type
     * @param $sourceId - Optional. If not set: get all connections, not just for a specific source item id
     * @param $targetTypeArg - Optional. If not set: get all connections, not just for a specific target type
     * @param $targetId - Optional. If not set: get all connections, not just for a specific target item id
     * @param $workspaceId
     * @return mixed An array with the keys 'sourceId', 'sourceType', 'targetId', 'targetType', 'workspaceId'
     * @throws CHttpException
     */
    public static function getConnection($sourceTypeArg, $sourceId, $targetTypeArg, $targetId, $workspaceId)
    {
        /*Yii::log('Get connections for sourceType ' . ($sourceTypeArg == NULL ? '<NULL>' : $sourceTypeArg)
        . ', sourceId ' . ($sourceId == NULL ? '<NULL>' : $sourceId)
        . ' , targetType ' . ($targetTypeArg == NULL ? '<NULL>' : $targetTypeArg)
        . ', targetId ' . ($targetId == NULL ? '<NULL>' : $targetId)
        . ', workspaceId ' . $workspaceId, 'debug', 'Helper');*/

        //region Handle/ read the source type
        $sourceTypes = array();
        if ($sourceTypeArg != NULL) {
            // Get connections for a specific source type
            $sourceTypes[] = strtolower($sourceTypeArg);
        } else {
            // Get connections for all source types
            $sourceTypes = Helper::getAllPossibleSourceTypesOfTargetType($targetTypeArg);
        }
        //endregion End handle the target type

        $allConnections = array();

        foreach ($sourceTypes as $sourceTypeIt) {
            $sourceType = strtolower($sourceTypeIt);

            //region Handle/ read the target type
            $targetTypes = array();
            if ($targetTypeArg != NULL) {
                // Get connections for a specific target type
                $targetTypes[] = strtolower($targetTypeArg);
            } else {
                // Get connections for all target types
                $targetId = NULL; // No target item identifier allowed in this case
                $targetTypes = Helper::getAllPossibleTargetTypesOfSourceType($sourceType);
            }
            //endregion End handle the target type


            // Fetch all connections for every possible target type
            foreach ($targetTypes as $targetTypeIt) {
                $targetType = strtolower($targetTypeIt);

                // Calculate the id columns
                if ($sourceType != $targetType) {
                    $sourceIdColumn = $sourceType . '_id';
                    $targetIdColumn = $targetType . '_id';
                } else {
                    // Connections between the same type: columns have 'source' and 'target' prefix
                    $sourceIdColumn = 'source_' . $sourceType . '_id';
                    $targetIdColumn = 'target_' . $targetType . '_id';
                }
                $table = strtolower($sourceType) . '_' . strtolower($targetType);

                // Restrict source items
                if ($sourceId == NULL)
                    $sourceRestrictionSubQuery = 'SELECT id FROM ' . $sourceType . ' WHERE workspace_id=\'' . $workspaceId . '\'';
                else
                    $sourceRestrictionSubQuery = '\'' . $sourceId . '\'';


                // Restrict target items
                // Note that in case of connections to workspaces, there is no need for an additional restriction (inner query). Instead, use the workspace id directly.
                if ($sourceType != 'workspace' && $targetType == 'workspace')
                    $targetRestrictionSubQuery = '\'' . $workspaceId . '\'';
                else if ($targetId == NULL && $targetType != 'workspace') {
                    $targetRestrictionSubQuery = 'SELECT id FROM ' . $targetType . ' WHERE workspace_id=\'' . $workspaceId . '\'';
                } else
                    $targetRestrictionSubQuery = '\'' . $targetId . '\'';

                // Create the SQL restrictions
                $sourceRestrictionSql = $sourceIdColumn . ' IN (' . $sourceRestrictionSubQuery . ')';
                $targetRestrictionSql = $targetIdColumn . ' IN (' . $targetRestrictionSubQuery . ')';

                $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $sourceRestrictionSql . ' AND ' . $targetRestrictionSql;

                $cmd = Yii::app()->db->createCommand($sql);
                $allRows = $cmd->queryAll();
                $result = array();
                if ($allRows === null)
                    throw new CHttpException(404, 'The requested page does not exist.');
                // Go through every result in order to set the appropriate sourceType and targetType (because this information is not available from the table since implicitly given by the table name)
                foreach ($allRows as $singleRow) {
                    $connection = array();
                    $connection['sourceId'] = $singleRow[$sourceIdColumn];
                    $connection['targetId'] = $singleRow[$targetIdColumn];
                    $connection['sourceType'] = $sourceType;
                    $connection['targetType'] = $targetType;
                    $result[] = $connection;
                }
                $allConnections = array_merge($allConnections, $result);
            }
            // End for each target type
        }
        // End for each source type

        return $allConnections;
    }

    //endregion Connection-related functions

    //region Item-related functions

    /**
     * Get an array of possible source types for connections that have the given type as destination
     * @param $targetType The target type of connections
     * @return array An array of all possible source types
     */
    public static function getAllPossibleSourceTypesOfTargetType($targetType = NULL)
    {
        $allTypes = Helper::getAllPossibleTargetTypesOfSourceType();

        if ($targetType == NULL) {
            // Return all possible item types, independently of the target type
            return $allTypes;
        }

        $targetType = ucfirst($targetType);
        $possibleSourceTypes = array();
        // Go through all item types
        foreach ($allTypes as $sourceType) {
            // Check if the item type would be a valid source for a connection to the targetType submitted as a parameter
            $inArray = in_array($targetType, Helper::getAllPossibleTargetTypesOfSourceType($sourceType));
//            Yii::log('Check if type ' . $targetType . ', is in the list of possible targets for source type ' . $sourceType . ': ' . ($inArray ? 'true' : 'false'), 'debug', 'Helper');
            if ($inArray) {
                // The target type asked for is a possible destination for connections of the current sourceType - thus, the current sourceType is a valid source type for the targetType
                $possibleSourceTypes[] = $sourceType;
            }
        }
//        Yii::log('Get all possible source types for target type ' . $targetType . ': possible types are ' . print_r($possibleSourceTypes, true), 'debug', 'Helper');
        return $possibleSourceTypes;
    }


    /**
     * Get an array of all possible target types for connections coming out of the given type
     * @param $sourceType - Optional. If not set: get all possible types
     * @return array An array of all possible target types
     */
    public static function getAllPossibleTargetTypesOfSourceType($sourceType = NULL)
    {
//        Yii::log('Get all possible target types of source type ' . ($sourceType == NULL ? '<NULL>' : $sourceType), 'debug', 'Helper');
        $types = NULL; // The result object
        if ($sourceType == NULL) {
            // Return all types that may have a connection
            $types = array('Task', 'Merger', 'Splitter', 'Postprocessor', 'Datasource');
//            Yii::log('All possible types for a connection are: ' . print_r($types, true), 'debug', 'ConnectionController');
        } else {
            // A source type was specified: the corresponding possible target objects must be returned
            $type = strtolower($sourceType);

            switch ($type) {
                case 'task':
                    $types = array('Task', 'Merger', 'Splitter', 'Postprocessor', 'Workspace');
                    break;
                case 'splitter':
                    $types = array('Task', 'Merger', 'Workspace');
                    break;
                case 'merger':
                    $types = array('Task', 'Merger', 'Workspace');
                    break;
                case 'datasource':
                    $types = array('Task');
                    break;
                case 'postprocessor':
                    $types = array('Task', 'Merger', 'Splitter', 'Workspace');
                    break;
                default:
                    $types = array();
                    break;
            }
            // End type switch
//            Yii::log('All possible connection target types for source type \'' . $sourceType . '\' are: ' . print_r($types, true), 'debug', 'ConnectionController');
        }
        // End a source type was indicated
        return $types;
    }

    //endregion Item-related functions
}