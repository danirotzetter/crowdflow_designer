<?php
class CsController extends Controller
{


    //region CRUD functions for items
    /**
     * Display a model as JSON
     * @var model The model to handle
     * @var attributeNames The attributes to include in the answer. E.g. 'user.group, group.department'
     */
    public function apiView($model, $attributeNames = NULL)
    {
        Yii::log('Called apiView for model ' . print_r($model->attributes, true), 'debug', 'CsController');
        $outputModel = $this->readModelData($model, $attributeNames);
        Yii::log('Output for apiView: ' . print_r($outputModel, true), 'debug', 'CsController');
        $this->prepareHeader();
        echo $this->getNormalizedAnswerObject(true, $outputModel);
    }

    /**
     * Get series of models - works like readModelData(), but returns all model's data as an array instead of just returning one single model's data
     */
    public function apiIndex($models, $attributeNames = NULL)
    {

        $outputModels = array();

        Yii::log('Using API for ' . sizeof($models) . ' models. Outputting ' . sizeof($attributeNames) . ' attributeNames (' . print_r($attributeNames, true) . ')', 'debug', 'CsController');

        foreach ($models as $model)
            $outputModels[] = $this->readModelData($model, $attributeNames);

        $this->prepareHeader();
        echo $this->getNormalizedAnswerObject(true, $outputModels);
    }

    /**
     * Returns the result of the operation of creating a new object.
     * @var model The model to handle
     * @var success If storing was successful
     */
    public function apiCreate($model, $success)
    {
        Yii::log('Creating model ' . print_r($model->attributes, true), 'info', 'CsController');

        // Re-load the model to fetch all attributes, e.g. also the auto-generated 'date_created' column
        $model = $this->loadModel($model->id);

        $this->prepareHeader($success ? 201 : 500);
        echo $this->getNormalizedAnswerObject($success, $this->readModelData($model));
        Yii::log('Created model ' . print_r($model->attributes, true), 'debug', 'CsController');
    }

    /**
     * Returns the result of the operation of updating an existing object.
     * @var model The model to handle
     * @var success If storing was successful
     */
    public function apiUpdate($model, $success)
    {
        Yii::log('Updating model ' . print_r($model->attributes, true), 'debug', 'CsController');

        // Make sure that all attributes are parsed correctly
        if(is_a($model, 'CCsActiveRecord'))
            $model->afterFind();

        $this->prepareHeader($success ? 200 : 500);
        echo $this->getNormalizedAnswerObject($success, $this->readModelData($model));
        Yii::log('Updated model ' . print_r($model->attributes, true), 'info', 'CsController');
    }

    /**
     * Returns the result of the operation of deleting an existing object.
     * @var model The model that was deleted
     * @var success If deletion was successful
     */
    public function apiDelete($model, $success)
    {
        Yii::log('Deleted model ' . print_r($model, true), 'info', 'CsController');
        $this->prepareHeader($success ? 200 : 500);
        echo $this->getNormalizedAnswerObject($success, $this->readModelData($model));
    }

    /**
     * Deletes a particular model.
     * @param integer $id the ID of the model to be deleted
     */
    public function actionDelete($id)
    {
        Yii::log('Deleting model ' . $id, 'debug', 'CsController');
        $model = $this->loadModel($id);
        $success = $model->delete();
        $this->apiDelete($model, $success);
    }

