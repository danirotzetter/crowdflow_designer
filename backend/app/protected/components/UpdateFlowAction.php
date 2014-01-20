<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 21.11.13
 * Time: 15:01
 * To change this template use File | Settings | File Templates.
 */

class UpdateFlowAction extends CAction
{
    public function run()
    {
        // Be careful - must be synchronized with PlatformsController->actionUpdateFlow()!


        try {
            $forceReload=true;
            Yii::log('UpdateFlow: Updating the flow of all items, forcing reload? ' . ($forceReload ? 'true' : 'false'), 'debug', 'UpdateFlowAction');

            /*
             * Each time an item is fetched from the database, some operations are executed (like getting crowd-sourcing data etc.).
             * In order to not execute these methods several times, in this method, all items are retrieved at once and then used
             * in the subsequent operations.
             */
            $items = FlowManagement::getAllItems();
            $errors = array(); // Keeping track of all errors that occurred

            // Automatically accept assignments that are not validated
            Yii::log('UpdateFlow: About to accept non-postprocessed crowd answers', 'debug', 'UpdateFlowAction');
            $autoAcceptedAssignments = FlowManagement::acceptNonValidatedItems($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();

            // Forward crowd-sourced assignments that require validation to the validation item
            Yii::log('UpdateFlow: Forwarding crowd-sourced assignments to validators', 'debug', 'UpdateFlowAction');
            $assignmentsForwardedToValidators = FlowManagement::forwardAssignmentsToValidators($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();

            // Accept and reject assignments that were validated by the crowd
            Yii::log('UpdateFlow: About to accept and reject crowd-validated assignments', 'debug', 'UpdateFlowAction');
            $crowdValidatedAssignments = FlowManagement::processAssignmentValidations($items, $errors);

            if ($forceReload)
                $items = FlowManagement::getAllItems();


            // Forward crowd-sourced items to the input queue of the following item
            Yii::log('UpdateFlow: Now forwarding the crowd-sourced items', 'debug', 'UpdateFlowAction');
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
            Yii::log('UpdateFlow: About to enable tasks on the crowd-sourcing platform, depending on their input queue', 'debug', 'UpdateFlowAction');
            $enabledTasks = FlowManagement::enableTasksWithInputQueue($items, $errors);


            // Process the input queue for items that are not crowd-sourced
            Yii::log('UpdateFlow: About to process input-queue items', 'debug', 'UpdateFlowAction');
            $processedInputQueue = FlowManagement::processNonCsInputQueue($items, $errors);

            Yii::log('UpdateFlow: All operations completed', 'debug', 'UpdateFlowAction');

            // Log the result
            $autoAcceptedAssignmentsText = '';
            foreach($autoAcceptedAssignments as $assignment){
                $autoAcceptedAssignmentsText.='assignment '.$assignment['platformResultId'].' for '.$assignment['itemId'].' of type '.$assignment['itemType'].'; ';
            }
            $assignmentsForwardedToValidatorsText = '';
            foreach($assignmentsForwardedToValidators as $assignment){
                $assignmentsForwardedToValidatorsText.='assignment '.$assignment['platformResultId'].' for '.$assignment['itemId'].' of type '.$assignment['itemType'].'; ';
            }
            $assignmentsApprovedByValidatorsText = '';
            foreach($crowdValidatedAssignments['approve'] as $assignment){
                $validatedAssignmentId = $assignment['validatedAssignmentId'];
                $subsequentItem = $assignment['subsequentItem'];
                $flowItemId = $assignment['flowItemId'];
                $assignmentsApprovedByValidatorsText.='assignment '.$validatedAssignmentId.' of flowItemId '.$flowItemId.' for '.$subsequentItem->getItemInfo().'; ';
            }
            $assignmentsRejectedByValidatorsText = '';
            foreach($crowdValidatedAssignments['reject'] as $assignment){
                $validatedAssignmentId = $assignment['validatedAssignmentId'];
                $subsequentItem = $assignment['subsequentItem'];
                $flowItemId = $assignment['flowItemId'];
                $assignmentsRejectedByValidatorsText.='assignment '.$validatedAssignmentId.' of flowItemId '.$flowItemId.' for '.$subsequentItem->getItemInfo().'; ';
            }
            $forwardedFlowItemsText= '';
            foreach($forwardedFlowItems as $forwardedFlowItem){
                $forwardedFlowItemsText.='flowItemId '.$forwardedFlowItem['flowItemId'].' for item '.$forwardedFlowItem['itemId'].' of type \''.$forwardedFlowItem.'\'; ';
            }
            $itemsEnabledText = '';
            foreach($enabledTasks as $enabledTask){
                $itemsEnabledText.=$enabledTask->getItemInfo().'; ';
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
            echo $msg;
            Yii::log($msg, 'info', 'UpdateFlowAction');
        } catch (Exception $e) {
            $msg = 'Could not forward crowd-sourced task results: ' . $e->getMessage() . '. Stack trace: ' . $e->getTraceAsString();
            echo $msg;
            Yii::log($msg, 'error', 'UpdateFlowAction');
        }
    }
}