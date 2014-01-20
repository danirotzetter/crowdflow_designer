<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 23.08.13
 * Time: 10:19
 * To change this template use File | Settings | File Templates.
 */

/**
 * Represents an active record that can be crowd-sourced
 * Class CCsActiveRecord
 */
class CCsActiveRecord extends CActiveRecord
{

    public function beforeSave()
    {
        // Parse associative arrays to JSON strings
        if ($this->hasAttribute('parameters')) {
            $parameters = $this->parameters;
            if (!is_string($parameters) && (is_object($parameters) || is_array($parameters))) {
                $this->parameters = json_encode($parameters);
            };
        }


        // Initialize the platform data if not set
        if ($this->platform_data == NULL)
            $this->platform_data = array();


        // Jsonize the data variable, if needed
        if ($this->hasAttribute('data')) {
            $data = $this->data;
            if (!is_string($data) && (is_object($data) || is_array($data))) {
                $this->data = json_encode($data);
            };
        }
        if (!is_string($this->platform_data) && (is_object($this->platform_data) || is_array($this->platform_data)))
            $this->platform_data = json_encode($this->platform_data);
        if ($this->hasAttribute('input_queue')) {
            if (!is_string($this->input_queue) && (is_object($this->input_queue) || is_array($this->input_queue)))
                $this->input_queue = json_encode($this->input_queue);
        }
        if ($this->hasAttribute('processed_flowitems')) {
            if (!is_string($this->processed_flowitems) && (is_object($this->processed_flowitems) || is_array($this->processed_flowitems)))
                $this->processed_flowitems = json_encode($this->processed_flowitems);
        }

        return parent::beforeSave();

    }

    public function afterFind()
    {
        parent::afterFind();

        // Initialize different properties and parse them to an associative array

        // Initialize the parameters
        if ($this->hasAttribute('parameters')) {
            $this->parameters = json_decode($this->parameters, true);
        }

        // Initialize the platform data if not set
        $pd = $this->platform_data;
        if (is_string($pd))
            $pd = json_decode($pd, true);
        if ($pd == NULL || $pd == 'null') {
            $pd = array();
        }
        // Initialize an empty array for the platform tasks
        if (!array_key_exists('tasks', $pd)) {
            $pd['tasks'] = array();
        }
        // Initialize the assignments tracking
        if (!array_key_exists('pendingAssignments', $pd))
            $pd['pendingAssignments'] = array();
        if (!array_key_exists('acceptedAssignments', $pd))
            $pd['acceptedAssignments'] = array();
        if (!array_key_exists('rejectedAssignments', $pd))
            $pd['rejectedAssignments'] = array();
        $this->platform_data = $pd;

        // Initialize the input queue
        if ($this->hasAttribute('input_queue')) {
            $inputQueue = $this->input_queue;
            if (is_string($this->input_queue))
                $inputQueue = json_decode($this->input_queue, true);
            if ($inputQueue == NULL || $inputQueue == 'null')
                $inputQueue = array();
            $this->input_queue = $inputQueue;
        }

        // Un-Jsonize the data variable, if the model has this attribute and if the data is json-encoded
        $hasDataAttribute = $this->hasAttribute('data');
        if ($hasDataAttribute) {
            $decodedData = $this->data;
            if (is_string($decodedData))
                $decodedData = json_decode($decodedData, true);
            if ($decodedData == null) {
                // No need to json-decode the data
            } else {
                // Continue with the json-decoded data
                $this->data = $decodedData;
            }
        }


        // Generate an empty array in case no processed assignments were defined in the database
        if ($this->hasAttribute('processed_flowitems')) {
            $processedFlowItems = $this->processed_flowitems;
            if ($processedFlowItems == NULL || $processedFlowItems == 'null')
                $processedFlowItems = array();
            if (is_string($processedFlowItems))
                $processedFlowItems = json_decode($processedFlowItems, true);
            $this->processed_flowitems = $processedFlowItems;
        }

        Yii::log('AfterFind completed: item ' . $this->getItemInfo() . ' has now platform data ' . print_r($this->platform_data, true), 'debug', 'CCsActiveRecord');
    }