    /**
     * Read the model data. Takes as argument a database object and converts it to a JSON object. Depending on the attributes, related items are added recursively
     * to the resulting json such that they are immediately available (instead of just containing a foreign key to the related object)
     * IMPORTANT: Also adds the type
     * @param $model The model to read
     * @param null $attributeNames The attributes to include in the answer. E.g. 'user.group, group.department'
     * @return object the JSON object with the attributes of the model
     * @throws CHttpException If model NULL
     */
    public function readModelData($model, $attributeNames = NULL)
    {
        $hasAttributes = $attributeNames != NULL;
        if ($model == NULL)
            throw new CHttpException(500, 'Cannot read data of a NULL model.');
        Yii::log('Reading model data for model ' . print_r($model->attributes, true) . ' models. Outputting ' . ($hasAttributes ? sizeof($attributeNames) : '0') . ' attributeNames' . ($hasAttributes ? ' ' . print_r($attributeNames, true) : ''), 'debug', 'CsController');

        if ($attributeNames == NULL || sizeof($attributeNames) == 0) {
            Yii::log('No need to include relations: returning all attributes immediately', 'debug', 'CsController');
            // No attribute relations to include
            $attrs = $model->attributes;
            $attrs['type'] = strtolower(get_class($model));
            return $attrs;
        }

        $modelAttributes = array(); //Attribute values of the model for the attributes that have to be included

        $modelAttributes['type'] = strtolower(get_class($model));;

        // Fetch the non-relation attributes (i.e. display the attribute value)
        foreach ($model->model()->tableSchema->columns as $modelAttribute) {
            if (!$modelAttribute->isForeignKey) {
                Yii::log('Checking attribute ' . $modelAttribute->name . ': including this attribute as non-relation in the model', 'debug', 'CsController');
                $modelAttributes[$modelAttribute->name] = $model[$modelAttribute->name];
            } else {
                Yii::log('Checking attribute ' . $modelAttribute->name . ': skipping this attribute since this is a relation', 'debug', 'CsController');
            }
        }


        // Fetch the relation attributes (i.e. link with the relation model)
        foreach ($attributeNames as $attributeName) {
            $attributeName = trim($attributeName); //in case of spaces around commas
            Yii::log('Check model to get attribute "' . $attributeName . '"', 'debug', 'CsController');
            $attrValue = CHtml::value($model, $attributeName); //this function walks the relations
            Yii::log('Got attribute value', 'debug', 'CsController');
            if ($attrValue == NULL) {
                // No need to read invalid/ NULL relation
                Yii::log('Skipping attribute "' . $attributeName . '" for this model, since the relation object is not set (NULL)', 'debug', 'CsController');
            } else {
                // Need to read relation
                if (!is_array($attrValue)) {
                    // Relation is a single object
                    $recursiveModelValues = $this->readModelData($attrValue, $this->getAttributeNamesForRelation($attributeName, $attributeNames));
                } else {
                    // Relation is ana array of objects
                    Yii::log('Recursively retrieving array of object relations', 'debug', 'CsController');
                    $recursiveModelValues = array();
                    foreach ($attrValue as $singleAttrValue) {
                        $recursiveModelValues[] = $this->readModelData($singleAttrValue, $this->getAttributeNamesForRelation($attributeName, $attributeNames));
                    }
                }
                $modelAttributes[$attributeName] = $recursiveModelValues;
            }
        }
        Yii::log('Returning model data ' . print_r($modelAttributes, true), 'debug', 'CsController');
        return $modelAttributes;
    }

//endregion CRUD functions for items







    //region Connection-related functions


    /**
     * Delete a connection
     * @param $workspaceId
     * @param $sourceType
     * @param $sourceId
     * @param $targetType
     * @param $targetId
     */
    public function deleteConnection($workspaceId, $sourceType, $sourceId, $targetType, $targetId)
    {
        Yii::log("Deleting connection from $sourceId of type '$sourceType' to $targetId of type '$targetType' in workspace $workspaceId");
        if ($sourceType != $targetType) {
            $sourceIdColumn = $sourceType . '_id';
            $targetIdColumn = $targetType . '_id';
        } else {
            // Connections between the same type: columns have 'source' and 'target' prefix
            $sourceIdColumn = 'source_' . $sourceType . '_id';
            $targetIdColumn = 'target_' . $targetType . '_id';
        }
        $table = strtolower($sourceType) . '_' . strtolower($targetType);
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . $sourceIdColumn . '=' . $sourceId . ' AND ' . $targetIdColumn . '=' . $targetId . ' AND workspace_id=' . $workspaceId;
        $cmd = Yii::app()->db->createCommand($sql);
        $affected = $cmd->execute();
        if ($affected == 0)
            Yii::log('Connection not found', 'debug', 'CsController');
        if ($affected == 0)
            Yii::log('Connection deleted', 'debug', 'CsController');
    }

    //endregion Connection related functions


