<?php
/**
 * Implements methods to control the flow management
 *
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 08.08.13
 * Time: 21:26
 */

class FlowManagement
{

    //region General item-related functions
    public static function getAllItems($associativeArray = false)
    {
        $tasks = Task::model()->findAll();
        $postprocessors = Postprocessor::model()->findAll();
        $mergers = Merger::model()->findAll();
        $splitters = Splitter::model()->findAll();
        $datasources = Datasource::model()->findAll();

        if ($associativeArray) {
            $items = array(
                'task' => $tasks,
                'postprocessor' => $postprocessors,
                'merger' => $mergers,
                'splitter' => $splitters,
                'datasources' => $datasources
            );
            Yii::log('Got all items. Total number: ' . sizeof($items['task']) . ' tasks, ' . sizeof($items['merger']) . ' mergers, ' . sizeof($items['splitter']) . ' splitters, ' . sizeof($items['postprocessor']) . ' postprocessors, ' . sizeof($items['datasource']) . ' datasources', 'debug', 'FlowManagement');
        } else {
            $items = array_merge($tasks, $postprocessors, $mergers, $splitters, $datasources);
            Yii::log('Got all items. Total number: ' . sizeof($items), 'debug', 'FlowManagement');
        }
        return $items;
    }

    /**
     * Get all items of the WebApplication that are crowd-sourced
     * @param bool flattened Whether the items should be returned in an associative array of type=>arrayOfItemsOfThisType (instead of a one-dimensional array of items)
     * @return Array The item models that are crowd-sourced
     */
    public static function getItemsWaitingForCrowdInput($associativeArray = false)
    {
        Yii::log('Get all items waiting for crowd input', 'debug', 'FlowManagement');

        // Initialize the objects and retrieve all items that come into question
        $items = NULL;
        if ($associativeArray) {
            // Result is an associative array
            $items = array('task' => array(), 'postprocessor' => array(), 'merger' => array(), 'splitter' => array());
        } else {
            // Result is a flat array
            $items = array();
        }

        Yii::log('Got all items from the database - now checking if they depend on the crowd', 'debug', 'FlowManagement');

        $allItems = FlowManagement::getAllItems();

        // Find all items that actually do depend on the crowd
        foreach ($allItems as $item) {
            if (FlowManagement::isCrowdsourced($item)) {
                if ($associativeArray) {
                    $type = strtolower(get_class($item));
                    $items[$type][] = $item;
                } else {
                    $items[] = $item;
                }
            }
        }

        if ($associativeArray) {
            Yii::log('Number of items waiting for crowd input: ' . sizeof($items['task']) . ' tasks, ' . sizeof($items['merger']) . ' mergers, ' . sizeof($items['splitter']) . ' splitters, ' . sizeof($items['postprocessor']) . ' postprocessors, ', 'debug', 'FlowManagement');
        } else {
            Yii::log('Number of items waiting for crowd input: ' . sizeof($items), 'debug', 'FlowManagement');
        }
        return $items;
    }


    //endregion General item-related functions

    //region Flow-control related functions

    /**
     * Removes the submitted assignments from the input queue of the submitted item.
     * @param $item The item from whose input queue the assignments should be removed
     * @param $flowItems The flowItem or the array of flowItems that must be removed
     * @return array The assignments that were removed from the item's input queue
     */
    public static function removeFromInputQueue($item, $flowItems)
    {
// If the submitted assignment is a single item: wrap it in an array for further uniform processing
        $isSingleFlowItem = array_key_exists('flowItemId', $flowItems) || array_key_exists('data', $flowItems) || array_key_exists('itemId', $flowItems); // An item that is added to the inputQueue will contain at least one of the specified properties/ keys
        $flowItemsArray = $isSingleFlowItem ? array($flowItems) : $flowItems;


        $item->refresh();
        $iq = $item->input_queue;

        Yii::log('Removing ' . sizeof($flowItemsArray) . ' flowItems from the input queue of ' . $item->getItemInfo() . ': items ' . print_r($flowItemsArray, true) . ', from the input queue of size ' . sizeof($iq), 'debug', 'FlowManagement');

        $removedItems = array();

        // Process every flowItem that must be removed
        foreach ($flowItemsArray as $flowItemToRemove) {
            // In order to remove the flowItem from the input queue, we have to find its index in the inputQueue array
            $indexOfItemInQueueToRemove = -1;
            Yii::log('Item to remove: ' . print_r($flowItemToRemove, true), 'debug', 'FlowManagement');
            $flowItemId = $flowItemToRemove['flowItemId'];
            for ($i = 0; $i < sizeof($iq); $i++) {
                $isItemToRemove = $iq[$i]['flowItemId'] == $flowItemId &&
                    (!array_key_exists('platformResultId', $iq[$i]) || // If an platformResultId exists, it must be present in both the inputQueue's item and the flowItem to be removed
                        (array_key_exists('platformResultId', $flowItemToRemove) && $iq[$i]['platformResultId'] == $flowItemToRemove['platformResultId']));
                if ($isItemToRemove)
                    $indexOfItemInQueueToRemove = $i;
            }

            // Then, we can unset the value
            if ($indexOfItemInQueueToRemove > -1) {
                unset($iq[$indexOfItemInQueueToRemove]);
                $iq = array_values($iq); // array_values removes the key which now as a NULL value and shifts all subsequent items in the input_queue such that there is no gap anymore
                // Store the updated input queue
                $item->input_queue = json_encode($iq);
                $item->saveAttributes(array('input_queue'));
                $removedItems[] = $flowItemToRemove;
                Yii::log('Successfully removed the flowItem with Id ' . $flowItemId . ' from the input queue of ' . $item->getItemInfo() . ': input queue index ' . $indexOfItemInQueueToRemove, 'debug', 'FlowManagement');
            } else {
                Yii::log('Could not remove the flowItem with Id ' . $flowItemId . ' from the input queue of ' . $item->getItemInfo() . ': could not find the assignment in the input queue', 'debug', 'FlowManagement');
            }
        }
        // End for each flowItem to be removed
        return $removedItems;
    }


