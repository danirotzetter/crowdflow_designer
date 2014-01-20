<?php

class PostprocessorController extends CsController
{

    private $defaultLoads = array();

//endregion Entity access methods

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
		$model=new Postprocessor;


		$data = $this->readJsonData();
        $data=$this->parsePosition($data);
		$model->attributes=$data;

		if(!$model->validate()){
			echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
		}
		else{
            Yii::log('Saving object '.print_r($model->attributes, true), 'debug', 'PostprocessorController');
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
        Yii::log('Before positions parsed '.print_r($data, true), 'debug', 'PostprocessorController');
        $data=$this->parsePosition($data);
        Yii::log('After positions parsed '.print_r($data, true), 'debug', 'PostprocessorController');

        $model->attributes=$data;

        if(!$model->validate()){
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        }
        else{
            Yii::log('Saving object '.print_r($model->attributes, true), 'debug', 'PostprocessorController');
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
		$dataProvider=new CActiveDataProvider('Postprocessor');
		$this->apiIndex($dataProvider->getData(), $this->defaultLoads);
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
		$model=Postprocessor::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}
//endregion Entity access methods



}