    //region Helper functions
    /**
     * Reads the array of $attributeNames and returns a new array that consists only the relations that are associated with the current model. This means,
     * that all array items of $attributeNames are returned which start with $attributeName, whereby the $attributeName part will be removed.
     * Explanation:
     * When reading the model attributes, one can indicate the $attributeNames which indicate, which relations should be loaded together with the model
     * (e.g. to load the tasks of the user object, one supplies $attributeNames=array('tasks')). When those relation objects are loaded, they, too, might
     * have to load associated relations (e.g. in the previous example, one could also request $attributeNames=array('tasks', 'tasks.workspace') in order to
     *read the user, the user's tasks and the workspaces of the user's tasks). So while reading the relations of the relations (i.e. the 'tasks' in the example),
     * the attributeNames must be adapted to only include the direct path (one must retrieve the array('workspace'), when the task model is loaded). To do so,
     * this method is called.
     *
     * @param $attributeName
     * @param $attributeNames
     * @return array
     */
    public function getAttributeNamesForRelation($attributeName, $attributeNames)
    {
        $stringToSearch = $attributeName. '.'; // An object relation's relations are separated by a dot
        $stringLength = strlen($stringToSearch);
        $attributeNamesOfThisRelation = array();
        foreach ($attributeNames as $attrToSearch) {
            if (strpos($attrToSearch, $stringToSearch) === 0) {
                // Found a relation's relation: add it to the answer array
                $attributeNameToAdd = substr($attrToSearch, $stringLength);
                $attributeNamesOfThisRelation[] = $attributeNameToAdd;
            }
        }
        // End for each attribute name
        return $attributeNamesOfThisRelation;
    }



    /**
     * Parse the REST request data to a json object
     * This is necessary due to the fact that AngularjS sends the data as simple HTTP contents string and not as form fields.
     * Therefore, we will read this content and transform it such that the controllers can work with a "regular" file
     */
    public function readJsonData()
    {

        $data = file_get_contents("php://input");
        Yii::log('Reading Json request: \'' . $data . '\'', 'debug', 'CsController');

        if ($data == '') {
            Yii::log('Json is empty: return empty JSON object', 'debug', 'CsController');
            return json_decode('{}', true);
        } else if ($data == '[]') {
            Yii::log('Json is empty array: return empty JSON array', 'debug', 'CsController');
            return json_decode('[]', true);
        }

        $jsonData = json_decode($data, true);

        if ($jsonData == NULL)
            $this->sendError(500, 'Cannot parse JSON data \'' . $data . '\'');
        return $jsonData;
    }

    /**
     * Converts positions to integers such that the model data can be stored in the integer-based columns
     * @param $model
     * @return The model with parsed position values
     */
    public function parsePosition($model)
    {
        $xExists = array_key_exists('pos_x', $model);
        $yExists = array_key_exists('pos_y', $model);
        Yii::log('Parsing positions for model '.print_r($model, true).' Pos_x exists? '.($xExists? 'true':'false'), 'debug', 'CsController');

        if($xExists)
                $xVal = intval($model['pos_x']);
            else
                $xVal=0;
        $model['pos_x'] = $xVal;

        if($yExists)
                $yVal = intval($model['pos_y']);
        else
                $yVal=0;
        $model['pos_y'] = $yVal;

        return $model;
    }

    /**
     * 'Flatten' the model errors
     */
    public function getModelErrorsAsArray($model)
    {
        $result = array();
        foreach ($model->getErrors() as $prop => $errs) {
            $errorsThisProperty = 'Property \'' . $prop . '\': ';
            foreach ($errs as $err) {
                $errorsThisProperty .= $err;
            }
            $result[] = $errorsThisProperty;
        }
        return $result;
    }

//endregion Helper functions


    //region API answer related functions
    /**
     * Throw an error message to a REST request. This method is used when a REST request is called, but an error
     * happened during message preparation. This means that there will be no data sent along with the answer, but
     * just the error message.
     */
    public function sendError($errorCode = 404, $errorMessage = '')
    {
        //Remove new lines as the error message will be sent in the header, whereas the header does not accept newlines
        $errorMessage = trim(preg_replace('/\s+/', ' ', $errorMessage));
        Yii::log('Error occurred: (' . $errorCode . ') "' . $errorMessage . '"', 'error', 'CsController');
        Yii::log('Error information: GET data: ' . print_r($_GET, true) . ', POST data: ' . print_r($_POST, true), 'debug', 'CsController');
        $this->prepareHeader($errorCode, $errorMessage);
        echo $this->getNormalizedAnswerObject(false, NULL, $errorMessage);
        Yii::app()->end();
    }