    /**
     * Get a very short information about the item in a human-readable way
     * @return The identifier and type of the item
     */
    public
    function getItemInfo()
    {
        return 'item ' . $this->id . ' of type \'' . get_class($this) . '\'';
    }

    /**
     * Optionally, items can overwrite this class to perform input queue operations.
     * For example, a splitter might overwrite this function to split each input queue
     * item into multiple items and add them to the input queue of the subsequent item.
     * @param $errors array The reference to the array where errors can be stored
     * @return the items from the input queue that were processed
     */
    public
    function processInputQueue(&$errors)
    {
        $type = get_class($this);
        Yii::log('ProcessInputQueue for ' . $this->getItemInfo() . ' - this function is not overwritten by the ' . $type . ' class, hence nothing is done here', 'debug', 'CCsActiveRecord');
        return array();
    }

    /**
     * From the input queue: only these items are returned whose input was generated by the specified flowItemId
     * @param $flowItemId The identifier of the flowItem that served as an input for the submitted assignments in the inputQueue
     * @return array The items in the inputQueue that were submitted for the specified flowItem
     */
    public
    function getInputQueueItemsForFlowItem($flowItemId)
    {
        Yii::log('About to find all queue items that were submitted for text transformation (flowItemId ' . $flowItemId . ') in the queue ' . print_r($this->input_queue, true), 'debug', 'Merger');
        $itemsFound = array();
        foreach ($this->input_queue as $inputQueueItem) {
            if (array_key_exists('flowItemId', $inputQueueItem)) {
                // The flowItemId attribute is directly accessible in the inputQueue item
                if ($inputQueueItem['flowItemId'] == $flowItemId) {
                    // Found a matching flowItemId
                    $itemsFound[] = $inputQueueItem;
                } else {
                    // Found a 'wrong'/ not matching flowItemId
                    continue;
                }
            } else {
                // The flowItemId reference is 'hidden' in a form that was crowd-sourced: search the corresponding field that was submitted in the form
                if (array_key_exists('data', $inputQueueItem)) {
                    // Not even the data attribute is available - it is impossible that this is the inputQueueItem we are looking for
                    continue;
                }
                foreach ($inputQueueItem['data'] as $queueItemField) {
                    if ($queueItemField['id'] == 'flowItemId' && $queueItemField['value'] == $flowItemId) {
                        // Found an item in the input queue that was submitted for the searched original
                        $itemsFound[] = $inputQueueItem;
                    }
                }
            }
        }
        return $itemsFound;
    }