    /**
     * Adds the submitted assignments to the input queue of the submitted item. Only adds the flowItem if this was not done before (thus respects and updates the list of flowItems that were already
     * treated/ added to the submitted item)
     * @param $item The item to whose input queue the assignments should be added
     * @param $flowItems The flowItem or the array of flowItems that must be added
     * @return array The assignments that were added to the item's input queue
     */
    public static function addToInputQueue($item, $flowItems)
    {

        // If the submitted assignment is a single item: wrap it in an array for further uniform processing
        $isSingleFlowItem = array_key_exists('flowItemId', $flowItems) || array_key_exists('data', $flowItems) || array_key_exists('itemId', $flowItems); // An item that is added to the inputQueue will contain at least one of the specified properties/ keys
        $flowItemsArray = $isSingleFlowItem ? array($flowItems) : $flowItems;

        Yii::log('Adding ' . sizeof($flowItemsArray) . ' flowItems to the input queue of ' . $item->getItemInfo() . ': ' . print_r($flowItems, true), 'debug', 'FlowManagement');

        // Do only process the flowItem that were not processed already
        $flowItemsToAdd = array();
        $processedFlowItems = $item->processed_flowitems;
        if ($processedFlowItems == null)
            $processedFlowItems = array();
        foreach ($flowItemsArray as $flowItem) {
            // If the flow item does not yet have a unique flowItem identifier: generate one
            $flowItemId = -1;
            if (array_key_exists('flowItemId', $flowItem)) {
                // Has already a flowItem identifier
                $flowItemId = $flowItem['flowItemId'];
            } else {
                // Does not yet have a flowItem identifier: check if one has been generated in a crowd-submitted form
                if (array_key_exists('data', $flowItem)) {
                    foreach ($flowItem['data'] as $formField) {
                        if ($formField['id'] == 'flowItemId') {
                            /*
                             * The unique flow item identifier has been generated by a form that was published to the crowd. See file base_form_header.php
                             */
                            $flowItemId = $formField['value'];
                        }
                    }
                }
                // Set the identifier in the flowItem to track it
                $flowItem['flowItemId'] = $flowItemId;
            }

            $flowItemsToAdd[] = $flowItem;
        }

        Yii::log('About to add ' . sizeof($flowItemsToAdd) . ' flowItems to the input queue of ' . $item->getItemInfo(), 'debug', 'FlowManagement');

        // Update the input queue
        $iq = $item->input_queue;
        if (is_string($iq)){
            Yii::log('Decoding input queue for '.$item->getItemInfo(), 'debug', 'FlowManagement');
            $iq = json_decode($iq, true);
        }
        if ($iq == NULL || $iq == ''){
            Yii::log('Replacing the entire inputQueue (which was empty before) for '.$item->getItemInfo(), 'debug', 'FlowManagement');
            $iq = $flowItemsToAdd;
        }
        else{
            Yii::log('Merging the objects of the old inputQueue (of size '.sizeof($iq).') with the new inputQueue (of size '.sizeof($flowItemsToAdd).') for '.$item->getItemInfo(), 'debug', 'FlowManagement');
            $iq = array_merge($iq, $flowItemsToAdd);
        }

        YIi::log('Adding to input queue of ' . $item->getItemInfo() . ': is now ' . print_r($iq, true), 'debug', 'FlowManagement');

        // Update the processed flowItem ids
        foreach ($flowItemsToAdd as $flowItemToAdd) {
            $flowItemIdToAdd = $flowItemToAdd['flowItemId'];
            if (!in_array($flowItemIdToAdd, $processedFlowItems))
                $processedFlowItems[] = $flowItemIdToAdd;
            Yii::log('Processed flowItem ' . $flowItemIdToAdd, 'debug', 'FlowManagement');
        }


        // Store the input queue and processed assignment changes to the database
        if (sizeof($flowItemsToAdd) > 0) {
            $item->input_queue = json_encode($iq);
            $item->processed_flowitems = json_encode($processedFlowItems);
            $item->saveAttributes(array('input_queue', 'processed_flowitems'));
            $item->input_queue = $iq;
            $item->processed_flowitems = $processedFlowItems;
        }
        // End updating input queue

        $nbAdded = sizeof($flowItemsToAdd);
        Yii::log('Successfully added ' . $nbAdded . ' flowItems to the input queue of ' . $item->getItemInfo(), 'debug', 'FlowManagement');
        return $flowItemsToAdd;
    }