    /**
     * Generate a REST answer.
     * The result is a JSON object. If $data is a JSON object itself, it will be first parsed to string in order to JSON-encode it later.
     * The result has the form
     *{success: bool
     *data: json
     *errors: array()
     *}
     * @param $success boolean If the API call was treated successfully
     * @param $data object The payload
     * @param null $errors A string or an array of string indicating the errors that occurred
     * @return string
     */
    public function getNormalizedAnswerObject($success=true, $data=NULL, $errors = NULL)
    {
        $success = $success ? true : false;
        Yii::log('Get REST answer. Success: ' . ($success ? 'true' : 'false') . ', data: ' . print_r($data, true) . ', errors: ' . print_r($errors, true), $success? 'debug':'error', 'CsController');

        // Create the result object
        $result = array();

        $result['success'] = $success;
        if ($errors != NULL) {
            if (is_array($errors))
                $result['errors'] = $errors;
            else
                // Single message: wrap the single string error message in an array, since the 'errors' item contains an array of errors
            $result['errors'] = array($errors);
            Yii::log('There are errors to send: \'' . print_r($errors, true) . '\'', 'debug', 'CsController');
        }
        // End errors available

        if ($data != NULL) {
            Yii::log('Data is not null', 'debug', 'CsController');
            $type = gettype($data);
            Yii::log('Answer is of type ' . $type, 'debug', 'CsController');
            if ($type == 'object') {
                Yii::log('Parsing object data', 'debug', 'CsController');
                // Data is defined as object: may directly json_encode it later
                $result['data'] = $data;
            } else if ($type == 'string') {
                if ($data == '') {
                    $data = '<empty string>';
                } else {
                    // json_decode returns null if $data is not JSON
                    $decoded = json_decode($data);
                    // Data is defined as string: if in JSON, we must first convert it back
                    if ($decoded != NULL) {
                        Yii::log('Decoded data: from \'' . $data . '\' to \'' . print_r($decoded, true) . '\'', 'debug', 'CsController');
                        $data = $decoded;
                    } else {
                        Yii::log('No need to decode data - is not JSON', 'debug', 'CsController');
                    }
                    Yii::log('Setting data for result object', 'debug', 'CsController');
                    $result['data'] = $data;
                }
                // End data not empty string
            } else if ($type == 'array') {
                if (empty($data))
                    $data = '<empty array>';
                Yii::log('Parsing array data \'' . print_r($data, true) . '\'', 'debug', 'CsController');
                $result['data'] = $data;
            } else {
                Yii::log('Not implemented how to parse data of type ' . $type . ' result object: using unparsed data "' . $data . '"', 'warning', 'CsController');
                $result['data'] = $data;
            }
        } // End data not null
        else {
            Yii::log('Data is null', 'debug', 'CsController');
            // data is null
            if ($success) {
                // Empty array as result
                Yii::log('Successful request: result is empty', 'debug', 'CsController');
                $result['data'] = $data;
            } else {
                // Error occurred, no data available. Return null as data object s.t. the array still contains the data key
                Yii::log('Error happened: data is null, success is false', 'debug', 'CsController');
                $result['data'] = NULL;
            }

        }
        return json_encode($result);

    }


    /**
     * Prepare a header for an API call response
     * @var statusCode The status code to use
     * @var message Custom message to send along with the status code
     * @var contentType The content type of the response
     */
    public function prepareHeader($statusCode = 200, $message = NULL, $contentType = 'application/json')
    {
        Yii::log('Preparing header using statusCode ' . $statusCode . ', message \'' . $message . '\', contentType ' . $contentType, 'debug', 'CsController');
        if ($message == NULL) {
            // If no custom message provided: use default HTTP messages
            switch ($statusCode) {
                case 200:
                    $message = 'Request success';
                    break;
                case 201:
                    $message = 'Entity created';
                    break;
                case 202:
                    $message = 'Request accepted';
                    break;
                case 400:
                    $message = 'Bad request';
                    break;
                case 401:
                    $message = 'You must be authorized to view this page.';
                    break;
                case 403:
                    $message = 'Forbidden';
                    break;
                case 404:
                    $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';
                    break;
                case 405:
                    $message = 'Method not allowed';
                    break;
                case 406:
                    $message = 'Not acceptable';
                    break;
                case 408:
                    $message = 'Request timeout';
                    break;
                case 415:
                    $message = 'Unsupported media type';
                    break;
                case 500:
                    $message = 'Internal server error';
                    break;
                case 501:
                    $message = 'Method not implemented';
                    break;
                case 502:
                    $message = 'Bad gateway';
                    break;
                case 503:
                    $message = 'Service unavailable';
                    break;
                case 504:
                    $message = 'Gateway timeout';
                    break;
                default:
                    $message = 'Error undefined';
            }
        }
        // End message is null

        $statusHeader = 'HTTP/1.1 ' . $statusCode . ' ' . $message;
        header($statusHeader);
        if (!isset($_GET['log']))
            header('Content-type: ' . $contentType);
    }
    //endregion API answer related functions

}