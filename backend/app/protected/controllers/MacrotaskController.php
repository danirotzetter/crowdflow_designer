<?php

class MacrotaskController extends CsController
{

    private $defaultLoads = array('inputs');


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
		$model=new Macrotask;


		$data = $this->readJsonData();
		$model->attributes=$data;

		if(!$model->validate()){
			echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
		}
		else{
            Yii::log('Saving object '.print_r($model->attributes, true), 'debug', 'MacrotaskController');
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

        $model->attributes=$data;

        if(!$model->validate()){
            echo $this->getNormalizedAnswerObject(false, $data, $this->getModelErrorsAsArray($model));
        }
        else{
            Yii::log('Saving object '.print_r($model->attributes, true), 'debug', 'MacrotaskController');
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
		$dataProvider=new CActiveDataProvider('Macrotask');
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
		$model=Macrotask::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

}