    /**
     * Automatically accepts all answers in the crowd for items that do not require validation
     * @param $items
     * @param $errors array The reference array to which errors are stored
     * @return array The parsed assignments that were accepted
     */
    public static function acceptNonValidatedItems($items, &$errors)
    {
        // Get all items coming into question
        Yii::log('About to accept crowd-sourced items that do not have a postprocessor', 'debug', 'FlowController');

        $validatedAssignments = array(); // Keep track of the automatically accepted answers

        $comp = Helper::getCurrentPlatformComponent();
        // Go through every item in order to accept all submitted assignments
        foreach ($items as $item) {
            /*
             *  Skip item if it has a validation destination. In this case, the validation must not be done
             * automatically, since the assignment is either approved or rejected, depending on the validator!
            */
            if (Postprocessor::itemHasValidationDestination($item)) {
                continue;
            }
            /*
             * Note that validation assignments are accepted automatically, because they will be used to either accept or reject another crowd-sourced item.
             */
            $resultsPending = $comp->getTaskResults($item, 'submitted');
            Yii::log('About to accept any answers for ' . $item->getItemInfo(), 'debug', 'FlowController');
            if ($resultsPending['success'] == true) {
                // Could fetch crowd-sourced results for the current item
                $assignments = $resultsPending['data'];
                Yii::log('About to automatically accept ' . sizeof($assignments) . ' crowd-sourced assignments that were submitted for ' . $item->getItemInfo(), 'debug', 'FlowController');
                // Go through each assignment that was submitted for the current item in order to validate it
                foreach ($assignments as $assignment) {
                    try {
                        $parsedResult = $comp->parseResult($item, $assignment);
                        $assignmentId = $parsedResult['platformResultId'];
                    } catch (Exception $e) {
                        $msg = 'Unable to parse result for an assignment that was submitted for ' . $item->getItemInfo() . ': ' . $e->getMessage();
                        Yii::log($msg, 'error', 'FlowController');
                        $errors[] = $msg;
                        continue;
                    }
                    try {
                        // We have first to make sure, that the flowItemId is supplied
                        $flowItemId = null;
                        // Get the flowItemId
                        foreach ($parsedResult['data'] as $field) {
                            if ($field['id'] == 'flowItemId')
                                $flowItemId = $field['value'];
                        }
                        if ($flowItemId == null) {
                            $msg = 'Cannot accept non-validated crowd assignment: the flowItemId which this assignment was submitted for could not be extracted, and hence the list of pending and accepted assignments cannot be adjusted';
                            Yii::log($msg, 'error', 'FlowManagement');
                            $errors[] = $msg;
                            continue;
                        }

                        Yii::log('Now validating assignment ' . $assignmentId, 'debug', 'FlowManagement');

                        // Then, we can validate the assignment
                        $comp->validateAssignment($assignmentId, true);

                        /*
                         * Note that the list of processed assignments is NOT treated here, but in a subsequent process.
                         * The reason for doing so is that a custom implementation may require this.
                         * For example, in majority voting, instead of adding the assignment to the 'accepted' list,
                         * it might land in the 'rejected' list since the assignment did not have the top most answer.
                         */

                        $validatedAssignments[] = $parsedResult;
                    } catch (Exception $e) {
                        $msg = 'Unable to validate assignment with id \'' . $assignmentId . '\' that was submitted for ' . $item->getItemInfo() . ': ' . $e->getMessage();
                        Yii::log($msg, 'error', 'FlowController');
                        $errors = $msg;
                    }
                }
            } else {
                // Could not fetch crowd-sourced results for the current item
                $errs = $resultsPending['errors'];
                $msg = 'Unable to fetch results for ' . $item->getItemInfo() . ': ' . (is_array($errs) ? implode(',', $errs) : $errs);
                Yii::log($msg, 'error', 'FlowController');
                $errors[] = $msg;
            }
        }

        Yii::log('Accepted ' . sizeof($validatedAssignments) . ' assignments for all ' . sizeof($items) . ' non-postprocessed, crowdsourced items', 'debug', 'FlowController');
        return $validatedAssignments;
    }


    /**
     * Forward assignments coming from a crowd-sourced task to the validators' input queue such that the assignments can either be accepted or rejected
     * @param $items array All items in the database
     * @param $errors array The reference array to which errors are stored
     * @return array The assignments that were forwarded to validators
     * @throws CHttpException
     */
    public static function forwardAssignmentsToValidators($items, &$errors)
    {
        Yii::log('About to forward assignments to validators', 'debug', 'FlowManagement');
        $comp = Helper::getCurrentPlatformComponent();
        $forwardedAssignments = array(); // The assignments that could be forwarded to the subsequent validator

        // Go through every item in order to accept all submitted assignments
        foreach ($items as $item) {
            if (Postprocessor::itemHasValidationDestination($item)) {
                // We are interested in validators only
                //$assignmentsResult = $comp->getTaskResults($item, 'Submitted');
                $assignmentsResult = $comp->getTaskResults($item);
                if ($assignmentsResult['success']) {
                    // Add the assignment to the input queue of each subsequent item
                    Yii::log('About to forward ' . sizeof($assignmentsResult['data']) . ' assignments from ' . $item->getItemInfo() . ' to their validators (unless they were already processed)', 'debug', 'FlowManagement');

                    try {
                        $pd = $item->platform_data;
                        $parsedAssignments = array(); // The assignments processed in this step in this method


                        $validator = Helper::getOutputItem($item, true);

                        foreach ($assignmentsResult['data'] as $unparsedAssignment) {
                            // Must parse the assignments first
                            $parsedAssignment = $comp->parseResult($item, $unparsedAssignment);
                            $assignmentId = $parsedAssignment['platformResultId'];
                            $flowItemId = null;
                            // Get the flowItemId
                            foreach ($parsedAssignment['data'] as $field) {
                                if ($field['id'] == 'flowItemId')
                                    $flowItemId = $field['value'];
                            }
                            if ($flowItemId == null) {
                                // FlowItemId not found
                                $errorMessage = 'Cannot process parsed assignment ' . $assignmentId . ': cannot assign it to a flowItemId, since no such value was provided in the assignment fields';
                                Yii::log($errorMessage, 'error', 'FlowManagement');
                                $errors[] = $errorMessage;
                                continue;
                            }

                            Yii::log('ForwardAssignments: analyzing the list of processed items: ' . print_r($pd, true), 'debug', 'FlowManagement');

                            // Make sure the item is not already forwarded to the queue of the validator
                            Yii::log('ForwardAssignments: verifying that assignment ' . $assignmentId . ' was not already added to the validator ' . $validator->getItemInfo(), 'debug', 'FlowManagement');
                            $alreadyAddedToValidator = false;
                            $validatorInputQueue = $validator->input_queue;
                            if (is_string($validatorInputQueue))
                                $validatorInputQueue = json_decode($validatorInputQueue, true);
                            foreach ($validatorInputQueue as $validatorInputQueueItem) {
                                if ($validatorInputQueueItem['platformResultId'] == $assignmentId) {
                                    $alreadyAddedToValidator = true;
                                    break;
                                }
                            }
                            if ($alreadyAddedToValidator) {
                                Yii::log('ForwardAssignments: not forwarding assignment ' . $assignmentId . ', since it was already forwarded to the validtor ' . $validator->getItemInfo(), 'debug', 'FlowManagement');
                                continue;
                            } else {
                                Yii::log('ForwardAssignments: continue check of assignment ' . $assignmentId . ', since it was not already forwarded to the validtor ' . $validator->getItemInfo(), 'debug', 'FlowManagement');
                            }

                            // Make sure the item is not already forwarded to the queue of the validator
                            Yii::log('ForwardAssignments: verifying that assignment ' . $assignmentId . ' was not already added to the validator ' . $validator->getItemInfo(), 'debug', 'FlowManagement');


                            // Make sure that the item has not already PASSED, i.e. was processed by, the validator
                            $assignmentWasAccepted = array_key_exists('acceptedAssignments', $pd) && array_key_exists($flowItemId, $pd['acceptedAssignments']) && in_array($assignmentId, $pd['acceptedAssignments'][$flowItemId]);
                            $assignmentWasRejected = array_key_exists('rejectedAssignments', $pd) && array_key_exists($flowItemId, $pd['rejectedAssignments']) && in_array($assignmentId, $pd['rejectedAssignments'][$flowItemId]);
                            if ($assignmentWasAccepted || $assignmentWasRejected) {
                                // Skip this assignment, since it was already processed
                                Yii::log('ForwardAssignments: Not forwarding assignment ' . $assignmentId . ' of ' . $item->getItemInfo() . ' to the validator - has already been processed (is in the list of ' . ($assignmentWasAccepted ? 'accepted' : 'rejected') . ' assignments for flowItem ' . $flowItemId, 'debug', 'FlowManagement');
                                continue;
                            } else {
                                Yii::log('ForwardAssignments: forwarding assignment ' . $assignmentId . ' of ' . $item->getItemInfo() . ' to the validator - has not already been processed, as it is not in the list of accepted or rejected assignments for flowItem ' . $flowItemId, 'debug', 'FlowManagement');
                                $parsedAssignments[] = $parsedAssignment;
                            }

                        }

                        // Update the item's information and the list of processed items
                        $forwardedAssignmentsForCurrentItem = FlowManagement::addToInputQueue($validator, $parsedAssignments);
                        $forwardedAssignments = array_merge($forwardedAssignments, $forwardedAssignmentsForCurrentItem);


                        $item->platform_data = json_encode($pd);
                        $item->saveAttributes(array('platform_data'));
                        $item->platform_data = json_decode($item->platform_data, true);

                    } catch (Exception $ex) {
                        // Error occurred while forwarding assignment to validation
                        $msg = 'An error has occurred while forwarding the assignments for validator ' . $item->getItemInfo() . ': ' . $ex->getMessage();
                        Yii::log($msg, 'error', 'FlowManagement');
                        $errors[] = $msg;
                        continue;
                    }
                    // End for each destination item
                    Yii::log('Successfully added the assignments from ' . $item->getItemInfo() . ' to the input queue of all its ' . sizeof($forwardedAssignmentsForCurrentItem) . ' post-processing items', 'debug', 'FlowManagement');
                } // End successfully could retrieve assignments
                else {
                    $msg = 'Could not get task results for item ' . $item->getItemInfo() . ': ' . implode(', ', $assignmentsResult['errors']);
                    Yii::log($msg, 'error', 'FlowManagement');
                    $errors[] = $msg;
                }
            }
            // End current item has a postprocessor as a destination

        }
        // End for each item

        return $forwardedAssignments;
    }

