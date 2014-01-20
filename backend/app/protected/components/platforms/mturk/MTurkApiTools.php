<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 20.07.13
 * Time: 14:06
 * To change this template use File | Settings | File Templates.
 */

class MTurkApiTools
{


//region Config properties
    /**
     * The URL with which the API of MTurk can be called
     * @return string
     */
    private function getBaseUrl()
    {
        if (Yii::app()->params['sandboxMode'])
            return 'https://mechanicalturk.sandbox.amazonaws.com';
        else
            return 'https://mechanicalturk.amazonaws.com';
    }

    /**
     * The URL to which the form data from a worker should be submitted
     * @return string
     */
    public function getFormBaseUrl()
    {
        if (Yii::app()->params['sandboxMode'])
            return 'https://workersandbox.mturk.com/mturk/externalSubmit?';
        else
            return 'https://www.mturk.com/mturk/externalSubmit?';
    }

    /**
     * The type of service that is requested from MTurk
     * @return string
     */
    private function getService()
    {
        return 'AWSMechanicalTurkRequester';
    }

    /**
     * Get the MTurk SecretAccessKey
     * @return string
     * @throws CHttpException
     */
    private function getSecretAccessKey()
    {
        return '<TODEFINE:SECRET_ACCESS_KEY>';
    }

    /**
     * Get the MTurk AccessKeyId
     * @return string
     * @throws CHttpException
     */
    private function getAccessKeyId()
    {
        return '<TODEFINE:ACCESS_KEY_ID>';
    }

    /**
     * URL to access a to-be-crowdsourced task
     * @param $model
     * @param $flowItemId
     * @return string
     */
    private function getUrlOfTaskForm($model, $flowItemId)
    {
        $type = strtolower(get_class($model));
        $url = Yii::app()->params['backendBaseUrl'].'CrowdsourceForms/itemFormView?itemType=' . $type . '&amp;itemId=' . $model->id.'&amp;flowItemId='.$flowItemId;
        Yii::log('Returning the url of the task form for model ' . $model->id . ' of type \'' . $type . '\': ' . $url, 'debug', 'MTurkApiTools');
        return $url;
    }

    //endregion


    //region MTurk API methods

    /**
     * Converts a parameter that can be a task model or a hit id to an array of hitIds
     * @param $modelOrHitId
     * @return array The list of HIT ids that are published for a model, or an array containing the single hitId submitted as a parameter
     */
    public function getHitIdFromModelOrId($modelOrHitId)
    {
        $hitIds = array();

        if (is_object($modelOrHitId)) {
            Yii::log('Getting HIT ids from model ' . $modelOrHitId->id . ' of type ' . get_class($modelOrHitId), 'debug', 'MTurkApiTools');
            $pd = $modelOrHitId->platform_data;
            if(is_string($pd))
                $pd = json_decode($pd, true);

            if ($pd != NULL && is_array($pd) && array_key_exists('tasks', $pd) && $pd['tasks'] != NULL) {
                // HIT ids available: browse through each task in order to read its hitId
                foreach($pd['tasks'] as $task){
                    if(array_key_exists('data', $task)&& array_key_exists('HITId', $task['data']))
                        $hitIds[] = $task['data']['HITId'];
                }
            }
        } else {
            Yii::log('Getting HIT id from identifier \'' . $modelOrHitId . '\' - this is the HIT id itself', 'debug', 'MTurkApiTools');
            $hitIds = array($modelOrHitId);
        }
        Yii::log('Getting HIT ids: '.(join(',', $hitIds)), 'debug', 'MTurkApiTools');
        return $hitIds;
    }