    /**
     * Forwards all assignments that were submitted for the current item to the subsequent item. For example, if a text was translated by the crowd,
     * then the translated text is added to the input queue of the following item (which e.g. could be a merger).
     * This method can be overwritten for custom implementation. A reason for doing so might be that not every assignment must be forwarded immediately,
     * but only a selection of the available assignments (typically this is the case for best-option selections, where only the assignment that was
     * selected most often is actually being used and forwarded to the subsequent item's input queue)
     * @param $approvedParsedAssignments array The parsed assignments that were approved. These are ALL assignments, for ALL items. Hence, the method should check itself
     * whether an assignment from the argument array is valid for the current item.
     * @return array The assignments that were actually forwarded to the subsequent item's input queue
     */
    public
    function forwardAssignmentsToSubsequentItem($approvedParsedAssignments)
    {
        Yii::log('About to forward ' . sizeof($approvedParsedAssignments) . ' assignments to subsequent item for ' . $this->getItemInfo(), 'debug', 'CCsActiveRecord');

        $forwardedAssignmentsFromThisItem = array();

        // Out of all assignments that were approved: find the assignments that were for the current item
        foreach ($approvedParsedAssignments as $approvedParsedAssignment) {
        Yii::log('Forwarding an assignments to subsequent item for ' . $this->getItemInfo().': '.print_r($approvedParsedAssignment, true), 'debug', 'CCsActiveRecord');
            if ($approvedParsedAssignment['itemId'] == $this->id && strtolower($approvedParsedAssignment['itemType']) == strtolower(get_class($this))) {
                // The assignment is for the current item

                // We have first to make sure, that the flowItemId is supplied
                $flowItemId = null;
                if (array_key_exists('flowItemId', $approvedParsedAssignment))
                    $flowItemId = $approvedParsedAssignment['flowItemId'];
                else {
                    // Get the flowItemId from the form data
                    foreach ($approvedParsedAssignment['data'] as $field) {
                        if ($field['id'] == 'flowItemId')
                            $flowItemId = $field['value'];
                    }
                }
                if ($flowItemId == null) {
                    $msg = 'Cannot update list of processed assignments for validator: no flowItemId was found for an assignment of ' . $this->getItemInfo();
                    Yii::log($msg, 'error', 'CCsActiveRecord');
                    $errors[] = $msg;
                    continue;
                }

                $this->refresh();

                if (Postprocessor::isValidation($this)) {
                    /*
                     * Skip validators. Validators' crowd assignments are accepted automatically. But these assignments
                     * are not further treated, because they are only there to accept an assignment from the validator's input item.
                     * For example, a task is validated by a postprocessor. The task's assignment will then be either accepted or rejected,
                     * depending on the assignment OF THE POSTPROCESSOR. Thus, the assignment that should be forwarded to the subsequent item
                     * of the postprocessor is THE ORIGINAL TAKS ASSIGNMENT, and not the postprocessor's assignment. Hence, in this case,
                     * the assignment of the postprocessor can simply be ignored, because forwarding the original task's assignment to the subsequent
                     * item of the postprocessor is  executed in the method 'processAssignmentValidations()'.
                     * If the subsequent 'continue' statement were ignored, then the postprocessor's assignment would be included in the macrotask flow instead
                     * of the assignments from the postprocessor's input.
                     *
                     * One exception is the handling of processed assignments. Whilst for non-validation items, this is done in the addToInputQueue method, this very method
                     * will not be called to validation items and hence must be done at this place
                     */
                    $assignmentId = $approvedParsedAssignment['platformResultId'];
                    $pd = $this->platform_data;


                    Yii::log('Updating validator\'s list of processed assignments', 'debug', 'CCsActiveRecord');
                    // Finally, the list of processed assignments can be updated
                    $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItemId, $assignmentId);
                    $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $assignmentId);
                    $this->platform_data = json_encode($pd);
                    $this->saveAttributes(array('platform_data'));
                    $this->platform_data = json_decode($this->platform_data, true);
                    continue;
                }

                /*
                 * Do not process again an item that was rejected by the crowd. The assignment may still have the 'approved' state on
                 * the crowd-sourcing platform, since the worker was paid. However, this does not mean that the webApplication accepted the result.
                 * In order to check what the crowd of the webApplication decided, we have to check the list of 'rejectedAssignments'.
                 * If the assignment is listed there, then it was paid but logically discarded, which means that it should not be forwarded to the subsequent item.
                 */
                $pd = $this->platform_data;
                if (is_string($pd))
                    $pd = json_decode($pd, true);
                $assignmentId = $approvedParsedAssignment['platformResultId'];
                Yii::log('Checking whether assignment ' . $assignmentId . ' for flowItemId ' . $flowItemId . ' must be forwarded to the subsequent item by evaluating the list of processed assignments ' . print_r($pd, true), 'debug', 'CCsActiveRecord');
                $wasRejected = array_key_exists($flowItemId, $pd['rejectedAssignments']) && in_array($assignmentId, $pd['rejectedAssignments'][$flowItemId]);
                if ($wasRejected) {
                    Yii::log('Not adding the assignment ' . $assignmentId . ' for flowItemId ' . $flowItemId . ' from ' . $this->getItemInfo() . ' to the subsequent item, as this is ignored: it was paid, but actually already rejected by the crowd', 'debug', 'CCsActiveRecord');
                    continue;
                }
                $wasAccepted = array_key_exists($flowItemId, $pd['acceptedAssignments']) && in_array($assignmentId, $pd['acceptedAssignments'][$flowItemId]);
                if ($wasAccepted) {
                    Yii::log('Not adding the assignment ' . $assignmentId . ' for flowItemId ' . $flowItemId . ' from ' . $this->getItemInfo() . ' to the subsequent item, as this is ignored: it was already accepted', 'debug', 'CCsActiveRecord');
                    continue;
                }


                $subsequentItem = Helper::getOutputItem($this);

                // Verify that the item was not processed in the subsequent item
                $siq = $subsequentItem->getInputQueueItemsForFlowItem($flowItemId);
                $inSubsequentItem = false;
                if (sizeof($siq) > 0) {
                    foreach ($siq as $subsequentInputQueueItem) {
                        if (array_key_exists('platformResultId', $subsequentInputQueueItem) && $subsequentInputQueueItem['platformResultId'] == $assignmentId) {
                            $inSubsequentItem = true;
                            break;
                        }
                    }
                }
                if ($inSubsequentItem) {
                    // Item is already in the subsequent item
                    Yii::log('Not adding the assignment ' . $assignmentId . ' for flowItemId ' . $flowItemId . ' from ' . $this->getItemInfo() . ' to the subsequent item, it was already forwarded to the subsequent ' . $subsequentItem->getItemInfo(), 'debug', 'CCsActiveRecord');
                    continue;
                }

                Yii::log('Now adding assignment ' . $assignmentId . ' from ' . $this->getItemInfo() . ' to the subsequent item - is not in the list of rejected assignments ' . print_r($pd['rejectedAssignments'], true), 'debug', 'CCsActiveRecord');
                $addedToSubsequentItem = FlowManagement::addToInputQueue($subsequentItem, $approvedParsedAssignment);

                // If this procedure was successful, we can adjust the list of accepted and pending assignments
                $pd = $this->platform_data;
                $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItemId, $assignmentId);
                $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $assignmentId);

                $this->platform_data = json_encode($pd);
                $this->saveAttributes(array('platform_data'));
                $this->platform_data = $pd;

                $forwardedAssignmentsFromThisItem = array_merge($forwardedAssignmentsFromThisItem, $addedToSubsequentItem);
            }
            // End assignment is for the current item
        }
        // End for each approved, parsed assignment

        return $forwardedAssignmentsFromThisItem;
    }


    /**
     * Get the number of times the current item must be published in order to comply with the rules given for this item AND thereby respecting the minimum and maximum publications for the supplied flowItemId
     * @param $flowItemId The flowItemId that must be within the limits of min and max publications
     * @param $input_queue Array The item's input queue
     * @return int The number of times this item must be published
     */
    public function getPublicationCount($flowItemId, $input_queue = array())
    {
        $this->refresh();

        Yii::log('GetPublicationCount: evaluating how often ' . $this->getItemInfo(). ' must be published for flowItemId '.$flowItemId, 'debug', 'CCsActiveRecord');

        if (!FlowManagement::isCrowdsourced($this)) {
            Yii::log('GetPublicationCount: no need to publish ' . $this->getItemInfo() . ' - is not crowd-sourced', 'debug', 'CCsActiveRecord');
            return 0;
        }
        if (!$this->workspace->publish) {
            Yii::log('GetPublicationCount: no need to publish ' . $this->getItemInfo() . ' - workspace is not active', 'debug', 'CCsActiveRecord');
            return 0;
        }

        $pd = $this->platform_data;
        if (is_string($pd))
            $pd = json_decode($pd, true);

        $parameters = $this->parameters;
        if (is_string($parameters))
            $parameters = json_decode($parameters, true);
        $maxAssignments = array_key_exists('max_assignments', $parameters) ? $parameters['max_assignments'] : 1;
        $minAssignments = array_key_exists('min_assignments', $parameters) ? $parameters['min_assignments'] : 1;



        // Pending assignments
        $nbAssignmentsPendingThisItem = 0;
        $nbAssignmentsPendingAllItems = 0;
        foreach ($pd['pendingAssignments'] as $assignmentFlowItemId => $submittedAssignments)
            $nbAssignmentsPendingAllItems += sizeof($submittedAssignments);

        if (array_key_exists($flowItemId, $pd['pendingAssignments'])) {
            $nbAssignmentsPendingThisItem = sizeof($pd['pendingAssignments'][$flowItemId]);
        }


        // Rejected assignments
        $nbAssignmentsRejectedThisItem = 0;
        $nbAssignmentsRejectedAllItems = 0;
        foreach ($pd['rejectedAssignments'] as $assignmentFlowItemId => $submittedAssignments)
            $nbAssignmentsRejectedAllItems += sizeof($submittedAssignments);
        if (array_key_exists($flowItemId, $pd['rejectedAssignments'])) {
            $nbAssignmentsRejectedThisItem = sizeof($pd['rejectedAssignments'][$flowItemId]);
        }



        // Accepted assignments
        $nbAssignmentsAcceptedThisItem = 0;
        $nbAssignmentsAcceptedAllItems = 0;
        foreach ($pd['acceptedAssignments'] as $assignmentFlowItemId => $submittedAssignments)
            $nbAssignmentsAcceptedAllItems += sizeof($submittedAssignments);

        if (array_key_exists($flowItemId, $pd['acceptedAssignments'])) {
            $nbAssignmentsAcceptedThisItem = sizeof($pd['acceptedAssignments'][$flowItemId]);
            /*
        * Special case for validators: in this case, we have to make sure that the assignment was accepted 'truly'
         * (as opposed to paying the assignment but rejecting its answer). The $nbAssignmentsAcceptedThisItem must thus
         * be decreased by the amount of assignments that were actually rejected.
         * Be careful here: the $nbAssignmentsAcceptedThisItem refers to the processing of flowItems for the validator, whereas
         * the subsequent number of the validator, whereas $nbAssignmentsRejectedPrecedentItem is the list of the processed flowItems
         * in the 'original' task
        */
            if (Postprocessor::isValidation($this)) {
                $nbAssignmentsRejectedPrecedentItem = 0;
                Yii::log('GetPublicationCount: ' . $this->getItemInfo() . ' is a validator. Must decrease the number of accepted assignments by the number of rejected assignments of the \'actual\' (precedent) item, in order to take into account the assignments that were paid but logically rejected for flowItemId ' . $flowItemId, 'debug', 'CCsActiveRecord');

                $previousItem = Helper::getInputItem($this);
                if ($previousItem == null) {
                    // Error finding previous item - not modifying the number of accepted assignments
                    Yii::log('GetPublicationCount: ' . $this->getItemInfo() . ' is a validator, but the previous item could not be found - thus not reducing the number of accepted assignments for flowItemId ' . $flowItemId, 'debug', 'CCsActiveRecord');
                } else {
                    // Found the previous item
                    $previousItemPd = $previousItem->platform_data;
                    if (is_string($previousItemPd))
                        $previousItemPd = json_decode($previousItemPd, true);
                    if (array_key_exists('rejectedAssignments', $previousItemPd) && array_key_exists($flowItemId, $previousItemPd['rejectedAssignments'])) {
                        // List of rejected assignments available in the previous item
                        $nbAssignmentsRejectedPrecedentItem = sizeof($previousItemPd['rejectedAssignments'][$flowItemId]);
                    } else {
                        // No list of rejected assignments available in the previous item
                    }
                    Yii::log('GetPublicationCount: ' . $this->getItemInfo() . ' is a validator - reducing the number of accepted assignments from ' . $nbAssignmentsAcceptedThisItem . ' by ' . $nbAssignmentsRejectedPrecedentItem.' and increment by the same amount the list of rejected assignments from '.$nbAssignmentsRejectedThisItem, 'debug', 'CCsActiveRecord');
                    $nbAssignmentsAcceptedThisItem= $nbAssignmentsAcceptedThisItem - $nbAssignmentsRejectedPrecedentItem;
                    $nbAssignmentsRejectedThisItem= $nbAssignmentsRejectedThisItem + $nbAssignmentsRejectedPrecedentItem;
                }
            }
        }


        // Total
        $nbAssignmentsTotalThisItem = $nbAssignmentsAcceptedThisItem + $nbAssignmentsPendingThisItem + $nbAssignmentsRejectedThisItem;
        $nbAssignmentsTotalAllItems = $nbAssignmentsAcceptedAllItems + $nbAssignmentsPendingAllItems + $nbAssignmentsRejectedAllItems;



        //Count the number of publications
        if (!array_key_exists('tasks', $pd))
            $pd['tasks'] = array();
        $nbPublishedSoFarAllItems = sizeof($pd['tasks']);
        $nbPublishedSoFarThisItem = 0;
        foreach ($pd['tasks'] as $task) {
            Yii::log('GetPublicationCount: Check if flowItemId ' . $flowItemId . ' is published in task ' . print_r($task, true), 'CCsActiveRecord');
            if ($task['flowItemId'] == $flowItemId) {
                $assignmentsPublished = $task['max_assignments'];
                Yii::log('GetPublicationCount: flowItemId ' . $flowItemId . ' has already a crowd-sourced task accepting ' . $assignmentsPublished . ' assignments', 'CCsActiveRecord');
                $nbPublishedSoFarThisItem += $assignmentsPublished;
            }
        }

        // Treat the pending published tasks
        $nbInInputQueueThisItem = 0;

        // Input is dynamic: evaluate the inputQueue
        Yii::log('GetPublicationCount: counting how many of the ' . sizeof($input_queue) . ' inputQueueItems are pending for flowItemId ' . $flowItemId, 'debug', 'CCsActiveRecord');

        // Must iterate over all inputQueue items in order to count the number of published tasks for the current flowItemId
        foreach ($input_queue as $inputQueueItem) {
            Yii::log('GetPublicationCount: check if flowItemId ' . $flowItemId . ' is present in inputQueueItem ' . print_r($inputQueueItem, true), 'debug', 'CCsActiveRecord');
            if (array_key_exists('flowItemId', $inputQueueItem) && $inputQueueItem['flowItemId'] == $flowItemId)
                $nbInInputQueueThisItem++;
        }


        $nbPossibleUntilMaximumReached = $maxAssignments - $nbPublishedSoFarThisItem;
        $nbRequiredUntilMinimumAcceptedReached = $minAssignments - $nbAssignmentsAcceptedThisItem;
        $nbPublishedButNotSubmittedThisItem = $nbPublishedSoFarThisItem - $nbAssignmentsTotalThisItem;
        $nbPossiblyAcceptedInFutureThisItem = $nbPublishedButNotSubmittedThisItem + $nbAssignmentsPendingThisItem;
        $nbRemainingIfPendingTasksAreAccepted = $nbRequiredUntilMinimumAcceptedReached - $nbPossiblyAcceptedInFutureThisItem;
        if ($nbAssignmentsPendingThisItem > 0) {
            /*
            * An inconvenient situation occurs if some workers have accepted the task first, but not submitted it. In this case,
             * an entry for this flowItemId was already added to the 'pendingAssignments' list by the WebApplication,
             * even though the assignment is not actually pending since it will never be submitted by the worker.
             * Hence, we have to find the erroneous 'zombie' assignments
            */
            $pendingAssignmentsList = array();

            // Browse through the pendingAssignments of this very flowItemId, if any available
            if (array_key_exists($flowItemId, $pd['pendingAssignments']))
                $pendingAssignmentsList = $pd['pendingAssignments'][$flowItemId];

            Yii::log('GetPublicationCount: Cleaning up list of pending assignments on ' . $this->getItemInfo() . ': for the list ' . print_r($pendingAssignmentsList, true), 'debug', 'CCsActiveRecord');
            $comp = Helper::getCurrentPlatformComponent();
            $assignmentIdsNotFound = array(); // Keep track of the assignmentIds that were not found on the crowd-sourcing platform
            foreach ($pendingAssignmentsList as $assignmentPending) {
                if ($comp->getAssignmentStatus($assignmentPending) == 'notfound') {
                    Yii::log('GetPublicationCount: assignment ' . $assignmentPending . ' was not found on the crowd-sourcing platform for flowItemId ' . $flowItemId . ' on ' . $this->getItemInfo(), 'debug', 'CCsActiveRecord');
                    $assignmentIdsNotFound[] = $assignmentPending;
                }
            }
            if (sizeof($assignmentIdsNotFound) > 0) {
                // Found at least one assignment that is not present on the platform: adjust the list of pendingAssignments accordingly by removing these assignmentIds from the list
                Yii::log('GetPublicationCount: removing assignments ' . print_r($assignmentIdsNotFound, true) . ' from the list ' . print_r($pendingAssignmentsList, true), 'debug', 'CCsActiveRecord');
            }

            // Fulfill the recorded pending updates
            $replacementList = array_diff($pendingAssignmentsList, $assignmentIdsNotFound);
            Yii::log('GetPublicationCount: replacing the list of pending assignments ' . print_r($pendingAssignmentsList, true) . ' with ' . print_r($replacementList, true), 'debug', 'CCsActiveRecord');
            $pd['pendingAssignments'][$flowItemId] = $replacementList;


            // Update the new list
            $this->platform_data = json_encode($pd);
            $this->saveAttributes(array('platform_data'));
            $this->platform_data = $pd;
        }
        // End pending assignments found


        $nbToPublish = 0;
        // Can only publish the item if there is at least one item in the input queue
        if ($nbInInputQueueThisItem > 0) {
            $nbToPublish = min($nbPossibleUntilMaximumReached, $nbRemainingIfPendingTasksAreAccepted);
        }

        // Handle negative values
        if ($nbToPublish < 0) {
            Yii::log('GetPublicationCount: calculated that ' . $this->getItemInfo() . ' for flowItemId ' . $flowItemId . ' must be published ' . $nbToPublish . ' times. Resetting this value to 0', 'error', 'CCsActiveRecord');
            $nbToPublish = 0;
        }

        // Log the analysis
        Yii::log('GetPublicationCount: flowItem ' . $flowItemId . ' in ' . $this->getItemInfo() . ' has a minimum of ' . $minAssignments . ', a maximum of ' . $maxAssignments . '. '
        . 'This item ' . $this->getItemInfo() . ' has been published ' . $nbPublishedSoFarThisItem . ' times so far and received ' . $nbAssignmentsTotalThisItem . ' assignments '
        . '(' . $nbAssignmentsPendingThisItem . ' pending, ' . $nbAssignmentsAcceptedThisItem . ' accepted, ' . $nbAssignmentsRejectedThisItem . ' rejected). '
        . 'In the inputQueue, ' . $nbInInputQueueThisItem . ' items are pending for this flowItem. '
        . 'Can publish up to ' . $nbPossibleUntilMaximumReached . ' times until the maximum is reached, and must be published at least '
        . $nbRequiredUntilMinimumAcceptedReached . ' times until the minimum is reached. '
        . $nbPublishedButNotSubmittedThisItem . ' items have been published but not yet been executed on the crowd for this flowItem. '
        . 'Theoretically, ' . $nbPossiblyAcceptedInFutureThisItem . ' assignments could be accepted in the future for this flowItem, '
        . $nbRemainingIfPendingTasksAreAccepted . ' publications are required assuming all these pending and published items are accepted. '
        . 'In this step, it will thus be published ' . $nbToPublish . ' times', 'debug', 'CCsActiveRecord');

        return $nbToPublish;
    }

}