<?php

class TaskController extends CsController
{

    private $defaultLoads = array('parent', 'user', 'connections');


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
        $model = new Task;

        // Uncomment the following line if AJAX validation is needed
        // $this->performAjaxValidation($model);

        Yii::log('About to read json data for task', 'debug', 'TaskController');
        $data = $this->readJsonData();
        $data=$this->parsePosition($data);
        Yii::log('Json data read for task', 'debug', 'TaskController');
        $model->attributes = $data;
        Yii::log('Json data set to model for task', 'debug', 'TaskController');

        // Manually set nested attributes
        Yii::log('Attributes are now ' . print_r($model->attributes, true), 'debug', 'TaskController');

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
        $model=$this->loadModel($id);

        $data = $this->readJsonData();
        if($data==''){
            echo $this->getNormalizedAnswerObject(false, $model, array('Got empty payload'));
            return;
        }
        $data=$this->parsePosition($data);
        $model->attributes=$data;

        Yii::log('Updating model: will have attributes '.print_r($model->attributes, true), 'debug', 'TaskController');

        if(!$model->validate()){
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        }
        else{
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
        $dataProvider = new CActiveDataProvider('Task');
        $this->apiIndex($dataProvider->getData(), $this->defaultLoads);
    }

    /**
     * Get all children of a task
     * @param $id The task whose children must be retrieved
     */
    public function actionChildren($id)
    {
        $model = $this->loadModel($id);
        $tasks = $model->tasks;
        Yii::log('Get children of task "' . $id . '": ' . print_r($tasks, true), 'debug', 'TaskController');
        $this->apiIndex($tasks);
    }


    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Task the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        $model = Task::model()->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        return $model;
    }


    //endregion Entity access methods

}
