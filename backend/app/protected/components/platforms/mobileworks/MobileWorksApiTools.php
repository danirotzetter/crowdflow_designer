<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 20.07.13
 * Time: 17:23
 * To change this template use File | Settings | File Templates.
 */

class MobileWorksApiTools
{

    //region Configuration parameters access
    private function getUsername()
    {
        return '<TODEFINE:USER_NAME>';
    }

    private function getPassword()
    {
        return '<TODEFINE:PASSWORD>';
    }

    private function getSandboxMode()
    {
        return Yii::app()->params['sandboxMode'];
    }

    /**
     * Check whether the Ssl certificate check of the server should be disabled.
     * Currently, the API only functions with this parameter set to 'true'
     * @return bool
     */
    private function getDisableSslCheck(){
        return true;
    }

    /**
     * Get the base URL that is used to send API queries
     * @return string
     */
    private function getBaseUrl()
    {
        if ($this->getSandboxMode())
            return 'https://sandbox.mobileworks.com/api/v1/';
        else
            return 'https://work.mobileworks.com/api/v1/';
    }

    /**
     * Generates and returns a fully generated MobileWorksLib object that can be used to configure an API call
     * @return MobileWorksLib
     */
    public function getMobileWorksLib()
    {
        // General request setup
        $mw = new MobileWorksLib();
        $mw->username = $this->getUsername();
        $mw->password = $this->getPassword();
        $mw->domain = $this->getBaseUrl();
        $mw->disableSslCheck = $this->getDisableSslCheck();
        return $mw;
    }

    /**
     * Execute an Api call
     */
    public function sendRequest($urlSuffix)
    {

        Yii::log('Api call for operation "' . $urlSuffix . '"', 'info', 'MobileWorksApiToolsr');

        $mw = $this->getMobileWorksLib();

        // Initialize the answer object
        $result = Array();

        try {
            $data = $mw->make_request($mw->domain. $urlSuffix)['content'];
            $result['success'] = true;
            // Parse to associative array
            $arr = json_decode($data, true);
            $result['data'] = $arr;
            Yii::log('Successfully sent MobileWorks API request', 'info', 'MobileWorksApiTools');
            return $result;
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code = intval(substr($message, 5, 3));
            Yii::log('Failure sending MobileWorks API request: code ' . $code . ', error message: ' . $message, 'debug', 'MobileWorksApiTools');
            $errors = array();
            if ($code == 401)
                $errors[] = array(
                    'Code' => $code,
                    'Message' => 'Login failure',
                );
            else
                $errors[] = array(
                    'Code' => $code,
                    'Message' => 'Server error',
                );
            $result['success'] = false;
            $result['errors'] = $errors;
            return $result;
        }

    }
}