    /**
     * Send an API request to the MTurk platform
     * @param $parameters The operation name or an associative array containing the operation name along with additional optional parameters
     * @return The result as an associative array
     */
    public function sendRequest($parameters)
    {
        Yii::log('Sending request with parameters ' . print_r($parameters, true), 'debug', 'MTurkApiTools');

        if ($parameters == NULL) {
            return array('success' => false, 'errors' => 'Invalid arguments: must provide request parameters');
        }

        // Handle the case where only the operation name is given
        if (is_array($parameters)) {
            // Normal call with array of parameters
            if (!array_key_exists('Operation', $parameters)) {
                return array('success' => false, 'errors' => 'No operation parameter given');
            }
        } else {
            /* API call without additional parameters
            * We will use an associative array to create the API call URL
             */
            $parameters = array('Operation' => $parameters);
        }


        // Add default values for mandatory parameters

        // Special handling for response group
        if (!array_key_exists('ResponseGroup', $parameters)) {
            $parameters['ResponseGroup'] = 'Minimal';
        } else {
            // Request will have to 'ResponseGroup' parameters (otherwize, 'Minimal' would be skipped and thus important response data would be lost)
            $parameters['ResponseGroup.1'] = $parameters['ResponseGroup'];
            $parameters['ResponseGroup.2'] = 'Minimal';

            // When using two parameters, there a special format is required by the API: remove the initial parameter that does not have a number indication
            unset($parameters['ResponseGroup']);
        }

        $operation = $parameters['Operation'];
        Yii::log('Sending request \'' . $operation . '\'', 'debug', 'MTurkApiTools');

        // Generate the url
        $url = $this->getBaseUrl() . '?Service=' . $this->getService() . '&AWSAccessKeyId=' . $this->getAccessKeyId() . '&Timestamp=' . $this->getTimestamp() . '&Signature=' . $this->getSignature($operation);

        // The parameters will be added url-encoded
        foreach ($parameters as $key => $value) {
            $url = $url . '&' . $key . '=' . urlencode($value);
        }

        Yii::log('Request API will be: \'' . $url . '\'', 'debug', 'MTurkApiTools');


        // Read the data in XML format
        $errorCode=0;
        if(!$this->call($url, $restResult, $errorCode)){
            // Cannot call the MTUrk api
            return array(
                'success'=>false,
                'data'=>$restResult,
                'errors'=>array(
                    'Cannot send web request to the MTurk API: failed to get the result of the request '.$url.' - got error code '.$errorCode,
                )
            );
        };

        // Parse to JSON
        $xml = new SimpleXMLElement($restResult);

        // Prepare the answer object
        $result = array();

        // Read the result and possible errors
        $errorsXml = $xml->xpath('//Errors/Error');
        $hasErrors = $errorsXml != NULL;
        $result['success'] = $hasErrors ? false : true;

        if ($hasErrors) {
            // Has errors
            $result['errors'] = array();
            foreach ($errorsXml as $errorXml) {
                Yii::log('Single error: ' . print_r($errorXml, true), 'debug', 'MTurkApiTools');
                $msgNode = $errorXml->xpath('//Message');
                $msg = $msgNode[0];
                $result['errors'][] = (string)$msg; // Do not modify - there was an error when calling $msg->textContent
            }
                Yii::log('Api call for operation \'' . $operation . '\' executed: has errors: ' . print_r($result['errors'], true), 'warning', 'MTurkApiTools');
        } else {
            // No errors
            $arr = json_decode(json_encode($xml), true);
            $result['data'] = $arr;
            Yii::log('Api call for operation \'' . $operation . '\' executed: has no errors, value: ' . print_r($arr, true) . ', result ' . print_r($result, true), 'info', 'MTurkApiTools');
        }
        return $result;
    }

    // End sendRequest


    //region Auxiliary calculations needed to access the MTurk Api
    /**
     * @param null $time
     * @return string
     */
    function getTimestamp($time = NULL)
    {
        if ($time == NULL)
            $time = time();
        return gmdate("Y-m-d\TH:i:s\\Z", $time);
    }


    /**
     * Generate the signature that must be sent along with an MTurk API Request
     * @param $operation
     * @return string
     */
    function getSignature($operation)
    {
        Yii::log('Start to generate signature', 'debug', 'MTurkApiTools');
        $timestamp = $this->getTimestamp();
        $service = $this->getService();
        $secretAccessKey = $this->getSecretAccessKey();

        $string_to_encode = $service . $operation . $timestamp;
        Yii::log('Generating signature with service ' . $service . ', operation ' . $operation . ', timestamp ' . $timestamp . ', secretAccessKey ' . $secretAccessKey, 'debug', 'ApiController');

        // Generate the signed HMAC signature AWS APIs require
        $hmac = $this->hasher($string_to_encode, $secretAccessKey);
        $hmac_b64 = $this->base64($hmac);
        return urlencode($hmac_b64);
    }

//endregion Auxiliary calculations needed to access the MTurk Api


