<?php
class ConnectionController extends CsController
{
private $defaultLoads=array();

    /**
     * @param $sourceType
     * @param $sourceId
     * @param $targetType
     * @param $targetId
     * @param $workspaceId
     */
    public function actionView($sourceType, $sourceId, $workspaceId, $targetType=NULL, $targetId=NULL)
	{
        $data=NULL;
        if($targetType==NULL){
            $data=array();
            Yii::log('Get all possible connections from source \''.$sourceId.'\' of type \''.$sourceType.'\'', 'debug', 'ConnectionController');
            // Get all connections, not only to a specified type
            foreach($this->getAllPossibleTargetTypesOfSourceType($sourceType) as $possibleTargetType){
                $data = array_merge($data, $this->getConnection($sourceType, $sourceId, $possibleTargetType, NULL, $workspaceId));
            }
        }
        else{
            // Get a single connection
            Yii::log('Get the connection from source \''.$sourceId.'\' of type \''.$sourceType.'\' to target \''.$targetId.'\' of type \''.$targetType.'\'', 'debug', 'ConnectionController');
		    $data = $this->getConnection($sourceType, $sourceId, $targetType, $targetId, $workspaceId);
        }
        $this->prepareHeader();
        echo $this->getNormalizedAnswerObject(true, $data);
	}



	/**
	 * Creates a new model.
	 */
	public function actionCreate()
	{
        Yii::log('Creating new connection', 'debug', 'ConnectionController');
		$data = $this->readJsonData();

        $success = $this->saveModel($data);

        $this->prepareHeader($success? 201:500);
        echo $this->getNormalizedAnswerObject($success, $data);
	}

	/**
	 * Updates a particular model.
	 */
	public function actionUpdate($sourceType, $sourceId, $targetType, $targetId,  $workspaceId)
	{
        Yii::log('Update the connection from source \''.$sourceId.'\' of type \''.$sourceType.'\' to target \''.$targetId.'\' of type \''.$targetType.'\'', 'debug', 'ConnectionController');

		$data['sourceId']=$sourceId;
        $data['sourceType']=$sourceType;
		$data['targetId']=$targetId;
        $data['targetType']=$targetType;
		$data['workspaceId']=$workspaceId;



        // There are so far no additional data to be read for a connection
        //$additionalData = $this->readJsonData();

         $success = $this->saveModel($data);
        $this->prepareHeader($success? 200:500);
        echo $this->getNormalizedAnswerObject($success, $data);
	}


    /**
     * Stores a series of connections. Used for example when saving a workspace
     */
    public function actionSaveBulk(){
        Yii::log('Bulk saving connections', 'debug', 'ConnectionController');
        /*
         *  Note that the connections are sent in a JSON object, wrapped behind the 'connections' property. The reason is that
         *  AngularJS must send AND receive a query in the same format. And since the result object of any CrowdSourcing API call is not an array but a JSON
         * object, we also need to send the connections as a JSON object instead of an array.
         */
        $data = $this->readJsonData();
        if(!array_key_exists('connections', $data))
            $this->sendError(400, 'Must provide connections information in the request');
        $data = $data['connections'];
        $successConnections=array();
        $errorConnections=array();
        foreach($data as $connection){
            Yii::log('Bulk saving connection '.print_r($connection, true), 'debug', 'ConnectionController');
            if($this->saveModel($connection)){
                // Could store connection
                $successConnections[]=$connection;
            Yii::log('Successfully bulk saved connection '.print_r($connection, true), 'debug', 'ConnectionController');
            }
            else{
                // Could not store connection
                $errorConnections[]=$connection;
                Yii::log('Error while bulk saving connection '.print_r($connection, true), 'debug', 'ConnectionController');
            }
        }// End for each connection

        $errorsOccurred = (sizeof($errorConnections)>0);
        $this->prepareHeader($errorsOccurred? 500:200);

        echo $this->getNormalizedAnswerObject(!$errorsOccurred, $successConnections, $errorConnections);
    }


    /**
     * Stores a connection into the database unless it already exists
     * @param $data
     * @param bool $remove If the connection should be detached
     * @return bool Whether the operation was successful
     */
    private function saveModel($data, $remove=false){
    Yii::log('Save connection '.print_r($data, true), 'debug', 'ConnectionController');
        $sourceType=strtolower($data['sourceType']);
        $targetType=strtolower($data['targetType']);

        $sourceId=$data['sourceId'];
        $targetId=$data['targetId'];
        $workspaceId=$data['workspaceId'];

        $sourceIdColumn=NULL;
        $targetIdColumn=NULL;

        // Calculate the id columns
        if($sourceType!=$targetType){
            $sourceIdColumn = $sourceType.'_id';
            $targetIdColumn = $targetType.'_id';
        }
        else{
            // Connections between the same type: columns have 'source' and 'target' prefix
            $sourceIdColumn = 'source_'.$sourceType.'_id';
            $targetIdColumn = 'target_'.$targetType.'_id';
        }
        $table=strtolower($sourceType).'_'.strtolower($targetType);

        // Special handling for connections to workspace
        $workspaceRestriction=($targetType=='workspace')?'':' AND workspace_id='.$workspaceId;
        $sql='SELECT COUNT(*) FROM '.$table.' WHERE '.$sourceIdColumn.'='.$sourceId.' AND '.$targetIdColumn.'='.$targetId.$workspaceRestriction;
        $db = Yii::app()->db;
        $cmd = $db->createCommand($sql);
        $count = $cmd->queryScalar();
        if($count>0){
            if($remove){
                // Request to detach the connection
            Yii::log('Deleting connection '.print_r($data, true), 'debug', 'ConnectionController');
                // Special handling for connections to workspace
                $workspaceRestriction=($targetType=='workspace')?'':' AND workspace_id='.$workspaceId;
            $sql = 'DELETE FROM '.$table.' WHERE '.$sourceIdColumn.'=\''.$sourceId.'\' AND '.$targetIdColumn.'= \''.$targetId.'\''.$workspaceRestriction;
            $cmd=$db->createCommand($sql);
            $cmd->execute();
            Yii::log('Successfully deleted connection '.print_r($data, true), 'debug', 'ConnectionController');
                return true;
            }
            else{
                // No need to update: already stored in db
            Yii::log('Trying to update '.print_r($data, true).': no need to update, is already in the database', 'debug', 'ConnectionController');
            return true;
            }
        }
        else{
            // Special handling for connections to workspace
            $workspaceInsertRestriction=($targetType=='workspace')?'':' , workspace_id';
            $workspaceValueRestriction=($targetType=='workspace')?'':', \''.$workspaceId.'\'';
            $sql = 'INSERT INTO '.$table.'('.$sourceIdColumn.', '.$targetIdColumn.$workspaceInsertRestriction.') VALUES (\''.$sourceId.'\', \''.$targetId.'\''.$workspaceValueRestriction.')';
            $cmd=$db->createCommand($sql);
            $cmd->execute();
            Yii::log('Successfully inserted '.print_r($data, true), 'debug', 'ConnectionController');
            return true;
        }
    }



}