    /**
     * Go through all items in order to fetch their crowd-sourced assignments. Then, forward these results to those items that use the crowd-sourced item as an input.
     * @param null $approvedParsedAssignments All assignments that were approved in the most recent step (and ONLY the ones that were approved recently: this is to prevent duplicates in the input queues)
     * @param null $items All items from the database
     * @param $errors array The reference array to which errors are stored
     * @return array The forwarded approved crowd-sourcing tasked
     * @throws CHttpException If task results could not be retrieved
     */
    public static function forwardAcceptedCrowdSourceResultsToSubsequentItem($approvedParsedAssignments = null, $items = null, &$errors)
    {
        Yii::log('About to forward the accepted assignments to the subsequent item. Provided the assignments as an argument? ' . ($approvedParsedAssignments == null ? 'No' : 'Yes') . '. Provided items as an argument? ' . ($items == null ? 'No' : 'Yes'), 'debug', 'FlowManagement');


        // Get the items manually, if they are not submitted
        if ($items == null)
            $items = FlowManagement::getAllItems();


        // Get all assignments, if they are not given as a parameter
        if ($approvedParsedAssignments == null) {
            Yii::log('Fetching all assignments manually, since no argument was given', 'debug', 'FlowManagement');
            $approvedParsedAssignments = array();

            $comp = Helper::getCurrentPlatformComponent();
            foreach ($items as $item) {
                $getTasksResult = $comp->getTaskResults($item, 'Approved');
                if ($getTasksResult['success']) {
                    $parsedResultsForThisItem = 0;
                    foreach ($getTasksResult['data'] as $assignmentToParse) {
                        $parsedResult = $comp->parseResult($item, $assignmentToParse);
                        $approvedParsedAssignments[] = $parsedResult;
                        $parsedResultsForThisItem++;
                    }
                    // End for each assignment to parse
                    Yii::log('Manually fetching assignments: found ' . $parsedResultsForThisItem . ' assignments that were approved for item ' . $item->getItemInfo() . ', now having a total of ' . sizeof($approvedParsedAssignments) . ' assignments', 'debug', 'FlowManagement');
                } // End could fetch task results
                else {
                    $msg = 'Cannot forward accepted crowdSource results to subsequent item - Manually fetching assignments: could not fetch assignments for item ' . $item->getItemInfo() . ': ' . ($getTasksResult['errors'] != null ? implode(',', $getTasksResult['errors']) : 'An undefined error occurred');
                    Yii::log($msg, 'debug', 'FlowManagement');
                    $errors[] = $msg;
                    continue;
                }
                // End could not fetch task results
            }
            // End for each item
            Yii::log('Successfully fetched ' . sizeof($approvedParsedAssignments) . ' assignments manually', 'debug', 'FlowManagement');
        }
        // End no arguments were given

        Yii::log('About to forward ' . sizeof($approvedParsedAssignments) . ' approved crowd-source results for ' . sizeof($items) . ' items', 'debug', 'FlowManagement');

        $forwardedAssignments = array(); // The assignments that were forwarded to the next item

        // Go through every item that depends on the crowd in order to push the crowd assignments to their input queues
        foreach ($items as $item) {
            Yii::log('About to process crowd-source results for ' . $item->getItemInfo(), 'debug', 'FlowManagement');

            // Let the custom implementations of the item forward the assignments to the subsequent item
            try {
                $forwardedAssignmentsFromThisItem = $item->forwardAssignmentsToSubsequentItem($approvedParsedAssignments);
            } catch (Exception $ex) {
                $errors[] = $ex->getMessage();
            }
            if ($forwardedAssignmentsFromThisItem != null)
                $forwardedAssignments = array_merge($forwardedAssignments, $forwardedAssignmentsFromThisItem);

        }
        // End for each item
        return $forwardedAssignments;
    }