    //region Helper methods for the MTurk API
    /*
        * Returns the HMAC for generating the signature
        * Algorithm adapted (stolen) from http://pear.php.net/package/Crypt_HMAC/ (via http://code.google.com/p/php-aws/)
        */
    private function hasher($data, $key)
    {
        if (strlen($key) > 64)
            $key = pack('H40', sha1($key));
        if (strlen($key) < 64)
            $key = str_pad($key, 64, chr(0));
        $ipad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
        $opad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));
        return sha1($opad . pack('H40', sha1($ipad . $data)));
    }

    /**
     * @param $str
     * @return string
     */
    private function base64($str)
    {
        $ret = '';
        for ($i = 0; $i < strlen($str); $i += 2)
            $ret .= chr(hexdec(substr($str, $i, 2)));
        return base64_encode($ret);
    }

    /*
    * Takes a UNIX timestamp and returns a timestamp in UTC
    */
    private function Unix2UTC($unix)
    {
        return date('Y-m-d\TH:i:s', $unix) . 'Z';
    }

    /**
     * Call the MTurk Api, gracefully catching error message
     * @param $url The URL to access
     * @param $content The answer of the succeeded url request
     * @param $errorCode Any error code from the web service
     * @param int $delay The delay after which the same request is re-sent, in seconds. If set to 0 or NULL or FALSE, the request is not re-transmitted
     * @param int attempts The number of requests that should be sent
     * @return Whether the call has succeeded
     */
    public function call($url, &$content, &$errorCode, $delay=1, $retries=1){
        Yii::log('MTurk call: calling URL '.$url.', attempt to send up to '.($retries+1).' requests with a delay of '.$delay.' seconds in between them', 'debug', 'MTurkApiTools');
        while($retries>=0 &&$retries!=false){
        $requestContent = @file_get_contents($url);
        if($requestContent===false){
            // Request has failed
            $errorCode = substr($http_response_header[0], 9,3);
            Yii::log('MTurk call failed, Error while calling MTurk URL '.$url.': error '.$errorCode, 'warning', 'MTurkApiTools');
            if($delay!=null && $delay!=0 &&$delay!=false){
            Yii::log('Sleeping for '.$delay.' seconds before re-issuing the request', 'debug', 'MTurkApiTools');
                sleep($delay);
                $retries-1;
            }
            else{
                // Undefined or no delay - do not re-send the request
                $content='';
                return false;
            }
        }
            else{
                // Request has succeeded
                Yii::log('MTurk call succeeded', 'debug', 'MTurkApiTools');
                $content=$requestContent;
                return true;
            }
        }
        // Define the error code if not set
        if($errorCode==null || $errorCode==0 || $errorCode=='' || $errorCode==false)
            $errorCode=500;
        $content='';
        Yii::log('MTurk call failed: could not get a result after '.$retries.' attempts - got error '.$errorCode, 'error', 'MTurkApiTools');
        return false;
    }

    //endregion Helper methods for the MTurk API

    //endregion MTurk API methods


    //region HIT creation


    /**
     * Constructs the associative array of parameters that are needed to send an API request to MTurk when creating a new HIT
     * @param $model
     * @param $flowItemId
     * @param $maxAssignmentsOverride The number of assignments required/ accepted for this task. If not set, the model's max_assignments value is used.
     * @return array
     */
    public function getParamsToCreateHIT($model, $flowItemId, $maxAssignmentsOverride=null)
    {
        $parameters = array();
        $parameters['Operation'] = 'CreateHIT';
        $parameters['Title'] = $model->name;
        // Use a prefix in order to identify the tasks quicker in the sandbox mode
        $prefix = Yii::app()->params['prefix'];
        $parameters['Title'] = $prefix. $model->name;
        if(Yii::app()->params['addWebAppData']) // Add additional data to the task title
            $parameters['Title']=$parameters['Title']. ' (' .strtolower(get_class($model)).' '. $model->id . ' from ' . date('Y-m-d H:i:s') . ')';
        $parameters['Description'] = $model->description;
        $parameters['Reward.1.Amount'] = $model->parameters['reward'];
        $parameters['Reward.1.CurrencyCode'] = 'USD';
        $parameters['AssignmentDurationInSeconds'] = $model->parameters['assignment_duration'];
        $parameters['LifetimeInSeconds'] = $model->parameters['lifetime'];
        $parameters['Keywords'] = $model->parameters['keywords'];

        // Evaluate the number of assignments accepted
        if($maxAssignmentsOverride==null){
            // No argument supplied - take the information from the model
            $itemParameters = $model->parameters;
            if(is_string($parameters))
                $itemParameters = json_decode($itemParameters, true);
            $itemParameterMaxAssignments = $itemParameters['max_assignments'];
            Yii::log('Using the parameter from the item in order to get the number of maximum assignments: '.$itemParameterMaxAssignments, 'debug', 'MTurkApiTools');
            $parameters['MaxAssignments'] = $itemParameterMaxAssignments;
        }
        else{
            // Use the supplied argument
            Yii::log('Using the function argument in order to get the number of maximum assignments: '.$maxAssignmentsOverride, 'debug', 'MTurkApiTools');
            $parameters['MaxAssignments'] = $maxAssignmentsOverride;
        }

        $parameters['AutoApprovalDelayInSeconds'] = 2592000;
        $parameters['Question'] = '<ExternalQuestion xmlns="http://mechanicalturk.amazonaws.com/AWSMechanicalTurkDataSchemas/2006-07-14/ExternalQuestion.xsd"><ExternalURL>' . $this->getUrlOfTaskForm($model, $flowItemId) . '</ExternalURL><FrameHeight>600</FrameHeight></ExternalQuestion>';

        return $parameters;

    }

    //endregion HIT creation
}