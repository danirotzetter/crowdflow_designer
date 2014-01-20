<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 20.07.13
 * Time: 17:22
 * To change this template use File | Settings | File Templates.
 */
class MobileWorks {

    private $tools;

    /**
     * Initializes the component
     */
    public function init(){
        $this->tools = new MobileWorksApiTools();
    }


    public function getAccountBalance(){
        $result = $this->tools->sendRequest('userprofile/?format=json');
        if($result['success']){
            Yii::log('Got answer '.print_r($result['data'], true), 'debug', 'MobileWorks');
            $val = $result['data']['objects'][0]['balance'];
            $result['data']=$val;
        }
        return $result;
    }

    /**
     * Generates a new task
     * @return array Containing the URL to the task that has to be solved by the crowd
     */
    public function createTask(){
        Yii::log('Creating new task', 'debug', 'MobileWorksApiTools');
        $mw = $this->tools->getMobileWorksLib();
        $t = $mw->Task();
        /*				$t->set_param('instruction','What is the name on this business card?');
                        $t->set_param("resource", "http://www.mobileworks.com/images/samplecard.jpg");
                        $t->add_field("Name", "t"); */
        $t = $mw->Task(array("instructions"=>"What is the name on this business card?"));
        $t->set_param("resource", "http://www.mobileworks.com/images/samplecard.jpg");
        $t->add_field("Name", "t");
        $task_url = $t->post();
        $result = Array();
        $result['success']=true;
        $result['data']=$task_url;
        return $result;
    }
}