    /**
     * Some assignments are forwarded to a post-processor which validates the assignments.
     * In this method, we use the validation results of the postprocessor item and, depending on the validation,
     * we will accept or reject the assignments that were submitted by a worker when executing a task of the input item.
     * For example, if a text processing task output is sent to a validation (type 'postprocessor'), and the
     * validation rejects the assignment, then the text-processing assignment is rejected.
     * @param array $items All items from the database
     * @param $errors array The reference array to which errors are stored
     * @return array The associative array with keys 'approve' and 'reject' containing the parsed assignments that were accepted resp. rejected
     * @throws CHttpException
     */
    public static function processAssignmentValidations($items, &$errors)
    {
        Yii::log('About to process assignment validations', 'debug', 'FlowManagement');

        $validatedAssignments = array('approve' => array(), 'reject' => array()); // The assignments that were accepted in this method call

        // First, retrieve the validation items
        $validators = array();


        foreach ($items as $item) {
            if (get_class($item) != 'Postprocessor') {
                continue;
            }
            if ($item->postprocessor_type_id != NULL && ($item->postprocessor_type_id == 11 || $item->postprocessor_type_id == 12)) {
                // This postprocessor is a validator
                $validators[] = $item;
            }
        }

        Yii::log('Processing ' . sizeof($validators) . ' assignment validators', 'debug', 'FlowManagement');

        // Then use the results of the validators to validate or reject the input assignment
        foreach ($validators as $validator) {
            $subsequentItem = Helper::getOutputItem($validator);
            if ($subsequentItem == null) {
                // No output connection was set for this item - cannot process the assignments, as they would have to be added to the subsequent item's input queue
                $msg = 'Not processing assignments that are pending for ' . $validator->getItemInfo() . ': no subsequent item could found';
                Yii::log($msg, 'warning', 'FlowManagement');
                $errors[] = $msg;
                continue;
            }


            Yii::log('About to process assignments that are pending for ' . $validator->getItemInfo(), 'debug', 'FlowManagement');
            switch ($validator->postprocessor_type_id) {
                case 12:
                    // Validation is done by the crowd
                    $comp = Helper::getCurrentPlatformComponent();
                    $taskResultsAnswer = $comp->getTaskResults($validator, 'Approved'); // These are the assignments from the validation, and NOT from the tasks this postprocessor is validating! Note that the validators' assignments were accepted automatically
                    if (!$taskResultsAnswer['success']) {
                        $msg = 'Could not get task answers of validator: ' . implode(',', $taskResultsAnswer['errors']);
                        Yii::log($msg, 'warning', 'FlowManagement');
                        $errors[] = $msg;
                        continue;
                    }
                    $crowdAnswers = $taskResultsAnswer['data'];

                    Yii::log('Validating ' . sizeof($crowdAnswers) . ' assignments for ' . $validator->getItemInfo(), 'debug', 'FlowManagement');

                    // Treat each crowd answer separately
                    foreach ($crowdAnswers as $crowdAnswer) {
                        // Found the assignment id that was validated

                        // Now, find out whether the crowd's task should be accepted or rejected by examining the validator's assignments
                        $validatorsParsedAnswer = $comp->parseResult($validator, $crowdAnswer);
                        $accept = false;
                        $validatedAssignmentId = 0;
                        $flowItemId = 0;


                        Yii::log('Fetching the result of the crowd-sourced validation by examining the result of assignment ' . print_r($validatorsParsedAnswer, true), 'debug', 'FlowManagement');

                        // Read the crowd-answered form by going through each form field
                        foreach ($validatorsParsedAnswer['data'] as $formField) {
                            if ($formField['id'] == 'approve') {
                                // Found whether the assignment was validated
                                if ($formField['value'] == 1)
                                    $accept = true;
                            } else if ($formField['id'] == 'platformResultIdToValidate') {
                                // Found the assignment identifier which should be approved or rejected
                                $validatedAssignmentId = $formField['value'];
                            } else if ($formField['id'] == 'flowItemId') {
                                // Found the identifier for the inputQueue item
                                $flowItemId = $formField['value'];
                            }
                        }
                        // End for each form field

                        // Verify that an assignmentId could be found
                        if ($validatedAssignmentId == 0) {
                            Yii::log('Cannot process a crowd-sourced validation - could not find the identifier of the result that must be approved or rejected (by examining the crowd-sourced ' . $validator->getItemInfo() . ' and its result id \'' . $validatorsParsedAnswer['platformResultId'] . '\')', 'warning', 'FlowManagement');
                            continue;
                        }

                        // Nothing to do if this assignment was already processed
                        $status = $comp->getAssignmentStatus($validatedAssignmentId);
                        Yii::log('Checking whether flowItemId ' . $flowItemId . ' was already processed before by evaluating the platformData of ' . $item->getItemInfo() . ': has status \'' . $status . '\'', 'debug', 'FlowManagement');
                        if ($status == 'accepted' || $status == 'rejected') {
                            // This item was already processed - go to the next assignment executed by the crowd
                            continue;
                        }

                        // Get the assignment that was validated
                        $iq = $validator->input_queue == null ? array() : $validator->input_queue;
                        Yii::log('About to find the assignment which the validator is referring to, such that it can be validated, by searching the input queue ' . print_r($iq, true), 'debug', 'FlowManagement');
                        $assignmentReferringTo = null;
                        foreach ($iq as $inputQueueItem) {
                            if ($inputQueueItem['platformResultId'] == $validatedAssignmentId) {
                                $assignmentReferringTo = $inputQueueItem;
                            }
                        }
                        if ($assignmentReferringTo == null) {
                            /* Cannot process as the validated assignment was not found in the input queue
                            * This is normal if the assignment was rejected earlier, as in this case, the rejected assignment is removed from the input queue (since it must not be processed further)
                             *
                             */
                            if ($accept) {
                                // This is an error (see previous comments)
                                $msg = 'Processing crowd-sourced validation  (flowItemId ' . $flowItemId . ') failed - cannot process ' . $validator->getItemInfo() . ': the assignment which the validator is referring to (\'' . $validatedAssignmentId . '\') could not be found in the input queue of ' . $validator->getItemInfo();
                                Yii::log($msg, 'error', 'FlowManagement');
                                $errors[] = $msg;
                            } else {
                                // This is not an error (see previous comments)
                                Yii::log('Processing crowd-sourced validation  (flowItemId ' . $flowItemId . ') failed - cannot process ' . $validator->getItemInfo() . ': the assignment which the validator is referring to (\'' . $validatedAssignmentId . '\') could not be found in the input queue. This is normal if the assignment (which was rejected) was already removed from the input queue of ' . $validator->getItemInfo(), $accept ? 'error' : 'debug', 'FlowManagement');
                            }
                            continue;
                        } else {
                            Yii::log('Processing crowd-sourced validation for ' . $validator->getItemInfo() . ': the assignment which the validator is referring to (\'' . $validatedAssignmentId . '\') could be found', 'debug', 'FlowManagement');
                        }

                        Yii::log('Validating assignmentId ' . $validatedAssignmentId . ': the crowd decided to ' . ($accept ? 'approve' : 'reject') . ' this assignment', 'debug', 'FlowManagement');

                        // Finally, accept or reject the 'original' assignment by evaluating the validator's answer
                        $payRejectedAnswers = Yii::app()->params['payRejectedAnswers'];

                        // Also take into account whether the requester has chosen to also pay rejected assignments
                        $acceptOnCrowd = $accept || $payRejectedAnswers;
                        if (!$accept && $payRejectedAnswers) {
                            Yii::log('Even though the assignment was rejected by the crowd, it will be paid anyway, as defined in the general settings', 'info', 'FlowManagement');
                        }
                        $validateResult = $comp->validateAssignment($validatedAssignmentId, $acceptOnCrowd);
                        Yii::log('Validate assignment result: ' . print_r($validateResult, true), 'debug', 'FlowManagement');
                        if ($validateResult['success']) {
                            /* Could successfully validate the assignment, or (if the 'data' key would not be present in the answer), the assignment was already accepted or rejected before
                            *  Add the validation result to the appropriate array, depending on whether it was approved or rejected.
                             *
                             */
                            $validatedAssignmentArray = array(
                                'validator' => $validator,
                                'parsedAnswer' => $validatorsParsedAnswer,
                                'assignmentReferringTo' => $assignmentReferringTo,
                                'validatedAssignmentId' => $validatedAssignmentId,
                                'subsequentItem' => $subsequentItem,
                                'flowItemId' => $flowItemId
                            );
                            if ($accept) {
                                // Assignment was accepted (in the WebApplication, not necessarily on the crowd-sourcing platform -> see variable $acceptOnCrowd)
                                $validatedAssignments['approve'][] = $validatedAssignmentArray;
                            } else {
                                // Assignment was rejected
                                $validatedAssignments['reject'][] = $validatedAssignmentArray;
                            }

                        }
                        // End validation of the crowd-sourced task was successful
                    }
                    // End for each assignment on the crowd-sourcing platform

                    break;
                case 11:
                    // Validation is done by the requester - fall through
                default:
                    // Validation type not specified
                    $msg = 'Cannot validate input assignments for ' . $validator->getItemInfo() . ': the method has not yet been implemented for this type of validator';
                    Yii::log($msg, 'error', 'FlowManagement');
                    $errors[] = $msg;
                    break;
            }
        }
        Yii::log('Successfully approved ' . sizeof($validatedAssignments['approve']) . ' assignments, rejected ' . sizeof($validatedAssignments['reject']) . ' assignments, now adding resp. removing them to the input queue of the subsequent item', 'debug', 'FlowManagement');

        /*
         * Go through every accepted assignment in order to forward it to the subsequent item.
         * Note that this procedure differs from the usual flow since the accepted assignment is not added to the DIRECT subsequent item (which would be the validator itself), but
         * to the subsequent item of the VALIDATOR (as the validator is just an intermediate step between two tasks)
         */
        foreach ($validatedAssignments['approve'] as $acceptedAssignment) {
            $validator = $acceptedAssignment['validator'];
            $validatorsParsedAnswer = $acceptedAssignment['parsedAnswer'];
            $assignmentReferringTo = $acceptedAssignment['assignmentReferringTo'];
            $validatedAssignmentId = $acceptedAssignment['validatedAssignmentId'];
            $subsequentItem = $acceptedAssignment['subsequentItem'];
            $platformResultId = $validatorsParsedAnswer['platformResultId'];
            $flowItemId = $acceptedAssignment['flowItemId'];
            Yii::log('About to add the result of assignment \'' . $platformResultId . '\' which was filled out for item ' . $assignmentReferringTo['itemId'] . ' of type \'' . $assignmentReferringTo['itemType'] . '\' to its subsequent item\'s result', 'debug', 'FlowManagement');

            if ($subsequentItem != null) {
                try {
                    // Add the assignment to the destination item of the validator
                    FlowManagement::addToInputQueue($subsequentItem, $assignmentReferringTo);


                    $itemReferringTo = Helper::getInputItem($validator, true);
                    // Add the assignment to the list of accepted assignments
                    Yii::log('About to add assignment ' . $validatedAssignmentId . ' to the list of accepted assignments of  ' . $itemReferringTo->getItemInfo(), 'debug', 'FlowManagement');
                    $pd = $itemReferringTo->platform_data;
                    $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItemId, $validatedAssignmentId);
                    $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $validatedAssignmentId);

                    $itemReferringTo->platform_data = json_encode($pd);
                    $itemReferringTo->saveAttributes(array('platform_data'));
                    $itemReferringTo->platform_data = json_decode($itemReferringTo->platform_data, true);

                    // Since this item is processed, it must be removed from the validator
                    FlowManagement::removeFromInputQueue($validator, $assignmentReferringTo);
                    Yii::log('Successfully added assignment ' . $platformResultId . ' to the list of accepted assignments of item ' . $itemReferringTo->getItemInfo(), 'debug', 'FlowManagement');

                } catch (Exception $ex) {
                    // An error occurred while processing the approved assignment
                    $msg = 'Cannot process approved assignment validation for validator ' . $validator->getItemInfo() . ': ' . $ex->getMessage();
                    Yii::log($msg, 'error', 'FlowManagement');
                    $errors[] = $msg;
                }
            } else {
                $msg = 'Cannot add assignment ' . $validatorsParsedAnswer['platformResultId'] . ' to the input queue of the subsequent item - could not fetch the subsequent item';
                Yii::log($msg, 'warning', 'FlowManagement');
                $errors[] = $msg;
            }
            // End could not get subsequent item
        }
        // End for each approved assignment

        /**
         * Then, the rejected assignments must be removed from the input queue of the validator and from their list of processed flowItems, such that a new publication of the rejected task is triggered in the next flow update
         */
        foreach ($validatedAssignments['reject'] as $acceptedAssignment) {
            try {
                $validator = $acceptedAssignment['validator'];
                $validatorsParsedAnswer = $acceptedAssignment['parsedAnswer'];
                $assignmentReferringTo = $acceptedAssignment['assignmentReferringTo'];
                $validatedAssignmentId = $acceptedAssignment['validatedAssignmentId'];
                $inputItem = Helper::getInputItem($validator);
                $platformResultId = $assignmentReferringTo['platformResultId'];
                $flowItemId = $acceptedAssignment['flowItemId'];
                Yii::log('About to remove the rejected assignment \'' . $validatedAssignmentId . '\' which was filled out for ' . $assignmentReferringTo['itemId'] . ' of type \'' . $assignmentReferringTo['itemType'] . '\' from the input queue of ' . $validator->getItemInfo(), 'debug', 'FlowManagement');

                /*
                * We keep track of the rejected assignments such that we can backtrack them. This is necessary because
                * if the requester chooses to also pay rejected assignments, the assignment status on the crowd-sourcing platform would be 'approved' even
                 * if it was rejected by the WebApplication.
                 * To keep track of the rejected assignments, we store them in the item's platform_data variable
                 */
                Yii::log('About to add assignment ' . $validatedAssignmentId . ' to the list of rejected assignments of ' . $inputItem->getItemInfo(), 'debug', 'FlowManagement');
                $pd = $inputItem->platform_data;


                $pd = Helper::modifyAssignmentsList('rejected', $pd, true, $flowItemId, $validatedAssignmentId);
                $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $validatedAssignmentId);

                // Since this assignment was rejected, another one is required, which means that the crowd-sourced task must be extended
                if ($inputItem->getPublicationCount($flowItemId) > 0) {
                    Yii::log('Rejected an assignment - re-publishing ' . $inputItem->getItemInfo() . ' in order to execute task with flowItemId ' . $flowItemId . ' again', 'debug', 'FlowManagement');
                    $comp->publishTask($inputItem, $flowItemId);
                } else {
                    // Cannot re-publish - number of possible publication is zero
                    Yii::log('Rejected an assignment. Cannot re-publish ' . $inputItem->getItemInfo() . ' for flowItemId' . $flowItemId . ' again, number of possible publications is zero.', 'debug', 'FlowManagement');
                }


                // Store the updated data
                $inputItem->platform_data = json_encode($pd);
                $inputItem->saveAttributes(array('platform_data'));
                $inputItem->platform_data = json_decode($inputItem->platform_data, true);
                Yii::log('Processed flowitems of validator after adjustment: ' . print_r($validator->processed_flowitems, true), 'debug', 'FlowManagement');
                // Since this item is processed, it must be removed from the validator
                FlowManagement::removeFromInputQueue($validator, $assignmentReferringTo);
                Yii::log('Successfully added assignment ' . $platformResultId . ' to the list of rejected assignments of item ' . $inputItem->getItemInfo(), 'debug', 'FlowManagement');
            } catch (Exception $ex) {
                // An error occurred while processing the rejected assignment
                $msg = 'Cannot process rejected assignment validation for validator ' . $validator->getItemInfo() . ': ' . $ex->getMessage();
                Yii::log($msg, 'error', 'FlowManagement');
                $errors[] = $msg;
            }

        }
        // End for each rejected assignment


        return $validatedAssignments;

    }


    /**
     * Enables items that depend on an input queue which is no longer empty
     * Disables items that depend on an input queue that has become empty
     * @param $items
     * @param $errors array The reference array to which errors are stored
     * @return array An associative array with 'enabled' items and 'disabled' items
     */
    public static function enableTasksWithInputQueue($items, &$errors)
    {
        $publishedTasks = array();

        Yii::log('EnableTasksWithInputQueue: About enable tasks with input queue', 'debug', 'FlowManagement');


        $comp = Helper::getCurrentPlatformComponent();

        // Go through every item to check whether it has to be updated depending on the input queue
        foreach ($items as $item) {
            Yii::log('EnableTasksWithInputQueue: checking ' . $item->getItemInfo(), 'debug', 'FlowManagement');
            if (!$item->hasAttribute('input_queue')) {
                // Only items with an input queue can be crowd-sourced
                Yii::log('Not enabling task for ' . $item->getItemInfo() . ' - cannot be crowd-sourced as it does not have an input queue', 'debug', 'FlowManagement');
                continue;
            }
            $inputQueue = $item->input_queue;
            if (is_string($inputQueue))
                $inputQueue = json_decode($inputQueue, true);
            foreach ($inputQueue as $inputQueueItem) {
                $flowItemId = $inputQueueItem['flowItemId'];
                $nbToPublish = $item->getPublicationCount($flowItemId, $inputQueue);
                if ($nbToPublish == 0) {
                    // No need to publish this item now
                    continue;
                }
                Yii::log('EnableTasksWithInputQueue: ' . $item->getItemInfo() . ' must be published for flowItemId ' . $flowItemId . ' accepting ' . $nbToPublish . ' assignments', 'debug', 'FlowManagement');
                $publishResult = $comp->publishTask($item, $inputQueueItem['flowItemId'], $nbToPublish);
                if (!$publishResult['success']) {
                    // Could not publish
                    $msg = 'Cannot publish task ' . $item->getItemInfo() . ': ' . ($publishResult['errors'] != null ? implode(', ', $publishResult['errors']) : 'An unknown error has occurred');
                    Yii::log($msg, 'debug', 'FlowManagement');
                    $errors[] = $msg;
                    continue;
                } else {
                    Yii::log('Publish success for ' . $item->getItemInfo(), 'debug', 'FlowManagement');
                    $publishedTasks[] = $item;
                }
            }
            // End for each inputQueue item
        }
        // End for each item

        Yii::log('EnableTasksWithInputQueue: published ' . sizeof($publishedTasks) . ' tasks from ' . sizeof($items) . ' items', 'debug', 'FlowManagement');
        return $publishedTasks;
    }


    /**
     * Process the input queue for all items that are not crowd-sourced. These may execute automatic operations like for example split the input queue, aggregate results etc.
     * @param null $items Optional, the items for which the input queue must be processed. If null, the items are fetched manually
     * @param $errors array The reference array to which errors are stored
     * @return array The array of input queue items that have been processed in this operation
     */
    public static function processNonCsInputQueue($items = null, &$errors)
    {
        if ($items == null)
            $items = FlowManagement::getAllItems();

        $itemsSize = sizeof($items);
        Yii::log('Processing the input queue for ' . $itemsSize . ' items', 'debug', 'FlowManagement');

        $processedInputQueueItems = array();

        // Call the appropriate processing function for each item in the WebApplication
        foreach ($items as $item) {
            try {
                $processedInputQueueItemsForThisItem = $item->processInputQueue($errors);
                if ($processedInputQueueItemsForThisItem != null) {
                    $processedInputQueueItems = array_merge($processedInputQueueItems, $processedInputQueueItemsForThisItem);

                    /*
 * As the items are processed, we can remove them from the list of pending and add them to the list of accepted flowItemIds.
 * This is necessary in order to keep track of the already processed items, thereby avoiding multiple processing of the same queue items
 */
                    if ($item->hasAttribute('platform_data')) {
                        $pd = $item->platform_data;
                        if (is_string($pd))
                            $pd = json_decode($pd, true);
                        foreach ($processedInputQueueItems as $processedInputQueueItem) {
                            Yii::log('Adjusting list of processed items for ' . $item->getItemInfo() . ': treating input queue item ' . print_r($processedInputQueueItem, true), 'debug', 'FlowManagement');
                            if (array_key_exists('flowItemId', $processedInputQueueItem) && array_key_exists('platformResultId', $processedInputQueueItem)) {
                                $flowItemId = $processedInputQueueItem['flowItemId'];
                                $assignmentId = $processedInputQueueItem['platformResultId'];
                                $pd = Helper::modifyAssignmentsList('pending', $pd, false, $flowItemId, $assignmentId);
                                $pd = Helper::modifyAssignmentsList('accepted', $pd, true, $flowItemId, $assignmentId);
                            }
                        }
                        $item->platform_data = json_encode($pd);
                        $item->saveAttributes(array('platform_data'));
                        $item->platform_data = json_decode($item->platform_data, true);
                    }
                }
            } catch (Exception $exc) {
                // An error occurred
                $msg = 'Cannot process ' . $item->getItemInfo() . ': ' . $exc->getMessage();
                Yii::log($msg, 'warning', 'FlowManagement');
                $errors[] = $msg;
                continue;
            }
        }

        Yii::log('Successfully processed ' . sizeof($processedInputQueueItems) . ' input queue items for ' . $itemsSize . ' items', 'debug', 'FlowManagement');


        return $processedInputQueueItems;

    }

