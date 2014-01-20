<?php
/**
 * Created by IntelliJ IDEA.
 * User: Dani
 * Date: 30.11.13
 * Time: 11:01
 * To change this template use File | Settings | File Templates.
 */

/**
 * Enables custom logging for analysis pruposes
 * Class CsAnalysisLog
 */
class CsAnalysisFileLogRoute extends CFileLogRoute {

    /**
     * Custom string that is being logged
     * @param $message
     * @param $level
     * @param $category
     * @param $time
     * @return mixed
     */
    protected function formatLogMessage($message, $level, $category, $time){
//        return @date( 'Y-m-d H:i:s', $time ).'-'.$message;
        return round($time)." - $message\n";
    }
}