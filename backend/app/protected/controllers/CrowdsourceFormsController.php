<?php

/**
 * Class CrowdsourceFormController
 * Deals with and prepares forms that are used to crowd-sourced micro-tasks to platforms
 */
class CrowdsourceFormsController extends CsController
{


	/**
	 * Displays a form that will be crowd-sourced. Fills the form with the values from the passed assignment, if one is supplied (this is useful for verification)
	 * @param string $itemType the item type
	 * @param integer $itemId the ID of the model to be displayed
	 * @param integer $flowItemId The flow item for which the form must be generated
	 */
	public function actionItemFormView($itemType=null, $itemId=null, $flowItemId=null)
	{
        $itemInfo = 'item '.$itemId.' of type \''.$itemType.'\'';

        Yii::log('Loading form view for '.$itemInfo, 'debug', 'CrowdsourceFormsController');

        //region Parameter verification
        $errorMsg=null;
        if($itemType==null){
            $errorMsg = 'Cannot load crowd-sourcing form - must supply an item type';
        }
        else if($itemId==null){
            $errorMsg = 'Cannot load crowd-sourcing form - must supply an item identifier';
        }
        else if($flowItemId==null){
            $errorMsg = 'Cannot load crowd-sourcing form - must supply a flowItem identifier';
        }
        if($errorMsg!=null){
            // An error occurred - abort form display
            Yii::log($errorMsg, 'error', 'CrowdsourceFormsController');
            echo $this->getNormalizedAnswerObject(false, null, array($errorMsg));
            return;
        }
        //endregion End parameter verification

        // Load the model data
        try{
        $model = CrowdsourceFormsController::loadModel($itemType, $itemId);
        }catch(CHttpException $ex){
            // Not found
            $errorMsg = 'Cannot load crowd-sourcing form - cannot find the requested model (item '.$itemId.' of type \''.$itemType.'\')';
            Yii::log($errorMsg, 'error', 'CrowdsourceFormsController');
            echo $this->getNormalizedAnswerObject(false, null, array($errorMsg));
            return;
        }

        // Read the rendering information
        $assignmentId = isset($_GET['assignmentId']) ? $_GET['assignmentId'] : 'ASSIGNMENT_ID_NOT_AVAILABLE';
        $previewMode = $assignmentId == 'ASSIGNMENT_ID_NOT_AVAILABLE';
        $appView = isset($_GET['appMode']); // If set, the preview is accessed from the WebApplication - in this case, more sophisticated controls can be provided (like using the AngularJS directives)


        // Get the input data for this task
        try{
        $inputData = Helper::renderInputData($model, $flowItemId, $previewMode);
        }catch(CHttpException $ex){
            // Failed to render the input
            Yii::log('Cannot render input data '.$ex->getMessage().' - stack trace: '.$ex->getTraceAsString(), 'error', 'CrowdsourceFormsController');
            $publicErrorMessage ='An error occurred, cannot render the form ('.$ex->getMessage().')';
           echo $this->getNormalizedAnswerObject(false, null, array($publicErrorMessage));
            return;
        }

        // Find out, which url (i.e. which form) must be loaded/ rendered
        $urlToLoad = Helper::getUrlOfCsForm($model);

        Yii::log('Rendering url \''.$urlToLoad.'\' AppView '.($appView?'true':'false').', previewMode '.($previewMode?'true':'false').' for model '.print_r($model->attributes, true), 'debug', 'CrowdsourceFormsController');

        if(!$previewMode){
            // Keep track of that assignment: indicate, that the flowItemId has a pending assignment
            $pd = $model->platform_data;
            /*
 * We have to keep track of all generated crowd-sourced assignments. In order to not further process the same assignment multiple
 * times, we make use of a unique identifier. We cannot use the assignmentId as unique identifier, since it may happen that a splitter or
 * a postprocessor creates multiple flowItems out of one assignment (in which case the flowItem will get another unique identifier).
 * Hence, an artificial identifier is used.
 * If the input data contains an item which already contains a valid flowItemId, then this one will be sent along with the answer. Otherwize,
 * a new unique Id is generated at random.
 * In order to generate a unique identifier for each assignment, this is done here, when creating the crowd-sourced form.
 * If a flowItem is added to the inputQueue of the subsequent item, then this hidden field is being read and the flowItemId can be used.
 * This field is being read in the FlowManagement.php file, in the method 'addToInputQueue()'
 */

            $pd=Helper::modifyAssignmentsList('pending', $pd, true, $flowItemId, $assignmentId);
            $model->platform_data=json_encode($pd);
            $model->saveAttributes(array('platform_data'));
            $model->platform_data = json_decode($model->platform_data, true);
            Yii::log('Incremented the number of pending assignments for ' . $model->getItemInfo() . ' and flowItem ' . $flowItemId . ': has now ' . sizeof($pd['pendingAssignments'][$flowItemId]) . ' items pending', 'debug', 'FlowManagement');
        }


        $this->renderPartial($urlToLoad,
            // Send the parameters from the request to the php file that is representing the form to be displayed
            array(
                'model'=>$model,
                'inputData'=>$inputData,
                'assignmentId'=>$assignmentId,
                'previewMode'=>$previewMode,
                'appView'=>$appView,
                'flowItemId'=>$flowItemId,
            )
        );


	}


    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param string $type The item type
     * @param integer $id the ID of the model to be loaded
     * @return the loaded model
     * @throws CHttpException
     */
    public static function loadModel($type, $id)
    {
        $model=NULL;
        if($type=='task')
        $model=Task::model()->findByPk($id);
        else if($type=='merger')
        $model=Merger::model()->findByPk($id);
        else if($type=='splitter')
        $model=Splitter::model()->findByPk($id);
        else if($type=='postprocessor')
        $model=Postprocessor::model()->findByPk($id);
        else
            throw new CHttpException(501,'Cannot prepare form to crowd-source an item of type \''.$type.'\'.');
        if($model===null)
            throw new CHttpException(404,'The requested page does not exist.');
Yii::log('Loaded model '.print_r($model->attributes, true), 'debug', 'CrowdsourceFormsController');
        return $model;
    }


    /**
     * Sends an email from within the crowd-sourced task
     */
    public function actionSendMail(){

            $email_to = "cfd_feedback@sensemail.ch";
            $email_subject = "CrowdFlow Designer Feedback";


            if(!isset($_POST['comments'])) {
                // Invalid parameters
                echo '<p>Not all required fields submitted</p>';
                return;
            }
        else{
            $comments = $_POST['comments'];
            $flowItemId = isset($_POST['flowItemId'])?$_POST['flowItemId']:'N/A';
            $assignmentId = isset($_POST['assignmentId'])?$_POST['assignmentId']:'N/A';
            $item = isset($_POST['item'])?$_POST['item']:'N/A';
            $msg = 'FlowItemId: '.$flowItemId.' ';
            $msg.= 'AssignmentId: '.$assignmentId.' ';
            $msg.= 'Item: '.$item.' ';
            $msg.= 'Comments: '.$comments.' ';
            $headers = 'From: '.$email_to."\r\n".
                'Reply-To: '.$email_to."\r\n" .
                'X-Mailer: PHP/' . phpversion();
            @mail($email_to, $email_subject, $msg, $headers);
            echo '<p>Message was successfully sent, thank you for your feedback</p>';
        }

    }

}