//endregion Flow-control related functions


    //region Crowd-sourcing related helper functions
    /**
     * Checks if an item is crowd-sourced
     * @param $modelOrType The model to be checked, or the item type (in which case the id parameter is mandatory)
     * @param null idIfTypeSubmitted The model's id if the type is submitted in the previous argument
     * @return bool TRUE, if the item is processed on the crowd, FALSE otherwize
     * @throws CHttpException on invalid parameters
     */
    public static function isCrowdsourced($modelOrType, $idIfTypeSubmitted = null)
    {
        Yii::log('About to check if an item is crowd-sourced', 'debug', 'FlowManagement');

        // At first, get the model and type of the item to be checked
        $model = null;
        $type = '';
        if (is_object($modelOrType)) {
            // Submitted a model as parameter
            $model = $modelOrType;
            $type = strtolower(get_class($modelOrType));
        } else {
            // Submitting item type and item id
            if ($idIfTypeSubmitted == null) {
                throw new CHttpException(500, 'Cannot check if item is crowd-sourced - must submit the id of the item along with its type');
            }
            $type = strtolower($modelOrType);
            $checkIsCrowdsourcedExpr = '$model = ' . ucfirst($type) . '::model()->findByPk(' . $idIfTypeSubmitted . ');';
            Yii::log('Retrieving the model from the database by evaluating the expression \'' . $checkIsCrowdsourcedExpr . '\'', 'debug', 'Helper');
            eval($checkIsCrowdsourcedExpr);
        }

        // Finally, we can apply the logic to check if the item is crowd-sourced
        $result = false;
        switch ($type) {
            case 'task':
                // all tasks are sent to the crowd
                $result = true;
                break;
            case 'merger':
                if ($modelOrType->merger_type_id == 12)
                    $result = true;
                break;
            case 'postprocessor':
                if ($modelOrType->postprocessor_type_id == 12)
                    $result = true;
                break;
            default:
                break;
        }
        Yii::log('Check if model ' . $model->id . ' of type \'' . get_class($model) . '\' is crowd-sourced: ' . ($result ? 'True' : 'False'), 'debug', 'FlowManagement');
        return $result;
    }


    //endregion Crowd-sourcing related helper functions

}
